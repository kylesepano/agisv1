import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Bell,
  CalendarCheck2,
  CheckCircle2,
  Clock3,
  ExternalLink,
  FileText,
  MessageSquareText,
  RefreshCw,
  ShieldAlert,
  UsersRound,
} from "lucide-react";
import { Link, useSearchParams } from "react-router";
import RegistryHeader from "../../components/ui/RegistryHeader";
import AemsEngagementWorkspaceNav from "../../components/aems/AemsEngagementWorkspaceNav";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import {
  aemsEntryConferenceApi,
  aemsExitConferenceApi,
  aemsFindingApi,
  aemsWorkQueueApi,
  notificationApi,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const tabs = [
  ["timeline", "Engagement timeline"],
  ["conferences", "Conferences"],
  ["dialogue", "Auditee dialogue"],
  ["queues", "Review queues"],
];

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function dateTime(value) {
  if (!value) return "Not recorded";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function shortDate(value) {
  if (!value) return "No target date";
  return new Intl.DateTimeFormat("en-PH", { dateStyle: "medium" }).format(
    new Date(`${value}T00:00:00`),
  );
}

function tone(status) {
  if (["COMPLETED", "ACKNOWLEDGED", "DIALOGUE_FINALIZED", "FINALIZED"].includes(status)) return "success";
  if (["OVERDUE", "DISAGREED", "DISMISSED", "CANCELLED"].includes(status)) return "danger";
  if (["UNDER_DIALOGUE", "AWAITING_MANAGEMENT_RESPONSE", "CLARIFICATION_REQUESTED", "OPEN"].includes(status)) return "warning";
  return "info";
}

function Section({ title, action, children }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 className="flex items-center gap-2 text-base font-bold text-slate-800">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  );
}

function TimelineItem({ item }) {
  return (
    <li className="relative pl-7">
      <span className="absolute left-0 top-1.5 h-3 w-3 rounded-full border-2 border-sky-500 bg-white" />
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-sm font-bold text-slate-800">{item.title}</p>
          <p className="mt-1 text-xs text-slate-500">{item.actor || "System record"}</p>
        </div>
        <p className="text-xs font-semibold text-slate-400">{dateTime(item.at)}</p>
      </div>
      {item.detail && <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">{item.detail}</p>}
      {item.status && <StatusBadge label={label(item.status)} tone={tone(item.status)} />}
    </li>
  );
}

function FindingDialogue({ finding }) {
  const responses = finding.managementResponses ?? [];
  return (
    <article className="rounded-lg border border-slate-200 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-bold text-sky-700">{finding.findingCode}</p>
          <h3 className="mt-1 font-bold text-slate-800">{finding.title}</h3>
          <p className="mt-1 text-xs text-slate-500">Responsible office: {finding.responsibleOffice?.name || "Not recorded"}</p>
        </div>
        <StatusBadge label={label(finding.status)} tone={tone(finding.status)} />
      </div>
      <div className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
        <div><span className="text-xs font-semibold uppercase tracking-wide text-slate-400">Response due</span><p className="mt-1 font-semibold text-slate-700">{shortDate(finding.managementResponseDueDate)}</p></div>
        <div><span className="text-xs font-semibold uppercase tracking-wide text-slate-400">Responses</span><p className="mt-1 font-semibold text-slate-700">{responses.length}</p></div>
        <div><span className="text-xs font-semibold uppercase tracking-wide text-slate-400">Clarifications</span><p className="mt-1 font-semibold text-slate-700">{responses.filter((item) => item.clarificationRequest).length}</p></div>
      </div>
      <div className="mt-4 space-y-3 border-l-2 border-slate-200 pl-4">
        {responses.map((response) => (
          <div className="rounded-lg bg-slate-50 p-3" key={response.id}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="text-xs font-bold text-slate-600">{response.responseCode} · Version {response.versionNumber}</p>
              <StatusBadge label={label(response.status)} tone={tone(response.status)} />
            </div>
            <p className="mt-2 text-sm font-semibold text-slate-700">{label(response.agreementPosition)}</p>
            <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-600">{response.managementComment || "No management comment recorded."}</p>
            {response.proposedAction && <p className="mt-2 text-sm text-slate-600"><strong>Corrective action:</strong> {response.proposedAction}</p>}
            {response.clarificationRequest && <div className="mt-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-sm text-amber-900"><strong>Clarification requested:</strong> {response.clarificationRequest}<span className="mt-1 block text-xs">{dateTime(response.clarificationRequestedAt)}</span></div>}
            <div className="mt-3 space-y-2">
              {(response.rejoinders ?? []).map((rejoinder) => (
                <div className="rounded-md border border-sky-200 bg-sky-50 p-2" key={rejoinder.id}>
                  <div className="flex flex-wrap items-center justify-between gap-2"><span className="text-xs font-bold text-sky-800">Auditor rejoinder · {label(rejoinder.disposition)}</span><StatusBadge label={label(rejoinder.status)} tone={tone(rejoinder.status)} /></div>
                  <p className="mt-1 whitespace-pre-wrap text-sm text-slate-700">{rejoinder.rejoinder}</p>
                  <p className="mt-1 text-xs text-slate-500">{dateTime(rejoinder.createdAt)} · {rejoinder.authoredBy?.name || "Auditor"}</p>
                </div>
              ))}
            </div>
          </div>
        ))}
        {!responses.length && <p className="text-sm text-slate-500">No response has been submitted for this formally communicated finding.</p>}
      </div>
    </article>
  );
}

export default function AemsConferenceDialoguePage() {
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(params.get("engagementId") ?? "");
  const [workspace, setWorkspace] = useState(null);
  const [activeTab, setActiveTab] = useState(params.get("tab") ?? "timeline");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [queueBusy, setQueueBusy] = useState(false);

  const loadEngagements = useCallback(async () => {
    const results = await Promise.allSettled([
      aemsEntryConferenceApi.engagements(),
      aemsExitConferenceApi.engagements(),
      aemsFindingApi.engagements(),
    ]);
    const byId = new Map();
    results.forEach((result) => {
      if (result.status === "fulfilled") {
        result.value.forEach((item) => byId.set(String(item.id), item));
      }
    });
    const available = [...byId.values()];
    setEngagements(available);
    setEngagementId((current) => current || String(available[0]?.id ?? ""));
    if (!available.length && results.every((result) => result.status === "rejected")) setError("No conference or dialogue engagement is available to your current scope.");
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!engagementId) return;
    setLoading(true);
    const results = await Promise.allSettled([
      aemsEntryConferenceApi.show(engagementId),
      aemsExitConferenceApi.show(engagementId),
      aemsFindingApi.show(engagementId),
      aemsWorkQueueApi.show(engagementId),
      notificationApi.recent(),
    ]);
    const value = (index, fallback) => results[index].status === "fulfilled" ? results[index].value : fallback;
    const rejected = results.filter((result) => result.status === "rejected");
    setWorkspace({ entry: value(0, null), exit: value(1, null), findings: value(2, { findings: [] }), queue: value(3, { tasks: [], dueProcess: [], escalationCandidates: [], reviewNotes: [] }), notifications: value(4, { notifications: [] }) });
    setError(rejected.length === results.length ? "The conference and dialogue workspace could not be loaded." : "");
    setLoading(false);
  }, [engagementId]);

  useEffect(() => { const timer = window.setTimeout(loadEngagements, 0); return () => window.clearTimeout(timer); }, [loadEngagements]);
  useEffect(() => { const timer = window.setTimeout(loadWorkspace, 0); return () => window.clearTimeout(timer); }, [loadWorkspace]);
  useEffect(() => { if (engagementId) setParams({ engagementId, tab: activeTab }, { replace: true }); }, [activeTab, engagementId, setParams]);

  const entry = workspace?.entry?.conference;
  const exits = useMemo(() => workspace?.exit?.conferences ?? [], [workspace?.exit?.conferences]);
  const findings = workspace?.findings?.findings ?? [];
  const dueProcess = useMemo(() => workspace?.queue?.dueProcess ?? [], [workspace?.queue?.dueProcess]);
  const candidates = workspace?.queue?.escalationCandidates ?? [];
  const notifications = (workspace?.notifications?.notifications ?? []).filter((item) => String(item.moduleCode ?? "").toUpperCase().startsWith("AEM"));
  const responseRows = findings.flatMap((finding) => (finding.managementResponses ?? []).map((response) => ({ finding, response })));
  const overdueResponses = findings.filter((finding) => ["COMMUNICATED", "AWAITING_MANAGEMENT_RESPONSE"].includes(finding.status) && finding.managementResponseDueDate && new Date(`${finding.managementResponseDueDate}T23:59:59`) < new Date());
  const openTasks = (workspace?.queue?.tasks ?? []).filter((task) => !["COMPLETED", "CANCELLED"].includes(task.status));

  const timeline = useMemo(() => {
    const items = [];
    if (entry) {
      items.push({
        at: entry.updatedAt || entry.scheduledStartAt || new Date().toISOString(),
        title: `Entry Conference ${label(entry.status)}`,
        actor: entry.updatedBy?.name || entry.createdBy?.name,
        detail: entry.conferenceNotes,
        status: entry.status,
      });
    }
    (workspace?.entry?.history ?? []).forEach((event) => items.push({ at: event.createdAt, title: `Entry Conference ${label(event.action)}`, actor: event.actor?.name, detail: event.comment, status: event.toStatus }));
    exits.forEach((conference) => {
      items.push({ at: conference.createdAt, title: `Exit Conference ${conference.conferenceCode} scheduled`, actor: conference.createdBy?.name, detail: conference.agenda, status: conference.status });
      if (conference.completedAt) items.push({ at: conference.completedAt, title: `Exit Conference ${conference.conferenceCode} completed`, actor: conference.completedBy?.name, detail: conference.minutes, status: conference.status });
      (conference.acknowledgements ?? []).forEach((ack) => items.push({ at: ack.acknowledgedAt, title: `Exit Conference acknowledgement · ${label(ack.status)}`, actor: ack.actor?.name, detail: ack.comment }));
    });
    responseRows.forEach(({ finding, response }) => {
      items.push({ at: response.createdAt, title: `${finding.findingCode} management response`, actor: response.authoredBy?.name, detail: response.managementComment, status: response.status });
      if (response.clarificationRequestedAt) items.push({ at: response.clarificationRequestedAt, title: `${finding.findingCode} clarification requested`, actor: response.finalizedBy?.name, detail: response.clarificationRequest, status: "CLARIFICATION_REQUESTED" });
      (response.rejoinders ?? []).forEach((rejoinder) => items.push({ at: rejoinder.createdAt, title: `${finding.findingCode} auditor rejoinder`, actor: rejoinder.authoredBy?.name, detail: rejoinder.rejoinder, status: rejoinder.status }));
    });
    dueProcess.forEach((item) => items.push({ at: item.recordedAt, title: `${label(item.eventType)} due-process exchange`, actor: item.actor?.name, detail: item.content, status: item.eventType === "FINAL_NON_RESPONSE" ? "OVERDUE" : item.eventType }));
    return items.filter((item) => item.at).sort((a, b) => new Date(b.at) - new Date(a.at));
  }, [dueProcess, entry, exits, responseRows, workspace?.entry?.history]);

  function selectEngagement(value) {
    setEngagementId(String(value ?? ""));
    setActiveTab("timeline");
  }

  function refresh() {
    loadWorkspace().then(() => toast.success("Conference and dialogue workspace refreshed."));
  }

  const conferenceEngagement =
    workspace?.engagement ??
    engagements.find((item) => String(item.id) === String(engagementId));
  const conferenceGate = getAemsWorkspaceGate(
    "conferences",
    conferenceEngagement,
  );

  async function queueAction(operation, message) {
    setQueueBusy(true);
    try {
      await operation();
      toast.success(message);
      await loadWorkspace();
    } catch (requestError) {
      toast.error(requestError.message);
    } finally {
      setQueueBusy(false);
    }
  }

  if (workspace && !conferenceGate.unlocked) {
    return (
      <main className="min-w-0 p-4 sm:p-5" data-testid="aems-conference-dialogue-page">
        <RegistryHeader
          description="Conference records are available only after the engagement is authorized and reaches the Entry Conference phase."
          icon={CalendarCheck2}
          title="Conference Management"
        />
        <AemsEngagementWorkspaceNav
          engagement={conferenceEngagement}
          engagementId={engagementId}
        />
        <AemsWorkspaceLockNotice
          engagementId={engagementId}
          gate={conferenceGate}
        />
      </main>
    );
  }

  return (
    <main className="min-w-0 p-4 sm:p-5" data-testid="aems-conference-dialogue-page">
      <RegistryHeader description="Schedule, conduct, and close Entry and Exit Conferences with briefing papers, attendance, minutes, waivers, agreements, and follow-up in one workspace." icon={CalendarCheck2} title="Conference Management" actions={<button className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50" onClick={refresh} type="button"><RefreshCw size={16} /> Refresh</button>} />
      <section className="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(18rem,1fr)_auto]">
        <SearchableSelect onChange={selectEngagement} options={engagements.map((item) => ({ value: item.id, label: `${item.engagementCode} — ${item.title}`, keywords: item.status }))} placeholder="Select an engagement" value={engagementId} />
        <Link className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" to={engagementId ? `/audit-engagement-management/${engagementId}` : "/audit-engagement-management"}><ExternalLink size={16} /> Open engagement</Link>
      </section>
      {error && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{error}</div>}
      {!engagementId ? <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Select an engagement to inspect conference and dialogue records.</div> : loading && !workspace ? <div className="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">Loading conference and dialogue workspace…</div> : (
        <>
          <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <SummaryCard icon={CalendarCheck2} label="Entry conference" value={entry ? label(entry.status) : "Not created"} tone="sky" />
            <SummaryCard icon={CalendarCheck2} label="Exit conferences" value={exits.length} tone="slate" />
            <SummaryCard icon={MessageSquareText} label="Response exchanges" value={responseRows.length} tone="emerald" />
            <SummaryCard icon={Clock3} label="Overdue responses" value={overdueResponses.length} tone="amber" />
            <SummaryCard icon={ShieldAlert} label="Open review candidates" value={candidates.filter((item) => item.status === "OPEN").length} tone="red" />
            <SummaryCard icon={Bell} label="AEMS notifications" value={notifications.length} tone="sky" />
          </section>
          <nav className="mb-5 flex min-w-0 gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Conference dialogue views">
            {tabs.map(([key, text]) => <button className={`whitespace-nowrap rounded-lg px-4 py-2.5 text-sm font-bold transition ${activeTab === key ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-50"}`} key={key} onClick={() => setActiveTab(key)} type="button">{text}</button>)}
          </nav>
          {activeTab === "timeline" && <div className="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,.6fr)]"><Section title="Conference and dialogue timeline"><ol className="relative space-y-6 border-l border-slate-200 pl-5">{timeline.map((item, index) => <TimelineItem item={item} key={`${item.title}-${item.at}-${index}`} />)}{!timeline.length && <p className="text-sm text-slate-500">No exchanges have been recorded yet.</p>}</ol></Section><Section title="Notification center" action={<Link className="text-xs font-bold text-sky-700 hover:underline" to="/notifications">Open center</Link>}><div className="space-y-3">{notifications.map((item) => <div className="rounded-lg border border-slate-200 p-3" key={item.id}><div className="flex items-start justify-between gap-2"><p className="text-sm font-bold text-slate-800">{item.title}</p>{!item.readAt && <span className="h-2 w-2 rounded-full bg-red-500" />}</div><p className="mt-1 text-xs leading-5 text-slate-500">{item.message}</p><p className="mt-2 text-[11px] text-slate-400">{dateTime(item.createdAt)}</p></div>)}{!notifications.length && <p className="text-sm text-slate-500">No recent AEMS notifications.</p>}</div></Section></div>}
          {activeTab === "conferences" && <div className="space-y-5"><Section title="Entry Conference">{entry ? <div className="grid gap-3 sm:grid-cols-2"><div><p className="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</p><StatusBadge label={label(entry.status)} tone={tone(entry.status)} /></div><div><p className="text-xs font-semibold uppercase tracking-wide text-slate-400">Schedule</p><p className="mt-1 text-sm font-semibold text-slate-700">{dateTime(entry.scheduledStartAt)}</p></div><div className="sm:col-span-2"><p className="text-xs font-semibold uppercase tracking-wide text-slate-400">Conference notes</p><p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-600">{entry.conferenceNotes || "No conference notes recorded."}</p></div></div> : <p className="text-sm text-slate-500">No Entry Conference record has been created.</p>}</Section><Section title="Exit Conference timeline"><div className="grid gap-4 lg:grid-cols-2">{exits.map((conference) => <article className="rounded-lg border border-slate-200 p-4" key={conference.id}><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-bold text-sky-700">{conference.conferenceCode}</p><h3 className="mt-1 font-bold text-slate-800">{dateTime(conference.scheduledStartAt)}</h3></div><StatusBadge label={label(conference.status)} tone={tone(conference.status)} /></div><p className="mt-3 text-sm text-slate-600">{conference.venue || conference.onlineMeetingDetails || "Venue pending"}</p><div className="mt-3 grid grid-cols-3 gap-2 text-xs"><span><UsersRound size={14} className="mb-1 text-slate-400" />{conference.participants?.length ?? 0} participants</span><span><FileText size={14} className="mb-1 text-slate-400" />{conference.findings?.length ?? 0} findings</span><span><CheckCircle2 size={14} className="mb-1 text-slate-400" />{conference.acknowledgements?.length ?? 0} acknowledgements</span></div><div className="mt-3 rounded-md bg-slate-50 p-3 text-sm text-slate-600"><strong>Agreements:</strong> {conference.agreements || "None recorded."}<br /><strong>Disagreements:</strong> {conference.disagreements || "None recorded."}</div></article>)}{!exits.length && <p className="text-sm text-slate-500">No Exit Conference records are visible in this engagement scope.</p>}</div></Section></div>}
          {activeTab === "dialogue" && <div className="space-y-5"><Section title="Auditee response and auditor rejoinder timeline" action={<Link className="inline-flex items-center gap-1 text-xs font-bold text-sky-700 hover:underline" to={`/audit-engagement-management/auditee-responses?engagementId=${engagementId}`}>Open dialogue workspace <ExternalLink size={13} /></Link>}><div className="space-y-4">{findings.map((finding) => <FindingDialogue finding={finding} key={finding.id} />)}{!findings.length && <p className="text-sm text-slate-500">No formally communicated findings are visible to this user and office.</p>}</div></Section><Section title="Clarification history"><div className="space-y-3">{responseRows.filter(({ response }) => response.clarificationRequest).map(({ finding, response }) => <div className="rounded-lg border border-amber-200 bg-amber-50 p-3" key={`${finding.id}-${response.id}`}><p className="text-sm font-bold text-amber-900">{finding.findingCode} · {response.responseCode}</p><p className="mt-1 whitespace-pre-wrap text-sm text-amber-800">{response.clarificationRequest}</p><p className="mt-1 text-xs text-amber-700">Requested {dateTime(response.clarificationRequestedAt)}</p></div>)}{!responseRows.some(({ response }) => response.clarificationRequest) && <p className="text-sm text-slate-500">No clarification requests recorded.</p>}</div></Section></div>}
          {activeTab === "queues" && <div className="grid gap-5 lg:grid-cols-3"><Section title="Overdue response queue"><div className="space-y-3">{overdueResponses.map((finding) => <div className="rounded-lg border border-red-200 bg-red-50 p-3" key={finding.id}><p className="text-sm font-bold text-red-900">{finding.findingCode}</p><p className="mt-1 text-sm text-red-800">{finding.title}</p><p className="mt-1 text-xs text-red-700">Due {shortDate(finding.managementResponseDueDate)}</p></div>)}{!overdueResponses.length && <p className="text-sm text-slate-500">No overdue management responses.</p>}</div></Section><Section title="Review notes and tasks"><div className="space-y-3">{openTasks.map((task) => <div className="rounded-lg border border-slate-200 p-3" key={task.id}><div className="flex items-start justify-between gap-2"><p className="text-sm font-bold text-slate-800">{task.title}</p><StatusBadge label={label(task.dueState)} tone={tone(task.dueState)} /></div><p className="mt-1 text-xs text-slate-500">{task.taskCode} · Due {dateTime(task.dueAt)}</p><div className="mt-2 flex flex-wrap gap-2">{task.status === "OPEN" && <button className="rounded border border-slate-300 px-2 py-1 text-xs font-bold text-slate-700 disabled:opacity-50" disabled={queueBusy} onClick={() => queueAction(() => aemsWorkQueueApi.transitionTask(engagementId, task.id, { action: "START", lockVersion: task.lockVersion }), "Task started")} type="button">Start</button>}{task.status === "IN_PROGRESS" && <button className="rounded border border-emerald-300 px-2 py-1 text-xs font-bold text-emerald-700 disabled:opacity-50" disabled={queueBusy} onClick={() => queueAction(() => aemsWorkQueueApi.transitionTask(engagementId, task.id, { action: "COMPLETE", lockVersion: task.lockVersion }), "Task completed")} type="button">Complete</button>}</div></div>)}{!openTasks.length && <p className="text-sm text-slate-500">No open engagement tasks.</p>}<div className="border-t border-slate-200 pt-3">{(workspace?.queue?.reviewNotes ?? []).map((note) => <div className="mb-2 rounded-lg border border-slate-200 p-3" key={note.id}><div className="flex items-center justify-between gap-2"><p className="text-sm font-bold text-slate-800">{note.noteCode}</p><StatusBadge label={label(note.status)} tone={tone(note.status)} /></div><p className="mt-1 text-sm text-slate-600">{note.content}</p>{note.status === "DRAFT" && <button className="mt-2 rounded border border-emerald-300 px-2 py-1 text-xs font-bold text-emerald-700 disabled:opacity-50" disabled={queueBusy} onClick={() => queueAction(() => aemsWorkQueueApi.transitionReviewNote(engagementId, note.id, { action: "FINALIZE", lockVersion: note.lockVersion }), "Review note finalized")} type="button">Finalize note</button>}</div>)}</div></div></Section><Section title="Escalation candidates"><div className="space-y-3">{candidates.map((candidate) => <div className="rounded-lg border border-slate-200 p-3" key={candidate.id}><div className="flex items-start justify-between gap-2"><p className="text-sm font-bold text-slate-800">{candidate.candidateCode}</p><StatusBadge label={label(candidate.status)} tone={tone(candidate.status)} /></div><p className="mt-1 text-sm text-slate-600">{candidate.reason}</p><p className="mt-1 text-xs text-slate-400">Detected {dateTime(candidate.detectedAt)}</p>{candidate.status === "OPEN" && <div className="mt-2 flex flex-wrap gap-2"><button className="rounded border border-sky-300 px-2 py-1 text-xs font-bold text-sky-700 disabled:opacity-50" disabled={queueBusy} onClick={() => queueAction(() => aemsWorkQueueApi.reviewEscalationCandidate(engagementId, candidate.id, { action: "ACKNOWLEDGE", lockVersion: candidate.lockVersion, comment: "Candidate acknowledged for review." }), "Escalation candidate acknowledged")} type="button">Acknowledge</button><button className="rounded border border-slate-300 px-2 py-1 text-xs font-bold text-slate-700 disabled:opacity-50" disabled={queueBusy} onClick={() => queueAction(() => aemsWorkQueueApi.reviewEscalationCandidate(engagementId, candidate.id, { action: "DISMISS", lockVersion: candidate.lockVersion, comment: "Candidate reviewed and dismissed." }), "Escalation candidate dismissed")} type="button">Dismiss</button></div>}</div>)}{!candidates.length && <p className="text-sm text-slate-500">No escalation candidates.</p>}</div></Section></div>}
        </>
      )}
    </main>
  );
}
