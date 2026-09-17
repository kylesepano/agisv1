import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Bell,
  CalendarClock,
  CheckCircle2,
  ClipboardList,
  ExternalLink,
  MessageSquareText,
  RefreshCw,
  ShieldAlert,
  XCircle,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  aemsTeamApi,
  aemsWorkQueueApi,
  notificationApi,
} from "../../services/api";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { useToast } from "../../ui/toast-context";

const tabs = [
  ["tasks", "Tasks", ClipboardList],
  ["review-notes", "Review Notes", MessageSquareText],
  ["due-process", "Due Process", CalendarClock],
  ["escalations", "Escalation Candidates", ShieldAlert],
];

function dateTime(value) {
  if (!value) return "Not scheduled";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function tone(status) {
  if (["COMPLETED", "FINALIZED", "RESOLVED", "ACKNOWLEDGED"].includes(status))
    return "success";
  if (["CANCELLED", "DISMISSED", "VOIDED"].includes(status)) return "inactive";
  if (status === "OVERDUE") return "danger";
  return "warning";
}

function ActionButton({
  children,
  onClick,
  tone: buttonTone = "sky",
  disabled,
}) {
  const colors = {
    sky: "border-sky-300 bg-sky-50 text-sky-800 hover:bg-sky-100",
    green:
      "border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100",
    red: "border-red-300 bg-red-50 text-red-800 hover:bg-red-100",
    slate: "border-slate-300 bg-white text-slate-700 hover:bg-slate-50",
  };
  return (
    <button
      className={`inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-50 ${colors[buttonTone]}`}
      disabled={disabled}
      onClick={onClick}
      type="button"
    >
      {children}
    </button>
  );
}

export default function AemsOperationalQueuesPage() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    searchParams.get("engagementId") || "",
  );
  const [workspace, setWorkspace] = useState(null);
  const [team, setTeam] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [tab, setTab] = useState(searchParams.get("tab") || "tasks");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [taskForm, setTaskForm] = useState({
    taskType: "GENERAL",
    title: "",
    dueAt: "",
    assignedTo: "",
  });

  const canCreateTask = hasPermission(user, "aems.task.create");
  const canCreateNote = hasPermission(user, "aems.review-note.create");
  const canDueProcess = hasPermission(user, "aems.due-process.create");
  const canReviewEscalation = hasPermission(
    user,
    "aems.escalation-candidate.review",
  );

  const loadEngagements = useCallback(async () => {
    try {
      const result = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      const records = result.engagements || [];
      setEngagements(records);
      setSelectedId((current) => current || String(records[0]?.id || ""));
    } catch (reason) {
      setError(reason.message);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!selectedId) {
      setWorkspace(null);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const results = await Promise.allSettled([
        aemsWorkQueueApi.show(selectedId),
        aemsTeamApi.show(selectedId),
        notificationApi.recent(),
      ]);
      if (results[0].status === "rejected") throw results[0].reason;
      setWorkspace(results[0].value);
      setTeam(
        results[1].status === "fulfilled"
          ? results[1].value?.teamMembers || []
          : [],
      );
      const recent = results[2].status === "fulfilled" ? results[2].value : [];
      setNotifications(
        Array.isArray(recent)
          ? recent
              .filter(
                (item) =>
                  item.moduleCode === "AEMS" || item.module_code === "AEMS",
              )
              .slice(0, 6)
          : [],
      );
    } catch (reason) {
      setError(reason.message);
      setWorkspace(null);
    } finally {
      setLoading(false);
    }
  }, [selectedId]);

  useEffect(() => {
    const timer = window.setTimeout(() => void loadEngagements(), 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);
  useEffect(() => {
    const timer = window.setTimeout(() => void loadWorkspace(), 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);
  useEffect(() => {
    if (selectedId)
      setSearchParams({ engagementId: selectedId, tab }, { replace: true });
  }, [selectedId, tab, setSearchParams]);

  const refresh = async () => {
    setBusy(true);
    await loadWorkspace();
    setBusy(false);
  };
  const run = async (action, message) => {
    setBusy(true);
    try {
      await action();
      toast.success(message);
      await loadWorkspace();
    } catch (reason) {
      setError(reason.message);
    } finally {
      setBusy(false);
    }
  };
  const tasks = workspace?.tasks || [];
  const notes = workspace?.reviewNotes || [];
  const dueProcess = workspace?.dueProcess || [];
  const candidates = workspace?.escalationCandidates || [];
  const overdue = tasks.filter((item) => item.dueState === "OVERDUE").length;
  const openTasks = tasks.filter(
    (item) => !["COMPLETED", "CANCELLED"].includes(item.status),
  ).length;
  const pendingNotes = notes.filter((item) => item.status === "DRAFT").length;
  const openCandidates = candidates.filter(
    (item) => !["RESOLVED", "DISMISSED"].includes(item.status),
  ).length;
  const selected = engagements.find(
    (item) => String(item.id) === String(selectedId),
  );
  const teamOptions = useMemo(
    () =>
      team
        .filter((member) => member.isActive !== false && member.user)
        .map((member) => member),
    [team],
  );

  async function createTask(event) {
    event.preventDefault();
    if (!taskForm.title.trim()) return;
    await run(
      () =>
        aemsWorkQueueApi.createTask(selectedId, {
          ...taskForm,
          assignedTo: taskForm.assignedTo || null,
          dueAt: taskForm.dueAt || null,
        }),
      "Task created",
    );
    setTaskForm({ taskType: "GENERAL", title: "", dueAt: "", assignedTo: "" });
  }

  if (!selectedId && !loading)
    return (
      <div className="min-w-0">
        <RegistryHeader
          icon={ClipboardList}
          title="AEMS Operational Work Queues"
          description="Tasks, review notes, due process, escalation candidates, notifications, and protected output links."
        />
        <Empty message="No authorized engagements are available in your scope." />
      </div>
    );

  return (
    <div className="min-w-0" data-testid="aems-operational-queues">
      <RegistryHeader
        icon={ClipboardList}
        title="AEMS Operational Work Queues"
        description="Act on scoped tasks, review notes, due-process exchanges, and reviewable escalation candidates with auditable status transitions."
        actions={
          <>
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={busy}
              onClick={() => void refresh()}
              type="button"
            >
              <RefreshCw className={busy ? "animate-spin" : ""} size={16} />{" "}
              Refresh
            </button>
            <a
              className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
              href="/audit-engagement-management/calendar"
            >
              <CalendarClock size={16} /> Audit Calendar
            </a>
          </>
        }
      />
      {error && (
        <div
          className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800"
          role="alert"
        >
          {error}
        </div>
      )}
      <section className="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
          <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
            Engagement scope
            <select
              className="mt-2 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800"
              value={selectedId}
              onChange={(event) => setSelectedId(event.target.value)}
            >
              <option value="">Select an engagement</option>
              {engagements.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.engagementCode} — {item.title}
                </option>
              ))}
            </select>
          </label>
          {selected && (
            <div className="rounded-lg bg-slate-50 px-4 py-3 text-sm">
              <p className="font-bold text-slate-800">
                {selected.engagementCode}
              </p>
              <p className="mt-1 text-xs text-slate-500">
                {selected.title} · {selected.status}
              </p>
            </div>
          )}
        </div>
      </section>
      {loading && (
        <div className="grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-white">
          <RefreshCw className="animate-spin text-sky-700" size={26} />
        </div>
      )}
      {!loading && workspace && (
        <>
          <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              icon={ClipboardList}
              label="Open tasks"
              value={openTasks}
              tone="sky"
            />
            <SummaryCard
              icon={CalendarClock}
              label="Overdue tasks"
              value={overdue}
              tone="red"
            />
            <SummaryCard
              icon={MessageSquareText}
              label="Draft review notes"
              value={pendingNotes}
              tone="amber"
            />
            <SummaryCard
              icon={ShieldAlert}
              label="Escalation candidates"
              value={openCandidates}
              tone="slate"
            />
          </section>
          <section className="mb-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="min-w-0 rounded-xl border border-slate-200 bg-white shadow-sm">
              <div
                className="flex min-w-0 gap-1 overflow-x-auto border-b border-slate-200 p-2"
                role="tablist"
              >
                {tabs.map(([key, label, Icon]) => (
                  <button
                    aria-selected={tab === key}
                    className={`inline-flex min-w-max items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-bold ${tab === key ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-50"}`}
                    key={key}
                    onClick={() => setTab(key)}
                    role="tab"
                    type="button"
                  >
                    <Icon size={16} /> {label}
                    <span className="rounded-full bg-white/70 px-1.5 text-[11px] text-slate-700">
                      {key === "tasks"
                        ? tasks.length
                        : key === "review-notes"
                          ? notes.length
                          : key === "due-process"
                            ? dueProcess.length
                            : candidates.length}
                    </span>
                  </button>
                ))}
              </div>
              <div className="p-4 sm:p-5">
                {tab === "tasks" && (
                  <TasksPanel
                    tasks={tasks}
                    team={teamOptions}
                    form={taskForm}
                    setForm={setTaskForm}
                    onCreate={createTask}
                    canCreate={canCreateTask}
                    busy={busy}
                    run={run}
                    engagementId={selectedId}
                  />
                )}
                {tab === "review-notes" && (
                  <NotesPanel
                    notes={notes}
                    tasks={tasks}
                    canCreate={canCreateNote}
                    canFinalize={hasPermission(
                      user,
                      "aems.review-note.finalize",
                    )}
                    busy={busy}
                    run={run}
                    engagementId={selectedId}
                  />
                )}
                {tab === "due-process" && (
                  <DueProcessPanel
                    items={dueProcess}
                    canCreate={canDueProcess}
                    busy={busy}
                    run={run}
                    engagementId={selectedId}
                  />
                )}
                {tab === "escalations" && (
                  <EscalationPanel
                    candidates={candidates}
                    canReview={canReviewEscalation}
                    busy={busy}
                    run={run}
                    engagementId={selectedId}
                  />
                )}
              </div>
            </div>
            <NotificationPanel items={notifications} />
          </section>
          <section className="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
            <p className="font-bold">Controlled queue actions</p>
            <p className="mt-1 leading-6">
              Assignments, office scope, role permissions, separation-of-duties,
              immutable revisions, optimistic locks, notifications, activity
              logs, and audit trails are enforced by the backend. This workspace
              only exposes actions returned by those contracts.
            </p>
          </section>
        </>
      )}
    </div>
  );
}

