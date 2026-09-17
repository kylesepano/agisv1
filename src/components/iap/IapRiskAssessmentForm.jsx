import { useMemo, useState } from "react";
import { Calculator, CircleAlert, Gauge, ShieldAlert } from "lucide-react";
import FormField from "../ui/FormField";
import SearchableSelect from "../ui/SearchableSelect";
import StatusBadge from "../ui/StatusBadge";

const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

const ratingLabels = {
  1: "Very Low",
  2: "Low",
  3: "Moderate",
  4: "High",
  5: "Very High",
};

function firstError(errors, key) {
  return Array.isArray(errors?.[key]) ? errors[key][0] : "";
}

function weightFromDescription(description, fallback) {
  const match = String(description ?? "").match(/weight\s+(\d+(?:\.\d+)?)%/i);
  return match ? Number(match[1]) : fallback;
}

function riskCode(score) {
  if (score < 2) return "LOW";
  if (score < 3) return "MEDIUM";
  if (score < 4) return "HIGH";
  return "CRITICAL";
}

function initialForm(assessment, criteria) {
  const fallbackWeight = criteria.length ? 100 / criteria.length : 0;
  const existingScores = new Map(
    (assessment?.scores ?? []).map((score) => [
      String(score.criterionId),
      score,
    ]),
  );

  return {
    officeId: assessment?.officeId ?? "",
    auditAreaId: assessment?.auditAreaId ?? "",
    assessmentDate:
      assessment?.assessmentDate ?? new Date().toISOString().slice(0, 10),
    lastAuditDate: assessment?.lastAuditDate ?? "",
    inherentRiskNotes: assessment?.inherentRiskNotes ?? "",
    controlEnvironmentNotes: assessment?.controlEnvironmentNotes ?? "",
    overrideRiskLevelId: assessment?.overrideRiskLevelId ?? "",
    overrideReason: assessment?.overrideReason ?? "",
    justification: assessment?.justification ?? "",
    scores: criteria.map((criterion) => {
      const existing = existingScores.get(String(criterion.id));
      return {
        criterionId: criterion.id,
        criterion,
        weight:
          existing?.weight ??
          weightFromDescription(criterion.description, fallbackWeight),
        rating: existing?.rating ?? 3,
        comment: existing?.comment ?? "",
      };
    }),
  };
}

