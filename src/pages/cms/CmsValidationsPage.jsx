import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  ClipboardCheck,
  Download,
  FilePenLine,
  History,
  Plus,
  RefreshCw,
  RotateCcw,
  Save,
  Send,
  ShieldCheck,
  Trash2,
  UserRoundPlus,
} from "lucide-react";
import { Link, useNavigate, useParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import {
  CmsValidationConclusionBadge,
  CmsValidationStatusBadge,
  CmsStatusBadge,
} from "../../components/cms/CmsBadges";
import { labelFor } from "../../components/cms/cms-format";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { hasPermission } from "../../config/navigation";
import { ApiError, cmsApi, documentApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const textareaClass = `${inputClass} min-h-24`;
const conclusions = [
  "NOT_IMPLEMENTED",
  "PARTIALLY_IMPLEMENTED",
  "IMPLEMENTED",
  "INADEQUATE_BASIS",
];
const itemConclusions = [
  "SATISFIED",
  "PARTIALLY_SATISFIED",
  "NOT_SATISFIED",
  "INADEQUATE_BASIS",
  "NOT_APPLICABLE",
];
const relevance = [
  "RELEVANT",
  "PARTIALLY_RELEVANT",
  "NOT_RELEVANT",
  "NOT_ASSESSED",
];
const reliability = [
  "RELIABLE",
  "LIMITED_RELIABILITY",
  "UNRELIABLE",
  "NOT_ASSESSED",
];
const sufficiency = [
  "SUFFICIENT",
  "PARTIALLY_SUFFICIENT",
  "INSUFFICIENT",
  "NOT_ASSESSED",
];

function displayDate(value, time = false) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    ...(time ? { timeStyle: "short" } : {}),
  }).format(date);
}

function firstError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

function valueOrFallback(value) {
  return value === null || value === undefined || value === ""
    ? "Not available"
    : value;
}

function ContextPanel({ context, recommendation }) {
  const source = context || recommendation || {};
  return (
    <section className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
            {source.cmsRecommendationCode ||
              source.recommendationCode ||
              "CMS recommendation"}
          </p>
          <p className="mt-2 max-w-4xl whitespace-pre-wrap text-sm leading-6 text-slate-800">
            {source.recommendation ||
              recommendation?.recommendation ||
              "Recommendation wording is unavailable."}
          </p>
        </div>
        <CmsStatusBadge status={source.status || recommendation?.status} />
      </div>
      <dl className="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <ContextDatum
          label="Responsible office"
          value={
            source.responsibleOffice?.name ||
            recommendation?.responsibleOffice?.name
          }
        />
        <ContextDatum
          label="Effective target date"
          value={displayDate(
            source.effectiveTargetDate || recommendation?.effectiveTargetDate,
          )}
        />
        <ContextDatum
          label="Compliance Monitor"
          value={
            source.activeComplianceMonitor?.name ||
            source.currentMonitor?.name ||
            recommendation?.currentMonitor?.user?.name ||
            "Unassigned"
          }
        />
        <ContextDatum
          label="Case status"
          value={labelFor(
            source.caseStatus || source.status || recommendation?.status,
          )}
        />
      </dl>
    </section>
  );
}

function ContextDatum({ label, value }) {
  return (
    <div>
      <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">
        {label}
      </dt>
      <dd className="mt-1 break-words text-slate-800">
        {valueOrFallback(value)}
      </dd>
    </div>
  );
}

function Skeleton({ label = "Loading independent validation" }) {
  return (
    <div aria-label={label} className="grid gap-4">
      <div className="h-24 animate-pulse rounded-xl bg-slate-200" />
      <div className="h-48 animate-pulse rounded-xl bg-slate-200" />
      <div className="h-72 animate-pulse rounded-xl bg-slate-200" />
    </div>
  );
}

function ErrorState({ message, onRetry }) {
  return (
    <div className="rounded-2xl border border-red-200 bg-red-50 px-6 py-14 text-center">
      <AlertTriangle className="mx-auto text-red-600" size={36} />
      <h2 className="mt-3 text-xl font-bold text-slate-800">
        Independent Validation unavailable
      </h2>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">
        {message}
      </p>
      <button
        className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white"
        onClick={onRetry}
        type="button"
      >
        <RefreshCw size={16} /> Retry
      </button>
    </div>
  );
}

function ValidationCard({ validation, onOpen, sourceProgress }) {
  const version = validation.currentVersion;
  const finalized = validation.finalizedVersion;
  const source = validation.sourceContext || {};
  return (
    <button
      className="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-sky-600"
      onClick={onOpen}
      type="button"
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
            {validation.displayCode}
          </p>
          <h3 className="mt-1 text-base font-bold text-slate-800">
            Validation sequence {validation.validationSequence}
          </h3>
        </div>
        <CmsValidationStatusBadge status={version?.status} />
      </div>
      <dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <dt className="font-bold text-slate-500">Source Progress Update</dt>
          <dd className="mt-1">
            {sourceProgress
              ? `${sourceProgress.displayCode} · ${displayDate(sourceProgress.reportingPeriodStart)} – ${displayDate(sourceProgress.reportingPeriodEnd)}`
              : source.recordedProgressUpdateVersionId ||
                validation.recordedProgressUpdateVersionId ||
                "Exact version pinned"}
          </dd>
        </div>
        <div>
          <dt className="font-bold text-slate-500">Baseline</dt>
          <dd className="mt-1">
            Version {validation.acceptedActionPlanVersionId || "Not available"}
          </dd>
        </div>
        <div>
          <dt className="font-bold text-slate-500">Primary Validator</dt>
          <dd className="mt-1">
            {validation.currentPrimaryValidator?.name || "Unassigned"}
          </dd>
        </div>
        <div>
          <dt className="font-bold text-slate-500">Validated completion</dt>
          <dd className="mt-1">
            {version?.validatedCompletionPercentage == null
              ? "Not recorded"
              : `${version.validatedCompletionPercentage}%`}
          </dd>
        </div>
      </dl>
      <div className="mt-4 flex flex-wrap items-center gap-2 text-xs">
        {version?.proposedConclusionCode && (
          <>
            <span className="font-semibold text-slate-500">Proposed</span>
            <CmsValidationConclusionBadge
              conclusion={version.proposedConclusionCode}
            />
          </>
        )}
        {finalized?.finalConclusionCode && (
          <>
            <span className="font-semibold text-slate-500">Final</span>
            <CmsValidationConclusionBadge
              conclusion={finalized.finalConclusionCode}
            />
          </>
        )}
        {version?.isCurrent && (
          <StatusBadge tone="info">Current version</StatusBadge>
        )}
        {version?.isHistorical && (
          <StatusBadge tone="inactive">Historical</StatusBadge>
        )}
        {finalized?.finalConclusionCode === "IMPLEMENTED" && (
          <StatusBadge tone="warning">Closure pending</StatusBadge>
        )}
      </div>
    </button>
  );
}