function TasksPanel({
  tasks,
  team,
  form,
  setForm,
  onCreate,
  canCreate,
  busy,
  run,
  engagementId,
}) {
  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-bold text-slate-900">
          Dedicated Tasks workspace
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          Assign owners, set due dates, and record controlled task transitions.
        </p>
      </div>
      {canCreate && (
        <form
          className="grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 md:grid-cols-[10rem_minmax(0,1fr)_10rem_12rem_auto]"
          onSubmit={onCreate}
        >
          <select
            className="h-10 rounded-lg border border-slate-300 bg-white px-2 text-sm"
            value={form.taskType}
            onChange={(e) => setForm({ ...form, taskType: e.target.value })}
          >
            <option>GENERAL</option>
            <option>REVIEW</option>
            <option>FOLLOW_UP</option>
            <option>REPORTING</option>
          </select>
          <input
            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
            placeholder="Task title"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
          />
          <input
            className="h-10 rounded-lg border border-slate-300 px-2 text-sm"
            type="datetime-local"
            value={form.dueAt}
            onChange={(e) => setForm({ ...form, dueAt: e.target.value })}
          />
          <select
            className="h-10 rounded-lg border border-slate-300 bg-white px-2 text-sm"
            value={form.assignedTo}
            onChange={(e) => setForm({ ...form, assignedTo: e.target.value })}
          >
            <option value="">Unassigned</option>
            {team.map((member) => (
              <option key={member.userId} value={member.userId}>
                {member.user?.name || member.userId}
              </option>
            ))}
          </select>
          <button
            className="h-10 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white disabled:opacity-50"
            disabled={busy || !form.title.trim()}
            type="submit"
          >
            Add task
          </button>
        </form>
      )}
      {tasks.length === 0 && (
        <Empty message="No tasks are registered for this engagement." />
      )}
      {tasks.map((task) => (
        <article
          className={`rounded-lg border p-4 ${task.dueState === "OVERDUE" ? "border-red-200 bg-red-50/50" : "border-slate-200 bg-white"}`}
          key={task.id}
        >
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {task.taskCode} · {task.taskType}
              </p>
              <h3 className="mt-1 font-bold text-slate-900">{task.title}</h3>
              <p className="mt-1 text-xs text-slate-500">
                {task.assignedTo?.name || "Unassigned"} · Due{" "}
                {dateTime(task.dueAt)}
              </p>
            </div>
            <StatusBadge
              label={task.dueState === "OVERDUE" ? "Overdue" : task.status}
              tone={tone(task.dueState === "OVERDUE" ? "OVERDUE" : task.status)}
            />
          </div>
          <div className="mt-3 flex flex-wrap gap-2">
            {task.status === "OPEN" && (
              <ActionButton
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      aemsWorkQueueApi.transitionTask(engagementId, task.id, {
                        action: "START",
                        lockVersion: task.lockVersion,
                      }),
                    "Task started",
                  )
                }
              >
                Start
              </ActionButton>
            )}
            {["OPEN", "IN_PROGRESS"].includes(task.status) && (
              <ActionButton
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      aemsWorkQueueApi.transitionTask(engagementId, task.id, {
                        action: "COMPLETE",
                        lockVersion: task.lockVersion,
                      }),
                    "Task completed",
                  )
                }
                tone="green"
              >
                <CheckCircle2 size={14} /> Complete
              </ActionButton>
            )}
            {task.status === "IN_PROGRESS" && (
              <ActionButton
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      aemsWorkQueueApi.transitionTask(engagementId, task.id, {
                        action: "ESCALATE",
                        lockVersion: task.lockVersion,
                      }),
                    "Escalation candidate created",
                  )
                }
                tone="red"
              >
                <ShieldAlert size={14} /> Escalate
              </ActionButton>
            )}
            {["COMPLETED", "CANCELLED"].includes(task.status) && (
              <ActionButton
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      aemsWorkQueueApi.transitionTask(engagementId, task.id, {
                        action: "REOPEN",
                        lockVersion: task.lockVersion,
                      }),
                    "Task reopened",
                  )
                }
                tone="slate"
              >
                Reopen
              </ActionButton>
            )}
          </div>
        </article>
      ))}
    </div>
  );
}

