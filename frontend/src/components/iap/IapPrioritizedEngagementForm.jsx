import { useMemo, useState } from "react";
import FormField from "../ui/FormField";
import SearchableSelect from "../ui/SearchableSelect";
import StatusBadge from "../ui/StatusBadge";

const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textAreaClass = `${inputClass} min-h-24 py-2.5`;

function firstError(errors, key) {
  return Array.isArray(errors?.[key]) ? errors[key][0] : "";
}

function activeItems(masterLists, code) {
  return (masterLists.find((list) => list.code === code)?.items ?? []).filter(
    (item) => item.isActive && !item.isArchived,
  );
}

function quarterDates(year, quarter) {
  const starts = ["01-01", "04-01", "07-01", "10-01"];
  const ends = ["03-31", "06-30", "09-30", "12-31"];
  return {
    plannedStartDate: `${year}-${starts[quarter - 1]}`,
    plannedEndDate: `${year}-${ends[quarter - 1]}`,
  };
}

export default function IapPrioritizedEngagementForm({
  item,
  plan,
  masterLists,
  errors = {},
  formId = "iap-prioritized-engagement-form",
  onSubmit,
}) {
  const priorities = useMemo(
    () => activeItems(masterLists, "IAP_PLANNING_PRIORITY"),
    [masterLists],
  );
  const types = useMemo(
    () => activeItems(masterLists, "IAP_ENGAGEMENT_TYPE"),
    [masterLists],
  );
  const approaches = useMemo(
    () => activeItems(masterLists, "IAP_AUDIT_APPROACH"),
    [masterLists],
  );
  const defaultPriority =
    priorities.find((entry) =>
      item.riskLevelCode === "CRITICAL"
        ? entry.code === "IMMEDIATE"
        : entry.code === item.riskLevelCode,
    )?.id ?? "";
  const initialQuarter = Math.min(4, Math.max(1, Number(item.finalRank ?? 1)));
  const [form, setForm] = useState({
    engagementCode: `${plan.planCode}-${String(item.finalRank ?? 1).padStart(2, "0")}`,
    title: item.subjectName,
    engagementTypeId: "",
    auditApproachId:
      approaches.find((entry) => entry.code === "RISK_BASED")?.id ?? "",
    priorityId: defaultPriority,
    background: `Selected from ${plan.prioritizationRun.runCode} at rank ${item.finalRank} with a ${item.riskLevelLabel.toLowerCase()} residual-risk rating.`,
    objectives: "",
    scope: "",
    exclusions: "",
    auditCriteria: "",
    proposedMethodology: "",
    targetQuarter: initialQuarter,
    ...quarterDates(plan.fiscalYear, initialQuarter),
    estimatedPersonDays: "",
    estimatedCost: "",
    planningNotes: "",
  });

  function update(key, value) {
    setForm((current) => {
      const next = { ...current, [key]: value };
      if (key === "targetQuarter") {
        Object.assign(next, quarterDates(plan.fiscalYear, Number(value)));
      }
      return next;
    });
  }

  return (
    <form
      className="grid gap-5"
      id={formId}
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          ...form,
          prioritizationItemId: item.id,
          riskAssessmentId: null,
          officeIds: [],
          auditAreaIds: [],
          auditFocusIds: [],
          estimatedCost: form.estimatedCost || null,
          lockVersion: plan.lockVersion,
        });
      }}
    >
      <section className="rounded-xl border border-sky-100 bg-sky-50 p-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
              {item.subjectCode} · Priority rank {item.finalRank}
            </p>
            <h3 className="mt-1 text-base font-bold text-slate-900">
              {item.subjectName}
            </h3>
            <p className="mt-1 text-sm text-slate-600">
              {item.officeCode} — {item.officeName} · {item.auditAreaName}
            </p>
          </div>
          <StatusBadge
            tone={
              ["CRITICAL", "HIGH"].includes(item.riskLevelCode)
                ? "danger"
                : "warning"
            }
          >
            {item.riskLevelLabel} · {item.residualRiskScore}
          </StatusBadge>
        </div>
        <p className="mt-3 text-xs leading-5 text-slate-600">
          The subject, source assessment, risk scores, responsible office, and
          primary audit area will be carried into the plan and remain linked to
          this finalized prioritization decision.
        </p>
      </section>

      <div className="grid gap-4 sm:grid-cols-2">
        <FormField
          error={firstError(errors, "engagementCode")}
          htmlFor="engagement-code"
          label="Engagement code"
          required
        >
          <input
            className={inputClass}
            id="engagement-code"
            onChange={(event) => update("engagementCode", event.target.value)}
            value={form.engagementCode}
          />
        </FormField>
        <FormField
          error={firstError(errors, "title")}
          htmlFor="engagement-title"
          label="Engagement title"
          required
        >
          <input
            className={inputClass}
            id="engagement-title"
            onChange={(event) => update("title", event.target.value)}
            value={form.title}
          />
        </FormField>
        <FormField
          error={firstError(errors, "engagementTypeId")}
          label="Engagement type"
          required
        >
          <SearchableSelect
            onChange={(value) => update("engagementTypeId", value)}
            options={types.map((entry) => ({
              value: entry.id,
              label: entry.label,
              keywords: entry.code,
            }))}
            placeholder="Select engagement type..."
            value={form.engagementTypeId}
          />
        </FormField>
        <FormField
          error={firstError(errors, "auditApproachId")}
          label="Audit approach"
          required
        >
          <SearchableSelect
            onChange={(value) => update("auditApproachId", value)}
            options={approaches.map((entry) => ({
              value: entry.id,
              label: entry.label,
              keywords: entry.code,
            }))}
            placeholder="Select audit approach..."
            value={form.auditApproachId}
          />
        </FormField>
        <FormField
          error={firstError(errors, "priorityId")}
          label="Planning priority"
          required
        >
          <SearchableSelect
            onChange={(value) => update("priorityId", value)}
            options={priorities.map((entry) => ({
              value: entry.id,
              label: entry.label,
              keywords: entry.code,
            }))}
            placeholder="Select priority..."
            value={form.priorityId}
          />
        </FormField>
        <FormField
          error={firstError(errors, "targetQuarter")}
          label="Target quarter"
          required
        >
          <SearchableSelect
            onChange={(value) => update("targetQuarter", Number(value))}
            options={[1, 2, 3, 4].map((quarter) => ({
              value: quarter,
              label: `Quarter ${quarter}`,
            }))}
            value={form.targetQuarter}
          />
        </FormField>
      </div>

      <FormField
        error={firstError(errors, "background")}
        htmlFor="engagement-background"
        label="Background"
      >
        <textarea
          className={textAreaClass}
          id="engagement-background"
          onChange={(event) => update("background", event.target.value)}
          value={form.background}
        />
      </FormField>
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField
          error={firstError(errors, "objectives")}
          htmlFor="engagement-objectives"
          label="Planned objectives"
          required
        >
          <textarea
            className={textAreaClass}
            id="engagement-objectives"
            onChange={(event) => update("objectives", event.target.value)}
            value={form.objectives}
          />
        </FormField>
        <FormField
          error={firstError(errors, "scope")}
          htmlFor="engagement-scope"
          label="Preliminary scope"
          required
        >
          <textarea
            className={textAreaClass}
            id="engagement-scope"
            onChange={(event) => update("scope", event.target.value)}
            value={form.scope}
          />
        </FormField>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <FormField
          error={firstError(errors, "plannedStartDate")}
          htmlFor="engagement-start"
          label="Planned start"
          required
        >
          <input
            className={inputClass}
            id="engagement-start"
            onChange={(event) => update("plannedStartDate", event.target.value)}
            type="date"
            value={form.plannedStartDate}
          />
        </FormField>
        <FormField
          error={firstError(errors, "plannedEndDate")}
          htmlFor="engagement-end"
          label="Planned end"
          required
        >
          <input
            className={inputClass}
            id="engagement-end"
            onChange={(event) => update("plannedEndDate", event.target.value)}
            type="date"
            value={form.plannedEndDate}
          />
        </FormField>
        <FormField
          error={firstError(errors, "estimatedPersonDays")}
          htmlFor="engagement-person-days"
          label="Estimated person-days"
          required
        >
          <input
            className={inputClass}
            id="engagement-person-days"
            min="0.5"
            onChange={(event) =>
              update("estimatedPersonDays", event.target.value)
            }
            step="0.5"
            type="number"
            value={form.estimatedPersonDays}
          />
        </FormField>
      </div>

      <details className="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <summary className="cursor-pointer text-sm font-bold text-slate-700">
          Additional planning details
        </summary>
        <div className="mt-4 grid gap-4">
          {[
            ["exclusions", "Known exclusions"],
            ["auditCriteria", "Preliminary audit criteria"],
            ["proposedMethodology", "Proposed methodology"],
            ["planningNotes", "Planning notes"],
          ].map(([key, label]) => (
            <FormField
              error={firstError(errors, key)}
              htmlFor={`engagement-${key}`}
              key={key}
              label={label}
            >
              <textarea
                className={textAreaClass}
                id={`engagement-${key}`}
                onChange={(event) => update(key, event.target.value)}
                value={form[key]}
              />
            </FormField>
          ))}
          <FormField
            error={firstError(errors, "estimatedCost")}
            htmlFor="engagement-cost"
            label="Estimated cost"
          >
            <input
              className={inputClass}
              id="engagement-cost"
              min="0"
              onChange={(event) => update("estimatedCost", event.target.value)}
              step="0.01"
              type="number"
              value={form.estimatedCost}
            />
          </FormField>
        </div>
      </details>
    </form>
  );
}
