import { useMemo, useState } from "react";
import { Plus, Trash2 } from "lucide-react";
import FormField from "../ui/FormField";
import SearchableSelect from "../ui/SearchableSelect";
import { hasPermission } from "../../config/navigation";

const nextYear = new Date().getFullYear() + 1;

function blankObjective(index = 0) {
  return {
    objectiveCode: `OBJ-${index + 1}`,
    title: "",
    description: "",
    expectedOutcome: "",
    auditAreaIds: [],
  };
}

function blankPriority(index = 0) {
  return {
    priorityCode: `PRI-${index + 1}`,
    title: "",
    theme: "",
    description: "",
    expectedOutcome: "",
  };
}

function initialForm(plan) {
  if (plan) {
    return {
      planCode: plan.planCode,
      startYear: plan.startYear,
      endYear: plan.endYear,
      title: plan.title,
      strategicContext: plan.strategicContext ?? "",
      vision: plan.vision ?? "",
      missionAlignment: plan.missionAlignment ?? "",
      planningMethodology: plan.planningMethodology ?? "",
      expectedOutcomes: plan.expectedOutcomes ?? "",
      coordinatorId: plan.coordinatorId ?? "",
      objectives: plan.objectives.map((objective) => ({
        objectiveCode: objective.objectiveCode,
        title: objective.title,
        description: objective.description,
        expectedOutcome: objective.expectedOutcome,
        auditAreaIds: objective.auditAreaIds,
      })),
      priorities: plan.priorities.map((priority) => ({
        priorityCode: priority.priorityCode,
        title: priority.title,
        theme: priority.theme,
        description: priority.description,
        expectedOutcome: priority.expectedOutcome,
      })),
      lockVersion: plan.lockVersion,
    };
  }

  return {
    planCode: "",
    startYear: nextYear,
    endYear: nextYear + 4,
    title: `${nextYear}–${nextYear + 4} Strategic Internal Audit Plan`,
    strategicContext: "",
    vision: "",
    missionAlignment: "",
    planningMethodology:
      "Risk-based planning using the Audit Universe, periodic risk assessment, resource capacity, management priorities, and applicable internal-audit standards.",
    expectedOutcomes: "",
    coordinatorId: "",
    objectives: [blankObjective()],
    priorities: [blankPriority()],
  };
}