function CreateValidationDialog({
  open,
  busy,
  errors,
  progressUpdates,
  eligibleValidators,
  lockVersion,
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState({
    recordedProgressUpdateVersionId: "",
    validatorUserId: "",
    assignmentReason: "",
  });
  const recorded = progressUpdates.filter(
    (item) => item.recordedVersionId && item.recordedVersionNumber,
  );
  return (
    <Modal
      description="Create a review pinned to one recorded management Progress Update and an independently eligible Primary Validator."
      onClose={onClose}
      open={open}
      size="lg"
      title="Create Validation Review"
      footer={
        <>
          <button
            className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
            disabled={busy}
            onClick={onClose}
            type="button"
          >
            Cancel
          </button>
          <button
            className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
            disabled={
              busy ||
              !eligibleValidators.length ||
              !form.recordedProgressUpdateVersionId ||
              !form.validatorUserId ||
              !form.assignmentReason.trim()
            }
            onClick={() => onSubmit({ ...form, lockVersion })}
            type="button"
          >
            {busy ? "Creating..." : "Create and assign"}
          </button>
        </>
      }
    >
      <div className="grid gap-4">
        <div className="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm leading-6 text-sky-900">
          The validator must be independent from the responsible office, Action
          Plan and Progress Update preparation/recording, and current Compliance
          Monitor assignment. Laravel performs the final independence check.
        </div>
        <FormField
          error={firstError(errors, "recordedProgressUpdateVersionId")}
          htmlFor="validation-progress"
          label="Recorded Progress Update"
          required
        >
          <select
            className={inputClass}
            id="validation-progress"
            onChange={(e) =>
              setForm({
                ...form,
                recordedProgressUpdateVersionId: e.target.value,
              })
            }
            value={form.recordedProgressUpdateVersionId}
          >
            <option value="">Select an eligible recorded version</option>
            {recorded.map((item) => (
              <option
                key={item.recordedVersionId}
                value={item.recordedVersionId}
              >
                {item.displayCode} · {displayDate(item.reportingPeriodStart)} –{" "}
                {displayDate(item.reportingPeriodEnd)} ·{" "}
                {item.recordedVersion?.managementReportedOverallPercentage ??
                  item.recordedVersion
                    ?.systemCalculatedWeightedReportedPercentage ??
                  "Not reported"}
                % reported
              </option>
            ))}
          </select>
        </FormField>
        {recorded.length === 0 && (
          <p className="-mt-2 text-xs text-amber-700">
            No eligible recorded Progress Update is available in this authorized
            scope.
          </p>
        )}
        <FormField
          error={firstError(errors, "validatorUserId")}
          htmlFor="validation-validator"
          label="Primary Validator"
          required
        >
          <select
            className={inputClass}
            disabled={!eligibleValidators.length}
            id="validation-validator"
            onChange={(e) =>
              setForm({ ...form, validatorUserId: e.target.value })
            }
            value={form.validatorUserId}
          >
            <option value="">
              {eligibleValidators.length
                ? "Select an eligible validator"
                : "Eligible validator options are not supplied by CMS-5A"}
            </option>
            {eligibleValidators.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name} {item.employeeId ? `(${item.employeeId})` : ""}
              </option>
            ))}
          </select>
        </FormField>
        {!eligibleValidators.length && (
          <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
            No safe eligible-validator option is currently available from the
            scoped CMS validation-options endpoint. No unrestricted User
            Registry is loaded.
          </div>
        )}
        <FormField
          error={firstError(errors, "assignmentReason")}
          htmlFor="validation-assignment-reason"
          label="Assignment reason"
          required
        >
          <textarea
            className={textareaClass}
            id="validation-assignment-reason"
            maxLength={5000}
            onChange={(e) =>
              setForm({ ...form, assignmentReason: e.target.value })
            }
            value={form.assignmentReason}
          />
        </FormField>
        {Object.entries(errors || {})
          .filter(
            ([key]) =>
              ![
                "recordedProgressUpdateVersionId",
                "validatorUserId",
                "assignmentReason",
              ].includes(key),
          )
          .map(([key, value]) => (
            <p className="text-sm font-semibold text-red-700" key={key}>
              {Array.isArray(value) ? value[0] : value}
            </p>
          ))}
      </div>
    </Modal>
  );
}

function ReadOnlyField({ label, value, wide = false }) {
  return (
    <div
      className={`rounded-lg border border-slate-200 bg-slate-50 p-3 ${wide ? "sm:col-span-2" : ""}`}
    >
      <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">
        {label}
      </dt>
      <dd className="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">
        {valueOrFallback(value)}
      </dd>
    </div>
  );
}

function DraftForm({ form, setForm, errors, busy, onSave }) {
  const field = (key, label, wide = false) => (
    <FormField
      error={firstError(errors, key)}
      htmlFor={`validation-${key}`}
      label={label}
    >
      <textarea
        className={`${textareaClass} ${wide ? "min-h-32" : ""}`}
        disabled={busy}
        id={`validation-${key}`}
        onChange={(e) => setForm({ ...form, [key]: e.target.value })}
        value={form[key] ?? ""}
      />
    </FormField>
  );
  return (
    <div className="grid gap-4">
      <div className="grid gap-4 rounded-xl border border-sky-100 bg-sky-50 p-4 sm:grid-cols-2">
        {field("validationScope", "Validation scope", true)}
        {field("validationObjectives", "Validation objectives", true)}
        {field("methodologySummary", "Methodology summary", true)}
        {field("overallWorkPerformed", "Overall work performed", true)}
        {field("overallEvidenceSummary", "Overall evidence summary", true)}
        {field("limitations", "Limitations", true)}
        {field(
          "professionalJudgmentRationale",
          "Professional judgment rationale",
          true,
        )}
        <FormField
          error={firstError(errors, "proposedConclusionCode")}
          htmlFor="validation-proposed-conclusion"
          label="Proposed professional conclusion"
        >
          <select
            className={inputClass}
            disabled={busy}
            id="validation-proposed-conclusion"
            onChange={(e) =>
              setForm({ ...form, proposedConclusionCode: e.target.value })
            }
            value={form.proposedConclusionCode ?? ""}
          >
            <option value="">Select conclusion</option>
            {conclusions.map((code) => (
              <option key={code} value={code}>
                {labelFor(code)}
              </option>
            ))}
          </select>
        </FormField>
        <FormField
          error={firstError(errors, "validatedCompletionPercentage")}
          htmlFor="validation-completion"
          label="Validated completion percentage"
        >
          <input
            className={inputClass}
            disabled={busy}
            id="validation-completion"
            max="100"
            min="0"
            onChange={(e) =>
              setForm({
                ...form,
                validatedCompletionPercentage: e.target.value,
              })
            }
            step="0.01"
            type="number"
            value={form.validatedCompletionPercentage ?? ""}
          />
        </FormField>
      </div>
      <div className="flex justify-end">
        <button
          className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60"
          disabled={busy}
          onClick={onSave}
          type="button"
        >
          <Save size={16} /> {busy ? "Saving..." : "Save draft"}
        </button>
      </div>
    </div>
  );
}