function NotesPanel({
  notes,
  tasks,
  canCreate,
  canFinalize,
  busy,
  run,
  engagementId,
}) {
  const [content, setContent] = useState("");
  const [taskId, setTaskId] = useState("");
  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-bold text-slate-900">
          Dedicated Review Notes workspace
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          Prepare linked review notes; finalization remains independent and
          immutable.
        </p>
      </div>
      {canCreate && (
        <div className="grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 md:grid-cols-[14rem_minmax(0,1fr)_auto]">
          <select
            className="h-10 rounded-lg border border-slate-300 bg-white px-2 text-sm"
            value={taskId}
            onChange={(e) => setTaskId(e.target.value)}
          >
            <option value="">Link to a task</option>
            {tasks.map((task) => (
              <option key={task.id} value={task.id}>
                {task.taskCode} · {task.title}
              </option>
            ))}
          </select>
          <input
            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
            placeholder="Review note content"
            value={content}
            onChange={(e) => setContent(e.target.value)}
          />
          <ActionButton
            disabled={busy || !taskId || content.trim().length < 3}
            onClick={() =>
              void run(async () => {
                await aemsWorkQueueApi.createReviewNote(engagementId, {
                  taskId,
                  content,
                  noteType: "REVIEW",
                });
                setContent("");
                setTaskId("");
              }, "Review note created")
            }
          >
            Add note
          </ActionButton>
        </div>
      )}
      {notes.length === 0 && (
        <Empty message="No review notes are registered for this engagement." />
      )}
      {notes.map((note) => (
        <article
          className="rounded-lg border border-slate-200 p-4"
          key={note.id}
        >
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {note.noteCode} · Version {note.versionNumber}
              </p>
              <p className="mt-1 text-sm leading-6 text-slate-700">
                {note.content}
              </p>
              <p className="mt-1 text-xs text-slate-500">
                Prepared by {note.createdBy?.name || "Unknown"}
              </p>
            </div>
            <StatusBadge label={note.status} tone={tone(note.status)} />
          </div>
          {canFinalize && note.status === "DRAFT" && (
            <div className="mt-3">
              <ActionButton
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      aemsWorkQueueApi.transitionReviewNote(
                        engagementId,
                        note.id,
                        { action: "FINALIZE", lockVersion: note.lockVersion },
                      ),
                    "Review note finalized",
                  )
                }
                tone="green"
              >
                <CheckCircle2 size={14} /> Finalize (independent reviewer)
              </ActionButton>
            </div>
          )}
        </article>
      ))}
    </div>
  );
}

