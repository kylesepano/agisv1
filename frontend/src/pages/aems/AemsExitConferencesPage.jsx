import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Ban,
  CalendarCheck2,
  CalendarClock,
  CheckCircle2,
  Download,
  FileText,
  MapPin,
  Pencil,
  Plus,
  RefreshCw,
  ShieldCheck,
  Upload,
  UsersRound,
  Video,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import {
  aemsExitConferenceApi,
  ApiError,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textAreaClass = `${inputClass} min-h-24 resize-y py-2.5`;
const editableStatuses = new Set(["SCHEDULED", "RESCHEDULED"]);

const emptySchedule = {
  scheduledStartAt: "",
  scheduledEndAt: "",
  venue: "",
  meetingLink: "",
  onlineMeetingDetails: "",
  agenda: "",
  findingIds: [],
  participants: [
    {
      mode: "internal",
      userId: "",
      officeId: "",
      externalName: "",
      externalEmail: "",
      participantRole: "AUDITEE_REPRESENTATIVE",
    },
  ],
};

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function dateTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function shortDate(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", { dateStyle: "medium" }).format(
    new Date(`${value}T00:00:00`),
  );
}

function bytes(value) {
  const size = Number(value || 0);
  if (size < 1024) return `${size} B`;
  if (size < 1024 ** 2) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 ** 2).toFixed(1)} MB`;
}

function localDateTime(value) {
  if (!value) return "";
  const instance = new Date(value);
  const offset = instance.getTimezoneOffset() * 60_000;
  return new Date(instance.getTime() - offset).toISOString().slice(0, 16);
}

function Field({ title, error, children, wide = false }) {
  return (
    <label
      className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}
    >
      {title}
      <span className="mt-1.5 block">{children}</span>
      {error && <small className="mt-1 block text-red-600">{error[0]}</small>}
    </label>
  );
}

function ActionButton({ children, onClick, tone = "sky", disabled = false }) {
  const tones = {
    sky: "border-sky-300 text-sky-700 hover:bg-sky-50",
    green: "border-emerald-300 text-emerald-700 hover:bg-emerald-50",
    red: "border-red-300 text-red-700 hover:bg-red-50",
    amber: "border-amber-300 text-amber-700 hover:bg-amber-50",
  };
  return (
    <button
      className={`inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border bg-white px-3 text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-50 ${tones[tone]}`}
      disabled={disabled}
      onClick={onClick}
      type="button"
    >
      {children}
    </button>
  );
}

function statusTone(status) {
  return {
    SCHEDULED: "info",
    RESCHEDULED: "warning",
    COMPLETED: "success",
    CANCELLED: "danger",
    WAIVED: "inactive",
  }[status] ?? "inactive";
}

/** Dedicated AEMS workspace for conference scheduling, minutes, and acknowledgement. */
export default function AemsExitConferencesPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(
    params.get("engagementId") ?? "",
  );
  const [workspace, setWorkspace] = useState(null);
  const [selectedId, setSelectedId] = useState("");
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [scheduleOpen, setScheduleOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [schedule, setSchedule] = useState(emptySchedule);
  const [completionOpen, setCompletionOpen] = useState(false);
  const [completion, setCompletion] = useState(null);
  const [attachmentOpen, setAttachmentOpen] = useState(false);
  const [attachment, setAttachment] = useState({
    file: null,
    category: "SUPPORTING",
    caption: "",
  });
  const [transitionOpen, setTransitionOpen] = useState(false);
  const [transition, setTransition] = useState({
    action: "CANCEL",
    reason: "",
  });
  const [ackOpen, setAckOpen] = useState(false);
  const [acknowledgement, setAcknowledgement] = useState({
    status: "ACKNOWLEDGED",
    comment: "",
  });

  const canManage = hasPermission(user, "aems.conference.manage");
  const canAcknowledge = hasPermission(user, "aems.conference.acknowledge");
  const exitStageOpen = workspace?.engagement?.status === "EXIT_CONFERENCE";

  const loadEngagements = useCallback(async () => {
    setLoading(true);
    try {
      const records = await aemsExitConferenceApi.engagements();
      setEngagements(records);
      setEngagementId((current) => current || String(records[0]?.id ?? ""));
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!engagementId) {
      setWorkspace(null);
      return;
    }
    setLoading(true);
    try {
      const data = await aemsExitConferenceApi.show(engagementId);
      setWorkspace(data);
      setSelectedId((current) => {
        const exists = data.conferences.some(
          (conference) => String(conference.id) === String(current),
        );
        return exists ? current : String(data.conferences[0]?.id ?? "");
      });
      setError("");
    } catch (requestError) {
      setError(requestError.message);
      setWorkspace(null);
    } finally {
      setLoading(false);
    }
  }, [engagementId]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  useEffect(() => {
    const timer = window.setTimeout(loadWorkspace, 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);

  useEffect(() => {
    if (engagementId) {
      setParams({ engagementId }, { replace: true });
    }
  }, [engagementId, setParams]);

  const conferences = useMemo(
    () => workspace?.conferences ?? [],
    [workspace?.conferences],
  );
  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return conferences;
    return conferences.filter((conference) =>
      [
        conference.conferenceCode,
        conference.venue,
        conference.agenda,
        conference.status,
      ].some((value) => String(value ?? "").toLowerCase().includes(needle)),
    );
  }, [conferences, query]);
  const selected =
    conferences.find(
      (conference) => String(conference.id) === String(selectedId),
    ) ?? null;
  const ownAcknowledgement = selected?.acknowledgements?.find(
    (record) => Number(record.actor?.id) === Number(user?.id),
  );

  function showError(requestError) {
    if (requestError instanceof ApiError) {
      setErrors(requestError.errors ?? {});
    }
    toast.error(requestError.message);
  }

  async function refresh(message) {
    await loadWorkspace();
    if (message) toast.success(message);
  }

  function openSchedule(conference = null) {
    setErrors({});
    setEditing(conference);
    if (!conference) {
      setSchedule(emptySchedule);
    } else {
      setSchedule({
        scheduledStartAt: localDateTime(conference.scheduledStartAt),
        scheduledEndAt: localDateTime(conference.scheduledEndAt),
        venue: conference.venue ?? "",
        meetingLink: conference.meetingLink ?? "",
        onlineMeetingDetails: conference.onlineMeetingDetails ?? "",
        agenda: conference.agenda ?? "",
        findingIds: conference.findings.map((finding) => finding.id),
        participants: conference.participants.map((participant) => ({
          mode: participant.userId ? "internal" : "external",
          userId: participant.userId ?? "",
          officeId: participant.officeId ?? "",
          externalName: participant.externalName ?? "",
          externalEmail: participant.externalEmail ?? "",
          participantRole: participant.participantRole,
        })),
      });
    }
    setScheduleOpen(true);
  }

  function updateParticipant(index, changes) {
    setSchedule((current) => ({
      ...current,
      participants: current.participants.map((participant, position) =>
        position === index ? { ...participant, ...changes } : participant,
      ),
    }));
  }

  async function saveSchedule() {
    setSaving(true);
    setErrors({});
    try {
      const payload = {
        ...schedule,
        scheduledStartAt: new Date(schedule.scheduledStartAt).toISOString(),
        scheduledEndAt: schedule.scheduledEndAt
          ? new Date(schedule.scheduledEndAt).toISOString()
          : null,
        participants: schedule.participants.map((participant) => ({
          userId:
            participant.mode === "internal"
              ? Number(participant.userId) || null
              : null,
          officeId: Number(participant.officeId) || null,
          externalName:
            participant.mode === "external" ? participant.externalName : null,
          externalEmail:
            participant.mode === "external" ? participant.externalEmail : null,
          participantRole: participant.participantRole,
        })),
        ...(editing ? { lockVersion: editing.lockVersion } : {}),
      };
      const conference = editing
        ? await aemsExitConferenceApi.update(
            engagementId,
            editing.id,
            payload,
          )
        : await aemsExitConferenceApi.create(engagementId, payload);
      setScheduleOpen(false);
      setSelectedId(String(conference.id));
      await refresh(editing ? "Conference updated." : "Conference scheduled.");
    } catch (requestError) {
      showError(requestError);
    } finally {
      setSaving(false);
    }
  }

  function openCompletion() {
    setErrors({});
    setCompletion({
      discussionSummary: selected.discussionSummary ?? "",
      minutes: selected.minutes ?? "",
      agreements: selected.agreements ?? "",
      disagreements: selected.disagreements ?? "",
      participantAttendance: selected.participants.map((participant) => ({
        participantId: participant.id,
        attendanceStatus:
          participant.attendanceStatus === "INVITED"
            ? "ATTENDED"
            : participant.attendanceStatus,
        attendanceNotes: participant.attendanceNotes ?? "",
      })),
      findingDiscussions: selected.findings.map((finding) => ({
        findingId: finding.id,
        discussionStatus:
          finding.discussion?.discussionStatus === "PENDING"
            ? "DISCUSSED"
            : finding.discussion?.discussionStatus,
        agreementStatus: finding.discussion?.agreementStatus ?? "AGREED",
        discussionNotes: finding.discussion?.discussionNotes ?? "",
        agreementDetails: finding.discussion?.agreementDetails ?? "",
        disagreementDetails: finding.discussion?.disagreementDetails ?? "",
        revisedTargetDate: finding.discussion?.revisedTargetDate ?? "",
      })),
    });
    setCompletionOpen(true);
  }

  async function completeConference() {
    setSaving(true);
    setErrors({});
    try {
      await aemsExitConferenceApi.complete(engagementId, selected.id, {
        ...completion,
        lockVersion: selected.lockVersion,
      });
      setCompletionOpen(false);
      await refresh("Conference completed and minutes locked.");
    } catch (requestError) {
      showError(requestError);
    } finally {
      setSaving(false);
    }
  }

  async function uploadAttachment() {
    if (!attachment.file) {
      setErrors({ file: ["Select a file to upload."] });
      return;
    }
    setSaving(true);
    setErrors({});
    try {
      await aemsExitConferenceApi.uploadAttachment(
        engagementId,
        selected.id,
        {
          ...attachment,
          lockVersion: selected.lockVersion,
        },
      );
      setAttachmentOpen(false);
      setAttachment({ file: null, category: "SUPPORTING", caption: "" });
      await refresh("Conference document uploaded.");
    } catch (requestError) {
      showError(requestError);
    } finally {
      setSaving(false);
    }
  }

  async function closeConference() {
    setSaving(true);
    setErrors({});
    try {
      await aemsExitConferenceApi.transition(engagementId, selected.id, {
        ...transition,
        lockVersion: selected.lockVersion,
      });
      setTransitionOpen(false);
      await refresh(
        transition.action === "WAIVE"
          ? "Conference formally waived."
          : "Conference cancelled.",
      );
    } catch (requestError) {
      showError(requestError);
    } finally {
      setSaving(false);
    }
  }

  async function acknowledge() {
    setSaving(true);
    setErrors({});
    try {
      await aemsExitConferenceApi.acknowledge(
        engagementId,
        selected.id,
        {
          ...acknowledgement,
          lockVersion: selected.lockVersion,
        },
      );
      setAckOpen(false);
      await refresh("Conference minutes acknowledged.");
    } catch (requestError) {
      showError(requestError);
    } finally {
      setSaving(false);
    }
  }

  const summary = {
    total: conferences.length,
    scheduled: conferences.filter((item) =>
      editableStatuses.has(item.status),
    ).length,
    completed: conferences.filter((item) => item.status === "COMPLETED").length,
    acknowledged: conferences.filter(
      (item) => item.acknowledgements.length > 0,
    ).length,
  };

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        description="Schedule conferences, record attendance and finding outcomes, lock minutes, and collect auditee acknowledgement."
        icon={CalendarCheck2}
        title="Exit Conferences"
        actions={
          canManage ? (
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
              disabled={!engagementId || !exitStageOpen}
              onClick={() => openSchedule()}
              type="button"
            >
              <Plus size={17} /> Schedule conference
            </button>
          ) : null
        }
      />

      {workspace && !exitStageOpen && (
        <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
          Exit Conference scheduling is locked. Complete Fieldwork and Issues
          &amp; AFR, then move the engagement to the Exit Conference stage from
          the lifecycle workspace before scheduling.
        </div>
      )}

      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard icon={CalendarCheck2} label="Conference records" value={summary.total} tone="sky" />
        <SummaryCard icon={CalendarClock} label="Scheduled" value={summary.scheduled} tone="amber" />
        <SummaryCard icon={CheckCircle2} label="Completed" value={summary.completed} tone="emerald" />
        <SummaryCard icon={ShieldCheck} label="Acknowledged" value={summary.acknowledged} tone="sky" />
      </section>

      <section className="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(18rem,.7fr)_minmax(18rem,1.3fr)_auto]">
        <SearchableSelect
          onChange={(value) => setEngagementId(String(value ?? ""))}
          options={engagements.map((engagement) => ({
            value: engagement.id,
            label: `${engagement.engagementCode} — ${engagement.title}`,
          }))}
          placeholder="Select engagement"
          value={engagementId}
        />
        <input
          className={inputClass}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Search conference code, venue, agenda, or status"
          value={query}
        />
        <ActionButton onClick={loadWorkspace}>
          <RefreshCw size={15} /> Refresh
        </ActionButton>
      </section>

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      {!engagementId ? (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
          No conference-enabled engagement is available.
        </div>
      ) : loading && !workspace ? (
        <div className="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
          Loading Exit Conference workspace…
        </div>
      ) : (
        <div className="grid min-w-0 gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
          <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="border-b border-slate-200 px-4 py-3">
              <h2 className="font-bold text-slate-800">Conference records</h2>
              <p className="text-xs text-slate-500">
                {filtered.length} visible record{filtered.length === 1 ? "" : "s"}
              </p>
            </header>
            <div className="max-h-[68vh] divide-y divide-slate-100 overflow-y-auto">
              {filtered.map((conference) => (
                <button
                  className={`w-full p-4 text-left transition hover:bg-slate-50 ${
                    String(conference.id) === String(selectedId)
                      ? "bg-sky-50"
                      : "bg-white"
                  }`}
                  key={conference.id}
                  onClick={() => setSelectedId(String(conference.id))}
                  type="button"
                >
                  <div className="flex items-start justify-between gap-3">
                    <p className="text-sm font-bold text-slate-800">
                      {conference.conferenceCode}
                    </p>
                    <StatusBadge
                      label={label(conference.status)}
                      tone={statusTone(conference.status)}
                    />
                  </div>
                  <p className="mt-2 text-xs font-semibold text-slate-600">
                    {dateTime(conference.scheduledStartAt)}
                  </p>
                  <p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">
                    {conference.venue || conference.onlineMeetingDetails || "Online conference"}
                  </p>
                  <p className="mt-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                    {conference.findings.length} finding
                    {conference.findings.length === 1 ? "" : "s"} ·{" "}
                    {conference.participants.length} participant
                    {conference.participants.length === 1 ? "" : "s"}
                  </p>
                </button>
              ))}
              {!filtered.length && (
                <p className="p-8 text-center text-sm text-slate-500">
                  No Exit Conference records match this view.
                </p>
              )}
            </div>
          </section>

          {selected ? (
            <ConferenceDetail
              canAcknowledge={canAcknowledge}
              canManage={canManage && exitStageOpen}
              conference={selected}
              engagementId={engagementId}
              onAcknowledge={() => {
                setErrors({});
                setAckOpen(true);
              }}
              onComplete={openCompletion}
              onDownload={(file) =>
                aemsExitConferenceApi
                  .downloadAttachment(engagementId, selected.id, file)
                  .catch(showError)
              }
              onEdit={() => openSchedule(selected)}
              onTransition={(action) => {
                setErrors({});
                setTransition({ action, reason: "" });
                setTransitionOpen(true);
              }}
              onUpload={() => {
                setErrors({});
                setAttachmentOpen(true);
              }}
              ownAcknowledgement={ownAcknowledgement}
            />
          ) : (
            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
              Select a conference to review its schedule, participants, findings,
              minutes, and acknowledgements.
            </div>
          )}
        </div>
      )}

      <ScheduleModal
        errors={errors}
        form={schedule}
        onChange={setSchedule}
        onClose={() => setScheduleOpen(false)}
        onParticipant={updateParticipant}
        onSave={saveSchedule}
        open={scheduleOpen}
        references={workspace?.references}
        saving={saving}
        editing={Boolean(editing)}
      />

      <CompletionModal
        conference={selected}
        errors={errors}
        form={completion}
        onChange={setCompletion}
        onClose={() => setCompletionOpen(false)}
        onSave={completeConference}
        open={completionOpen}
        saving={saving}
      />

      <Modal
        footer={
          <>
            <ActionButton onClick={() => setAttachmentOpen(false)}>Cancel</ActionButton>
            <ActionButton disabled={saving} onClick={uploadAttachment} tone="green">
              <Upload size={15} /> Upload document
            </ActionButton>
          </>
        }
        onClose={() => setAttachmentOpen(false)}
        open={attachmentOpen}
        title="Attach conference document"
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <Field title="Document category">
            <select
              className={inputClass}
              onChange={(event) =>
                setAttachment((current) => ({
                  ...current,
                  category: event.target.value,
                }))
              }
              value={attachment.category}
            >
              <option value="SUPPORTING">Supporting document</option>
              <option value="MINUTES">Minutes file</option>
            </select>
          </Field>
          <Field error={errors.file} title="File">
            <input
              className={`${inputClass} py-2`}
              onChange={(event) =>
                setAttachment((current) => ({
                  ...current,
                  file: event.target.files?.[0] ?? null,
                }))
              }
              type="file"
            />
          </Field>
          <Field title="Caption" wide>
            <input
              className={inputClass}
              onChange={(event) =>
                setAttachment((current) => ({
                  ...current,
                  caption: event.target.value,
                }))
              }
              value={attachment.caption}
            />
          </Field>
        </div>
      </Modal>

      <Modal
        footer={
          <>
            <ActionButton onClick={() => setTransitionOpen(false)}>Cancel</ActionButton>
            <ActionButton
              disabled={saving}
              onClick={closeConference}
              tone={transition.action === "CANCEL" ? "red" : "amber"}
            >
              Confirm {transition.action === "CANCEL" ? "cancellation" : "waiver"}
            </ActionButton>
          </>
        }
        onClose={() => setTransitionOpen(false)}
        open={transitionOpen}
        title={transition.action === "CANCEL" ? "Cancel conference" : "Waive conference"}
      >
        <Field error={errors.reason} title="Documented reason">
          <textarea
            className={textAreaClass}
            onChange={(event) =>
              setTransition((current) => ({
                ...current,
                reason: event.target.value,
              }))
            }
            value={transition.reason}
          />
        </Field>
      </Modal>

      <Modal
        footer={
          <>
            <ActionButton onClick={() => setAckOpen(false)}>Cancel</ActionButton>
            <ActionButton disabled={saving} onClick={acknowledge} tone="green">
              <ShieldCheck size={15} /> Record acknowledgement
            </ActionButton>
          </>
        }
        onClose={() => setAckOpen(false)}
        open={ackOpen}
        title="Acknowledge conference minutes"
      >
        <div className="space-y-4">
          <Field title="Acknowledgement">
            <select
              className={inputClass}
              onChange={(event) =>
                setAcknowledgement((current) => ({
                  ...current,
                  status: event.target.value,
                }))
              }
              value={acknowledgement.status}
            >
              <option value="ACKNOWLEDGED">Acknowledged</option>
              <option value="WITH_RESERVATIONS">Acknowledged with reservations</option>
            </select>
          </Field>
          <Field title="Comment or reservation">
            <textarea
              className={textAreaClass}
              onChange={(event) =>
                setAcknowledgement((current) => ({
                  ...current,
                  comment: event.target.value,
                }))
              }
              value={acknowledgement.comment}
            />
          </Field>
        </div>
      </Modal>
    </main>
  );
}

function ConferenceDetail({
  conference,
  canManage,
  canAcknowledge,
  ownAcknowledgement,
  onEdit,
  onComplete,
  onUpload,
  onTransition,
  onAcknowledge,
  onDownload,
}) {
  const editable = editableStatuses.has(conference.status);
  return (
    <section className="min-w-0 space-y-5">
      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="text-xl font-bold text-slate-800">
                {conference.conferenceCode}
              </h2>
              <StatusBadge
                label={label(conference.status)}
                tone={statusTone(conference.status)}
              />
            </div>
            <p className="mt-1 text-sm text-slate-500">
              Created by {conference.createdBy?.name ?? "Unknown"} ·{" "}
              {dateTime(conference.createdAt)}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {canManage && editable && (
              <>
                <ActionButton onClick={onEdit}><Pencil size={15} /> Edit</ActionButton>
                <ActionButton onClick={onUpload}><Upload size={15} /> Attach</ActionButton>
                <ActionButton onClick={onComplete} tone="green">
                  <CheckCircle2 size={15} /> Complete
                </ActionButton>
                <ActionButton onClick={() => onTransition("WAIVE")} tone="amber">
                  Waive
                </ActionButton>
                <ActionButton onClick={() => onTransition("CANCEL")} tone="red">
                  <Ban size={15} /> Cancel
                </ActionButton>
              </>
            )}
            {canAcknowledge &&
              conference.status === "COMPLETED" &&
              !ownAcknowledgement && (
                <ActionButton onClick={onAcknowledge} tone="green">
                  <ShieldCheck size={15} /> Acknowledge minutes
                </ActionButton>
              )}
          </div>
        </div>

        <div className="mt-5 grid gap-4 md:grid-cols-2">
          <Detail icon={CalendarClock} title="Schedule">
            {dateTime(conference.scheduledStartAt)}
            {conference.scheduledEndAt && ` to ${dateTime(conference.scheduledEndAt)}`}
          </Detail>
          <Detail icon={MapPin} title="Venue">
            {conference.venue || "No physical venue"}
          </Detail>
          <Detail icon={Video} title="Online meeting">
            {conference.meetingLink ? (
              <a
                className="font-semibold text-sky-700 hover:underline"
                href={conference.meetingLink}
                rel="noreferrer"
                target="_blank"
              >
                Open meeting link
              </a>
            ) : (
              "No meeting link"
            )}
            {conference.onlineMeetingDetails && (
              <p className="mt-1 whitespace-pre-wrap">{conference.onlineMeetingDetails}</p>
            )}
          </Detail>
          <Detail icon={FileText} title="Agenda">
            <p className="whitespace-pre-wrap">{conference.agenda}</p>
          </Detail>
        </div>
        {(conference.waiverReason || conference.cancellationReason) && (
          <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            {conference.waiverReason || conference.cancellationReason}
          </div>
        )}
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 className="flex items-center gap-2 font-bold text-slate-800">
          <UsersRound size={18} /> Participants and attendance
        </h3>
        <div className="mt-4 overflow-x-auto">
          <table className="w-full min-w-[40rem] text-left text-sm">
            <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th className="pb-2">Participant</th>
                <th className="pb-2">Office</th>
                <th className="pb-2">Role</th>
                <th className="pb-2">Attendance</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {conference.participants.map((participant) => (
                <tr key={participant.id}>
                  <td className="py-3 font-semibold text-slate-700">
                    {participant.user?.name || participant.externalName}
                    {participant.externalEmail && (
                      <span className="block text-xs font-normal text-slate-400">
                        {participant.externalEmail}
                      </span>
                    )}
                  </td>
                  <td className="py-3 text-slate-600">
                    {participant.office?.acronym || participant.office?.name || "External"}
                  </td>
                  <td className="py-3 text-slate-600">{label(participant.participantRole)}</td>
                  <td className="py-3">
                    <StatusBadge
                      label={label(participant.attendanceStatus)}
                      tone={participant.attendanceStatus === "ATTENDED" ? "success" : "inactive"}
                    />
                    {participant.attendanceNotes && (
                      <p className="mt-1 text-xs text-slate-500">{participant.attendanceNotes}</p>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 className="font-bold text-slate-800">Findings discussed</h3>
        <div className="mt-4 space-y-3">
          {conference.findings.map((finding) => (
            <div className="rounded-lg border border-slate-200 p-4" key={finding.id}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="text-xs font-bold text-sky-700">{finding.findingCode}</p>
                  <h4 className="mt-1 font-bold text-slate-800">{finding.title}</h4>
                  <p className="mt-1 text-xs text-slate-500">
                    {finding.responsibleOffice?.name}
                  </p>
                </div>
                {finding.discussion?.agreementStatus && (
                  <StatusBadge
                    label={label(finding.discussion.agreementStatus)}
                    tone={
                      finding.discussion.agreementStatus === "AGREED"
                        ? "success"
                        : "warning"
                    }
                  />
                )}
              </div>
              {finding.discussion?.discussionNotes && (
                <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-600">
                  {finding.discussion.discussionNotes}
                </p>
              )}
              {finding.discussion?.agreementDetails && (
                <p className="mt-2 text-sm text-emerald-700">
                  Agreement: {finding.discussion.agreementDetails}
                </p>
              )}
              {finding.discussion?.disagreementDetails && (
                <p className="mt-2 text-sm text-red-700">
                  Disagreement: {finding.discussion.disagreementDetails}
                </p>
              )}
              {finding.discussion?.revisedTargetDate && (
                <p className="mt-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                  Revised target: {shortDate(finding.discussion.revisedTargetDate)}
                </p>
              )}
            </div>
          ))}
        </div>
      </div>

      {conference.status === "COMPLETED" && (
        <div className="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
          <h3 className="font-bold text-slate-800">Locked conference record</h3>
          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            {[
              ["Discussion summary", conference.discussionSummary],
              ["Minutes", conference.minutes],
              ["Agreements", conference.agreements],
              ["Disagreements", conference.disagreements],
            ].map(([title, value]) => (
              <div key={title}>
                <h4 className="text-xs font-bold uppercase tracking-wide text-slate-400">
                  {title}
                </h4>
                <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                  {value || "None recorded."}
                </p>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="grid gap-5 lg:grid-cols-2">
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="font-bold text-slate-800">Attachments</h3>
          <div className="mt-3 space-y-2">
            {conference.attachments.map((file) => (
              <button
                className="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-left hover:bg-slate-50"
                key={file.id}
                onClick={() => onDownload(file)}
                type="button"
              >
                <span className="min-w-0">
                  <span className="block truncate text-sm font-bold text-slate-700">
                    {file.fileName}
                  </span>
                  <span className="block text-xs text-slate-400">
                    {label(file.category)} · v{file.fileVersionNumber} · {bytes(file.fileSize)}
                  </span>
                </span>
                <Download className="shrink-0 text-sky-700" size={17} />
              </button>
            ))}
            {!conference.attachments.length && (
              <p className="text-sm text-slate-500">No documents attached.</p>
            )}
          </div>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="font-bold text-slate-800">Auditee acknowledgements</h3>
          <div className="mt-3 space-y-3">
            {conference.acknowledgements.map((record) => (
              <div className="rounded-lg bg-emerald-50 p-3" key={record.id}>
                <div className="flex items-center justify-between gap-3">
                  <p className="text-sm font-bold text-emerald-900">{record.actor?.name}</p>
                  <StatusBadge label={label(record.status)} tone="success" />
                </div>
                <p className="mt-1 text-xs text-emerald-700">
                  {record.office?.name} · {dateTime(record.acknowledgedAt)} · version {record.versionNumber}
                </p>
                {record.comment && (
                  <p className="mt-2 text-sm leading-6 text-emerald-900">{record.comment}</p>
                )}
              </div>
            ))}
            {!conference.acknowledgements.length && (
              <p className="text-sm text-slate-500">
                No Auditee Representative has acknowledged the minutes yet.
              </p>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}

function Detail({ icon: Icon, title, children }) {
  return (
    <div className="rounded-lg bg-slate-50 p-3 text-sm leading-6 text-slate-600">
      <h3 className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-400">
        <Icon size={15} /> {title}
      </h3>
      <div className="mt-1">{children}</div>
    </div>
  );
}

function ScheduleModal({
  open,
  editing,
  form,
  references,
  errors,
  saving,
  onChange,
  onParticipant,
  onClose,
  onSave,
}) {
  const users = references?.users ?? [];
  const offices = references?.offices ?? [];
  const findings = references?.findings ?? [];
  return (
    <Modal
      description="Select formally communicated findings and invite internal or external participants."
      footer={
        <>
          <ActionButton onClick={onClose}>Cancel</ActionButton>
          <ActionButton disabled={saving} onClick={onSave} tone="green">
            {editing ? "Save conference" : "Schedule conference"}
          </ActionButton>
        </>
      }
      onClose={onClose}
      open={open}
      size="xl"
      title={editing ? "Edit Exit Conference" : "Schedule Exit Conference"}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field error={errors.scheduledStartAt} title="Start date and time">
          <input
            className={inputClass}
            onChange={(event) =>
              onChange((current) => ({
                ...current,
                scheduledStartAt: event.target.value,
              }))
            }
            type="datetime-local"
            value={form.scheduledStartAt}
          />
        </Field>
        <Field error={errors.scheduledEndAt} title="End date and time">
          <input
            className={inputClass}
            onChange={(event) =>
              onChange((current) => ({
                ...current,
                scheduledEndAt: event.target.value,
              }))
            }
            type="datetime-local"
            value={form.scheduledEndAt}
          />
        </Field>
        <Field error={errors.venue} title="Venue">
          <input
            className={inputClass}
            onChange={(event) =>
              onChange((current) => ({ ...current, venue: event.target.value }))
            }
            value={form.venue}
          />
        </Field>
        <Field error={errors.meetingLink} title="Online meeting link">
          <input
            className={inputClass}
            onChange={(event) =>
              onChange((current) => ({
                ...current,
                meetingLink: event.target.value,
              }))
            }
            placeholder="https://"
            value={form.meetingLink}
          />
        </Field>
        <Field title="Online meeting details" wide>
          <textarea
            className={textAreaClass}
            onChange={(event) =>
              onChange((current) => ({
                ...current,
                onlineMeetingDetails: event.target.value,
              }))
            }
            value={form.onlineMeetingDetails}
          />
        </Field>
        <Field error={errors.agenda} title="Agenda" wide>
          <textarea
            className={textAreaClass}
            onChange={(event) =>
              onChange((current) => ({ ...current, agenda: event.target.value }))
            }
            value={form.agenda}
          />
        </Field>
      </div>

      <section className="mt-6">
        <h3 className="font-bold text-slate-800">Findings to discuss</h3>
        <div className="mt-3 grid gap-2 md:grid-cols-2">
          {findings.map((finding) => (
            <label
              className="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50"
              key={finding.id}
            >
              <input
                checked={form.findingIds.includes(finding.id)}
                className="mt-1"
                onChange={(event) =>
                  onChange((current) => ({
                    ...current,
                    findingIds: event.target.checked
                      ? [...current.findingIds, finding.id]
                      : current.findingIds.filter((id) => id !== finding.id),
                  }))
                }
                type="checkbox"
              />
              <span>
                <span className="block text-xs font-bold text-sky-700">{finding.findingCode}</span>
                <span className="mt-0.5 block text-sm font-semibold text-slate-700">{finding.title}</span>
                <span className="mt-1 block text-xs text-slate-400">{finding.responsibleOffice?.name}</span>
              </span>
            </label>
          ))}
        </div>
        {errors.findingIds && <p className="mt-2 text-xs text-red-600">{errors.findingIds[0]}</p>}
      </section>

      <section className="mt-6">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-bold text-slate-800">Participants</h3>
          <ActionButton
            onClick={() =>
              onChange((current) => ({
                ...current,
                participants: [
                  ...current.participants,
                  {
                    mode: "internal",
                    userId: "",
                    officeId: "",
                    externalName: "",
                    externalEmail: "",
                    participantRole: "AUDITEE_REPRESENTATIVE",
                  },
                ],
              }))
            }
          >
            <Plus size={14} /> Add participant
          </ActionButton>
        </div>
        <div className="mt-3 space-y-3">
          {form.participants.map((participant, index) => (
            <div className="grid gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-4" key={`${index}-${participant.mode}`}>
              <select
                className={inputClass}
                onChange={(event) =>
                  onParticipant(index, {
                    mode: event.target.value,
                    userId: "",
                    externalName: "",
                  })
                }
                value={participant.mode}
              >
                <option value="internal">Internal user</option>
                <option value="external">External participant</option>
              </select>
              {participant.mode === "internal" ? (
                <select
                  className={inputClass}
                  onChange={(event) => {
                    const selectedUser = users.find(
                      (member) => String(member.id) === event.target.value,
                    );
                    onParticipant(index, {
                      userId: event.target.value,
                      officeId: selectedUser?.officeId ?? "",
                    });
                  }}
                  value={participant.userId}
                >
                  <option value="">Select user</option>
                  {users.map((member) => (
                    <option key={member.id} value={member.id}>{member.name}</option>
                  ))}
                </select>
              ) : (
                <input
                  className={inputClass}
                  onChange={(event) =>
                    onParticipant(index, { externalName: event.target.value })
                  }
                  placeholder="External participant name"
                  value={participant.externalName}
                />
              )}
              {participant.mode === "external" ? (
                <input
                  className={inputClass}
                  onChange={(event) =>
                    onParticipant(index, { externalEmail: event.target.value })
                  }
                  placeholder="Email (optional)"
                  type="email"
                  value={participant.externalEmail}
                />
              ) : (
                <select
                  className={inputClass}
                  onChange={(event) =>
                    onParticipant(index, { officeId: event.target.value })
                  }
                  value={participant.officeId}
                >
                  <option value="">No office</option>
                  {offices.map((office) => (
                    <option key={office.id} value={office.id}>{office.name}</option>
                  ))}
                </select>
              )}
              <div className="flex gap-2">
                <input
                  className={inputClass}
                  onChange={(event) =>
                    onParticipant(index, { participantRole: event.target.value })
                  }
                  placeholder="Participant role"
                  value={participant.participantRole}
                />
                <button
                  aria-label="Remove participant"
                  className="rounded-lg border border-red-200 px-3 font-bold text-red-600 hover:bg-red-50"
                  disabled={form.participants.length === 1}
                  onClick={() =>
                    onChange((current) => ({
                      ...current,
                      participants: current.participants.filter(
                        (_, position) => position !== index,
                      ),
                    }))
                  }
                  type="button"
                >
                  ×
                </button>
              </div>
            </div>
          ))}
        </div>
      </section>
    </Modal>
  );
}

function CompletionModal({
  open,
  conference,
  form,
  errors,
  saving,
  onChange,
  onClose,
  onSave,
}) {
  if (!form || !conference) return null;
  function updateAttendance(index, changes) {
    onChange((current) => ({
      ...current,
      participantAttendance: current.participantAttendance.map((record, position) =>
        position === index ? { ...record, ...changes } : record,
      ),
    }));
  }
  function updateDiscussion(index, changes) {
    onChange((current) => ({
      ...current,
      findingDiscussions: current.findingDiscussions.map((record, position) =>
        position === index ? { ...record, ...changes } : record,
      ),
    }));
  }
  return (
    <Modal
      description="Completion locks the schedule, attendance, finding outcomes, dates, minutes, and attachment references."
      footer={
        <>
          <ActionButton onClick={onClose}>Cancel</ActionButton>
          <ActionButton disabled={saving} onClick={onSave} tone="green">
            <CheckCircle2 size={15} /> Complete and lock
          </ActionButton>
        </>
      }
      onClose={onClose}
      open={open}
      size="xl"
      title="Complete Exit Conference"
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field error={errors.discussionSummary} title="Discussion summary" wide>
          <textarea className={textAreaClass} onChange={(event) => onChange((current) => ({ ...current, discussionSummary: event.target.value }))} value={form.discussionSummary} />
        </Field>
        <Field error={errors.minutes} title="Minutes" wide>
          <textarea className={`${textAreaClass} min-h-40`} onChange={(event) => onChange((current) => ({ ...current, minutes: event.target.value }))} value={form.minutes} />
        </Field>
        <Field title="Overall agreements">
          <textarea className={textAreaClass} onChange={(event) => onChange((current) => ({ ...current, agreements: event.target.value }))} value={form.agreements} />
        </Field>
        <Field title="Overall disagreements">
          <textarea className={textAreaClass} onChange={(event) => onChange((current) => ({ ...current, disagreements: event.target.value }))} value={form.disagreements} />
        </Field>
      </div>

      <section className="mt-6">
        <h3 className="font-bold text-slate-800">Attendance</h3>
        <div className="mt-3 space-y-2">
          {conference.participants.map((participant, index) => (
            <div className="grid gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-[1fr_12rem_1fr]" key={participant.id}>
              <p className="self-center text-sm font-bold text-slate-700">
                {participant.user?.name || participant.externalName}
              </p>
              <select className={inputClass} onChange={(event) => updateAttendance(index, { attendanceStatus: event.target.value })} value={form.participantAttendance[index].attendanceStatus}>
                <option value="ATTENDED">Attended</option>
                <option value="ABSENT">Absent</option>
                <option value="EXCUSED">Excused</option>
              </select>
              <input className={inputClass} onChange={(event) => updateAttendance(index, { attendanceNotes: event.target.value })} placeholder="Attendance notes" value={form.participantAttendance[index].attendanceNotes} />
            </div>
          ))}
        </div>
      </section>

      <section className="mt-6">
        <h3 className="font-bold text-slate-800">Finding outcomes</h3>
        <div className="mt-3 space-y-4">
          {conference.findings.map((finding, index) => {
            const record = form.findingDiscussions[index];
            return (
              <div className="rounded-lg border border-slate-200 p-4" key={finding.id}>
                <p className="text-xs font-bold text-sky-700">{finding.findingCode}</p>
                <h4 className="mt-1 font-bold text-slate-800">{finding.title}</h4>
                <div className="mt-3 grid gap-3 md:grid-cols-3">
                  <select className={inputClass} onChange={(event) => updateDiscussion(index, { discussionStatus: event.target.value })} value={record.discussionStatus}>
                    <option value="DISCUSSED">Discussed</option>
                    <option value="NOT_DISCUSSED">Not discussed</option>
                  </select>
                  <select className={inputClass} disabled={record.discussionStatus !== "DISCUSSED"} onChange={(event) => updateDiscussion(index, { agreementStatus: event.target.value })} value={record.agreementStatus}>
                    <option value="AGREED">Agreed</option>
                    <option value="PARTIALLY_AGREED">Partially agreed</option>
                    <option value="DISAGREED">Disagreed</option>
                  </select>
                  <input className={inputClass} onChange={(event) => updateDiscussion(index, { revisedTargetDate: event.target.value })} type="date" value={record.revisedTargetDate} />
                </div>
                <div className="mt-3 grid gap-3 md:grid-cols-2">
                  <textarea className={textAreaClass} onChange={(event) => updateDiscussion(index, { discussionNotes: event.target.value })} placeholder="Discussion notes" value={record.discussionNotes} />
                  <textarea className={textAreaClass} onChange={(event) => updateDiscussion(index, { agreementDetails: event.target.value })} placeholder="Agreement details" value={record.agreementDetails} />
                  <textarea className={`${textAreaClass} md:col-span-2`} onChange={(event) => updateDiscussion(index, { disagreementDetails: event.target.value })} placeholder="Disagreement or partial-agreement details" value={record.disagreementDetails} />
                </div>
              </div>
            );
          })}
        </div>
      </section>
    </Modal>
  );
}
