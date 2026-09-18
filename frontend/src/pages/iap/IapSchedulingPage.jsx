import {
  AlertTriangle,
  CalendarClock,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  History,
  LayoutGrid,
  List,
  Pencil,
  RefreshCw,
  UsersRound,
  XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import IapScheduleForm from "../../components/iap/IapScheduleForm";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { ApiError, schedulingApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const statusTone = {
  UNSCHEDULED: "inactive",
  SCHEDULED: "success",
  CANCELLED: "danger",
};

function formatDate(value, time = false) {
  if (!value) return "—";
  const date = new Date(time ? value : `${value}T00:00:00`);
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(time ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(date);
}

function dateKey(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function monthCells(monthDate) {
  const year = monthDate.getFullYear();
  const month = monthDate.getMonth();
  const first = new Date(year, month, 1);
  const start = new Date(year, month, 1 - first.getDay());
  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(start);
    date.setDate(start.getDate() + index);
    return {
      date,
      key: dateKey(date),
      currentMonth: date.getMonth() === month,
    };
  });
}

/**
 * Schedules approved plan engagements while surfacing auditor, office, date,
 * skill, and person-day conflicts in table and calendar views.
 */
export default function IapSchedulingPage() {
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const toast = useToast();
  const [data, setData] = useState({
    schedules: [],
    plans: [],
    auditors: [],
    teamRoles: [],
    capacities: [],
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [loadError, setLoadError] = useState("");
  const [view, setView] = useState("table");
  const [search, setSearch] = useState("");
  const [planId, setPlanId] = useState("");
  const [status, setStatus] = useState("");
  const [editing, setEditing] = useState(null);
  const [selected, setSelected] = useState(null);
  useRecordView(selected, {
    module: "IAP",
    recordType: "SCHEDULE",
    code: (record) => record.engagementCode,
    label: (record) => record.title,
  });
  const [cancelTarget, setCancelTarget] = useState(null);
  const [cancelReason, setCancelReason] = useState("");
  const [calendarMonth, setCalendarMonth] = useState(
    () => new Date(new Date().getFullYear(), new Date().getMonth(), 1),
  );
  const canSchedule = hasPermission(user, "iap.assign_team");
  const isManagement = hasPermission(user, "iap.manage_universe");
  const canModify = (schedule) =>
    canSchedule &&
    ["DRAFT", "RETURNED_FOR_REVISION"].includes(schedule.plan.status) &&
    (isManagement || Number(schedule.plan.preparedBy) === Number(user.id));

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError("");
    try {
      const result = await schedulingApi.list();
      setData(result);
      const requestedEngagement = Number(searchParams.get("engagement"));
      if (requestedEngagement) {
        setSelected(
          result.schedules.find(
            (item) => Number(item.id) === requestedEngagement,
          ) ?? null,
        );
      }
      if (result.plans.length > 0) {
        setCalendarMonth((current) =>
          current.getFullYear() === result.plans[0].fiscalYear
            ? current
            : new Date(result.plans[0].fiscalYear, 0, 1),
        );
      }
    } catch (error) {
      setLoadError(
        error instanceof Error
          ? error.message
          : "Unable to load audit schedules.",
      );
    } finally {
      setLoading(false);
    }
  }, [searchParams]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const visibleSchedules = useMemo(() => {
    const term = search.trim().toLowerCase();
    return data.schedules.filter((schedule) => {
      if (planId && Number(schedule.plan.id) !== Number(planId)) return false;
      if (status && schedule.scheduleStatus !== status) return false;
      if (!term) return true;
      return [
        schedule.engagementCode,
        schedule.title,
        schedule.plan.planCode,
        ...schedule.offices.flatMap((office) => [office.code, office.name]),
        ...schedule.teamMembers.flatMap((member) => [
          member.user?.name,
          member.user?.employeeId,
        ]),
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(term),
      );
    });
  }, [data.schedules, planId, search, status]);

  const metrics = useMemo(
    () => ({
      total: visibleSchedules.length,
      scheduled: visibleSchedules.filter(
        (schedule) => schedule.scheduleStatus === "SCHEDULED",
      ).length,
      conflicts: visibleSchedules.filter(
        (schedule) => schedule.conflicts.length > 0,
      ).length,
      cancelled: visibleSchedules.filter(
        (schedule) => schedule.scheduleStatus === "CANCELLED",
      ).length,
      personDays: visibleSchedules
        .filter((schedule) => schedule.scheduleStatus === "SCHEDULED")
        .reduce((total, schedule) => total + schedule.estimatedPersonDays, 0),
    }),
    [visibleSchedules],
  );

  async function checkConflicts(payload) {
    setErrors({});
    try {
      return await schedulingApi.checkConflicts(editing.id, payload);
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error ? error.message : "Unable to check conflicts.",
      );
      return [];
    }
  }

  async function saveSchedule(payload) {
    setSaving(true);
    setErrors({});
    try {
      await schedulingApi.update(editing.id, payload);
      toast.success(
        editing.scheduleStatus === "UNSCHEDULED"
          ? "Audit scheduled successfully."
          : "Audit rescheduled successfully.",
      );
      setEditing(null);
      await load();
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error ? error.message : "Unable to save the schedule.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function cancelSchedule() {
    if (!cancelTarget) return;
    setSaving(true);
    setErrors({});
    try {
      await schedulingApi.cancel(cancelTarget.id, {
        reason: cancelReason,
        lockVersion: cancelTarget.plan.lockVersion,
      });
      toast.success("Schedule cancelled and retained in its history.");
      setCancelTarget(null);
      setCancelReason("");
      await load();
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to cancel the schedule.",
      );
    } finally {
      setSaving(false);
    }
  }

  const columns = [
    {
      key: "engagementCode",
      label: "Engagement",
      render: (schedule) => (
        <span>
          <strong className="block text-sm text-slate-800">
            {schedule.engagementCode}
          </strong>
          <span className="mt-0.5 block max-w-sm text-xs text-slate-500">
            {schedule.title}
          </span>
          <span className="mt-1 block text-[11px] font-semibold text-sky-700">
            {schedule.plan.planCode}
          </span>
        </span>
      ),
    },
    {
      key: "plannedStartDate",
      label: "Audit Dates",
      render: (schedule) => (
        <span className="whitespace-nowrap text-xs">
          {formatDate(schedule.plannedStartDate)}
          <span className="mx-1 text-slate-300">→</span>
          {formatDate(schedule.plannedEndDate)}
        </span>
      ),
    },
    {
      key: "expectedReportDate",
      label: "Expected Report",
      render: (schedule) => formatDate(schedule.expectedReportDate),
    },
    {
      key: "offices",
      label: "Office",
      sortable: false,
      render: (schedule) => (
        <span className="text-xs">
          {schedule.offices.map((office) => office.code).join(", ") || "—"}
        </span>
      ),
    },
    {
      key: "teamMembers",
      label: "Team Leader / Team",
      sortable: false,
      render: (schedule) => {
        const leader = schedule.teamMembers.find(
          (member) => member.teamRoleCode === "LEAD_AUDITOR",
        );
        return (
          <span>
            <strong className="block text-xs text-slate-700">
              {leader?.user?.name ?? "Not assigned"}
            </strong>
            <span className="text-[11px] text-slate-500">
              {schedule.teamMembers.length} team member
              {schedule.teamMembers.length === 1 ? "" : "s"} ·{" "}
              {schedule.estimatedPersonDays} days
            </span>
          </span>
        );
      },
    },
    {
      key: "conflicts",
      label: "Conflicts",
      sortable: false,
      render: (schedule) =>
        schedule.conflicts.length ? (
          <StatusBadge tone="danger">
            {schedule.conflicts.length} detected
          </StatusBadge>
        ) : (
          <StatusBadge tone="success">Clear</StatusBadge>
        ),
    },
    {
      key: "scheduleStatus",
      label: "Status",
      render: (schedule) => (
        <StatusBadge tone={statusTone[schedule.scheduleStatus]}>
          {schedule.scheduleStatus}
        </StatusBadge>
      ),
    },
    ...(canSchedule
      ? [
          {
            key: "actions",
            label: "Actions",
            sortable: false,
            render: (schedule) =>
              canModify(schedule) ? (
                <div
                  className="flex justify-end gap-1.5"
                  onClick={(event) => event.stopPropagation()}
                >
                  <button
                    aria-label="Edit schedule"
                    className="grid h-9 w-9 place-items-center rounded-lg border border-sky-200 text-sky-700 transition hover:bg-sky-50"
                    onClick={() => {
                      setErrors({});
                      setEditing(schedule);
                    }}
                    type="button"
                  >
                    <Pencil size={16} />
                  </button>
                  {schedule.scheduleStatus === "SCHEDULED" && (
                    <button
                      aria-label="Cancel schedule"
                      className="grid h-9 w-9 place-items-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50"
                      onClick={() => {
                        setErrors({});
                        setCancelReason("");
                        setCancelTarget(schedule);
                      }}
                      type="button"
                    >
                      <XCircle size={16} />
                    </button>
                  )}
                </div>
              ) : (
                <span className="text-xs text-slate-400">View only</span>
              ),
          },
        ]
      : []),
  ];

  const cells = useMemo(() => monthCells(calendarMonth), [calendarMonth]);
  const monthLabel = new Intl.DateTimeFormat("en-PH", {
    month: "long",
    year: "numeric",
  }).format(calendarMonth);
  const selectedYear =
    data.plans.find((plan) => Number(plan.id) === Number(planId))?.fiscalYear ??
    calendarMonth.getFullYear();
  const capacityRows = data.capacities.filter(
    (entry) => Number(entry.fiscalYear) === Number(selectedYear),
  );

  if (loading) {
    return (
      <main className="grid min-h-[calc(100vh-7rem)] place-items-center p-5">
        <span className="flex items-center gap-3 text-sm font-semibold text-slate-500">
          <RefreshCw className="animate-spin" size={20} />
          Loading audit schedules...
        </span>
      </main>
    );
  }

  return (
    <main className="p-3 sm:p-5">
      <RegistryHeader
        description="Coordinate planned audits and dates using ARMIS-authoritative team capacity, availability, and conflict checks."
        icon={CalendarClock}
        title="Audit Scheduling"
      />

      {loadError && (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {loadError}
        </div>
      )}

      <section className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <SummaryCard
          icon={CalendarDays}
          label="Plan Engagements"
          tone="sky"
          value={metrics.total}
        />
        <SummaryCard
          icon={CalendarClock}
          label="Scheduled"
          tone="emerald"
          value={metrics.scheduled}
        />
        <SummaryCard
          icon={AlertTriangle}
          label="With Conflicts"
          tone="red"
          value={metrics.conflicts}
        />
        <SummaryCard
          icon={XCircle}
          label="Cancelled"
          tone="amber"
          value={metrics.cancelled}
        />
        <SummaryCard
          icon={UsersRound}
          label="Scheduled Person-days"
          tone="slate"
          value={metrics.personDays}
        />
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-3 border-b border-slate-200 p-4 lg:grid-cols-[minmax(16rem,1fr)_18rem_14rem_auto]">
          <label className="relative">
            <span className="sr-only">Search schedules</span>
            <input
              className="min-h-11 w-full rounded-lg border border-slate-300 px-4 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search engagement, office, or auditor..."
              value={search}
            />
          </label>
          <SearchableSelect
            onChange={setPlanId}
            options={data.plans.map((plan) => ({
              value: plan.id,
              label: `${plan.planCode} — ${plan.title}`,
              keywords: `${plan.fiscalYear} ${plan.status}`,
            }))}
            placeholder="All annual plans"
            value={planId}
          />
          <SearchableSelect
            onChange={setStatus}
            options={[
              { value: "UNSCHEDULED", label: "Unscheduled" },
              { value: "SCHEDULED", label: "Scheduled" },
              { value: "CANCELLED", label: "Cancelled" },
            ]}
            placeholder="All schedule statuses"
            value={status}
          />
          <div className="flex rounded-lg border border-slate-200 bg-slate-50 p-1">
            {[
              ["table", List, "Table"],
              ["calendar", LayoutGrid, "Calendar"],
            ].map(([mode, Icon, label]) => (
              <button
                className={`inline-flex min-h-9 items-center gap-2 rounded-md px-3 text-xs font-bold transition ${
                  view === mode
                    ? "bg-white text-sky-700 shadow-sm"
                    : "text-slate-500 hover:text-slate-800"
                }`}
                key={mode}
                onClick={() => setView(mode)}
                type="button"
              >
                <Icon size={15} />
                {label}
              </button>
            ))}
          </div>
        </div>

        {view === "table" ? (
          <DataTable
            columns={columns}
            emptyMessage="No audit engagements match the scheduling filters."
            onRowClick={setSelected}
            rows={visibleSchedules}
          />
        ) : (
          <div className="overflow-x-auto p-4">
            <div className="mb-3 flex items-center justify-between">
              <button
                className="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                onClick={() =>
                  setCalendarMonth(
                    (current) =>
                      new Date(
                        current.getFullYear(),
                        current.getMonth() - 1,
                        1,
                      ),
                  )
                }
                type="button"
              >
                <ChevronLeft size={18} />
              </button>
              <h2 className="text-base font-bold text-slate-800">
                {monthLabel}
              </h2>
              <button
                className="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                onClick={() =>
                  setCalendarMonth(
                    (current) =>
                      new Date(
                        current.getFullYear(),
                        current.getMonth() + 1,
                        1,
                      ),
                  )
                }
                type="button"
              >
                <ChevronRight size={18} />
              </button>
            </div>
            <div className="grid min-w-[760px] grid-cols-7 border-l border-t border-slate-200 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500">
              {["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].map((day) => (
                <div className="border-b border-r p-2" key={day}>
                  {day}
                </div>
              ))}
              {cells.map((cell) => {
                const daySchedules = visibleSchedules.filter(
                  (schedule) =>
                    schedule.scheduleStatus === "SCHEDULED" &&
                    schedule.plannedStartDate <= cell.key &&
                    schedule.plannedEndDate >= cell.key,
                );
                return (
                  <div
                    className={`min-h-28 border-b border-r p-1.5 text-left ${
                      cell.currentMonth ? "bg-white" : "bg-slate-50"
                    }`}
                    key={cell.key}
                  >
                    <span
                      className={`text-xs font-bold ${
                        cell.currentMonth ? "text-slate-700" : "text-slate-400"
                      }`}
                    >
                      {cell.date.getDate()}
                    </span>
                    <div className="mt-1 grid gap-1">
                      {daySchedules.slice(0, 3).map((schedule) => (
                        <button
                          className={`truncate rounded px-1.5 py-1 text-left text-[10px] font-bold ${
                            schedule.conflicts.length
                              ? "bg-red-100 text-red-800"
                              : "bg-sky-100 text-sky-800"
                          }`}
                          key={schedule.id}
                          onClick={() => setSelected(schedule)}
                          title={`${schedule.engagementCode} — ${schedule.title}`}
                          type="button"
                        >
                          {schedule.engagementCode}
                        </button>
                      ))}
                      {daySchedules.length > 3 && (
                        <span className="text-[10px] text-slate-500">
                          +{daySchedules.length - 3} more
                        </span>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}
      </section>

      <section className="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="border-b border-slate-200 px-5 py-4">
          <h2 className="text-base font-bold text-slate-800">
            Auditor Capacity · {selectedYear}
          </h2>
          <p className="mt-1 text-xs text-slate-500">
            Scheduled person-days compared with the ARMIS annual capacity
            ledger. Resource changes are managed in ARMIS Planning.
          </p>
        </header>
        <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
          {capacityRows.map((capacity) => {
            const auditor = data.auditors.find(
              (candidate) => candidate.id === capacity.userId,
            );
            const percent =
              capacity.availablePersonDays > 0
                ? Math.min(
                    100,
                    (capacity.allocatedPersonDays /
                      capacity.availablePersonDays) *
                      100,
                  )
                : 100;
            return (
              <article
                className="rounded-xl border border-slate-200 bg-slate-50 p-4"
                key={`${capacity.fiscalYear}-${capacity.userId}`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <strong className="text-sm text-slate-800">
                      {auditor?.name}
                    </strong>
                    <p className="mt-0.5 text-xs text-slate-500">
                      {auditor?.employeeId}
                    </p>
                  </div>
                  <span className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    Managed in ARMIS
                  </span>
                </div>
                <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                  <div
                    className={`h-full rounded-full ${
                      capacity.remainingPersonDays < 0
                        ? "bg-red-500"
                        : "bg-sky-600"
                    }`}
                    style={{ width: `${percent}%` }}
                  />
                </div>
                <div className="mt-2 flex justify-between text-xs">
                  <span>{capacity.allocatedPersonDays} allocated</span>
                  <strong
                    className={
                      capacity.remainingPersonDays < 0
                        ? "text-red-600"
                        : "text-slate-700"
                    }
                  >
                    {capacity.remainingPersonDays} remaining
                  </strong>
                </div>
              </article>
            );
          })}
        </div>
      </section>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => setEditing(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="min-h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={saving}
              form="iap-schedule-form"
              type="submit"
            >
              {saving
                ? "Saving..."
                : editing?.scheduleStatus === "UNSCHEDULED"
                  ? "Save schedule"
                  : editing?.scheduleStatus === "CANCELLED"
                    ? "Reinstate schedule"
                    : "Save reschedule"}
            </button>
          </>
        }
        onClose={() => !saving && setEditing(null)}
        open={Boolean(editing)}
        size="xl"
        title={
          editing?.scheduleStatus === "UNSCHEDULED"
            ? "Schedule planned audit"
            : "Update audit schedule"
        }
      >
        {editing && (
          <IapScheduleForm
            auditors={data.auditors}
            capacities={data.capacities}
            errors={errors}
            key={`${editing.id}-${editing.plan.lockVersion}`}
            onCheck={checkConflicts}
            onSubmit={saveSchedule}
            schedule={editing}
            teamRoles={data.teamRoles}
          />
        )}
      </Modal>

      <Modal
        onClose={() => setSelected(null)}
        open={Boolean(selected)}
        size="xl"
        title="Audit schedule details"
      >
        {selected && (
          <div className="grid gap-5">
            <section className="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
                    {selected.plan.planCode} · {selected.engagementCode}
                  </p>
                  <h3 className="mt-1 text-lg font-bold text-slate-900">
                    {selected.title}
                  </h3>
                </div>
                <StatusBadge tone={statusTone[selected.scheduleStatus]}>
                  {selected.scheduleStatus}
                </StatusBadge>
              </div>
              <dl className="mt-4 grid gap-4 sm:grid-cols-3">
                {[
                  [
                    "Audit period",
                    `${formatDate(selected.plannedStartDate)} – ${formatDate(selected.plannedEndDate)}`,
                  ],
                  ["Expected report", formatDate(selected.expectedReportDate)],
                  ["Person-days", selected.estimatedPersonDays],
                ].map(([label, value]) => (
                  <div key={label}>
                    <dt className="text-xs font-bold uppercase text-slate-400">
                      {label}
                    </dt>
                    <dd className="mt-1 text-sm font-semibold text-slate-700">
                      {value}
                    </dd>
                  </div>
                ))}
              </dl>
            </section>

            <section>
              <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                <UsersRound size={17} />
                Proposed team
              </h3>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {selected.teamMembers.map((member) => (
                  <div
                    className="rounded-lg border border-slate-200 p-3"
                    key={member.id}
                  >
                    <strong className="text-sm text-slate-800">
                      {member.user?.name}
                    </strong>
                    <p className="mt-1 text-xs text-slate-500">
                      {member.teamRoleCode === "LEAD_AUDITOR"
                        ? "Team Leader"
                        : member.teamRoleLabel}{" "}
                      · {member.plannedPersonDays} person-days
                    </p>
                  </div>
                ))}
              </div>
            </section>

            {selected.conflicts.length > 0 && (
              <section className="rounded-xl border border-red-200 bg-red-50 p-4">
                <h3 className="flex items-center gap-2 text-sm font-bold text-red-800">
                  <AlertTriangle size={17} />
                  Current conflicts
                </h3>
                <ul className="mt-2 grid gap-1 text-xs text-red-700">
                  {selected.conflicts.map((conflict, index) => (
                    <li key={`${conflict.type}-${index}`}>
                      • {conflict.message}
                    </li>
                  ))}
                </ul>
              </section>
            )}

            <section>
              <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                <History size={17} />
                Schedule history
              </h3>
              <ol className="mt-3 grid gap-2">
                {selected.history.length ? (
                  selected.history
                    .slice()
                    .reverse()
                    .map((event) => (
                      <li
                        className="rounded-lg border border-slate-200 p-3"
                        key={event.id}
                      >
                        <div className="flex justify-between gap-3">
                          <strong className="text-xs text-slate-800">
                            {event.action}
                          </strong>
                          <span className="text-[11px] text-slate-500">
                            {formatDate(event.createdAt, true)}
                          </span>
                        </div>
                        <p className="mt-1 text-xs text-slate-600">
                          {formatDate(event.newStartDate ?? event.oldStartDate)}{" "}
                          – {formatDate(event.newEndDate ?? event.oldEndDate)}
                        </p>
                        {event.reason && (
                          <p className="mt-1 text-xs leading-5 text-slate-500">
                            {event.reason}
                          </p>
                        )}
                        <p className="mt-1 text-[11px] text-slate-400">
                          Recorded by {event.actor?.name}
                        </p>
                      </li>
                    ))
                ) : (
                  <li className="rounded-lg bg-slate-50 p-4 text-xs text-slate-500">
                    This engagement has not yet been formally scheduled.
                  </li>
                )}
              </ol>
            </section>
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => setCancelTarget(null)}
              type="button"
            >
              Keep schedule
            </button>
            <button
              className="min-h-10 rounded-lg bg-red-600 px-5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50"
              disabled={saving || cancelReason.trim().length < 10}
              onClick={cancelSchedule}
              type="button"
            >
              {saving ? "Cancelling..." : "Cancel schedule"}
            </button>
          </>
        }
        onClose={() => !saving && setCancelTarget(null)}
        open={Boolean(cancelTarget)}
        size="sm"
        title="Cancel audit schedule?"
      >
        <div>
          <p className="text-sm leading-6 text-slate-600">
            The schedule will remain recoverable and its dates, team, reason,
            and complete history will be retained.
          </p>
          <label className="mt-4 block">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">
              Cancellation reason <span className="text-red-500">*</span>
            </span>
            <textarea
              className="min-h-28 w-full rounded-lg border border-slate-300 p-3 text-sm outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100"
              onChange={(event) => setCancelReason(event.target.value)}
              placeholder="Explain why this audit is being cancelled..."
              value={cancelReason}
            />
            {errors.reason?.[0] && (
              <p className="mt-1.5 text-xs font-medium text-red-600">
                {errors.reason[0]}
              </p>
            )}
          </label>
        </div>
      </Modal>
    </main>
  );
}
