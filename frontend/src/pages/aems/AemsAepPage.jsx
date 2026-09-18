import { useCallback, useEffect, useMemo, useState } from "react";
import {
  BadgeCheck,
  CalendarRange,
  ClipboardCheck,
  FileClock,
  FilePenLine,
  History,
  Plus,
  RotateCcw,
  Send,
  ShieldCheck,
  Undo2,
  UsersRound,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";
import { hasPermission } from "../../config/navigation";
import { aemsAepApi, aemsEngagementApi, ApiError } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const emptyForm = {
  objectives: "",
  scope: "",
  exclusions: "",
  methodology: "",
  auditCriteria: "",
  materiality: "",
  samplingApproach: "",
  plannedStartDate: "",
  plannedEndDate: "",
  expectedReportDate: "",
  plannedPersonDays: "",
  confidentialityLevelId: "",
  resourceRequirements: {
    staffing: "",
    skills: "",
    tools: "",
    logistics: "",
  },
  managementCoordination: {
    contactPerson: "",
    contactDetails: "",
    kickoffDetails: "",
    recordsDeadline: "",
    notes: "",
  },
  changeReason: "",
};

const statusLabels = {
  DRAFT: "Draft",
  PENDING_REVIEW: "Pending Review",
  RETURNED_FOR_REVISION: "Returned",
  RESUBMITTED: "Resubmitted",
  APPROVED: "Approved",
  SUPERSEDED: "Superseded",
};

const statusTones = {
  DRAFT: "inactive",
  PENDING_REVIEW: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  APPROVED: "success",
  SUPERSEDED: "inactive",
};

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function displayDate(value, time = false) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(time ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(new Date(time ? value : `${value}T00:00:00`));
}

function TextBlock({ title, value }) {
  return (
    <div>
      <h3 className="text-xs font-bold uppercase tracking-wide text-slate-400">
        {title}
      </h3>
      <p className="mt-1 whitespace-pre-wrap text-sm leading-7 text-slate-700">
        {value || "—"}
      </p>
    </div>
  );
}

function Field({ error, label: fieldLabel, children, wide = false }) {
  return (
    <label
      className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}
    >
      {fieldLabel}
      <span className="mt-1.5 block">{children}</span>
      {error && <small className="mt-1 block text-red-600">{error[0]}</small>}
    </label>
  );
}

/**
 * Presents the controlled, versioned Audit Engagement Plan for a selected
 * engagement. All role, workflow, immutable-version, and concurrency rules are
 * enforced again by the API.
 */
