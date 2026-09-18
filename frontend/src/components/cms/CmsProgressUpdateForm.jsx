import { Save, X } from "lucide-react";
import FormField from "../ui/FormField";

const inputClass =
  "mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const textareaClass =
  "mt-1 min-h-24 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";

const statusOptions = [
  ["NOT_STARTED", "Not started"],
  ["IN_PROGRESS", "In progress"],
  ["REPORTED_COMPLETED", "Reported completed"],
  ["DELAYED", "Delayed"],
  ["ON_HOLD", "On hold"],
];

function firstError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

function milestoneDefinition(milestone, entry, index) {
  return {
    id: milestone?.id ?? entry?.actionPlanMilestoneId,
    sequenceNumber:
      milestone?.sequenceNumber ?? entry?.milestoneSequence ?? index + 1,
    title: milestone?.title ?? entry?.milestoneSnapshot?.title ?? "Milestone",
    description:
      milestone?.description ?? entry?.milestoneSnapshot?.description ?? "",
    expectedOutput:
      milestone?.expectedOutput ?? entry?.milestoneSnapshot?.expectedOutput,
    responsibleOffice:
      milestone?.responsibleOffice ??
      (entry?.milestoneSnapshot?.responsibleOfficeId
        ? { id: entry.milestoneSnapshot.responsibleOfficeId }
        : null),
    responsibleUser:
      milestone?.responsibleUser ??
      (entry?.milestoneSnapshot?.responsibleUserId
        ? { id: entry.milestoneSnapshot.responsibleUserId }
        : null),
    plannedStartDate:
      milestone?.plannedStartDate ?? entry?.milestoneSnapshot?.plannedStartDate,
    plannedTargetDate:
      milestone?.plannedTargetDate ??
      entry?.milestoneSnapshot?.plannedTargetDate,
    weightPercentage:
      milestone?.weightPercentage ?? entry?.milestoneSnapshot?.weightPercentage,
  };
}

