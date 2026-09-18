import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import {
  BarChart3,
  CalendarRange,
  Download,
  FileBarChart,
  FileDown,
  FileSpreadsheet,
  FileText,
  History,
  Layers3,
  ListOrdered,
  Printer,
  RefreshCw,
  Search,
  ShieldCheck,
  TableProperties,
  UsersRound,
} from "lucide-react";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import { iapReportApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const reportIcons = {
  "approved-siap": ShieldCheck,
  "audit-universe": Layers3,
  "risk-assessment-matrix": TableProperties,
  "risk-heat-map": BarChart3,
  "prioritization-ranking": ListOrdered,
  "approved-annual-plan": FileText,
  "annual-audit-schedule": CalendarRange,
  "auditor-allocation": UsersRound,
  "plan-revision-history": History,
};

const filterLabels = {
  strategicPlanId: "Approved strategic plan",
  planId: "Annual audit plan",
  riskPeriodId: "Validated risk period",
  prioritizationId: "Finalized prioritization",
  fiscalYear: "Fiscal year",
};

const heatTones = {
  CRITICAL: "border-red-200 bg-red-100 text-red-800",
  HIGH: "border-orange-200 bg-orange-100 text-orange-800",
  MEDIUM: "border-amber-200 bg-amber-100 text-amber-800",
  LOW: "border-emerald-200 bg-emerald-100 text-emerald-800",
};

function formatDateTime(value) {
  if (!value) return "";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function getFilterOptions(filter, catalog) {
  const source = {
    strategicPlanId: catalog.strategicPlans,
    planId: catalog.plans,
    riskPeriodId: catalog.riskPeriods,
    prioritizationId: catalog.prioritizations,
    fiscalYear: catalog.fiscalYears,
  }[filter];

  return (source ?? []).map((item) => {
    if (typeof item === "number" || typeof item === "string") {
      return { value: item, label: String(item) };
    }

    return {
      value: item.id,
      label: item.label,
      description: item.status
        ? String(item.status).replaceAll("_", " ")
        : undefined,
    };
  });
}

function defaultFilterValue(report, catalog) {
  if (!report?.filter) return "";
  const options = getFilterOptions(report.filter, catalog);
  if (!options.length) return "";

  if (report.code === "approved-annual-plan") {
    const approved = (catalog.approvedPlans ?? [])[0];
    return approved?.id ?? "";
  }

  return options[0].value;
}

function displayValue(value) {
  if (value === null || value === undefined || value === "") return "—";
  return String(value).replaceAll("_", " ");
}

function ReportCard({ report, selected, onClick }) {
  const Icon = reportIcons[report.code] ?? FileBarChart;

  return (
    <button
      className={`group min-h-28 w-full rounded-xl border p-4 text-left shadow-sm transition duration-200 hover:-translate-y-1 hover:border-sky-300 hover:shadow-md ${
        selected
          ? "border-sky-500 bg-sky-50 ring-2 ring-sky-100"
          : "border-slate-200 bg-white"
      }`}
      onClick={onClick}
      type="button"
    >
      <span
        className={`mb-3 grid h-9 w-9 place-items-center rounded-lg transition group-hover:scale-110 ${
          selected
            ? "bg-sky-600 text-white"
            : "bg-slate-100 text-slate-600 group-hover:bg-sky-100 group-hover:text-sky-700"
        }`}
      >
        <Icon size={19} />
      </span>
      <strong className="block text-sm leading-5 text-slate-900">
        {report.title}
      </strong>
      <span className="mt-1 line-clamp-2 block text-xs leading-4 text-slate-500">
        {report.description}
      </span>
    </button>
  );
}

function RiskHeatMap({ visualization }) {
  if (visualization?.type !== "riskHeatMap") return null;

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="mb-4">
        <h3 className="text-sm font-bold text-slate-900">
          Inherent-to-residual risk heat map
        </h3>
        <p className="mt-1 text-xs text-slate-500">
          Rows show inherent risk and columns show residual risk after controls.
        </p>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[540px] border-separate border-spacing-2">
          <thead>
            <tr>
              <th className="px-3 py-2 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                Inherent \ Residual
              </th>
              {visualization.levels.map((level) => (
                <th
                  className="px-3 py-2 text-center text-xs font-bold uppercase tracking-wide text-slate-600"
                  key={level}
                >
                  {level}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {visualization.matrix.map((row) => (
              <tr key={row.inherent}>
                <th className="px-3 py-3 text-left text-xs font-bold text-slate-700">
                  {row.inherent}
                </th>
                {row.cells.map((cell) => (
                  <td
                    className={`rounded-xl border p-4 text-center ${heatTones[cell.residual]}`}
                    key={`${row.inherent}-${cell.residual}`}
                  >
                    <strong className="block text-2xl">{cell.value}</strong>
                    <span className="text-[10px] font-semibold uppercase tracking-wide opacity-70">
                      subjects
                    </span>
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function ReportTable({
  report,
  search,
  page,
  pageSize,
  setPage,
  setPageSize,
  setSearch,
}) {
  const filteredRows = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return report?.rows ?? [];

    return (report?.rows ?? []).filter((row) =>
      report.columns.some((column) =>
        String(row[column.key] ?? "")
          .toLowerCase()
          .includes(term),
      ),
    );
  }, [report, search]);
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const rows = filteredRows.slice(
    (safePage - 1) * pageSize,
    safePage * pageSize,
  );

  return (
    <section className="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
          <h3 className="text-sm font-bold text-slate-900">Report details</h3>
          <p className="mt-0.5 text-xs text-slate-500">
            {filteredRows.length} matching{" "}
            {filteredRows.length === 1 ? "record" : "records"}
          </p>
        </div>
        <label className="flex h-10 w-full max-w-sm items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-400 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
          <Search size={16} />
          <input
            className="min-w-0 flex-1 text-sm text-slate-800 outline-none"
            onChange={(event) => {
              setPage(1);
              setSearch(event.target.value);
            }}
            placeholder="Search this report..."
            value={search}
          />
        </label>
      </header>
      <div className="overflow-x-auto">
        <table className="w-full min-w-max text-left">
          <thead className="bg-slate-50">
            <tr>
              {report.columns.map((column) => (
                <th
                  className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600"
                  key={column.key}
                >
                  {column.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, rowIndex) => (
              <tr
                className="border-b border-slate-100 align-top transition hover:bg-sky-50/60"
                key={`${safePage}-${rowIndex}`}
              >
                {report.columns.map((column) => (
                  <td
                    className="max-w-sm whitespace-pre-line px-4 py-3 text-xs leading-5 text-slate-700"
                    key={column.key}
                  >
                    {displayValue(row[column.key])}
                  </td>
                ))}
              </tr>
            ))}
            {!rows.length && (
              <tr>
                <td
                  className="px-5 py-12 text-center text-sm text-slate-500"
                  colSpan={Math.max(report.columns.length, 1)}
                >
                  No records matched this report.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
        <select
          className="h-9 rounded-lg border border-slate-300 bg-white px-2 text-xs text-slate-700"
          onChange={(event) => {
            setPage(1);
            setPageSize(Number(event.target.value));
          }}
          value={pageSize}
        >
          {[10, 25, 50].map((size) => (
            <option key={size} value={size}>
              {size} rows
            </option>
          ))}
        </select>
        <div className="flex items-center gap-2 text-xs text-slate-600">
          <button
            className="h-9 rounded-lg border border-slate-300 px-3 font-semibold disabled:cursor-not-allowed disabled:opacity-40"
            disabled={safePage <= 1}
            onClick={() => setPage((current) => Math.max(1, current - 1))}
            type="button"
          >
            Previous
          </button>
          <span>
            Page {safePage} of {totalPages}
          </span>
          <button
            className="h-9 rounded-lg border border-slate-300 px-3 font-semibold disabled:cursor-not-allowed disabled:opacity-40"
            disabled={safePage >= totalPages}
            onClick={() =>
              setPage((current) => Math.min(totalPages, current + 1))
            }
            type="button"
          >
            Next
          </button>
        </div>
      </footer>
    </section>
  );
}

/**
 * Generates role-scoped IAP reports and visualizations and coordinates PDF,
 * spreadsheet, CSV, and print export actions.
 */
export default function IapReportsPage() {
  const { runtimeConfig } = useAuth();
  const [searchParams] = useSearchParams();
  const toast = useToast();
  const [catalog, setCatalog] = useState({
    reports: [],
    strategicPlans: [],
    plans: [],
    approvedPlans: [],
    riskPeriods: [],
    prioritizations: [],
    fiscalYears: [],
    canExport: false,
  });
  const [selectedCode, setSelectedCode] = useState("audit-universe");
  const [filterValue, setFilterValue] = useState("");
  const [report, setReport] = useState(null);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(runtimeConfig.paginationSize);
  const [loadingCatalog, setLoadingCatalog] = useState(true);
  const [loadingReport, setLoadingReport] = useState(true);
  const [exporting, setExporting] = useState("");
  const [error, setError] = useState("");

  const selectedReport = useMemo(
    () => catalog.reports.find((item) => item.code === selectedCode) ?? null,
    [catalog.reports, selectedCode],
  );
  const filterOptions = useMemo(
    () => getFilterOptions(selectedReport?.filter, catalog),
    [catalog, selectedReport],
  );
  const filters = useMemo(
    () =>
      selectedReport?.filter && filterValue !== ""
        ? { [selectedReport.filter]: filterValue }
        : {},
    [filterValue, selectedReport],
  );

  useEffect(() => {
    let active = true;
    iapReportApi
      .catalog()
      .then((data) => {
        if (!active) return;
        const nextCatalog = data ?? {};
        setCatalog((current) => ({ ...current, ...nextCatalog }));
        const requestedCode = searchParams.get("report");
        const defaultReport =
          (nextCatalog.reports ?? []).find(
            (item) => item.code === requestedCode,
          ) ??
          (nextCatalog.reports ?? []).find(
            (item) => item.code === "audit-universe",
          ) ??
          nextCatalog.reports?.[0];
        if (defaultReport) {
          setSelectedCode(defaultReport.code);
          const requestedPlan = searchParams.get("plan");
          setFilterValue(
            defaultReport.filter === "planId" && requestedPlan
              ? Number(requestedPlan)
              : defaultFilterValue(defaultReport, nextCatalog),
          );
        }
        setError("");
      })
      .catch((requestError) => {
        if (active) {
          setError(
            requestError instanceof Error
              ? requestError.message
              : "Unable to load IAP reports.",
          );
        }
      })
      .finally(() => active && setLoadingCatalog(false));

    return () => {
      active = false;
    };
  }, [searchParams]);

  useEffect(() => {
    if (loadingCatalog || !selectedCode) return undefined;
    let active = true;
    iapReportApi
      .preview(selectedCode, filters)
      .then((data) => {
        if (active) setReport(data);
      })
      .catch((requestError) => {
        if (active) {
          setReport(null);
          setError(
            requestError instanceof Error
              ? requestError.message
              : "Unable to generate this report.",
          );
        }
      })
      .finally(() => active && setLoadingReport(false));

    return () => {
      active = false;
    };
  }, [filters, loadingCatalog, selectedCode]);

  function chooseReport(nextReport) {
    setLoadingReport(true);
    setError("");
    setSelectedCode(nextReport.code);
    setFilterValue(defaultFilterValue(nextReport, catalog));
    setSearch("");
    setPage(1);
  }

  async function refresh() {
    setLoadingReport(true);
    setError("");
    try {
      setReport(await iapReportApi.preview(selectedCode, filters));
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to refresh this report.",
      );
    } finally {
      setLoadingReport(false);
    }
  }

  async function download(format) {
    setExporting(format);
    try {
      await iapReportApi.download(selectedCode, format, filters);
      toast.success(
        `${format === "excel" ? "Excel" : format.toUpperCase()} report downloaded.`,
      );
    } catch (requestError) {
      toast.error(
        requestError instanceof Error
          ? requestError.message
          : "Unable to export this report.",
      );
    } finally {
      setExporting("");
    }
  }

  function printReport() {
    window.open(
      iapReportApi.printUrl(selectedCode, filters),
      "_blank",
      "noopener,noreferrer",
    );
  }

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        description="Generate role-scoped planning reports, review live data, and export authorized records to PDF, Excel, CSV, or print."
        icon={FileBarChart}
        title="IAP Reports and Exports"
      />

      {error && (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      {loadingCatalog ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 6 }).map((_, index) => (
            <div
              className="h-28 animate-pulse rounded-xl bg-slate-200"
              key={index}
            />
          ))}
        </div>
      ) : (
        <>
          <section className="mb-5 grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
            {catalog.reports.map((item) => (
              <ReportCard
                key={item.code}
                onClick={() => chooseReport(item)}
                report={item}
                selected={item.code === selectedCode}
              />
            ))}
          </section>

          <section className="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
              <div className="min-w-0">
                <h2 className="text-lg font-bold text-slate-900">
                  {selectedReport?.title}
                </h2>
                <p className="mt-1 max-w-3xl text-sm leading-5 text-slate-500">
                  {selectedReport?.description}
                </p>
              </div>
              <button
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 disabled:opacity-50"
                disabled={loadingReport}
                onClick={refresh}
                type="button"
              >
                <RefreshCw
                  className={loadingReport ? "animate-spin" : ""}
                  size={16}
                />
                Refresh
              </button>
            </header>
            <div className="flex flex-col items-stretch gap-3 px-4 py-4 sm:px-5 lg:flex-row lg:flex-wrap lg:items-end">
              {selectedReport?.filter && (
                <label className="min-w-64 flex-1">
                  <span className="mb-1.5 block text-xs font-bold text-slate-700">
                    {filterLabels[selectedReport.filter]}
                  </span>
                  <SearchableSelect
                    emptyMessage="No eligible records are available."
                    onChange={(value) => {
                      setLoadingReport(true);
                      setError("");
                      setFilterValue(value);
                      setPage(1);
                    }}
                    options={
                      selectedReport.code === "approved-annual-plan"
                        ? (catalog.approvedPlans ?? []).map((item) => ({
                            value: item.id,
                            label: item.label,
                            description: item.status?.replaceAll("_", " "),
                          }))
                        : filterOptions
                    }
                    placeholder={`Select ${filterLabels[selectedReport.filter].toLowerCase()}`}
                    value={filterValue}
                  />
                </label>
              )}
              {catalog.canExport ? (
                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg bg-red-600 px-3 text-sm font-bold text-white transition hover:bg-red-700 disabled:opacity-50"
                    disabled={Boolean(exporting) || !report}
                    onClick={() => download("pdf")}
                    type="button"
                  >
                    <FileDown size={16} />
                    PDF
                  </button>
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-600 px-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    disabled={Boolean(exporting) || !report}
                    onClick={() => download("excel")}
                    type="button"
                  >
                    <FileSpreadsheet size={16} />
                    Excel
                  </button>
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-600 px-3 text-sm font-bold text-white transition hover:bg-sky-700 disabled:opacity-50"
                    disabled={Boolean(exporting) || !report}
                    onClick={() => download("csv")}
                    type="button"
                  >
                    <Download size={16} />
                    CSV
                  </button>
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    disabled={!report}
                    onClick={printReport}
                    type="button"
                  >
                    <Printer size={16} />
                    Print
                  </button>
                </div>
              ) : (
                <span className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">
                  Your role can preview reports but cannot export them.
                </span>
              )}
            </div>
          </section>

          {loadingReport ? (
            <div className="grid gap-4">
              <div className="h-24 animate-pulse rounded-xl bg-slate-200" />
              <div className="h-80 animate-pulse rounded-xl bg-slate-200" />
            </div>
          ) : (
            report && (
              <div className="grid min-w-0 gap-5">
                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                  {report.meta.map((item) => (
                    <article
                      className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                      key={item.label}
                    >
                      <span className="block text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        {item.label}
                      </span>
                      <strong className="mt-1 block break-words text-base text-slate-900">
                        {item.value}
                      </strong>
                    </article>
                  ))}
                </section>

                {report.sections.map((section) => (
                  <section
                    className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    key={section.title}
                  >
                    <h3 className="mb-3 text-sm font-bold text-slate-900">
                      {section.title}
                    </h3>
                    <div className="grid gap-3 md:grid-cols-2">
                      {section.items
                        .filter((item) => item.text)
                        .map((item, index) => (
                          <article
                            className="rounded-lg border-l-4 border-sky-500 bg-slate-50 px-4 py-3"
                            key={`${item.heading}-${index}`}
                          >
                            <strong className="block text-xs text-slate-800">
                              {item.heading}
                            </strong>
                            <p className="mt-1 whitespace-pre-line text-xs leading-5 text-slate-600">
                              {item.text}
                            </p>
                          </article>
                        ))}
                    </div>
                  </section>
                ))}

                <RiskHeatMap visualization={report.visualization} />

                <div className="min-w-0">
                  <ReportTable
                    page={page}
                    pageSize={pageSize}
                    report={report}
                    search={search}
                    setPage={setPage}
                    setPageSize={setPageSize}
                    setSearch={setSearch}
                  />
                </div>

                <p className="text-right text-xs text-slate-500">
                  Generated {formatDateTime(report.generatedAt)}
                </p>
              </div>
            )
          )}
        </>
      )}
    </main>
  );
}
