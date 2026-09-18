import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  BrainCircuit,
  CheckCircle2,
  LockKeyhole,
  RefreshCw,
  ShieldCheck,
} from "lucide-react";
import { Link } from "react-router";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import {
  aisAggregationApi,
  aisContractApi,
  aisDashboardApi,
} from "../../services/api";

function readable(value) {
  return String(value || "")
    .replaceAll("_", " ")
    .toLowerCase()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

export default function AisGovernancePage() {
  const [contract, setContract] = useState(null);
  const [foundation, setFoundation] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [snapshotBusy, setSnapshotBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [nextContract, nextFoundation] = await Promise.all([
        aisContractApi.show(),
        aisDashboardApi.show(),
      ]);
      setContract(nextContract);
      setFoundation(nextFoundation);
    } catch (caught) {
      setError(caught.message || "The AIS foundation is unavailable.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  const generateSnapshot = async () => {
    setSnapshotBusy(true);
    setError("");
    try {
      const snapshot = await aisAggregationApi.generate();
      setFoundation((current) => ({ ...current, latestSnapshot: snapshot }));
    } catch (caught) {
      setError(caught.message || "The AIS snapshot could not be generated.");
    } finally {
      setSnapshotBusy(false);
    }
  };

  if (loading) {
    return (
      <div className="grid min-h-[32rem] place-items-center text-sm text-slate-500">
        Loading AIS foundation…
      </div>
    );
  }

  if (error && !contract) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-8 text-center">
        <AlertTriangle className="mx-auto text-red-600" />
        <h2 className="mt-2 font-bold text-red-900">
          AIS foundation unavailable
        </h2>
        <p className="mt-2 text-sm text-red-700">{error}</p>
        <button
          className="mt-4 inline-flex items-center gap-2 rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white"
          onClick={load}
          type="button"
        >
          <RefreshCw size={15} /> Retry
        </button>
      </div>
    );
  }

  const cards = [
    ["Offices", foundation?.metrics?.core?.offices ?? "—"],
    ["Active users", foundation?.metrics?.core?.activeUsers ?? "—"],
    ["Active engagements", foundation?.metrics?.aems?.activeEngagements ?? "—"],
    ["Open CMS cases", foundation?.metrics?.cms?.openCases ?? "—"],
  ];
  const headline = foundation?.headline || {};
  const distributions = foundation?.distributions || {};
  const engagementStatuses = distributions.engagementStatuses || [];
  const snapshotTrend = foundation?.snapshotTrend || [];
  const maxEngagementStatus = Math.max(
    1,
    ...engagementStatuses.map((item) => Number(item.value) || 0),
  );
  const maxTrendValue = Math.max(
    1,
    ...snapshotTrend.map((item) => Number(item.activeEngagements) || 0),
  );
  const attention = foundation?.attention || [];

  return (
    <div className="p-3 sm:p-5">
      <RegistryHeader
        icon={BrainCircuit}
        title="Audit Intelligence System"
        description="AIS governance and read-only analytical views. Source modules remain authoritative for all professional audit decisions."
        actions={
          <div className="flex items-center gap-2">
            <Link
              className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
              to="/audit-intelligence-system/reports"
            >
              Reports and exports
            </Link>
            <StatusBadge tone="info">
              Read-only analytics · {contract?.contractVersion}
            </StatusBadge>
          </div>
        }
      />
      {error && (
        <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <main className="min-w-0 space-y-5">
          <section className="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <div className="flex items-start gap-3">
              <LockKeyhole
                className="mt-0.5 shrink-0 text-amber-700"
                size={21}
              />
              <div>
                <h2 className="font-bold text-amber-950">
                  Operational intelligence is not enabled
                </h2>
                <p className="mt-1 text-sm leading-6 text-amber-900">
                  AIS-1 reads scope-filtered source records and AIS-2 presents
                  analytical views from those metrics and immutable snapshots.
                  It cannot write to Core, IAP, AEMS, CMS, or ARMIS and cannot
                  validate findings, close recommendations, or change provider
                  authority.
                </p>
              </div>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="border-b border-slate-200 px-5 py-4">
              <h2 className="text-sm font-bold text-slate-900">
                Analytical overview
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                Current indicators are calculated from records visible to your
                account.
              </p>
            </header>
            <div className="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
              {[
                [
                  "Approved IAP plans",
                  headline.approvedIapPlans ?? "—",
                  "text-blue-700",
                  "bg-blue-50",
                ],
                [
                  "Findings awaiting review",
                  headline.findingsAwaitingReview ?? "—",
                  "text-amber-700",
                  "bg-amber-50",
                ],
                [
                  "Evidence requiring assessment",
                  headline.evidenceAwaitingAssessment ?? "—",
                  "text-orange-700",
                  "bg-orange-50",
                ],
                [
                  "Overdue CMS cases",
                  headline.overdueCmsCases ?? "—",
                  "text-red-700",
                  "bg-red-50",
                ],
                [
                  "Findings awaiting response",
                  headline.findingsAwaitingResponse ?? "—",
                  "text-violet-700",
                  "bg-violet-50",
                ],
                [
                  "Open CMS cases",
                  headline.openCmsCases ?? "—",
                  "text-sky-700",
                  "bg-sky-50",
                ],
                [
                  "ARMIS assignments",
                  headline.approvedArmisAssignments ?? "—",
                  "text-emerald-700",
                  "bg-emerald-50",
                ],
                [
                  "Planned person-days",
                  headline.plannedPersonDays ?? "—",
                  "text-teal-700",
                  "bg-teal-50",
                ],
              ].map(([label, value, textColor, background]) => (
                <article
                  className={`rounded-lg border border-slate-200 p-4 ${background}`}
                  key={label}
                >
                  <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {label}
                  </p>
                  <p className={`mt-2 text-2xl font-bold ${textColor}`}>
                    {value}
                  </p>
                </article>
              ))}
            </div>
          </section>

          <section className="grid gap-5 xl:grid-cols-2">
            <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="text-sm font-bold text-slate-900">
                    Engagement status distribution
                  </h2>
                  <p className="mt-1 text-xs text-slate-500">
                    Scope-filtered AEMS lifecycle statuses.
                  </p>
                </div>
                <StatusBadge tone="info">AEMS</StatusBadge>
              </div>
              <div className="mt-5 space-y-3">
                {engagementStatuses.map((item) => (
                  <div key={item.code}>
                    <div className="mb-1 flex items-center justify-between gap-3 text-xs">
                      <span className="font-semibold text-slate-700">
                        {item.label}
                      </span>
                      <strong className="text-slate-900">{item.value}</strong>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                      <div
                        className="h-full rounded-full bg-sky-600 transition-all"
                        style={{
                          width: `${Math.round((Number(item.value) / maxEngagementStatus) * 100)}%`,
                        }}
                      />
                    </div>
                  </div>
                ))}
                {!engagementStatuses.length && (
                  <p className="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">
                    No engagement statuses are available in this scope.
                  </p>
                )}
              </div>
            </article>
            <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="text-sm font-bold text-slate-900">
                    Snapshot trend
                  </h2>
                  <p className="mt-1 text-xs text-slate-500">
                    Actor-owned immutable AIS snapshots, not an editable source
                    ledger.
                  </p>
                </div>
                <StatusBadge tone="success">Reproducible</StatusBadge>
              </div>
              {snapshotTrend.length ? (
                <div className="mt-5 space-y-3">
                  {snapshotTrend.map((item) => (
                    <div key={item.snapshotCode}>
                      <div className="mb-1 flex items-center justify-between gap-3 text-xs">
                        <span className="font-semibold text-slate-700">
                          {item.period || item.snapshotCode}
                        </span>
                        <span className="text-slate-500">
                          {item.activeEngagements} engagements ·{" "}
                          {item.openCmsCases} open CMS
                        </span>
                      </div>
                      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                          className="h-full rounded-full bg-emerald-600 transition-all"
                          style={{
                            width: `${Math.round((Number(item.activeEngagements) / maxTrendValue) * 100)}%`,
                          }}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="mt-5 rounded-lg border border-dashed border-slate-300 p-6 text-center">
                  <p className="text-sm font-semibold text-slate-700">
                    No trend baseline yet
                  </p>
                  <p className="mt-1 text-xs text-slate-500">
                    Generate an immutable AIS-1 snapshot to establish a
                    reproducible trend.
                  </p>
                </div>
              )}
            </article>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="border-b border-slate-200 px-5 py-4">
              <h2 className="text-sm font-bold text-slate-900">
                Needs attention
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                Indicators for human review. AIS does not automatically change a
                source workflow.
              </p>
            </header>
            <div className="grid gap-3 p-5 sm:grid-cols-2">
              {attention.map((item) => (
                <div
                  className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-4"
                  key={item.code}
                >
                  <div>
                    <p className="text-sm font-bold text-slate-800">
                      {item.label}
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                      Review in the authoritative module.
                    </p>
                  </div>
                  <span
                    className={`rounded-full px-3 py-1 text-sm font-bold ${item.tone === "danger" ? "bg-red-100 text-red-700" : item.tone === "warning" ? "bg-amber-100 text-amber-700" : "bg-sky-100 text-sky-700"}`}
                  >
                    {item.value}
                  </span>
                </div>
              ))}
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
              <div>
                <h2 className="text-sm font-bold text-slate-900">
                  Read-only aggregation foundation
                </h2>
                <p className="mt-1 text-xs text-slate-500">
                  Live counts use each module’s existing scope service. No
                  source records are copied or mutated.
                </p>
              </div>
              <button
                className="inline-flex items-center gap-2 rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-60"
                disabled={snapshotBusy}
                onClick={generateSnapshot}
                type="button"
              >
                <RefreshCw
                  className={snapshotBusy ? "animate-spin" : ""}
                  size={14}
                />
                {snapshotBusy ? "Generating…" : "Generate immutable snapshot"}
              </button>
            </header>
            <div className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
              {cards.map(([label, value]) => (
                <div
                  className="rounded-lg border border-slate-200 bg-slate-50 p-4"
                  key={label}
                >
                  <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {label}
                  </p>
                  <p className="mt-2 text-2xl font-bold text-slate-900">
                    {value}
                  </p>
                </div>
              ))}
            </div>
            {foundation?.latestSnapshot && (
              <p className="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                Latest snapshot:{" "}
                <strong className="text-slate-700">
                  {foundation.latestSnapshot.displayCode}
                </strong>{" "}
                · checksum{" "}
                {foundation.latestSnapshot.checksumSha256?.slice(0, 16)}…
              </p>
            )}
          </section>

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="border-b border-slate-200 px-5 py-4">
              <h2 className="text-sm font-bold text-slate-900">
                Authoritative source modules
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                AIS consumes approved, scope-filtered records without
                duplicating ownership.
              </p>
            </header>
            <div className="grid gap-3 p-5 md:grid-cols-2">
              {(contract?.sourceModules || []).map((source) => (
                <article
                  className="rounded-lg border border-slate-200 bg-slate-50 p-4"
                  key={source.module}
                >
                  <div className="flex items-center justify-between gap-3">
                    <strong className="text-sm text-slate-900">
                      {source.module}
                    </strong>
                    <StatusBadge tone="info">{source.mode}</StatusBadge>
                  </div>
                  <p className="mt-2 text-xs text-slate-500">
                    Authority: {source.authority}
                  </p>
                  <p className="mt-2 text-xs leading-5 text-slate-700">
                    {source.consumedRecords.join(", ")}
                  </p>
                </article>
              ))}
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="border-b border-slate-200 px-5 py-4">
              <h2 className="text-sm font-bold text-slate-900">
                AIS delivery phases
              </h2>
            </header>
            <div className="divide-y divide-slate-100">
              {(contract?.plannedCapabilities || []).map((phase) => (
                <div
                  className="flex items-center justify-between gap-3 px-5 py-3"
                  key={phase.code}
                >
                  <div>
                    <strong className="text-sm text-slate-800">
                      {phase.code}
                    </strong>
                    <p className="mt-1 text-xs text-slate-500">{phase.label}</p>
                  </div>
                  <StatusBadge
                    tone={
                      phase.status === "IMPLEMENTED" ? "success" : "inactive"
                    }
                  >
                    {readable(phase.status)}
                  </StatusBadge>
                </div>
              ))}
            </div>
          </section>
        </main>

        <aside className="space-y-5">
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-bold text-slate-900">
              Professional controls
            </h2>
            <div className="mt-3 grid gap-2">
              {Object.entries(contract?.professionalControls || {}).map(
                ([key, value]) => (
                  <div
                    className="flex items-center justify-between gap-3 text-xs"
                    key={key}
                  >
                    <span className="text-slate-600">{readable(key)}</span>
                    {value ? (
                      <CheckCircle2 className="text-emerald-600" size={16} />
                    ) : (
                      <AlertTriangle className="text-amber-600" size={16} />
                    )}
                  </div>
                ),
              )}
            </div>
          </section>
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-bold text-slate-900">Access scope</h2>
            <p className="mt-2 text-xs text-slate-600">
              Office: <strong>{contract?.scope?.office}</strong>
            </p>
            <p className="mt-1 text-xs text-slate-600">
              Engagement: <strong>{contract?.scope?.engagement}</strong>
            </p>
            <p className="mt-1 text-xs text-slate-600">
              Restricted records:{" "}
              <strong>
                {contract?.scope?.confidentiality?.restricted
                  ? "Allowed"
                  : "Filtered"}
              </strong>
            </p>
            <div className="mt-4 flex items-center gap-2 text-xs font-semibold text-sky-700">
              <ShieldCheck size={15} /> Scope and confidentiality are rechecked
              by the backend.
            </div>
          </section>
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-sm font-bold text-slate-900">
                Deployment hardening
              </h2>
              <StatusBadge tone="success">
                {contract?.hardening?.status || "ENFORCED"}
              </StatusBadge>
            </div>
            <p className="mt-2 text-xs leading-5 text-slate-500">
              AIS-5B controls protect source scope, reconciliation, exports,
              diagnostics, and immutable analytical records.
            </p>
            <div className="mt-3 grid gap-2">
              {Object.entries(contract?.hardening?.checks || {}).map(
                ([key, value]) => (
                  <div
                    className="flex items-center justify-between gap-3 text-xs"
                    key={key}
                  >
                    <span className="text-slate-600">{readable(key)}</span>
                    {value ? (
                      <CheckCircle2 className="text-emerald-600" size={15} />
                    ) : (
                      <AlertTriangle className="text-amber-600" size={15} />
                    )}
                  </div>
                ),
              )}
            </div>
            <p className="mt-3 text-[11px] text-slate-400">
              Limits: {contract?.hardening?.rateLimits?.readPerMinute || "—"}{" "}
              reads/min ·{" "}
              {contract?.hardening?.rateLimits?.generatePerMinute || "—"}{" "}
              generations/min
            </p>
          </section>
        </aside>
      </div>
    </div>
  );
}