function MilestoneCard({
  definition,
  entry,
  index,
  errors,
  onChange,
  evidenceCount,
}) {
  const prefix = `milestoneProgress.${index}`;
  const set = (field, value) => onChange({ ...entry, [field]: value });

  return (
    <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
            Milestone {definition.sequenceNumber}
          </p>
          <h4 className="mt-1 text-base font-bold text-slate-800">
            {definition.title}
          </h4>
          {definition.description && (
            <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-600">
              {definition.description}
            </p>
          )}
        </div>
        <div className="flex flex-wrap items-center gap-2 text-xs">
          {definition.weightPercentage !== null &&
            definition.weightPercentage !== undefined && (
              <span className="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">
                Weight {definition.weightPercentage}%
              </span>
            )}
          <span className="rounded-full bg-sky-50 px-2.5 py-1 font-semibold text-sky-700">
            Evidence: {evidenceCount ?? 0}
          </span>
        </div>
      </div>

      <div className="mt-4 grid gap-4 md:grid-cols-2">
        <FormField
          error={firstError(errors, `${prefix}.managementReportedStatusCode`)}
          htmlFor={`milestone-status-${definition.id}`}
          label="Management-reported status"
          required
        >
          <select
            className={inputClass}
            id={`milestone-status-${definition.id}`}
            onChange={(event) =>
              set("managementReportedStatusCode", event.target.value)
            }
            value={entry.managementReportedStatusCode ?? "NOT_STARTED"}
          >
            {statusOptions.map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </FormField>
        <FormField
          error={firstError(errors, `${prefix}.managementReportedPercentage`)}
          htmlFor={`milestone-percentage-${definition.id}`}
          label="Management-reported percentage"
          required
          hint="This is a management report, not an independent validation result."
        >
          <input
            className={inputClass}
            id={`milestone-percentage-${definition.id}`}
            max="100"
            min="0"
            onChange={(event) =>
              set("managementReportedPercentage", event.target.value)
            }
            step="0.01"
            type="number"
            value={entry.managementReportedPercentage ?? 0}
          />
        </FormField>
        <div className="md:col-span-2">
          <FormField
            error={firstError(errors, `${prefix}.accomplishmentDescription`)}
            htmlFor={`milestone-accomplishment-${definition.id}`}
            label="Reported accomplishment"
            hint="Describe what management reports as accomplished, or use the explanation below."
          >
            <textarea
              className={textareaClass}
              id={`milestone-accomplishment-${definition.id}`}
              maxLength={10000}
              onChange={(event) =>
                set("accomplishmentDescription", event.target.value)
              }
              value={entry.accomplishmentDescription ?? ""}
            />
          </FormField>
        </div>
        <FormField
          error={firstError(errors, `${prefix}.issuesAndConstraints`)}
          htmlFor={`milestone-issues-${definition.id}`}
          label="Issues and constraints"
        >
          <textarea
            className={textareaClass}
            id={`milestone-issues-${definition.id}`}
            maxLength={10000}
            onChange={(event) =>
              set("issuesAndConstraints", event.target.value)
            }
            value={entry.issuesAndConstraints ?? ""}
          />
        </FormField>
        <FormField
          error={firstError(errors, `${prefix}.nextStep`)}
          htmlFor={`milestone-next-step-${definition.id}`}
          label="Next step"
        >
          <textarea
            className={textareaClass}
            id={`milestone-next-step-${definition.id}`}
            maxLength={10000}
            onChange={(event) => set("nextStep", event.target.value)}
            value={entry.nextStep ?? ""}
          />
        </FormField>
        <FormField
          error={firstError(errors, `${prefix}.forecastCompletionDate`)}
          htmlFor={`milestone-forecast-${definition.id}`}
          label="Forecast completion date"
        >
          <input
            className={inputClass}
            id={`milestone-forecast-${definition.id}`}
            onChange={(event) =>
              set("forecastCompletionDate", event.target.value)
            }
            type="date"
            value={entry.forecastCompletionDate ?? ""}
          />
        </FormField>
        <div className="md:col-span-2">
          <FormField
            error={firstError(errors, `${prefix}.noEvidenceExplanation`)}
            htmlFor={`milestone-no-evidence-${definition.id}`}
            label="No-evidence explanation"
            hint="Required when progress is reported without linked supporting evidence."
          >
            <textarea
              className={textareaClass}
              id={`milestone-no-evidence-${definition.id}`}
              maxLength={10000}
              onChange={(event) =>
                set("noEvidenceExplanation", event.target.value)
              }
              value={entry.noEvidenceExplanation ?? ""}
            />
          </FormField>
        </div>
      </div>
    </article>
  );
}

export default function CmsProgressUpdateForm({
  form,
  setForm,
  errors,
  milestones = [],
  evidenceByMilestone = {},
  busy,
  isCreate = false,
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

  const entries = form.milestoneProgress ?? [];
  const definitions = milestones.length
    ? milestones
    : entries.map((entry, index) => milestoneDefinition(null, entry, index));

  function set(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  function updateMilestone(index, value) {
    setForm((current) => ({
      ...current,
      milestoneProgress: current.milestoneProgress.map((entry, entryIndex) =>
        entryIndex === index ? value : entry,
      ),
    }));
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
          aria-labelledby="progress-validation-summary"
          className="rounded-xl border border-red-200 bg-red-50 p-4"
          role="alert"
        >
          <h3
            className="text-sm font-bold text-red-800"
            id="progress-validation-summary"
          >
            Review the following Progress Update fields
          </h3>
          <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
            {summaryErrors.slice(0, 15).map((error, index) => (
              <li key={`${error.key}-${index}`}>{error.message}</li>
            ))}
          </ul>
        </section>
      )}

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-bold text-slate-800">
              Management progress report
            </h3>
            <p className="mt-1 text-xs leading-5 text-slate-500">
              This workspace records management-reported progress and supporting
              evidence. It does not independently validate implementation.
            </p>
          </div>
          <span className="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800">
            Awaiting independent validation
          </span>
        </div>

        <div className="mt-5 grid gap-4 md:grid-cols-2">
          <FormField
            error={firstError(errors, "reportingPeriodStart")}
            htmlFor="progress-period-start"
            label="Reporting period start"
            required
          >
            <input
              className={inputClass}
              disabled={!isCreate}
              id="progress-period-start"
              onChange={(event) =>
                set("reportingPeriodStart", event.target.value)
              }
              type="date"
              value={form.reportingPeriodStart ?? ""}
            />
          </FormField>
          <FormField
            error={firstError(errors, "reportingPeriodEnd")}
            htmlFor="progress-period-end"
            label="Reporting period end"
            required
          >
            <input
              className={inputClass}
              disabled={!isCreate}
              id="progress-period-end"
              onChange={(event) =>
                set("reportingPeriodEnd", event.target.value)
              }
              type="date"
              value={form.reportingPeriodEnd ?? ""}
            />
          </FormField>
          <div className="md:col-span-2">
            <FormField
              error={firstError(errors, "accomplishmentSummary")}
              htmlFor="progress-accomplishment-summary"
              label="Accomplishment summary"
              required
            >
              <textarea
                className={textareaClass}
                id="progress-accomplishment-summary"
                maxLength={10000}
                onChange={(event) =>
                  set("accomplishmentSummary", event.target.value)
                }
                value={form.accomplishmentSummary ?? ""}
              />
            </FormField>
          </div>
          <FormField
            error={firstError(errors, "managementReportedOverallPercentage")}
            htmlFor="progress-overall-percentage"
            label="Overall management-reported percentage"
            hint={
              form.baselineWeighted
                ? "Calculated by the server from milestone weights."
                : "Enter management's overall reported percentage. The system does not average unweighted milestones."
            }
          >
            <input
              className={inputClass}
              disabled={Boolean(form.baselineWeighted)}
              id="progress-overall-percentage"
              max="100"
              min="0"
              onChange={(event) =>
                set("managementReportedOverallPercentage", event.target.value)
              }
              step="0.01"
              type="number"
              value={form.managementReportedOverallPercentage ?? ""}
            />
          </FormField>
          <div className="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
            <strong>
              {form.baselineWeighted
                ? "Weighted baseline"
                : "Unweighted baseline"}
            </strong>
            <p className="mt-1 text-xs leading-5">
              {form.systemCalculatedWeightedReportedPercentage !== null &&
              form.systemCalculatedWeightedReportedPercentage !== undefined
                ? `Server-calculated weighted reported progress: ${form.systemCalculatedWeightedReportedPercentage}% (rounded to two decimals).`
                : "The overall percentage remains management-reported."}
            </p>
          </div>
          <div className="md:col-span-2">
            <FormField
              error={firstError(errors, "issuesAndConstraints")}
              htmlFor="progress-issues"
              label="Issues and constraints"
            >
              <textarea
                className={textareaClass}
                id="progress-issues"
                maxLength={10000}
                onChange={(event) =>
                  set("issuesAndConstraints", event.target.value)
                }
                value={form.issuesAndConstraints ?? ""}
              />
            </FormField>
          </div>
          <FormField
            error={firstError(errors, "correctiveActionsForDelays")}
            htmlFor="progress-delays"
            label="Corrective actions for delays"
          >
            <textarea
              className={textareaClass}
              id="progress-delays"
              maxLength={10000}
              onChange={(event) =>
                set("correctiveActionsForDelays", event.target.value)
              }
              value={form.correctiveActionsForDelays ?? ""}
            />
          </FormField>
          <FormField
            error={firstError(errors, "nextSteps")}
            htmlFor="progress-next-steps"
            label="Next steps"
          >
            <textarea
              className={textareaClass}
              id="progress-next-steps"
              maxLength={10000}
              onChange={(event) => set("nextSteps", event.target.value)}
              value={form.nextSteps ?? ""}
            />
          </FormField>
          <FormField
            error={firstError(errors, "forecastCompletionDate")}
            htmlFor="progress-forecast"
            label="Forecast completion date"
          >
            <input
              className={inputClass}
              id="progress-forecast"
              onChange={(event) =>
                set("forecastCompletionDate", event.target.value)
              }
              type="date"
              value={form.forecastCompletionDate ?? ""}
            />
          </FormField>
          <FormField
            error={firstError(errors, "managementDeclaration")}
            htmlFor="progress-declaration"
            label="Management declaration"
          >
            <textarea
              className={textareaClass}
              id="progress-declaration"
              maxLength={10000}
              onChange={(event) =>
                set("managementDeclaration", event.target.value)
              }
              value={form.managementDeclaration ?? ""}
            />
          </FormField>
          <div className="md:col-span-2">
            <FormField
              error={firstError(errors, "generalEvidenceExplanation")}
              htmlFor="progress-general-evidence"
              label="General evidence explanation"
              hint="A supporting document or suitable explanation is required before submission."
            >
              <textarea
                className={textareaClass}
                id="progress-general-evidence"
                maxLength={10000}
                onChange={(event) =>
                  set("generalEvidenceExplanation", event.target.value)
                }
                value={form.generalEvidenceExplanation ?? ""}
              />
            </FormField>
          </div>
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
        <div>
          <h3 className="text-base font-bold text-slate-800">
            Milestone progress
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            The accepted Action Plan baseline is fixed. Every accepted milestone
            must be reported exactly once, and its wording, owner, dates, and
            weight cannot be changed here.
          </p>
        </div>
        <div className="mt-4 grid gap-4">
          {definitions.map((definition, index) => (
            <MilestoneCard
              definition={milestoneDefinition(
                definition,
                entries[index],
                index,
              )}
              entry={
                entries[index] ?? {
                  actionPlanMilestoneId: definition.id,
                  managementReportedStatusCode: "NOT_STARTED",
                  managementReportedPercentage: 0,
                  displayOrder: index + 1,
                }
              }
              evidenceCount={evidenceByMilestone[entries[index]?.id] ?? 0}
              errors={errors}
              index={index}
              key={definition.id ?? index}
              onChange={(value) => updateMilestone(index, value)}
            />
          ))}
        </div>
        {definitions.length === 0 && (
          <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            No accepted Action Plan milestones are available. Progress reporting
            cannot be submitted until an accepted baseline is available.
          </p>
        )}
      </section>

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
          <Save size={16} />{" "}
          {busy ? "Saving..." : isCreate ? "Create draft" : "Save draft"}
        </button>
      </div>
    </form>
  );
}
