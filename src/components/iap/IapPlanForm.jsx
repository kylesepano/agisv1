import { useMemo, useState } from "react";
import FormField from "../ui/FormField";
import SearchableSelect from "../ui/SearchableSelect";
import { hasPermission } from "../../config/navigation";

const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

function firstError(errors, key) {
  return Array.isArray(errors?.[key]) ? errors[key][0] : "";
}

function emptyForm(currentUserId) {
  const year = new Date().getFullYear() + 1;
  return {
    planCode: "",
    fiscalYear: year,
    planningPeriodTypeId: "",
    planningPeriodStart: `${year}-01-01`,
    planningPeriodEnd: `${year}-12-31`,
    title: `${year} Annual Internal Audit Plan`,
    executiveSummary: "",
    planningMethodology: "",
    overallObjective: "",
    overallScope: "",
    limitations: "",
    preparedBy: currentUserId ?? "",
    coordinatorId: "",
  };
}

function fromPlan(plan, currentUserId) {
  if (!plan) return emptyForm(currentUserId);

  return {
    planCode: plan.planCode ?? "",
    fiscalYear: plan.fiscalYear,
    planningPeriodTypeId: plan.planningPeriodTypeId ?? "",
    planningPeriodStart: plan.planningPeriodStart ?? "",
    planningPeriodEnd: plan.planningPeriodEnd ?? "",
    title: plan.title ?? "",
    executiveSummary: plan.executiveSummary ?? "",
    planningMethodology: plan.planningMethodology ?? "",
    overallObjective: plan.overallObjective ?? "",
    overallScope: plan.overallScope ?? "",
    limitations: plan.limitations ?? "",
    preparedBy: plan.preparedBy ?? currentUserId ?? "",
    coordinatorId: plan.coordinatorId ?? "",
    lockVersion: plan.lockVersion,
  };
}

