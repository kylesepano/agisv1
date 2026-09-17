import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  Download,
  FileBarChart,
  FileSpreadsheet,
  FileText,
  History,
  LockKeyhole,
  RefreshCw,
  ShieldCheck,
} from "lucide-react";
import { Link } from "react-router";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { aisReportApi } from "../../services/api";

function displayDate(value) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

export default function AisReportsPage() {
  const [catalog, setCatalog] = useState(null);
  const [runs, setRuns] = useState([]);
  const [alerts, setAlerts] = useState([]);
  const [selectedCode, setSelectedCode] = useState("");
  const [selectedRun, setSelectedRun] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setBusy(true);
    setError("");
    try {
      const [nextCatalog, nextRuns, nextAlerts] = await Promise.all([
        aisReportApi.catalog(),
        aisReportApi.runs(),
        aisReportApi.alerts(),
      ]);
      setCatalog(nextCatalog);
      setRuns(nextRuns);
      setAlerts(nextAlerts?.alerts || []);
      setSelectedCode(
        (current) => current || nextCatalog?.reports?.[0]?.code || "",
      );
    } catch (caught) {
      setError(caught.message || "The AIS report workspace is unavailable.");
    } finally {
      setBusy(false);
    }
  }, []);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  const selectedDefinition = useMemo(
    () => catalog?.reports?.find((item) => item.code === selectedCode) || null,
    [catalog, selectedCode],
  );

  const generate = async () => {
    if (!selectedCode) return;
    setBusy(true);
    setError("");
    try {
      const run = await aisReportApi.generate(selectedCode);
      setSelectedRun(run);
      setRuns((current) => [
        run,
        ...current.filter((item) => item.id !== run.id),
      ]);
    } catch (caught) {
      setError(caught.message || "The AIS report could not be generated.");
    } finally {
      setBusy(false);
    }
  };

  const exportRun = async (format) => {
    if (!selectedRun) return;
    setBusy(true);
    setError("");
    try {
      const exportRecord = await aisReportApi.export(selectedRun.id, format);
      setSelectedRun((current) => ({
        ...current,
        exports: [
          exportRecord,
          ...(current.exports || []).filter(
            (item) => item.id !== exportRecord.id,
          ),
        ],
      }));
    } catch (caught) {
      setError(
        caught.message || "The protected export could not be generated.",
      );
    } finally {
      setBusy(false);
    }
  };

  const downloadExport = async (exportRecord) => {
    setBusy(true);
    setError("");
    try {
      await aisReportApi.download(exportRecord);
    } catch (caught) {
      setError(
        caught.message ||
          "The protected export download could not be completed.",
      );
    } finally {
      setBusy(false);
    }
  };

  if (!catalog && error) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-8 text-center">
        <AlertTriangle className="mx-auto text-red-600" />
        <h2 className="mt-3 font-bold text-red-900">AIS reports unavailable</h2>
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

  return (
    <div className="min-w-0 p-3 sm:p-5" data-testid="ais-reports-page">
      <RegistryHeader
        icon={FileBarChart}
        title="AIS Reports and Protected Exports"
        description="Generate immutable, scope-aware analytical reports and download authenticated CSV or PDF outputs."
        actions={
          <div className="flex items-center gap-2">
            <Link
              className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
              to="/audit-intelligence-system"
            >
              Dashboard
            </Link>
            <button
              className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700"
              disabled={busy}
              onClick={() => void load()}
              type="button"
            >
              <RefreshCw className={busy ? "animate-spin" : ""} size={14} />{" "}
              Refresh
            </button>
          </div>
        }
      />
      {error && (
        <div
          className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          role="alert"
        >
          {error}
        </div>
      )}
      <section className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div className="flex items-start gap-3">
          <LockKeyhole className="mt-0.5 shrink-0 text-amber-700" size={19} />
          <div>
            <h2 className="text-sm font-bold text-amber-950">
              Review-only analytical outputs
            </h2>
            <p className="mt-1 text-xs leading-5 text-amber-900">
              Reports summarize scope-filtered records. They do not validate
              findings, close recommendations, issue notices, or change any
              source workflow. Exports are protected by <code>ais.export</code>.
            </p>
          </div>
        </div>
      </section>
      {alerts.length > 0 && (
        <section className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4">
          <div className="flex items-center gap-2">
            <AlertTriangle className="text-red-700" size={18} />
            <h2 className="text-sm font-bold text-red-900">
              Review indicators
            </h2>
            <StatusBadge tone="warning">Human review required</StatusBadge>
          </div>
          <div className="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            {alerts.map((item) => (
              <div
                className="rounded-lg border border-red-100 bg-white p-3"
                key={item.code}
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="text-xs font-bold text-slate-800">
                    {item.label}
                  </span>
                  <strong className="text-red-700">{item.value}</strong>
                </div>
                <p className="mt-1 text-[11px] text-slate-500">
                  Source: {item.sourceModule} · review only
                </p>
              </div>
            ))}
          </div>
        </section>
      )}
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <main className="min-w-0 space-y-5">
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h2 className="text-sm font-bold text-slate-900">
                  Report catalog
                </h2>
                <p className="mt-1 text-xs text-slate-500">
                  Each report is generated from a reproducible AIS-3 snapshot.
                </p>
              </div>
              <button
                className="inline-flex items-center gap-2 rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                disabled={!selectedCode || busy}
                onClick={() => void generate()}
                type="button"
              >
                <FileBarChart size={14} /> Generate report
              </button>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              {(catalog?.reports || []).map((report) => (
                <button
                  className={`rounded-lg border p-4 text-left transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-sm ${selectedCode === report.code ? "border-sky-500 bg-sky-50 ring-1 ring-sky-200" : "border-slate-200 bg-white"}`}
                  key={report.code}
                  onClick={() => setSelectedCode(report.code)}
                  type="button"
                >
                  <strong className="text-sm text-slate-900">
                    {report.title}
                  </strong>
                  <p className="mt-1 text-xs leading-5 text-slate-500">
                    {report.description}
                  </p>
                </button>
              ))}
            </div>
            {selectedDefinition && (
              <p className="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Selected:{" "}
                <strong className="text-slate-800">
                  {selectedDefinition.title}
                </strong>{" "}
                · {selectedDefinition.columns.length} output columns
              </p>
            )}
          </section>
          {selectedRun && (
            <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
              <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                  <div className="flex items-center gap-2">
                    <FileText className="text-sky-700" size={17} />
                    <h2 className="text-sm font-bold text-slate-900">
                      {selectedRun.title}
                    </h2>
                    <StatusBadge tone="success">Immutable</StatusBadge>
                  </div>
                  <p className="mt-1 text-xs text-slate-500">
                    {selectedRun.displayCode} · {selectedRun.rowCount} rows ·{" "}
                    {displayDate(selectedRun.generatedAt)}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  {catalog?.canExport && (
                    <>
                      <button
                        className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-50"
                        disabled={busy}
                        onClick={() => void exportRun("csv")}
                        type="button"
                      >
                        <FileSpreadsheet size={14} /> CSV
                      </button>
                      <button
                        className="inline-flex items-center gap-2 rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                        disabled={busy}
                        onClick={() => void exportRun("pdf")}
                        type="button"
                      >
                        <Download size={14} /> PDF
                      </button>
                    </>
                  )}
                </div>
              </header>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[620px] text-left text-xs">
                  <thead className="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                      {(selectedRun.resultSnapshot?.columns || []).map(
                        (column) => (
                          <th className="px-4 py-3 font-bold" key={column.key}>
                            {column.label}
                          </th>
                        ),
                      )}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {(selectedRun.resultSnapshot?.rows || []).map(
                      (row, index) => (
                        <tr
                          className="hover:bg-slate-50"
                          key={`${selectedRun.id}-${index}`}
                        >
                          {(selectedRun.resultSnapshot?.columns || []).map(
                            (column) => (
                              <td
                                className="px-4 py-3 text-slate-700"
                                key={column.key}
                              >
                                {String(row[column.key] ?? "-")}
                              </td>
                            ),
                          )}
                        </tr>
                      ),
                    )}
                    {!selectedRun.rowCount && (
                      <tr>
                        <td
                          className="px-4 py-8 text-center text-slate-500"
                          colSpan={
                            selectedRun.resultSnapshot?.columns?.length || 1
                          }
                        >
                          No rows were available in this scope.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
              {(selectedRun.exports || []).length > 0 && (
                <div className="border-t border-slate-200 px-5 py-4">
                  <div className="flex items-center justify-between gap-3">
                    <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500">
                      Protected exports
                    </h3>
                    <span className="text-[11px] text-slate-400">
                      Authenticated download only
                    </span>
                  </div>
                  <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    {selectedRun.exports.map((exportRecord) => (
                      <div
                        className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                        key={exportRecord.id}
                      >
                        <div className="min-w-0">
                          <p className="truncate text-xs font-semibold text-slate-700">
                            {exportRecord.fileName}
                          </p>
                          <p className="mt-1 text-[11px] text-slate-500">
                            {exportRecord.format} · v
                            {exportRecord.versionNumber} · SHA-256{" "}
                            {exportRecord.checksumSha256?.slice(0, 12)}…
                          </p>
                        </div>
                        <button
                          className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                          disabled={busy}
                          onClick={() => void downloadExport(exportRecord)}
                          type="button"
                        >
                          <Download size={13} /> Download
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </section>
          )}
        </main>
        <aside className="space-y-5">
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2">
              <History className="text-sky-700" size={17} />
              <h2 className="text-sm font-bold text-slate-900">Run history</h2>
            </div>
            <p className="mt-1 text-xs text-slate-500">
              Only runs generated by your account are listed.
            </p>
            <div className="mt-3 divide-y divide-slate-100">
              {runs.map((run) => (
                <button
                  className={`block w-full px-2 py-3 text-left hover:bg-sky-50 ${selectedRun?.id === run.id ? "bg-sky-50" : ""}`}
                  key={run.id}
                  onClick={() => setSelectedRun(run)}
                  type="button"
                >
                  <div className="flex items-center justify-between gap-2">
                    <strong className="text-xs text-sky-800">
                      {run.displayCode}
                    </strong>
                    <span className="text-[11px] text-slate-500">
                      {run.rowCount} rows
                    </span>
                  </div>
                  <p className="mt-1 truncate text-xs font-semibold text-slate-700">
                    {run.title}
                  </p>
                  <p className="mt-1 text-[11px] text-slate-500">
                    {displayDate(run.generatedAt)}
                  </p>
                </button>
              ))}
              {!runs.length && (
                <p className="py-6 text-center text-xs text-slate-500">
                  No AIS report runs yet.
                </p>
              )}
            </div>
          </section>
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2">
              <ShieldCheck className="text-emerald-700" size={17} />
              <h2 className="text-sm font-bold text-slate-900">
                Output controls
              </h2>
            </div>
            <div className="mt-3 grid gap-2 text-xs text-slate-600">
              <div className="flex justify-between gap-2">
                <span>Scope pinned</span>
                <strong className="text-emerald-700">Yes</strong>
              </div>
              <div className="flex justify-between gap-2">
                <span>Immutable runs</span>
                <strong className="text-emerald-700">Yes</strong>
              </div>
              <div className="flex justify-between gap-2">
                <span>CSV formula mitigation</span>
                <strong className="text-emerald-700">Yes</strong>
              </div>
              <div className="flex justify-between gap-2">
                <span>Export permission</span>
                <strong
                  className={
                    catalog?.canExport ? "text-emerald-700" : "text-slate-500"
                  }
                >
                  {catalog?.canExport ? "Granted" : "View only"}
                </strong>
              </div>
            </div>
          </section>
        </aside>
      </div>
    </div>
  );
}
