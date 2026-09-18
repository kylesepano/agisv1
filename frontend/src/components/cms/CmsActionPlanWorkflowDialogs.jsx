import FormField from "../ui/FormField";
import ConfirmDialog from "../ui/ConfirmDialog";
import { milestoneWeightState } from "./cms-action-plan";

function firstError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

function SummaryDatum({ label, value }) {
  return (
    <div className="rounded-lg bg-slate-100 px-3 py-2">
      <dt className="text-[11px] font-bold uppercase tracking-wide text-slate-500">
        {label}
      </dt>
      <dd className="mt-0.5 text-sm font-semibold text-slate-800">{value}</dd>
    </div>
  );
}

export default function CmsActionPlanWorkflowDialogs({
  dialog,
  version,
  plan,
  form,
  busy,
  errors,
  comment,
  setComment,
  confirmed,
  setConfirmed,
  onClose,
  onConfirm,
}) {
  if (!dialog || !version) return null;

  const weight = milestoneWeightState(
    form?.milestones ?? version.milestones ?? [],
  );
  const commonTextArea = (
    <textarea
      autoFocus
      className="mt-1 min-h-28 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
      id="action-plan-workflow-comment"
      maxLength={5000}
      onChange={(event) => setComment(event.target.value)}
      value={comment}
    />
  );

  if (dialog === "submit") {
    return (
      <ConfirmDialog
        busy={busy}
        confirmLabel="Submit Action Plan"
        description="Submission creates an immutable snapshot. This version cannot be edited afterward."
        onCancel={onClose}
        onConfirm={onConfirm}
        open
        title="Review and submit Action Plan"
        tone="warning"
      >
        <dl className="mt-4 grid gap-2 sm:grid-cols-2">
          <SummaryDatum
            label="Version"
            value={`Version ${version.versionNumber}`}
          />
          <SummaryDatum
            label="Responsible office"
            value={plan.ownerOffice?.name || "Not recorded"}
          />
          <SummaryDatum
            label="Focal user"
            value={version.focalUser?.name || "Not recorded"}
          />
          <SummaryDatum
            label="Planned dates"
            value={`${version.plannedStartDate || "Not set"} – ${version.plannedTargetDate || "Not set"}`}
          />
          <SummaryDatum
            label="Milestones"
            value={String(version.milestones?.length ?? 0)}
          />
          <SummaryDatum
            label="Weight"
            value={
              weight.supplied === 0 ? "Not used" : `${weight.total.toFixed(2)}%`
            }
          />
        </dl>
        {firstError(errors, "confirmation") && (
          <p className="mt-3 text-xs font-semibold text-red-600">
            {firstError(errors, "confirmation")}
          </p>
        )}
      </ConfirmDialog>
    );
  }

  if (dialog === "start-review") {
    return (
      <ConfirmDialog
        busy={busy}
        confirmLabel="Start review"
        description="Begin independent compliance review of this submitted, immutable Action Plan version."
        onCancel={onClose}
        onConfirm={onConfirm}
        open
        title="Start Action Plan review"
      />
    );
  }

  if (dialog === "return") {
    return (
      <ConfirmDialog
        busy={busy}
        confirmLabel="Return Action Plan"
        description="The returned version remains immutable. The responsible office must create a controlled revision to make corrections."
        onCancel={onClose}
        onConfirm={onConfirm}
        open
        title="Return Action Plan"
        tone="warning"
      >
        <div className="mt-4">
          <FormField
            error={firstError(errors, "returnReason")}
            htmlFor="action-plan-workflow-comment"
            label="Return instructions"
            required
          >
            {commonTextArea}
          </FormField>
        </div>
      </ConfirmDialog>
    );
  }

  if (dialog === "accept") {
    return (
      <ConfirmDialog
        busy={busy}
        confirmLabel="Accept as baseline"
        description="Acceptance establishes this version as the official monitoring baseline and may move the recommendation case to Monitoring."
        onCancel={onClose}
        onConfirm={onConfirm}
        open
        title="Accept Action Plan"
      >
        <div className="mt-4 grid gap-3">
          <FormField
            error={firstError(errors, "acceptanceComment")}
            htmlFor="action-plan-workflow-comment"
            label="Acceptance comment"
            required
          >
            {commonTextArea}
          </FormField>
          <label className="flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
            <input
              checked={confirmed}
              className="mt-1"
              onChange={(event) => setConfirmed(event.target.checked)}
              type="checkbox"
            />
            <span>
              I confirm that this reviewed version is complete and should become
              the accepted monitoring baseline.
            </span>
          </label>
          {firstError(errors, "confirmation") && (
            <p className="text-xs font-semibold text-red-600">
              {firstError(errors, "confirmation")}
            </p>
          )}
        </div>
      </ConfirmDialog>
    );
  }

  return (
    <ConfirmDialog
      busy={busy}
      confirmLabel="Create revision"
      description={
        version.status === "ACCEPTED"
          ? "The accepted version remains immutable and in effect until the new draft revision is independently accepted."
          : "The returned version remains immutable. Its content and milestones will be copied into a new draft."
      }
      onCancel={onClose}
      onConfirm={onConfirm}
      open
      title="Create controlled revision"
    >
      <div className="mt-4">
        <FormField
          error={firstError(errors, "revisionReason")}
          htmlFor="action-plan-workflow-comment"
          label="Revision reason"
          required
        >
          {commonTextArea}
        </FormField>
      </div>
    </ConfirmDialog>
  );
}
