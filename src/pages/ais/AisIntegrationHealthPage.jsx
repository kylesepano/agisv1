import {
  Activity,
  AlertTriangle,
  CheckCircle2,
  ExternalLink,
  History,
  RefreshCw,
  ShieldCheck,
  XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { Link } from "react-router";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { aisContractApi } from "../../services/api";

const sourceLinks = {
  CORE: { label: "Core Records", path: "/office-registry" },
  IAP: { label: "Internal Audit Planning", path: "/internal-audit-planning" },
  AEMS: {
    label: "Audit Engagement Management",
    path: "/audit-engagement-management",
  },
  CMS: { label: "Compliance Management", path: "/compliance-management" },
  ARMIS: {
    label: "Audit Resource Management",
    path: "/audit-resource-management/resources",
  },
};

function readable(value) {
  return String(value || "Unknown")
    .replaceAll("_", " ")
    .toLowerCase()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

function displayDate(value) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function toneFor(status) {
  const normalized = String(status || "").toUpperCase();
  if (["HEALTHY", "PASS", "CURRENT", "READY"].includes(normalized))
    return "success";
  if (["STALE", "DEGRADED", "WARN"].includes(normalized)) return "warning";
  if (["BLOCKED", "FAILED", "FAIL", "UNAVAILABLE"].includes(normalized))
    return "danger";
  return "info";
}

function StatusIcon({ status }) {
  const normalized = String(status || "").toUpperCase();
  if (["HEALTHY", "PASS", "CURRENT", "READY"].includes(normalized))
    return <CheckCircle2 className="text-emerald-600" size={18} />;
  if (["BLOCKED", "FAILED", "FAIL", "UNAVAILABLE"].includes(normalized))
    return <XCircle className="text-red-600" size={18} />;
  return <AlertTriangle className="text-amber-600" size={18} />;
}

function LoadingState() {
  return (
    <div aria-label="Loading AIS integration health" className="space-y-5">
      <div className="h-24 animate-pulse rounded-xl bg-slate-200" />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {Array.from({ length: 5 }, (_, index) => (
          <div
            className="h-52 animate-pulse rounded-xl bg-slate-200"
            key={index}
          />
        ))}
      </div>
      <div className="h-48 animate-pulse rounded-xl bg-slate-200" />
    </div>
  );
}

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <XCircle className="mx-auto text-red-600" size={32} />
      <h2 className="mt-3 font-bold text-red-900">
        Integration health unavailable
      </h2>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-red-700">
        {message}
      </p>
      <button
        className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800"
        onClick={onRetry}
        type="button"
      >
        <RefreshCw size={16} /> Retry
      </button>
    </section>
  );
}