function DueProcessPanel({ items, canCreate, busy, run, engagementId }) {
  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-bold text-slate-900">
          Due-process work queue
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          Every reminder, notice, clarification, extension, late response, and
          escalation is append-only and linked to a finding.
        </p>
      </div>
      {items.length === 0 && (
        <Empty message="No due-process exchanges are pending." />
      )}
      {items.map((item) => (
        <article
          className="rounded-lg border border-slate-200 p-4"
          key={item.id}
        >
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {item.eventCode} · {item.eventType}
              </p>
              <p className="mt-1 text-sm leading-6 text-slate-700">
                {item.content}
              </p>
              <p className="mt-1 text-xs text-slate-500">
                {item.finding?.finding_code || "Finding"} · Due{" "}
                {item.dueDate || "No due date"} · {dateTime(item.recordedAt)}
              </p>
            </div>
            <StatusBadge
              label={item.eventType.replaceAll("_", " ")}
              tone={tone(item.eventType)}
            />
          </div>
          {canCreate && item.finding?.id && (
            <div className="mt-3 flex flex-wrap gap-2">
              <ActionButton
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      aemsWorkQueueApi.recordDueProcess(engagementId, {
                        findingId: item.finding.id,
                        eventType: "REMINDER",
                        content: `Follow-up reminder recorded for ${item.eventCode}.`,
                        dueDate: item.dueDate || null,
                      }),
                    "Due-process reminder recorded",
                  )
                }
              >
                <Bell size={14} /> Record reminder
              </ActionButton>
              <ActionButton
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      aemsWorkQueueApi.recordDueProcess(engagementId, {
                        findingId: item.finding.id,
                        eventType: "CLARIFICATION_REQUESTED",
                        content: `Clarification requested in response to ${item.eventCode}.`,
                      }),
                    "Clarification request recorded",
                  )
                }
                tone="slate"
              >
                <MessageSquareText size={14} /> Request clarification
              </ActionButton>
            </div>
          )}
        </article>
      ))}
    </div>
  );
}

