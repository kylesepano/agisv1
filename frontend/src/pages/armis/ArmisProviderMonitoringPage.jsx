import {
  Activity,
  AlertTriangle,
  CheckCircle2,
  Eye,
  History,
  RefreshCw,
  ServerCog,
  XCircle,
} from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { useAuth } from "../../auth/auth-context";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { hasPermission } from "../../config/navigation";
import { armisProviderMonitoringApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

function collection(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.data)) return value.data;
  return [];
}

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

const statusTone = {
  HEALTHY: "success",
  PASS: "success",
  DEGRADED: "warning",
  WARN: "warning",
  FAILED: "danger",
  FAIL: "danger",
};

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <XCircle className="mx-auto text-red-600" size={34} />
      <h2 className="mt-3 font-bold text-red-900">ARMIS monitoring unavailable</h2>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-red-700">{message}</p>
      <button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800" onClick={onRetry} type="button"><RefreshCw size={16} /> Retry</button>
    </section>
  );
}

function MetricCard({ label, value, note, tone = "sky", icon: Icon }) {
  const tones = {
    sky: "border-sky-200 bg-sky-50 text-sky-900",
    emerald: "border-emerald-200 bg-emerald-50 text-emerald-900",
    amber: "border-amber-200 bg-amber-50 text-amber-900",
    red: "border-red-200 bg-red-50 text-red-900",
  };
  return <section className={`rounded-xl border p-4 shadow-sm ${tones[tone] || tones.sky}`}><div className="flex items-center justify-between gap-3"><p className="text-xs font-bold uppercase tracking-wide">{label}</p><Icon size={18} /></div><p className="mt-2 text-2xl font-bold">{value}</p><p className="mt-1 text-xs opacity-80">{note}</p></section>;
}

function CheckTable({ checks }) {
  return checks.length === 0 ? <p className="px-5 py-10 text-center text-sm text-slate-500">No diagnostic checks returned.</p> : <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left"><thead className="bg-slate-50"><tr><th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Check</th><th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Status</th><th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Finding</th><th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Observed</th></tr></thead><tbody>{checks.map((check) => <tr className="border-b border-slate-100 align-top" key={check.code}><td className="px-4 py-3 text-xs font-bold text-slate-800">{readable(check.code)}</td><td className="px-4 py-3"><StatusBadge tone={statusTone[check.status] || "info"}>{readable(check.status)}</StatusBadge></td><td className="max-w-sm px-4 py-3 text-xs leading-5 text-slate-600">{check.message}</td><td className="max-w-sm whitespace-pre-wrap px-4 py-3 font-mono text-[11px] leading-5 text-slate-600">{JSON.stringify(check.observed || {}, null, 2)}</td></tr>)}</tbody></table></div>;
}

function HistoryList({ checks, selectedId, onSelect }) {
  return <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><header className="flex items-center gap-2 border-b border-slate-200 px-4 py-3"><History className="text-sky-700" size={17} /><div><h2 className="text-sm font-bold text-slate-900">Monitoring history</h2><p className="text-xs text-slate-500">Immutable provider verification snapshots.</p></div></header>{checks.length === 0 ? <p className="px-4 py-10 text-center text-sm text-slate-500">No monitoring checks recorded.</p> : <div className="divide-y divide-slate-100">{checks.map((check) => <button className={`block w-full px-4 py-3 text-left transition hover:bg-sky-50 ${selectedId === check.id ? "bg-sky-50" : "bg-white"}`} key={check.id} onClick={() => onSelect(check)} type="button"><div className="flex items-start justify-between gap-3"><strong className="text-sm text-sky-800">{check.displayCode}</strong><StatusBadge tone={statusTone[check.overallStatus] || "info"}>{readable(check.overallStatus)}</StatusBadge></div><p className="mt-1 text-xs text-slate-600">{readable(check.providerMode)} · {readable(check.configuredMode)}</p><p className="mt-1 text-xs text-slate-500">{displayDate(check.performedAt)}</p></button>)}</div>}</section>;
}

export default function ArmisProviderMonitoringPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [status, setStatus] = useState(null);
  const [checks, setChecks] = useState([]);
  const [selectedCheck, setSelectedCheck] = useState(null);
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);
  const [error, setError] = useState("");
  const canMonitor = hasPermission(user, "armis.provider.monitor");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [nextStatus, nextChecks] = await Promise.all([armisProviderMonitoringApi.getStatus(), armisProviderMonitoringApi.getChecks()]);
      const normalizedChecks = collection(nextChecks);
      setStatus(nextStatus || {});
      setChecks(normalizedChecks);
      setSelectedCheck((current) => normalizedChecks.find((item) => item.id === current?.id) || normalizedChecks[0] || null);
    } catch (requestError) {
      setError(requestError.message || "Unable to load ARMIS provider monitoring data.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  async function runCheck() {
    setRunning(true);
    try {
      const check = await armisProviderMonitoringApi.runCheck();
      if (!check) throw new Error("The monitoring check was not returned by the server.");
      setChecks((current) => [check, ...current.filter((item) => item.id !== check.id)]);
      setSelectedCheck(check);
      toast.success(`ARMIS provider check completed: ${readable(check.overallStatus)}.`);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "Unable to run the ARMIS provider check.");
    } finally {
      setRunning(false);
    }
  }

  const liveChecks = collection(status?.checks);
  const displayedChecks = collection(selectedCheck?.checks).length ? collection(selectedCheck.checks) : liveChecks;
  const overallStatus = selectedCheck?.overallStatus || status?.overallStatus || "UNKNOWN";
  const failedCount = displayedChecks.filter((check) => check.status === "FAIL").length;
  const warningCount = displayedChecks.filter((check) => check.status === "WARN").length;

  return <main className="min-w-0 p-4 sm:p-5"><RegistryHeader actions={<div className="flex flex-wrap gap-2"><button aria-label="Refresh ARMIS provider monitoring" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50 disabled:opacity-50" disabled={loading} onClick={load} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button>{canMonitor && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50" disabled={running} onClick={runCheck} type="button"><Activity size={16} />{running ? "Checking…" : "Run provider check"}</button>}</div>} description="Verify the active ARMIS resource ledger and AEMS read path without changing operational records." icon={Activity} title="ARMIS Provider Monitoring" />
    {error && <div className="mb-5"><ErrorState message={error} onRetry={load} /></div>}
    {loading ? <div aria-label="Loading ARMIS provider monitoring" className="grid gap-3 md:grid-cols-4"><div className="h-28 animate-pulse rounded-xl bg-slate-200" /><div className="h-28 animate-pulse rounded-xl bg-slate-200" /><div className="h-28 animate-pulse rounded-xl bg-slate-200" /><div className="h-28 animate-pulse rounded-xl bg-slate-200" /></div> : <>
      <section className={`rounded-2xl border p-5 shadow-sm ${overallStatus === "HEALTHY" ? "border-emerald-200 bg-emerald-50" : overallStatus === "FAILED" ? "border-red-200 bg-red-50" : "border-amber-200 bg-amber-50"}`}><div className="flex flex-wrap items-start justify-between gap-4"><div className="flex items-start gap-3">{overallStatus === "HEALTHY" ? <CheckCircle2 className="mt-0.5 shrink-0 text-emerald-700" size={24} /> : overallStatus === "FAILED" ? <XCircle className="mt-0.5 shrink-0 text-red-700" size={24} /> : <AlertTriangle className="mt-0.5 shrink-0 text-amber-700" size={24} />}<div><h2 className="font-bold text-slate-900">Provider verification status</h2><p className="mt-1 text-sm leading-6 text-slate-700">{selectedCheck ? `Immutable check ${selectedCheck.displayCode} was performed ${displayDate(selectedCheck.performedAt)}.` : "Live diagnostics are shown below. Run a check to preserve an immutable verification snapshot."}</p><div className="mt-3 flex flex-wrap items-center gap-2"><StatusBadge tone={statusTone[overallStatus] || "info"}>{readable(overallStatus)}</StatusBadge><span className="text-xs text-slate-600">Effective mode: {readable(selectedCheck?.providerMode || status?.providerMode)}</span></div></div></div><div className="rounded-lg bg-white/70 px-3 py-2 text-xs font-bold text-slate-700 ring-1 ring-black/5">Read-only operational verification</div></div></section>
      <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><MetricCard icon={ServerCog} label="Effective provider" value={readable(selectedCheck?.providerMode || status?.providerMode)} note="Authority gate remains separate" /><MetricCard icon={CheckCircle2} label="Passing checks" value={displayedChecks.filter((check) => check.status === "PASS").length} note="Current verification snapshot" tone="emerald" /><MetricCard icon={AlertTriangle} label="Warnings" value={warningCount} note="Review freshness or readiness" tone="amber" /><MetricCard icon={XCircle} label="Failures" value={failedCount} note="Requires operational attention" tone="red" /></div>
      <section className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950"><strong>Control boundary:</strong> this page verifies ARMIS provider health only. It cannot change authority, mutate IAP or ARMIS ledgers, or make a professional decision. ARMIS remains the sole operational resource provider.</section>
      <div className="mt-5 grid min-w-0 gap-5 xl:grid-cols-[19rem_minmax(0,1fr)]"><HistoryList checks={checks} onSelect={setSelectedCheck} selectedId={selectedCheck?.id} /><section className="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4"><div className="flex items-center gap-2"><Eye className="text-sky-700" size={18} /><div><h2 className="font-bold text-slate-900">Diagnostic checks</h2><p className="text-xs text-slate-500">{selectedCheck ? `${selectedCheck.displayCode} · SHA-256 ${selectedCheck.resultChecksumSha256?.slice(0, 16) || "-"}…` : "Current provider diagnostics"}</p></div></div><StatusBadge tone={statusTone[overallStatus] || "info"}>{readable(overallStatus)}</StatusBadge></header><CheckTable checks={displayedChecks} />{selectedCheck && <footer className="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs leading-5 text-slate-600"><strong>Performed by:</strong> {selectedCheck.performedBy?.name || "Unknown actor"} · {displayDate(selectedCheck.performedAt)} · Immutable snapshot</footer>}</section></div>
      <section className="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2"><div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Configured mode</p><p className="mt-1 text-sm font-bold text-slate-900">{readable(selectedCheck?.configuredMode || status?.configuredMode)}</p></div><div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Operational source</p><p className="mt-1 text-sm font-bold text-slate-900">ARMIS resource ledger</p><p className="mt-1 text-xs text-slate-500">Current approved ARMIS revisions are authoritative; no IAP fallback or provider reconciliation is required.</p></div></section>
    </>}
  </main>;
}
