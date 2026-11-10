import { useMemo, useState } from "react";
import {
  ArrowLeft,
  CheckCircle2,
  FileCheck2,
  Printer,
  Save,
  Send,
  ShieldCheck,
  Undo2,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  AeoDocumentSection,
  AeoSystemControlsCard,
  RecipientsDistribution,
  SourceBaselinePanel,
  WorkflowSignoffSection,
} from "./components";
import {
  buildBaselineSnapshot,
  buildInitialAeoState,
  defaultPrepareAeoState,
} from "./prepare-order-data";
import { auditAreaRegistry } from "../define-scope/define-scope-data";
import { prepareAeoSchema } from "./prepare-order-schema";
import type {
  AeoRecipient,
  AeoStatus,
  PrepareAeoFormState,
} from "./prepare-order-types";
import { exportAeoToPdf } from "./utils/aeoPdfExport";
import { localStore } from "../../../../lib/localStore";

export function PrepareOrderPage() {
  const { showToast } = useToast();

  // Extract engagement ID from pathname e.g. /admin/audit-engagement/AEMS-2025-001/prepare-order
  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  const engagementParamId = pathParts[3] || "AEMS-2026-010";

  // Resolve matching engagement and upstream state from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementParamId);
  }, [engagementParamId]);

  const [formState, setFormState] = useState<PrepareAeoFormState>(() => {
    const savedAeo = localStore.getAeoState(engagementParamId);
    if (savedAeo) {
      return {
        ...savedAeo,
        reviewedBy: savedAeo.reviewedBy || "Atty. Fernando M. Gomez, CPA",
        reviewedRole: savedAeo.reviewedRole || "Engagement Manager / Supervising Auditor",
        approvedBy: savedAeo.approvedBy || "Director Manuel C. Tan, CPA, CFE",
        approverRole: savedAeo.approverRole || "Department Head, City Internal Audit Service",
      };
    }

    if (existingEngagement) {
      const savedTeam = localStore.getTeamAssignment(existingEngagement.code);
      const savedScope = localStore.getScopeState(existingEngagement.code);
      const isSeeded = existingEngagement.code === "AEMS-2026-010";

      if (isSeeded) {
        return {
          ...defaultPrepareAeoState,
          engagementNumber: existingEngagement.code,
          reviewedBy: defaultPrepareAeoState.reviewedBy || "Atty. Fernando M. Gomez, CPA",
          approvedBy: defaultPrepareAeoState.approvedBy || "Director Manuel C. Tan, CPA, CFE",
        };
      }

      return buildInitialAeoState(existingEngagement, savedTeam, savedScope);
    }

    return {
      ...defaultPrepareAeoState,
      engagementNumber: engagementParamId,
      reviewedBy: defaultPrepareAeoState.reviewedBy || "Atty. Fernando M. Gomez, CPA",
      approvedBy: defaultPrepareAeoState.approvedBy || "Director Manuel C. Tan, CPA, CFE",
    };
  });

  const [touched, setTouched] = useState<Record<string, boolean>>({});
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [showReturnModal, setShowReturnModal] = useState(false);
  const [returnReasonText, setReturnReasonText] = useState("");

  const handleChangeField = <K extends keyof PrepareAeoFormState>(
    field: K,
    value: PrepareAeoFormState[K],
  ) => {
    setFormState((prev) => {
      const updated = {
        ...prev,
        [field]: value,
      };
      localStore.saveAeoState(prev.engagementNumber, updated);
      return updated;
    });
  };

  const handleBlurField = (field: string) => {
    setTouched((prev) => ({
      ...prev,
      [field]: true,
    }));
  };

  // Upstream Live Drift Evaluator (§5.1)
  const liveScope = useMemo(() => {
    return localStore.getScopeState(engagementParamId);
  }, [engagementParamId]);

  const liveTeam = useMemo(() => {
    return localStore.getTeamAssignment(engagementParamId);
  }, [engagementParamId]);

  const detectedDrift = useMemo(() => {
    if (!formState.baseline) return { isDrifted: false };
    const drifts: string[] = [];

    // Check Office drift
    const currentOffice = liveScope?.office || existingEngagement?.office;
    if (
      currentOffice &&
      formState.baseline.office &&
      currentOffice.trim().toLowerCase() !== formState.baseline.office.trim().toLowerCase()
    ) {
      drifts.push(`Target Office modified: "${formState.baseline.office}" → "${currentOffice}"`);
    }

    // Check Scope Areas drift
    if (liveScope && liveScope.selectedAuditAreaIds && liveScope.selectedAuditAreaIds.length > 0) {
      const liveAreas = liveScope.selectedAuditAreaIds.map((id) => {
        const a = auditAreaRegistry.find((item) => item.id === id || item.code === id);
        return a ? `${a.code} — ${a.name}` : id;
      });
      const baselineAreasStr = (formState.baseline.auditAreas || []).slice().sort().join("; ");
      const liveAreasStr = liveAreas.slice().sort().join("; ");
      if (baselineAreasStr !== liveAreasStr) {
        drifts.push(`Audit Area modified in SCR-212: "${baselineAreasStr}" → "${liveAreasStr}"`);
      }
    }

    // Check Focus Items count drift
    if (liveScope && liveScope.selectedAuditFocusIds && liveScope.selectedAuditFocusIds.length > 0) {
      if (liveScope.selectedAuditFocusIds.length !== (formState.baseline.auditFocuses || []).length) {
        drifts.push(
          `Focus items changed in SCR-212: ${formState.baseline.auditFocuses?.length || 0} → ${liveScope.selectedAuditFocusIds.length} items`,
        );
      }
    }

    // Check Lead Auditor drift
    if (liveTeam && liveTeam.team && liveTeam.team.length > 0) {
      const liveLead = liveTeam.team.find((t) => t.role.toLowerCase().includes("lead"))?.name;
      const baselineLead = formState.baseline.team.find((t) => t.role.toLowerCase().includes("lead"))?.name;
      if (liveLead && baselineLead && liveLead.trim().toLowerCase() !== baselineLead.trim().toLowerCase()) {
        drifts.push(`Lead Auditor reassigned in SCR-213: "${baselineLead}" → "${liveLead}"`);
      }
    }

    if (drifts.length > 0) {
      return {
        isDrifted: true,
        summary: `Upstream parameter changes detected (§5.1): ${drifts.join(" • ")}`,
      };
    }

    return { isDrifted: false };
  }, [formState.baseline, existingEngagement, liveScope, liveTeam]);

  const isDrifted = formState.isSourceDrifted || detectedDrift.isDrifted;
  const activeDriftSummary = formState.driftSummary || detectedDrift.summary;

  // Real-time Zod validation
  const validationResult = useMemo(() => {
    return prepareAeoSchema.safeParse(formState);
  }, [formState]);

  const errors = useMemo<Record<string, string>>(() => {
    const errorMap: Record<string, string> = {};
    if (!validationResult.success) {
      for (const issue of validationResult.error.issues) {
        const path = issue.path[0];
        if (typeof path === "string" && !errorMap[path]) {
          errorMap[path] = issue.message;
        }
      }
    }
    return errorMap;
  }, [validationResult]);

  const isFormValid = validationResult.success;
  const isReadyForIssuance = isFormValid && !isDrifted && formState.recipients.length > 0;

  // Refresh Baseline Snapshot Action (§5.1)
  const handleRefreshBaseline = () => {
    const currentVersionNum = parseFloat(formState.version.replace("v", "")) || 1.0;
    const nextVersion = `v${(currentVersionNum + 0.1).toFixed(1)}`;
    const now = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });

    // Refresh from current stored engagement, scope, and team
    const latestEngagement = localStore.getEngagement(engagementParamId) || existingEngagement;
    const latestTeam = latestEngagement ? localStore.getTeamAssignment(latestEngagement.code) : null;
    const latestScope = latestEngagement ? localStore.getScopeState(latestEngagement.code) : null;
    const refreshedBaseline = latestEngagement
      ? buildBaselineSnapshot(latestEngagement, latestTeam, latestScope, `BL-${nextVersion}`)
      : formState.baseline;

    const updatedState: PrepareAeoFormState = {
      ...formState,
      version: nextVersion,
      isSourceDrifted: false,
      driftSummary: undefined,
      baseline: refreshedBaseline,
      history: [
        {
          id: `TR-${Date.now().toString().slice(-4)}`,
          timestamp: `2026-08-19 ${now}`,
          action: "Source Baseline Refreshed",
          actor: "CIAS Lead Auditor",
          role: "Lead Auditor",
          previousStatus: formState.status,
          newStatus: formState.status,
          version: nextVersion,
          remarks: "Re-snapshotted upstream SCR-212 and SCR-213 parameters. Source drift cleared.",
        },
        ...formState.history,
      ],
    };

    setFormState(updatedState);
    localStore.saveAeoState(formState.engagementNumber, updatedState);

    showToast({
      title: "Baseline Snapshot Refreshed",
      description: `Synchronized with upstream parameters and incremented document to ${nextVersion}.`,
      tone: "success",
    });
  };

  const handleSimulateDrift = () => {
    const nextDrifted = !isDrifted;
    setFormState((prev) => ({
      ...prev,
      isSourceDrifted: nextDrifted,
      driftSummary: nextDrifted
        ? "Upstream Scope (SCR-212) or Audit Team (SCR-213) was modified after AEO snapshot creation."
        : undefined,
    }));
    showToast({
      title: nextDrifted ? "Source Drift Simulated" : "Drift Cleared",
      description: "Demonstrating MDS-200 §5.1 drift detection requirement.",
      tone: "info",
    });
  };

  // Workflow State Transitions (§10.9)
  const handleTransitionStatus = (targetStatus: AeoStatus, remarks?: string) => {
    const now = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    const today = new Date().toISOString().split("T")[0];
    const timestamp = `${today} ${now}`;

    let actor = formState.reviewedBy || "Atty. Fernando M. Gomez, CPA";
    let role = "Supervising Auditor";

    if (targetStatus === "Submitted") {
      actor = formState.baseline.team.find((t) => t.role === "Lead Auditor")?.name || "Juan Dela Cruz";
      role = "Lead Auditor";
    } else if (targetStatus === "Under Review" || targetStatus === "Returned for Correction") {
      actor = formState.reviewedBy || "Atty. Fernando M. Gomez, CPA";
      role = "Supervising Auditor";
    } else if (targetStatus === "Approved" || targetStatus === "Issued") {
      actor = formState.approvedBy || "Director Manuel C. Tan, CPA, CFE";
      role = "CIAS Department Head";
    }

    const updatedState: PrepareAeoFormState = {
      ...formState,
      status: targetStatus,
      reviewDate:
        targetStatus === "Under Review" || targetStatus === "Approved"
          ? today
          : formState.reviewDate,
      approvalDate:
        targetStatus === "Approved" || targetStatus === "Issued"
          ? today
          : formState.approvalDate,
      effectiveDate:
        targetStatus === "Issued"
          ? today
          : formState.effectiveDate,
      history: [
        {
          id: `TR-${Date.now().toString().slice(-4)}`,
          timestamp,
          action: `Status transitioned to ${targetStatus}`,
          actor,
          role,
          previousStatus: formState.status,
          newStatus: targetStatus,
          version: formState.version,
          remarks: remarks || `Transitioned AEO to ${targetStatus}.`,
        },
        ...formState.history,
      ],
    };

    setFormState(updatedState);
    localStore.saveAeoState(formState.engagementNumber, updatedState);

    showToast({
      title: `AEO Status: ${targetStatus}`,
      description: `Audit Engagement Order ${formState.aeoNumber} is now marked as ${targetStatus}.`,
      tone: targetStatus === "Issued" ? "success" : "info",
    });

    if (existingEngagement) {
      localStore.saveEngagement({
        ...existingEngagement,
        status: targetStatus === "Issued" ? "Active" : existingEngagement.status,
        progress: Math.max(existingEngagement.progress || 0, 25),
      });
    }

    if (targetStatus === "Issued") {
      setTimeout(() => {
        navigateTo(`/admin/audit-engagement/${formState.engagementNumber}`);
      }, 800);
    }
  };

  // Return for Correction Modal Handlers
  const handleConfirmReturn = (e: React.FormEvent) => {
    e.preventDefault();
    if (!returnReasonText.trim()) return;

    handleTransitionStatus("Returned for Correction", returnReasonText.trim());
    setShowReturnModal(false);
    setReturnReasonText("");
  };

  // Submit for Review Handler (with immediate validation feedback)
  const handleSubmitForReview = (remarks = "Submitted for supervisory review.") => {
    setIsSubmitted(true);
    if (formState.isSourceDrifted) {
      showToast({
        title: "Source Drift Detected",
        description: "Please refresh the baseline snapshot to resolve source drift before submitting.",
        tone: "warning",
      });
      return;
    }
    if (!validationResult.success) {
      const issueMsgs = Object.values(errors).join(". ");
      showToast({
        title: "Validation Required",
        description: `Please complete: ${issueMsgs || "Please fill in all mandatory fields."}`,
        tone: "warning",
      });
      return;
    }
    handleTransitionStatus("Submitted", remarks);
  };

  // Recipient Management
  const handleAddRecipient = (newRec: AeoRecipient) => {
    setFormState((prev) => {
      const updated = {
        ...prev,
        recipients: [...prev.recipients, newRec],
      };
      localStore.saveAeoState(prev.engagementNumber, updated);
      return updated;
    });
    showToast({
      title: "Recipient Added",
      description: `Added ${newRec.name} to AEO distribution list.`,
      tone: "info",
    });
  };

  const handleRemoveRecipient = (id: string) => {
    setFormState((prev) => {
      const updated = {
        ...prev,
        recipients: prev.recipients.filter((r) => r.id !== id),
      };
      localStore.saveAeoState(prev.engagementNumber, updated);
      return updated;
    });
  };

  const handleToggleRecipientStatus = (id: string) => {
    setFormState((prev) => {
      const updated: PrepareAeoFormState = {
        ...prev,
        recipients: prev.recipients.map((r) => {
          if (r.id === id) {
            const nextStatus: "Pending" | "Distributed" | "Acknowledged" =
              r.distributionStatus === "Pending"
                ? "Distributed"
                : r.distributionStatus === "Distributed"
                ? "Acknowledged"
                : "Pending";
            return {
              ...r,
              distributionStatus: nextStatus,
              acknowledgedAt:
                nextStatus === "Acknowledged"
                  ? new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
                  : undefined,
            };
          }
          return r;
        }),
      };
      localStore.saveAeoState(prev.engagementNumber, updated);
      return updated;
    });
  };

  const handleSaveDraft = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitted(true);
    localStore.saveAeoState(formState.engagementNumber, formState);
    showToast({
      title: "AEO Draft Saved",
      description: `Saved controlled draft ${formState.aeoNumber} (${formState.version}).`,
      tone: "info",
    });
  };

  const handleExportPdf = () => {
    exportAeoToPdf(formState);
    showToast({
      title: "Generating Official AEO PDF",
      description: `Exporting controlled document ${formState.aeoNumber} (${formState.version}) per MDS-200 §18.`,
      tone: "info",
    });
  };

  const isEditable = formState.status === "Draft" || formState.status === "Returned for Correction";
  const isReadOnly = !isEditable;

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Unified Top Header Card matching SCR-210–213 */}
        <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
          {/* Breadcrumb row */}
          <div className="flex items-center justify-between text-[11px] text-slate-400">
            <div className="flex items-center gap-1.5 font-medium">
              <button
                type="button"
                onClick={() => navigateTo("/admin/audit-engagements")}
                className="hover:text-blue-600 transition"
              >
                Audit Engagements
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Prepare Audit Engagement Order (SCR-214)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §5.1, §7, §9.1 • Controlled Document
            </span>
          </div>

          {/* Main Title and Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() => navigateTo("/admin/audit-engagements")}
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                title="Back to Engagements Workspace"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    CONTROLLED DOC
                  </span>
                  <span className="font-mono text-[10px] font-bold text-slate-600 bg-slate-100 px-1.5 py-0.2 rounded">
                    SCR-214
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Prepare Audit Engagement Order (AEO)
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Generate from controlled baselines; review, approve, sign, issue, distribute, and preserve the official AEO.
                </p>
              </div>
            </div>

            {/* Top Action Button Group (MDS-200 §10.9 Lifecycle State-Gated) */}
            <div className="flex items-center gap-2 shrink-0">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99]"
                onClick={() => navigateTo("/admin/audit-engagements")}
              >
                Cancel
              </Button>

              {(formState.status === "Submitted" || formState.status === "Under Review") && (
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 px-3 text-xs font-semibold transition flex items-center gap-1"
                  onClick={() => setShowReturnModal(true)}
                >
                  <Undo2 size={13} />
                  <span>Return for Correction</span>
                </Button>
              )}

              {isEditable && (
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99]"
                  onClick={handleSaveDraft}
                >
                  <Save className="h-4 w-4 sm:mr-1" />
                  <span className="hidden sm:inline">Save Draft</span>
                </Button>
              )}

              {/* Status-Driven Primary Action */}
              {formState.status === "Draft" ? (
                <Button
                  type="button"
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                  onClick={() => handleSubmitForReview("Submitted for supervisory review.")}
                >
                  <Send className="h-4 w-4" />
                  <span>Submit for Review</span>
                </Button>
              ) : formState.status === "Returned for Correction" ? (
                <Button
                  type="button"
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                  onClick={() => handleSubmitForReview("Resubmitted for review after corrections.")}
                >
                  <Send className="h-4 w-4" />
                  <span>Resubmit for Review</span>
                </Button>
              ) : formState.status === "Submitted" ? (
                <Button
                  type="button"
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                  onClick={() => handleTransitionStatus("Under Review", "Supervising auditor initiated formal review.")}
                >
                  <FileCheck2 className="h-4 w-4" />
                  <span>Start Review</span>
                </Button>
              ) : formState.status === "Under Review" ? (
                <Button
                  type="button"
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                  onClick={() => handleTransitionStatus("Approved", "Approved by CIAS Department Head.")}
                >
                  <ShieldCheck className="h-4 w-4" />
                  <span>Approve AEO</span>
                </Button>
              ) : formState.status === "Approved" ? (
                <Button
                  type="button"
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                  disabled={!isReadyForIssuance}
                  onClick={() => handleTransitionStatus("Issued", "AEO officially issued and distributed. Satisfies AEM-FR-010.")}
                >
                  <CheckCircle2 className="h-4 w-4" />
                  <span>Issue & Activate (SCR-220)</span>
                </Button>
              ) : (
                <div className="flex items-center gap-2">
                  <Button
                    type="button"
                    onClick={handleExportPdf}
                    className="h-10 rounded-lg px-3.5 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                    title="Export / Print official controlled AEO PDF (MDS-200 §18)"
                  >
                    <Printer className="h-4 w-4" />
                    <span>Export / Print AEO</span>
                  </Button>
                  <div className="flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 border border-emerald-200">
                    <CheckCircle2 size={14} />
                    <span>AEO Issued & Effective</span>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* 2-Column Main Form & Right Context Sidebar */}
        <div className="grid grid-cols-1 gap-2.5 xl:grid-cols-12">
          {/* Main Transaction Form Column (8 cols) */}
          <div className="space-y-2.5 xl:col-span-8">
            {/* Step 1: Controlled Source Baseline Snapshot Panel */}
            <SourceBaselinePanel
              baseline={formState.baseline}
              isDrifted={isDrifted}
              driftSummary={activeDriftSummary}
              onRefreshBaseline={handleRefreshBaseline}
              onSimulateDrift={handleSimulateDrift}
            />

            {/* Step 2: AEO Document Body Section */}
            <AeoDocumentSection
              formState={formState}
              errors={errors}
              touched={touched}
              isSubmitted={isSubmitted}
              isReadOnly={isReadOnly}
              onChangeField={handleChangeField}
              onBlurField={handleBlurField}
            />

            {/* Step 3: Workflow Sign-Off & Authority Section */}
            <WorkflowSignoffSection
              formState={formState}
              onChangeField={handleChangeField}
            />

            {/* Step 4: Recipients & Distribution List */}
            <RecipientsDistribution
              recipients={formState.recipients}
              isReadOnly={isReadOnly}
              onAddRecipient={handleAddRecipient}
              onRemoveRecipient={handleRemoveRecipient}
              onToggleStatus={handleToggleRecipientStatus}
            />

            {/* Bottom Action Footer with Authority Helper Text */}
            <div className="mt-2.5 flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-1.5 text-xs text-slate-500">
                <span className="h-2 w-2 rounded-full bg-emerald-500 shrink-0" />
                <span>
                  {formState.status === "Issued"
                    ? "This AEO has been officially issued and is locked per MDS-200 Rule 31."
                    : "Issuing this AEO formally activates the engagement and satisfies the initiation requirement for fieldwork (MDS-200 §9.1 AEM-FR-010)."}
                </span>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                  onClick={() => navigateTo("/admin/audit-engagements")}
                >
                  Cancel
                </Button>

                {(formState.status === "Submitted" || formState.status === "Under Review") && (
                  <Button
                    type="button"
                    variant="outline"
                    className="h-10 rounded-lg border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 px-3 text-xs font-semibold transition flex items-center gap-1"
                    onClick={() => setShowReturnModal(true)}
                  >
                    <Undo2 size={13} />
                    <span>Return for Correction</span>
                  </Button>
                )}

                {isEditable && (
                  <Button
                    type="button"
                    variant="outline"
                    className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                    onClick={handleSaveDraft}
                  >
                    <Save className="h-4 w-4 sm:mr-1" />
                    <span className="hidden sm:inline">Save Draft</span>
                  </Button>
                )}

                {formState.status === "Draft" ? (
                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                    onClick={() => handleSubmitForReview("Submitted for supervisory review.")}
                  >
                    <span>Submit AEO Order</span>
                    <Send className="h-4 w-4" />
                  </Button>
                ) : formState.status === "Returned for Correction" ? (
                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                    onClick={() => handleSubmitForReview("Resubmitted for review after corrections.")}
                  >
                    <span>Resubmit AEO Order</span>
                    <Send className="h-4 w-4" />
                  </Button>
                ) : formState.status === "Submitted" ? (
                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                    onClick={() => handleTransitionStatus("Under Review", "Supervising auditor initiated formal review.")}
                  >
                    <span>Start Review</span>
                    <FileCheck2 className="h-4 w-4" />
                  </Button>
                ) : formState.status === "Under Review" ? (
                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                    onClick={() => handleTransitionStatus("Approved", "Approved by CIAS Department Head.")}
                  >
                    <span>Approve AEO</span>
                    <ShieldCheck className="h-4 w-4" />
                  </Button>
                ) : formState.status === "Approved" ? (
                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                    disabled={!isReadyForIssuance}
                    onClick={() => handleTransitionStatus("Issued", "AEO officially issued and distributed.")}
                  >
                    <span>Issue AEO (Satisfy Gate)</span>
                    <CheckCircle2 className="h-4 w-4" />
                  </Button>
                ) : (
                  <div className="flex items-center gap-2">
                    <Button
                      type="button"
                      onClick={handleExportPdf}
                      className="h-10 rounded-lg px-3.5 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                    >
                      <Printer className="h-4 w-4" />
                      <span>Export / Print AEO</span>
                    </Button>
                    <div className="flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 border border-emerald-200">
                      <CheckCircle2 size={14} />
                      <span>AEO Issued</span>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Right Governance Context Column (4 cols) */}
          <div className="xl:col-span-4">
            <div className="sticky top-2">
              <AeoSystemControlsCard
                formState={formState}
                isReadyForIssuance={isReadyForIssuance}
                onExportPdf={handleExportPdf}
              />
            </div>
          </div>
        </div>

        {/* Return for Correction Modal */}
        {showReturnModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div className="w-full max-w-md rounded-xl bg-white p-5 shadow-xl space-y-4 animate-in zoom-in-95">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div className="flex items-center gap-2 text-rose-700 font-bold text-sm">
                  <Undo2 size={16} />
                  <span>Return AEO for Correction (§10.9)</span>
                </div>
                <button
                  type="button"
                  onClick={() => setShowReturnModal(false)}
                  className="text-slate-400 hover:text-slate-600 text-sm"
                >
                  ✕
                </button>
              </div>

              <p className="text-xs text-slate-600 leading-relaxed">
                Document reason for returning the AEO to the preparation team. All return actions are permanently logged in the audit trail.
              </p>

              <form onSubmit={handleConfirmReturn} className="space-y-3">
                <textarea
                  rows={3}
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-rose-500 focus:outline-none"
                  placeholder="Specify corrections needed (e.g. clarify technical deliverables, add missing auditee recipient)..."
                  value={returnReasonText}
                  onChange={(e) => setReturnReasonText(e.target.value)}
                  required
                />

                <div className="flex justify-end gap-2 pt-2">
                  <Button
                    type="button"
                    variant="outline"
                    className="h-9 px-3 text-xs"
                    onClick={() => setShowReturnModal(false)}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    className="h-9 px-4 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold"
                  >
                    Confirm Return
                  </Button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}

export default PrepareOrderPage;
