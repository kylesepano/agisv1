import { useCallback, useEffect, useMemo, useState } from "react";
import {
  BarChart3,
  Check,
  Download,
  FilePlus2,
  FileText,
  LockKeyhole,
  Plus,
  RefreshCw,
  ShieldCheck,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { baicsApi, officeApi, userApi } from "../../services/api";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { useToast } from "../../ui/toast-context";

const CONTROL_STATUSES = [
  "Existing",
  "Partially Designed",
  "Not Designed",
  "Operating Effectively",
  "Partially Effective",
  "Not Operating",
  "Control Gap",
  "Deficiency",
  "Breakdown",
];
const COMPONENTS = [
  ["CONTROL_ENVIRONMENT", "Control Environment"],
  ["RISK_ASSESSMENT", "Risk Assessment"],
  ["CONTROL_ACTIVITIES", "Control Activities"],
  ["INFORMATION_COMMUNICATION", "Information and Communication"],
  ["MONITORING_EVALUATION", "Monitoring and Evaluation"],
];
const EMPTY_CONTROL = {
  scopeItemId: "",
  componentId: "",
  controlCode: "",
  processStep: "",
  responsibleUnit: "",
  controlOwnerOfficeId: "",
  controlOwnerUserId: "",
  objective: "",
  relatedRisk: "",
  controlDescription: "",
  expectedResult: "",
  controlType: "PREVENTIVE",
  executionMode: "MANUAL",
  frequency: "",
  evidenceProduced: "",
  approvalRequired: false,
  segregationOfDutiesRequired: false,
  designAssessment: "",
  operatingAssessment: "",
  controlStatus: "Existing",
  deficiencyClassification: "",
  limitationDetails: "",
  gapDetails: "",
  breakdownDetails: "",
  contradictionDetails: "",
  recommendationAction: "",
  reviewerId: "",
  methodIds: [],
  evidenceLinkIds: [],
};
const EMPTY_ANALYSIS = {
  analysisCode: "",
  title: "",
  analysisPeriodStart: "",
  analysisPeriodEnd: "",
  analysisNarrative: "",
  findingsSummary: "",
  recommendationsSummary: "",
  limitations: "",
  reviewerId: "",
};
const EMPTY_REPORT = {
  title: "Baseline Assessment Report",
  executiveSummary: "",
  objectivesScopeMethodology: "",
  overallFindings: "",
  controlGapSummary: "",
  recommendationsSummary: "",
  limitationsExceptions: "",
  controlIds: [],
  interimAnalysisIds: [],
  reviewerId: "",
};

function tone(status) {
  if (["APPROVED", "ISSUED"].includes(status)) return "success";
  if (["PENDING_REVIEW", "RETURNED"].includes(status)) return "warning";
  if (["SUPERSEDED"].includes(status)) return "danger";
  return "slate";
}
function label(value) {
  return String(value ?? "").replaceAll("_", " ");
}
function Field({ label: title, className = "", children }) {
  return (
    <label
      className={`block text-xs font-semibold text-slate-600 ${className}`}
    >
      {title}
      {children}
    </label>
  );
}
function Input(props) {
  return (
    <input
      {...props}
      className={`mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${props.className ?? ""}`}
    />
  );
}
function Textarea(props) {
  return (
    <textarea
      {...props}
      className={`mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${props.className ?? ""}`}
    />
  );
}

export default function IapBaicsControlUniversePage() {
  const { user } = useAuth();
  const toast = useToast();
  const canManage =
    hasPermission(user, "iap.baics.manage-controls") ||
    hasPermission(user, "iap.baics.update");
  const canApprove = hasPermission(user, "iap.baics.approve");
  const canExport = hasPermission(user, "iap.baics.export");
  const [records, setRecords] = useState([]);
  const [selected, setSelected] = useState(null);
  const [controls, setControls] = useState([]);
  const [readiness, setReadiness] = useState(null);
  const [analyses, setAnalyses] = useState([]);
  const [reports, setReports] = useState([]);
  const [offices, setOffices] = useState([]);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [controlEditor, setControlEditor] = useState(null);
  const [analysisEditor, setAnalysisEditor] = useState(null);
  const [reportEditor, setReportEditor] = useState(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [list, officeList, userList] = await Promise.all([
        baicsApi.list({ perPage: 100 }),
        officeApi.list(),
        userApi.list(),
      ]);
      setRecords(list.assessments ?? []);
      setOffices(officeList);
      setUsers(userList);
      setSelected((current) => current ?? list.assessments?.[0] ?? null);
    } catch (e) {
      setError(
        e instanceof Error
          ? e.message
          : "Unable to load BAICS control universe.",
      );
    } finally {
      setLoading(false);
    }
  }, []);
  useEffect(() => {
    const timer = window.setTimeout(() => {
      void load();
    }, 0);
    return () => window.clearTimeout(timer);
  }, [load]);
  const selectCycle = useCallback(
    async (cycle) => {
      setSelected(cycle);
      try {
        const [detail, controlResult, analysisResult, reportResult] =
          await Promise.all([
            baicsApi.show(cycle.id),
            baicsApi.controls(cycle.id),
            baicsApi.interimAnalyses(cycle.id),
            baicsApi.reports(cycle.id),
          ]);
        setSelected(detail ?? cycle);
        setControls(controlResult.controls);
        setReadiness(controlResult.readiness ?? reportResult.readiness);
        setAnalyses(analysisResult);
        setReports(reportResult.reports);
      } catch (e) {
        toast.error(
          e instanceof Error ? e.message : "Unable to load BAICS outputs.",
        );
      }
    },
    [toast],
  );
  useEffect(() => {
    if (!selected) return undefined;
    const timer = window.setTimeout(() => {
      void selectCycle(selected);
    }, 0);
    return () => window.clearTimeout(timer);
  }, [selected?.id]); // eslint-disable-line react-hooks/exhaustive-deps
  const cycleOptions = useMemo(
    () => records.filter((item) => !["ARCHIVED"].includes(item.status)),
    [records],
  );

  async function saveControl(event) {
    event.preventDefault();
    if (!selected) return;
    setSaving(true);
    try {
      const payload = {
        ...controlEditor,
        methodIds: String(controlEditor.methodIdsText ?? "")
          .split(",")
          .map((value) => Number(value.trim()))
          .filter(Boolean),
        evidenceLinkIds: String(controlEditor.evidenceLinkIdsText ?? "")
          .split(",")
          .map((value) => Number(value.trim()))
          .filter(Boolean),
      };
      delete payload.methodIdsText;
      delete payload.evidenceLinkIdsText;
      const result = controlEditor.id
        ? await baicsApi.updateControl(selected.id, controlEditor.id, {
            ...payload,
            lockVersion: controlEditor.lockVersion,
          })
        : await baicsApi.createControl(selected.id, payload);
      toast.success(
        controlEditor.id
          ? "Control Universe record updated."
          : "Control Universe record created.",
      );
      setControlEditor(null);
      await selectCycle(selected);
      return result;
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to save control.");
    } finally {
      setSaving(false);
    }
  }
  async function transitionControl(control, action) {
    try {
      await baicsApi.transitionControl(selected.id, control.id, action, {
        lockVersion: control.lockVersion,
        ...(action === "RETURN"
          ? { comment: window.prompt("Return reason") ?? "" }
          : {}),
      });
      toast.success(`Control ${action.toLowerCase()}d.`);
      await selectCycle(selected);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to update control.");
    }
  }
  async function saveAnalysis(event) {
    event.preventDefault();
    if (!selected) return;
    try {
      if (analysisEditor.id)
        await baicsApi.updateInterimAnalysis(
          selected.id,
          analysisEditor.id,
          analysisEditor,
        );
      else await baicsApi.createInterimAnalysis(selected.id, analysisEditor);
      toast.success("Interim analysis saved.");
      setAnalysisEditor(null);
      await selectCycle(selected);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to save interim analysis.",
      );
    }
  }
  async function transitionAnalysis(analysis, action) {
    try {
      await baicsApi.transitionInterimAnalysis(
        selected.id,
        analysis.id,
        action,
        {
          ...(action === "RETURN"
            ? { comment: window.prompt("Return reason") ?? "" }
            : {}),
        },
      );
      toast.success(`Interim analysis ${action.toLowerCase()}d.`);
      await selectCycle(selected);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to update interim analysis.",
      );
    }
  }
  async function saveReport(event) {
    event.preventDefault();
    if (!selected) return;
    try {
      if (reportEditor.id)
        await baicsApi.updateReport(selected.id, reportEditor.id, reportEditor);
      else await baicsApi.createReport(selected.id, reportEditor);
      toast.success("Baseline Assessment Report saved.");
      setReportEditor(null);
      await selectCycle(selected);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to save BAR.");
    }
  }
  async function transitionReport(report, action) {
    try {
      await baicsApi.transitionReport(selected.id, report.id, action, {
        ...(action === "RETURN"
          ? { comment: window.prompt("Return reason") ?? "" }
          : {}),
      });
      toast.success(`BAR ${action.toLowerCase()}d.`);
      await selectCycle(selected);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Unable to update BAR.");
    }
  }

  return (
    <div className="space-y-5 p-4 sm:p-6">
      <RegistryHeader
        icon={ShieldCheck}
        eyebrow="Internal Audit Planning"
        title="Control Universe & Baseline Assessment Report"
        description="Translate approved BAICS evidence into a traceable control inventory and reproducible planning baseline report."
        actions={
          <button
            type="button"
            onClick={load}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold"
          >
            <RefreshCw size={15} /> Refresh
          </button>
        }
      />
      <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 className="text-base font-bold text-slate-900">
              Assessment cycle
            </h2>
            <p className="text-xs text-slate-500">
              Control Universe records and BARs remain inside the selected BAICS
              cycle.
            </p>
          </div>
          <select
            value={selected?.id ?? ""}
            onChange={(event) => {
              const next = records.find(
                (item) => String(item.id) === event.target.value,
              );
              if (next) setSelected(next);
            }}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm md:max-w-xl"
          >
            <option value="">Select cycle</option>
            {cycleOptions.map((item) => (
              <option key={item.id} value={item.id}>
                {item.assessmentCode} · {item.name} · {item.status}
              </option>
            ))}
          </select>
        </div>
        {error && (
          <div className="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {error}
          </div>
        )}
        {loading && (
          <p className="mt-4 text-sm text-slate-500">Loading cycles…</p>
        )}
      </section>
      {selected && (
        <>
          <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div className="rounded-xl border border-sky-200 bg-sky-50 p-4">
              <BarChart3 className="text-sky-700" size={20} />
              <strong className="mt-2 block text-2xl text-slate-900">
                {readiness?.controlCount ?? controls.length}
              </strong>
              <span className="text-xs text-slate-600">
                Controls in universe
              </span>
            </div>
            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
              <Check className="text-emerald-700" size={20} />
              <strong className="mt-2 block text-2xl text-slate-900">
                {readiness?.approvedControlCount ?? 0}
              </strong>
              <span className="text-xs text-slate-600">Approved controls</span>
            </div>
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
              <ShieldCheck className="text-amber-700" size={20} />
              <strong className="mt-2 block text-2xl text-slate-900">
                {readiness?.classifiedControlCount ?? 0}
              </strong>
              <span className="text-xs text-slate-600">
                Gaps / deficiencies
              </span>
            </div>
            <div className="rounded-xl border border-violet-200 bg-violet-50 p-4">
              <FileText className="text-violet-700" size={20} />
              <strong className="mt-2 block text-2xl text-slate-900">
                {readiness?.approvedInterimAnalysisCount ?? 0}
              </strong>
              <span className="text-xs text-slate-600">Approved analyses</span>
            </div>
            <div
              className={`rounded-xl border p-4 ${readiness?.ready ? "border-emerald-200 bg-emerald-50" : "border-rose-200 bg-rose-50"}`}
            >
              <LockKeyhole
                className={
                  readiness?.ready ? "text-emerald-700" : "text-rose-700"
                }
                size={20}
              />
              <strong className="mt-2 block text-sm text-slate-900">
                {readiness?.ready ? "BAR source-ready" : "Sources incomplete"}
              </strong>
              <span className="text-xs text-slate-600">
                Approved evidence and controls required
              </span>
            </div>
          </section>
          <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-slate-200 p-4">
              <div>
                <h2 className="text-base font-bold text-slate-900">
                  Control Universe
                </h2>
                <p className="text-xs text-slate-500">
                  Every control retains process, owner, component,
                  classification, method, and exact evidence lineage.
                </p>
              </div>
              {canManage &&
                !["APPROVED", "PUBLISHED", "ARCHIVED"].includes(
                  selected.status,
                ) && (
                  <button
                    type="button"
                    onClick={() =>
                      setControlEditor({
                        ...EMPTY_CONTROL,
                        controlOwnerOfficeId: selected.responsibleOfficeId,
                        reviewerId: "",
                      })
                    }
                    className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white"
                  >
                    <Plus size={15} /> Add control
                  </button>
                )}
            </div>
            <div className="divide-y divide-slate-100">
              {controls.length === 0 ? (
                <p className="p-8 text-center text-sm text-slate-500">
                  No controls have been drafted for this cycle.
                </p>
              ) : (
                controls.map((control) => (
                  <article key={control.id} className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <strong className="text-sm text-slate-900">
                            {control.controlCode}
                          </strong>
                          <StatusBadge tone={tone(control.status)}>
                            {label(control.status)}
                          </StatusBadge>
                          <span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600">
                            {control.controlStatus}
                          </span>
                          {control.deficiencyClassification && (
                            <span className="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800">
                              {control.deficiencyClassification}
                            </span>
                          )}
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                          {control.processStep} ·{" "}
                          {control.scopeItem?.sourceSnapshot?.name ??
                            "Scoped process"}{" "}
                          · {control.controlOwnerOffice?.name ?? "Owner office"}
                        </p>
                        <p className="mt-2 max-w-4xl text-sm text-slate-700">
                          {control.controlDescription}
                        </p>
                        <p className="mt-2 text-xs text-slate-500">
                          {control.methods?.length ?? 0} methods ·{" "}
                          {control.evidence?.length ?? 0} exact evidence links ·{" "}
                          {control.readiness?.ready === false
                            ? "Incomplete readiness"
                            : "Traceability recorded"}
                        </p>
                      </div>
                      <div className="flex shrink-0 flex-wrap gap-2">
                        {canManage &&
                          ["DRAFT", "RETURNED"].includes(control.status) && (
                            <button
                              type="button"
                              onClick={() =>
                                setControlEditor({
                                  ...control,
                                  methodIdsText: (control.methods ?? [])
                                    .map((item) => item.id)
                                    .join(", "),
                                  evidenceLinkIdsText: (control.evidence ?? [])
                                    .map(
                                      (item) => item.evidenceLinkId ?? item.id,
                                    )
                                    .join(", "),
                                  controlOwnerOfficeId:
                                    control.controlOwnerOfficeId ?? "",
                                  reviewerId: control.reviewer?.id ?? "",
                                })
                              }
                              className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold"
                            >
                              Edit
                            </button>
                          )}
                        {["DRAFT", "RETURNED"].includes(control.status) && (
                          <button
                            type="button"
                            onClick={() => transitionControl(control, "SUBMIT")}
                            className="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white"
                          >
                            Submit
                          </button>
                        )}
                        {control.status === "PENDING_REVIEW" && canApprove && (
                          <>
                            <button
                              type="button"
                              onClick={() =>
                                transitionControl(control, "RETURN")
                              }
                              className="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-800"
                            >
                              Return
                            </button>
                            <button
                              type="button"
                              onClick={() =>
                                transitionControl(control, "APPROVE")
                              }
                              className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white"
                            >
                              Approve
                            </button>
                          </>
                        )}
                      </div>
                    </div>
                  </article>
                ))
              )}
            </div>
          </section>
          <section className="grid gap-5 lg:grid-cols-2">
            <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="flex items-center justify-between">
                <div>
                  <h2 className="text-base font-bold text-slate-900">
                    Interim analysis
                  </h2>
                  <p className="text-xs text-slate-500">
                    Capture approved analysis sources for the BAR.
                  </p>
                </div>
                {canManage && (
                  <button
                    type="button"
                    onClick={() =>
                      setAnalysisEditor({ ...EMPTY_ANALYSIS, reviewerId: "" })
                    }
                    className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-semibold"
                  >
                    <Plus size={14} /> Add
                  </button>
                )}
              </div>
              <div className="mt-3 space-y-2">
                {analyses.length === 0 ? (
                  <p className="text-sm text-slate-500">
                    No interim analysis has been drafted.
                  </p>
                ) : (
                  analyses.map((analysis) => (
                    <div
                      key={analysis.id}
                      className="rounded-lg border border-slate-200 p-3"
                    >
                      <div className="flex items-center justify-between gap-2">
                        <strong className="text-xs text-slate-800">
                          {analysis.analysisCode} · {analysis.title}
                        </strong>
                        <StatusBadge tone={tone(analysis.status)}>
                          {label(analysis.status)}
                        </StatusBadge>
                      </div>
                      <p className="mt-1 line-clamp-2 text-xs text-slate-600">
                        {analysis.analysisNarrative}
                      </p>
                      <div className="mt-2 flex gap-2">
                        {canManage &&
                          ["DRAFT", "RETURNED"].includes(analysis.status) && (
                            <>
                              <button
                                type="button"
                                onClick={() => setAnalysisEditor(analysis)}
                                className="text-xs font-semibold text-sky-700"
                              >
                                Edit
                              </button>
                              <button
                                type="button"
                                onClick={() =>
                                  transitionAnalysis(analysis, "SUBMIT")
                                }
                                className="text-xs font-semibold text-sky-700"
                              >
                                Submit
                              </button>
                            </>
                          )}
                        {analysis.status === "PENDING_REVIEW" && canApprove && (
                          <>
                            <button
                              type="button"
                              onClick={() =>
                                transitionAnalysis(analysis, "RETURN")
                              }
                              className="text-xs font-semibold text-amber-700"
                            >
                              Return
                            </button>
                            <button
                              type="button"
                              onClick={() =>
                                transitionAnalysis(analysis, "APPROVE")
                              }
                              className="text-xs font-semibold text-emerald-700"
                            >
                              Approve
                            </button>
                          </>
                        )}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </section>
            <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="flex items-center justify-between">
                <div>
                  <h2 className="text-base font-bold text-slate-900">
                    Baseline Assessment Report
                  </h2>
                  <p className="text-xs text-slate-500">
                    Assemble approved controls and analyses into an immutable,
                    protected report.
                  </p>
                </div>
                {canManage && (
                  <button
                    type="button"
                    onClick={() =>
                      setReportEditor({
                        ...EMPTY_REPORT,
                        controlIds: controls
                          .filter((item) => item.status === "APPROVED")
                          .map((item) => item.id),
                        interimAnalysisIds: analyses
                          .filter((item) => item.status === "APPROVED")
                          .map((item) => item.id),
                        reviewerId: "",
                      })
                    }
                    className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-semibold"
                  >
                    <FilePlus2 size={14} /> New BAR
                  </button>
                )}
              </div>
              <div className="mt-3 space-y-2">
                {reports.length === 0 ? (
                  <p className="text-sm text-slate-500">
                    No BAR has been drafted.
                  </p>
                ) : (
                  reports.map((report) => (
                    <div
                      key={report.id}
                      className="rounded-lg border border-slate-200 p-3"
                    >
                      <div className="flex items-center justify-between gap-2">
                        <strong className="text-xs text-slate-800">
                          {report.reportCode} · {report.title}
                        </strong>
                        <StatusBadge tone={tone(report.status)}>
                          {label(report.status)}
                        </StatusBadge>
                      </div>
                      <p className="mt-1 text-xs text-slate-500">
                        v{report.versionNumber} · {report.controls?.length ?? 0}{" "}
                        controls · {report.interimAnalyses?.length ?? 0} interim
                        analyses
                      </p>
                      <div className="mt-2 flex flex-wrap gap-2">
                        {canManage &&
                          ["DRAFT", "RETURNED"].includes(report.status) && (
                            <button
                              type="button"
                              onClick={() =>
                                setReportEditor({
                                  ...report,
                                  controlIds: (report.controls ?? []).map(
                                    (item) => item.id,
                                  ),
                                  interimAnalysisIds: (
                                    report.interimAnalyses ?? []
                                  ).map((item) => item.id),
                                  reviewerId: report.reviewer?.id ?? "",
                                })
                              }
                              className="text-xs font-semibold text-sky-700"
                            >
                              Edit
                            </button>
                          )}
                        {["DRAFT", "RETURNED"].includes(report.status) && (
                          <button
                            type="button"
                            onClick={() => transitionReport(report, "SUBMIT")}
                            className="text-xs font-semibold text-sky-700"
                          >
                            Submit
                          </button>
                        )}
                        {report.status === "PENDING_REVIEW" && canApprove && (
                          <>
                            <button
                              type="button"
                              onClick={() => transitionReport(report, "RETURN")}
                              className="text-xs font-semibold text-amber-700"
                            >
                              Return
                            </button>
                            <button
                              type="button"
                              onClick={() =>
                                transitionReport(report, "APPROVE")
                              }
                              className="text-xs font-semibold text-emerald-700"
                            >
                              Approve
                            </button>
                          </>
                        )}
                        {report.status === "APPROVED" && canApprove && (
                          <button
                            type="button"
                            onClick={() => transitionReport(report, "ISSUE")}
                            className="text-xs font-semibold text-emerald-700"
                          >
                            Issue
                          </button>
                        )}
                        {["APPROVED", "ISSUED", "SUPERSEDED"].includes(
                          report.status,
                        ) &&
                          canExport && (
                            <>
                              <button
                                type="button"
                                onClick={() =>
                                  baicsApi.downloadReport(
                                    selected.id,
                                    report.id,
                                    "pdf",
                                  )
                                }
                                className="inline-flex items-center gap-1 text-xs font-semibold text-slate-700"
                              >
                                <Download size={13} /> PDF
                              </button>
                              <button
                                type="button"
                                onClick={() =>
                                  baicsApi.downloadReport(
                                    selected.id,
                                    report.id,
                                    "csv",
                                  )
                                }
                                className="inline-flex items-center gap-1 text-xs font-semibold text-slate-700"
                              >
                                <Download size={13} /> CSV
                              </button>
                            </>
                          )}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </section>
          </section>
        </>
      )}
      {controlEditor && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 p-4">
          <form
            onSubmit={saveControl}
            className="mx-auto max-w-5xl rounded-2xl bg-white p-5 shadow-xl"
          >
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-slate-900">
                {controlEditor.id
                  ? "Edit control"
                  : "Add Control Universe record"}
              </h2>
              <button
                type="button"
                onClick={() => setControlEditor(null)}
                className="text-sm text-slate-500"
              >
                Close
              </button>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <Field label="Control code">
                <Input
                  value={controlEditor.controlCode ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      controlCode: e.target.value,
                    })
                  }
                  required
                />
              </Field>
              <Field label="Process step">
                <Input
                  value={controlEditor.processStep ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      processStep: e.target.value,
                    })
                  }
                  required
                />
              </Field>
              <Field label="Component">
                <select
                  value={controlEditor.componentId ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      componentId: e.target.value,
                    })
                  }
                  required
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select component</option>
                  {COMPONENTS.map(([code, name]) => (
                    <option
                      key={code}
                      value={
                        (selected?.components ?? []).find(
                          (item) => item.componentCode === code,
                        )?.id ?? ""
                      }
                    >
                      {name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Scope item ID">
                <Input
                  type="number"
                  value={controlEditor.scopeItemId ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      scopeItemId: e.target.value,
                    })
                  }
                  required
                />
              </Field>
              <Field label="Owner office">
                <select
                  value={controlEditor.controlOwnerOfficeId ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      controlOwnerOfficeId: e.target.value,
                    })
                  }
                  required
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select office</option>
                  {offices.map((office) => (
                    <option key={office.id} value={office.id}>
                      {office.code} · {office.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Control owner">
                <select
                  value={controlEditor.controlOwnerUserId ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      controlOwnerUserId: e.target.value,
                    })
                  }
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select user</option>
                  {users.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Reviewer">
                <select
                  value={controlEditor.reviewerId ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      reviewerId: e.target.value,
                    })
                  }
                  required
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select reviewer</option>
                  {users.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Control status">
                <select
                  value={controlEditor.controlStatus ?? "Existing"}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      controlStatus: e.target.value,
                    })
                  }
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  {CONTROL_STATUSES.map((item) => (
                    <option key={item}>{item}</option>
                  ))}
                </select>
              </Field>
              <Field label="Control type">
                <select
                  value={controlEditor.controlType ?? "PREVENTIVE"}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      controlType: e.target.value,
                    })
                  }
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option>PREVENTIVE</option>
                  <option>DETECTIVE</option>
                  <option>HYBRID</option>
                </select>
              </Field>
              <Field label="Execution mode">
                <select
                  value={controlEditor.executionMode ?? "MANUAL"}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      executionMode: e.target.value,
                    })
                  }
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option>MANUAL</option>
                  <option>AUTOMATED</option>
                  <option>HYBRID</option>
                </select>
              </Field>
              <Field label="Objective" className="sm:col-span-2">
                <Textarea
                  value={controlEditor.objective ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      objective: e.target.value,
                    })
                  }
                  required
                  rows={2}
                />
              </Field>
              <Field label="Related risk" className="sm:col-span-2">
                <Textarea
                  value={controlEditor.relatedRisk ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      relatedRisk: e.target.value,
                    })
                  }
                  required
                  rows={2}
                />
              </Field>
              <Field label="Control description" className="sm:col-span-2">
                <Textarea
                  value={controlEditor.controlDescription ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      controlDescription: e.target.value,
                    })
                  }
                  required
                  rows={2}
                />
              </Field>
              <Field label="Expected result" className="sm:col-span-2">
                <Textarea
                  value={controlEditor.expectedResult ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      expectedResult: e.target.value,
                    })
                  }
                  required
                  rows={2}
                />
              </Field>
              <Field label="Design assessment" className="sm:col-span-2">
                <Textarea
                  value={controlEditor.designAssessment ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      designAssessment: e.target.value,
                    })
                  }
                  required
                  rows={2}
                />
              </Field>
              <Field label="Operating assessment" className="sm:col-span-2">
                <Textarea
                  value={controlEditor.operatingAssessment ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      operatingAssessment: e.target.value,
                    })
                  }
                  required
                  rows={2}
                />
              </Field>
              <Field label="Classification">
                <Input
                  value={controlEditor.deficiencyClassification ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      deficiencyClassification: e.target.value,
                    })
                  }
                  placeholder="GAP / DEFICIENCY / BREAKDOWN"
                />
              </Field>
              <Field label="Gap / breakdown basis" className="sm:col-span-2">
                <Textarea
                  value={
                    controlEditor.gapDetails ??
                    controlEditor.breakdownDetails ??
                    ""
                  }
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      gapDetails: e.target.value,
                    })
                  }
                  rows={2}
                />
              </Field>
              <Field
                label="Recommendation / improvement"
                className="sm:col-span-2"
              >
                <Textarea
                  value={controlEditor.recommendationAction ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      recommendationAction: e.target.value,
                    })
                  }
                  rows={2}
                />
              </Field>
              <Field
                label="Approved method IDs (comma separated)"
                className="sm:col-span-2"
              >
                <Input
                  value={controlEditor.methodIdsText ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      methodIdsText: e.target.value,
                    })
                  }
                  placeholder="e.g. 12,13"
                />
              </Field>
              <Field
                label="Evidence link IDs (comma separated)"
                className="sm:col-span-2"
              >
                <Input
                  value={controlEditor.evidenceLinkIdsText ?? ""}
                  onChange={(e) =>
                    setControlEditor({
                      ...controlEditor,
                      evidenceLinkIdsText: e.target.value,
                    })
                  }
                  placeholder="e.g. 41,42"
                />
              </Field>
            </div>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setControlEditor(null)}
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              >
                Cancel
              </button>
              <button
                disabled={saving}
                className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white"
              >
                Save control
              </button>
            </div>
          </form>
        </div>
      )}
      {analysisEditor && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 p-4">
          <form
            onSubmit={saveAnalysis}
            className="mx-auto max-w-3xl rounded-2xl bg-white p-5 shadow-xl"
          >
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold">Interim analysis</h2>
              <button
                type="button"
                onClick={() => setAnalysisEditor(null)}
                className="text-sm text-slate-500"
              >
                Close
              </button>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <Field label="Analysis code">
                <Input
                  value={analysisEditor.analysisCode ?? ""}
                  onChange={(e) =>
                    setAnalysisEditor({
                      ...analysisEditor,
                      analysisCode: e.target.value,
                    })
                  }
                  required
                />
              </Field>
              <Field label="Title">
                <Input
                  value={analysisEditor.title ?? ""}
                  onChange={(e) =>
                    setAnalysisEditor({
                      ...analysisEditor,
                      title: e.target.value,
                    })
                  }
                  required
                />
              </Field>
              <Field label="Reviewer">
                <select
                  value={analysisEditor.reviewerId ?? ""}
                  onChange={(e) =>
                    setAnalysisEditor({
                      ...analysisEditor,
                      reviewerId: e.target.value,
                    })
                  }
                  required
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select reviewer</option>
                  {users.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Period start">
                <Input
                  type="date"
                  value={analysisEditor.analysisPeriodStart ?? ""}
                  onChange={(e) =>
                    setAnalysisEditor({
                      ...analysisEditor,
                      analysisPeriodStart: e.target.value,
                    })
                  }
                />
              </Field>
              <Field label="Period end">
                <Input
                  type="date"
                  value={analysisEditor.analysisPeriodEnd ?? ""}
                  onChange={(e) =>
                    setAnalysisEditor({
                      ...analysisEditor,
                      analysisPeriodEnd: e.target.value,
                    })
                  }
                />
              </Field>
              <Field label="Narrative" className="sm:col-span-2">
                <Textarea
                  value={analysisEditor.analysisNarrative ?? ""}
                  onChange={(e) =>
                    setAnalysisEditor({
                      ...analysisEditor,
                      analysisNarrative: e.target.value,
                    })
                  }
                  required
                  rows={6}
                />
              </Field>
              <Field label="Findings summary" className="sm:col-span-2">
                <Textarea
                  value={analysisEditor.findingsSummary ?? ""}
                  onChange={(e) =>
                    setAnalysisEditor({
                      ...analysisEditor,
                      findingsSummary: e.target.value,
                    })
                  }
                  rows={3}
                />
              </Field>
              <Field label="Recommendations summary" className="sm:col-span-2">
                <Textarea
                  value={analysisEditor.recommendationsSummary ?? ""}
                  onChange={(e) =>
                    setAnalysisEditor({
                      ...analysisEditor,
                      recommendationsSummary: e.target.value,
                    })
                  }
                  rows={3}
                />
              </Field>
            </div>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setAnalysisEditor(null)}
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              >
                Cancel
              </button>
              <button className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">
                Save analysis
              </button>
            </div>
          </form>
        </div>
      )}
      {reportEditor && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 p-4">
          <form
            onSubmit={saveReport}
            className="mx-auto max-w-4xl rounded-2xl bg-white p-5 shadow-xl"
          >
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold">Baseline Assessment Report</h2>
              <button
                type="button"
                onClick={() => setReportEditor(null)}
                className="text-sm text-slate-500"
              >
                Close
              </button>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <Field label="Title" className="sm:col-span-2">
                <Input
                  value={reportEditor.title ?? ""}
                  onChange={(e) =>
                    setReportEditor({ ...reportEditor, title: e.target.value })
                  }
                  required
                />
              </Field>
              <Field label="Independent reviewer">
                <select
                  value={reportEditor.reviewerId ?? ""}
                  onChange={(e) =>
                    setReportEditor({
                      ...reportEditor,
                      reviewerId: e.target.value,
                    })
                  }
                  required
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select reviewer</option>
                  {users.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </Field>
              {[
                ["executiveSummary", "Executive summary"],
                [
                  "objectivesScopeMethodology",
                  "Objectives, scope and methodology",
                ],
                ["overallFindings", "Overall findings"],
                ["controlGapSummary", "Control-gap summary"],
                ["recommendationsSummary", "Recommendations"],
                ["limitationsExceptions", "Limitations and exceptions"],
              ].map(([key, title]) => (
                <Field key={key} label={title} className="sm:col-span-2">
                  <Textarea
                    value={reportEditor[key] ?? ""}
                    onChange={(e) =>
                      setReportEditor({
                        ...reportEditor,
                        [key]: e.target.value,
                      })
                    }
                    required={
                      ![
                        "recommendationsSummary",
                        "limitationsExceptions",
                      ].includes(key)
                    }
                    rows={3}
                  />
                </Field>
              ))}
            </div>
            <div className="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
              <strong>Included approved controls:</strong>{" "}
              {(reportEditor.controlIds ?? []).length} ·{" "}
              <strong>Approved analyses:</strong>{" "}
              {(reportEditor.interimAnalysisIds ?? []).length}
              <div className="mt-2 flex flex-wrap gap-2">
                {controls
                  .filter((item) => item.status === "APPROVED")
                  .map((control) => (
                    <label
                      key={control.id}
                      className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-1"
                    >
                      <input
                        type="checkbox"
                        checked={(reportEditor.controlIds ?? []).includes(
                          control.id,
                        )}
                        onChange={(e) =>
                          setReportEditor({
                            ...reportEditor,
                            controlIds: e.target.checked
                              ? [
                                  ...new Set([
                                    ...(reportEditor.controlIds ?? []),
                                    control.id,
                                  ]),
                                ]
                              : (reportEditor.controlIds ?? []).filter(
                                  (id) => id !== control.id,
                                ),
                          })
                        }
                      />
                      {control.controlCode}
                    </label>
                  ))}
              </div>
            </div>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setReportEditor(null)}
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              >
                Cancel
              </button>
              <button className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">
                Save BAR
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