function SourceHealthCard({ source, diagnostic }) {
  const module = String(source?.module || "UNKNOWN").toUpperCase();
  const destination = sourceLinks[module] || {
    label: `${module} module`,
    path: "/dashboard",
  };
  const reconciliation = source?.reconciliation?.status || "UNKNOWN";
  const freshness = source?.freshness?.status || "UNKNOWN";
  const issues = diagnostic?.issues || [];

  return (
    <article className="flex min-h-[17rem] flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md">
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 items-center gap-3">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-black text-sky-800">
            {module.slice(0, 2)}
          </span>
          <div className="min-w-0">
            <h2 className="truncate text-sm font-bold text-slate-900">
              {destination.label}
            </h2>
            <p className="mt-0.5 text-xs text-slate-500">
              Authority: {source?.authority || module}
            </p>
          </div>
        </div>
        <StatusBadge tone={toneFor(diagnostic?.status || reconciliation)}>
          {readable(diagnostic?.status || reconciliation)}
        </StatusBadge>
      </div>
      <div className="mt-4 grid gap-2 text-xs">
        <div className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
          <span className="text-slate-600">Availability</span>
          <span className="flex items-center gap-1.5 font-bold text-slate-800">
            <StatusIcon status={source?.available ? "PASS" : "UNAVAILABLE"} />
            {source?.available ? "Available" : "Unavailable"}
          </span>
        </div>
        <div className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
          <span className="text-slate-600">Freshness</span>
          <StatusBadge tone={toneFor(freshness)}>
            {readable(freshness)}
          </StatusBadge>
        </div>
        <div className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
          <span className="text-slate-600">Reconciliation</span>
          <StatusBadge tone={toneFor(reconciliation)}>
            {readable(reconciliation)}
          </StatusBadge>
        </div>
      </div>
      <div className="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
        <span
          className={`rounded-full px-2 py-1 ${source?.scopeRevalidated ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}
        >
          Scope {source?.scopeRevalidated ? "rechecked" : "blocked"}
        </span>
        <span
          className={`rounded-full px-2 py-1 ${source?.confidentialityRevalidated ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}
        >
          Confidentiality{" "}
          {source?.confidentialityRevalidated ? "rechecked" : "blocked"}
        </span>
      </div>
      {issues.length > 0 && (
        <div className="mt-3 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-800">
          <p className="font-bold">Exceptions</p>
          <p className="mt-1 leading-5">{issues.map(readable).join("; ")}</p>
        </div>
      )}
      <div className="mt-auto pt-4">
        <Link
          className="inline-flex items-center gap-1.5 text-xs font-bold text-sky-700 hover:text-sky-900"
          to={destination.path}
        >
          Open authoritative module <ExternalLink size={13} />
        </Link>
        <p className="mt-1 text-[11px] text-slate-400">
          Observed {displayDate(source?.freshness?.observedAt)}
        </p>
      </div>
    </article>
  );
}

export default function AisIntegrationHealthPage() {
  const [health, setHealth] = useState(null);
  const [snapshots, setSnapshots] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [nextHealth, nextSnapshots] = await Promise.all([
        aisContractApi.integrationHealth(),
        aisContractApi.integrationSnapshots(),
      ]);
      setHealth(nextHealth || null);
      setSnapshots(nextSnapshots?.snapshots || []);
    } catch (caught) {
      setError(caught.message || "Unable to load AIS integration health.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  async function captureSnapshot() {
    setBusy(true);
    setError("");
    try {
      const result = await aisContractApi.captureIntegrationSnapshot();
      if (result?.snapshot)
        setSnapshots((current) => [
          result.snapshot,
          ...current.filter((item) => item.id !== result.snapshot.id),
        ]);
    } catch (caught) {
      setError(caught.message || "Unable to capture an integration snapshot.");
    } finally {
      setBusy(false);
    }
  }

  const diagnosticsByModule = useMemo(
    () =>
      Object.fromEntries(
        (health?.diagnostics || []).map((item) => [item.module, item]),
      ),
    [health],
  );
  const exceptions = (health?.diagnostics || []).filter(
    (item) => item.status !== "PASS",
  );
  const failedChecks = health?.validation?.failedChecks || [];
  const sourceModules = health?.sourceModules || [];

  if (loading)
    return (
      <main className="min-w-0 p-3 sm:p-5">
        <RegistryHeader
          icon={Activity}
          title="AIS Integration Health"
          description="Monitor source-system availability, freshness, reconciliation, and scope controls."
        />
        <LoadingState />
      </main>
    );
  if (error && !health)
    return (
      <main className="min-w-0 p-3 sm:p-5">
        <RegistryHeader
          icon={Activity}
          title="AIS Integration Health"
          description="Monitor source-system availability, freshness, reconciliation, and scope controls."
        />
        <ErrorState message={error} onRetry={load} />
      </main>
    );

  return (
    <main className="min-w-0 p-3 sm:p-5">
      <RegistryHeader
        icon={Activity}
        title="AIS Integration Health"
        description="Monitor Core, IAP, AEMS, CMS, and ARMIS source-system health without changing authoritative records."
        actions={
          <div className="flex flex-wrap gap-2">
            <button
              aria-label="Refresh AIS integration health"
              className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50 disabled:opacity-50"
              disabled={loading}
              onClick={() => void load()}
              type="button"
            >
              <RefreshCw className={loading ? "animate-spin" : ""} size={16} />{" "}
              Refresh
            </button>
            <button
              className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50"
              disabled={busy}
              onClick={() => void captureSnapshot()}
              type="button"
            >
              <History size={16} />
              {busy ? "Capturing..." : "Capture snapshot"}
            </button>
          </div>
        }
      />
      {error && (
        <div className="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          {error}
        </div>
      )}
      <section
        className={`mb-5 rounded-2xl border p-5 shadow-sm ${health?.status === "HEALTHY" ? "border-emerald-200 bg-emerald-50" : "border-red-200 bg-red-50"}`}
      >
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <StatusIcon status={health?.status} />
            <div>
              <h2 className="font-bold text-slate-900">
                Source integration {readable(health?.status)}
              </h2>
              <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-700">
                {health?.status === "HEALTHY"
                  ? "All source contracts are available, scope-checked, and reconciled for read-only AIS use."
                  : "AIS will not aggregate or generate reports until the blocked source exceptions are resolved by the authoritative module."}
              </p>
              <div className="mt-3 flex flex-wrap items-center gap-2">
                <StatusBadge tone={toneFor(health?.status)}>
                  {readable(health?.status)}
                </StatusBadge>
                <span className="text-xs text-slate-600">
                  Checked {displayDate(health?.checkedAt)}
                </span>
                <span className="text-xs text-slate-600">
                  Contract {health?.healthContractVersion}
                </span>
              </div>
            </div>
          </div>
          <div className="rounded-lg bg-white/70 px-3 py-2 text-xs font-bold text-slate-700 ring-1 ring-black/5">
            Read-only source monitoring
          </div>
        </div>
      </section>
      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
            Sources monitored
          </p>
          <p className="mt-2 text-2xl font-bold text-slate-900">
            {sourceModules.length}
          </p>
          <p className="mt-1 text-xs text-slate-500">
            Core, IAP, AEMS, CMS, ARMIS
          </p>
        </div>
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-wide text-emerald-700">
            Passing sources
          </p>
          <p className="mt-2 text-2xl font-bold text-emerald-900">
            {
              sourceModules.filter((source) => source.reconciliation?.eligible)
                .length
            }
          </p>
          <p className="mt-1 text-xs text-emerald-700">
            Eligible for read-only analytics
          </p>
        </div>
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-wide text-red-700">
            Exceptions
          </p>
          <p className="mt-2 text-2xl font-bold text-red-900">
            {exceptions.length + failedChecks.length}
          </p>
          <p className="mt-1 text-xs text-red-700">
            Requires authoritative-module review
          </p>
        </div>
        <div className="rounded-xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
            Immutable snapshots
          </p>
          <p className="mt-2 text-2xl font-bold text-sky-900">
            {snapshots.length}
          </p>
          <p className="mt-1 text-xs text-sky-700">
            Actor-owned health history
          </p>
        </div>
      </section>
      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {sourceModules.map((source) => (
          <SourceHealthCard
            diagnostic={diagnosticsByModule[source.module]}
            key={source.module}
            source={source}
          />
        ))}
      </section>
      {!sourceModules.length && (
        <section className="mt-5 rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">
          No source health records are visible in your scope.
        </section>
      )}
      {(exceptions.length > 0 || failedChecks.length > 0) && (
        <section className="mt-5 rounded-xl border border-red-200 bg-white shadow-sm">
          <header className="flex items-center gap-2 border-b border-red-100 px-5 py-4">
            <AlertTriangle className="text-red-600" size={18} />
            <div>
              <h2 className="text-sm font-bold text-slate-900">
                Integration exceptions
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                These are diagnostics only. Resolve them in the authoritative
                source module.
              </p>
            </div>
          </header>
          <div className="grid gap-3 p-5 sm:grid-cols-2">
            {exceptions
              .flatMap((item) =>
                (item.issues || []).map((issue) => ({
                  module: item.module,
                  issue,
                })),
              )
              .concat(
                failedChecks.map((issue) => ({
                  module: "AIS contract",
                  issue,
                })),
              )
              .map((item, index) => (
                <div
                  className="rounded-lg border border-red-100 bg-red-50 p-3 text-xs text-red-900"
                  key={`${item.module}-${item.issue}-${index}`}
                >
                  <strong>{item.module}</strong>
                  <p className="mt-1">{readable(item.issue)}</p>
                </div>
              ))}
          </div>
        </section>
      )}
      <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <header className="flex items-center gap-2 border-b border-slate-200 px-5 py-4">
            <ShieldCheck className="text-sky-700" size={18} />
            <div>
              <h2 className="text-sm font-bold text-slate-900">
                Scope and confidentiality
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                The backend rechecks these controls for every health and
                aggregation request.
              </p>
            </div>
          </header>
          <div className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
            <div className="rounded-lg bg-slate-50 p-3 text-xs">
              <p className="text-slate-500">Office scope</p>
              <strong className="mt-1 block text-slate-900">
                {health?.scope?.officeScope || "Not available"}
              </strong>
            </div>
            <div className="rounded-lg bg-slate-50 p-3 text-xs">
              <p className="text-slate-500">Engagement scope</p>
              <strong className="mt-1 block text-slate-900">
                {health?.scope?.engagementScope || "Not available"}
              </strong>
            </div>
            <div className="rounded-lg bg-slate-50 p-3 text-xs">
              <p className="text-slate-500">Confidential records</p>
              <strong className="mt-1 block text-slate-900">
                {health?.scope?.confidentiality?.confidential
                  ? "Allowed"
                  : "Filtered"}
              </strong>
            </div>
            <div className="rounded-lg bg-slate-50 p-3 text-xs">
              <p className="text-slate-500">Restricted records</p>
              <strong className="mt-1 block text-slate-900">
                {health?.scope?.confidentiality?.restricted
                  ? "Allowed"
                  : "Filtered"}
              </strong>
            </div>
          </div>
        </section>
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="flex items-center gap-2">
            <History className="text-sky-700" size={17} />
            <h2 className="text-sm font-bold text-slate-900">
              Snapshot history
            </h2>
          </div>
          <p className="mt-1 text-xs text-slate-500">
            Only snapshots generated by your account are listed.
          </p>
          <div className="mt-3 divide-y divide-slate-100">
            {snapshots.slice(0, 5).map((snapshot) => (
              <div className="py-3" key={snapshot.id}>
                <div className="flex items-center justify-between gap-2">
                  <strong className="text-xs text-sky-800">
                    {snapshot.snapshotCode}
                  </strong>
                  <StatusBadge tone={toneFor(snapshot.status)}>
                    {readable(snapshot.status)}
                  </StatusBadge>
                </div>
                <p className="mt-1 text-[11px] text-slate-500">
                  {displayDate(snapshot.generatedAt)}
                </p>
                <p className="mt-1 truncate font-mono text-[10px] text-slate-400">
                  {snapshot.sourceContractHashSha256}
                </p>
              </div>
            ))}
            {!snapshots.length && (
              <p className="py-6 text-center text-xs text-slate-500">
                No integration snapshots yet.
              </p>
            )}
          </div>
        </section>
      </div>
      <section className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">
        <strong>Ownership boundary:</strong> AIS observes source health only.
        Use the links on each card to resolve source exceptions. AIS cannot edit
        Core, IAP, AEMS, CMS, or ARMIS records, change provider authority, or
        make professional audit decisions.
      </section>
    </main>
  );
}
