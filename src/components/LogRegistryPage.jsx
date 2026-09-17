import { useCallback, useEffect, useMemo, useState } from "react";
import {
  CalendarClock,
  Download,
  FileClock,
  Search,
  ShieldCheck,
  UsersRound,
  X,
} from "lucide-react";
import { useAuth } from "../auth/auth-context";
import { hasPermission } from "../config/navigation";
import { logApi } from "../services/api";
import { useToast } from "../ui/toast-context";
import Modal from "./ui/Modal";
import RegistryHeader from "./ui/RegistryHeader";
import SearchableSelect from "./ui/SearchableSelect";
import StatusBadge from "./ui/StatusBadge";
import SummaryCard from "./ui/SummaryCard";

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const buttonClass =
  "inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50";
const formats = [
  ["csv", "CSV"],
  ["excel", "Excel"],
  ["pdf", "PDF"],
  ["print", "Print"],
];

function dateTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function titleCase(value = "") {
  return value.replaceAll(".", " ").replaceAll("_", " ");
}

function fieldLabel(value = "") {
  return value
    .replace(/Ids?$/, "")
    .replaceAll(".", " ")
    .replaceAll("_", " ")
    .replace(/([a-z\d])([A-Z])/g, "$1 $2")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function displayValue(value) {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "boolean") return value ? "Yes" : "No";
  if (Array.isArray(value)) return value.map(displayValue).join(", ");
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

function changesFor(record) {
  const oldValues = record.displayOldValues ?? record.oldValues ?? {};
  const newValues = record.displayNewValues ?? record.newValues ?? {};
  const keys = new Set([
    ...Object.keys(record.oldValues ?? {}),
    ...Object.keys(record.newValues ?? {}),
  ]);
  return [...keys]
    .filter(
      (key) =>
        JSON.stringify(record.oldValues?.[key]) !==
        JSON.stringify(record.newValues?.[key]),
    )
    .map((key) => ({
      key,
      oldValue: oldValues[key],
      newValue: newValues[key],
    }));
}

export default function LogRegistryPage({ icon: PageIcon, mode }) {
  const audit = mode === "audit";
  const { user, runtimeConfig } = useAuth();
  const toast = useToast();
  const [records, setRecords] = useState([]);
  const [summary, setSummary] = useState({});
  const [options, setOptions] = useState({
    modules: [],
    actions: [],
    users: [],
    recordTypes: [],
  });
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    perPage: runtimeConfig.paginationSize,
    total: 0,
  });
  const [filters, setFilters] = useState({
    search: "",
    module: "",
    action: "",
    userId: "",
    recordType: "",
    dateFrom: "",
    dateTo: "",
    page: 1,
    perPage: runtimeConfig.paginationSize,
  });
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null);
  const [exportOpen, setExportOpen] = useState(false);
  const canExport = hasPermission(
    user,
    audit ? "audit_logs.export" : "activity_logs.export",
  );

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await logApi.list(mode, filters);
      setRecords(audit ? data.auditLogs ?? [] : data.activityLogs ?? []);
      setSummary(data.summary ?? {});
      setOptions(data.options ?? {});
      setPagination(data.pagination ?? {});
    } catch (error) {
      toast.error(error.message);
    } finally {
      setLoading(false);
    }
  }, [audit, filters, mode, toast]);

  useEffect(() => {
    const timer = window.setTimeout(load, filters.search ? 250 : 0);
    return () => window.clearTimeout(timer);
  }, [filters.search, load]);

  function changeFilter(key, value) {
    setFilters((current) => ({ ...current, [key]: value, page: 1 }));
  }

  const hasFilters = useMemo(
    () =>
      Boolean(
        filters.search ||
          filters.module ||
          filters.action ||
          filters.userId ||
          filters.recordType ||
          filters.dateFrom ||
          filters.dateTo,
      ),
    [filters],
  );
  const selectedChanges = selected ? changesFor(selected) : [];

  function exportRecords(format) {
    setExportOpen(false);
    const url = logApi.exportUrl(mode, { ...filters, page: "", format });
    if (format === "print") {
      window.open(url, "_blank", "noopener,noreferrer");
      return;
    }
    window.location.assign(url);
  }

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          canExport && (
            <div className="relative">
              <button
                className={buttonClass}
                onClick={() => setExportOpen((current) => !current)}
                type="button"
              >
                <Download size={17} /> Export
              </button>
              {exportOpen && (
                <div className="absolute right-0 top-12 z-30 min-w-40 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                  {formats.map(([value, label]) => (
                    <button
                      className="block w-full rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-sky-50 hover:text-sky-800"
                      key={value}
                      onClick={() => exportRecords(value)}
                      type="button"
                    >
                      {label}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )
        }
        description={
          audit
            ? "Permanent old-and-new value history for changes to AGIS records, workflows, plans, documents, and registries."
            : "Operational events including authentication, exports, downloads, assignments, profile updates, and administrative actions."
        }
        icon={PageIcon}
        readOnly
        title={audit ? "Audit Trail" : "Activity Log"}
      />

      <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={FileClock}
          label={audit ? "Matching changes" : "Matching activities"}
          tone="sky"
          value={summary.total ?? 0}
        />
        <SummaryCard
          icon={CalendarClock}
          label="Recorded today"
          tone="emerald"
          value={summary.today ?? 0}
        />
        <SummaryCard
          icon={UsersRound}
          label="Distinct actors"
          tone="amber"
          value={summary.actors ?? 0}
        />
        <SummaryCard
          icon={ShieldCheck}
          label={audit ? "Changed records" : "Security events"}
          tone="slate"
          value={audit ? summary.changedRecords ?? 0 : summary.security ?? 0}
        />
      </div>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-3 border-b border-slate-200 p-4 lg:grid-cols-2 xl:grid-cols-[minmax(18rem,1fr)_9rem_13rem_14rem_auto]">
          <label className="relative">
            <Search className="absolute left-3 top-3.5 text-slate-400" size={17} />
            <input
              className={`${inputClass} pl-10`}
              onChange={(event) => changeFilter("search", event.target.value)}
              placeholder={
                audit
                  ? "Search actor, action, record type, or record value..."
                  : "Search actor, employee ID, action, description, or IP..."
              }
              value={filters.search}
            />
          </label>
          <select
            className={inputClass}
            onChange={(event) => changeFilter("module", event.target.value)}
            value={filters.module}
          >
            <option value="">All modules</option>
            {(options.modules ?? []).map((item) => (
              <option key={item}>{item}</option>
            ))}
          </select>
          <SearchableSelect
            onChange={(value) => changeFilter("action", value)}
            options={(options.actions ?? []).map((item) => ({
              value: item,
              label: titleCase(item),
              keywords: item,
            }))}
            placeholder="All actions"
            value={filters.action}
          />
          <SearchableSelect
            onChange={(value) => changeFilter("userId", value)}
            options={(options.users ?? []).map((item) => ({
              value: item.id,
              label: item.name,
              description: item.employeeId,
              keywords: item.employeeId,
            }))}
            placeholder="All actors"
            value={filters.userId}
          />
          <button
            className={buttonClass}
            disabled={!hasFilters}
            onClick={() =>
              setFilters({
                search: "",
                module: "",
                action: "",
                userId: "",
                recordType: "",
                dateFrom: "",
                dateTo: "",
                page: 1,
                perPage: filters.perPage,
              })
            }
            type="button"
          >
            <X size={16} /> Clear
          </button>
        </div>
        <div className="grid gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:grid-cols-2 lg:grid-cols-[15rem_11rem_11rem]">
          {audit && (
            <SearchableSelect
              onChange={(value) => changeFilter("recordType", value)}
              options={options.recordTypes ?? []}
              placeholder="All record types"
              value={filters.recordType}
            />
          )}
          <label className="grid gap-1 text-xs font-bold text-slate-600">
            From date
            <input
              className={inputClass}
              onChange={(event) => changeFilter("dateFrom", event.target.value)}
              type="date"
              value={filters.dateFrom}
            />
          </label>
          <label className="grid gap-1 text-xs font-bold text-slate-600">
            To date
            <input
              className={inputClass}
              min={filters.dateFrom}
              onChange={(event) => changeFilter("dateTo", event.target.value)}
              type="date"
              value={filters.dateTo}
            />
          </label>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3">Date and time</th>
                <th className="px-4 py-3">Actor</th>
                <th className="px-4 py-3">Module / action</th>
                <th className="px-4 py-3">{audit ? "Changed record" : "Activity"}</th>
                <th className="px-4 py-3">{audit ? "Changes" : "IP address"}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading &&
                [1, 2, 3, 4, 5].map((item) => (
                  <tr key={item}>
                    <td className="px-4 py-4" colSpan="5">
                      <div className="h-5 animate-pulse rounded bg-slate-100" />
                    </td>
                  </tr>
                ))}
              {!loading &&
                records.map((record) => {
                  const changes = changesFor(record);
                  return (
                    <tr
                      className="cursor-pointer transition hover:bg-sky-50"
                      key={record.id}
                      onClick={() => setSelected(record)}
                    >
                      <td className="whitespace-nowrap px-4 py-4 text-xs text-slate-500">
                        {dateTime(record.createdAt)}
                      </td>
                      <td className="px-4 py-4">
                        <div className="flex min-w-44 items-center gap-2">
                          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">
                            {record.actorInitials}
                          </span>
                          <span>
                            <strong className="block text-slate-800">{record.actor}</strong>
                            <small className="text-slate-500">{record.actorEmployeeId ?? "System"}</small>
                          </span>
                        </div>
                      </td>
                      <td className="px-4 py-4">
                        <StatusBadge tone={record.module === "CORE" ? "info" : "success"}>
                          {record.module}
                        </StatusBadge>
                        <span className="mt-1 block whitespace-nowrap text-xs capitalize text-slate-600">
                          {titleCase(record.action)}
                        </span>
                      </td>
                      <td className="min-w-72 px-4 py-4 text-slate-700">
                        {audit ? (
                          <>
                            <strong className="block">{record.recordLabel}</strong>
                            <small className="text-slate-500">
                              {record.recordType}
                            </small>
                          </>
                        ) : (
                          record.description
                        )}
                      </td>
                      <td className="px-4 py-4 text-xs text-slate-600">
                        {audit ? `${changes.length} field${changes.length === 1 ? "" : "s"}` : record.ipAddress ?? "—"}
                      </td>
                    </tr>
                  );
                })}
              {!loading && records.length === 0 && (
                <tr>
                  <td className="px-4 py-16 text-center text-slate-500" colSpan="5">
                    No records match the selected filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <footer className="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center">
          <span className="text-xs text-slate-500">
            Showing {pagination.from ?? 0}–{pagination.to ?? 0} of {pagination.total ?? 0}
          </span>
          <div className="flex flex-wrap items-center gap-2 sm:ml-auto">
            <select
              className="h-10 rounded-lg border border-slate-300 px-2 text-xs"
              onChange={(event) =>
                setFilters((current) => ({
                  ...current,
                  perPage: Number(event.target.value),
                  page: 1,
                }))
              }
              value={filters.perPage}
            >
              {[10, 25, 50, 100].map((count) => (
                <option key={count} value={count}>{count} per page</option>
              ))}
            </select>
            <button
              className={buttonClass}
              disabled={pagination.currentPage <= 1}
              onClick={() =>
                setFilters((current) => ({ ...current, page: current.page - 1 }))
              }
              type="button"
            >
              Previous
            </button>
            <span className="text-xs font-bold text-slate-600">
              {pagination.currentPage ?? 1} / {pagination.lastPage ?? 1}
            </span>
            <button
              className={buttonClass}
              disabled={pagination.currentPage >= pagination.lastPage}
              onClick={() =>
                setFilters((current) => ({ ...current, page: current.page + 1 }))
              }
              type="button"
            >
              Next
            </button>
          </div>
        </footer>
      </section>

      <Modal
        description={
          selected
            ? `${selected.module} · ${titleCase(selected.action)} · ${dateTime(selected.createdAt)}`
            : ""
        }
        onClose={() => setSelected(null)}
        open={Boolean(selected)}
        size="lg"
        title={audit ? selected?.recordLabel ?? "Audit change" : selected?.description ?? "Activity"}
      >
        {selected && (
          <div className="grid gap-5">
            <dl className="grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
              <div><dt className="text-xs font-bold uppercase text-slate-400">Actor</dt><dd className="mt-1 font-semibold">{selected.actor} ({selected.actorEmployeeId ?? "System"})</dd></div>
              <div><dt className="text-xs font-bold uppercase text-slate-400">IP address</dt><dd className="mt-1 font-semibold">{selected.ipAddress ?? "—"}</dd></div>
              {audit && <div><dt className="text-xs font-bold uppercase text-slate-400">Record</dt><dd className="mt-1 font-semibold">{selected.recordType} · {selected.recordLabel}</dd></div>}
              {!audit && selected.subject && <div><dt className="text-xs font-bold uppercase text-slate-400">Affected user</dt><dd className="mt-1 font-semibold">{selected.subject} ({selected.subjectEmployeeId})</dd></div>}
              <div className="sm:col-span-2"><dt className="text-xs font-bold uppercase text-slate-400">Browser / client</dt><dd className="mt-1 break-all text-xs text-slate-600">{selected.userAgent ?? "—"}</dd></div>
            </dl>
            <section>
              <h3 className="mb-2 font-bold text-slate-800">Recorded changes</h3>
              {selectedChanges.length ? (
                <div className="overflow-hidden rounded-xl border border-slate-200">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500"><tr><th className="px-3 py-2">Field</th><th className="px-3 py-2">Old value</th><th className="px-3 py-2">New value</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                      {selectedChanges.map((change) => (
                        <tr key={change.key}>
                          <th className="px-3 py-3 text-slate-700">{fieldLabel(change.key)}</th>
                          <td className="max-w-64 break-words px-3 py-3 text-red-600">{displayValue(change.oldValue)}</td>
                          <td className="max-w-64 break-words px-3 py-3 text-emerald-700">{displayValue(change.newValue)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="rounded-lg border border-dashed p-4 text-sm text-slate-500">This event did not change stored field values.</p>
              )}
            </section>
            {selected.displayMetadata && Object.keys(selected.displayMetadata).length > 0 && (
              <section>
                <h3 className="mb-2 font-bold text-slate-800">Event metadata</h3>
                <pre className="overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs leading-6 text-slate-100">{JSON.stringify(selected.displayMetadata, null, 2)}</pre>
              </section>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
