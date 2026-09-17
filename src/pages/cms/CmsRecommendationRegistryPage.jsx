import { useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronLeft,
  ChevronRight,
  ClipboardCheck,
  FilterX,
  RefreshCw,
  Search,
} from "lucide-react";
import { useNavigate, useSearchParams } from "react-router";
import {
  CmsOverdueBadge,
  CmsRiskBadge,
  CmsStatusBadge,
} from "../../components/cms/CmsBadges";
import RegistryHeader from "../../components/ui/RegistryHeader";
import { cmsApi } from "../../services/api";

const inputClass =
  "h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

const sortColumns = [
  ["recommendationCode", "CMS recommendation"],
  ["responsibleOffice", "Responsible office"],
  ["risk", "Risk"],
  ["status", "Status"],
  ["targetDate", "Target date"],
  ["assignedMonitor", "Compliance Monitor"],
  ["transferredAt", "Transferred"],
];

function displayDate(value) {
  if (!value) return "Not set";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not set";
  return new Intl.DateTimeFormat("en-PH", { dateStyle: "medium" }).format(date);
}

function firstOptionLabel(option) {
  return option.label || option.name || option.code;
}

function TableSkeleton() {
  return (
    <div aria-label="Loading recommendation registry" className="grid gap-2 p-4">
      {Array.from({ length: 7 }).map((_, index) => (
        <div
          className="h-14 animate-pulse rounded-lg bg-slate-100"
          key={index}
        />
      ))}
    </div>
  );
}

function SortIcon({ active, direction }) {
  if (!active) return <ArrowUpDown size={14} aria-hidden="true" />;
  return direction === "asc" ? (
    <ArrowUp size={14} aria-hidden="true" />
  ) : (
    <ArrowDown size={14} aria-hidden="true" />
  );
}

export default function CmsRecommendationRegistryPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryKey = searchParams.toString();
  const [searchInput, setSearchInput] = useState(
    () => searchParams.get("search") || "",
  );
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [refreshKey, setRefreshKey] = useState(0);
  const filters = data?.filters ?? {};
  const pagination = data?.pagination ?? {
    currentPage: 1,
    lastPage: 1,
    perPage: 25,
    total: 0,
    from: null,
    to: null,
  };

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const current = searchParams.get("search") || "";
      if (searchInput.trim() === current) return;
      setLoading(true);
      setError("");
      const next = new URLSearchParams(searchParams);
      if (searchInput.trim()) next.set("search", searchInput.trim());
      else next.delete("search");
      next.set("page", "1");
      setSearchParams(next, { replace: true });
    }, 350);
    return () => window.clearTimeout(timer);
  }, [searchInput, searchParams, setSearchParams]);

  useEffect(() => {
    let active = true;
    cmsApi
      .getRecommendations(Object.fromEntries(searchParams.entries()))
      .then((result) => {
        if (active) setData(result);
      })
      .catch((requestError) => {
        if (active) {
          setError(
            requestError.message ||
              "The Recommendation Registry could not be loaded.",
          );
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
    // queryKey is the stable request identity; searchParams is intentionally read
    // from the render that produced it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queryKey, refreshKey]);

  const records = useMemo(() => data?.recommendations ?? [], [data]);

  function updateFilter(name, value) {
    setLoading(true);
    setError("");
    const next = new URLSearchParams(searchParams);
    if (value === "") next.delete(name);
    else next.set(name, value);
    next.set("page", "1");
    setSearchParams(next);
  }

  function clearFilters() {
    setLoading(true);
    setError("");
    setSearchInput("");
    setSearchParams(new URLSearchParams());
  }

  function sortBy(key) {
    setLoading(true);
    setError("");
    const next = new URLSearchParams(searchParams);
    const currentKey = next.get("sortBy") || "transferredAt";
    const currentDirection = next.get("sortDirection") || "desc";
    next.set("sortBy", key);
    next.set(
      "sortDirection",
      currentKey === key && currentDirection === "asc" ? "desc" : "asc",
    );
    next.set("page", "1");
    setSearchParams(next);
  }

  function goToPage(page) {
    setLoading(true);
    setError("");
    const next = new URLSearchParams(searchParams);
    next.set("page", String(page));
    setSearchParams(next);
  }

  const currentSort = searchParams.get("sortBy") || "transferredAt";
  const currentDirection = searchParams.get("sortDirection") || "desc";
  const hasFilters = Array.from(searchParams.keys()).some(
    (key) => !["page", "perPage", "sortBy", "sortDirection"].includes(key),
  );

  return (
    <main className="min-w-0 p-4 sm:p-5 lg:p-6">
      <RegistryHeader
        actions={
          <button
            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
            disabled={loading}
            onClick={() => {
              setLoading(true);
              setError("");
              setRefreshKey((current) => current + 1);
            }}
            type="button"
          >
            <RefreshCw className={loading ? "animate-spin" : ""} size={16} />
            Refresh
          </button>
        }
        description="Search and monitor AEMS recommendations within your authorized CMS scope."
        icon={ClipboardCheck}
        title="Recommendation Registry"
      />

      <section className="mb-5 min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="relative">
          <Search
            aria-hidden="true"
            className="pointer-events-none absolute left-3 top-3 text-slate-400"
            size={18}
          />
          <label className="sr-only" htmlFor="cms-recommendation-search">
            Search recommendations
          </label>
          <input
            className="h-12 w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
            id="cms-recommendation-search"
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder="Search CMS code, recommendation, finding, engagement, report, or office..."
            type="search"
            value={searchInput}
          />
        </div>

        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          <FilterSelect
            label="Case status"
            onChange={(value) => updateFilter("status", value)}
            options={(filters.statuses ?? []).map((status) => ({
              code: status,
              label: status.replaceAll("_", " "),
            }))}
            value={searchParams.get("status") || ""}
          />
          <FilterSelect
            label="Responsible office"
            onChange={(value) => updateFilter("officeId", value)}
            options={filters.responsibleOffices}
            value={searchParams.get("officeId") || ""}
          />
          <FilterSelect
            label="Risk level"
            onChange={(value) => updateFilter("risk", value)}
            options={filters.riskLevels}
            value={searchParams.get("risk") || ""}
          />
          <FilterSelect
            label="Confidentiality"
            onChange={(value) => updateFilter("confidentiality", value)}
            options={filters.confidentialityLevels}
            value={searchParams.get("confidentiality") || ""}
          />
          <FilterSelect
            label="Assigned monitor"
            onChange={(value) => updateFilter("monitorId", value)}
            options={filters.assignedMonitors}
            value={searchParams.get("monitorId") || ""}
          />
          <TriStateFilter
            falseLabel="Unassigned only"
            label="Assignment"
            name="assigned"
            onChange={updateFilter}
            trueLabel="Assigned only"
            value={searchParams.get("assigned") || ""}
          />
          <TriStateFilter
            falseLabel="Without target date"
            label="Target date"
            name="hasTargetDate"
            onChange={updateFilter}
            trueLabel="Has target date"
            value={searchParams.get("hasTargetDate") || ""}
          />
          <TriStateFilter
            falseLabel="Not overdue"
            label="Overdue state"
            name="overdue"
            onChange={updateFilter}
            trueLabel="Overdue only"
            value={searchParams.get("overdue") || ""}
          />
          <DateFilter
            label="Transferred from"
            name="transferredFrom"
            onChange={updateFilter}
            value={searchParams.get("transferredFrom") || ""}
          />
          <DateFilter
            label="Transferred to"
            name="transferredTo"
            onChange={updateFilter}
            value={searchParams.get("transferredTo") || ""}
          />
          <DateFilter
            label="Target from"
            name="targetFrom"
            onChange={updateFilter}
            value={searchParams.get("targetFrom") || ""}
          />
          <DateFilter
            label="Target to"
            name="targetTo"
            onChange={updateFilter}
            value={searchParams.get("targetTo") || ""}
          />
        </div>

        {hasFilters && (
          <button
            className="mt-4 inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50"
            onClick={clearFilters}
            type="button"
          >
            <FilterX size={15} /> Clear filters
          </button>
        )}
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
          <div>
            <h3 className="font-bold text-slate-800">Recommendations</h3>
            <p className="mt-0.5 text-xs text-slate-500">
              {loading && !data
                ? "Loading results..."
                : `${pagination.total} result${pagination.total === 1 ? "" : "s"}`}
            </p>
          </div>
          <label className="flex items-center gap-2 text-xs font-semibold text-slate-600">
            Rows
            <select
              className="h-9 rounded-lg border border-slate-300 bg-white px-2"
              onChange={(event) => updateFilter("perPage", event.target.value)}
              value={String(pagination.perPage)}
            >
              {[10, 25, 50, 100].map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </label>
        </header>

        {error ? (
          <div className="px-5 py-12 text-center">
            <AlertTriangle className="mx-auto text-red-600" size={32} />
            <h3 className="mt-3 font-bold text-slate-800">
              Recommendations unavailable
            </h3>
            <p className="mt-1 text-sm text-slate-600">{error}</p>
            <button
              className="mt-4 rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white"
              onClick={() => {
                setLoading(true);
                setError("");
                setRefreshKey((current) => current + 1);
              }}
              type="button"
            >
              Retry
            </button>
          </div>
        ) : loading && !data ? (
          <TableSkeleton />
        ) : records.length === 0 ? (
          <div className="px-5 py-14 text-center">
            <ClipboardCheck className="mx-auto text-slate-300" size={38} />
            <h3 className="mt-3 font-bold text-slate-700">
              No recommendations found
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              Try changing the search or filters. Only records in your authorized
              scope are shown.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1120px] border-collapse text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  {sortColumns.map(([key, label]) => (
                    <th className="px-4 py-3 font-bold" key={key} scope="col">
                      <button
                        className="inline-flex items-center gap-1.5 hover:text-sky-700 focus-visible:outline-2 focus-visible:outline-sky-600"
                        onClick={() => sortBy(key)}
                        type="button"
                      >
                        {label}
                        <SortIcon
                          active={currentSort === key}
                          direction={currentDirection}
                        />
                      </button>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {records.map((record) => (
                  <tr
                    className="cursor-pointer transition hover:bg-sky-50 focus-within:bg-sky-50"
                    key={record.id}
                    onClick={() =>
                      navigate(
                        `/compliance-management/recommendations/${record.id}`,
                      )
                    }
                  >
                    <td className="px-4 py-3">
                      <button
                        className="text-left focus-visible:outline-2 focus-visible:outline-sky-600"
                        onClick={(event) => {
                          event.stopPropagation();
                          navigate(
                            `/compliance-management/recommendations/${record.id}`,
                          );
                        }}
                        type="button"
                      >
                        <strong className="block text-sky-800">
                          {record.cmsRecommendationCode}
                        </strong>
                        <span
                          className="mt-1 block max-w-[21rem] truncate text-xs text-slate-500"
                          title={record.recommendation || record.finding?.title}
                        >
                          {record.recommendation ||
                            record.finding?.title ||
                            "No summary"}
                        </span>
                      </button>
                    </td>
                    <td className="px-4 py-3 text-slate-600">
                      {record.responsibleOffice?.name || "Not assigned"}
                    </td>
                    <td className="px-4 py-3">
                      <CmsRiskBadge risk={record.risk} />
                    </td>
                    <td className="px-4 py-3">
                      <CmsStatusBadge status={record.status} />
                      {record.currentlyReopened && (
                        <span className="mt-1 block text-xs font-semibold text-emerald-700">
                          Reopened · Cycle {record.activeCycleNumber ?? 1}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-slate-600">
                      <span className="block">
                        {displayDate(record.effectiveTargetDate)}
                      </span>
                      <span className="mt-1 block">
                        <CmsOverdueBadge overdue={record.isOverdue} />
                      </span>
                    </td>
                    <td className="px-4 py-3 text-slate-600">
                      {record.currentMonitor?.user?.name || "Unassigned"}
                    </td>
                    <td className="px-4 py-3 text-slate-600">
                      {displayDate(record.transferredAt || record.openedAt)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {!error && pagination.total > 0 && (
          <footer className="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-slate-500">
              Showing {pagination.from}–{pagination.to} of {pagination.total}
            </p>
            <div className="flex items-center gap-2">
              <button
                aria-label="Previous page"
                className="grid h-9 w-9 place-items-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                disabled={pagination.currentPage <= 1 || loading}
                onClick={() => goToPage(pagination.currentPage - 1)}
                type="button"
              >
                <ChevronLeft size={17} />
              </button>
              <span className="px-2 text-xs font-semibold text-slate-600">
                Page {pagination.currentPage} of {pagination.lastPage}
              </span>
              <button
                aria-label="Next page"
                className="grid h-9 w-9 place-items-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                disabled={
                  pagination.currentPage >= pagination.lastPage || loading
                }
                onClick={() => goToPage(pagination.currentPage + 1)}
                type="button"
              >
                <ChevronRight size={17} />
              </button>
            </div>
          </footer>
        )}
      </section>
    </main>
  );
}

function FilterSelect({ label, value, options = [], onChange }) {
  return (
    <label className="min-w-0 text-xs font-semibold text-slate-600">
      {label}
      <select
        className={`${inputClass} mt-1`}
        onChange={(event) => onChange(event.target.value)}
        value={value}
      >
        <option value="">All</option>
        {options.map((option) => (
          <option
            key={`${option.id ?? option.code}-${firstOptionLabel(option)}`}
            value={
              label === "Responsible office" || label === "Assigned monitor"
                ? option.id
                : option.code
            }
          >
            {firstOptionLabel(option)}
          </option>
        ))}
      </select>
    </label>
  );
}

function TriStateFilter({
  label,
  name,
  value,
  trueLabel,
  falseLabel,
  onChange,
}) {
  return (
    <label className="min-w-0 text-xs font-semibold text-slate-600">
      {label}
      <select
        className={`${inputClass} mt-1`}
        onChange={(event) => onChange(name, event.target.value)}
        value={value}
      >
        <option value="">All</option>
        <option value="1">{trueLabel}</option>
        <option value="0">{falseLabel}</option>
      </select>
    </label>
  );
}

function DateFilter({ label, name, value, onChange }) {
  return (
    <label className="min-w-0 text-xs font-semibold text-slate-600">
      {label}
      <input
        className={`${inputClass} mt-1`}
        onChange={(event) => onChange(name, event.target.value)}
        type="date"
        value={value}
      />
    </label>
  );
}
