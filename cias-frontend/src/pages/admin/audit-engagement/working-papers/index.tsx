import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  FileSpreadsheet,
  Plus,
  RotateCcw,
  Send,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  WorkingPaperControlsRail,
  WorkingPaperFormSection,
  WorkingPaperIdentityBanner,
  WorkingPaperReviewNotesSection,
  WorkingPaperRosterSection,
  WorkingPaperSourceLinkageCard,
} from "./components";
import { defaultWorkingPaperPackageState } from "./working-papers-data";
import { workingPaperSchema } from "./working-papers-schema";
import type {
  WorkingPaper,
  WorkingPaperEvidenceLink,
  WorkingPaperPackageState,
} from "./working-papers-types";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";
import { EngagementPhaseGate } from "../components/EngagementPhaseGate";
import { localStore } from "../../../../lib/localStore";

export function AdminWorkingPapersPage() {
  const { showToast } = useToast();

  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/").filter(Boolean);
  // e.g. ["admin", "audit-engagement", "AEMS-2026-010", "execution", "workpapers", "WP-2026-003-WH-01"]
  // or ["admin", "audit-engagement", "AEMS-2026-010", "workpapers", "WP-2026-003-WH-01"]
  const engagementId = pathParts[2] || "AEMS-2026-010";

  // Check if last segment is a specific WP ID
  const lastPart = pathParts[pathParts.length - 1];
  const urlWpId =
    lastPart &&
      lastPart.startsWith("WP-")
      ? lastPart
      : null;

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementId);
  }, [engagementId]);

  const engagementNumber = existingEngagement?.code || engagementId;

  const [packageState, setPackageState] = useState<WorkingPaperPackageState>(() => {
    const saved = localStore.getWorkingPapersState(engagementId);
    if (saved) {
      if (urlWpId && saved.workingPapers.some((w) => w.id.toLowerCase() === urlWpId.toLowerCase())) {
        return { ...saved, activeWorkingPaperId: urlWpId };
      }
      return saved;
    }

    const isSeeded = engagementNumber === "AEMS-2026-010";
    if (isSeeded) {
      const baseState = { ...defaultWorkingPaperPackageState };
      if (existingEngagement) {
        baseState.engagementNumber = existingEngagement.code;
        baseState.engagementTitle =
          existingEngagement.auditArea ||
          existingEngagement.subject ||
          defaultWorkingPaperPackageState.engagementTitle;
        baseState.office =
          existingEngagement.office || defaultWorkingPaperPackageState.office;
      }
      if (urlWpId && baseState.workingPapers.some((w) => w.id.toLowerCase() === urlWpId.toLowerCase())) {
        baseState.activeWorkingPaperId = urlWpId;
      }
      return baseState;
    }

    return {
      engagementNumber,
      engagementTitle:
        existingEngagement?.auditArea ||
        existingEngagement?.subject ||
        "",
      office: existingEngagement?.office || "",
      activeWorkingPaperId: "",
      workingPapers: [],
    };
  });

  // Enforce Phase Gating (MDS-200 Module Rule 11)
  const gate = evaluateEngagementGating(engagementNumber);

  // Sync if URL param changes
  useEffect(() => {
    if (urlWpId) {
      const found = packageState.workingPapers.find(
        (wp) => wp.id.toLowerCase() === urlWpId.toLowerCase(),
      );
      if (found && found.id !== packageState.activeWorkingPaperId) {
        setPackageState((prev) => ({ ...prev, activeWorkingPaperId: found.id }));
      }
    }
  }, [urlWpId, packageState.workingPapers, packageState.activeWorkingPaperId]);

  const activeWorkingPaper = useMemo(() => {
    const found = packageState.workingPapers.find(
      (wp) => wp.id === packageState.activeWorkingPaperId,
    );
    return found || packageState.workingPapers[0];
  }, [packageState.workingPapers, packageState.activeWorkingPaperId]);

  // Zod Validation
  const validationResult = useMemo(() => {
    const parsed = workingPaperSchema.safeParse(activeWorkingPaper);
    return {
      isValid: parsed.success,
      errors: parsed.success
        ? {}
        : parsed.error.issues.reduce<Record<string, string>>((acc, issue) => {
          acc[issue.path.join(".")] = issue.message;
          return acc;
        }, {}),
    };
  }, [activeWorkingPaper]);

  // Handlers
  const handleFieldChange = (field: keyof WorkingPaper, value: any) => {
    setPackageState((prev) => ({
      ...prev,
      workingPapers: prev.workingPapers.map((wp) =>
        wp.id === prev.activeWorkingPaperId
          ? { ...wp, [field]: value, updatedAt: "2026-03-16 10:00" }
          : wp,
      ),
    }));
  };

  const handleAddEvidenceLink = () => {
    const newId = `EVD-2026-00${activeWorkingPaper.evidenceLinks.length + 1}`;
    const newLink: WorkingPaperEvidenceLink = {
      id: newId,
      title: "Supplemental Audit Evidence Artifact",
      type: "Scanned Attachment",
      sourceUrlOrRef: `${activeWorkingPaper.id}/${newId}.pdf`,
    };

    handleFieldChange("evidenceLinks", [
      ...activeWorkingPaper.evidenceLinks,
      newLink,
    ]);
    showToast({
      title: "Evidence Reference Added",
      description: `Cross-referenced ${newId} to ${activeWorkingPaper.id}.`,
      tone: "info",
    });
  };

  const handleUpdateEvidenceLink = (
    id: string,
    updates: Partial<WorkingPaperEvidenceLink>,
  ) => {
    const updated = activeWorkingPaper.evidenceLinks.map((evd) =>
      evd.id === id ? { ...evd, ...updates } : evd,
    );
    handleFieldChange("evidenceLinks", updated);
  };

  const handleRemoveEvidenceLink = (id: string) => {
    const filtered = activeWorkingPaper.evidenceLinks.filter(
      (evd) => evd.id !== id,
    );
    handleFieldChange("evidenceLinks", filtered);
  };

  const handleSelectWorkingPaper = (id: string) => {
    setPackageState((prev) => ({ ...prev, activeWorkingPaperId: id }));
  };

  const handleCreateNewWorkingPaper = () => {
    const engSuffix =
      (packageState.engagementNumber || "").replace(/^AEMS-/, "") || "2026-020";
    const progPkg = localStore.getProgramsState(packageState.engagementNumber);
    const firstProg = progPkg?.programs?.[0];
    const firstProc = firstProg?.procedures?.[0];

    const procId = firstProc?.id || `PROC-${engSuffix}-001-01`;
    const procTitle =
      firstProc?.procedureTitle ||
      "Farmer Beneficiary Registry Cross-Check & Voucher Vouching";
    const rmiId = firstProc?.riskMatrixItemId || `RMI-${engSuffix}-001`;
    const progId = firstProg?.id || `PROG-${engSuffix}-001`;

    const nextNum = packageState.workingPapers.length + 1;
    const newId = `WP-${engSuffix}-OPS-${String(nextNum).padStart(2, "0")}`;
    const newWp: WorkingPaper = {
      id: newId,
      referenceNumber: `${packageState.engagementNumber}-WP-${String(nextNum).padStart(3, "0")}`,
      engagementNumber: packageState.engagementNumber,
      type: "Execution",
      plannedReference: `AEP-${engSuffix}-01 / ${progId}`,
      procedureId: procId,
      procedureTitle: procTitle,
      riskMatrixItemId: rmiId,
      riskMatrixTitle: "Beneficiary Masterlist Authenticity & Subsidy Misallocation Risk",
      auditArea: packageState.engagementTitle || "Operational Compliance Audit",
      purpose: "Conduct substantive verification and testing of audit procedures and records.",
      workPerformed: "1. Examined operational transaction files and verified approval authorizations.\n2. Cross-referenced disbursement records and official registries.",
      evidenceLinks: [],
      results: "Initial test samples verified against statutory guidelines with full supporting documents.",
      conclusion: "Operational control workflows evaluated in accordance with internal audit standards.",
      preparer: "Juan Dela Cruz (Lead Auditor)",
      reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
      reviewNotes: [],
      reviewStatus: "Draft",
      version: "v1.0",
      createdAt: new Date().toISOString().replace("T", " ").substring(0, 16),
      updatedAt: new Date().toISOString().replace("T", " ").substring(0, 16),
    };

    setPackageState((prev) => ({
      ...prev,
      activeWorkingPaperId: newId,
      workingPapers: [newWp, ...prev.workingPapers],
    }));

    showToast({
      title: "New Working Paper Initialized",
      description: `Draft working paper ${newId} created.`,
      tone: "info",
    });
  };

  const handleSaveDraft = () => {
    localStore.saveWorkingPapersState(packageState.engagementNumber, packageState);
    showToast({
      title: "Working Paper Saved",
      description: `Draft changes for ${activeWorkingPaper.id} saved successfully.`,
      tone: "info",
    });
  };

  const handleSubmitWorkingPaper = () => {
    if (!validationResult.isValid) {
      showToast({
        title: "Validation Incomplete",
        description: "Please complete all mandatory work performed, results, and conclusion fields.",
        tone: "warning",
      });
      return;
    }

    // UID-200 §11 Consequence-bearing action confirmation
    const confirmed = window.confirm(
      `Submit Working Paper ${activeWorkingPaper.id} to Supervising Auditor (${activeWorkingPaper.reviewer}) for review?\n\nThis will lock testing procedures and transmit conclusions toward engagement reporting.`,
    );

    if (!confirmed) return;

    const updatedPapers = packageState.workingPapers.map((wp) =>
      wp.id === activeWorkingPaper.id ? { ...wp, reviewStatus: "Submitted" as const } : wp,
    );
    const updatedState: WorkingPaperPackageState = {
      ...packageState,
      workingPapers: updatedPapers,
    };
    setPackageState(updatedState);
    localStore.saveWorkingPapersState(packageState.engagementNumber, updatedState);

    showToast({
      title: "Working Paper Submitted",
      description: `Working paper ${activeWorkingPaper.id} transmitted to ${activeWorkingPaper.reviewer} for quality review.`,
      tone: "success",
    });
  };

  const handleClearReviewNotes = () => {
    const updatedNotes = activeWorkingPaper.reviewNotes.map((rn) => ({
      ...rn,
      status: "Cleared" as const,
      clearedBy: activeWorkingPaper.reviewer,
      clearanceDate: "2026-03-16",
    }));

    setPackageState((prev) => ({
      ...prev,
      workingPapers: prev.workingPapers.map((wp) =>
        wp.id === prev.activeWorkingPaperId
          ? {
            ...wp,
            reviewNotes: updatedNotes,
            reviewStatus: "Cleared",
            clearance: {
              clearedBy: activeWorkingPaper.reviewer,
              clearanceDate: "2026-03-16",
              clearanceRemarks: "All queries and review points satisfactorily resolved and cleared.",
            },
            updatedAt: "2026-03-16 10:30",
          }
          : wp,
      ),
    }));

    showToast({
      title: "Review Notes Cleared",
      description: `Supervising clearance recorded for ${activeWorkingPaper.id}.`,
      tone: "success",
    });
  };

  const handleReset = () => {
    setPackageState(defaultWorkingPaperPackageState);
    showToast({
      title: "Repository Reset",
      description: "Reverted working papers repository to default baseline.",
      tone: "info",
    });
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Top Header Card */}
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
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}`,
                  )
                }
                className="hover:text-blue-600 transition"
              >
                {packageState.engagementNumber}
              </button>
              <span>/</span>
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                  )
                }
                className="hover:text-blue-600 transition"
              >
                Execution Workspace (SCR-226)
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Working Papers (SCR-228)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §7, §9.3 · UID-200 v0.5 §8.3 · Phase 4 Controlled Working Papers
            </span>
          </div>

          {/* Main Title and Header Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                  )
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer"
                title="Back to Execution Workspace Hub"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-purple-700 bg-purple-50 px-1.5 py-0.2 rounded border border-purple-200">
                    SCR-228
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Working Papers (WP) Management & Repository
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Index, document, cross-reference, review, and archive audit testing working papers for <strong>{packageState.engagementNumber}</strong>.
                </p>
              </div>
            </div>

            {/* Header Actions */}
            <div className="flex items-center gap-2 shrink-0">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99] cursor-pointer"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                  )
                }
              >
                Return to Execution
              </Button>

              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
                onClick={handleReset}
              >
                <RotateCcw className="h-3.5 w-3.5" />
                <span>Reset</span>
              </Button>

              <Button
                type="button"
                className="h-10 rounded-lg px-4 text-xs font-semibold bg-purple-600 hover:bg-purple-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                disabled={!activeWorkingPaper || !validationResult.isValid || activeWorkingPaper?.reviewStatus === "Submitted" || activeWorkingPaper?.reviewStatus === "Cleared"}
                onClick={handleSubmitWorkingPaper}
              >
                <Send className="h-4 w-4" />
                <span>
                  {activeWorkingPaper?.reviewStatus === "Cleared"
                    ? "Working Paper Cleared"
                    : activeWorkingPaper?.reviewStatus === "Submitted"
                      ? "Submitted for Review"
                      : "Submit Working Paper"}
                </span>
                <ArrowRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Main Content or Gated View */}
        {!gate.isPhase3Complete ? (
          <EngagementPhaseGate
            engagementNumber={packageState.engagementNumber}
            requiredPhaseName="Phase 4: Fieldwork Execution"
            requiredPhaseNumber={4}
            screenTitle="Working Papers Workspace (SCR-228)"
          />
        ) : !activeWorkingPaper ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center space-y-4 shadow-sm">
            <div className="grid h-12 w-12 place-items-center rounded-full bg-purple-50 text-purple-600 mx-auto">
              <FileSpreadsheet className="h-6 w-6" />
            </div>
            <div className="space-y-1 max-w-md mx-auto">
              <h3 className="text-sm font-bold text-slate-900">
                No Working Papers Initialized
              </h3>
              <p className="text-xs text-slate-500">
                Initialize your first audit testing working paper repository for <strong>{packageState.engagementNumber}</strong>.
              </p>
            </div>
            <Button
              type="button"
              onClick={handleCreateNewWorkingPaper}
              className="h-9 px-4 text-xs font-semibold bg-purple-600 hover:bg-purple-700 text-white shadow-sm flex items-center gap-2 mx-auto cursor-pointer"
            >
              <Plus className="h-4 w-4" />
              <span>Initialize First Working Paper</span>
            </Button>
          </div>
        ) : (
          <>
            {/* 2-Column Main Layout Grid */}
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
              {/* Left Column (8 cols): Controlled Working Paper Artifacts */}
              <div className="space-y-3 lg:col-span-8">
                {/* 1. Working Paper Identity Banner */}
                <WorkingPaperIdentityBanner
                  workingPaper={activeWorkingPaper}
                  engagementTitle={packageState.engagementTitle}
                  office={packageState.office}
                />

                {/* 2. Source Cross-Reference & Linkage Architecture (Rule 13 Invariant) */}
                <WorkingPaperSourceLinkageCard
                  workingPaper={activeWorkingPaper}
                  engagementNumber={packageState.engagementNumber}
                />

                {/* 3. Primary Working Paper Substantive Form Section */}
                <WorkingPaperFormSection
                  workingPaper={activeWorkingPaper}
                  onChangeField={handleFieldChange}
                  onAddEvidenceLink={handleAddEvidenceLink}
                  onUpdateEvidenceLink={handleUpdateEvidenceLink}
                  onRemoveEvidenceLink={handleRemoveEvidenceLink}
                />

                {/* 4. Supervisory Review Notes & Clearance Tracking (SCR-226 Linkage) */}
                <WorkingPaperReviewNotesSection
                  workingPaper={activeWorkingPaper}
                  onClearReviewNotes={
                    activeWorkingPaper.reviewStatus !== "Cleared"
                      ? handleClearReviewNotes
                      : undefined
                  }
                />

                {/* 5. Engagement Working Papers Master Repository Roster */}
                <WorkingPaperRosterSection
                  workingPapers={packageState.workingPapers}
                  activeWorkingPaperId={packageState.activeWorkingPaperId}
                  onSelectWorkingPaper={handleSelectWorkingPaper}
                  onCreateNewWorkingPaper={handleCreateNewWorkingPaper}
                />
              </div>

              {/* Right Column (4 cols): Working Paper Controls & Governance Checklist */}
              <div className="space-y-3 lg:col-span-4">
                <div className="sticky top-4">
                  <WorkingPaperControlsRail
                    workingPaper={activeWorkingPaper}
                    isValid={validationResult.isValid}
                    onSaveDraft={handleSaveDraft}
                    onSubmitWorkingPaper={handleSubmitWorkingPaper}
                    onCreateNewWorkingPaper={handleCreateNewWorkingPaper}
                  />
                </div>
              </div>
            </div>

            {/* Bottom Action Footer Card */}
            <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-1.5 text-xs text-slate-500">
                <CheckCircle2 size={14} className="text-purple-700 shrink-0" />
                <span>
                  Working Paper: <strong>{activeWorkingPaper.id} ({activeWorkingPaper.type})</strong> • Linked to <strong>{activeWorkingPaper.procedureId}</strong> &bull; Status: <strong>{activeWorkingPaper.reviewStatus}</strong> &bull; Governed under MDS-200 §7, §9.3 & Rule 13
                </span>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                  onClick={() =>
                    navigateTo(
                      `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                    )
                  }
                >
                  Return to Execution (SCR-226)
                </Button>

                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-purple-700 hover:bg-purple-50 cursor-pointer flex items-center gap-1.5"
                  onClick={() =>
                    navigateTo(
                      `/admin/audit-engagement/${packageState.engagementNumber}/fieldwork-records`,
                    )
                  }
                >
                  <FileSpreadsheet size={13} />
                  <span>Fieldwork Records (SCR-227)</span>
                </Button>

                <Button
                  type="button"
                  onClick={handleSubmitWorkingPaper}
                  disabled={!activeWorkingPaper || !validationResult.isValid || activeWorkingPaper?.reviewStatus === "Submitted" || activeWorkingPaper?.reviewStatus === "Cleared"}
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-purple-600 hover:bg-purple-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                >
                  <span>
                    {activeWorkingPaper?.reviewStatus === "Cleared"
                      ? "Working Paper Cleared"
                      : activeWorkingPaper?.reviewStatus === "Submitted"
                        ? "Submitted for Review"
                        : "Submit Working Paper"}
                  </span>
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </div>
          </>
        )}
      </div>
    </AdminLayout>
  );
}

export default AdminWorkingPapersPage;
