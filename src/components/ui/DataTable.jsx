import { useMemo, useState } from "react";
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";

function buildPageItems(currentPage, totalPages) {
  if (totalPages <= 7) {
    return Array.from({ length: totalPages }, (_, index) => index + 1);
  }

  const pages = new Set([1, totalPages, currentPage]);
  if (currentPage > 1) pages.add(currentPage - 1);
  if (currentPage < totalPages) pages.add(currentPage + 1);
  if (currentPage <= 3) {
    pages.add(2);
    pages.add(3);
    pages.add(4);
  }
  if (currentPage >= totalPages - 2) {
    pages.add(totalPages - 1);
    pages.add(totalPages - 2);
    pages.add(totalPages - 3);
  }

  const sorted = [...pages]
    .filter((page) => page >= 1 && page <= totalPages)
    .sort((left, right) => left - right);

  const result = [];
  sorted.forEach((page, index) => {
    const previous = sorted[index - 1];
    if (index > 0 && page - previous > 1) result.push(`ellipsis-${page}`);
    result.push(page);
  });
  return result;
}

export default function DataTable({
  columns,
  rows,
  rowKey = "id",
  loading = false,
  emptyMessage = "No records found.",
  initialPageSize,
  pageSizeOptions = [10, 25, 50, 100],
  onRowClick,
}) {
  const { runtimeConfig } = useAuth();
  const configuredPageSize = Number(
    initialPageSize ?? runtimeConfig.paginationSize ?? 25,
  );
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(configuredPageSize);
  const [sort, setSort] = useState({ key: null, direction: "asc" });
  const availablePageSizes = useMemo(
    () =>
      [...new Set([configuredPageSize, ...pageSizeOptions])]
        .filter((value) => Number.isFinite(value) && value > 0)
        .sort((left, right) => left - right),
    [configuredPageSize, pageSizeOptions],
  );

  const sortedRows = useMemo(() => {
    if (!sort.key) return rows;
    const column = columns.find((candidate) => candidate.key === sort.key);
    if (!column) return rows;

    return [...rows].sort((left, right) => {
      const leftValue = column.sortValue
        ? column.sortValue(left)
        : left[column.key];
      const rightValue = column.sortValue
        ? column.sortValue(right)
        : right[column.key];

      if (leftValue === rightValue) return 0;
      if (leftValue === null || leftValue === undefined) return 1;
      if (rightValue === null || rightValue === undefined) return -1;

      const comparison =
        typeof leftValue === "number" && typeof rightValue === "number"
          ? leftValue - rightValue
          : String(leftValue).localeCompare(String(rightValue), undefined, {
              numeric: true,
              sensitivity: "base",
            });
      return sort.direction === "asc" ? comparison : -comparison;
    });
  }, [columns, rows, sort]);

  const totalPages = Math.max(1, Math.ceil(sortedRows.length / pageSize));
  const currentPage = Math.min(page, totalPages);
  const visibleRows = sortedRows.slice(
    (currentPage - 1) * pageSize,
    currentPage * pageSize,
  );
  const firstRecord =
    sortedRows.length === 0 ? 0 : (currentPage - 1) * pageSize + 1;
  const lastRecord = Math.min(currentPage * pageSize, sortedRows.length);
  const pageItems = buildPageItems(currentPage, totalPages);

  function toggleSort(column) {
    if (column.sortable === false || column.key === "actions") return;
    setPage(1);
    setSort((current) => ({
      key: column.key,
      direction:
        current.key === column.key && current.direction === "asc"
          ? "desc"
          : "asc",
    }));
  }

  return (
    <div className="min-w-0">
      <div className="overflow-x-auto">
        <table className="min-w-full border-separate border-spacing-0 text-left text-sm">
          <thead className="sticky top-0 z-10 bg-slate-50/95 backdrop-blur">
            <tr>
              {columns.map((column) => {
                const sortable =
                  column.sortable !== false && column.key !== "actions";
                const active = sort.key === column.key;
                const SortIcon = active
                  ? sort.direction === "asc"
                    ? ArrowUp
                    : ArrowDown
                  : ArrowUpDown;

                return (
                  <th
                    className={`border-b border-slate-200 px-4 py-3.5 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500 first:pl-5 last:pr-5 ${column.headerClassName ?? ""}`}
                    key={column.key}
                    scope="col"
                  >
                    <button
                      className={`inline-flex items-center gap-1.5 whitespace-nowrap transition ${sortable ? "cursor-pointer hover:text-sky-700" : "cursor-default"}`}
                      disabled={!sortable}
                      onClick={() => toggleSort(column)}
                      type="button"
                    >
                      {column.label}
                      {sortable && (
                        <SortIcon
                          className={active ? "text-sky-700" : "text-slate-300"}
                          size={13}
                        />
                      )}
                    </button>
                  </th>
                );
              })}
            </tr>
          </thead>

          <tbody className="bg-white">
            {loading &&
              Array.from({ length: 6 }, (_, index) => (
                <tr key={`loading-${index}`}>
                  {columns.map((column) => (
                    <td
                      className="border-b border-slate-100 px-4 py-4 first:pl-5 last:pr-5"
                      key={column.key}
                    >
                      <span className="block h-4 animate-pulse rounded-full bg-slate-100" />
                    </td>
                  ))}
                </tr>
              ))}

            {!loading &&
              visibleRows.map((row) => (
                <tr
                  className={`group transition-colors hover:bg-sky-50/55 ${onRowClick ? "cursor-pointer focus-within:bg-sky-50/55" : ""}`}
                  key={typeof rowKey === "function" ? rowKey(row) : row[rowKey]}
                  onClick={
                    onRowClick
                      ? (event) => {
                          if (
                            event.target.closest(
                              "button, a, input, select, textarea, label, summary",
                            )
                          ) {
                            return;
                          }
                          onRowClick(row);
                        }
                      : undefined
                  }
                  onKeyDown={
                    onRowClick
                      ? (event) => {
                          if (
                            event.target.closest(
                              "button, a, input, select, textarea, label, summary",
                            )
                          ) {
                            return;
                          }
                          if (event.key === "Enter" || event.key === " ") {
                            event.preventDefault();
                            onRowClick(row);
                          }
                        }
                      : undefined
                  }
                  role={onRowClick ? "button" : undefined}
                  tabIndex={onRowClick ? 0 : undefined}
                >
                  {columns.map((column) => (
                    <td
                      className={`border-b border-slate-100 px-4 py-3.5 align-middle text-slate-600 first:pl-5 last:pr-5 group-last:border-b-0 ${column.className ?? ""}`}
                      key={column.key}
                    >
                      {column.render
                        ? column.render(row)
                        : (row[column.key] ?? "—")}
                    </td>
                  ))}
                </tr>
              ))}

            {!loading && rows.length === 0 && (
              <tr>
                <td
                  className="px-6 py-16 text-center text-sm text-slate-500"
                  colSpan={columns.length}
                >
                  <div className="mx-auto max-w-sm rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8">
                    <p className="font-semibold text-slate-700">
                      {emptyMessage}
                    </p>
                    <p className="mt-1 text-xs text-slate-400">
                      Try changing the search terms or filters.
                    </p>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {!loading && rows.length > 0 && (
        <footer className="flex flex-col gap-3 border-t border-slate-200 bg-white px-5 py-3.5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
          <span>
            Showing <strong className="text-slate-700">{firstRecord}</strong>–
            <strong className="text-slate-700">{lastRecord}</strong> of{" "}
            <strong className="text-slate-700">{sortedRows.length}</strong>{" "}
            records
          </span>

          <div className="flex flex-wrap items-center gap-2">
            <label className="flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-slate-500">
              <span className="hidden sm:inline">Rows per page</span>
              <select
                className="bg-transparent font-semibold text-slate-700 outline-none"
                onChange={(event) => {
                  setPageSize(Number(event.target.value));
                  setPage(1);
                }}
                value={pageSize}
              >
                {availablePageSizes.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </label>

            <nav className="flex items-center gap-1" aria-label="Table pages">
              <button
                aria-label="Previous page"
                className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-35"
                disabled={currentPage <= 1}
                onClick={() => setPage(currentPage - 1)}
                type="button"
              >
                <ChevronLeft size={16} />
              </button>

              {pageItems.map((item) =>
                typeof item === "number" ? (
                  <button
                    aria-current={item === currentPage ? "page" : undefined}
                    className={`grid h-9 min-w-9 place-items-center rounded-lg border px-2 text-xs font-bold transition ${
                      item === currentPage
                        ? "border-sky-700 bg-sky-700 text-white shadow-sm"
                        : "border-slate-200 bg-white text-slate-600 hover:border-sky-200 hover:bg-sky-50"
                    }`}
                    key={item}
                    onClick={() => setPage(item)}
                    type="button"
                  >
                    {item}
                  </button>
                ) : (
                  <span
                    className="grid h-9 min-w-7 place-items-center text-slate-400"
                    key={item}
                  >
                    …
                  </span>
                ),
              )}

              <button
                aria-label="Next page"
                className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-35"
                disabled={currentPage >= totalPages}
                onClick={() => setPage(currentPage + 1)}
                type="button"
              >
                <ChevronRight size={16} />
              </button>
            </nav>
          </div>
        </footer>
      )}
    </div>
  );
}
