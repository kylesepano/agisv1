import { useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  Ellipsis,
  Filter,
  Plus,
  Search,
} from "lucide-react";
import { useNavigate } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsEngagementApi } from "../../services/api";

const phaseLabels = {
  FOUNDATION: "Planning",
  PLANNING: "Planning",
  EXECUTION: "Execution",
  ISSUES_AFR: "Issues",
  CONFERENCES: "Execution",
  REPORTING: "Audit Reports",
  COMPLETION_TRANSFER: "Completion",
  CLOSURE: "Completion",
};

const statusLabels = {
  DRAFT: "Draft",
  AUTHORIZATION_PREPARATION: "For Authorization",
  RETURNED_FOR_REVISION: "Under Review",
  AUTHORIZED: "Authorized",
  ENGAGEMENT_PLANNING: "In Progress",
  ENTRY_CONFERENCE: "In Progress",
  FIELDWORK: "In Progress",
  FINDINGS_COMMUNICATION: "Under Review",
  EXIT_CONFERENCE: "Under Review",
  REPORTING: "Draft AFR",
  ISSUED: "Issued",
  CLOSURE_REVIEW: "For Closure",
  COMPLETED: "For Closure",
  CLOSED: "Closed",
  SUSPENDED: "Suspended",
  CANCELLED: "Cancelled",
};

function formatDate(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "2-digit",
    year: "numeric",
  }).format(new Date(value.includes("T") ? value : `${value}T00:00:00`));
}

function leaderFor(engagement) {
  const members = engagement.teamMembers ?? [];
  return (
    members.find((member) =>
      ["TEAM_LEADER", "AUDIT_TEAM_LEADER", "LEAD_AUDITOR"].includes(
        member.assignmentRoleCode,
      ),
    )?.user?.name ??
    members[0]?.user?.name ??
    "Not assigned"
  );
}

function healthFor(engagement) {
  if (["CLOSED", "COMPLETED", "ISSUED"].includes(engagement.status)) {
    return { label: "On Track", tone: "text-emerald-600" };
  }
  if (!engagement.plannedEndDate) {
    return { label: "At Risk", tone: "text-amber-500" };
  }
  const days = Math.ceil(
    (new Date(`${engagement.plannedEndDate}T23:59:59`) - new Date()) /
      86400000,
  );
  if (days < 0) return { label: "Delayed", tone: "text-red-600" };
  if (days <= 30) return { label: "At Risk", tone: "text-amber-500" };
  return { label: "On Track", tone: "text-emerald-600" };
}

