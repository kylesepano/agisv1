import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  CalendarRange,
  CheckCircle2,
  Download,
  FileBarChart,
  FileSpreadsheet,
  FileText,
  History,
  RefreshCw,
  Search,
  ShieldCheck,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { cmsApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

const statusOptions = [
  "MONITORING",
  "IMPLEMENTED",
  "PARTIALLY_IMPLEMENTED",
  "ACCEPTED_RISK",
  "NO_LONGER_APPLICABLE",
  "CLOSED",
];

const reportIcons = {
  "portfolio-status": ShieldCheck,
  "implementation-progress": CheckCircle2,
  "target-date-monitoring": CalendarRange,
  "closure-readiness": FileBarChart,
};

function itemsFromCollection(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.data)) return value.data;
  return [];
}

function displayDate(value, includeTime = true) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    ...(includeTime ? { timeStyle: "short" } : {}),
  }).format(date);
}

function readable(value) {
  return String(value || "")
    .replaceAll("_", " ")
    .toLowerCase()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

function formatBytes(value) {
  const size = Number(value || 0);
  if (!size) return "0 B";
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function valueForDisplay(value) {
  if (value === null || value === undefined || value === "") return "-";
  return typeof value === "object" ? JSON.stringify(value) : String(value);
}

function ReportCard({ report, selected, onSelect }) {
  const Icon = reportIcons[report.code] || FileBarChart;
  return (
    <button
      aria-pressed={selected}
      className={`group min-h-32 rounded-xl border p-4 text-left shadow-sm transition duration-200 hover:-translate-y-1 hover:border-sky-300 hover:shadow-md ${
        selected
          ? "border-sky-500 bg-sky-50 ring-2 ring-sky-100"
          : "border-slate-200 bg-white"
      }`}
      onClick={onSelect}
      type="button"
    >
      <span
        className={`mb-3 grid h-10 w-10 place-items-center rounded-lg transition group-hover:scale-110 ${
          selected
            ? "bg-sky-600 text-white"
            : "bg-slate-100 text-slate-600 group-hover:bg-sky-100 group-hover:text-sky-700"
        }`}
      >
        <Icon size={20} />
      </span>
      <strong className="block text-sm leading-5 text-slate-900">
        {report.title}
      </strong>
      <span className="mt-1 block text-xs leading-5 text-slate-500">
        {report.description}
      </span>
    </button>
  );
}

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <AlertTriangle className="mx-auto text-red-600" size={34} />
      <h2 className="mt-3 font-bold text-red-900">Reports workspace unavailable</h2>
      <p className="mx-auto mt-2 max-w-xl text-sm text-red-700">{message}</p>
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

function RunHistory({ runs, selectedId, onSelect }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
        <History className="text-sky-700" size={17} />
        <div>
          <h2 className="text-sm font-bold text-slate-900">Report run history</h2>
          <p className="text-xs text-slate-500">Immutable snapshots generated in your authorized scope.</p>
        </div>
      </header>
      {runs.length === 0 ? (
        <p className="px-4 py-8 text-center text-sm text-slate-500">No report runs yet.</p>
      ) : (
        <div className="divide-y divide-slate-100">
          {runs.map((item) => (
            <button
              className={`block w-full px-4 py-3 text-left transition hover:bg-sky-50 ${
                selectedId === item.id ? "bg-sky-50" : "bg-white"
              }`}
              key={item.id}
              onClick={() => onSelect(item)}
              type="button"
            >
              <div className="flex items-start justify-between gap-3">
                <strong className="text-sm text-sky-800">
                  {item.displayCode || `Run #${item.id}`}
                </strong>
                <span className="text-[11px] text-slate-500">{item.rowCount ?? 0} rows</span>
              </div>
              <p className="mt-1 truncate text-xs font-semibold text-slate-700">{item.title || readable(item.reportCode)}</p>
              <p className="mt-1 text-xs text-slate-500">{displayDate(item.generatedAt)}</p>
            </button>
          ))}
        </div>
      )}
    </section>
  );
}

export default function CmsReportsPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [searchParams] = useSearchParams();
  const [catalog, setCatalog] = useState({ reports: [], formats: [], scope: {}, canExport: false });
  const [runs, setRuns] = useState([]);
  const [selectedCode, setSelectedCode] = useState("");
  const [filters, setFilters] = useState({
    search: "",
    status: "",
    officeId: "",
    riskCode: "",
    dateFrom: "",
    dateTo: "",
  });
  const [run, setRun] = useState(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [exporting, setExporting] = useState("");
  const [error, setError] = useState("");

  const canExport = catalog.canExport && hasPermission(user, "cms.report.export");
  const selectedReport = useMemo(
    () => catalog.reports.find((item) => item.code === selectedCode) || null,
    [catalog.reports, selectedCode],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [catalogData, history] = await Promise.all([
        cmsApi.getReportCatalog(),
        cmsApi.getReportRuns(),
      ]);
      const nextCatalog = catalogData || {};
      const nextRuns = itemsFromCollection(history);
      const requestedCode = searchParams.get("report");
      const nextCode = nextCatalog.reports?.some((item) => item.code === requestedCode)
        ? requestedCode
        : nextCatalog.reports?.[0]?.code || "";
      setCatalog({ reports: [], formats: [], scope: {}, canExport: false, ...nextCatalog });
      setRuns(nextRuns);
      setSelectedCode(nextCode);
      setRun(nextRuns[0] || null);
    } catch (requestError) {
      setError(requestError.message || "Unable to load CMS reports.");
    } finally {
      setLoading(false);
    }
  }, [searchParams]);

  useEffect(() => {
    const timer = window.setTimeout(() => load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  function selectReport(report) {
    setSelectedCode(report.code);
    setRun(null);
    setFilters({ search: "", status: "", officeId: "", riskCode: "", dateFrom: "", dateTo: "" });
    setError("");
  }

  function updateFilter(name, value) {
    setFilters((current) => ({ ...current, [name]: value }));
  }

  async function generate() {
    if (!selectedCode) return;
    setGenerating(true);
    setError("");
    try {
      const nextRun = await cmsApi.generateReport(
        selectedCode,
        Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== "")),
      );
      if (!nextRun) throw new Error("The report run was not returned by the server.");
      setRun(nextRun);
      setRuns((current) => [nextRun, ...current.filter((item) => item.id !== nextRun.id)]);
      toast.success("CMS report generated from an immutable snapshot.");
    } catch (requestError) {
      setError(requestError.message || "Unable to generate this report.");
      toast.error(requestError.message || "Unable to generate this report.");
    } finally {
      setGenerating(false);
    }
  }

  async function generateExport(format) {
    if (!run) return;
    setExporting(format);
    try {
      const nextExport = await cmsApi.createReportExport(run.id, format);
      setRun((current) => ({
        ...current,
        exports: [nextExport, ...itemsFromCollection(current.exports).filter((item) => item.id !== nextExport.id)],
      }));
      toast.success(`${format.toUpperCase()} export generated. Use the download action below to retrieve it.`);
    } catch (requestError) {
      toast.error(requestError.message || `Unable to generate the ${format.toUpperCase()} export.`);
    } finally {
      setExporting("");
    }
  }

  async function downloadExport(exportItem) {
    setExporting(`download-${exportItem.id}`);
    try {
      await cmsApi.downloadReportExport(exportItem.id, exportItem.fileName);
      toast.success(`${exportItem.format.toUpperCase()} export downloaded.`);
    } catch (requestError) {
      toast.error(requestError.message || "Unable to download this export.");
    } finally {
      setExporting("");
    }
  }

  function chooseRun(nextRun) {
    setRun(nextRun);
    setSelectedCode(nextRun.reportCode);
  }

  const columns = run?.columns?.length ? run.columns : selectedReport?.columns || [];
  const exports = itemsFromCollection(run?.exports);
  const scope = catalog.scope || {};

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        actions={
          <button
            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 disabled:opacity-50"
            disabled={loading}
            onClick={load}
            type="button"
          >
            <RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh
          </button>
        }
        description="Generate reproducible, scope-aware CMS reports and download protected CSV or PDF exports."
        icon={FileBarChart}
        title="CMS Reports & Exports"
      />

      {error && <div className="mb-5"><ErrorState message={error} onRetry={load} /></div>}

      {loading ? (
        <div aria-label="Loading CMS reports" className="grid gap-3 md:grid-cols-2 2xl:grid-cols-4">
          {Array.from({ length: 4 }).map((_, index) => <div className="h-32 animate-pulse rounded-xl bg-slate-200" key={index} />)}
        </div>
      ) : (
        <div className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="min-w-0">
            <section className="mb-5 grid gap-3 md:grid-cols-2 2xl:grid-cols-4">
              {catalog.reports.map((report) => (
                <ReportCard key={report.code} onSelect={() => selectReport(report)} report={report} selected={report.code === selectedCode} />
              ))}
            </section>

            <section className="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-lg font-bold text-slate-900">{selectedReport?.title || "Select a report"}</h2>
                  <p className="mt-1 text-sm leading-5 text-slate-500">{selectedReport?.description || "Choose one of the report definitions above."}</p>
                </div>
                <span className="rounded-lg bg-sky-50 px-3 py-2 text-xs font-bold text-sky-800">Backend-generated snapshot</span>
              </div>
              <div className="mt-5 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <label className="lg:col-span-2">
                  <span className="text-xs font-bold uppercase tracking-wide text-slate-600">Search</span>
                  <span className="relative block"><Search className="absolute left-3 top-3 text-slate-400" size={16} /><input aria-label="Search report" className={`${inputClass} pl-9`} onChange={(event) => updateFilter("search", event.target.value)} placeholder="Case, recommendation, office, or status" value={filters.search} /></span>
                </label>
                <label><span className="text-xs font-bold uppercase tracking-wide text-slate-600">Status</span><select aria-label="Report status" className={inputClass} onChange={(event) => updateFilter("status", event.target.value)} value={filters.status}><option value="">All statuses</option>{statusOptions.map((status) => <option key={status} value={status}>{readable(status)}</option>)}</select></label>
                <label><span className="text-xs font-bold uppercase tracking-wide text-slate-600">Responsible office ID</span><input aria-label="Responsible office ID" className={inputClass} min="1" onChange={(event) => updateFilter("officeId", event.target.value)} placeholder="Optional" type="number" value={filters.officeId} /></label>
                <label><span className="text-xs font-bold uppercase tracking-wide text-slate-600">Risk code</span><input aria-label="Risk code" className={inputClass} onChange={(event) => updateFilter("riskCode", event.target.value)} placeholder="For example HIGH" value={filters.riskCode} /></label>
                <label><span className="text-xs font-bold uppercase tracking-wide text-slate-600">Opened from</span><input aria-label="Opened from" className={inputClass} onChange={(event) => updateFilter("dateFrom", event.target.value)} type="date" value={filters.dateFrom} /></label>
                <label><span className="text-xs font-bold uppercase tracking-wide text-slate-600">Opened to</span><input aria-label="Opened to" className={inputClass} onChange={(event) => updateFilter("dateTo", event.target.value)} type="date" value={filters.dateTo} /></label>
              </div>
              <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p className="text-xs leading-5 text-slate-500">Filters are evaluated by the backend and preserved in the immutable run.</p>
                <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50" disabled={!selectedCode || generating} onClick={generate} type="button"><FileBarChart size={16} />{generating ? "Generating..." : "Generate report"}</button>
              </div>
            </section>

            {run ? (
              <section className="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2"><h2 className="text-lg font-bold text-slate-900">{run.displayCode || `Run #${run.id}`}</h2><StatusBadge tone="info">{readable(run.reportCode)}</StatusBadge></div>
                    <p className="mt-1 text-xs text-slate-500">Generated {displayDate(run.generatedAt)} · {run.rowCount ?? 0} rows · Query {run.sourceQueryVersion}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {canExport ? <><button className="inline-flex h-9 items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-xs font-bold text-emerald-800 hover:bg-emerald-100 disabled:opacity-50" disabled={Boolean(exporting)} onClick={() => generateExport("csv")} type="button"><FileSpreadsheet size={15} />{exporting === "csv" ? "Generating..." : "Generate CSV"}</button><button className="inline-flex h-9 items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-3 text-xs font-bold text-red-800 hover:bg-red-100 disabled:opacity-50" disabled={Boolean(exporting)} onClick={() => generateExport("pdf")} type="button"><FileText size={15} />{exporting === "pdf" ? "Generating..." : "Generate PDF"}</button></> : <span className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">Export permission not granted</span>}
                  </div>
                </header>
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs text-slate-600"><span>SHA-256: <strong className="font-mono text-slate-800" title={run.resultChecksumSha256}>{run.resultChecksumSha256 ? `${run.resultChecksumSha256.slice(0, 16)}...` : "-"}</strong></span><span>Snapshot is immutable and scope-rechecked on access.</span></div>
                <div className="overflow-x-auto"><table className="w-full min-w-max text-left"><thead className="bg-slate-50"><tr>{columns.map((column) => <th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600" key={column.key}>{column.label}</th>)}</tr></thead><tbody>{(run.rows || []).map((row, rowIndex) => <tr className="border-b border-slate-100 align-top transition hover:bg-sky-50/60" key={`${run.id}-${rowIndex}`}>{columns.map((column) => <td className="max-w-sm whitespace-pre-line px-4 py-3 text-xs leading-5 text-slate-700" key={column.key}>{valueForDisplay(row[column.key])}</td>)}</tr>)}{!(run.rows || []).length && <tr><td className="px-5 py-12 text-center text-sm text-slate-500" colSpan={Math.max(columns.length, 1)}>No records matched the selected report filters.</td></tr>}</tbody></table></div>
                <footer className="border-t border-slate-200 px-5 py-3"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Generated exports</p>{exports.length ? <div className="mt-2 flex flex-wrap gap-2">{exports.map((item) => <button className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50 disabled:opacity-50" disabled={Boolean(exporting)} key={item.id} onClick={() => downloadExport(item)} type="button"><Download size={14} />{item.format.toUpperCase()} v{item.versionNumber} · {formatBytes(item.fileSize)}{exporting === `download-${item.id}` ? " · Downloading..." : ""}</button>)}</div> : <p className="mt-1 text-xs text-slate-500">No files generated for this snapshot yet.</p>}</footer>
              </section>
            ) : <section className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center"><FileBarChart className="mx-auto text-slate-400" size={34} /><h2 className="mt-3 font-bold text-slate-800">Generate a report snapshot</h2><p className="mx-auto mt-2 max-w-xl text-sm text-slate-500">The report will contain only records visible to your account at generation time.</p></section>}
          </div>
          <aside className="grid content-start gap-5">
            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><h2 className="text-sm font-bold text-slate-900">Authorized scope</h2><div className="mt-3 grid gap-2 text-xs text-slate-600"><div className="flex items-center justify-between gap-3"><span>Portfolio visibility</span><strong className="text-slate-800">{scope.portfolioWide ? "Portfolio-wide" : "Scoped"}</strong></div><div className="flex items-center justify-between gap-3"><span>Assignment scope</span><strong className="text-slate-800">{scope.assignmentScoped ? "Applied" : "Not applied"}</strong></div><div className="flex items-center justify-between gap-3"><span>Confidential records</span><strong className="text-slate-800">{scope.confidentiality?.confidential ? "Allowed" : "Filtered"}</strong></div><div className="flex items-center justify-between gap-3"><span>Restricted records</span><strong className="text-slate-800">{scope.confidentiality?.restricted ? "Allowed" : "Filtered"}</strong></div></div><p className="mt-3 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-500">Report runs, exports, and downloads are authenticated and rechecked against this scope.</p></section>
            <RunHistory onSelect={chooseRun} runs={runs} selectedId={run?.id} />
          </aside>
        </div>
      )}
    </main>
  );
}
