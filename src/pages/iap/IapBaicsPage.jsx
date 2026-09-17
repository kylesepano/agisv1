import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  CheckCircle2,
  ClipboardCheck,
  Eye,
  FileCheck2,
  History,
  Link2,
  Pencil,
  Plus,
  RotateCcw,
  Save,
  ShieldCheck,
  UserPlus,
  X,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import {
  auditAreaApi,
  auditFocusApi,
  auditUniverseApi,
  baicsApi,
  officeApi,
  userApi,
} from "../../services/api";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SummaryCard from "../../components/ui/SummaryCard";
import StatusBadge from "../../components/ui/StatusBadge";
import Modal from "../../components/ui/Modal";
import { useToast } from "../../ui/toast-context";

const EMPTY = {
  assessmentYear: new Date().getFullYear(),
  name: "",
  responsibleOfficeId: "",
  scopeSummary: "",
  objectives: "",
  boundaries: "",
  exclusions: "",
  limitations: "",
  methodology: "",
  plannedStartDate: "",
  plannedEndDate: "",
  reviewDate: "",
  reportDate: "",
  scopeItems: [],
};
const METHOD_TYPES = [
  "ICQ",
  "INTERVIEW_INQUIRY_FGD",
  "DOCUMENTARY_CRITERIA_REVIEW",
  "PROCESS_NARRATIVE_FLOWCHART",
  "WALKTHROUGH_OBSERVATION",
  "TEST_OF_CONTROLS",
  "OVERSIGHT_REPORT_REVIEW",
  "INTERIM_ANALYSIS",
];
const COMPONENT_LABELS = {
  CONTROL_ENVIRONMENT: "Control Environment",
  RISK_ASSESSMENT: "Risk Assessment",
  CONTROL_ACTIVITIES: "Control Activities",
  INFORMATION_COMMUNICATION: "Information and Communication",
  MONITORING_EVALUATION: "Monitoring and Evaluation",
};

function tone(status) {
  if (["APPROVED", "PUBLISHED"].includes(status)) return "success";
  if (["PENDING_REVIEW", "RESUBMITTED"].includes(status)) return "warning";
  if (["RETURNED", "ARCHIVED", "REJECTED"].includes(status)) return "danger";
  return "slate";
}
function emptyScope(universe = []) {
  const source = universe[0];
  return {
    auditUniverseItemId: source?.id ?? "",
    officeId:
      source?.responsibleOfficeId ?? source?.responsibleOffice?.id ?? "",
    auditAreaId:
      source?.primaryAuditAreaId ?? source?.primaryAuditArea?.id ?? "",
    auditFocusId: "",
    scopeNotes: "",
    boundaries: "",
    exclusions: "",
    limitations: "",
  };
}