export default function IapRiskAssessmentForm({
  assessment,
  criteria,
  riskLevels,
  offices,
  errors = {},
  formId = "iap-risk-form",
  onSubmit,
}) {
  const [form, setForm] = useState(() => initialForm(assessment, criteria));

  const officeOptions = useMemo(
    () =>
      offices.map((office) => ({
        value: office.id,
        label: `${office.code} — ${office.name}`,
        description: office.sector,
        keywords: `${office.code} ${office.name} ${office.sector ?? ""}`,
      })),
    [offices],
  );

  const selectedOffice = offices.find(
    (office) => String(office.id) === String(form.officeId),
  );
  const areaOptions = useMemo(
    () =>
      (selectedOffice?.auditAreas ?? []).map((area) => ({
        value: area.id,
        label: `${area.code} — ${area.name}`,
        description: area.description,
      })),
    [selectedOffice],
  );
  const riskOptions = riskLevels.map((level) => ({
    value: level.id,
    label: level.label,
    description: level.description,
  }));
  const totalWeight = form.scores.reduce(
    (sum, score) => sum + Number(score.weight || 0),
    0,
  );
  const weightedScore = form.scores.reduce(
    (sum, score) =>
      sum + (Number(score.rating || 0) * Number(score.weight || 0)) / 100,
    0,
  );
  const calculatedCode = riskCode(weightedScore);
  const calculatedLevel = riskLevels.find(
    (level) => level.code === calculatedCode,
  );
  const overrideLevel = riskLevels.find(
    (level) => String(level.id) === String(form.overrideRiskLevelId),
  );
  const finalLevel = overrideLevel ?? calculatedLevel;

  function update(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function updateScore(index, key, value) {
    setForm((current) => ({
      ...current,
      scores: current.scores.map((score, scoreIndex) =>
        scoreIndex === index ? { ...score, [key]: value } : score,
      ),
    }));
  }

  return (
    <form
      className="grid gap-5"
      id={formId}
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          officeId: Number(form.officeId),
          auditAreaId: Number(form.auditAreaId),
          assessmentDate: form.assessmentDate,
          lastAuditDate: form.lastAuditDate || null,
          inherentRiskNotes: form.inherentRiskNotes.trim() || null,
          controlEnvironmentNotes: form.controlEnvironmentNotes.trim() || null,
          overrideRiskLevelId: form.overrideRiskLevelId
            ? Number(form.overrideRiskLevelId)
            : null,
          overrideReason: form.overrideRiskLevelId
            ? form.overrideReason.trim()
            : null,
          justification: form.justification.trim(),
          scores: form.scores.map((score) => ({
            criterionId: Number(score.criterionId),
            weight: Number(score.weight),
            rating: Number(score.rating),
            comment: score.comment.trim() || null,
          })),
        });
      }}
    >
      <section className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
            <ShieldAlert className="text-sky-700" size={18} />
            Assessment subject
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            Each office and audit-area combination may be assessed once per plan
            revision.
          </p>
        </div>
        <FormField
          error={firstError(errors, "officeId")}
          label="Office"
          required
        >
          <SearchableSelect
            disabled={Boolean(assessment)}
            onChange={(officeId) =>
              setForm((current) => ({
                ...current,
                officeId,
                auditAreaId: "",
              }))
            }
            options={officeOptions}
            placeholder="Select an office"
            searchPlaceholder="Search city offices..."
            value={form.officeId}
          />
        </FormField>
        <FormField
          error={firstError(errors, "auditAreaId")}
          label="Audit area"
          required
          hint={
            form.officeId
              ? "Only audit areas linked to the selected office are available."
              : "Select an office first."
          }
        >
          <SearchableSelect
            disabled={!form.officeId || Boolean(assessment)}
            onChange={(auditAreaId) => update("auditAreaId", auditAreaId)}
            options={areaOptions}
            placeholder="Select a linked audit area"
            searchPlaceholder="Search linked audit areas..."
            value={form.auditAreaId}
          />
        </FormField>
        <FormField
          error={firstError(errors, "assessmentDate")}
          htmlFor="risk-assessment-date"
          label="Assessment date"
          required
        >
          <input
            className={inputClass}
            id="risk-assessment-date"
            onChange={(event) => update("assessmentDate", event.target.value)}
            type="date"
            value={form.assessmentDate}
          />
        </FormField>
        <FormField
          error={firstError(errors, "lastAuditDate")}
          htmlFor="risk-last-audit-date"
          label="Last audit date"
          hint="Leave blank when no reliable prior audit date is available."
        >
          <input
            className={inputClass}
            id="risk-last-audit-date"
            max={form.assessmentDate}
            onChange={(event) => update("lastAuditDate", event.target.value)}
            type="date"
            value={form.lastAuditDate}
          />
        </FormField>
      </section>

      <section>
        <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
              <Calculator className="text-violet-700" size={18} />
              Weighted risk criteria
            </h3>
            <p className="mt-1 text-xs leading-5 text-slate-500">
              Rate every criterion from 1 to 5. Criterion weights must total
              exactly 100%.
            </p>
          </div>
          <span
            className={`rounded-lg px-3 py-2 text-xs font-bold ${
              Math.abs(totalWeight - 100) <= 0.01
                ? "bg-emerald-50 text-emerald-700"
                : "bg-red-50 text-red-700"
            }`}
          >
            Total weight: {totalWeight.toFixed(2)}%
          </span>
        </div>
        {firstError(errors, "scores") && (
          <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
            {firstError(errors, "scores")}
          </p>
        )}
        <div className="overflow-hidden rounded-xl border border-slate-200">
          <div className="hidden grid-cols-[minmax(15rem,1fr)_7rem_10rem_9rem] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-500 lg:grid">
            <span>Criterion</span>
            <span>Weight</span>
            <span>Rating</span>
            <span>Weighted score</span>
          </div>
          <div className="divide-y divide-slate-100 bg-white">
            {form.scores.map((score, index) => {
              const lineScore =
                (Number(score.rating || 0) * Number(score.weight || 0)) / 100;
              return (
                <article className="grid gap-3 p-4" key={score.criterionId}>
                  <div className="grid items-start gap-3 lg:grid-cols-[minmax(15rem,1fr)_7rem_10rem_9rem]">
                    <div>
                      <strong className="block text-sm text-slate-800">
                        {score.criterion.label}
                      </strong>
                      <p className="mt-1 text-xs leading-5 text-slate-500">
                        {score.criterion.description}
                      </p>
                    </div>
                    <label>
                      <span className="mb-1 block text-[11px] font-bold uppercase text-slate-400 lg:hidden">
                        Weight %
                      </span>
                      <input
                        className={inputClass}
                        max="100"
                        min="0.01"
                        onChange={(event) =>
                          updateScore(index, "weight", event.target.value)
                        }
                        step="0.01"
                        type="number"
                        value={score.weight}
                      />
                    </label>
                    <label>
                      <span className="mb-1 block text-[11px] font-bold uppercase text-slate-400 lg:hidden">
                        Rating
                      </span>
                      <select
                        className={inputClass}
                        onChange={(event) =>
                          updateScore(index, "rating", event.target.value)
                        }
                        value={score.rating}
                      >
                        {Object.entries(ratingLabels).map(([value, label]) => (
                          <option key={value} value={value}>
                            {value} — {label}
                          </option>
                        ))}
                      </select>
                    </label>
                    <div>
                      <span className="mb-1 block text-[11px] font-bold uppercase text-slate-400 lg:hidden">
                        Weighted score
                      </span>
                      <span className="flex min-h-11 items-center rounded-lg bg-slate-50 px-3 text-sm font-bold text-slate-700">
                        {lineScore.toFixed(4)}
                      </span>
                    </div>
                  </div>
                  <input
                    className={inputClass}
                    onChange={(event) =>
                      updateScore(index, "comment", event.target.value)
                    }
                    placeholder="Optional criterion-specific evidence or comment"
                    value={score.comment}
                  />
                </article>
              );
            })}
          </div>
        </div>
      </section>

      <section className="grid gap-4 rounded-xl border border-sky-200 bg-sky-50/70 p-4 sm:grid-cols-[1fr_1fr]">
        <div className="sm:col-span-2">
          <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
            <Gauge className="text-sky-700" size={18} />
            Calculated assessment result
          </h3>
        </div>
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-sky-100">
          <span className="text-xs font-bold uppercase tracking-wide text-slate-400">
            Weighted score
          </span>
          <strong className="mt-2 block text-3xl text-slate-900">
            {weightedScore.toFixed(2)}
            <span className="ml-1 text-sm font-medium text-slate-400">/ 5</span>
          </strong>
        </div>
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-sky-100">
          <span className="text-xs font-bold uppercase tracking-wide text-slate-400">
            Final risk level
          </span>
          <div className="mt-3">
            <StatusBadge
              tone={
                ["CRITICAL", "HIGH"].includes(finalLevel?.code)
                  ? "danger"
                  : finalLevel?.code === "MEDIUM"
                    ? "warning"
                    : "success"
              }
            >
              {finalLevel?.label ?? calculatedCode}
            </StatusBadge>
            {overrideLevel && (
              <span className="ml-2 text-xs font-semibold text-violet-700">
                Management override
              </span>
            )}
          </div>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2">
        <FormField
          error={firstError(errors, "inherentRiskNotes")}
          htmlFor="risk-inherent-notes"
          label="Inherent risk notes"
        >
          <textarea
            className={`${inputClass} min-h-28 py-3`}
            id="risk-inherent-notes"
            onChange={(event) =>
              update("inherentRiskNotes", event.target.value)
            }
            placeholder="Describe exposure before considering controls."
            value={form.inherentRiskNotes}
          />
        </FormField>
        <FormField
          error={firstError(errors, "controlEnvironmentNotes")}
          htmlFor="risk-control-notes"
          label="Control environment notes"
        >
          <textarea
            className={`${inputClass} min-h-28 py-3`}
            id="risk-control-notes"
            onChange={(event) =>
              update("controlEnvironmentNotes", event.target.value)
            }
            placeholder="Summarize control maturity, known weaknesses, and monitoring."
            value={form.controlEnvironmentNotes}
          />
        </FormField>
        <div className="sm:col-span-2">
          <FormField
            error={firstError(errors, "justification")}
            htmlFor="risk-justification"
            label="Assessment justification"
            required
          >
            <textarea
              className={`${inputClass} min-h-28 py-3`}
              id="risk-justification"
              onChange={(event) => update("justification", event.target.value)}
              placeholder="Explain the evidence and reasoning supporting the assessment."
              value={form.justification}
            />
          </FormField>
        </div>
      </section>

      <section className="grid gap-4 rounded-xl border border-violet-200 bg-violet-50/60 p-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <h3 className="text-sm font-bold text-slate-800">
            Management risk override
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            Leave blank to use the calculated level. Any override requires a
            written reason and remains visible in the assessment record.
          </p>
        </div>
        <FormField
          error={firstError(errors, "overrideRiskLevelId")}
          label="Override risk level"
        >
          <SearchableSelect
            onChange={(value) => {
              update("overrideRiskLevelId", value);
              if (!value) update("overrideReason", "");
            }}
            options={riskOptions}
            placeholder="Use calculated risk level"
            searchPlaceholder="Search risk levels..."
            value={form.overrideRiskLevelId}
          />
          {form.overrideRiskLevelId && (
            <button
              className="mt-2 text-xs font-bold text-violet-700 hover:text-violet-900"
              onClick={() =>
                setForm((current) => ({
                  ...current,
                  overrideRiskLevelId: "",
                  overrideReason: "",
                }))
              }
              type="button"
            >
              Remove override
            </button>
          )}
        </FormField>
        <FormField
          error={firstError(errors, "overrideReason")}
          htmlFor="risk-override-reason"
          label="Override reason"
          required={Boolean(form.overrideRiskLevelId)}
        >
          <textarea
            className={`${inputClass} min-h-24 py-3 disabled:bg-slate-100`}
            disabled={!form.overrideRiskLevelId}
            id="risk-override-reason"
            onChange={(event) => update("overrideReason", event.target.value)}
            placeholder="Explain why professional judgment differs from the calculated result."
            value={form.overrideReason}
          />
        </FormField>
      </section>

      {Math.abs(totalWeight - 100) > 0.01 && (
        <div className="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
          <CircleAlert className="shrink-0" size={19} />
          Adjust criterion weights to exactly 100% before saving.
        </div>
      )}
    </form>
  );
}
