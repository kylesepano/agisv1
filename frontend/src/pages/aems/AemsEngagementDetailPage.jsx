import { useEffect, useState } from "react";
import {
  Activity,
  BarChart3,
  CalendarDays,
  CheckSquare,
  ChevronDown,
  FileSearch,
  FileText,
  Folder,
  Home,
  Info,
  LockKeyhole,
  Settings,
  Target,
  TriangleAlert,
} from "lucide-react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router";
import useRecordView from "../../hooks/useRecordView";
import { aemsEngagementApi } from "../../services/api";

const phaseLabels = {
  FOUNDATION: "Planning",
  PLANNING: "Planning",
  EXECUTION: "Execution",
  ISSUES_AFR: "Audit Issues",
  CONFERENCES: "Execution",
  REPORTING: "Audit Reports",
  COMPLETION_TRANSFER: "Completion",
  CLOSURE: "Completion",
};

const statusLabels = {
  DRAFT: "Draft",
  AUTHORIZATION_PREPARATION: "For Authorization",
  RETURNED_FOR_REVISION: "Under Review",
  AUTHORIZED: "Authorized",
  ENGAGEMENT_PLANNING: "In Progress",
  ENTRY_CONFERENCE: "In Progress",
  FIELDWORK: "In Progress",
  FINDINGS_COMMUNICATION: "Under Review",
  EXIT_CONFERENCE: "Under Review",
  REPORTING: "Draft AFR",
  ISSUED: "Issued",
  CLOSURE_REVIEW: "For Closure",
  COMPLETED: "For Closure",
  CLOSED: "Closed",
  SUSPENDED: "Suspended",
  CANCELLED: "Cancelled",
};

const phaseOrder = {
  FOUNDATION: 1,
  PLANNING: 1,
  EXECUTION: 2,
  ISSUES_AFR: 3,
  CONFERENCES: 4,
  REPORTING: 5,
  COMPLETION_TRANSFER: 6,
  CLOSURE: 6,
};

const tabRequirements = {
  details: { minimumPhase: 0 },
  planning: { minimumPhase: 1 },
  execution: { minimumPhase: 2, reason: "Complete the Planning stage before Execution is available." },
  issues: { minimumPhase: 3, reason: "Move the engagement to Audit Issues before this workspace is available." },
  afrs: { minimumPhase: 3, reason: "Move the engagement to Audit Issues before AFRs are available." },
  reports: { minimumPhase: 5, reason: "Complete the preceding engagement stages before Audit Reports are available." },
  completion: { minimumPhase: 6, reason: "Issue the audit report before Completion & Transfer is available." },
  activity: { minimumPhase: 0 },
};

function formatDate(value, withTime = false) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(withTime ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(new Date(value.includes("T") ? value : `${value}T00:00:00`));
}

function duration(start, end) {
  if (!start || !end) return "—";
  return `${Math.max(
    1,
    Math.floor(
      (new Date(`${end}T00:00:00`) - new Date(`${start}T00:00:00`)) /
        86400000,
    ) + 1,
  )} days`;
}

function Section({ icon: Icon, title, children, className = "" }) {
  return (
    <section className={`overflow-hidden rounded-md border border-[#75b5d2] bg-[#f4f9ff] ${className}`}>
      <header className="flex items-center gap-3 border-b border-[#75b5d2] bg-[#d7e9f8] px-5 py-3 text-[#123a98]">
        <Icon size={26} />
        <h3 className="text-lg font-semibold">{title}</h3>
      </header>
      <div className="px-6 py-4">{children}</div>
    </section>
  );
}

function Row({ label, children }) {
  return (
    <div className="grid gap-2 py-1.5 text-sm sm:grid-cols-[11rem_minmax(0,1fr)]">
      <dt className="text-[#123a98]">{label}</dt>
      <dd className="min-w-0 text-slate-700">{children || "—"}</dd>
    </div>
  );
}

