import { Save, X } from "lucide-react";
import FormField from "../ui/FormField";
import CmsActionPlanMilestoneEditor from "./CmsActionPlanMilestoneEditor";

const inputClass =
  "mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const textareaClass =
  "mt-1 min-h-28 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";

function firstActionPlanError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

export default function CmsActionPlanForm({
  form,
  setForm,
  errors,
  ownerOffice,
  userOptions,
  effectiveTargetDate,
  busy,
  isCreate,
  onSave,
  onCancel,
}) {
  const summaryErrors = Object.entries(errors ?? {})
    .filter(([key]) => key !== "lockVersion")
    .flatMap(([key, values]) =>
      (Array.isArray(values) ? values : [values]).map((message) => ({
        key,
        message,
      })),
    );

  function set(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  return (
    <form
      className="grid gap-5"
      onSubmit={(event) => {
        event.preventDefault();
        onSave();
      }}
    >
      {summaryErrors.length > 0 && (
        <section
          aria-labelledby="action-plan-validation-summary"
          className="rounded-xl border border-red-200 bg-red-50 p-4"
          role="alert"
        >
          <h3
            className="text-sm font-bold text-red-800"
            id="action-plan-validation-summary"
          >
            Review the following Action Plan fields
          </h3>
          <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
            {summaryErrors.slice(0, 12).map((error, index) => (
              <li key={`${error.key}-${index}`}>{error.message}</li>
            ))}
          </ul>
        </section>
      )}

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div>
          <h3 className="text-base font-bold text-slate-800">
            Management commitment
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            Draft content remains editable until submission. Submission creates
            an immutable controlled version.
          </p>
        </div>

        <div className="mt-5 grid gap-4 md:grid-cols-2">
          <div className="md:col-span-2">
            <FormField
              error={firstActionPlanError(errors, "planSummary")}
              htmlFor="action-plan-summary"
              label="Plan summary"
              hint="Required before submission."
            >
              <textarea
                className={textareaClass}
                id="action-plan-summary"
                maxLength={10000}
                onChange={(event) => set("planSummary", event.target.value)}
                value={form.planSummary}
              />
            </FormField>
          </div>
          <div className="md:col-span-2">
            <FormField
              error={firstActionPlanError(errors, "implementationStrategy")}
              htmlFor="action-plan-strategy"
              label="Implementation strategy"
              hint="Required before submission."
            >
              <textarea
                className={textareaClass}
                id="action-plan-strategy"
                maxLength={10000}
                onChange={(event) =>
                  set("implementationStrategy", event.target.value)
                }
                value={form.implementationStrategy}
              />
            </FormField>
          </div>
          <div className="md:col-span-2">
            <FormField
              error={firstActionPlanError(errors, "expectedOutcome")}
              htmlFor="action-plan-outcome"
              label="Expected outcome"
              hint="Required before submission."
            >
              <textarea
                className={textareaClass}
                id="action-plan-outcome"
                maxLength={10000}
                onChange={(event) => set("expectedOutcome", event.target.value)}
                value={form.expectedOutcome}
              />
            </FormField>
          </div>
          <FormField
            error={firstActionPlanError(errors, "rootCauseResponse")}
            htmlFor="action-plan-root-cause"
            label="Root-cause response"
          >
            <textarea
              className={textareaClass}
              id="action-plan-root-cause"
              maxLength={10000}
              onChange={(event) => set("rootCauseResponse", event.target.value)}
              value={form.rootCauseResponse}
            />
          </FormField>
          <FormField
            error={firstActionPlanError(errors, "resourcesRequired")}
            htmlFor="action-plan-resources"
            label="Resources required"
          >
            <textarea
              className={textareaClass}
              id="action-plan-resources"
              maxLength={10000}
              onChange={(event) => set("resourcesRequired", event.target.value)}
              value={form.resourcesRequired}
            />
          </FormField>
          <FormField
            error={firstActionPlanError(errors, "dependencies")}
            htmlFor="action-plan-dependencies"
            label="Dependencies"
          >
            <textarea
              className={textareaClass}
              id="action-plan-dependencies"
              maxLength={10000}
              onChange={(event) => set("dependencies", event.target.value)}
              value={form.dependencies}
            />
          </FormField>
          <FormField
            error={firstActionPlanError(errors, "risksAndConstraints")}
            htmlFor="action-plan-risks"
            label="Risks and constraints"
          >
            <textarea
              className={textareaClass}
              id="action-plan-risks"
              maxLength={10000}
              onChange={(event) =>
                set("risksAndConstraints", event.target.value)
              }
              value={form.risksAndConstraints}
            />
          </FormField>
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h3 className="text-base font-bold text-slate-800">
          Ownership and schedule
        </h3>
        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <FormField
            error={firstActionPlanError(errors, "ownerOfficeId")}
            htmlFor="action-plan-owner-office"
            label="Owner office"
          >
            <input
              className={inputClass}
              disabled
              id="action-plan-owner-office"
              value={ownerOffice?.name || "Lead responsible office"}
            />
          </FormField>
          <FormField
            error={firstActionPlanError(errors, "focalUserId")}
            htmlFor="action-plan-focal-user"
            label="Focal user"
            hint="Only eligible same-office users are listed."
          >
            <select
              className={inputClass}
              id="action-plan-focal-user"
              onChange={(event) => set("focalUserId", event.target.value)}
              value={form.focalUserId ?? ""}
            >
              <option value="">Select a focal user</option>
              {userOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </FormField>
          <FormField
            error={firstActionPlanError(errors, "plannedStartDate")}
            htmlFor="action-plan-start-date"
            label="Planned start date"
            hint="Required before submission."
          >
            <input
              className={inputClass}
              id="action-plan-start-date"
              onChange={(event) => set("plannedStartDate", event.target.value)}
              type="date"
              value={form.plannedStartDate}
            />
          </FormField>
          <FormField
            error={firstActionPlanError(errors, "plannedTargetDate")}
            htmlFor="action-plan-target-date"
            label="Planned target date"
            hint={
              effectiveTargetDate
                ? `Cannot exceed the effective recommendation target of ${effectiveTargetDate}.`
                : "This remains a proposal; it does not establish a recommendation target date."
            }
          >
            <input
              className={inputClass}
              id="action-plan-target-date"
              max={effectiveTargetDate || undefined}
              onChange={(event) => set("plannedTargetDate", event.target.value)}
              type="date"
              value={form.plannedTargetDate}
            />
          </FormField>
        </div>
      </section>

      <CmsActionPlanMilestoneEditor
        errors={errors}
        milestones={form.milestones}
        onChange={(milestones) => set("milestones", milestones)}
        ownerOffice={ownerOffice}
        plannedStartDate={form.plannedStartDate}
        plannedTargetDate={form.plannedTargetDate}
        targetCeiling={effectiveTargetDate}
        userOptions={userOptions}
      />

      <div className="sticky bottom-3 z-20 flex flex-wrap justify-end gap-2 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur">
        {isCreate && (
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
            disabled={busy}
            onClick={onCancel}
            type="button"
          >
            <X size={16} /> Cancel
          </button>
        )}
        <button
          className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
          disabled={busy}
          type="submit"
        >
          <Save size={16} />
          {busy ? "Saving..." : isCreate ? "Create draft" : "Save draft"}
        </button>
      </div>
    </form>
  );
}
