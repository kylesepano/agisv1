import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  CheckCircle2,
  ClipboardCheck,
  Database,
  LockKeyhole,
  RefreshCw,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsClosureApi, aemsEngagementApi } from "../../services/api";
import AemsClosureWorkspace from "../../components/aems/AemsClosureWorkspace";
import AemsRecordsWorkspace from "../../components/aems/AemsRecordsWorkspace";
import AemsRetentionWorkspace from "../../components/aems/AemsRetentionWorkspace";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";

const statusLabels = {
  COMPLETED: "Completed",
  CLOSED: "Closed",
  CLOSURE_REVIEW: "Closure Review",
  ISSUED: "Issued",
  REPORTING: "Reporting",
};

function statusTone(status) {
  if (status === "CLOSED") return "success";
  if (status === "COMPLETED") return "active";
  if (status === "CLOSURE_REVIEW") return "warning";
  return "info";
}

function definition(status) {
  if (status === "COMPLETED")
    return "Substantive audit work is complete. The engagement remains administratively open until the formal Closure workflow is approved and closed.";
  if (status === "CLOSED")
    return "Formal Closure is complete. Official child records and the final document index are locked; records actions remain separately controlled.";
  return "Closure readiness is evaluated from authoritative completion, transfer, reporting, records, and dialogue controls.";
}

export default function AemsRecordsClosurePage() {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const canClosure = hasPermission(user, "aems.closure.view");
  const canRetention = hasPermission(user, "aems.retention.view");
  const canRecords = hasPermission(user, "aems.records.view");
  const tabOptions = useMemo(
    () => [
      ...(canClosure
        ? [["overview", "Closure Readiness", ClipboardCheck]]
        : []),
      ...(canRetention && canClosure
        ? [["retention", "Retention Monitoring", Database]]
        : []),
      ...(canRecords ? [["records", "Records & Disposition", Archive]] : []),
    ],
    [canClosure, canRecords, canRetention],
  );
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    searchParams.get("engagementId") || "",
  );
  const [tab, setTab] = useState(
    searchParams.get("tab") || tabOptions[0]?.[0] || "records",
  );
  const [summary, setSummary] = useState(null);
  const [recordSummary, setRecordSummary] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const loadEngagements = useCallback(async () => {
    setError("");
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
    } finally {
      setLoading(false);
    }
  }, []);

  const loadSummary = useCallback(async () => {
    if (!selectedId) return;
    const requests = [];
    if (canClosure) requests.push(aemsClosureApi.show(selectedId));
    else requests.push(Promise.resolve(null));
    if (canRecords) requests.push(aemsClosureApi.records(selectedId));
    else requests.push(Promise.resolve(null));
    const results = await Promise.allSettled(requests);
    if (results[0].status === "fulfilled") setSummary(results[0].value);
    if (results[1].status === "fulfilled") setRecordSummary(results[1].value);
    const failure = results.find((item) => item.status === "rejected");
    if (failure) setError(failure.reason.message);
  }, [canClosure, canRecords, selectedId]);

  useEffect(() => {
    const timer = window.setTimeout(() => void loadEngagements(), 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);
  useEffect(() => {
    const timer = window.setTimeout(() => void loadSummary(), 0);
    return () => window.clearTimeout(timer);
  }, [loadSummary]);
  useEffect(() => {
    if (selectedId)
      setSearchParams({ engagementId: selectedId, tab }, { replace: true });
  }, [selectedId, setSearchParams, tab]);
  useEffect(() => {
    if (!tabOptions.length || tabOptions.some(([value]) => value === tab))
      return undefined;
    const timer = window.setTimeout(() => setTab(tabOptions[0][0]), 0);
    return () => window.clearTimeout(timer);
  }, [tab, tabOptions]);

  const selected = engagements.find(
    (item) => String(item.id) === String(selectedId),
  );
  const closureReadiness = summary?.readiness;
  const retention = summary?.retention || recordSummary?.retention;
  const blockers =
    summary?.readiness?.blockers || recordSummary?.blockers || [];
  const completionGate = getAemsWorkspaceGate("completion", selected);

  return (
    <div className="min-w-0" data-testid="aems-records-closure-page">
      <RegistryHeader
        icon={Archive}
        title="Records and Administrative Closure"
        description="Reconcile closure blockers, monitor retention, review legal holds, and control archive/disposition actions without conflating substantive completion with formal closure."
        actions={
          <button
            className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
            disabled={loading}
            onClick={() => {
              setLoading(true);
              void loadEngagements();
            }}
            type="button"
          >
            <RefreshCw className={loading ? "animate-spin" : ""} size={16} />{" "}
            Refresh
          </button>
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
          <div className="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
            <div className="flex flex-wrap items-center gap-2">
              <strong>{selected.engagementCode}</strong>
              <StatusBadge tone={statusTone(selected.status)}>
                {statusLabels[selected.status] || selected.status}
              </StatusBadge>
            </div>
            <p className="mt-2 leading-6">{definition(selected.status)}</p>
          </div>
        )}
      </section>
      {selectedId && !completionGate.unlocked && (
        <AemsWorkspaceLockNotice
          engagementId={selectedId}
          gate={completionGate}
        />
      )}
      {selectedId && completionGate.unlocked && (
        <>
          <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              icon={ClipboardCheck}
              label="Lifecycle status"
              value={statusLabels[selected?.status] || selected?.status || "—"}
              tone={
                selected?.status === "CLOSED"
                  ? "emerald"
                  : selected?.status === "COMPLETED"
                    ? "sky"
                    : "amber"
              }
            />
            <SummaryCard
              icon={CheckCircle2}
              label="Closure readiness"
              value={closureReadiness ? `${closureReadiness.percentage}%` : "—"}
              tone={closureReadiness?.ready ? "emerald" : "amber"}
            />
            <SummaryCard
              icon={LockKeyhole}
              label="Open blockers"
              value={blockers.length}
              tone={blockers.length ? "red" : "emerald"}
            />
            <SummaryCard
              icon={Database}
              label="Archive status"
              value={retention?.archiveStatus || "ACTIVE"}
              tone={
                retention?.archiveStatus === "ARCHIVED" ? "emerald" : "slate"
              }
            />
          </section>
          <nav
            aria-label="Records and closure sections"
            className="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
          >
            {tabOptions.map(([value, label, Icon]) => (
              <button
                aria-current={tab === value ? "page" : undefined}
                className={`inline-flex min-w-max items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold transition ${tab === value ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-100"}`}
                key={value}
                onClick={() => setTab(value)}
                type="button"
              >
                <Icon size={16} /> {label}
              </button>
            ))}
          </nav>
          {tab === "overview" && canClosure && (
            <AemsClosureWorkspace engagementId={selectedId} />
          )}
          {tab === "retention" && canRetention && canClosure && (
            <AemsRetentionWorkspace engagementId={selectedId} />
          )}
          {tab === "records" && canRecords && (
            <AemsRecordsWorkspace engagementId={selectedId} />
          )}
        </>
      )}
    </div>
  );
}
