import { useCallback, useEffect, useState } from "react";
import {
  Download,
  FileBarChart,
  Files,
  RefreshCw,
  ShieldCheck,
} from "lucide-react";
import { useSearchParams } from "react-router";
import {
  aemsClosureApi,
  aemsDashboardApi,
  aemsDocumentIndexApi,
  aemsEngagementApi,
  aemsReportApi,
} from "../../services/api";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SummaryCard from "../../components/ui/SummaryCard";

async function downloadUrl(url, fallbackName) {
  const response = await fetch(url, {
    credentials: "include",
    headers: { Accept: "text/csv", "X-Requested-With": "XMLHttpRequest" },
  });
  if (!response.ok)
    throw new Error("The protected export could not be downloaded.");
  const blob = await response.blob();
  const disposition = response.headers.get("content-disposition") || "";
  const fileName =
    disposition.match(/filename\*?=(?:UTF-8'')?"?([^;"]+)/i)?.[1] ||
    fallbackName;
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = decodeURIComponent(fileName);
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(link.href);
}

export default function AemsRegistersPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    searchParams.get("engagementId") || "",
  );
  const [records, setRecords] = useState(null);
  const [reports, setReports] = useState(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const loadEngagements = useCallback(async () => {
    try {
      const result = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(result.engagements || []);
      setSelectedId(
        (current) => current || String(result.engagements?.[0]?.id || ""),
      );
    } catch (reason) {
      setError(reason.message);
    }
  }, []);
  const load = useCallback(async () => {
    if (!selectedId) return;
    setBusy(true);
    setError("");
    const result = await Promise.allSettled([
      aemsClosureApi.records(selectedId),
      aemsReportApi.show(selectedId),
    ]);
    if (result[0].status === "fulfilled") setRecords(result[0].value);
    if (result[1].status === "fulfilled") setReports(result[1].value);
    const failure = result.find((item) => item.status === "rejected");
    if (failure) setError(failure.reason.message);
    setBusy(false);
  }, [selectedId]);
  useEffect(() => {
    const timer = window.setTimeout(() => void loadEngagements(), 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);
  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);
  useEffect(() => {
    if (selectedId)
      setSearchParams({ engagementId: selectedId }, { replace: true });
  }, [selectedId, setSearchParams]);

  const exportAction = async (action) => {
    setBusy(true);
    setError("");
    try {
      await action();
    } catch (reason) {
      setError(reason.message);
    } finally {
      setBusy(false);
    }
  };
  const recordItems = records?.items || records?.records || [];
  const reportItems = reports?.reports || reports?.reportFamilies || [];

  return (
    <div className="min-w-0" data-testid="aems-registers-page">
      <RegistryHeader
        icon={FileBarChart}
        title="AEMS Registers and Protected Exports"
        description="Scope-aware register surfaces and authenticated reproducible exports for engagement progress, work queues, records, and reports."
        actions={
          <button
            className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
            disabled={busy}
            onClick={() => void load()}
            type="button"
          >
            <RefreshCw className={busy ? "animate-spin" : ""} size={16} />{" "}
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
      <section className="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
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
      </section>
      {selectedId && (
        <>
          <section className="mb-5 grid gap-3 sm:grid-cols-3">
            <SummaryCard
              icon={Files}
              label="Indexed records"
              value={records?.summary?.total ?? recordItems.length}
              tone="sky"
            />
            <SummaryCard
              icon={FileBarChart}
              label="Report families"
              value={reportItems.length || (reports ? 1 : 0)}
              tone="amber"
            />
            <SummaryCard
              icon={ShieldCheck}
              label="Protected exports"
              value="4"
              tone="emerald"
            />
          </section>
          <section className="mb-5 grid gap-5 xl:grid-cols-2">
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
              <h2 className="font-bold text-slate-900">Register exports</h2>
              <p className="mt-1 text-sm text-slate-500">
                Each export is generated server-side and requires the
                authenticated user’s current scope.
              </p>
              <div className="mt-4 grid gap-2 sm:grid-cols-2">
                <button
                  className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white disabled:opacity-50"
                  disabled={busy}
                  onClick={() =>
                    void exportAction(() => aemsDashboardApi.export())
                  }
                  type="button"
                >
                  <Download size={15} /> Progress CSV
                </button>
                <button
                  className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white disabled:opacity-50"
                  disabled={busy}
                  onClick={() =>
                    void exportAction(() => aemsDashboardApi.exportQueues())
                  }
                  type="button"
                >
                  <Download size={15} /> Work queues CSV
                </button>
                <button
                  className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 disabled:opacity-50"
                  disabled={busy}
                  onClick={() =>
                    void exportAction(() =>
                      downloadUrl(
                        aemsDocumentIndexApi.exportUrl(selectedId),
                        "aems-document-index.csv",
                      ),
                    )
                  }
                  type="button"
                >
                  <Download size={15} /> Document index CSV
                </button>
                <a
                  className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-center text-xs font-bold text-slate-700 hover:bg-slate-50"
                  href={`/audit-engagement-management/reports?engagementId=${selectedId}`}
                >
                  <FileBarChart size={15} /> Protected report workspace
                </a>
              </div>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
              <h2 className="font-bold text-slate-900">Register surfaces</h2>
              <p className="mt-1 text-sm text-slate-500">
                Open the canonical workspace without leaving the current
                engagement context.
              </p>
              <div className="mt-4 grid gap-2">
                <a
                  className="rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50"
                  href={`/audit-engagement-management/work-queues?engagementId=${selectedId}`}
                >
                  Operational queues{" "}
                  <span className="block text-xs font-normal text-slate-500">
                    Tasks, review notes, due process, escalation candidates
                  </span>
                </a>
                <a
                  className="rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50"
                  href={`/audit-engagement-management/calendar?engagementId=${selectedId}`}
                >
                  Audit Calendar and milestones{" "}
                  <span className="block text-xs font-normal text-slate-500">
                    Dates, owners, overdue blockers, completion
                  </span>
                </a>
                <a
                  className="rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50"
                  href={`/audit-engagement-management?engagementId=${selectedId}`}
                >
                  Audit Engagement Workspace{" "}
                  <span className="block text-xs font-normal text-slate-500">
                    Scope-aware engagement register
                  </span>
                </a>
              </div>
            </div>
          </section>
          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-4 py-3">
              <h2 className="font-bold text-slate-900">
                Indexed records preview
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                The complete document index remains available through the
                protected export.
              </p>
            </div>
            <div className="divide-y divide-slate-100">
              {recordItems.slice(0, 8).map((item) => (
                <div
                  className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                  key={item.id}
                >
                  <span className="font-semibold text-slate-800">
                    {item.referenceCode ||
                      item.documentCode ||
                      item.code ||
                      item.title ||
                      "Indexed record"}
                  </span>
                  <span className="text-xs text-slate-500">
                    {item.status ||
                      item.recordType ||
                      item.documentType ||
                      "Registered"}
                  </span>
                </div>
              ))}
              {!recordItems.length && (
                <p className="p-5 text-sm text-slate-500">
                  No indexed records are available in this scope.
                </p>
              )}
            </div>
          </section>
        </>
      )}
    </div>
  );
}
