import { AlertTriangle, Plus, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";
import FormField from "../ui/FormField";
import SearchableSelect from "../ui/SearchableSelect";

const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

function firstError(errors, key) {
  return Array.isArray(errors?.[key]) ? errors[key][0] : "";
}

export default function IapScheduleForm({
  schedule,
  auditors,
  teamRoles,
  capacities,
  errors = {},
  formId = "iap-schedule-form",
  onCheck,
  onSubmit,
}) {
  const roleByCode = useMemo(
    () => Object.fromEntries(teamRoles.map((role) => [role.code, role])),
    [teamRoles],
  );
  const initialMembers =
    schedule.teamMembers.length > 0
      ? schedule.teamMembers.map((member) => ({
          userId: member.userId,
          teamRoleId: member.teamRoleId,
          plannedPersonDays: member.plannedPersonDays,
          notes: member.notes ?? "",
        }))
      : [
          {
            userId: "",
            teamRoleId: roleByCode.LEAD_AUDITOR?.id ?? "",
            plannedPersonDays: "",
            notes: "",
          },
          {
            userId: "",
            teamRoleId: roleByCode.REVIEWER?.id ?? "",
            plannedPersonDays: "",
            notes: "",
          },
        ];
  const [form, setForm] = useState({
    plannedStartDate: schedule.plannedStartDate ?? "",
    plannedEndDate: schedule.plannedEndDate ?? "",
    expectedReportDate: schedule.expectedReportDate ?? "",
    members: initialMembers,
    reason: "",
    acknowledgeConflicts: false,
  });
  const [conflicts, setConflicts] = useState(schedule.conflicts ?? []);
  const [checking, setChecking] = useState(false);
  const fiscalYear = schedule.plan.fiscalYear;
  const capacityByUser = useMemo(
    () =>
      Object.fromEntries(
        capacities
          .filter((entry) => Number(entry.fiscalYear) === Number(fiscalYear))
          .map((entry) => [String(entry.userId), entry]),
      ),
    [capacities, fiscalYear],
  );

  function update(key, value) {
    setForm((current) => ({
      ...current,
      [key]: value,
      acknowledgeConflicts: key === "acknowledgeConflicts" ? value : false,
    }));
    if (key !== "acknowledgeConflicts") setConflicts([]);
  }

  function updateMember(index, key, value) {
    setForm((current) => ({
      ...current,
      acknowledgeConflicts: false,
      members: current.members.map((member, memberIndex) =>
        memberIndex === index ? { ...member, [key]: value } : member,
      ),
    }));
    setConflicts([]);
  }

  function payload() {
    return {
      ...form,
      members: form.members.map((member) => ({
        ...member,
        plannedPersonDays: Number(member.plannedPersonDays),
      })),
      lockVersion: schedule.plan.lockVersion,
    };
  }

  async function check() {
    setChecking(true);
    try {
      setConflicts(await onCheck(payload()));
    } finally {
      setChecking(false);
    }
  }

  const assignedTotal = form.members.reduce(
    (total, member) => total + Number(member.plannedPersonDays || 0),
    0,
  );
  const requiresReason = ["SCHEDULED", "CANCELLED"].includes(
    schedule.scheduleStatus,
  );

  return (
    <form
      className="grid gap-5"
      id={formId}
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit(payload());
      }}
    >
      <section className="rounded-xl border border-sky-100 bg-sky-50 p-4">
        <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
          {schedule.plan.planCode} · {schedule.engagementCode}
        </p>
        <h3 className="mt-1 text-base font-bold text-slate-900">
          {schedule.title}
        </h3>
        <p className="mt-1 text-sm text-slate-600">
          {schedule.offices.map((office) => office.code).join(", ")} ·{" "}
          {schedule.auditAreas.map((area) => area.name).join(", ")}
        </p>
      </section>

      <div className="grid gap-4 sm:grid-cols-3">
        <FormField
          error={firstError(errors, "plannedStartDate")}
          htmlFor="schedule-start"
          label="Planned start"
          required
        >
          <input
            className={inputClass}
            id="schedule-start"
            onChange={(event) => update("plannedStartDate", event.target.value)}
            type="date"
            value={form.plannedStartDate}
          />
        </FormField>
        <FormField
          error={firstError(errors, "plannedEndDate")}
          htmlFor="schedule-end"
          label="Planned end"
          required
        >
          <input
            className={inputClass}
            id="schedule-end"
            onChange={(event) => update("plannedEndDate", event.target.value)}
            type="date"
            value={form.plannedEndDate}
          />
        </FormField>
        <FormField
          error={firstError(errors, "expectedReportDate")}
          htmlFor="schedule-report"
          label="Expected report"
          required
        >
          <input
            className={inputClass}
            id="schedule-report"
            onChange={(event) =>
              update("expectedReportDate", event.target.value)
            }
            type="date"
            value={form.expectedReportDate}
          />
        </FormField>
      </div>

      <section>
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h3 className="text-sm font-bold text-slate-800">
              Proposed audit team
            </h3>
            <p className="mt-1 text-xs text-slate-500">
              Assign exactly one Team Leader and at least one Reviewer.
            </p>
          </div>
          <div
            className={`rounded-lg px-3 py-2 text-xs font-bold ${
              Math.abs(assignedTotal - schedule.estimatedPersonDays) < 0.01
                ? "bg-emerald-50 text-emerald-700"
                : "bg-amber-50 text-amber-800"
            }`}
          >
            {assignedTotal.toFixed(2)} /{" "}
            {schedule.estimatedPersonDays.toFixed(2)} person-days
          </div>
        </div>
        {firstError(errors, "members") && (
          <p className="mt-2 text-xs font-medium text-red-600">
            {firstError(errors, "members")}
          </p>
        )}
        <div className="mt-3 grid gap-3">
          {form.members.map((member, index) => {
            const selectedElsewhere = form.members
              .filter((_, memberIndex) => memberIndex !== index)
              .map((entry) => String(entry.userId));
            const capacity = capacityByUser[String(member.userId)];
            return (
              <div
                className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_9rem_2.5rem]"
                key={`${index}-${member.teamRoleId}`}
              >
                <div>
                  <label className="mb-1 block text-xs font-bold text-slate-600">
                    Auditor
                  </label>
                  <SearchableSelect
                    onChange={(value) => updateMember(index, "userId", value)}
                    options={auditors
                      .filter(
                        (auditor) =>
                          !selectedElsewhere.includes(String(auditor.id)),
                      )
                      .map((auditor) => ({
                        value: auditor.id,
                        label: `${auditor.name} (${auditor.employeeId})`,
                        keywords: `${auditor.roleCode} ${auditor.initials}`,
                      }))}
                    placeholder="Search CIAS auditor..."
                    value={member.userId}
                  />
                  {capacity && (
                    <p
                      className={`mt-1 text-[11px] ${
                        capacity.remainingPersonDays < 0
                          ? "text-red-600"
                          : "text-slate-500"
                      }`}
                    >
                      {capacity.allocatedPersonDays} allocated ·{" "}
                      {capacity.remainingPersonDays} remaining of{" "}
                      {capacity.availablePersonDays}
                    </p>
                  )}
                </div>
                <div>
                  <label className="mb-1 block text-xs font-bold text-slate-600">
                    Team role
                  </label>
                  <SearchableSelect
                    onChange={(value) =>
                      updateMember(index, "teamRoleId", value)
                    }
                    options={teamRoles.map((role) => ({
                      value: role.id,
                      label:
                        role.code === "LEAD_AUDITOR"
                          ? "Team Leader"
                          : role.label,
                      keywords: role.code,
                    }))}
                    value={member.teamRoleId}
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-bold text-slate-600">
                    Person-days
                  </label>
                  <input
                    className={inputClass}
                    min="0.5"
                    onChange={(event) =>
                      updateMember(
                        index,
                        "plannedPersonDays",
                        event.target.value,
                      )
                    }
                    step="0.5"
                    type="number"
                    value={member.plannedPersonDays}
                  />
                </div>
                <button
                  aria-label="Remove team member"
                  className="mt-5 grid h-10 w-10 place-items-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-30"
                  disabled={form.members.length <= 2}
                  onClick={() => {
                    update(
                      "members",
                      form.members.filter(
                        (_, memberIndex) => memberIndex !== index,
                      ),
                    );
                  }}
                  type="button"
                >
                  <Trash2 size={16} />
                </button>
              </div>
            );
          })}
        </div>
        <button
          className="mt-3 inline-flex min-h-10 items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 text-xs font-bold text-sky-700 transition hover:bg-sky-100"
          onClick={() =>
            update("members", [
              ...form.members,
              {
                userId: "",
                teamRoleId: roleByCode.TEAM_MEMBER?.id ?? "",
                plannedPersonDays: "",
                notes: "",
              },
            ])
          }
          type="button"
        >
          <Plus size={15} />
          Add team member
        </button>
      </section>

      {requiresReason && (
        <FormField
          error={firstError(errors, "reason")}
          htmlFor="schedule-reason"
          label={
            schedule.scheduleStatus === "CANCELLED"
              ? "Reinstatement and rescheduling reason"
              : "Rescheduling reason"
          }
          required
        >
          <textarea
            className={`${inputClass} min-h-24 py-2.5`}
            id="schedule-reason"
            onChange={(event) => update("reason", event.target.value)}
            placeholder="Explain why the approved schedule or team is changing..."
            value={form.reason}
          />
        </FormField>
      )}

      <section className="rounded-xl border border-slate-200 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="text-sm font-bold text-slate-800">Conflict check</h3>
            <p className="mt-1 text-xs text-slate-500">
              Checks auditor dates, office audit overlaps, and annual person-day
              capacity.
            </p>
          </div>
          <button
            className="min-h-10 rounded-lg border border-sky-200 px-4 text-xs font-bold text-sky-700 hover:bg-sky-50 disabled:opacity-50"
            disabled={checking}
            onClick={check}
            type="button"
          >
            {checking ? "Checking..." : "Check conflicts"}
          </button>
        </div>
        {conflicts.length === 0 ? (
          <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-xs font-semibold text-emerald-700">
            No current conflicts detected, or run the check after changing the
            schedule.
          </p>
        ) : (
          <div className="mt-3 grid gap-2">
            {conflicts.map((conflict, index) => (
              <div
                className={`flex gap-2 rounded-lg p-3 text-xs ${
                  conflict.severity === "danger"
                    ? "bg-red-50 text-red-800"
                    : "bg-amber-50 text-amber-800"
                }`}
                key={`${conflict.type}-${conflict.engagementId ?? conflict.userId}-${index}`}
              >
                <AlertTriangle className="shrink-0" size={15} />
                {conflict.message}
              </div>
            ))}
            <label className="mt-1 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
              <input
                checked={form.acknowledgeConflicts}
                className="mt-0.5"
                onChange={(event) =>
                  update("acknowledgeConflicts", event.target.checked)
                }
                type="checkbox"
              />
              I reviewed these conflicts and authorize saving the schedule with
              the recorded warnings.
            </label>
          </div>
        )}
        {errors.conflicts?.map((message) => (
          <p className="mt-2 text-xs font-medium text-red-600" key={message}>
            {message}
          </p>
        ))}
      </section>
    </form>
  );
}