export default function AemsAepPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    params.get("engagementId") ?? "",
  );
  const [workspace, setWorkspace] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [form, setForm] = useState(emptyForm);
  const [formOpen, setFormOpen] = useState(false);
  const [actionOpen, setActionOpen] = useState(false);
  const [action, setAction] = useState("");
  const [comment, setComment] = useState("");

  const canCreate = hasPermission(user, "aems.aep.create");
  const canReview = hasPermission(user, "aems.aep.review");
  const canApprove = hasPermission(user, "aems.aep.approve");
  const canRevise = hasPermission(user, "aems.aep.revise");

  const loadEngagements = useCallback(async () => {
    setLoading(true);
    try {
      const data = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(data.engagements);
      setSelectedId(
        (current) => current || String(data.engagements[0]?.id ?? ""),
      );
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!selectedId) {
      setWorkspace(null);
      return;
    }
    setLoading(true);
    setError("");
    try {
      setWorkspace(await aemsAepApi.show(selectedId));
    } catch (requestError) {
      setWorkspace(null);
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [selectedId]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  useEffect(() => {
    const timer = window.setTimeout(loadWorkspace, 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);

  useEffect(() => {
    if (selectedId) {
      setParams({ engagementId: selectedId }, { replace: true });
    }
  }, [selectedId, setParams]);

  const plan = workspace?.plan;
  const version = plan?.latestVersion;
  const planningGate = getAemsWorkspaceGate("planning", workspace?.engagement);
  const planningUnlocked = planningGate.unlocked;
  const risk = version?.linkedRiskSnapshot?.riskAssessment;
  const engagementOptions = engagements.map((engagement) => ({
    value: engagement.id,
    label: `${engagement.engagementCode} — ${engagement.title}`,
    keywords: engagement.offices?.map((office) => office.name).join(" "),
  }));

  const actions = useMemo(() => {
    // The complete Planning Workspace, including AEP review actions, stays
    // locked until the aggregate engagement reaches ENGAGEMENT_PLANNING.
    if (!plan || !planningUnlocked) return [];
    const available = [];
    if (plan.status === "DRAFT" && canCreate) {
      available.push(["SUBMIT", "Submit for review", Send, "primary"]);
    }
    if (["PENDING_REVIEW", "RESUBMITTED"].includes(plan.status) && canReview) {
      available.push(["REVIEW", "Record review", ClipboardCheck, "primary"]);
      available.push(["RETURN", "Return for revision", Undo2, "warning"]);
    }
    if (["PENDING_REVIEW", "RESUBMITTED"].includes(plan.status) && canApprove) {
      available.push(["APPROVE", "Approve AEP", BadgeCheck, "success"]);
    }
    if (plan.status === "RETURNED_FOR_REVISION" && canCreate) {
      available.push(["RESUBMIT", "Resubmit AEP", Send, "primary"]);
    }
    if (plan.status === "APPROVED" && canRevise) {
      available.push([
        "REVISE",
        "Create formal revision",
        RotateCcw,
        "warning",
      ]);
    }
    return available;
  }, [canApprove, canCreate, canReview, canRevise, plan, planningUnlocked]);

  function updateNested(group, key, value) {
    setForm((current) => ({
      ...current,
      [group]: { ...current[group], [key]: value },
    }));
  }

  function openForm() {
    setErrors({});
    setForm(
      version
        ? {
            ...emptyForm,
            ...version,
            confidentialityLevelId: version.confidentialityLevelId ?? "",
            resourceRequirements: {
              ...emptyForm.resourceRequirements,
              ...version.resourceRequirements,
            },
            managementCoordination: {
              ...emptyForm.managementCoordination,
              ...version.managementCoordination,
            },
            changeReason: "",
          }
        : {
            ...emptyForm,
            objectives: workspace?.engagement.objectives ?? "",
            scope: workspace?.engagement.scope ?? "",
            exclusions: workspace?.engagement.exclusions ?? "",
            materiality: workspace?.engagement.sourceMateriality ?? "",
            plannedStartDate: workspace?.engagement.plannedStartDate ?? "",
            plannedEndDate: workspace?.engagement.plannedEndDate ?? "",
            expectedReportDate: workspace?.engagement.expectedReportDate ?? "",
            plannedPersonDays: workspace?.engagement.plannedPersonDays || "",
            linkedRiskSnapshot: undefined,
          },
    );
    setFormOpen(true);
  }

  async function savePlan() {
    setSaving(true);
    setErrors({});
    try {
      if (plan) {
        await aemsAepApi.update(selectedId, plan.id, {
          ...form,
          lockVersion: plan.lockVersion,
          changeReason:
            form.changeReason ||
            "Updated following engagement planning review.",
        });
        toast.success("A new immutable AEP version was created.");
      } else {
        await aemsAepApi.create(selectedId, form);
        toast.success("Draft Audit Engagement Plan created.");
      }
      setFormOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  function openAction(nextAction) {
    setAction(nextAction);
    setComment("");
    setErrors({});
    setActionOpen(true);
  }

  async function performAction() {
    setSaving(true);
    setErrors({});
    try {
      if (action === "REVISE") {
        await aemsAepApi.revise(selectedId, plan.id, {
          lockVersion: plan.lockVersion,
          reason: comment,
        });
      } else {
        await aemsAepApi.transition(selectedId, plan.id, {
          action,
          lockVersion: plan.lockVersion,
          comment: comment || null,
        });
      }
      toast.success(`${label(action)} completed.`);
      setActionOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  const inputClass =
    "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 font-normal outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
  const textAreaClass =
    "min-h-24 w-full rounded-lg border border-slate-300 bg-white p-3 font-normal outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

  return (
    <main className="min-w-0 p-3 sm:p-5 lg:p-6">
      <RegistryHeader
        icon={ClipboardCheck}
        title="Audit Engagement Plan"
        description="Define the objectives, scope, methodology, criteria, resources, source risks, schedule, and management coordination for an authorized engagement."
        readOnly={!planningUnlocked || (!canCreate && !canReview && !canApprove)}
        actions={
          !plan && canCreate && planningUnlocked && selectedId ? (
            <button
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
              disabled={!workspace?.issuedAeo}
              onClick={openForm}
              type="button"
            >
              <Plus size={17} /> Create draft AEP
            </button>
          ) : null
        }
      />

      <section className="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label className="block text-xs font-bold uppercase tracking-wide text-slate-500">
          Engagement
          <span className="mt-2 block max-w-4xl normal-case">
            <SearchableSelect
              options={engagementOptions}
              placeholder="Select an engagement"
              searchPlaceholder="Search engagement code or title..."
              value={selectedId}
              onChange={(value) => setSelectedId(String(value))}
            />
          </span>
        </label>
      </section>

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      {workspace && !planningUnlocked && (
        <AemsWorkspaceLockNotice engagementId={selectedId} gate={planningGate} />
      )}

      {workspace && planningUnlocked && !workspace.issuedAeo && !plan && (
        <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <strong>Authorization prerequisite:</strong> issue the Audit
          Engagement Order before preparing the AEP.
        </div>
      )}

      {workspace && !plan && !loading && (
        <section className="grid min-h-64 place-items-center rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
          <div className="max-w-xl">
            <ClipboardCheck className="mx-auto text-sky-600" size={42} />
            <h2 className="mt-4 text-xl font-bold text-slate-800">
              No Audit Engagement Plan yet
            </h2>
            <p className="mt-2 text-sm leading-6 text-slate-500">
              The AEP converts the issued authority and preserved IAP risk
              context into the engagement’s controlled execution plan.
            </p>
            {canCreate && planningUnlocked && workspace.issuedAeo && (
              <button
                className="mt-5 inline-flex items-center gap-2 rounded-lg bg-sky-700 px-5 py-3 text-sm font-bold text-white"
                onClick={openForm}
                type="button"
              >
                <Plus size={17} /> Create draft AEP
              </button>
            )}
          </div>
        </section>
      )}

      {plan && (
        <>
          <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              icon={ClipboardCheck}
              label="AEP status"
              value={statusLabels[plan.status] ?? label(plan.status)}
              tone={plan.status === "APPROVED" ? "emerald" : "sky"}
            />
            <SummaryCard
              icon={History}
              label="Current version"
              value={`v${plan.currentVersionNumber}`}
              tone="sky"
            />
            <SummaryCard
              icon={UsersRound}
              label="Planned person-days"
              value={version?.plannedPersonDays ?? 0}
              tone="amber"
            />
            <SummaryCard
              icon={FileClock}
              label="Versions retained"
              value={plan.versions.length}
              tone="slate"
            />
          </section>

          <section className="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="font-bold text-slate-800">{plan.planCode}</h2>
                  <StatusBadge tone={statusTones[plan.status] ?? "info"}>
                    {statusLabels[plan.status] ?? label(plan.status)}
                  </StatusBadge>
                </div>
                <p className="mt-1 text-xs text-slate-500">
                  {workspace.engagement.engagementCode} · Version{" "}
                  {plan.currentVersionNumber}
                </p>
              </div>
              {canCreate &&
                ["DRAFT", "RETURNED_FOR_REVISION"].includes(plan.status) && (
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                    onClick={openForm}
                    type="button"
                  >
                    <FilePenLine size={15} /> New content version
                  </button>
                )}
            </header>

            <div className="grid gap-5 p-4 lg:grid-cols-[minmax(0,1fr)_22rem] sm:p-5">
              <div className="space-y-5">
                <TextBlock title="Objectives" value={version?.objectives} />
                <TextBlock title="Scope" value={version?.scope} />
                <TextBlock title="Exclusions" value={version?.exclusions} />
                <TextBlock title="Methodology" value={version?.methodology} />
                <TextBlock
                  title="Audit criteria"
                  value={version?.auditCriteria}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextBlock title="Materiality" value={version?.materiality} />
                  <TextBlock
                    title="Sampling approach"
                    value={version?.samplingApproach}
                  />
                </div>
              </div>

              <aside className="space-y-3">
                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                  <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                    <CalendarRange size={17} className="text-sky-700" />
                    Execution schedule
                  </h3>
                  <dl className="mt-3 space-y-2 text-xs text-slate-600">
                    <div className="flex justify-between gap-3">
                      <dt>Start</dt>
                      <dd className="font-semibold">
                        {displayDate(version?.plannedStartDate)}
                      </dd>
                    </div>
                    <div className="flex justify-between gap-3">
                      <dt>End</dt>
                      <dd className="font-semibold">
                        {displayDate(version?.plannedEndDate)}
                      </dd>
                    </div>
                    <div className="flex justify-between gap-3">
                      <dt>Expected report</dt>
                      <dd className="font-semibold">
                        {displayDate(version?.expectedReportDate)}
                      </dd>
                    </div>
                  </dl>
                </div>
                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                  <h3 className="flex items-center gap-2 text-sm font-bold text-emerald-900">
                    <ShieldCheck size={17} /> Risks carried from IAP
                  </h3>
                  {risk ? (
                    <dl className="mt-3 space-y-2 text-xs text-emerald-900">
                      <div className="flex justify-between gap-3">
                        <dt>Residual level</dt>
                        <dd className="font-bold">
                          {risk.residualRiskLevel ?? "—"}
                        </dd>
                      </div>
                      <div className="flex justify-between gap-3">
                        <dt>Residual score</dt>
                        <dd className="font-bold">
                          {risk.residualRiskScore ?? "—"}
                        </dd>
                      </div>
                      <p className="pt-2 leading-5">{risk.justification}</p>
                    </dl>
                  ) : (
                    <p className="mt-2 text-xs leading-5 text-emerald-800">
                      This special engagement has no source IAP risk assessment.
                    </p>
                  )}
                </div>
                <div className="rounded-xl border border-slate-200 p-4 text-xs text-slate-600">
                  <strong className="block text-slate-800">
                    Management coordination
                  </strong>
                  <p className="mt-2">
                    {version?.managementCoordination?.contactPerson || "—"}
                  </p>
                  <p>{version?.managementCoordination?.contactDetails}</p>
                  <p className="mt-2 leading-5">
                    {version?.managementCoordination?.kickoffDetails}
                  </p>
                  <p className="mt-2">
                    Records deadline:{" "}
                    {displayDate(
                      version?.managementCoordination?.recordsDeadline,
                    )}
                  </p>
                </div>
              </aside>
            </div>
          </section>

          {!!actions.length && (
            <section className="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-4">
              <h2 className="text-sm font-bold text-slate-800">
                Available workflow actions
              </h2>
              <div className="mt-3 flex flex-wrap gap-2">
                {actions.map(([code, text, Icon, tone]) => (
                  <button
                    className={`inline-flex h-10 items-center gap-2 rounded-lg px-4 text-xs font-bold text-white ${
                      tone === "success"
                        ? "bg-emerald-700 hover:bg-emerald-800"
                        : tone === "warning"
                          ? "bg-amber-600 hover:bg-amber-700"
                          : "bg-sky-700 hover:bg-sky-800"
                    }`}
                    key={code}
                    onClick={() => openAction(code)}
                    type="button"
                  >
                    <Icon size={15} /> {text}
                  </button>
                ))}
              </div>
            </section>
          )}

          <div className="grid gap-5 xl:grid-cols-2">
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <header className="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 className="font-bold text-slate-800">Version history</h2>
                <p className="mt-1 text-xs text-slate-500">
                  Approved versions remain immutable.
                </p>
              </header>
              <div className="divide-y divide-slate-100">
                {plan.versions.map((item) => (
                  <div
                    className="grid gap-1 px-4 py-3 text-xs sm:grid-cols-[5rem_1fr_auto] sm:px-5"
                    key={item.id}
                  >
                    <strong className="text-sky-800">
                      v{item.versionNumber}
                    </strong>
                    <span className="text-slate-600">
                      {item.changeReason || "Initial AEP version"}
                    </span>
                    <span className="text-slate-400">
                      {displayDate(item.createdAt, true)}
                    </span>
                  </div>
                ))}
              </div>
            </section>
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <header className="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 className="font-bold text-slate-800">Workflow history</h2>
                <p className="mt-1 text-xs text-slate-500">
                  Every decision records actor, date, version, and comment.
                </p>
              </header>
              <div className="divide-y divide-slate-100">
                {plan.events.map((event) => (
                  <div className="px-4 py-3 text-xs sm:px-5" key={event.id}>
                    <div className="flex flex-wrap justify-between gap-2">
                      <strong className="text-sky-800">
                        {label(event.action.replace("AEP_", ""))}
                      </strong>
                      <span className="text-slate-400">
                        {displayDate(event.createdAt, true)}
                      </span>
                    </div>
                    <p className="mt-1 text-slate-600">
                      {event.comment ||
                        `${event.fromStatus ?? "New"} → ${event.toStatus}`}
                    </p>
                    <p className="mt-1 text-slate-400">
                      {event.actor?.name} · v{event.subjectVersion}
                    </p>
                  </div>
                ))}
              </div>
            </section>
          </div>
        </>
      )}

      <Modal
        open={formOpen}
        onClose={() => !saving && setFormOpen(false)}
        size="xl"
        title={plan ? "Create new AEP content version" : "Create draft AEP"}
        description="Saving creates immutable plan content. Risk lineage is copied automatically from the preserved IAP source."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setFormOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={savePlan}
              type="button"
            >
              {saving ? "Saving..." : "Save immutable version"}
            </button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          {[
            ["objectives", "Objectives"],
            ["scope", "Scope"],
            ["exclusions", "Scope exclusions"],
            ["methodology", "Methodology"],
            ["auditCriteria", "Audit criteria"],
            ["materiality", "Materiality"],
            ["samplingApproach", "Sampling approach"],
          ].map(([key, fieldLabel]) => (
            <Field error={errors[key]} key={key} label={fieldLabel} wide>
              <textarea
                className={textAreaClass}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    [key]: event.target.value,
                  }))
                }
                value={form[key] ?? ""}
              />
            </Field>
          ))}

          <Field error={errors.plannedStartDate} label="Planned start">
            <input
              className={inputClass}
              type="date"
              value={form.plannedStartDate}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  plannedStartDate: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.plannedEndDate} label="Planned end">
            <input
              className={inputClass}
              type="date"
              value={form.plannedEndDate}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  plannedEndDate: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.expectedReportDate} label="Expected report date">
            <input
              className={inputClass}
              type="date"
              value={form.expectedReportDate}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  expectedReportDate: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.plannedPersonDays} label="Planned person-days">
            <input
              className={inputClass}
              min="0.5"
              step="0.5"
              type="number"
              value={form.plannedPersonDays}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  plannedPersonDays: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.confidentialityLevelId} label="Confidentiality">
            <SearchableSelect
              options={(workspace?.confidentialityLevels ?? []).map((item) => ({
                value: item.id,
                label: item.label,
                keywords: item.code,
              }))}
              placeholder="Select confidentiality"
              value={form.confidentialityLevelId}
              onChange={(value) =>
                setForm((current) => ({
                  ...current,
                  confidentialityLevelId: value,
                }))
              }
            />
          </Field>

          <h3 className="mt-2 border-b border-slate-200 pb-2 text-sm font-bold text-slate-800 sm:col-span-2">
            Resource requirements
          </h3>
          {["staffing", "skills", "tools", "logistics"].map((key) => (
            <Field
              error={errors[`resourceRequirements.${key}`]}
              key={key}
              label={label(key)}
            >
              <textarea
                className={textAreaClass}
                value={form.resourceRequirements[key]}
                onChange={(event) =>
                  updateNested("resourceRequirements", key, event.target.value)
                }
              />
            </Field>
          ))}

          <h3 className="mt-2 border-b border-slate-200 pb-2 text-sm font-bold text-slate-800 sm:col-span-2">
            Management coordination
          </h3>
          {[
            ["contactPerson", "Contact person"],
            ["contactDetails", "Contact details"],
            ["recordsDeadline", "Records deadline", "date"],
          ].map(([key, fieldLabel, type]) => (
            <Field
              error={errors[`managementCoordination.${key}`]}
              key={key}
              label={fieldLabel}
            >
              <input
                className={inputClass}
                type={type ?? "text"}
                value={form.managementCoordination[key]}
                onChange={(event) =>
                  updateNested(
                    "managementCoordination",
                    key,
                    event.target.value,
                  )
                }
              />
            </Field>
          ))}
          {[
            ["kickoffDetails", "Entrance or kickoff coordination"],
            ["notes", "Coordination notes"],
          ].map(([key, fieldLabel]) => (
            <Field
              error={errors[`managementCoordination.${key}`]}
              key={key}
              label={fieldLabel}
              wide
            >
              <textarea
                className={textAreaClass}
                value={form.managementCoordination[key]}
                onChange={(event) =>
                  updateNested(
                    "managementCoordination",
                    key,
                    event.target.value,
                  )
                }
              />
            </Field>
          ))}

          {plan && (
            <Field error={errors.changeReason} label="Change reason" wide>
              <textarea
                className={textAreaClass}
                value={form.changeReason}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    changeReason: event.target.value,
                  }))
                }
              />
            </Field>
          )}
        </div>
      </Modal>

      <Modal
        open={actionOpen}
        onClose={() => !saving && setActionOpen(false)}
        size="sm"
        title={`${label(action)} AEP`}
        description="The action records your identity, timestamp, comment, old and new status, and exact AEP version."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setActionOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={performAction}
              type="button"
            >
              {saving ? "Processing..." : "Confirm action"}
            </button>
          </>
        }
      >
        <Field
          error={errors.comment || errors.reason || errors.action}
          label={
            ["RETURN", "REVISE"].includes(action)
              ? "Required reason"
              : "Comment"
          }
        >
          <textarea
            className={textAreaClass}
            value={comment}
            onChange={(event) => setComment(event.target.value)}
          />
        </Field>
      </Modal>
    </main>
  );
}