function ItemsPanel({ form, setForm, errors, readOnly }) {
  const items = form.validationItems || [];
  function update(index, key, value) {
    setForm({
      ...form,
      validationItems: items.map((item, i) =>
        i === index ? { ...item, [key]: value } : item,
      ),
    });
  }
  function move(index, direction) {
    const next = [...items];
    const target = index + direction;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    setForm({
      ...form,
      validationItems: next.map((item, i) => ({
        ...item,
        sequenceNumber: i + 1,
        displayOrder: i + 1,
      })),
    });
  }
  return (
    <section className="grid gap-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-lg font-bold text-slate-800">
            Procedures & Conclusions
          </h3>
          <p className="mt-1 text-sm text-slate-500">
            Action Plan milestone links and management-reported context are
            read-only. Only the professional validation fields are editable in a
            draft.
          </p>
        </div>
        {!readOnly && (
          <button
            className="inline-flex h-9 items-center gap-2 rounded-lg border border-sky-300 bg-white px-3 text-xs font-bold text-sky-800"
            onClick={() =>
              setForm({
                ...form,
                validationItems: [
                  ...items,
                  {
                    scopeCode: "RECOMMENDATION",
                    sequenceNumber: items.length + 1,
                    displayOrder: items.length + 1,
                    criterion: "",
                    procedurePerformed: "",
                    populationOrSource: "",
                    sampleDescription: "",
                    resultSummary: "",
                    exceptionSummary: "",
                    itemConclusionCode: "",
                    validatedMilestonePercentage: "",
                    followUpRequired: false,
                  },
                ],
              })
            }
            type="button"
          >
            <Plus size={14} /> Add item
          </button>
        )}
      </div>
      {items.length === 0 && (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
          No Validation Items returned by the backend.
        </div>
      )}
      {items.map((item, index) => (
        <article
          className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
          key={item.id || `new-${index}`}
        >
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
                Item {item.sequenceNumber || index + 1} ·{" "}
                {labelFor(item.scopeCode)}
              </p>
              <p className="mt-1 text-sm text-slate-600">
                {item.milestone?.title ||
                  (item.actionPlanMilestoneId
                    ? `Action Plan milestone #${item.actionPlanMilestoneId}`
                    : "Recommendation-level procedure")}
              </p>
            </div>
            {!readOnly && (
              <div className="flex gap-1">
                <button
                  aria-label={`Move item ${index + 1} up`}
                  className="rounded border px-2 py-1 text-xs"
                  disabled={index === 0}
                  onClick={() => move(index, -1)}
                  type="button"
                >
                  ↑
                </button>
                <button
                  aria-label={`Move item ${index + 1} down`}
                  className="rounded border px-2 py-1 text-xs"
                  disabled={index === items.length - 1}
                  onClick={() => move(index, 1)}
                  type="button"
                >
                  ↓
                </button>
              </div>
            )}
          </div>
          <dl className="mt-4 grid gap-3 sm:grid-cols-2">
            <ReadOnlyField
              label="Management milestone context"
              value={
                item.milestone?.description ||
                item.milestone?.managementReportedPercentage != null
                  ? `${item.milestone?.description || "Milestone"} · ${item.milestone?.managementReportedPercentage ?? "Not reported"}% reported`
                  : "Pinned Action Plan milestone"
              }
              wide
            />
          </dl>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {[
              ["criterion", "Criterion"],
              ["procedurePerformed", "Procedure performed"],
              ["populationOrSource", "Population or source"],
              ["sampleDescription", "Sample description"],
              ["resultSummary", "Result summary"],
              ["exceptionSummary", "Exception summary"],
            ].map(([key, label]) => (
              <FormField
                error={firstError(errors, `validationItems.${index}.${key}`)}
                htmlFor={`validation-item-${index}-${key}`}
                key={key}
                label={label}
              >
                <textarea
                  className={textareaClass}
                  disabled={readOnly}
                  id={`validation-item-${index}-${key}`}
                  onChange={(e) => update(index, key, e.target.value)}
                  value={item[key] ?? ""}
                />
              </FormField>
            ))}
            <FormField
              error={firstError(
                errors,
                `validationItems.${index}.itemConclusionCode`,
              )}
              htmlFor={`validation-item-${index}-conclusion`}
              label="Item conclusion"
            >
              <select
                className={inputClass}
                disabled={readOnly}
                id={`validation-item-${index}-conclusion`}
                onChange={(e) =>
                  update(index, "itemConclusionCode", e.target.value)
                }
                value={item.itemConclusionCode ?? ""}
              >
                <option value="">Select item conclusion</option>
                {itemConclusions.map((code) => (
                  <option key={code} value={code}>
                    {labelFor(code)}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField
              error={firstError(
                errors,
                `validationItems.${index}.validatedMilestonePercentage`,
              )}
              htmlFor={`validation-item-${index}-percentage`}
              label="Validated milestone percentage"
            >
              <input
                className={inputClass}
                disabled={readOnly}
                id={`validation-item-${index}-percentage`}
                max="100"
                min="0"
                onChange={(e) =>
                  update(index, "validatedMilestonePercentage", e.target.value)
                }
                type="number"
                value={item.validatedMilestonePercentage ?? ""}
              />
            </FormField>
            <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
              <input
                checked={Boolean(item.followUpRequired)}
                disabled={readOnly}
                onChange={(e) =>
                  update(index, "followUpRequired", e.target.checked)
                }
                type="checkbox"
              />{" "}
              Follow-up required
            </label>
          </div>
        </article>
      ))}
    </section>
  );
}

function AssessmentsPanel({ form, setForm, errors, readOnly }) {
  const assessments = form.evidenceAssessments || [];
  function update(index, key, value) {
    setForm({
      ...form,
      evidenceAssessments: assessments.map((item, i) =>
        i === index ? { ...item, [key]: value } : item,
      ),
    });
  }
  return (
    <section className="grid gap-3">
      <div>
        <h3 className="text-lg font-bold text-slate-800">
          Management Evidence Assessment
        </h3>
        <p className="mt-1 text-sm text-slate-500">
          Assessments annotate the exact recorded Progress Update evidence; they
          never modify management’s evidence link.
        </p>
      </div>
      {assessments.length === 0 && (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
          No management evidence assessments were returned.
        </div>
      )}
      {assessments.map((item, index) => (
        <article
          className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
          key={
            item.id ||
            item.progressEvidenceLinkId ||
            item.validationEvidenceLinkId ||
            index
          }
        >
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="font-bold text-slate-800">
                {item.managementEvidence?.title ||
                  item.evidenceTitle ||
                  `Evidence assessment ${index + 1}`}
              </p>
              <p className="mt-1 text-xs text-slate-500">
                {item.managementEvidence?.fileName ||
                  "Exact document version pinned"}{" "}
                ·{" "}
                {item.managementEvidence?.checksumSha256
                  ? `${item.managementEvidence.checksumSha256.slice(0, 16)}…`
                  : "Checksum protected"}
              </p>
            </div>
            <StatusBadge tone="info">
              {labelFor(item.evidenceSourceCode)}
            </StatusBadge>
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-3">
            {[
              ["relevanceCode", "Relevance", relevance],
              ["reliabilityCode", "Reliability", reliability],
              ["sufficiencyCode", "Sufficiency", sufficiency],
            ].map(([key, label, options]) => (
              <FormField
                error={firstError(
                  errors,
                  `evidenceAssessments.${index}.${key}`,
                )}
                htmlFor={`validation-assessment-${index}-${key}`}
                key={key}
                label={label}
              >
                <select
                  className={inputClass}
                  disabled={readOnly}
                  id={`validation-assessment-${index}-${key}`}
                  onChange={(e) => update(index, key, e.target.value)}
                  value={item[key] ?? "NOT_ASSESSED"}
                >
                  {options.map((code) => (
                    <option key={code} value={code}>
                      {labelFor(code)}
                    </option>
                  ))}
                </select>
              </FormField>
            ))}
          </div>
          <label className="mt-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
            <input
              checked={Boolean(item.reliedUpon)}
              disabled={readOnly}
              onChange={(e) => update(index, "reliedUpon", e.target.checked)}
              type="checkbox"
            />{" "}
            Relied upon in professional conclusion
          </label>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <FormField
              error={firstError(
                errors,
                `evidenceAssessments.${index}.assessmentSummary`,
              )}
              htmlFor={`validation-assessment-${index}-summary`}
              label="Assessment summary"
            >
              <textarea
                className={textareaClass}
                disabled={readOnly}
                id={`validation-assessment-${index}-summary`}
                onChange={(e) =>
                  update(index, "assessmentSummary", e.target.value)
                }
                value={item.assessmentSummary ?? ""}
              />
            </FormField>
            <FormField
              error={firstError(
                errors,
                `evidenceAssessments.${index}.limitationSummary`,
              )}
              htmlFor={`validation-assessment-${index}-limitation`}
              label="Limitation summary"
            >
              <textarea
                className={textareaClass}
                disabled={readOnly}
                id={`validation-assessment-${index}-limitation`}
                onChange={(e) =>
                  update(index, "limitationSummary", e.target.value)
                }
                value={item.limitationSummary ?? ""}
              />
            </FormField>
          </div>
        </article>
      ))}
    </section>
  );
}

function ValidatorEvidencePanel({
  version,
  confidentialityLevels,
  form,
  canUpload,
  onUpload,
  onDownload,
  onRemove,
  busy,
}) {
  const [upload, setUpload] = useState({
    evidenceCategory: "VALIDATION_SUPPORT",
    title: "",
    description: "",
    sourceOrCustodian: "",
    confidentialityLevelId: "",
    validationItemId: "",
    file: null,
  });
  const evidence = version?.validatorEvidence || [];
  return (
    <section className="grid gap-4">
      <div>
        <h3 className="text-lg font-bold text-slate-800">Validator Evidence</h3>
        <p className="mt-1 text-sm text-slate-500">
          Validator-obtained evidence is linked to exact Core Document Versions
          and remains protected by authenticated download.
        </p>
      </div>
      {canUpload && (
        <div className="rounded-xl border border-sky-100 bg-sky-50 p-4">
          <div className="grid gap-3 sm:grid-cols-2">
            <FormField
              htmlFor="validation-evidence-title"
              label="Title"
              required
            >
              <input
                className={inputClass}
                id="validation-evidence-title"
                onChange={(e) =>
                  setUpload({ ...upload, title: e.target.value })
                }
                value={upload.title}
              />
            </FormField>
            <FormField
              htmlFor="validation-evidence-category"
              label="Category"
              required
            >
              <input
                className={inputClass}
                id="validation-evidence-category"
                onChange={(e) =>
                  setUpload({ ...upload, evidenceCategory: e.target.value })
                }
                value={upload.evidenceCategory}
              />
            </FormField>
            <FormField
              htmlFor="validation-evidence-source"
              label="Source or custodian"
            >
              <input
                className={inputClass}
                id="validation-evidence-source"
                onChange={(e) =>
                  setUpload({ ...upload, sourceOrCustodian: e.target.value })
                }
                value={upload.sourceOrCustodian}
              />
            </FormField>
            <FormField
              htmlFor="validation-evidence-confidentiality"
              label="Confidentiality"
              required
            >
              <select
                className={inputClass}
                id="validation-evidence-confidentiality"
                onChange={(e) =>
                  setUpload({
                    ...upload,
                    confidentialityLevelId: e.target.value,
                  })
                }
                value={upload.confidentialityLevelId}
              >
                <option value="">Select confidentiality</option>
                {confidentialityLevels.map((level) => (
                  <option key={level.id} value={level.id}>
                    {level.label || level.name || level.code}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField
              htmlFor="validation-evidence-description"
              label="Description"
            >
              <textarea
                className={textareaClass}
                id="validation-evidence-description"
                onChange={(e) =>
                  setUpload({ ...upload, description: e.target.value })
                }
                value={upload.description}
              />
            </FormField>
            <FormField
              htmlFor="validation-evidence-item"
              label="Link to Validation Item"
            >
              <select
                className={inputClass}
                id="validation-evidence-item"
                onChange={(e) =>
                  setUpload({ ...upload, validationItemId: e.target.value })
                }
                value={upload.validationItemId}
              >
                <option value="">Overall validation</option>
                {(form.validationItems || []).map((item, index) => (
                  <option key={item.id || index} value={item.id || ""}>
                    Item {item.sequenceNumber || index + 1}
                  </option>
                ))}
              </select>
            </FormField>
          </div>
          <div className="mt-3 flex flex-wrap items-center gap-3">
            <input
              aria-label="Validator evidence file"
              onChange={(e) =>
                setUpload({ ...upload, file: e.target.files?.[0] || null })
              }
              type="file"
            />
            <button
              className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60"
              disabled={
                busy ||
                !upload.title.trim() ||
                !upload.file ||
                !upload.confidentialityLevelId
              }
              onClick={() =>
                onUpload(upload, () =>
                  setUpload({
                    evidenceCategory: "VALIDATION_SUPPORT",
                    title: "",
                    description: "",
                    sourceOrCustodian: "",
                    confidentialityLevelId: "",
                    validationItemId: "",
                    file: null,
                  }),
                )
              }
              type="button"
            >
              <Plus size={16} /> Upload evidence
            </button>
          </div>
        </div>
      )}
      {evidence.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
          No validator-obtained evidence is linked.
        </div>
      ) : (
        <div className="grid gap-3">
          {evidence.map((item) => (
            <article
              className="rounded-xl border border-slate-200 bg-white p-4"
              key={item.id}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h4 className="font-bold text-slate-800">{item.title}</h4>
                  <p className="mt-1 text-xs text-slate-500">
                    {item.file?.name || item.fileName || "Document version"} ·{" "}
                    {item.file?.mimeType || item.mimeType || "MIME protected"}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <button
                    className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700"
                    onClick={() => onDownload(item)}
                    type="button"
                  >
                    <Download size={14} /> Download
                  </button>
                  {canUpload && (
                    <button
                      className="inline-flex h-9 items-center gap-2 rounded-lg border border-red-300 bg-white px-3 text-xs font-bold text-red-700"
                      onClick={() => onRemove(item)}
                      type="button"
                    >
                      <Trash2 size={14} /> Remove link
                    </button>
                  )}
                </div>
              </div>
              <dl className="mt-3 grid gap-3 text-xs text-slate-600 sm:grid-cols-3">
                <div>
                  <dt className="font-bold text-slate-500">Checksum</dt>
                  <dd className="mt-1 break-all font-mono">
                    {item.checksumSha256 || "Protected"}
                  </dd>
                </div>
                <div>
                  <dt className="font-bold text-slate-500">Confidentiality</dt>
                  <dd className="mt-1">
                    {item.confidentiality?.label ||
                      item.confidentiality?.code ||
                      "Restricted"}
                  </dd>
                </div>
                <div>
                  <dt className="font-bold text-slate-500">Linked</dt>
                  <dd className="mt-1">
                    {item.linkedBy?.name || "Validator"} ·{" "}
                    {displayDate(item.linkedAt, true)}
                  </dd>
                </div>
              </dl>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}

function AssignmentsPanel({
  assignments,
  canAssign,
  eligibleValidators,
  onAssign,
  onEnd,
}) {
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({
    validatorUserId: "",
    assignmentReason: "",
  });
  return (
    <section>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-lg font-bold text-slate-800">
            Validator assignments
          </h3>
          <p className="mt-1 text-sm text-slate-500">
            Replacement preserves prior Primary Validator history. Assignment
            never grants supervisory finalization authority.
          </p>
        </div>
        {canAssign && (
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
            onClick={() => setOpen(true)}
            type="button"
          >
            <UserRoundPlus size={16} /> Replace validator
          </button>
        )}
      </div>
      {open && (
        <div className="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4">
          <div className="grid gap-3 sm:grid-cols-2">
            <FormField
              htmlFor="validation-replacement-validator"
              label="Replacement validator"
              required
            >
              <select
                className={inputClass}
                id="validation-replacement-validator"
                onChange={(e) =>
                  setForm({ ...form, validatorUserId: e.target.value })
                }
                value={form.validatorUserId}
              >
                <option value="">Select eligible validator</option>
                {eligibleValidators.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField
              htmlFor="validation-replacement-reason"
              label="Reason"
              required
            >
              <textarea
                className={textareaClass}
                id="validation-replacement-reason"
                onChange={(e) =>
                  setForm({ ...form, assignmentReason: e.target.value })
                }
                value={form.assignmentReason}
              />
            </FormField>
          </div>
          <div className="mt-3 flex gap-2">
            <button
              className="h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold"
              onClick={() => setOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-9 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white disabled:opacity-60"
              disabled={!form.validatorUserId || !form.assignmentReason.trim()}
              onClick={() => {
                onAssign(form);
                setOpen(false);
              }}
              type="button"
            >
              Confirm replacement
            </button>
          </div>
          {!eligibleValidators.length && (
            <p className="mt-2 text-xs text-amber-700">
              No safe eligible-validator option is available from the scoped
              validation-options endpoint.
            </p>
          )}
        </div>
      )}
      <div className="mt-4 grid gap-3 lg:grid-cols-2">
        {assignments.length === 0 ? (
          <p className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
            No assignment history was returned.
          </p>
        ) : (
          assignments.map((item) => (
            <article
              className="rounded-xl border border-slate-200 bg-white p-4"
              key={item.id}
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h4 className="font-bold text-slate-800">
                    {item.user?.name || "Unknown user"}
                  </h4>
                  <p className="text-xs text-slate-500">
                    {item.user?.employeeId || "Employee ID unavailable"}
                  </p>
                </div>
                <StatusBadge tone={item.isCurrent ? "success" : "inactive"}>
                  {item.isCurrent ? "Current" : "Ended"}
                </StatusBadge>
              </div>
              <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-xs font-semibold text-slate-500">
                    Assigned
                  </dt>
                  <dd className="mt-1">{displayDate(item.assignedAt, true)}</dd>
                </div>
                <div>
                  <dt className="text-xs font-semibold text-slate-500">
                    Assigned by
                  </dt>
                  <dd className="mt-1">{item.assignedBy?.name || "System"}</dd>
                </div>
                <div className="sm:col-span-2">
                  <dt className="text-xs font-semibold text-slate-500">
                    Reason
                  </dt>
                  <dd className="mt-1 whitespace-pre-wrap">
                    {item.assignmentReason || "No reason recorded"}
                  </dd>
                </div>
                {!item.isCurrent && (
                  <div className="sm:col-span-2">
                    <dt className="text-xs font-semibold text-slate-500">
                      End reason
                    </dt>
                    <dd className="mt-1 whitespace-pre-wrap">
                      {item.endReason || "No reason recorded"}
                    </dd>
                  </div>
                )}
              </dl>
              {item.isCurrent && canAssign && (
                <button
                  className="mt-3 text-xs font-bold text-red-700"
                  onClick={() => onEnd(item)}
                  type="button"
                >
                  End assignment
                </button>
              )}
            </article>
          ))
        )}
      </div>
    </section>
  );
}

function VersionHistory({ versions, selectedId, onSelect }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="flex items-center gap-2">
        <History className="text-sky-700" size={18} />
        <h3 className="font-bold text-slate-800">Versions & History</h3>
      </div>
      <p className="mt-1 text-xs leading-5 text-slate-500">
        Submitted, returned, and finalized versions remain immutable. Returned
        work requires a new draft revision.
      </p>
      <div className="mt-4 grid gap-2">
        {versions.map((version) => (
          <button
            className={`rounded-lg border p-3 text-left ${selectedId === version.id ? "border-sky-400 bg-sky-50" : "border-slate-200 hover:border-sky-300"}`}
            key={version.id}
            onClick={() => onSelect(version.id)}
            type="button"
          >
            <div className="flex flex-wrap items-center justify-between gap-2">
              <strong className="text-sm text-slate-800">
                Version {version.versionNumber}
              </strong>
              <CmsValidationStatusBadge status={version.status} />
            </div>
            <p className="mt-1 text-xs text-slate-500">
              {displayDate(version.createdAt, true)}
            </p>
            <div className="mt-2 flex flex-wrap gap-1">
              {version.isCurrent && (
                <StatusBadge tone="info">Current</StatusBadge>
              )}
              {version.isFinalizedCurrent && (
                <StatusBadge tone="success">Finalized</StatusBadge>
              )}
              {version.isHistorical && (
                <StatusBadge tone="inactive">Historical</StatusBadge>
              )}
            </div>
          </button>
        ))}
      </div>
    </section>
  );
}

function WorkflowDialog({
  action,
  version,
  busy,
  errors,
  comment,
  confirmed,
  onClose,
  onConfirm,
  setComment,
  setConfirmed,
  finalConclusion,
  setFinalConclusion,
  overrideReason,
  setOverrideReason,
}) {
  if (!action) return null;
  const copy = {
    submit: [
      "Submit Validation",
      "Submission makes the version, procedures, assessments, and evidence links immutable.",
    ],
    "start-review": [
      "Start Supervisory Review",
      "The submitted validator work remains immutable while a separate supervisor reviews it.",
    ],
    return: [
      "Return Validation",
      "Returned work remains immutable. Corrections require a new draft revision.",
    ],
    revise: [
      "Create Validation Revision",
      "The returned version stays immutable and the backend copies its pinned content into a new draft.",
    ],
    finalize: [
      "Finalize Professional Conclusion",
      "Finalization records the supervisory conclusion and updates the recommendation case status. Closure remains a separate later workflow.",
    ],
  }[action];
  const requiredComment = ["return", "revise", "finalize"].includes(action);
  const overrideRequired =
    action === "finalize" &&
    finalConclusion &&
    finalConclusion !== version?.proposedConclusionCode;
  return (
    <Modal
      description={copy[1]}
      onClose={onClose}
      open
      size="lg"
      title={copy[0]}
      footer={
        <>
          <button
            className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
            disabled={busy}
            onClick={onClose}
            type="button"
          >
            Cancel
          </button>
          <button
            className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
            disabled={busy}
            onClick={onConfirm}
            type="button"
          >
            {busy ? "Please wait..." : copy[0]}
          </button>
        </>
      }
    >
      <div className="grid gap-4">
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
          Version {version?.versionNumber} · Proposed{" "}
          {labelFor(version?.proposedConclusionCode, "Not selected")} ·
          Validated completion{" "}
          {version?.validatedCompletionPercentage ?? "Not recorded"}%
        </div>
        {action === "finalize" && (
          <>
            <FormField
              error={firstError(errors, "finalConclusionCode")}
              htmlFor="validation-final-conclusion"
              label="Final professional conclusion"
              required
            >
              <select
                className={inputClass}
                id="validation-final-conclusion"
                onChange={(e) => setFinalConclusion(e.target.value)}
                value={finalConclusion}
              >
                <option value="">Select final conclusion</option>
                {conclusions.map((code) => (
                  <option key={code} value={code}>
                    {labelFor(code)}
                  </option>
                ))}
              </select>
            </FormField>
            {finalConclusion === "IMPLEMENTED" && (
              <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm leading-6 text-emerald-900">
                This finalizes independent validation as implemented. The
                recommendation remains open until a separate closure workflow is
                completed.
              </div>
            )}
            {finalConclusion && (
              <p className="text-xs text-slate-600">
                Case transition preview:{" "}
                {finalConclusion === "IMPLEMENTED"
                  ? "IMPLEMENTED"
                  : finalConclusion === "PARTIALLY_IMPLEMENTED"
                    ? "PARTIALLY_IMPLEMENTED"
                    : "MONITORING"}
                .
              </p>
            )}
            {overrideRequired && (
              <FormField
                error={firstError(errors, "overrideReason")}
                htmlFor="validation-override-reason"
                label="Supervisory override reason"
                required
              >
                <textarea
                  className={textareaClass}
                  id="validation-override-reason"
                  onChange={(e) => setOverrideReason(e.target.value)}
                  value={overrideReason}
                />
              </FormField>
            )}
          </>
        )}
        {(requiredComment || action === "start-review") && (
          <FormField
            error={firstError(
              errors,
              action === "return"
                ? "returnReason"
                : action === "revise"
                  ? "revisionReason"
                  : action === "finalize"
                    ? "finalizationComment"
                    : "reviewComment",
            )}
            htmlFor="validation-workflow-comment"
            label={
              action === "return"
                ? "Return reason"
                : action === "revise"
                  ? "Revision reason"
                  : action === "finalize"
                    ? "Finalization comment"
                    : "Review comment"
            }
            required={requiredComment}
          >
            <textarea
              className={textareaClass}
              id="validation-workflow-comment"
              onChange={(e) => setComment(e.target.value)}
              value={comment}
            />
          </FormField>
        )}
        {["submit", "finalize"].includes(action) && (
          <label className="flex items-start gap-2 text-sm text-slate-700">
            <input
              checked={confirmed}
              className="mt-1"
              onChange={(e) => setConfirmed(e.target.checked)}
              type="checkbox"
            />
            <span>
              I confirm this professional validation action and understand that
              it does not create or approve closure.
            </span>
          </label>
        )}
        {errors &&
          Object.keys(errors)
            .filter(
              (key) =>
                ![
                  "returnReason",
                  "revisionReason",
                  "finalizationComment",
                  "reviewComment",
                  "overrideReason",
                  "finalConclusionCode",
                ].includes(key),
            )
            .map((key) => (
              <p className="text-sm font-semibold text-red-700" key={key}>
                {firstError(errors, key)}
              </p>
            ))}
      </div>
    </Modal>
  );
}

export default function CmsValidationsPage() {
  const { recommendationId, validationId } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const { user } = useAuth();
  const detailMode = Boolean(validationId);
  const canCreate = hasPermission(user, "cms.validation.create");
  const canAssign = hasPermission(user, "cms.validation.assign");
  const [recommendation, setRecommendation] = useState(null);
  const [listData, setListData] = useState(null);
  const [validation, setValidation] = useState(null);
  const [validationOptions, setValidationOptions] = useState(null);
  const [assignments, setAssignments] = useState([]);
  const [confidentialityLevels, setConfidentialityLevels] = useState([]);
  const [progressUpdates, setProgressUpdates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);
  const [conflict, setConflict] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [createErrors, setCreateErrors] = useState({});
  const [selectedVersionId, setSelectedVersionId] = useState(null);
  const [tab, setTab] = useState("overview");
  const [form, setForm] = useState(null);
  const [formErrors, setFormErrors] = useState({});
  const [workflow, setWorkflow] = useState("");
  const [workflowComment, setWorkflowComment] = useState("");
  const [workflowErrors, setWorkflowErrors] = useState({});
  const [workflowConfirmed, setWorkflowConfirmed] = useState(false);
  const [finalConclusion, setFinalConclusion] = useState("");
  const [overrideReason, setOverrideReason] = useState("");

  const loadList = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [recommendationResult, validationsResult, optionsResult] =
        await Promise.all([
          cmsApi.getRecommendation(recommendationId),
          cmsApi.getValidations(recommendationId),
          canCreate
            ? cmsApi.getValidationOptions(recommendationId)
            : Promise.resolve(null),
        ]);
      setRecommendation(recommendationResult);
      setListData(validationsResult);
      setValidationOptions(optionsResult);
      setProgressUpdates(optionsResult?.eligibleRecordedProgressUpdates || []);
    } catch (requestError) {
      setError(
        requestError.status === 404
          ? "This recommendation or its Validation Reviews are unavailable or outside your authorized scope."
          : requestError.message ||
              "The Validation Reviews could not be loaded.",
      );
    } finally {
      setLoading(false);
    }
  }, [canCreate, recommendationId]);
  const canEvidenceUploadPermission = hasPermission(
    user,
    "cms.validation-evidence.upload",
  );
  const loadDetail = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = await cmsApi.getValidation(validationId);
      setValidation(result);
      const version = result?.currentVersion || result?.versions?.[0];
      setSelectedVersionId((current) => current || version?.id);
      const selected =
        result?.versions?.find(
          (item) => item.id === (selectedVersionId || version?.id),
        ) || version;
      setForm(selected ? formFromVersion(selected) : null);
      const [assignmentResult, optionsResult] = await Promise.all([
        canAssign ||
        result?.currentVersion?.availableActions?.includes("replace-validator")
          ? cmsApi.getValidationAssignments(validationId)
          : Promise.resolve(null),
        canAssign && canCreate
          ? cmsApi.getValidationOptions(result.caseId)
          : Promise.resolve(null),
      ]);
      setAssignments(
        assignmentResult?.assignments || result?.assignments || [],
      );
      if (optionsResult) setValidationOptions(optionsResult);
      if (canEvidenceUploadPermission) {
        const docs = await documentApi.list();
        setConfidentialityLevels(docs.confidentialityLevels || []);
      }
    } catch (requestError) {
      setError(
        requestError.status === 404
          ? "This Validation Review is unavailable or outside your authorized scope."
          : requestError.message ||
              "The Validation Review could not be loaded.",
      );
    } finally {
      setLoading(false);
    }
  }, [
    canAssign,
    canCreate,
    canEvidenceUploadPermission,
    selectedVersionId,
    validationId,
  ]);
  // Data-loading effects intentionally synchronize the workspace with the API.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    (detailMode ? loadDetail() : loadList()).catch(() => {});
  }, [detailMode, loadDetail, loadList]);
  useEffect(() => {
    if (!validation) return;
    const selected =
      validation.versions?.find((item) => item.id === selectedVersionId) ||
      validation.currentVersion;
    if (selected) {
      // Synchronize the editable form with the selected persisted version.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setForm(formFromVersion(selected));
      setTab("overview");
    }
  }, [selectedVersionId, validation]);

  function formFromVersion(version) {
    return {
      validationScope: version.validationScope || "",
      validationObjectives: version.validationObjectives || "",
      methodologySummary: version.methodologySummary || "",
      overallWorkPerformed: version.overallWorkPerformed || "",
      overallEvidenceSummary: version.overallEvidenceSummary || "",
      limitations: version.limitations || "",
      professionalJudgmentRationale:
        version.professionalJudgmentRationale || "",
      proposedConclusionCode: version.proposedConclusionCode || "",
      validatedCompletionPercentage:
        version.validatedCompletionPercentage ?? "",
      validationItems: (version.validationItems || []).map((item) => ({
        ...item,
      })),
      evidenceAssessments: (version.evidenceAssessments || []).map((item) => ({
        ...item,
        reliedUpon: Boolean(item.reliedUpon),
      })),
      lockVersion: version.lockVersion,
    };
  }
  const selectedVersion =
    validation?.versions?.find((item) => item.id === selectedVersionId) ||
    validation?.currentVersion;
  const actions = selectedVersion?.availableActions || [];
  const readOnly =
    selectedVersion?.status !== "DRAFT" || !actions.includes("update");
  async function refreshAfterConflict(requestError) {
    const isConflict =
      requestError instanceof ApiError &&
      (requestError.status === 409 || requestError.errors?.lockVersion);
    if (!isConflict) return false;
    setConflict(
      "Another user or process changed this validation. Your local draft is preserved; reload the latest authoritative version before retrying.",
    );
    await loadDetail();
    return true;
  }
  async function createReview(values) {
    if (saving) return;
    setSaving(true);
    setCreateErrors({});
    try {
      const created = await cmsApi.createValidation(recommendationId, {
        ...values,
        recordedProgressUpdateVersionId: Number(
          values.recordedProgressUpdateVersionId,
        ),
        validatorUserId: Number(values.validatorUserId),
        lockVersion: Number(values.lockVersion),
      });
      setCreateOpen(false);
      toast.success(
        "Validation Review created and Primary Validator assigned.",
      );
      navigate(
        `/compliance-management/recommendations/${recommendationId}/validations/${created.id}`,
      );
    } catch (requestError) {
      setCreateErrors(requestError.errors || {});
      toast.error(
        requestError.message || "The Validation Review could not be created.",
      );
    } finally {
      setSaving(false);
    }
  }
  async function saveDraft() {
    if (!validation || !selectedVersion || saving) return;
    setSaving(true);
    setFormErrors({});
    setConflict("");
    try {
      const saved = await cmsApi.updateValidation(
        validation.id,
        selectedVersion.id,
        {
          ...form,
          lockVersion: selectedVersion.lockVersion,
          validatedCompletionPercentage:
            form.validatedCompletionPercentage === ""
              ? null
              : Number(form.validatedCompletionPercentage),
          validationItems: form.validationItems.map((item, index) => ({
            ...item,
            sequenceNumber: index + 1,
            displayOrder: index + 1,
          })),
          evidenceAssessments: form.evidenceAssessments,
        },
      );
      setValidation(saved);
      setSelectedVersionId(saved.currentVersion?.id || selectedVersion.id);
      toast.success("Validation draft saved.");
    } catch (requestError) {
      setFormErrors(requestError.errors || {});
      await refreshAfterConflict(requestError);
      toast.error(
        requestError.message || "The validation draft could not be saved.",
      );
    } finally {
      setSaving(false);
    }
  }
  function openWorkflow(action) {
    setWorkflow(action);
    setWorkflowComment("");
    setWorkflowErrors({});
    setWorkflowConfirmed(false);
    setFinalConclusion(selectedVersion?.proposedConclusionCode || "");
    setOverrideReason("");
  }
  async function confirmWorkflow() {
    if (!selectedVersion || saving) return;
    const local = {};
    if (
      ["return", "revise", "finalize"].includes(workflow) &&
      !workflowComment.trim()
    )
      local[
        workflow === "return"
          ? "returnReason"
          : workflow === "revise"
            ? "revisionReason"
            : "finalizationComment"
      ] = ["Required."];
    if (["submit", "finalize"].includes(workflow) && !workflowConfirmed)
      local.confirmation = ["Confirmation is required."];
    if (workflow === "finalize" && !finalConclusion)
      local.finalConclusionCode = ["Select a final conclusion."];
    if (
      workflow === "finalize" &&
      finalConclusion !== selectedVersion.proposedConclusionCode &&
      !overrideReason.trim()
    )
      local.overrideReason = [
        "An override reason is required when the final conclusion differs.",
      ];
    if (Object.keys(local).length) {
      setWorkflowErrors(local);
      return;
    }
    setSaving(true);
    setWorkflowErrors({});
    try {
      const payload = { lockVersion: selectedVersion.lockVersion };
      let result;
      if (workflow === "submit")
        result = await cmsApi.submitValidation(
          validation.id,
          selectedVersion.id,
          { ...payload, confirmation: true },
        );
      if (workflow === "start-review")
        result = await cmsApi.startValidationReview(
          validation.id,
          selectedVersion.id,
          { ...payload, reviewComment: workflowComment.trim() || null },
        );
      if (workflow === "return")
        result = await cmsApi.returnValidation(
          validation.id,
          selectedVersion.id,
          { ...payload, returnReason: workflowComment.trim() },
        );
      if (workflow === "revise")
        result = await cmsApi.createValidationRevision(
          validation.id,
          selectedVersion.id,
          { ...payload, revisionReason: workflowComment.trim() },
        );
      if (workflow === "finalize")
        result = await cmsApi.finalizeValidation(
          validation.id,
          selectedVersion.id,
          {
            ...payload,
            finalConclusionCode: finalConclusion,
            finalizationComment: workflowComment.trim(),
            confirmation: true,
            ...(overrideReason.trim()
              ? { overrideReason: overrideReason.trim() }
              : {}),
          },
        );
      setWorkflow("");
      setValidation(result);
      setSelectedVersionId(
        result?.currentVersion?.id || result?.finalizedVersion?.id,
      );
      setForm(
        result?.currentVersion ? formFromVersion(result.currentVersion) : null,
      );
      toast.success(
        workflow === "finalize"
          ? "Professional validation conclusion finalized."
          : `Validation ${workflow === "start-review" ? "supervisory review started" : workflow === "revise" ? "revision created" : `${workflow}ed`} successfully.`,
      );
    } catch (requestError) {
      setWorkflowErrors(requestError.errors || {});
      await refreshAfterConflict(requestError);
      toast.error(
        requestError.message ||
          "The validation workflow action could not be completed.",
      );
    } finally {
      setSaving(false);
    }
  }
  async function uploadEvidence(values, reset) {
    if (!selectedVersion || saving) return;
    setSaving(true);
    try {
      const data = new FormData();
      data.set("lockVersion", String(selectedVersion.lockVersion));
      data.set("evidenceCategory", values.evidenceCategory);
      data.set("title", values.title);
      data.set("description", values.description || "");
      data.set("sourceOrCustodian", values.sourceOrCustodian || "");
      data.set("confidentialityLevelId", String(values.confidentialityLevelId));
      if (values.validationItemId)
        data.set("validationItemId", String(values.validationItemId));
      data.set("file", values.file);
      await cmsApi.uploadValidationEvidence(
        validation.id,
        selectedVersion.id,
        data,
      );
      reset();
      await loadDetail();
      toast.success("Validator evidence linked to the draft.");
    } catch (requestError) {
      await refreshAfterConflict(requestError);
      toast.error(
        requestError.message || "Validator evidence could not be uploaded.",
      );
    } finally {
      setSaving(false);
    }
  }
  async function removeEvidence(item) {
    const reason = window.prompt(
      "Removal reason (the Core Document will be retained):",
    );
    if (!reason?.trim()) return;
    setSaving(true);
    try {
      await cmsApi.removeValidationEvidence(item.id, {
        lockVersion: selectedVersion.lockVersion,
        removalReason: reason.trim(),
      });
      await loadDetail();
      toast.success(
        "Draft evidence link removed; the Core Document was retained.",
      );
    } catch (requestError) {
      await refreshAfterConflict(requestError);
      toast.error(
        requestError.message || "The evidence link could not be removed.",
      );
    } finally {
      setSaving(false);
    }
  }
  async function assignValidator(values) {
    setSaving(true);
    try {
      const result = await cmsApi.assignValidator(validation.id, {
        validatorUserId: Number(values.validatorUserId),
        assignmentReason: values.assignmentReason.trim(),
        lockVersion: validation.lockVersion,
      });
      setValidation(result);
      await loadDetail();
      toast.success("Primary Validator assignment updated.");
    } catch (requestError) {
      await refreshAfterConflict(requestError);
      toast.error(
        requestError.message ||
          "The validator assignment could not be updated.",
      );
    } finally {
      setSaving(false);
    }
  }
  async function endValidator(item) {
    const reason = window.prompt("End reason:");
    if (!reason?.trim()) return;
    setSaving(true);
    try {
      await cmsApi.endValidatorAssignment(validation.id, item.id, {
        lockVersion: validation.lockVersion,
        endReason: reason.trim(),
      });
      await loadDetail();
      toast.success("Validator assignment ended.");
    } catch (requestError) {
      await refreshAfterConflict(requestError);
      toast.error(requestError.message || "The assignment could not be ended.");
    } finally {
      setSaving(false);
    }
  }

  if (loading && (detailMode ? !validation : !listData)) return <Skeleton />;
  if (error && (detailMode ? !validation : !listData))
    return (
      <ErrorState
        message={error}
        onRetry={detailMode ? loadDetail : loadList}
      />
    );
  if (!detailMode) {
    const validations = listData?.validations || [];
    const permitted = listData?.permittedActions || [];
    const eligibleValidators = validationOptions?.eligibleValidators || [];
    const unavailableReasons = validationOptions?.unavailableReasons || [];
    const hasEligibleProgress = progressUpdates.length > 0;
    return (
      <div className="min-w-0">
        <RegistryHeader
          actions={
            <div className="flex flex-wrap gap-2">
              <Link
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
                to={`/compliance-management/recommendations/${recommendationId}`}
              >
                <ArrowLeft size={16} /> Recommendation
              </Link>
              <button
                aria-label="Refresh Validations"
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 disabled:opacity-60"
                disabled={loading}
                onClick={loadList}
                type="button"
              >
                <RefreshCw
                  className={loading ? "animate-spin" : ""}
                  size={16}
                />{" "}
                Refresh
              </button>
              {canCreate && permitted.includes("create") && (
                <button
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60"
                  disabled={!eligibleValidators.length || !hasEligibleProgress}
                  onClick={() => {
                    setCreateErrors({});
                    setCreateOpen(true);
                  }}
                  type="button"
                >
                  <Plus size={16} /> Create Validation Review
                </button>
              )}
            </div>
          }
          description="Independently validate a recorded management Progress Update without conflating professional implementation conclusions with closure."
          icon={ClipboardCheck}
          title="Independent Validation"
        />
        {error && (
          <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Refresh failed. Showing the last successfully loaded workspace.
          </div>
        )}
        <ContextPanel
          context={listData?.caseContext}
          recommendation={recommendation}
        />
        {validations.length === 0 ? (
          <section className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
            <ClipboardCheck className="mx-auto text-slate-300" size={42} />
            <h2 className="mt-3 text-lg font-bold text-slate-800">
              No Validation Reviews yet
            </h2>
            <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">
              Create a review only after a recorded Progress Update is eligible
              and a safe validator option is supplied by the backend.
            </p>
            {unavailableReasons.length > 0 && (
              <ul className="mx-auto mt-4 max-w-xl list-disc text-left text-xs leading-5 text-amber-700">
                {unavailableReasons.map((reason) => (
                  <li key={reason}>{reason}</li>
                ))}
              </ul>
            )}
          </section>
        ) : (
          <div className="grid gap-3">
            {validations.map((item) => (
              <ValidationCard
                key={item.id}
                onOpen={() =>
                  navigate(
                    `/compliance-management/recommendations/${recommendationId}/validations/${item.id}`,
                  )
                }
                sourceProgress={progressUpdates.find(
                  (update) =>
                    update.recordedVersionId ===
                    item.recordedProgressUpdateVersionId,
                )}
                validation={item}
              />
            ))}
          </div>
        )}
        <CreateValidationDialog
          eligibleValidators={eligibleValidators}
          errors={createErrors}
          lockVersion={
            validationOptions?.caseContext?.lockVersion ||
            listData?.caseContext?.lockVersion ||
            recommendation?.lockVersion
          }
          onClose={() => !saving && setCreateOpen(false)}
          onSubmit={createReview}
          open={createOpen}
          progressUpdates={progressUpdates}
          busy={saving}
        />
      </div>
    );
  }
  if (error && validation)
    return (
      <div className="min-w-0">
        <ErrorState message={error} onRetry={loadDetail} />
      </div>
    );
  if (!validation || !selectedVersion || !form)
    return (
      <ErrorState
        message="The Validation Version is unavailable."
        onRetry={loadDetail}
      />
    );
  const source = validation.sourceContext || {};
  const eligibleValidators =
    validationOptions?.eligibleValidators ||
    validation.eligibleValidators ||
    [];
  const canUpload =
    actions.includes("upload-evidence") &&
    hasPermission(user, "cms.validation-evidence.upload");
  const tabItems = [
    ["overview", "Overview"],
    ["procedures", "Procedures & Conclusions"],
    ["assessments", "Evidence Assessment"],
    ["validator-evidence", "Validator Evidence"],
    ["assignments", "Assignments"],
    ["history", "Versions & History"],
  ];
  return (
    <div className="min-w-0">
      <RegistryHeader
        actions={
          <div className="flex flex-wrap gap-2">
            <Link
              className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              to={`/compliance-management/recommendations/${validation.caseId || source.caseId || ""}/validations`}
            >
              <ArrowLeft size={16} /> Validations
            </Link>
            <button
              aria-label="Refresh Validation"
              className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 disabled:opacity-60"
              disabled={loading || saving}
              onClick={loadDetail}
              type="button"
            >
              <RefreshCw className={loading ? "animate-spin" : ""} size={16} />{" "}
              Refresh
            </button>
          </div>
        }
        description="Procedures, evidence assessment, supervisory review, and immutable professional conclusions."
        icon={ShieldCheck}
        title={validation.displayCode || "Validation Review"}
      />
      {conflict && (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          <span>{conflict}</span>
          <button
            className="inline-flex h-9 items-center gap-2 rounded-lg bg-amber-700 px-3 text-xs font-bold text-white"
            onClick={loadDetail}
            type="button"
          >
            <RefreshCw size={14} /> Reload latest
          </button>
        </div>
      )}
      {error && (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      )}
      <ContextPanel
        context={source}
        recommendation={{
          recommendation: source.recommendation,
          responsibleOffice: source.responsibleOffice,
          status: source.caseStatus,
        }}
      />
      <section className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <strong className="text-slate-800">
              Version {selectedVersion.versionNumber}
            </strong>
            <CmsValidationStatusBadge status={selectedVersion.status} />
            {selectedVersion.proposedConclusionCode && (
              <CmsValidationConclusionBadge
                conclusion={selectedVersion.proposedConclusionCode}
              />
            )}
            {selectedVersion.finalConclusionCode === "IMPLEMENTED" && (
              <StatusBadge tone="warning">
                Implemented · closure pending
              </StatusBadge>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            {actions.includes("update") && (
              <button
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm font-bold text-sky-800"
                onClick={() => setTab("overview")}
                type="button"
              >
                <Save size={16} /> Edit draft
              </button>
            )}
            {actions.includes("submit") && (
              <button
                className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60"
                disabled={saving}
                onClick={() => openWorkflow("submit")}
                type="button"
              >
                <Send size={16} /> Submit
              </button>
            )}
            {actions.includes("start-review") && (
              <button
                className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                disabled={saving}
                onClick={() => openWorkflow("start-review")}
                type="button"
              >
                Start supervisory review
              </button>
            )}
            {actions.includes("return") && (
              <button
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300 bg-white px-4 text-sm font-bold text-amber-800 disabled:opacity-60"
                disabled={saving}
                onClick={() => openWorkflow("return")}
                type="button"
              >
                <RotateCcw size={16} /> Return
              </button>
            )}
            {actions.includes("revise") && (
              <button
                className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60"
                disabled={saving}
                onClick={() => openWorkflow("revise")}
                type="button"
              >
                <FilePenLine size={16} /> Create revision
              </button>
            )}
            {actions.includes("finalize") && (
              <button
                className="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                disabled={saving}
                onClick={() => openWorkflow("finalize")}
                type="button"
              >
                <CheckCircle2 size={16} /> Finalize validation
              </button>
            )}
          </div>
        </div>
        <dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
          <ContextDatum
            label="Management-reported percentage"
            value={
              source.managementReportedPercentage == null
                ? "Not reported"
                : `${source.managementReportedPercentage}%`
            }
          />
          <ContextDatum
            label="Validated completion"
            value={
              selectedVersion.validatedCompletionPercentage == null
                ? "Not recorded"
                : `${selectedVersion.validatedCompletionPercentage}%`
            }
          />
          <ContextDatum
            label="Primary Validator"
            value={
              validation.currentPrimaryValidator?.name ||
              selectedVersion.validator?.name ||
              "Unassigned"
            }
          />
          <ContextDatum
            label="Accepted Action Plan baseline"
            value={
              validation.acceptedActionPlanVersionId
                ? `Version ID ${validation.acceptedActionPlanVersionId}`
                : "Not available"
            }
          />
        </dl>
      </section>
      <div
        className="mb-4 flex overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
        role="tablist"
      >
        {tabItems.map(([key, label]) => (
          <button
            aria-selected={tab === key}
            className={`inline-flex min-w-max flex-1 items-center justify-center rounded-lg px-3 py-2.5 text-sm font-bold ${tab === key ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-50"}`}
            key={key}
            onClick={() => setTab(key)}
            role="tab"
            type="button"
          >
            {label}
          </button>
        ))}
      </div>
      <main className="min-w-0">
        {tab === "overview" &&
          (readOnly ? (
            <div className="grid gap-3">
              <dl className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
                <ReadOnlyField
                  label="Validation scope"
                  value={selectedVersion.validationScope}
                  wide
                />
                <ReadOnlyField
                  label="Validation objectives"
                  value={selectedVersion.validationObjectives}
                  wide
                />
                <ReadOnlyField
                  label="Methodology summary"
                  value={selectedVersion.methodologySummary}
                  wide
                />
                <ReadOnlyField
                  label="Overall work performed"
                  value={selectedVersion.overallWorkPerformed}
                  wide
                />
                <ReadOnlyField
                  label="Overall evidence summary"
                  value={selectedVersion.overallEvidenceSummary}
                  wide
                />
                <ReadOnlyField
                  label="Limitations"
                  value={selectedVersion.limitations}
                  wide
                />
                <ReadOnlyField
                  label="Professional judgment rationale"
                  value={selectedVersion.professionalJudgmentRationale}
                  wide
                />
                <ReadOnlyField
                  label="Final conclusion"
                  value={
                    selectedVersion.finalConclusionCode
                      ? labelFor(selectedVersion.finalConclusionCode)
                      : "Not finalized"
                  }
                />
              </dl>
              {selectedVersion.finalConclusionCode === "IMPLEMENTED" && (
                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900">
                  <strong>
                    Independently validated as implemented — closure not yet
                    completed.
                  </strong>
                </div>
              )}
            </div>
          ) : (
            <DraftForm
              busy={saving}
              errors={formErrors}
              form={form}
              onSave={saveDraft}
              setForm={setForm}
            />
          ))}
        {tab === "procedures" && (
          <ItemsPanel
            errors={formErrors}
            form={form}
            readOnly={readOnly}
            setForm={setForm}
          />
        )}
        {tab === "assessments" && (
          <AssessmentsPanel
            errors={formErrors}
            form={form}
            readOnly={readOnly}
            setForm={setForm}
          />
        )}
        {tab === "validator-evidence" && (
          <ValidatorEvidencePanel
            busy={saving}
            canUpload={canUpload}
            confidentialityLevels={confidentialityLevels}
            form={form}
            onDownload={(item) =>
              cmsApi
                .downloadValidationEvidence(
                  item.id,
                  item.file?.name || item.title,
                )
                .catch((e) => toast.error(e.message || "Download failed."))
            }
            onRemove={removeEvidence}
            onUpload={uploadEvidence}
            version={selectedVersion}
          />
        )}
        {tab === "assignments" && (
          <AssignmentsPanel
            assignments={
              assignments.length ? assignments : validation.assignments || []
            }
            canAssign={canAssign && actions.includes("replace-validator")}
            eligibleValidators={eligibleValidators}
            onAssign={assignValidator}
            onEnd={endValidator}
          />
        )}
        {tab === "history" && (
          <VersionHistory
            onSelect={(id) => setSelectedVersionId(id)}
            selectedId={selectedVersion.id}
            versions={validation.versions || []}
          />
        )}
      </main>
      <WorkflowDialog
        action={workflow}
        busy={saving}
        comment={workflowComment}
        confirmed={workflowConfirmed}
        errors={workflowErrors}
        finalConclusion={finalConclusion}
        onClose={() => !saving && setWorkflow("")}
        onConfirm={confirmWorkflow}
        overrideReason={overrideReason}
        setComment={setWorkflowComment}
        setConfirmed={setWorkflowConfirmed}
        setFinalConclusion={setFinalConclusion}
        setOverrideReason={setOverrideReason}
        version={selectedVersion}
      />
    </div>
  );
}