export default function SiapPlanForm({
  plan,
  auditAreas,
  users,
  errors = {},
  saving = false,
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState(() => initialForm(plan));

  const areaOptions = useMemo(
    () =>
      auditAreas
        .filter((area) => area.isActive && !area.isArchived)
        .map((area) => ({
          value: area.id,
          label: `${area.code} — ${area.name}`,
          keywords: `${area.code} ${area.name}`,
        })),
    [auditAreas],
  );
  const userOptions = useMemo(
    () =>
      users
        .filter((candidate) => hasPermission(candidate, "iap.assign_team"))
        .map((candidate) => ({
          value: candidate.id,
          label: `${candidate.employeeId} — ${candidate.name}`,
        })),
    [users],
  );

  function updateObjective(index, field, value) {
    setForm((current) => ({
      ...current,
      objectives: current.objectives.map((objective, objectiveIndex) =>
        objectiveIndex === index ? { ...objective, [field]: value } : objective,
      ),
    }));
  }

  function updatePriority(index, field, value) {
    setForm((current) => ({
      ...current,
      priorities: current.priorities.map((priority, priorityIndex) =>
        priorityIndex === index ? { ...priority, [field]: value } : priority,
      ),
    }));
  }

  return (
    <form
      className="grid gap-5"
      id="siap-plan-form"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          ...form,
          coordinatorId: form.coordinatorId || null,
          startYear: Number(form.startYear),
          endYear: Number(form.endYear),
        });
      }}
    >
      <section className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2">
        <FormField
          error={errors.planCode?.[0]}
          htmlFor="siap-code"
          label="Plan code"
        >
          <input
            className="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 uppercase outline-none focus:border-sky-500"
            id="siap-code"
            onChange={(event) =>
              setForm({ ...form, planCode: event.target.value })
            }
            placeholder="Automatically generated when blank"
            value={form.planCode}
          />
        </FormField>
        <FormField
          error={errors.title?.[0]}
          htmlFor="siap-title"
          label="Strategic plan title"
          required
        >
          <input
            className="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 outline-none focus:border-sky-500"
            id="siap-title"
            onChange={(event) =>
              setForm({ ...form, title: event.target.value })
            }
            required
            value={form.title}
          />
        </FormField>
        <FormField
          error={errors.startYear?.[0]}
          htmlFor="siap-start"
          label="Start year"
          required
        >
          <input
            className="h-11 w-full rounded-lg border border-slate-300 bg-white px-3"
            id="siap-start"
            max="2199"
            min="2000"
            onChange={(event) =>
              setForm({ ...form, startYear: event.target.value })
            }
            required
            type="number"
            value={form.startYear}
          />
        </FormField>
        <FormField
          error={errors.endYear?.[0]}
          htmlFor="siap-end"
          label="End year"
          required
        >
          <input
            className="h-11 w-full rounded-lg border border-slate-300 bg-white px-3"
            id="siap-end"
            max="2200"
            min="2001"
            onChange={(event) =>
              setForm({ ...form, endYear: event.target.value })
            }
            required
            type="number"
            value={form.endYear}
          />
        </FormField>
        <FormField
          error={errors.coordinatorId?.[0]}
          htmlFor="siap-coordinator"
          label="Planning coordinator"
        >
          <SearchableSelect
            onChange={(value) => setForm({ ...form, coordinatorId: value })}
            options={userOptions}
            placeholder="Select a CIAS coordinator"
            value={form.coordinatorId}
          />
        </FormField>
      </section>

      <section className="grid gap-4 sm:grid-cols-2">
        {[
          ["strategicContext", "Strategic context"],
          ["vision", "Internal-audit vision"],
          ["missionAlignment", "Alignment with city and CIAS mandate"],
          ["planningMethodology", "Planning methodology"],
          ["expectedOutcomes", "Overall expected outcomes"],
        ].map(([field, label], index) => (
          <FormField
            error={errors[field]?.[0]}
            htmlFor={`siap-${field}`}
            key={field}
            label={label}
            required={field === "expectedOutcomes"}
          >
            <textarea
              className={`w-full rounded-lg border border-slate-300 p-3 outline-none focus:border-sky-500 ${
                index === 4 ? "min-h-28" : "min-h-24"
              }`}
              id={`siap-${field}`}
              onChange={(event) =>
                setForm({ ...form, [field]: event.target.value })
              }
              required={field === "expectedOutcomes"}
              value={form[field]}
            />
          </FormField>
        ))}
      </section>

      <section className="rounded-xl border border-sky-200 bg-sky-50/40 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="font-bold text-slate-800">Strategic objectives</h3>
            <p className="mt-1 text-xs text-slate-500">
              Every objective must identify an expected outcome and at least one
              related audit area.
            </p>
          </div>
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-3 text-sm font-bold text-white"
            onClick={() =>
              setForm((current) => ({
                ...current,
                objectives: [
                  ...current.objectives,
                  blankObjective(current.objectives.length),
                ],
              }))
            }
            type="button"
          >
            <Plus size={16} /> Add objective
          </button>
        </div>
        <div className="mt-4 grid gap-4">
          {form.objectives.map((objective, index) => (
            <article
              className="rounded-xl border border-sky-200 bg-white p-4 shadow-sm"
              key={`objective-${index}`}
            >
              <div className="mb-3 flex items-center justify-between">
                <strong className="text-sm text-slate-800">
                  Objective {index + 1}
                </strong>
                <button
                  className="grid h-9 w-9 place-items-center rounded-lg text-red-600 hover:bg-red-50 disabled:opacity-30"
                  disabled={form.objectives.length === 1}
                  onClick={() =>
                    setForm((current) => ({
                      ...current,
                      objectives: current.objectives.filter(
                        (_, itemIndex) => itemIndex !== index,
                      ),
                    }))
                  }
                  type="button"
                >
                  <Trash2 size={17} />
                </button>
              </div>
              <div className="grid gap-3 sm:grid-cols-[10rem_1fr]">
                <input
                  className="h-11 rounded-lg border border-slate-300 px-3 uppercase"
                  onChange={(event) =>
                    updateObjective(index, "objectiveCode", event.target.value)
                  }
                  placeholder="OBJ-1"
                  required
                  value={objective.objectiveCode}
                />
                <input
                  className="h-11 rounded-lg border border-slate-300 px-3"
                  onChange={(event) =>
                    updateObjective(index, "title", event.target.value)
                  }
                  placeholder="Objective title"
                  required
                  value={objective.title}
                />
                <textarea
                  className="min-h-20 rounded-lg border border-slate-300 p-3 sm:col-span-2"
                  onChange={(event) =>
                    updateObjective(index, "description", event.target.value)
                  }
                  placeholder="Describe the strategic objective."
                  required
                  value={objective.description}
                />
                <textarea
                  className="min-h-20 rounded-lg border border-slate-300 p-3 sm:col-span-2"
                  onChange={(event) =>
                    updateObjective(
                      index,
                      "expectedOutcome",
                      event.target.value,
                    )
                  }
                  placeholder="Expected outcome"
                  required
                  value={objective.expectedOutcome}
                />
                <div className="sm:col-span-2">
                  <SearchableSelect
                    multiple
                    onChange={(value) =>
                      updateObjective(index, "auditAreaIds", value)
                    }
                    options={areaOptions}
                    placeholder="Link one or more audit areas"
                    searchPlaceholder="Search audit areas..."
                    value={objective.auditAreaIds}
                  />
                  {errors[`objectives.${index}.auditAreaIds`]?.[0] && (
                    <p className="mt-1 text-xs font-semibold text-red-600">
                      {errors[`objectives.${index}.auditAreaIds`][0]}
                    </p>
                  )}
                </div>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="rounded-xl border border-violet-200 bg-violet-50/40 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="font-bold text-slate-800">
              Audit priorities and themes
            </h3>
            <p className="mt-1 text-xs text-slate-500">
              Define the strategic themes that guide annual risk-based planning.
            </p>
          </div>
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg bg-violet-700 px-3 text-sm font-bold text-white"
            onClick={() =>
              setForm((current) => ({
                ...current,
                priorities: [
                  ...current.priorities,
                  blankPriority(current.priorities.length),
                ],
              }))
            }
            type="button"
          >
            <Plus size={16} /> Add priority
          </button>
        </div>
        <div className="mt-4 grid gap-4">
          {form.priorities.map((priority, index) => (
            <article
              className="rounded-xl border border-violet-200 bg-white p-4 shadow-sm"
              key={`priority-${index}`}
            >
              <div className="mb-3 flex items-center justify-between">
                <strong className="text-sm text-slate-800">
                  Priority {index + 1}
                </strong>
                <button
                  className="grid h-9 w-9 place-items-center rounded-lg text-red-600 hover:bg-red-50 disabled:opacity-30"
                  disabled={form.priorities.length === 1}
                  onClick={() =>
                    setForm((current) => ({
                      ...current,
                      priorities: current.priorities.filter(
                        (_, itemIndex) => itemIndex !== index,
                      ),
                    }))
                  }
                  type="button"
                >
                  <Trash2 size={17} />
                </button>
              </div>
              <div className="grid gap-3 sm:grid-cols-[10rem_1fr_1fr]">
                <input
                  className="h-11 rounded-lg border border-slate-300 px-3 uppercase"
                  onChange={(event) =>
                    updatePriority(index, "priorityCode", event.target.value)
                  }
                  placeholder="PRI-1"
                  required
                  value={priority.priorityCode}
                />
                <input
                  className="h-11 rounded-lg border border-slate-300 px-3"
                  onChange={(event) =>
                    updatePriority(index, "title", event.target.value)
                  }
                  placeholder="Priority title"
                  required
                  value={priority.title}
                />
                <input
                  className="h-11 rounded-lg border border-slate-300 px-3"
                  onChange={(event) =>
                    updatePriority(index, "theme", event.target.value)
                  }
                  placeholder="Theme, e.g. Digital Governance"
                  required
                  value={priority.theme}
                />
                <textarea
                  className="min-h-20 rounded-lg border border-slate-300 p-3 sm:col-span-3"
                  onChange={(event) =>
                    updatePriority(index, "description", event.target.value)
                  }
                  placeholder="Describe the audit priority."
                  required
                  value={priority.description}
                />
                <textarea
                  className="min-h-20 rounded-lg border border-slate-300 p-3 sm:col-span-3"
                  onChange={(event) =>
                    updatePriority(index, "expectedOutcome", event.target.value)
                  }
                  placeholder="Expected outcome"
                  required
                  value={priority.expectedOutcome}
                />
              </div>
            </article>
          ))}
        </div>
      </section>

      <div className="flex justify-end gap-2 border-t border-slate-200 pt-4">
        <button
          className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
          onClick={onCancel}
          type="button"
        >
          Cancel
        </button>
        <button
          className="h-11 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-50"
          disabled={saving}
          type="submit"
        >
          {saving ? "Saving..." : plan ? "Save changes" : "Create SIAP"}
        </button>
      </div>
    </form>
  );
}
