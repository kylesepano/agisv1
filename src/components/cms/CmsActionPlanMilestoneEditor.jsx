import { ArrowDown, ArrowUp, CirclePlus, Trash2 } from "lucide-react";
import FormField from "../ui/FormField";
import { milestoneWeightState } from "./cms-action-plan";

const inputClass =
  "mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const textareaClass =
  "mt-1 min-h-24 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";

function firstError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

function normalizeOrder(milestones) {
  return milestones.map((milestone, index) => ({
    ...milestone,
    sequenceNumber: index + 1,
    displayOrder: index + 1,
  }));
}

export default function CmsActionPlanMilestoneEditor({
  milestones,
  onChange,
  errors,
  ownerOffice,
  userOptions,
  plannedStartDate,
  plannedTargetDate,
  targetCeiling,
  disabled = false,
}) {
  const weight = milestoneWeightState(milestones);

  function addMilestone() {
    const next = {
      id: null,
      sequenceNumber: milestones.length + 1,
      title: "",
      description: "",
      expectedOutput: "",
      successIndicator: "",
      verificationMethod: "",
      responsibleOfficeId: ownerOffice?.id ?? "",
      responsibleUserId: "",
      plannedStartDate: plannedStartDate || "",
      plannedTargetDate: plannedTargetDate || "",
      weightPercentage: "",
      displayOrder: milestones.length + 1,
    };
    onChange([...milestones, next]);
  }

  function updateMilestone(index, field, value) {
    onChange(
      milestones.map((milestone, candidate) =>
        candidate === index ? { ...milestone, [field]: value } : milestone,
      ),
    );
  }

  function removeMilestone(index) {
    onChange(
      normalizeOrder(milestones.filter((_, candidate) => candidate !== index)),
    );
  }

  function moveMilestone(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= milestones.length) return;
    const next = [...milestones];
    [next[index], next[target]] = [next[target], next[index]];
    onChange(normalizeOrder(next));
  }

  return (
    <section
      aria-labelledby="action-plan-milestones"
      className="rounded-xl border border-slate-200 bg-slate-50 p-4"
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3
            className="text-base font-bold text-slate-800"
            id="action-plan-milestones"
          >
            Measurable milestones
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            Milestones remain with {ownerOffice?.name || "the owner office"} in
            CMS-3. Reordering recalculates the controlled sequence.
          </p>
          {targetCeiling && (
            <p className="mt-1 text-xs font-semibold text-amber-700">
              Effective recommendation target ceiling: {targetCeiling}
            </p>
          )}
        </div>
        {!disabled && (
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
            onClick={addMilestone}
            type="button"
          >
            <CirclePlus size={16} /> Add milestone
          </button>
        )}
      </div>

      {firstError(errors, "milestones") && (
        <div
          className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700"
          role="alert"
        >
          {firstError(errors, "milestones")}
        </div>
      )}

      <div
        className={`mt-3 rounded-lg border px-3 py-2 text-sm ${
          weight.valid
            ? "border-slate-200 bg-white text-slate-600"
            : "border-amber-300 bg-amber-50 text-amber-800"
        }`}
      >
        <span className="font-bold">Milestone weight:</span>{" "}
        {weight.supplied === 0
          ? "Not used"
          : `${weight.total.toFixed(2)}% across ${weight.supplied} of ${milestones.length} milestones`}
        {!weight.valid && (
          <span className="ml-1 font-semibold">
            {weight.partial
              ? "Every milestone must be weighted when any weight is used."
              : "The total must equal exactly 100%."}
          </span>
        )}
      </div>

      {milestones.length === 0 ? (
        <div className="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center">
          <p className="text-sm font-semibold text-slate-700">
            No milestones have been added.
          </p>
          <p className="mt-1 text-xs text-slate-500">
            At least one complete milestone is required before submission.
          </p>
        </div>
      ) : (
        <div className="mt-4 grid gap-4">
          {milestones.map((milestone, index) => (
            <article
              className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
              key={milestone.id ?? `new-${index}`}
            >
              <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
                    Milestone {index + 1}
                  </p>
                  <p className="mt-0.5 text-xs text-slate-500">
                    Sequence {milestone.sequenceNumber}
                  </p>
                </div>
                {!disabled && (
                  <div className="flex gap-1">
                    <button
                      aria-label={`Move milestone ${index + 1} up`}
                      className="grid h-9 w-9 place-items-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                      disabled={index === 0}
                      onClick={() => moveMilestone(index, -1)}
                      type="button"
                    >
                      <ArrowUp size={16} />
                    </button>
                    <button
                      aria-label={`Move milestone ${index + 1} down`}
                      className="grid h-9 w-9 place-items-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                      disabled={index === milestones.length - 1}
                      onClick={() => moveMilestone(index, 1)}
                      type="button"
                    >
                      <ArrowDown size={16} />
                    </button>
                    <button
                      aria-label={`Remove milestone ${index + 1}`}
                      className="grid h-9 w-9 place-items-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50"
                      onClick={() => removeMilestone(index)}
                      type="button"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                )}
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <FormField
                  error={firstError(errors, `milestones.${index}.title`)}
                  htmlFor={`milestone-${index}-title`}
                  label="Title"
                  required
                >
                  <input
                    className={inputClass}
                    disabled={disabled}
                    id={`milestone-${index}-title`}
                    maxLength={255}
                    onChange={(event) =>
                      updateMilestone(index, "title", event.target.value)
                    }
                    value={milestone.title}
                  />
                </FormField>
                <FormField
                  error={firstError(
                    errors,
                    `milestones.${index}.expectedOutput`,
                  )}
                  htmlFor={`milestone-${index}-output`}
                  label="Expected output"
                  required
                >
                  <input
                    className={inputClass}
                    disabled={disabled}
                    id={`milestone-${index}-output`}
                    onChange={(event) =>
                      updateMilestone(
                        index,
                        "expectedOutput",
                        event.target.value,
                      )
                    }
                    value={milestone.expectedOutput}
                  />
                </FormField>
                <FormField
                  error={firstError(errors, `milestones.${index}.description`)}
                  htmlFor={`milestone-${index}-description`}
                  label="Description"
                >
                  <textarea
                    className={textareaClass}
                    disabled={disabled}
                    id={`milestone-${index}-description`}
                    onChange={(event) =>
                      updateMilestone(index, "description", event.target.value)
                    }
                    value={milestone.description}
                  />
                </FormField>
                <FormField
                  error={firstError(
                    errors,
                    `milestones.${index}.successIndicator`,
                  )}
                  htmlFor={`milestone-${index}-indicator`}
                  label="Success indicator"
                >
                  <textarea
                    className={textareaClass}
                    disabled={disabled}
                    id={`milestone-${index}-indicator`}
                    onChange={(event) =>
                      updateMilestone(
                        index,
                        "successIndicator",
                        event.target.value,
                      )
                    }
                    value={milestone.successIndicator}
                  />
                </FormField>
                <FormField
                  error={firstError(
                    errors,
                    `milestones.${index}.verificationMethod`,
                  )}
                  htmlFor={`milestone-${index}-verification`}
                  label="Verification method"
                >
                  <input
                    className={inputClass}
                    disabled={disabled}
                    id={`milestone-${index}-verification`}
                    onChange={(event) =>
                      updateMilestone(
                        index,
                        "verificationMethod",
                        event.target.value,
                      )
                    }
                    value={milestone.verificationMethod}
                  />
                </FormField>
                <FormField
                  htmlFor={`milestone-${index}-office`}
                  label="Responsible office"
                >
                  <input
                    className={inputClass}
                    disabled
                    id={`milestone-${index}-office`}
                    value={ownerOffice?.name || "Owner office"}
                  />
                </FormField>
                <FormField
                  error={firstError(
                    errors,
                    `milestones.${index}.responsibleUserId`,
                  )}
                  htmlFor={`milestone-${index}-user`}
                  label="Responsible person"
                  hint="Optional. Only eligible same-office users are listed."
                >
                  <select
                    className={inputClass}
                    disabled={disabled}
                    id={`milestone-${index}-user`}
                    onChange={(event) =>
                      updateMilestone(
                        index,
                        "responsibleUserId",
                        event.target.value,
                      )
                    }
                    value={milestone.responsibleUserId ?? ""}
                  >
                    <option value="">Not assigned</option>
                    {userOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </FormField>
                <FormField
                  error={firstError(
                    errors,
                    `milestones.${index}.weightPercentage`,
                  )}
                  htmlFor={`milestone-${index}-weight`}
                  label="Weight percentage"
                  hint="Optional. If used here, every milestone must be weighted."
                >
                  <input
                    className={inputClass}
                    disabled={disabled}
                    id={`milestone-${index}-weight`}
                    max="100"
                    min="0.01"
                    onChange={(event) =>
                      updateMilestone(
                        index,
                        "weightPercentage",
                        event.target.value,
                      )
                    }
                    step="0.01"
                    type="number"
                    value={milestone.weightPercentage ?? ""}
                  />
                </FormField>
                <FormField
                  error={firstError(
                    errors,
                    `milestones.${index}.plannedStartDate`,
                  )}
                  htmlFor={`milestone-${index}-start`}
                  label="Planned start date"
                >
                  <input
                    className={inputClass}
                    disabled={disabled}
                    id={`milestone-${index}-start`}
                    onChange={(event) =>
                      updateMilestone(
                        index,
                        "plannedStartDate",
                        event.target.value,
                      )
                    }
                    type="date"
                    value={milestone.plannedStartDate ?? ""}
                  />
                </FormField>
                <FormField
                  error={firstError(
                    errors,
                    `milestones.${index}.plannedTargetDate`,
                  )}
                  htmlFor={`milestone-${index}-target`}
                  label="Planned completion date"
                  required
                >
                  <input
                    className={inputClass}
                    disabled={disabled}
                    id={`milestone-${index}-target`}
                    max={plannedTargetDate || targetCeiling || undefined}
                    onChange={(event) =>
                      updateMilestone(
                        index,
                        "plannedTargetDate",
                        event.target.value,
                      )
                    }
                    type="date"
                    value={milestone.plannedTargetDate ?? ""}
                  />
                </FormField>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