export default function AemsEngagementDetailPage() {
  const { engagementId } = useParams();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [engagement, setEngagement] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const requestedTab = searchParams.get("tab");
  const tab = ["planning", "activity"].includes(requestedTab)
    ? requestedTab
    : "details";

  useEffect(() => {
    let active = true;
    aemsEngagementApi
      .show(engagementId)
      .then((record) => active && setEngagement(record))
      .catch((reason) => active && setError(reason.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [engagementId]);

  useRecordView(engagement, {
    module: "AEMS",
    recordType: "AuditEngagement",
    code: (record) => record.engagementCode,
    label: (record) => `${record.engagementCode} — ${record.title}`,
  });

  if (loading) {
    return <main className="grid min-h-[60vh] place-items-center bg-[#eef7fa]"><span className="h-10 w-10 animate-spin rounded-full border-2 border-sky-200 border-t-sky-700" /></main>;
  }
  if (!engagement) {
    return <main className="bg-[#eef7fa] p-8"><div className="rounded border border-red-300 bg-red-50 p-4 text-red-700">{error || "The engagement could not be found."}</div></main>;
  }

  const snapshot = engagement.sourceSnapshot ?? {};
  const plan = snapshot.plan ?? {};
  const source = engagement.sourceType === "PLANNED";
  const currentPhaseOrder = phaseOrder[engagement.phase] ?? 1;
  const tabs = [
    ["details", "Details", Home, null],
    ["planning", "Planning", FileText, null],
    ["execution", "Execution", Settings],
    ["issues", "Audit Issues", TriangleAlert],
    ["afrs", "AFRs", FileSearch],
    ["reports", "Audit Reports", BarChart3],
    ["completion", "Completion & Transfer", CheckSquare],
    ["activity", "Activity Log", Activity, null],
  ];

  return (
    <main className="min-w-0 bg-[#eef7fa] px-5 py-5 text-[#10389a] sm:px-8">
      <nav aria-label="Breadcrumb" className="mb-4 flex items-center gap-2 text-sm text-sky-700">
        <Link className="hover:text-sky-900 hover:underline" to="/dashboard">Home</Link>
        <span className="text-slate-400">›</span>
        <Link className="hover:text-sky-900 hover:underline" to="/audit-engagement-management">Audit Engagements</Link>
        <span className="text-slate-400">›</span>
        <span aria-current="page">Audit Engagement Details</span>
      </nav>
      <div className="hidden">
        Home <span className="mx-2 text-slate-400">›</span> Audit Engagements{" "}
        <span className="mx-2 text-slate-400">›</span> Audit Engagement Details
      </div>

      <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-4">
            <h2 className="text-3xl font-semibold tracking-tight text-[#073b9b] sm:text-[2.35rem]">Audit Engagement Details</h2>
            <span className="text-sm text-red-500">[WF-SCR-200-03]</span>
          </div>
          <p className="mt-1 text-sm text-[#154da8]">View the authoritative details of this audit engagement and access its component workspaces.</p>
        </div>
        <div className="flex gap-3">
          <button className="h-11 rounded-md border border-slate-400 bg-white px-8 text-slate-700" onClick={() => navigate(`/audit-engagement-management/edit?engagementId=${engagement.id}&source=${engagement.sourceType === "SPECIAL" ? "unplanned" : "planned"}`)} type="button">Edit Engagement</button>
          <button className="inline-flex h-11 items-center gap-2 rounded-md bg-[#087bea] px-8 text-white" type="button">More Actions <ChevronDown size={16} /></button>
        </div>
      </div>

      <section className="mb-3 grid gap-6 rounded-lg border-2 border-[#2294d2] bg-[#f7fbff] px-8 py-6 lg:grid-cols-[1.05fr_1.35fr_1fr]">
        <div className="border-b border-[#78afca] pb-4 lg:border-b-0 lg:border-r lg:pb-0 lg:pr-8">
          <strong className="block text-3xl text-[#123a98]">{engagement.engagementCode}</strong>
          <h3 className="mt-2 text-xl font-semibold text-[#123a98]">{engagement.title}</h3>
          <p className="mt-1 text-sm">{engagement.offices?.[0]?.name ?? "Office not assigned"}{engagement.offices?.[0]?.code ? ` (${engagement.offices[0].code})` : ""}</p>
        </div>
        <dl className="border-b border-[#78afca] pb-4 lg:border-b-0 lg:border-r lg:pb-0 lg:pr-8">
          <Row label="Audit Type:">{engagement.auditType?.label ?? "—"}</Row>
          <Row label="Audit Year:">{engagement.auditYear ?? plan.fiscalYear ?? new Date(engagement.plannedStartDate ?? engagement.createdAt).getFullYear()}</Row>
          <Row label="Period Covered:">{formatDate(engagement.periodCoveredStartDate ?? plan.periodStart)} - {formatDate(engagement.periodCoveredEndDate ?? plan.periodEnd)}</Row>
        </dl>
        <dl>
          <Row label="Current Phase"><span className="inline-block min-w-32 rounded-lg bg-amber-100 px-4 py-1 text-center font-semibold text-amber-700">{phaseLabels[engagement.phase] ?? "Planning"}</span></Row>
          <Row label="Engagement Status"><span className="inline-block min-w-32 rounded-lg bg-blue-100 px-4 py-1 text-center font-semibold text-blue-700">{statusLabels[engagement.status] ?? engagement.status}</span></Row>
        </dl>
      </section>

      <nav className="overflow-x-auto rounded-t-lg border-2 border-[#2294d2] bg-[#dcebfa]" aria-label="Engagement workspaces">
        <div className="flex min-w-max">
          {tabs.map(([key, label, Icon]) => {
            const requirement = tabRequirements[key];
            const locked = currentPhaseOrder < requirement.minimumPhase;
            return (
              <button
                aria-disabled={locked}
                className={`flex h-14 items-center gap-2 border-b-4 px-5 text-base font-semibold ${
                  locked
                    ? "cursor-not-allowed border-transparent text-slate-400"
                    : tab === key
                      ? "border-emerald-400 bg-white text-[#087bea]"
                      : "border-transparent text-[#123a98] hover:bg-white/60"
                }`}
                disabled={locked}
                key={key}
                onClick={() => setSearchParams(key === "details" ? {} : { tab: key })}
                title={locked ? requirement.reason : undefined}
                type="button"
              >
                {locked && <LockKeyhole size={16} />}
                <Icon className={locked ? "text-slate-400" : "text-sky-500"} size={25} />
                {label}
              </button>
            );
          })}
        </div>
      </nav>

      <section className="mb-7 rounded-b-lg border-x-2 border-b-2 border-[#2294d2] bg-[#dcebfa] p-4 sm:p-7">
      {tab === "activity" ? (
        <Section icon={Activity} title="Activity Log">
          {(engagement.events ?? []).length ? <ol className="divide-y divide-sky-100">{engagement.events.slice().reverse().map((event) => <li className="grid gap-2 py-4 sm:grid-cols-[12rem_1fr]" key={event.id}><div><strong className="block text-sm text-[#123a98]">{event.action.replaceAll("_", " ")}</strong><span className="text-xs text-slate-500">{formatDate(event.createdAt, true)}</span></div><div className="text-sm text-slate-700"><p>{event.comment || "Engagement record updated."}</p><span className="mt-1 block text-xs text-slate-500">By {event.actor?.name ?? "System"}</span></div></li>)}</ol> : <p className="text-sm text-slate-500">No engagement activity has been recorded.</p>}
        </Section>
      ) : tab === "planning" ? (
        <div className="mx-auto grid max-w-[1500px] gap-5 lg:grid-cols-2">
          <Section icon={FileText} title="Planning Workspace">
            <dl>
              <Row label="Planning Stage"><span className="inline-block rounded-lg bg-amber-100 px-4 py-1 font-semibold text-amber-700">{phaseLabels[engagement.phase] ?? "Planning"}</span></Row>
              <Row label="Engagement Status">{statusLabels[engagement.status] ?? engagement.status}</Row>
              <Row label="Planning Objective">{engagement.objectives || "No initial objective has been recorded."}</Row>
            </dl>
          </Section>

          <Section icon={CalendarDays} title="Planning Schedule">
            <dl>
              <Row label="Planned Start">{formatDate(engagement.plannedStartDate)}</Row>
              <Row label="Planned End">{formatDate(engagement.plannedEndDate)}</Row>
              <Row label="Planned Duration">{duration(engagement.plannedStartDate, engagement.plannedEndDate)}</Row>
            </dl>
          </Section>

          <Section className="lg:col-span-2" icon={Target} title="Planning Scope">
            <dl>
              <Row label="Audit Area(s)"><div className="flex flex-wrap gap-2">{(engagement.auditAreas ?? []).map((area) => <span className="rounded bg-[#d8eafa] px-3 py-1 text-xs text-slate-700" key={area.id}>{area.name}</span>)}{!(engagement.auditAreas ?? []).length && "No audit areas have been selected."}</div></Row>
              <Row label="Audit Focus(es)"><div className="flex flex-wrap gap-2">{(engagement.auditFocuses ?? []).map((focus) => <span className="rounded bg-[#d8eafa] px-3 py-1 text-xs text-slate-700" key={focus.id}>{focus.name}</span>)}{!(engagement.auditFocuses ?? []).length && "No audit focuses have been selected."}</div></Row>
              <Row label="Planning Note">The detailed Planning Package will be added here while the engagement remains in the Planning stage.</Row>
            </dl>
          </Section>
        </div>
      ) : (
        <div className="mx-auto grid max-w-[1500px] gap-5 lg:grid-cols-2">
          <Section icon={Info} title="Engagement Source">
            <dl>
              <Row label="Engagement Source">{source ? "Internal Audit Planning (IAP)" : "Authorized Unplanned Engagement"}</Row>
              {source ? <><Row label="Plan Reference"><span className="font-medium text-[#0878ef] underline">{plan.code ?? "—"}</span></Row><Row label="Approved By">{plan.approvedByName ?? "—"}</Row><Row label="Approval Date">{formatDate(plan.approvedAt)}</Row></> : <><Row label="Authority Type">{engagement.specialAuthorityTypeCode?.replaceAll("_", " ")}</Row><Row label="Authority Reference">{engagement.specialAuthorityReference}</Row><Row label="Directing Authority">{engagement.specialAuthorityApprover?.name}</Row><Row label="Requesting Office">{engagement.requestingOffice?.name}</Row><Row label="Date Received">{formatDate(engagement.specialAuthorityReceivedDate)}</Row></>}
            </dl>
          </Section>

          <Section icon={CalendarDays} title="Initial Schedule">
            <dl><Row label="Start Date">{formatDate(engagement.plannedStartDate)}</Row><Row label="End Date">{formatDate(engagement.plannedEndDate)}</Row><Row label="Planned Duration">{duration(engagement.plannedStartDate, engagement.plannedEndDate)}</Row></dl>
          </Section>

          <Section className="lg:col-span-2" icon={Target} title="Initial Scope">
            <dl>
              <Row label="Audit Area(s)"><div className="flex flex-wrap gap-2">{(engagement.auditAreas ?? []).map((area) => <span className="rounded bg-[#d8eafa] px-3 py-1 text-xs text-slate-700" key={area.id}>{area.name}</span>)}{!(engagement.auditAreas ?? []).length && "—"}</div></Row>
              <Row label="Audit Focus(es)"><div className="flex flex-wrap gap-2">{(engagement.auditFocuses ?? []).map((focus) => <span className="rounded bg-[#d8eafa] px-3 py-1 text-xs text-slate-700" key={focus.id}>{focus.name}</span>)}{!(engagement.auditFocuses ?? []).length && "—"}</div></Row>
              <Row label="Initial Objective"><p className="rounded border border-[#bfd8e8] bg-white px-3 py-2">{engagement.objectives || "—"}</p></Row>
            </dl>
          </Section>

          <Section className="lg:col-span-2" icon={Folder} title="Record Information">
            <dl className="grid gap-x-10 md:grid-cols-2"><Row label="Created By">{engagement.creator?.name}</Row><Row label="Date Created">{formatDate(engagement.createdAt)}</Row><Row label="Last Updated">{formatDate(engagement.updatedAt)}</Row></dl>
          </Section>
        </div>
      )}
      </section>
    </main>
  );
}