function EscalationPanel({ candidates, canReview, busy, run, engagementId }) {
  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-bold text-slate-900">
          Escalation-candidate queue
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          Candidates are reviewable prompts only; no notice or final
          professional decision is issued automatically.
        </p>
      </div>
      {candidates.length === 0 && (
        <Empty message="No escalation candidates are registered." />
      )}
      {candidates.map((candidate) => (
        <article
          className="rounded-lg border border-slate-200 p-4"
          key={candidate.id}
        >
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {candidate.candidateCode} · {candidate.candidateType}
              </p>
              <p className="mt-1 text-sm leading-6 text-slate-700">
                {candidate.reason}
              </p>
              <p className="mt-1 text-xs text-slate-500">
                Detected {dateTime(candidate.detectedAt)}
                {candidate.dueAt ? ` · Due ${dateTime(candidate.dueAt)}` : ""}
              </p>
            </div>
            <StatusBadge
              label={candidate.status}
              tone={tone(candidate.status)}
            />
          </div>
          {canReview &&
            !["RESOLVED", "DISMISSED"].includes(candidate.status) && (
              <div className="mt-3 flex flex-wrap gap-2">
                <ActionButton
                  disabled={busy}
                  onClick={() =>
                    void run(
                      () =>
                        aemsWorkQueueApi.reviewEscalationCandidate(
                          engagementId,
                          candidate.id,
                          {
                            action: "ACKNOWLEDGE",
                            lockVersion: candidate.lockVersion,
                            comment: "Candidate acknowledged for review.",
                          },
                        ),
                      "Candidate acknowledged",
                    )
                  }
                >
                  <CheckCircle2 size={14} /> Acknowledge
                </ActionButton>
                <ActionButton
                  disabled={busy}
                  onClick={() =>
                    void run(
                      () =>
                        aemsWorkQueueApi.reviewEscalationCandidate(
                          engagementId,
                          candidate.id,
                          {
                            action: "RESOLVE",
                            lockVersion: candidate.lockVersion,
                            comment: "Candidate reviewed and resolved.",
                          },
                        ),
                      "Candidate resolved",
                    )
                  }
                  tone="green"
                >
                  Resolve
                </ActionButton>
                <ActionButton
                  disabled={busy}
                  onClick={() =>
                    void run(
                      () =>
                        aemsWorkQueueApi.reviewEscalationCandidate(
                          engagementId,
                          candidate.id,
                          {
                            action: "DISMISS",
                            lockVersion: candidate.lockVersion,
                            comment: "Candidate reviewed and dismissed.",
                          },
                        ),
                      "Candidate dismissed",
                    )
                  }
                  tone="red"
                >
                  <XCircle size={14} /> Dismiss
                </ActionButton>
              </div>
            )}
        </article>
      ))}
    </div>
  );
}