export default function AemsEngagementRegistryPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [engagements, setEngagements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [phase, setPhase] = useState("");
  const [status, setStatus] = useState("");
  const [sort, setSort] = useState("code-asc");
  const [showFilters, setShowFilters] = useState(false);
  const [pageSize, setPageSize] = useState(10);
  const [page, setPage] = useState(1);
  const canCreate = hasPermission(user, "aems.engagement.create");

  useEffect(() => {
    let active = true;
    aemsEngagementApi
      .list({ perPage: 100, sortBy: "engagement_code", sortDirection: "asc" })
      .then((result) => active && setEngagements(result.engagements))
      .catch(
        (reason) =>
          active &&
          setError(
            reason instanceof Error
              ? reason.message
              : "Unable to load engagements.",
          ),
      )
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, []);

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    const rows = engagements.filter((engagement) => {
      const matchesQuery =
        !query ||
        [
          engagement.engagementCode,
          engagement.title,
          ...((engagement.offices ?? []).flatMap((office) => [
            office.code,
            office.name,
          ])),
        ].some((value) => String(value ?? "").toLowerCase().includes(query));
      return (
        matchesQuery &&
        (!phase || engagement.phase === phase) &&
        (!status || engagement.status === status)
      );
    });
    return rows.sort((left, right) => {
      if (sort === "updated-desc") {
        return new Date(right.updatedAt) - new Date(left.updatedAt);
      }
      if (sort === "title-asc") return left.title.localeCompare(right.title);
      return left.engagementCode.localeCompare(right.engagementCode);
    });
  }, [engagements, phase, search, sort, status]);

  const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
  const currentPage = Math.min(page, pageCount);
  const rows = filtered.slice(
    (currentPage - 1) * pageSize,
    currentPage * pageSize,
  );
  const overdue = engagements.filter(
    (item) =>
      item.plannedEndDate &&
      new Date(`${item.plannedEndDate}T23:59:59`) < new Date() &&
      !["CLOSED", "COMPLETED", "ISSUED", "CANCELLED"].includes(item.status),
  ).length;
  const dueReporting = engagements.filter((item) => {
    if (
      !item.expectedReportDate ||
      ["ISSUED", "CLOSED"].includes(item.status)
    )
      return false;
    const days = Math.ceil(
      (new Date(`${item.expectedReportDate}T23:59:59`) - new Date()) /
        86400000,
    );
    return days >= 0 && days <= 30;
  }).length;
  const incomplete = engagements.filter(
    (item) => item.status === "DRAFT" && !(item.auditAreas ?? []).length,
  ).length;

  return (
    <main className="min-w-0 bg-[#eef7fa] px-5 py-5 text-[#0c318d] sm:px-8">
      <div className="mb-5 text-sm text-sky-700">
        <span>Home</span>
        <span className="mx-2 text-slate-400">›</span>
        <span>Audit Engagements Workspace</span>
      </div>

      <div className="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-4">
            <h2 className="text-3xl font-semibold tracking-tight text-[#073b9b] sm:text-[2.35rem]">
              Audit Engagements Workspace
            </h2>
            <span className="text-sm text-red-500">[WF-SCR-200-01]</span>
          </div>
          <p className="mt-1 text-sm text-[#154da8]">
            Search, filter, and manage audit engagements. Select an engagement
            to view details and access its component workspaces.
          </p>
        </div>
        {canCreate && (
          <button
            className="inline-flex h-12 items-center gap-2 rounded-lg bg-[#087bea] px-5 font-semibold text-white shadow-sm hover:bg-[#0668ca]"
            onClick={() =>
              navigate("/audit-engagement-management/create?source=planned")
            }
            type="button"
          >
            <Plus size={19} /> New Audit Engagement
          </button>
        )}
      </div>

      <div className="mx-auto mb-7 flex max-w-3xl items-center rounded-full border-2 border-[#84bdd7] bg-white px-5 py-3 shadow-sm focus-within:border-sky-500">
        <Search className="mr-4 text-sky-500" size={30} strokeWidth={2.5} />
        <input
          className="min-w-0 flex-1 bg-transparent text-center text-base text-slate-700 outline-none placeholder:text-slate-400"
          onChange={(event) => {
            setSearch(event.target.value);
            setPage(1);
          }}
          placeholder="Search by engagement code, title, office, or keyword"
          value={search}
        />
      </div>

      {error && (
        <div className="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <div className="mb-3 flex flex-wrap items-center gap-4 text-sm text-slate-600">
        <span>
          Showing {filtered.length ? (currentPage - 1) * pageSize + 1 : 0}–
          {Math.min(currentPage * pageSize, filtered.length)} of {filtered.length}{" "}
          engagements
        </span>
        <button
          className="ml-auto inline-flex items-center gap-2 px-2 py-1 hover:text-sky-700"
          onClick={() => setShowFilters((value) => !value)}
          type="button"
        >
          <Filter size={17} /> More Filters
        </button>
        <label className="flex items-center gap-3">
          <span>Sort by</span>
          <select
            className="h-9 rounded border border-sky-200 bg-white px-4 text-slate-600"
            onChange={(event) => setSort(event.target.value)}
            value={sort}
          >
            <option value="code-asc">Engagement Code (A-Z)</option>
            <option value="title-asc">Title (A-Z)</option>
            <option value="updated-desc">Last Updated</option>
          </select>
        </label>
      </div>

      {showFilters && (
        <div className="mb-3 flex flex-wrap gap-3 rounded-lg border border-sky-200 bg-white p-3">
          <select
            className="h-10 rounded border border-slate-300 px-3 text-sm text-slate-700"
            onChange={(event) => {
              setPhase(event.target.value);
              setPage(1);
            }}
            value={phase}
          >
            <option value="">All phases</option>
            {Object.entries(phaseLabels).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
          <select
            className="h-10 rounded border border-slate-300 px-3 text-sm text-slate-700"
            onChange={(event) => {
              setStatus(event.target.value);
              setPage(1);
            }}
            value={status}
          >
            <option value="">All statuses</option>
            {Object.entries(statusLabels).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
          <button
            className="text-sm font-semibold text-sky-700"
            onClick={() => {
              setPhase("");
              setStatus("");
              setPage(1);
            }}
            type="button"
          >
            Clear filters
          </button>
        </div>
      )}

      <section className="overflow-x-auto rounded-md border border-[#73b6d6] bg-white">
        <table className="w-full min-w-[1120px] border-collapse text-left text-sm text-slate-700">
          <thead className="bg-[#dcecff] text-[#173e9d]">
            <tr>
              {[
                "Engagement ID",
                "Title",
                "Office",
                "Phase",
                "Status",
                "Health",
                "Team Leader",
                "Last Updated",
                "Actions",
              ].map((heading) => (
                <th
                  className="border-r border-[#aed1e5] px-4 py-3 text-center font-semibold last:border-r-0"
                  key={heading}
                >
                  {heading}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {loading &&
              Array.from({ length: 6 }).map((_, index) => (
                <tr key={index}>
                  <td
                    className="h-12 animate-pulse border-t border-sky-100 bg-slate-50"
                    colSpan="9"
                  />
                </tr>
              ))}
            {!loading &&
              rows.map((engagement) => {
                const health = healthFor(engagement);
                return (
                  <tr
                    className="cursor-pointer border-t border-[#b7d5e7] hover:bg-sky-50"
                    key={engagement.id}
                    onClick={() =>
                      navigate(`/audit-engagement-management/${engagement.id}`)
                    }
                  >
                    <td className="border-r border-[#c7deeb] px-4 py-3 font-medium text-[#0878ef]">
                      {engagement.engagementCode}
                    </td>
                    <td className="border-r border-[#c7deeb] px-4 py-3">
                      {engagement.title}
                    </td>
                    <td className="border-r border-[#c7deeb] px-4 py-3">
                      {engagement.offices?.[0]?.code ??
                        engagement.offices?.[0]?.name ??
                        "—"}
                    </td>
                    <td className="border-r border-[#c7deeb] px-4 py-3">
                      {phaseLabels[engagement.phase] ?? "Planning"}
                    </td>
                    <td className="border-r border-[#c7deeb] px-4 py-3">
                      {statusLabels[engagement.status] ?? engagement.status}
                    </td>
                    <td
                      className={`border-r border-[#c7deeb] px-4 py-3 font-semibold ${health.tone}`}
                    >
                      {health.label}
                    </td>
                    <td className="border-r border-[#c7deeb] px-4 py-3">
                      {leaderFor(engagement)}
                    </td>
                    <td className="border-r border-[#c7deeb] px-4 py-3">
                      {formatDate(engagement.updatedAt)}
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center justify-center gap-4">
                        <button
                          className="rounded border-2 border-[#1689ee] px-4 py-0.5 text-[#0878ef]"
                          onClick={(event) => {
                            event.stopPropagation();
                            navigate(
                              `/audit-engagement-management/${engagement.id}`,
                            );
                          }}
                          type="button"
                        >
                          Open
                        </button>
                        <button
                          aria-label="More actions"
                          className="text-slate-900"
                          onClick={(event) => event.stopPropagation()}
                          type="button"
                        >
                          <Ellipsis size={24} />
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            {!loading && rows.length === 0 && (
              <tr>
                <td
                  className="px-4 py-12 text-center text-slate-500"
                  colSpan="9"
                >
                  No audit engagements match the current filters.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </section>

      <div className="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-600">
        <span>Show</span>
        <select
          className="h-9 rounded border border-sky-200 bg-white px-3"
          onChange={(event) => {
            setPageSize(Number(event.target.value));
            setPage(1);
          }}
          value={pageSize}
        >
          {[10, 25, 50].map((size) => (
            <option key={size} value={size}>
              {size}
            </option>
          ))}
        </select>
        <span>entries</span>
        <div className="ml-auto flex items-center gap-1">
          <button
            className="grid h-9 w-9 place-items-center rounded border border-sky-200 bg-white disabled:opacity-40"
            disabled={currentPage === 1}
            onClick={() => setPage((value) => Math.max(1, value - 1))}
            type="button"
          >
            <ChevronLeft size={18} />
          </button>
          {Array.from({ length: pageCount }, (_, index) => index + 1)
            .slice(Math.max(0, currentPage - 3), currentPage + 2)
            .map((number) => (
              <button
                className={`h-9 min-w-9 rounded border px-2 ${
                  number === currentPage
                    ? "border-[#0878ef] bg-[#0878ef] text-white"
                    : "border-sky-200 bg-white"
                }`}
                key={number}
                onClick={() => setPage(number)}
                type="button"
              >
                {number}
              </button>
            ))}
          <button
            className="grid h-9 w-9 place-items-center rounded border border-sky-200 bg-white disabled:opacity-40"
            disabled={currentPage === pageCount}
            onClick={() => setPage((value) => Math.min(pageCount, value + 1))}
            type="button"
          >
            <ChevronRight size={18} />
          </button>
        </div>
      </div>

      <section className="mt-5 max-w-2xl overflow-hidden rounded-md border border-[#73b6d6] bg-white">
        <header className="flex items-center gap-3 border-b border-[#73b6d6] bg-[#dcecff] px-5 py-3">
          <AlertTriangle size={24} />
          <h3 className="text-lg font-semibold">Needs Attention</h3>
          <span className="ml-auto text-sm text-slate-700">
            {overdue + dueReporting + incomplete} items
          </span>
        </header>
        <div className="space-y-3 px-5 py-4 text-sm">
          {[
            [overdue, "Overdue reviews", "Items past due for review", "bg-red-500"],
            [dueReporting, "Engagement due for reporting", "Target end date within 30 days", "bg-red-500"],
            [incomplete, "Incomplete planning component", "Missing required elements", "bg-amber-500"],
          ].map(([count, title, note, color]) => (
            <div className="flex items-center gap-3" key={title}>
              <span
                className={`grid h-6 w-6 place-items-center rounded-full text-xs font-bold text-white ${color}`}
              >
                {count}
              </span>
              <div>
                <strong className="block text-[#173e9d]">{title}</strong>
                <span className="text-xs text-slate-500">{note}</span>
              </div>
              <ChevronRight className="ml-auto text-slate-400" size={18} />
            </div>
          ))}
        </div>
      </section>
    </main>
  );
}