export default function IapBaicsPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [records, setRecords] = useState([]);
  const [offices, setOffices] = useState([]);
  const [areas, setAreas] = useState([]);
  const [focuses, setFocuses] = useState([]);
  const [universe, setUniverse] = useState([]);
  const [users, setUsers] = useState([]);
  const [selected, setSelected] = useState(null);
  const [components, setComponents] = useState([]);
  const [componentReadiness, setComponentReadiness] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [form, setForm] = useState(EMPTY);
  const [editing, setEditing] = useState(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [returnComment, setReturnComment] = useState("");
  const [assignment, setAssignment] = useState({
    userId: "",
    roleCode: "ASSESSOR",
    authorityLevel: "",
    assignmentReason: "",
  });
  const [componentEditor, setComponentEditor] = useState(null);
  const [methodEditor, setMethodEditor] = useState(null);
  const [methodForm, setMethodForm] = useState({
    methodType: "ICQ",
    title: "",
    description: "",
    performedBy: "",
    performedOn: new Date().toISOString().slice(0, 10),
    procedure: "",
    result: "",
    limitations: "",
    reviewerId: "",
  });
  const [evidenceForm, setEvidenceForm] = useState({
    componentId: "",
    methodId: "",
    documentVersionId: "",
    description: "",
  });
  const [exceptionForm, setExceptionForm] = useState({
    componentId: "",
    reason: "",
    authorityUserId: "",
    compensatingEvidence: "",
    expiryDate: "",
  });
  const canCreate = hasPermission(user, "iap.baics.create");
  const canUpdate =
    hasPermission(user, "iap.baics.update") ||
    hasPermission(user, "iap.baics.manage-controls");
  const canAssign = hasPermission(user, "iap.baics.assign");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [list, officeList, areaList, focusList, universeList, userList] =
        await Promise.all([
          baicsApi.list({ includeArchived: true, perPage: 100 }),
          officeApi.list(),
          auditAreaApi.list(),
          auditFocusApi.list(),
          auditUniverseApi.list({ perPage: 100 }),
          userApi.list(),
        ]);
      setRecords(list.assessments);
      setOffices(officeList);
      setAreas(areaList);
      setFocuses(focusList);
      setUniverse(universeList.items ?? []);
      setUsers(userList);
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Unable to load BAICS assessments.",
      );
    } finally {
      setLoading(false);
    }
  }, []);
  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);
  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    return records.filter(
      (item) =>
        !query ||
        [
          item.assessmentCode,
          item.name,
          item.status,
          item.responsibleOffice?.name,
        ].some((value) =>
          String(value ?? "")
            .toLowerCase()
            .includes(query),
        ),
    );
  }, [records, search]);
  const stats = useMemo(
    () => ({
      total: records.filter((item) => !item.isArchived).length,
      active: records.filter((item) =>
        ["PLANNING", "IN_PROGRESS"].includes(item.status),
      ).length,
      review: records.filter((item) =>
        ["PENDING_REVIEW", "RESUBMITTED"].includes(item.status),
      ).length,
      published: records.filter((item) => item.status === "PUBLISHED").length,
    }),
    [records],
  );

  async function refreshControls(id) {
    try {
      const result = await baicsApi.components(id);
      setComponents(result.components);
      setComponentReadiness(result.readiness);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to load control components.",
      );
    }
  }
  async function openDetails(item) {
    try {
      const detail = await baicsApi.show(item.id);
      setSelected(detail);
      await refreshControls(item.id);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to load BAICS detail.",
      );
    }
  }
  function openCreate() {
    setEditing(null);
    setForm({
      ...EMPTY,
      scopeItems: universe.length ? [emptyScope(universe)] : [],
    });
    setEditorOpen(true);
  }
  async function openEdit(item) {
    try {
      const detail = await baicsApi.show(item.id);
      setEditing(detail);
      setForm({
        ...EMPTY,
        ...detail,
        scopeItems: (detail.scopeItems ?? []).map((scope) => ({
          auditUniverseItemId: scope.auditUniverseItemId,
          officeId: scope.officeId,
          auditAreaId: scope.auditAreaId,
          auditFocusId: scope.auditFocusId,
          scopeNotes: scope.scopeNotes ?? "",
          boundaries: scope.boundaries ?? "",
          exclusions: scope.exclusions ?? "",
          limitations: scope.limitations ?? "",
        })),
        lockVersion: detail.lockVersion,
      });
      setEditorOpen(true);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to load BAICS detail.",
      );
    }
  }
  function updateForm(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }
  function updateScope(index, key, value) {
    setForm((current) => ({
      ...current,
      scopeItems: current.scopeItems.map((item, itemIndex) => {
        if (itemIndex !== index) return item;
        if (key === "auditUniverseItemId") {
          const source = universe.find(
            (record) => String(record.id) === String(value),
          );
          return {
            ...item,
            auditUniverseItemId: value,
            officeId:
              source?.responsibleOfficeId ??
              source?.responsibleOffice?.id ??
              "",
            auditAreaId:
              source?.primaryAuditAreaId ?? source?.primaryAuditArea?.id ?? "",
            auditFocusId: "",
          };
        }
        return { ...item, [key]: value };
      }),
    }));
  }
  async function save(event) {
    event.preventDefault();
    setSaving(true);
    try {
      const result = editing
        ? await baicsApi.update(editing.id, form)
        : await baicsApi.create(form);
      toast.success(editing ? "BAICS cycle updated." : "BAICS cycle created.");
      setEditorOpen(false);
      await load();
      setSelected(result);
      await refreshControls(result.id);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to save BAICS cycle.",
      );
    } finally {
      setSaving(false);
    }
  }
  async function transition(action) {
    if (!selected) return;
    try {
      const result = await baicsApi.transition(selected.id, action, {
        lockVersion: selected.lockVersion,
        ...(action === "RETURN" ? { comment: returnComment } : {}),
      });
      toast.success(`BAICS cycle ${action.toLowerCase()}d.`);
      setSelected(result);
      setReturnComment("");
      await load();
      await refreshControls(result.id);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to update workflow.",
      );
    }
  }
  async function revise() {
    try {
      const result = await baicsApi.revision(selected.id);
      toast.success("BAICS revision created.");
      setSelected(result);
      await load();
      await refreshControls(result.id);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to create revision.",
      );
    }
  }
  async function assign(event) {
    event.preventDefault();
    try {
      await baicsApi.assign(selected.id, assignment);
      toast.success("Assessment assignment saved.");
      setAssignment({
        userId: "",
        roleCode: "ASSESSOR",
        authorityLevel: "",
        assignmentReason: "",
      });
      setSelected(await baicsApi.show(selected.id));
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to assign user.");
    }
  }
  async function saveControl(event) {
    event.preventDefault();
    try {
      await baicsApi.updateComponent(
        selected.id,
        componentEditor.id,
        componentEditor,
      );
      toast.success("Control component saved.");
      setComponentEditor(null);
      await refreshControls(selected.id);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to save component.");
    }
  }
  async function transitionControl(component, action) {
    try {
      const comment =
        action === "RETURN"
          ? (window.prompt("Return reason") ?? "")
          : undefined;
      const result = await baicsApi.transitionComponent(
        selected.id,
        component.id,
        action,
        {
          lockVersion: component.lockVersion,
          ...(comment !== undefined ? { comment } : {}),
        },
      );
      toast.success(`Component ${action.toLowerCase()}d.`);
      setComponents((current) =>
        current.map((item) => (item.id === result.id ? result : item)),
      );
      await refreshControls(selected.id);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to update component workflow.",
      );
    }
  }
  async function saveMethod(event) {
    event.preventDefault();
    try {
      await baicsApi.createMethod(selected.id, methodEditor, methodForm);
      toast.success("Assessment method recorded.");
      setMethodEditor(null);
      setMethodForm({
        methodType: "ICQ",
        title: "",
        description: "",
        performedBy: "",
        performedOn: new Date().toISOString().slice(0, 10),
        procedure: "",
        result: "",
        limitations: "",
        reviewerId: "",
      });
      await refreshControls(selected.id);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to record method.");
    }
  }
  async function transitionMethod(componentId, method, action) {
    try {
      const comment =
        action === "RETURN"
          ? (window.prompt("Return reason") ?? "")
          : undefined;
      await baicsApi.transitionMethod(
        selected.id,
        componentId,
        method.id,
        action,
        {
          lockVersion: method.lockVersion,
          ...(comment !== undefined ? { comment } : {}),
        },
      );
      toast.success(`Method ${action.toLowerCase()}d.`);
      await refreshControls(selected.id);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to update method workflow.",
      );
    }
  }
  async function linkEvidence(event) {
    event.preventDefault();
    try {
      await baicsApi.linkEvidence(selected.id, evidenceForm.componentId, {
        ...evidenceForm,
        documentVersionId: Number(evidenceForm.documentVersionId),
        methodId: evidenceForm.methodId ? Number(evidenceForm.methodId) : null,
      });
      toast.success("Core Document Version linked.");
      setEvidenceForm({
        componentId: "",
        methodId: "",
        documentVersionId: "",
        description: "",
      });
      await refreshControls(selected.id);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to link evidence.");
    }
  }
  async function saveException(event) {
    event.preventDefault();
    try {
      await baicsApi.createException(selected.id, {
        ...exceptionForm,
        componentId: Number(exceptionForm.componentId),
        authorityUserId: Number(exceptionForm.authorityUserId),
      });
      toast.success("Corroboration exception drafted.");
      setExceptionForm({
        componentId: "",
        reason: "",
        authorityUserId: "",
        compensatingEvidence: "",
        expiryDate: "",
      });
      await refreshControls(selected.id);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to save exception.");
    }
  }

  return (
    <div className="mx-auto w-full max-w-[1600px] px-4 py-5 sm:px-6 lg:px-8">
      <RegistryHeader
        icon={ClipboardCheck}
        title="Baseline Assessment (BAICS)"
        description="Assess internal-control baselines inside IAP with explicit scope, Audit Universe lineage, assignments, and controlled review."
        actions={
          canCreate && (
            <button
              type="button"
              onClick={openCreate}
              className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700"
            >
              <Plus size={17} /> New assessment cycle
            </button>
          )
        }
      />
      <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={ClipboardCheck}
          label="Assessment cycles"
          value={stats.total}
          tone="sky"
        />
        <SummaryCard
          icon={ShieldCheck}
          label="Planning / in progress"
          value={stats.active}
          tone="emerald"
        />
        <SummaryCard
          icon={History}
          label="Awaiting review"
          value={stats.review}
          tone="amber"
        />
        <SummaryCard
          icon={Archive}
          label="Published baselines"
          value={stats.published}
          tone="slate"
        />
      </div>
      <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 className="text-base font-bold text-slate-900">
              Assessment cycles
            </h3>
            <p className="text-xs text-slate-500">
              Each cycle retains exact Audit Universe source snapshots and an
              internal-control assessment baseline.
            </p>
          </div>
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search code, name, office, status"
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:max-w-sm"
          />
        </div>
        {error && (
          <div className="m-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {error}
          </div>
        )}
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500">
            Loading BAICS assessments…
          </div>
        ) : filtered.length === 0 ? (
          <div className="p-10 text-center text-sm text-slate-500">
            No assessment cycles found.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3">Cycle</th>
                  <th className="px-4 py-3">Responsible office</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Version</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filtered.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3">
                      <strong className="block text-slate-900">
                        {item.name}
                      </strong>
                      <span className="text-xs font-semibold text-sky-700">
                        {item.assessmentCode} · {item.assessmentYear}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-slate-600">
                      {item.responsibleOffice?.code} ·{" "}
                      {item.responsibleOffice?.name}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge tone={tone(item.status)}>
                        {item.status.replaceAll("_", " ")}
                      </StatusBadge>
                    </td>
                    <td className="px-4 py-3 text-slate-600">
                      v{item.versionNumber}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => openDetails(item)}
                          className="rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-100"
                          title="View"
                        >
                          <Eye size={16} />
                        </button>
                        {canUpdate &&
                          [
                            "DRAFT",
                            "PLANNING",
                            "IN_PROGRESS",
                            "RETURNED",
                            "RESUBMITTED",
                          ].includes(item.status) && (
                            <button
                              type="button"
                              onClick={() => openEdit(item)}
                              className="rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-100"
                              title="Edit"
                            >
                              <Pencil size={16} />
                            </button>
                          )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
      {selected && (
        <section className="mt-5 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h3 className="text-lg font-bold text-slate-900">
                  {selected.name}
                </h3>
                <StatusBadge tone={tone(selected.status)}>
                  {selected.status.replaceAll("_", " ")}
                </StatusBadge>
              </div>
              <p className="mt-1 text-sm text-slate-500">
                {selected.assessmentCode} · v{selected.versionNumber} ·{" "}
                {selected.responsibleOffice?.name}
              </p>
            </div>
            <button
              type="button"
              onClick={() => setSelected(null)}
              className="self-end rounded-lg p-2 text-slate-500 hover:bg-slate-100 sm:self-start"
            >
              <X size={18} />
            </button>
          </div>
          <div className="grid gap-4 lg:grid-cols-3">
            <div className="lg:col-span-2">
              <h4 className="mb-2 text-sm font-bold text-slate-800">
                Scope and lineage
              </h4>
              <p className="text-sm text-slate-600">{selected.scopeSummary}</p>
              <div className="mt-3 grid gap-2 sm:grid-cols-2">
                {(selected.scopeItems ?? []).map((scope) => (
                  <div
                    key={scope.id}
                    className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs"
                  >
                    <strong className="block text-slate-800">
                      {scope.auditUniverseItem?.subject_code} ·{" "}
                      {scope.auditUniverseItem?.name}
                    </strong>
                    <span className="text-slate-500">
                      {scope.office?.code} · {scope.auditArea?.code} /{" "}
                      {scope.auditFocus?.code}
                    </span>
                    <span className="mt-1 block text-slate-400">
                      Source snapshot captured and read-only.
                    </span>
                  </div>
                ))}
              </div>
            </div>
            <div className="rounded-xl bg-slate-50 p-4">
              <h4 className="text-sm font-bold text-slate-800">
                Cycle readiness
              </h4>
              <p className="mt-1 text-2xl font-bold text-slate-900">
                {selected.readiness?.ready ? "Ready" : "Incomplete"}
              </p>
              <ul className="mt-2 space-y-1 text-xs text-slate-600">
                {Object.entries(selected.readiness?.checks ?? {}).map(
                  ([key, value]) => (
                    <li
                      key={key}
                      className={value ? "text-emerald-700" : "text-amber-700"}
                    >
                      {value ? "✓" : "•"}{" "}
                      {key.replace(
                        /[A-Z]/g,
                        (letter) => ` ${letter.toLowerCase()}`,
                      )}
                    </li>
                  ),
                )}
              </ul>
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            {(selected.availableActions ?? []).map((action) => (
              <button
                key={action}
                type="button"
                onClick={() => transition(action)}
                className="rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-700"
              >
                {action.replaceAll("_", " ")}
              </button>
            ))}
            {canUpdate &&
              ["APPROVED", "PUBLISHED"].includes(selected.status) && (
                <button
                  type="button"
                  onClick={revise}
                  className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
                >
                  <RotateCcw size={14} /> Create revision
                </button>
              )}
          </div>
          {selected.status === "PENDING_REVIEW" && (
            <div className="max-w-xl">
              <label className="text-xs font-semibold text-slate-600">
                Return reason
              </label>
              <textarea
                value={returnComment}
                onChange={(event) => setReturnComment(event.target.value)}
                rows={2}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
          )}
          <section className="rounded-xl border border-slate-200 p-4">
            <div className="flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h3 className="flex items-center gap-2 text-base font-bold text-slate-900">
                  <ShieldCheck size={18} className="text-sky-600" />{" "}
                  Internal-control assessment
                </h3>
                <p className="text-xs text-slate-500">
                  All five components require independent methods, exact Core
                  evidence, a conclusion, and controlled review.
                </p>
              </div>
              <StatusBadge
                tone={componentReadiness?.ready ? "success" : "warning"}
              >
                {componentReadiness?.ready ? "Ready" : "Incomplete"}
              </StatusBadge>
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
              {components.map((component) => {
                const readiness =
                  component.readiness ??
                  componentReadiness?.components?.[component.componentCode];
                return (
                  <article
                    key={component.id}
                    className="rounded-xl border border-slate-200 bg-slate-50 p-3"
                  >
                    <div className="flex items-start justify-between gap-2">
                      <h4 className="text-xs font-bold text-slate-800">
                        {COMPONENT_LABELS[component.componentCode] ??
                          component.componentCode.replaceAll("_", " ")}
                      </h4>
                      <StatusBadge
                        tone={
                          component.status === "APPROVED"
                            ? "success"
                            : readiness?.ready
                              ? "warning"
                              : "slate"
                        }
                      >
                        {component.status}
                      </StatusBadge>
                    </div>
                    <p className="mt-2 line-clamp-3 text-xs text-slate-600">
                      {component.conclusion || "Conclusion required."}
                    </p>
                    <p className="mt-2 text-[11px] text-slate-500">
                      {readiness?.corroboratingMethodCount ?? 0} corroborating ·{" "}
                      {readiness?.methodCount ?? 0} methods
                    </p>
                    <div className="mt-3 flex flex-wrap gap-1">
                      {canUpdate && component.status !== "APPROVED" && (
                        <button
                          type="button"
                          onClick={() =>
                            setComponentEditor({
                              ...component,
                              assessorId:
                                component.assessorId ?? user?.id ?? "",
                              reviewerId: component.reviewerId ?? "",
                              supportingSummary:
                                component.supportingSummary ?? "",
                              limitations: component.limitations ?? "",
                            })
                          }
                          className="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-semibold"
                        >
                          <Pencil size={12} className="mr-1 inline" /> Edit
                        </button>
                      )}
                      {["DRAFT", "RETURNED"].includes(component.status) && (
                        <button
                          type="button"
                          onClick={() => transitionControl(component, "SUBMIT")}
                          className="rounded-md bg-sky-600 px-2 py-1 text-[11px] font-semibold text-white"
                        >
                          Submit
                        </button>
                      )}
                      {component.status === "PENDING_REVIEW" &&
                        hasPermission(user, "iap.baics.return") && (
                          <button
                            type="button"
                            onClick={() =>
                              transitionControl(component, "RETURN")
                            }
                            className="rounded-md border border-amber-300 px-2 py-1 text-[11px] font-semibold text-amber-700"
                          >
                            Return
                          </button>
                        )}
                      {component.status === "PENDING_REVIEW" &&
                        hasPermission(user, "iap.baics.approve") && (
                          <button
                            type="button"
                            onClick={() =>
                              transitionControl(component, "APPROVE")
                            }
                            className="rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-semibold text-white"
                          >
                            Approve
                          </button>
                        )}
                    </div>
                  </article>
                );
              })}
            </div>
            {componentEditor && (
              <form
                onSubmit={saveControl}
                className="mt-4 grid gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 sm:grid-cols-2"
              >
                <h4 className="text-sm font-bold text-slate-800 sm:col-span-2">
                  Edit{" "}
                  {COMPONENT_LABELS[componentEditor.componentCode] ??
                    componentEditor.componentCode}
                </h4>
                <label className="text-xs font-semibold text-slate-600">
                  Assessor
                  <select
                    value={componentEditor.assessorId}
                    onChange={(event) =>
                      setComponentEditor({
                        ...componentEditor,
                        assessorId: event.target.value,
                      })
                    }
                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm"
                  >
                    {users.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="text-xs font-semibold text-slate-600">
                  Independent reviewer
                  <select
                    value={componentEditor.reviewerId}
                    onChange={(event) =>
                      setComponentEditor({
                        ...componentEditor,
                        reviewerId: event.target.value,
                      })
                    }
                    required
                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm"
                  >
                    <option value="">Select reviewer</option>
                    {users.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="text-xs font-semibold text-slate-600 sm:col-span-2">
                  Conclusion
                  <textarea
                    value={componentEditor.conclusion ?? ""}
                    onChange={(event) =>
                      setComponentEditor({
                        ...componentEditor,
                        conclusion: event.target.value,
                      })
                    }
                    required
                    rows={3}
                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm"
                  />
                </label>
                <label className="text-xs font-semibold text-slate-600 sm:col-span-2">
                  Supporting summary
                  <textarea
                    value={componentEditor.supportingSummary ?? ""}
                    onChange={(event) =>
                      setComponentEditor({
                        ...componentEditor,
                        supportingSummary: event.target.value,
                      })
                    }
                    rows={2}
                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm"
                  />
                </label>
                <div className="flex justify-end gap-2 sm:col-span-2">
                  <button
                    type="button"
                    onClick={() => setComponentEditor(null)}
                    className="rounded-lg border border-slate-300 px-3 py-2 text-xs"
                  >
                    Cancel
                  </button>
                  <button className="rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white">
                    Save component
                  </button>
                </div>
              </form>
            )}
          </section>
          <div className="grid gap-4 lg:grid-cols-2">
            <section className="rounded-xl border border-slate-200 p-4">
              <h4 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                <FileCheck2 size={16} /> Assessment methods
              </h4>
              <p className="mt-1 text-xs text-slate-500">
                Use distinct methods and independent performers to corroborate
                each component.
              </p>
              {components.map((component) => (
                <div
                  key={component.id}
                  className="mt-3 rounded-lg bg-slate-50 p-3"
                >
                  <div className="flex items-center justify-between">
                    <strong className="text-xs text-slate-700">
                      {COMPONENT_LABELS[component.componentCode] ??
                        component.componentCode}
                    </strong>
                    {canUpdate && component.status !== "APPROVED" && (
                      <button
                        type="button"
                        onClick={() => {
                          setMethodEditor(component.id);
                          setMethodForm((current) => ({
                            ...current,
                            performedBy: user?.id ?? "",
                            reviewerId: "",
                          }));
                        }}
                        className="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-semibold"
                      >
                        <Plus size={12} className="mr-1 inline" /> Add method
                      </button>
                    )}
                  </div>
                  <div className="mt-2 space-y-1">
                    {(component.methods ?? []).map((method) => (
                      <div
                        key={method.id}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-2 py-2 text-[11px]"
                      >
                        <span>
                          <strong>{method.methodType}</strong>
                          <span className="ml-2 text-slate-500">
                            {method.title}
                          </span>
                        </span>
                        <span className="flex items-center gap-1">
                          <StatusBadge
                            tone={
                              method.status === "APPROVED" ? "success" : "slate"
                            }
                          >
                            {method.status}
                          </StatusBadge>
                          {method.status === "DRAFT" && (
                            <button
                              type="button"
                              onClick={() =>
                                transitionMethod(component.id, method, "SUBMIT")
                              }
                              className="text-sky-700"
                            >
                              Submit
                            </button>
                          )}
                          {method.status === "PENDING_REVIEW" &&
                            hasPermission(user, "iap.baics.review") && (
                              <button
                                type="button"
                                onClick={() =>
                                  transitionMethod(
                                    component.id,
                                    method,
                                    "APPROVE",
                                  )
                                }
                                className="text-emerald-700"
                              >
                                Approve
                              </button>
                            )}
                        </span>
                      </div>
                    ))}
                  </div>
                  {methodEditor === component.id && (
                    <form
                      onSubmit={saveMethod}
                      className="mt-3 grid gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-2"
                    >
                      <select
                        value={methodForm.methodType}
                        onChange={(event) =>
                          setMethodForm({
                            ...methodForm,
                            methodType: event.target.value,
                          })
                        }
                        className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                      >
                        {METHOD_TYPES.map((type) => (
                          <option key={type}>{type}</option>
                        ))}
                      </select>
                      <input
                        value={methodForm.title}
                        onChange={(event) =>
                          setMethodForm({
                            ...methodForm,
                            title: event.target.value,
                          })
                        }
                        placeholder="Method title"
                        required
                        className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                      />
                      <select
                        value={methodForm.performedBy}
                        onChange={(event) =>
                          setMethodForm({
                            ...methodForm,
                            performedBy: event.target.value,
                          })
                        }
                        required
                        className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                      >
                        <option value="">Performed by</option>
                        {users.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.name}
                          </option>
                        ))}
                      </select>
                      <select
                        value={methodForm.reviewerId}
                        onChange={(event) =>
                          setMethodForm({
                            ...methodForm,
                            reviewerId: event.target.value,
                          })
                        }
                        required
                        className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                      >
                        <option value="">Reviewer</option>
                        {users.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.name}
                          </option>
                        ))}
                      </select>
                      <input
                        type="date"
                        value={methodForm.performedOn}
                        onChange={(event) =>
                          setMethodForm({
                            ...methodForm,
                            performedOn: event.target.value,
                          })
                        }
                        required
                        className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                      />
                      <textarea
                        value={methodForm.procedure}
                        onChange={(event) =>
                          setMethodForm({
                            ...methodForm,
                            procedure: event.target.value,
                          })
                        }
                        placeholder="Procedure performed"
                        required
                        className="rounded-md border border-slate-300 px-2 py-2 text-xs sm:col-span-2"
                      />
                      <textarea
                        value={methodForm.result}
                        onChange={(event) =>
                          setMethodForm({
                            ...methodForm,
                            result: event.target.value,
                          })
                        }
                        placeholder="Result"
                        required
                        className="rounded-md border border-slate-300 px-2 py-2 text-xs sm:col-span-2"
                      />
                      <div className="flex justify-end gap-2 sm:col-span-2">
                        <button
                          type="button"
                          onClick={() => setMethodEditor(null)}
                          className="rounded-md border border-slate-300 px-2 py-1 text-xs"
                        >
                          Cancel
                        </button>
                        <button className="rounded-md bg-sky-600 px-2 py-1 text-xs font-semibold text-white">
                          Save method
                        </button>
                      </div>
                    </form>
                  )}
                </div>
              ))}
            </section>
            <div className="space-y-4">
              <form
                onSubmit={linkEvidence}
                className="rounded-xl border border-slate-200 p-4"
              >
                <h4 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                  <Link2 size={16} /> Exact Core evidence
                </h4>
                <p className="mt-1 text-xs text-slate-500">
                  Link an exact Core Document Version. Core retains checksum,
                  size, MIME type, custody, confidentiality, and protected
                  downloads.
                </p>
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                  <select
                    value={evidenceForm.componentId}
                    onChange={(event) =>
                      setEvidenceForm({
                        ...evidenceForm,
                        componentId: event.target.value,
                      })
                    }
                    required
                    className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                  >
                    <option value="">Component</option>
                    {components.map((component) => (
                      <option key={component.id} value={component.id}>
                        {COMPONENT_LABELS[component.componentCode] ??
                          component.componentCode}
                      </option>
                    ))}
                  </select>
                  <input
                    type="number"
                    value={evidenceForm.documentVersionId}
                    onChange={(event) =>
                      setEvidenceForm({
                        ...evidenceForm,
                        documentVersionId: event.target.value,
                      })
                    }
                    required
                    placeholder="Core Document Version ID"
                    className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                  />
                  <input
                    value={evidenceForm.methodId}
                    onChange={(event) =>
                      setEvidenceForm({
                        ...evidenceForm,
                        methodId: event.target.value,
                      })
                    }
                    placeholder="Optional method ID"
                    className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                  />
                  <input
                    value={evidenceForm.description}
                    onChange={(event) =>
                      setEvidenceForm({
                        ...evidenceForm,
                        description: event.target.value,
                      })
                    }
                    placeholder="Evidence description"
                    className="rounded-md border border-slate-300 px-2 py-2 text-xs"
                  />
                </div>
                <button className="mt-3 rounded-md bg-slate-800 px-3 py-2 text-xs font-semibold text-white">
                  Link exact version
                </button>
              </form>
              <form
                onSubmit={saveException}
                className="rounded-xl border border-amber-200 bg-amber-50 p-4"
              >
                <h4 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                  <CheckCircle2 size={16} /> Corroboration exception
                </h4>
                <p className="mt-1 text-xs text-slate-500">
                  Fewer than three methods requires a designated authority,
                  compensating evidence, reason, and expiry.
                </p>
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                  <select
                    value={exceptionForm.componentId}
                    onChange={(event) =>
                      setExceptionForm({
                        ...exceptionForm,
                        componentId: event.target.value,
                      })
                    }
                    required
                    className="rounded-md border border-slate-300 bg-white px-2 py-2 text-xs"
                  >
                    <option value="">Component</option>
                    {components.map((component) => (
                      <option key={component.id} value={component.id}>
                        {COMPONENT_LABELS[component.componentCode] ??
                          component.componentCode}
                      </option>
                    ))}
                  </select>
                  <select
                    value={exceptionForm.authorityUserId}
                    onChange={(event) =>
                      setExceptionForm({
                        ...exceptionForm,
                        authorityUserId: event.target.value,
                      })
                    }
                    required
                    className="rounded-md border border-slate-300 bg-white px-2 py-2 text-xs"
                  >
                    <option value="">Authority</option>
                    {users.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.name}
                      </option>
                    ))}
                  </select>
                  <input
                    type="date"
                    value={exceptionForm.expiryDate}
                    onChange={(event) =>
                      setExceptionForm({
                        ...exceptionForm,
                        expiryDate: event.target.value,
                      })
                    }
                    required
                    className="rounded-md border border-slate-300 bg-white px-2 py-2 text-xs"
                  />
                  <textarea
                    value={exceptionForm.reason}
                    onChange={(event) =>
                      setExceptionForm({
                        ...exceptionForm,
                        reason: event.target.value,
                      })
                    }
                    required
                    placeholder="Reason"
                    className="rounded-md border border-slate-300 bg-white px-2 py-2 text-xs"
                  />
                  <textarea
                    value={exceptionForm.compensatingEvidence}
                    onChange={(event) =>
                      setExceptionForm({
                        ...exceptionForm,
                        compensatingEvidence: event.target.value,
                      })
                    }
                    required
                    placeholder="Compensating evidence"
                    className="rounded-md border border-slate-300 bg-white px-2 py-2 text-xs sm:col-span-2"
                  />
                </div>
                <button className="mt-3 rounded-md bg-amber-600 px-3 py-2 text-xs font-semibold text-white">
                  Draft exception
                </button>
              </form>
            </div>
          </div>
          <div className="grid gap-4 lg:grid-cols-2">
            <section className="rounded-xl border border-slate-200 p-4">
              <h4 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
                <UserPlus size={16} /> Assignments
              </h4>
              {canAssign &&
                !["APPROVED", "PUBLISHED", "ARCHIVED"].includes(
                  selected.status,
                ) && (
                  <form onSubmit={assign} className="grid gap-2 sm:grid-cols-2">
                    <select
                      value={assignment.userId}
                      onChange={(event) =>
                        setAssignment({
                          ...assignment,
                          userId: event.target.value,
                        })
                      }
                      required
                      className="rounded-lg border border-slate-300 px-2 py-2 text-xs"
                    >
                      <option value="">Select user</option>
                      {users.map((item) => (
                        <option key={item.id} value={item.id}>
                          {item.name}
                        </option>
                      ))}
                    </select>
                    <select
                      value={assignment.roleCode}
                      onChange={(event) =>
                        setAssignment({
                          ...assignment,
                          roleCode: event.target.value,
                        })
                      }
                      className="rounded-lg border border-slate-300 px-2 py-2 text-xs"
                    >
                      {[
                        "COORDINATOR",
                        "ASSESSOR",
                        "REVIEWER",
                        "APPROVER",
                        "RESPONDENT",
                      ].map((role) => (
                        <option key={role}>{role}</option>
                      ))}
                    </select>
                    <input
                      value={assignment.assignmentReason}
                      onChange={(event) =>
                        setAssignment({
                          ...assignment,
                          assignmentReason: event.target.value,
                        })
                      }
                      placeholder="Assignment reason"
                      className="rounded-lg border border-slate-300 px-2 py-2 text-xs sm:col-span-2"
                    />
                    <button className="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white sm:col-span-2">
                      Assign user
                    </button>
                  </form>
                )}
              <div className="mt-3 space-y-2">
                {(selected.assignments ?? []).map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs"
                  >
                    <span>
                      <strong>{item.user?.name}</strong>
                      <span className="ml-2 text-slate-500">
                        {item.roleCode}
                      </span>
                    </span>
                    <StatusBadge
                      tone={item.status === "ASSIGNED" ? "success" : "slate"}
                    >
                      {item.status}
                    </StatusBadge>
                  </div>
                ))}
              </div>
            </section>
            <section className="rounded-xl border border-slate-200 p-4">
              <h4 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
                <History size={16} /> Immutable version history
              </h4>
              <div className="space-y-2">
                {(selected.versions ?? []).map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs"
                  >
                    <span>
                      <strong>v{item.versionNumber}</strong>
                      <span className="ml-2 text-slate-500">
                        {item.status} · {item.reason}
                      </span>
                    </span>
                    <span className="font-mono text-[10px] text-slate-400">
                      {item.snapshotHash?.slice(0, 12)}…
                    </span>
                  </div>
                ))}
              </div>
            </section>
          </div>
        </section>
      )}
      <Modal
        open={editorOpen}
        onClose={() => setEditorOpen(false)}
        title={
          editing ? "Edit BAICS assessment cycle" : "New BAICS assessment cycle"
        }
        size="xl"
      >
        <form onSubmit={save} className="space-y-5">
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="text-xs font-semibold text-slate-600">
              Assessment year
              <input
                type="number"
                value={form.assessmentYear}
                onChange={(event) =>
                  updateForm("assessmentYear", event.target.value)
                }
                required
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </label>
            <label className="text-xs font-semibold text-slate-600">
              Cycle name
              <input
                value={form.name}
                onChange={(event) => updateForm("name", event.target.value)}
                required
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </label>
            <label className="text-xs font-semibold text-slate-600 sm:col-span-2">
              Responsible office
              <select
                value={form.responsibleOfficeId}
                onChange={(event) =>
                  updateForm("responsibleOfficeId", event.target.value)
                }
                required
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Select office</option>
                {offices.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.code} · {item.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-xs font-semibold text-slate-600 sm:col-span-2">
              Scope summary
              <textarea
                value={form.scopeSummary}
                onChange={(event) =>
                  updateForm("scopeSummary", event.target.value)
                }
                required
                rows={2}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </label>
            <label className="text-xs font-semibold text-slate-600 sm:col-span-2">
              Objectives
              <textarea
                value={form.objectives}
                onChange={(event) =>
                  updateForm("objectives", event.target.value)
                }
                required
                rows={2}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </label>
            <label className="text-xs font-semibold text-slate-600 sm:col-span-2">
              Methodology
              <textarea
                value={form.methodology}
                onChange={(event) =>
                  updateForm("methodology", event.target.value)
                }
                required
                rows={2}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </label>
          </div>
          <div className="rounded-xl border border-slate-200 p-3">
            <div className="mb-3 flex items-center justify-between">
              <div>
                <h4 className="text-sm font-bold text-slate-800">
                  Audit Universe source scope
                </h4>
                <p className="text-xs text-slate-500">
                  Every source is captured as an immutable snapshot with Office,
                  Area, and Focus.
                </p>
              </div>
              <button
                type="button"
                onClick={() =>
                  setForm((current) => ({
                    ...current,
                    scopeItems: [...current.scopeItems, emptyScope(universe)],
                  }))
                }
                className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-semibold"
              >
                <Plus size={13} /> Add source
              </button>
            </div>
            {form.scopeItems.map((item, index) => (
              <div
                key={index}
                className="mb-3 grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-2"
              >
                <select
                  value={item.auditUniverseItemId}
                  onChange={(event) =>
                    updateScope(
                      index,
                      "auditUniverseItemId",
                      event.target.value,
                    )
                  }
                  required
                  className="rounded-lg border border-slate-300 px-2 py-2 text-xs"
                >
                  <option value="">Audit Universe subject</option>
                  {universe.map((source) => (
                    <option key={source.id} value={source.id}>
                      {source.subjectCode} · {source.name}
                    </option>
                  ))}
                </select>
                <select
                  value={item.officeId}
                  onChange={(event) =>
                    updateScope(index, "officeId", event.target.value)
                  }
                  required
                  className="rounded-lg border border-slate-300 px-2 py-2 text-xs"
                >
                  <option value="">Scope office</option>
                  {offices.map((office) => (
                    <option key={office.id} value={office.id}>
                      {office.code} · {office.name}
                    </option>
                  ))}
                </select>
                <select
                  value={item.auditAreaId}
                  onChange={(event) =>
                    updateScope(index, "auditAreaId", event.target.value)
                  }
                  required
                  className="rounded-lg border border-slate-300 px-2 py-2 text-xs"
                >
                  <option value="">Audit Area</option>
                  {areas.map((area) => (
                    <option key={area.id} value={area.id}>
                      {area.code} · {area.name}
                    </option>
                  ))}
                </select>
                <select
                  value={item.auditFocusId}
                  onChange={(event) =>
                    updateScope(index, "auditFocusId", event.target.value)
                  }
                  required
                  className="rounded-lg border border-slate-300 px-2 py-2 text-xs"
                >
                  <option value="">Audit Focus</option>
                  {focuses
                    .filter(
                      (focus) =>
                        !item.auditAreaId ||
                        String(focus.auditAreaId ?? focus.audit_area_id) ===
                          String(item.auditAreaId),
                    )
                    .map((focus) => (
                      <option key={focus.id} value={focus.id}>
                        {focus.code} · {focus.name}
                      </option>
                    ))}
                </select>
              </div>
            ))}
          </div>
          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={() => setEditorOpen(false)}
              className="rounded-lg border border-slate-300 px-4 py-2 text-sm"
            >
              Cancel
            </button>
            <button
              disabled={saving}
              className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              <Save size={16} /> {saving ? "Saving…" : "Save cycle"}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
