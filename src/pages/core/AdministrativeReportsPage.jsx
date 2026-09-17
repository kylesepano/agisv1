import { useCallback, useEffect, useMemo, useState } from "react";
import { AlertTriangle, Download, FileBarChart, History, RefreshCw, ShieldCheck } from "lucide-react";
import RegistryHeader from "../../components/ui/RegistryHeader";
import { coreAdministrativeReportApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

function dateTime(value) {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "—" : new Intl.DateTimeFormat("en-PH", { dateStyle: "medium", timeStyle: "short" }).format(date);
}

function rowsFrom(run) {
  return run?.result_snapshot?.rows || run?.resultSnapshot?.rows || [];
}

function columnsFrom(run) {
  return run?.result_snapshot?.columns || run?.resultSnapshot?.columns || [];
}

export default function AdministrativeReportsPage() {
  const toast = useToast();
  const [catalog, setCatalog] = useState(null);
  const [runs, setRuns] = useState([]);
  const [selected, setSelected] = useState(null);
  const [selectedCode, setSelectedCode] = useState("");
  const [loading, setLoading] = useState(true);
  const [working, setWorking] = useState(false);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true); setError("");
    try {
      const [definition, history] = await Promise.all([coreAdministrativeReportApi.catalog(), coreAdministrativeReportApi.runs()]);
      setCatalog(definition); setRuns(history);
      setSelected((current) => current || history[0] || null);
      setSelectedCode((current) => current || definition?.reports?.[0]?.code || "");
    } catch (caught) { setError(caught.message || "Administrative reports are unavailable."); }
    finally { setLoading(false); }
  }, []);

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { load(); }, [load]);

  const selectedDefinition = useMemo(() => (catalog?.reports || []).find((report) => report.code === selectedCode), [catalog, selectedCode]);

  async function generate() {
    if (!selectedCode) return;
    setWorking(true);
    try {
      const run = await coreAdministrativeReportApi.generate(selectedCode);
      setRuns((current) => [run, ...current]); setSelected(run);
      toast.success("Administrative report generated from a protected snapshot.");
    } catch (caught) { toast.error(caught.message || "The report could not be generated."); }
    finally { setWorking(false); }
  }

  async function exportRun(format) {
    if (!selected) return;
    setWorking(true);
    try {
      const record = await coreAdministrativeReportApi.export(selected.id, format);
      await coreAdministrativeReportApi.download(record);
      toast.success(`${format.toUpperCase()} download started.`);
    } catch (caught) { toast.error(caught.message || "The export could not be downloaded."); }
    finally { setWorking(false); }
  }

  if (loading) return <div className="grid min-h-[32rem] place-items-center text-sm text-slate-500">Loading administrative reports…</div>;
  if (error) return <div className="rounded-xl border border-red-200 bg-red-50 p-8 text-center"><AlertTriangle className="mx-auto text-red-600" /><h2 className="mt-2 font-bold text-red-900">Administrative reports unavailable</h2><p className="mt-2 text-sm text-red-700">{error}</p><button className="mt-4 inline-flex items-center gap-2 rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white" onClick={load} type="button"><RefreshCw size={15} /> Retry</button></div>;

  const columns = columnsFrom(selected);
  const rows = rowsFrom(selected);
  return (
    <div className="p-3 sm:p-5">
      <RegistryHeader icon={FileBarChart} title="Administrative Reports" description="Generate reproducible, scope-aware Core reports and download protected CSV or PDF exports." actions={<button className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50" onClick={load} type="button"><RefreshCw size={15} /> Refresh</button>} />
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <main className="min-w-0 space-y-5">
          <section className="grid gap-3 sm:grid-cols-2">
            {(catalog?.reports || []).map((report) => <button key={report.code} className={`rounded-xl border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md ${selectedCode === report.code ? "border-sky-500 bg-sky-50" : "border-slate-200 bg-white"}`} onClick={() => setSelectedCode(report.code)} type="button"><strong className="block text-sm text-slate-900">{report.title}</strong><span className="mt-1 block text-xs leading-5 text-slate-500">{report.description}</span></button>)}
          </section>
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-sm font-bold text-slate-900">{selectedDefinition?.title || "Select a report"}</h2><p className="mt-1 text-xs text-slate-500">{selectedDefinition?.description}</p></div><button className="inline-flex items-center gap-2 rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" disabled={!selectedCode || working} onClick={generate} type="button"><ShieldCheck size={15} /> {working ? "Working…" : "Generate snapshot"}</button></div>
            {selected && <div className="mt-4 overflow-x-auto"><div className="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500"><span>{selected.display_code || selected.displayCode || `Run #${selected.id}`} · {selected.row_count ?? selected.rowCount ?? 0} rows · {dateTime(selected.generated_at || selected.generatedAt)}</span><div className="flex gap-2"><button className="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 font-bold hover:bg-slate-50" onClick={() => exportRun("csv")} type="button"><Download size={13} /> CSV</button><button className="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 font-bold hover:bg-slate-50" onClick={() => exportRun("pdf")} type="button"><Download size={13} /> PDF</button></div></div><table className="w-full min-w-[640px] text-left text-xs"><thead><tr className="border-b border-slate-200 text-slate-500">{columns.map((column) => <th className="px-3 py-2 font-bold" key={column.key}>{column.label}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row, index) => <tr className="border-b border-slate-100" key={index}>{columns.map((column) => <td className="px-3 py-2 text-slate-700" key={column.key}>{row[column.key] ?? "—"}</td>)}</tr>) : <tr><td className="px-3 py-8 text-center text-slate-500" colSpan={columns.length || 1}>No records in the authorized scope.</td></tr>}</tbody></table></div>}
          </section>
        </main>
        <aside className="rounded-xl border border-slate-200 bg-white shadow-sm"><header className="flex items-center gap-2 border-b border-slate-200 px-4 py-3"><History size={16} className="text-sky-700" /><h2 className="text-sm font-bold text-slate-900">Snapshot history</h2></header>{runs.length ? <div className="divide-y divide-slate-100">{runs.map((run) => <button className={`w-full px-4 py-3 text-left transition hover:bg-sky-50 ${selected?.id === run.id ? "bg-sky-50" : ""}`} key={run.id} onClick={() => { setSelected(run); setSelectedCode(run.report_code || run.reportCode); }} type="button"><strong className="text-xs text-sky-800">{run.display_code || run.displayCode || `Run #${run.id}`}</strong><span className="mt-1 block text-xs font-semibold text-slate-700">{run.report_title || run.reportTitle}</span><span className="mt-1 block text-[11px] text-slate-500">{run.row_count ?? run.rowCount ?? 0} rows · {dateTime(run.generated_at || run.generatedAt)}</span></button>)}</div> : <p className="p-6 text-center text-xs text-slate-500">No snapshots generated yet.</p>}</aside>
      </div>
    </div>
  );
}