function NotificationPanel({ items }) {
  return (
    <aside className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-center justify-between gap-2">
        <h2 className="font-bold text-slate-900">
          Notification and overdue indicators
        </h2>
        <Bell size={17} className="text-sky-700" />
      </div>
      <p className="mt-1 text-xs leading-5 text-slate-500">
        AEMS reminders and escalations are delivered through the Core
        notification center.
      </p>
      <div className="mt-4 space-y-3">
        {items.length === 0 && (
          <p className="text-sm text-slate-500">
            No recent AEMS notifications.
          </p>
        )}
        {items.map((item) => (
          <div className="rounded-lg border border-slate-200 p-3" key={item.id}>
            <div className="flex items-start justify-between gap-2">
              <p className="text-sm font-bold text-slate-800">{item.title}</p>
              {!item.readAt && (
                <span className="h-2 w-2 rounded-full bg-red-500" />
              )}
            </div>
            <p className="mt-1 text-xs leading-5 text-slate-500">
              {item.message}
            </p>
          </div>
        ))}
        <a
          className="inline-flex items-center gap-1 text-xs font-bold text-sky-700 hover:underline"
          href="/notifications"
        >
          Open notification center <ExternalLink size={13} />
        </a>
      </div>
    </aside>
  );
}

function Empty({ message }) {
  return (
    <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
      {message}
    </div>
  );
}
