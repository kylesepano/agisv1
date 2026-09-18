import {
  AlertTriangle,
  Archive,
  Gauge,
  Pencil,
  Plus,
  RefreshCw,
  RotateCcw,
  Search,
  ShieldCheck,
  Sparkles,
  UsersRound,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useAuth } from "../../auth/auth-context";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { ApiError, resourceCapacityApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const emptyData = {
  fiscalYear: new Date().getFullYear(),
  years: [],
  auditors: [],
  engagements: [],
  specializations: [],
  unavailabilityTypes: [],
  proficiencyLevels: [],
  summary: {},
};

function formatDate(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

function inputClass() {
  return "min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
}

/**
 * Legacy compatibility screen. Current resource records are maintained in
 * ARMIS Planning; the canonical route redirects there from the application.
 */
export default function IapResourceCapacityPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [data, setData] = useState(emptyData);
  const [year, setYear] = useState("");
  const [tab, setTab] = useState("workload");
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [capacityTarget, setCapacityTarget] = useState(null);
  const [capacityDays, setCapacityDays] = useState("");
  const [availabilityTarget, setAvailabilityTarget] = useState(null);
  const [availabilityForm, setAvailabilityForm] = useState({});
  const [skillsTarget, setSkillsTarget] = useState(null);
  const [skillRows, setSkillRows] = useState([]);
  const [requirementTarget, setRequirementTarget] = useState(null);
  const [requirementRows, setRequirementRows] = useState([]);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const canManage = hasPermission(user, "iap.manage_universe");

  const load = useCallback(async (selectedYear = "") => {
    setLoading(true);
    setError("");
    try {
      const result = await resourceCapacityApi.show(selectedYear);
      setData(result);
      setYear(String(result.fiscalYear));
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to load resource capacity.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const filteredAuditors = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return data.auditors;
    return data.auditors.filter((auditor) =>
      [
        auditor.name,
        auditor.employeeId,
        auditor.position,
        ...auditor.skills.flatMap((skill) => [skill.code, skill.label]),
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(term),
      ),
    );
  }, [data.auditors, search]);

  const availabilityRows = useMemo(
    () =>
      filteredAuditors.flatMap((auditor) =>
        auditor.unavailability.map((period) => ({ ...period, auditor })),
      ),
    [filteredAuditors],
  );

  const filteredEngagements = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return data.engagements;
    return data.engagements.filter((engagement) =>
      [
        engagement.engagementCode,
        engagement.title,
        engagement.planCode,
        ...engagement.offices.flatMap((office) => [office.code, office.name]),
        ...engagement.requirements.flatMap((item) => [item.code, item.label]),
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(term),
      ),
    );
  }, [data.engagements, search]);

  async function run(action, successMessage) {
    setSaving(true);
    setErrors({});
    try {
      await action();
      toast.success(successMessage);
      setCapacityTarget(null);
      setAvailabilityTarget(null);
      setSkillsTarget(null);
      setRequirementTarget(null);
      setArchiveTarget(null);
      await load(year);
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(
        requestError instanceof Error
          ? requestError.message
          : "Unable to save resource data.",
      );
    } finally {
      setSaving(false);
    }
  }

  function openAvailability(auditor, period = null) {
    setErrors({});
    setAvailabilityTarget({ auditor, period });
    setAvailabilityForm({
      typeId: period?.typeId ?? "",
      title: period?.title ?? "",
      startDate: period?.startDate ?? "",
      endDate: period?.endDate ?? "",
      notes: period?.notes ?? "",
    });
  }

  function openSkills(auditor) {
    setSkillsTarget(auditor);
    setSkillRows(
      auditor.skills.map((skill) => ({
        specializationId: skill.specializationId,
        proficiencyLevel: skill.proficiencyLevel,
        notes: skill.notes ?? "",
      })),
    );
  }

  function openRequirements(engagement) {
    setRequirementTarget(engagement);
    setRequirementRows(
      engagement.requirements.map((item) => ({
        specializationId: item.specializationId,
        minimumAuditors: item.minimumAuditors,
        minimumProficiency: item.minimumProficiency,
        notes: item.notes ?? "",
      })),
    );
  }

  if (loading && data.auditors.length === 0) {
    return (
      <main className="grid min-h-[calc(100vh-7rem)] place-items-center p-5">
        <span className="flex items-center gap-3 text-sm font-semibold text-slate-500">
          <RefreshCw className="animate-spin" size={20} />
          Loading resource capacity...
        </span>
      </main>
    );
  }

  return (
    <main className="p-3 sm:p-5">
      <RegistryHeader
        description="Historical planning view. ARMIS is the sole operational source for capacity, availability, skills, and workload."
        icon={UsersRound}
        title="IAP Resource Capacity"
      />

      {error && (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      <section className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <SummaryCard
          icon={UsersRound}
          label="Available Auditors"
          tone="emerald"
          value={`${data.summary.availableAuditors ?? 0}/${data.summary.totalAuditors ?? 0}`}
        />
        <SummaryCard
          icon={Gauge}
          label="Available Person-days"
          tone="sky"
          value={data.summary.availablePersonDays ?? 0}
        />
        <SummaryCard
          icon={ShieldCheck}
          label="Required Person-days"
          tone="slate"
          value={data.summary.requiredPersonDays ?? 0}
        />
        <SummaryCard
          icon={Gauge}
          label="Allocated Person-days"
          tone="amber"
          value={data.summary.allocatedPersonDays ?? 0}
        />
        <SummaryCard
          icon={AlertTriangle}
          label="Overallocated"
          tone="red"
          value={data.summary.overallocatedAuditors ?? 0}
        />
        <SummaryCard
          icon={Sparkles}
          label="Skill Gaps"
          tone="red"
          value={data.summary.engagementsWithSkillGaps ?? 0}
        />
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="grid gap-3 border-b border-slate-200 p-4 lg:grid-cols-[minmax(18rem,1fr)_12rem_auto]">
          <label className="relative">
            <Search
              className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
              size={17}
            />
            <input
              className={`${inputClass()} pl-10`}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search auditor, skill, engagement, or office..."
              value={search}
            />
          </label>
          <SearchableSelect
            onChange={(value) => load(value)}
            options={data.years.map((item) => ({
              value: String(item),
              label: `Fiscal Year ${item}`,
            }))}
            placeholder="Fiscal year"
            value={year}
          />
          <button
            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50"
            onClick={() => load(year)}
            type="button"
          >
            <RefreshCw className={loading ? "animate-spin" : ""} size={16} />
            Refresh
          </button>
        </header>
        <nav className="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50 px-3 pt-2">
          {[
            ["workload", "Auditor Workload"],
            ["availability", "Availability"],
            ["skills", "Skills & Specializations"],
            ["requirements", "Engagement Requirements"],
          ].map(([value, label]) => (
            <button
              className={`whitespace-nowrap rounded-t-lg px-4 py-3 text-sm font-bold ${
                tab === value
                  ? "border border-b-white bg-white text-sky-700"
                  : "text-slate-500 hover:text-slate-800"
              }`}
              key={value}
              onClick={() => setTab(value)}
              type="button"
            >
              {label}
            </button>
          ))}
        </nav>

        {tab === "workload" && (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-5 py-3">Auditor</th>
                  <th className="px-5 py-3">Availability</th>
                  <th className="px-5 py-3">Annual Capacity</th>
                  <th className="px-5 py-3">Allocated</th>
                  <th className="px-5 py-3">Remaining</th>
                  <th className="px-5 py-3">Workload</th>
                  {canManage && (
                    <th className="px-5 py-3 text-right">Action</th>
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filteredAuditors.map((auditor) => (
                  <tr className="hover:bg-sky-50/50" key={auditor.id}>
                    <td className="px-5 py-4">
                      <strong className="block text-slate-800">
                        {auditor.name}
                      </strong>
                      <span className="text-xs text-slate-500">
                        {auditor.employeeId} · {auditor.position}
                      </span>
                    </td>
                    <td className="px-5 py-4">
                      <StatusBadge
                        tone={auditor.availableToday ? "success" : "danger"}
                      >
                        {auditor.availableToday
                          ? "Available today"
                          : "Unavailable"}
                      </StatusBadge>
                    </td>
                    <td className="px-5 py-4 font-bold">
                      {auditor.availablePersonDays}
                    </td>
                    <td className="px-5 py-4">{auditor.allocatedPersonDays}</td>
                    <td
                      className={`px-5 py-4 font-bold ${auditor.isOverallocated ? "text-red-600" : "text-emerald-700"}`}
                    >
                      {auditor.remainingPersonDays}
                    </td>
                    <td className="w-56 px-5 py-4">
                      <div className="mb-1 flex justify-between text-xs">
                        <span>{auditor.utilizationPercentage}% utilized</span>
                        {auditor.isOverallocated && (
                          <span className="font-bold text-red-600">
                            Overallocated
                          </span>
                        )}
                      </div>
                      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                          className={`h-full rounded-full ${auditor.isOverallocated ? "bg-red-500" : auditor.utilizationPercentage >= 80 ? "bg-amber-500" : "bg-emerald-500"}`}
                          style={{
                            width: `${Math.min(auditor.utilizationPercentage, 100)}%`,
                          }}
                        />
                      </div>
                    </td>
                    {canManage && (
                      <td className="px-5 py-4 text-right">
                        <button
                          className="inline-flex h-9 items-center gap-2 rounded-lg border border-sky-200 px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                          onClick={() => {
                            setCapacityTarget(auditor);
                            setCapacityDays(
                              String(auditor.availablePersonDays),
                            );
                          }}
                          type="button"
                        >
                          <Pencil size={14} /> Capacity
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {tab === "availability" && (
          <div>
            <div className="flex justify-end border-b p-3">
              {canManage && (
                <SearchableSelect
                  onChange={(value) => {
                    const auditor = data.auditors.find(
                      (item) => String(item.id) === String(value),
                    );
                    if (auditor) openAvailability(auditor);
                  }}
                  options={data.auditors.map((auditor) => ({
                    value: auditor.id,
                    label: auditor.name,
                    keywords: auditor.employeeId,
                  }))}
                  placeholder="+ Add unavailable period"
                  value=""
                />
              )}
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[850px] text-left text-sm">
                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th className="px-5 py-3">Auditor</th>
                    <th className="px-5 py-3">Type</th>
                    <th className="px-5 py-3">Unavailable Period</th>
                    <th className="px-5 py-3">Reason</th>
                    <th className="px-5 py-3">Status</th>
                    {canManage && (
                      <th className="px-5 py-3 text-right">Actions</th>
                    )}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {availabilityRows.map((row) => (
                    <tr
                      className={
                        row.isArchived
                          ? "bg-slate-50 opacity-70"
                          : "hover:bg-sky-50/50"
                      }
                      key={row.id}
                    >
                      <td className="px-5 py-4 font-bold text-slate-800">
                        {row.auditor.name}
                      </td>
                      <td className="px-5 py-4">{row.typeLabel}</td>
                      <td className="px-5 py-4">
                        {formatDate(row.startDate)} — {formatDate(row.endDate)}
                      </td>
                      <td className="px-5 py-4">
                        <strong className="block">{row.title}</strong>
                        {row.notes && (
                          <span className="text-xs text-slate-500">
                            {row.notes}
                          </span>
                        )}
                      </td>
                      <td className="px-5 py-4">
                        <StatusBadge
                          tone={row.isArchived ? "inactive" : "warning"}
                        >
                          {row.isArchived ? "Archived" : "Recorded"}
                        </StatusBadge>
                      </td>
                      {canManage && (
                        <td className="px-5 py-4">
                          <div className="flex justify-end gap-1">
                            {!row.isArchived ? (
                              <>
                                <button
                                  className="grid h-9 w-9 place-items-center rounded-lg border text-sky-700 hover:bg-sky-50"
                                  onClick={() =>
                                    openAvailability(row.auditor, row)
                                  }
                                  type="button"
                                >
                                  <Pencil size={15} />
                                </button>
                                <button
                                  className="grid h-9 w-9 place-items-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50"
                                  onClick={() => setArchiveTarget(row)}
                                  type="button"
                                >
                                  <Archive size={15} />
                                </button>
                              </>
                            ) : (
                              <button
                                className="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                                onClick={() =>
                                  run(
                                    () =>
                                      resourceCapacityApi.restoreUnavailability(
                                        row.id,
                                      ),
                                    "Unavailable period restored.",
                                  )
                                }
                                type="button"
                              >
                                <RotateCcw size={14} /> Restore
                              </button>
                            )}
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                  {availabilityRows.length === 0 && (
                    <tr>
                      <td
                        className="px-5 py-10 text-center text-slate-500"
                        colSpan={6}
                      >
                        No unavailable periods match the search.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {tab === "skills" && (
          <div className="grid gap-3 p-4 lg:grid-cols-2">
            {filteredAuditors.map((auditor) => (
              <article
                className="rounded-xl border border-slate-200 p-4"
                key={auditor.id}
              >
                <header className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="font-bold text-slate-800">{auditor.name}</h3>
                    <p className="text-xs text-slate-500">
                      {auditor.employeeId} · {auditor.position}
                    </p>
                  </div>
                  {canManage && (
                    <button
                      className="grid h-9 w-9 place-items-center rounded-lg border text-sky-700 hover:bg-sky-50"
                      onClick={() => openSkills(auditor)}
                      type="button"
                    >
                      <Pencil size={15} />
                    </button>
                  )}
                </header>
                <div className="mt-4 flex flex-wrap gap-2">
                  {auditor.skills.map((skill) => (
                    <span
                      className="rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-800"
                      key={skill.id}
                    >
                      {skill.label} · {skill.proficiencyLevel}
                    </span>
                  ))}
                  {auditor.skills.length === 0 && (
                    <span className="text-sm text-slate-400">
                      No verified specializations.
                    </span>
                  )}
                </div>
              </article>
            ))}
          </div>
        )}

        {tab === "requirements" && (
          <div className="grid gap-3 p-4">
            {filteredEngagements.map((engagement) => (
              <article
                className={`rounded-xl border p-4 ${engagement.skillGaps.length ? "border-amber-300 bg-amber-50/40" : "border-slate-200"}`}
                key={engagement.id}
              >
                <header className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="font-bold text-slate-800">
                        {engagement.engagementCode} — {engagement.title}
                      </h3>
                      {engagement.skillGaps.length ? (
                        <StatusBadge tone="warning">
                          {engagement.skillGaps.length} skill gap(s)
                        </StatusBadge>
                      ) : (
                        <StatusBadge tone="success">
                          Requirements met
                        </StatusBadge>
                      )}
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                      {engagement.planCode} ·{" "}
                      {engagement.offices
                        .map((office) => office.code)
                        .join(", ") || "No office"}{" "}
                      · {engagement.assignedPersonDays}/
                      {engagement.requiredPersonDays} person-days assigned
                    </p>
                  </div>
                  {canManage &&
                    ["DRAFT", "RETURNED_FOR_REVISION"].includes(
                      engagement.planStatus,
                    ) && (
                      <button
                        className="inline-flex h-9 items-center gap-2 rounded-lg border border-sky-200 px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                        onClick={() => openRequirements(engagement)}
                        type="button"
                      >
                        <Pencil size={14} /> Requirements
                      </button>
                    )}
                </header>
                <div className="mt-3 flex flex-wrap gap-2">
                  {engagement.requirements.map((item) => (
                    <span
                      className={`rounded-lg border px-3 py-2 text-xs ${item.hasGap ? "border-red-200 bg-red-50 text-red-700" : "border-emerald-200 bg-emerald-50 text-emerald-700"}`}
                      key={item.id}
                    >
                      <strong>{item.label}</strong> · {item.qualifiedAuditors}/
                      {item.minimumAuditors} at {item.minimumProficiency}
                    </span>
                  ))}
                  {engagement.requirements.length === 0 && (
                    <span className="text-sm text-slate-400">
                      No specialization requirements defined.
                    </span>
                  )}
                </div>
              </article>
            ))}
          </div>
        )}
      </section>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              disabled={saving}
              onClick={() => setCapacityTarget(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={() =>
                run(
                  () =>
                    resourceCapacityApi.updateCapacity(capacityTarget.id, {
                      fiscalYear: Number(year),
                      availablePersonDays: Number(capacityDays),
                    }),
                  "Annual capacity updated.",
                )
              }
              type="button"
            >
              Save capacity
            </button>
          </>
        }
        onClose={() => setCapacityTarget(null)}
        open={Boolean(capacityTarget)}
        title={`Annual capacity · ${capacityTarget?.name ?? ""}`}
      >
        <label className="grid gap-1.5 text-sm font-semibold text-slate-700">
          Available person-days for FY {year}
          <input
            className={inputClass()}
            min="0"
            onChange={(event) => setCapacityDays(event.target.value)}
            step="0.5"
            type="number"
            value={capacityDays}
          />
        </label>
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              disabled={saving}
              onClick={() => setAvailabilityTarget(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={() =>
                run(
                  () =>
                    availabilityTarget.period
                      ? resourceCapacityApi.updateUnavailability(
                          availabilityTarget.period.id,
                          availabilityForm,
                        )
                      : resourceCapacityApi.createUnavailability(
                          availabilityTarget.auditor.id,
                          availabilityForm,
                        ),
                  availabilityTarget?.period
                    ? "Unavailable period updated."
                    : "Unavailable period added.",
                )
              }
              type="button"
            >
              Save period
            </button>
          </>
        }
        onClose={() => setAvailabilityTarget(null)}
        open={Boolean(availabilityTarget)}
        title={`${availabilityTarget?.period ? "Edit" : "Add"} unavailable period`}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="grid gap-1.5 text-sm font-semibold text-slate-700 sm:col-span-2">
            Auditor
            <input
              className={`${inputClass()} bg-slate-50`}
              disabled
              value={availabilityTarget?.auditor?.name ?? ""}
            />
          </label>
          <label className="grid gap-1.5 text-sm font-semibold text-slate-700 sm:col-span-2">
            Type
            <SearchableSelect
              onChange={(value) =>
                setAvailabilityForm((form) => ({ ...form, typeId: value }))
              }
              options={data.unavailabilityTypes.map((item) => ({
                value: item.id,
                label: item.label,
              }))}
              value={availabilityForm.typeId}
            />
          </label>
          <label className="grid gap-1.5 text-sm font-semibold text-slate-700 sm:col-span-2">
            Title
            <input
              className={inputClass()}
              onChange={(event) =>
                setAvailabilityForm((form) => ({
                  ...form,
                  title: event.target.value,
                }))
              }
              value={availabilityForm.title ?? ""}
            />
          </label>
          <label className="grid gap-1.5 text-sm font-semibold text-slate-700">
            Start date
            <input
              className={inputClass()}
              onChange={(event) =>
                setAvailabilityForm((form) => ({
                  ...form,
                  startDate: event.target.value,
                }))
              }
              type="date"
              value={availabilityForm.startDate ?? ""}
            />
          </label>
          <label className="grid gap-1.5 text-sm font-semibold text-slate-700">
            End date
            <input
              className={inputClass()}
              onChange={(event) =>
                setAvailabilityForm((form) => ({
                  ...form,
                  endDate: event.target.value,
                }))
              }
              type="date"
              value={availabilityForm.endDate ?? ""}
            />
          </label>
          <label className="grid gap-1.5 text-sm font-semibold text-slate-700 sm:col-span-2">
            Notes
            <textarea
              className={`${inputClass()} min-h-24 py-3`}
              onChange={(event) =>
                setAvailabilityForm((form) => ({
                  ...form,
                  notes: event.target.value,
                }))
              }
              value={availabilityForm.notes ?? ""}
            />
          </label>
          {Object.values(errors)
            .flat()
            .map((message) => (
              <p
                className="text-xs font-semibold text-red-600 sm:col-span-2"
                key={message}
              >
                {message}
              </p>
            ))}
        </div>
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              disabled={saving}
              onClick={() => setSkillsTarget(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={() =>
                run(
                  () =>
                    resourceCapacityApi.syncSkills(skillsTarget.id, skillRows),
                  "Auditor specializations updated.",
                )
              }
              type="button"
            >
              Save skills
            </button>
          </>
        }
        onClose={() => setSkillsTarget(null)}
        open={Boolean(skillsTarget)}
        size="lg"
        title={`Skills & specializations · ${skillsTarget?.name ?? ""}`}
      >
        <EditableRows
          data={data}
          mode="skills"
          rows={skillRows}
          setRows={setSkillRows}
        />
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              disabled={saving}
              onClick={() => setRequirementTarget(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={() =>
                run(
                  () =>
                    resourceCapacityApi.syncRequirements(
                      requirementTarget.id,
                      requirementRows,
                    ),
                  "Engagement requirements updated.",
                )
              }
              type="button"
            >
              Save requirements
            </button>
          </>
        }
        onClose={() => setRequirementTarget(null)}
        open={Boolean(requirementTarget)}
        size="lg"
        title={`Skill requirements · ${requirementTarget?.engagementCode ?? ""}`}
      >
        <EditableRows
          data={data}
          mode="requirements"
          rows={requirementRows}
          setRows={setRequirementRows}
        />
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive period"
        description="This period will be soft-archived and can be restored later. It will no longer trigger scheduling conflicts."
        onCancel={() => setArchiveTarget(null)}
        onConfirm={() =>
          run(
            () => resourceCapacityApi.archiveUnavailability(archiveTarget.id),
            "Unavailable period archived.",
          )
        }
        open={Boolean(archiveTarget)}
        title="Archive unavailable period?"
        tone="danger"
      />
    </main>
  );
}

function EditableRows({ data, mode, rows, setRows }) {
  const requirementMode = mode === "requirements";
  function add() {
    setRows((current) => [
      ...current,
      requirementMode
        ? {
            specializationId: "",
            minimumAuditors: 1,
            minimumProficiency: "INTERMEDIATE",
            notes: "",
          }
        : { specializationId: "", proficiencyLevel: "INTERMEDIATE", notes: "" },
    ]);
  }
  function update(index, values) {
    setRows((current) =>
      current.map((row, rowIndex) =>
        rowIndex === index ? { ...row, ...values } : row,
      ),
    );
  }
  return (
    <div className="grid gap-3">
      {rows.map((row, index) => (
        <div
          className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[minmax(14rem,1fr)_13rem_auto]"
          key={`${row.specializationId}-${index}`}
        >
          <SearchableSelect
            onChange={(value) => update(index, { specializationId: value })}
            options={data.specializations.map((item) => ({
              value: item.id,
              label: item.label,
              keywords: item.code,
            }))}
            placeholder="Select specialization"
            value={row.specializationId}
          />
          {requirementMode ? (
            <div className="grid grid-cols-2 gap-2">
              <input
                className={inputClass()}
                min="1"
                onChange={(event) =>
                  update(index, { minimumAuditors: Number(event.target.value) })
                }
                title="Minimum auditors"
                type="number"
                value={row.minimumAuditors}
              />
              <SearchableSelect
                onChange={(value) =>
                  update(index, { minimumProficiency: value })
                }
                options={data.proficiencyLevels.map((item) => ({
                  value: item.code,
                  label: item.label,
                }))}
                value={row.minimumProficiency}
              />
            </div>
          ) : (
            <SearchableSelect
              onChange={(value) => update(index, { proficiencyLevel: value })}
              options={data.proficiencyLevels.map((item) => ({
                value: item.code,
                label: item.label,
              }))}
              value={row.proficiencyLevel}
            />
          )}
          <button
            className="grid h-11 w-11 place-items-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50"
            onClick={() =>
              setRows((current) =>
                current.filter((_, rowIndex) => rowIndex !== index),
              )
            }
            type="button"
          >
            <Archive size={15} />
          </button>
        </div>
      ))}
      {rows.length === 0 && (
        <div className="rounded-xl border border-dashed p-8 text-center text-sm text-slate-500">
          No {requirementMode ? "engagement requirements" : "auditor skills"}{" "}
          have been added.
        </div>
      )}
      <button
        className="inline-flex min-h-10 w-fit items-center gap-2 rounded-lg border border-sky-200 px-4 text-sm font-bold text-sky-700 hover:bg-sky-50"
        onClick={add}
        type="button"
      >
        <Plus size={16} /> Add {requirementMode ? "requirement" : "skill"}
      </button>
    </div>
  );
}