export default function IapPlanForm({
  plan,
  currentUserId,
  masterLists,
  users,
  errors = {},
  formId = "iap-plan-form",
  onSubmit,
}) {
  const [form, setForm] = useState(() => fromPlan(plan, currentUserId));

  const periodOptions = useMemo(() => {
    const list = masterLists.find(
      (candidate) => candidate.code === "IAP_PLANNING_PERIOD_TYPE",
    );
    return (list?.items ?? [])
      .filter((item) => item.isActive && !item.isArchived)
      .map((item) => ({
        value: item.id,
        label: item.label,
        description: item.description,
      }));
  }, [masterLists]);

  const userOptions = useMemo(
    () =>
      users
        .filter(
          (user) =>
            user.isActive &&
            !user.isArchived &&
            hasPermission(user, "iap.manage_engagements"),
        )
        .map((user) => ({
          value: user.id,
          label: `${user.name} · ${user.employeeId}`,
          description: `${user.role}${user.office ? ` · ${user.office}` : ""}`,
          keywords: `${user.name} ${user.employeeId} ${user.role}`,
        })),
    [users],
  );

  function update(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function updateYear(value) {
    const year = Number(value);
    setForm((current) => ({
      ...current,
      fiscalYear: value,
      planningPeriodStart:
        String(current.planningPeriodStart).slice(0, 4) ===
        String(current.fiscalYear)
          ? `${year}-01-01`
          : current.planningPeriodStart,
      planningPeriodEnd:
        String(current.planningPeriodEnd).slice(0, 4) ===
        String(current.fiscalYear)
          ? `${year}-12-31`
          : current.planningPeriodEnd,
      title:
        current.title === `${current.fiscalYear} Annual Internal Audit Plan`
          ? `${year} Annual Internal Audit Plan`
          : current.title,
    }));
  }

  return (
    <form
      className="grid gap-5"
      id={formId}
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          ...form,
          fiscalYear: Number(form.fiscalYear),
          planningPeriodTypeId: Number(form.planningPeriodTypeId),
          preparedBy: Number(form.preparedBy),
          coordinatorId: form.coordinatorId ? Number(form.coordinatorId) : null,
          planCode: form.planCode.trim() || null,
        });
      }}
    >
      <section className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <h3 className="text-sm font-bold text-slate-800">
            Plan identity and period
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            Define the fiscal period and the CIAS personnel accountable for
            preparing the plan.
          </p>
        </div>
        <FormField
          error={firstError(errors, "fiscalYear")}
          htmlFor="iap-fiscal-year"
          label="Fiscal year"
          required
        >
          <input
            className={inputClass}
            id="iap-fiscal-year"
            min="2000"
            onChange={(event) => updateYear(event.target.value)}
            type="number"
            value={form.fiscalYear}
          />
        </FormField>
        <FormField
          error={firstError(errors, "planCode")}
          htmlFor="iap-plan-code"
          label="Plan code"
          hint="Leave blank to generate the standard IAP code."
        >
          <input
            className={inputClass}
            id="iap-plan-code"
            onChange={(event) => update("planCode", event.target.value)}
            placeholder={`IAP-${form.fiscalYear}`}
            value={form.planCode}
          />
        </FormField>
        <FormField
          error={firstError(errors, "planningPeriodTypeId")}
          label="Planning period type"
          required
        >
          <SearchableSelect
            onChange={(value) => update("planningPeriodTypeId", value)}
            options={periodOptions}
            placeholder="Select a period type"
            searchPlaceholder="Search period types..."
            value={form.planningPeriodTypeId}
          />
        </FormField>
        <FormField
          error={firstError(errors, "title")}
          htmlFor="iap-plan-title"
          label="Plan title"
          required
        >
          <input
            className={inputClass}
            id="iap-plan-title"
            onChange={(event) => update("title", event.target.value)}
            value={form.title}
          />
        </FormField>
        <FormField
          error={firstError(errors, "planningPeriodStart")}
          htmlFor="iap-period-start"
          label="Period start"
          required
        >
          <input
            className={inputClass}
            id="iap-period-start"
            onChange={(event) =>
              update("planningPeriodStart", event.target.value)
            }
            type="date"
            value={form.planningPeriodStart}
          />
        </FormField>
        <FormField
          error={firstError(errors, "planningPeriodEnd")}
          htmlFor="iap-period-end"
          label="Period end"
          required
        >
          <input
            className={inputClass}
            id="iap-period-end"
            onChange={(event) =>
              update("planningPeriodEnd", event.target.value)
            }
            type="date"
            value={form.planningPeriodEnd}
          />
        </FormField>
        <FormField
          error={firstError(errors, "preparedBy")}
          label="Prepared by"
          required
        >
          <SearchableSelect
            onChange={(value) => update("preparedBy", value)}
            options={userOptions}
            placeholder="Select the plan preparer"
            searchPlaceholder="Search CIAS personnel..."
            value={form.preparedBy}
          />
        </FormField>
        <FormField
          error={firstError(errors, "coordinatorId")}
          label="Plan coordinator"
        >
          <SearchableSelect
            onChange={(value) => update("coordinatorId", value)}
            options={userOptions}
            placeholder="Select a coordinator"
            searchPlaceholder="Search CIAS personnel..."
            value={form.coordinatorId}
          />
        </FormField>
      </section>

      <section className="grid gap-4">
        <h3 className="text-sm font-bold text-slate-800">Planning rationale</h3>
        <FormField
          error={firstError(errors, "executiveSummary")}
          htmlFor="iap-executive-summary"
          label="Executive summary"
        >
          <textarea
            className={`${inputClass} min-h-24 py-3`}
            id="iap-executive-summary"
            onChange={(event) => update("executiveSummary", event.target.value)}
            placeholder="Summarize the priorities and basis of the annual plan."
            value={form.executiveSummary}
          />
        </FormField>
        <FormField
          error={firstError(errors, "planningMethodology")}
          htmlFor="iap-methodology"
          label="Planning methodology"
        >
          <textarea
            className={`${inputClass} min-h-24 py-3`}
            id="iap-methodology"
            onChange={(event) =>
              update("planningMethodology", event.target.value)
            }
            placeholder="Explain the risk-assessment, consultation, and resource-allocation methods."
            value={form.planningMethodology}
          />
        </FormField>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            error={firstError(errors, "overallObjective")}
            htmlFor="iap-objective"
            label="Overall objective"
            required
          >
            <textarea
              className={`${inputClass} min-h-32 py-3`}
              id="iap-objective"
              onChange={(event) =>
                update("overallObjective", event.target.value)
              }
              placeholder="State what the annual plan intends to accomplish."
              value={form.overallObjective}
            />
          </FormField>
          <FormField
            error={firstError(errors, "overallScope")}
            htmlFor="iap-scope"
            label="Overall scope"
            required
          >
            <textarea
              className={`${inputClass} min-h-32 py-3`}
              id="iap-scope"
              onChange={(event) => update("overallScope", event.target.value)}
              placeholder="Describe the offices, programs, systems, and periods covered."
              value={form.overallScope}
            />
          </FormField>
        </div>
        <FormField
          error={firstError(errors, "limitations")}
          htmlFor="iap-limitations"
          label="Known limitations"
        >
          <textarea
            className={`${inputClass} min-h-24 py-3`}
            id="iap-limitations"
            onChange={(event) => update("limitations", event.target.value)}
            placeholder="Record known resource, timing, information, or coverage limitations."
            value={form.limitations}
          />
        </FormField>
      </section>
    </form>
  );
}
