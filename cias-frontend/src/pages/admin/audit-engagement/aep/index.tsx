import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  RotateCcw,
  Send,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  AepCommunicationApprovalSection,
  AepIdentityContextCard,
  AepMethodologyEvidenceSection,
  AepMilestonesKpisSection,
  AepObjectivesScopeSection,
  AepSystemControlsCard,
} from "./components";
import {
  buildInitialAepState,
  defaultAepState,
} from "./aep-data";
import { aepSchema } from "./aep-schema";
import type { AepFormState, AepKpiMetric, AepMilestone } from "./aep-types";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";
import { EngagementPhaseGate } from "../components/EngagementPhaseGate";
import { localStore } from "../../../../lib/localStore";

export function AdminPrepareAepPage() {
  const { showToast } = useToast();

  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  const engagementId = pathParts[3] || "AEMS-2026-010";

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementId);
  }, [engagementId]);

  const engagementNumber = existingEngagement?.code || engagementId;

  const [formState, setFormState] = useState<AepFormState>(() => {
    const savedAep = localStore.getAepState(engagementNumber);
    if (savedAep) {
      return savedAep;
    }

    if (existingEngagement) {
      const isSeeded = engagementNumber === "AEMS-2026-010";
      if (isSeeded) {
        return defaultAepState;
      }
      const savedTeam = localStore.getTeamAssignment(engagementNumber);
      return buildInitialAepState(existingEngagement, savedTeam);
    }
    return defaultAepState;
  });

  // Enforce Phase Gating (Phase 1 AEO required for Phase 2 Planning)
  const gate = evaluateEngagementGating(engagementNumber);
  if (!gate.isPhase1Complete) {
    return (
      <EngagementPhaseGate
        engagementNumber={engagementNumber}
        requiredPhaseName="Phase 2: Audit Planning"
        requiredPhaseNumber={2}
        screenTitle="Prepare Audit Engagement Plan (SCR-222)"
        screenCitation="MDS-200 v0.8 §7 · UID-200 v0.5 §8.3 · Phase 2 Audit Planning · AEO Issuance Gate"
      />
    );
  }

  // Validation
  const validationResult = useMemo(() => {
    const parsed = aepSchema.safeParse(formState);
    return {
      isValid: parsed.success,
      errors: parsed.success
        ? {}
        : parsed.error.issues.reduce<Record<string, string>>((acc, issue) => {
          acc[issue.path.join(".")] = issue.message;
          return acc;
        }, {}),
    };
  }, [formState]);

  // Section Handlers
  const handleToggleCriteria = (criterion: string) => {
    setFormState((prev) => {
      const exists = prev.criteriaFramework.includes(criterion);
      return {
        ...prev,
        criteriaFramework: exists
          ? prev.criteriaFramework.filter((c) => c !== criterion)
          : [...prev.criteriaFramework, criterion],
      };
    });
  };

  const handleToggleEvidence = (category: string) => {
    setFormState((prev) => {
      const exists = prev.evidenceRequirements.includes(category);
      return {
        ...prev,
        evidenceRequirements: exists
          ? prev.evidenceRequirements.filter((c) => c !== category)
          : [...prev.evidenceRequirements, category],
      };
    });
  };

  // Milestone Handlers
  const handleUpdateMilestone = (id: string, updates: Partial<AepMilestone>) => {
    setFormState((prev) => ({
      ...prev,
      milestones: prev.milestones.map((m) =>
        m.id === id ? { ...m, ...updates } : m,
      ),
    }));
  };

  const handleAddMilestone = () => {
    const newId = `MS-0${formState.milestones.length + 1}`;
    const newM: AepMilestone = {
      id: newId,
      phaseTitle: "Supplemental Verification Phase",
      targetStartDate: "2026-03-01",
      targetEndDate: "2026-03-15",
      responsibleLead: "Juan Dela Cruz",
      deliverable: "Supplemental Working Paper",
    };
    setFormState((prev) => ({
      ...prev,
      milestones: [...prev.milestones, newM],
    }));
  };

  const handleRemoveMilestone = (id: string) => {
    if (formState.milestones.length <= 2) return;
    setFormState((prev) => ({
      ...prev,
      milestones: prev.milestones.filter((m) => m.id !== id),
    }));
  };

  // KPI Handlers
  const handleUpdateKpi = (id: string, updates: Partial<AepKpiMetric>) => {
    setFormState((prev) => ({
      ...prev,
      kpis: prev.kpis.map((k) => (k.id === id ? { ...k, ...updates } : k)),
    }));
  };

  const handleAddKpi = () => {
    const newId = `KPI-0${formState.kpis.length + 1}`;
    const newK: AepKpiMetric = {
      id: newId,
      metricName: "Fieldwork Completion Timeliness",
      targetValue: "100% of milestones met within planned timeline",
      measurementMethod: "Variance tracking vs baseline AEP dates",
      responsibleRole: "Lead Auditor",
    };
    setFormState((prev) => ({
      ...prev,
      kpis: [...prev.kpis, newK],
    }));
  };

  const handleRemoveKpi = (id: string) => {
    if (formState.kpis.length <= 2) return;
    setFormState((prev) => ({
      ...prev,
      kpis: prev.kpis.filter((k) => k.id !== id),
    }));
  };

  const handleSaveDraft = () => {
    localStore.saveAepState(formState.engagementNumber, formState);
    showToast({
      title: "AEP Draft Saved",
      description: `Draft plan ${formState.aepId} for ${formState.engagementNumber} saved successfully.`,
      tone: "info",
    });
  };

  const handleSubmitReview = (e?: React.FormEvent) => {
    if (e) {
      e.preventDefault();
    }

    if (!validationResult.isValid) {
      showToast({
        title: "Validation Error",
        description: "Please complete all mandatory AEP sections, criteria, and milestone deliverables.",
        tone: "warning",
      });
      return;
    }

    const updatedState: AepFormState = { ...formState, status: "Under Review" };
    setFormState(updatedState);
    localStore.saveAepState(formState.engagementNumber, updatedState);

    showToast({
      title: "Audit Engagement Plan Submitted",
      description: `Plan ${formState.aepId} transmitted to ${formState.reviewingSupervisor} for review. Proceeding to Planning Workspace...`,
      tone: "success",
    });

    setTimeout(() => {
      navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/planning/programs`);
    }, 400);
  };

  const handleReset = () => {
    setFormState(defaultAepState);
    showToast({
      title: "AEP Reset",
      description: "Form reverted to default AEP template.",
      tone: "info",
    });
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Top Unified Header Card */}
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
                  navigateTo(`/admin/audit-engagement/${formState.engagementNumber}`)
                }
                className="hover:text-blue-600 transition"
              >
                {formState.engagementNumber}
              </button>
              <span>/</span>
              <button
                type="button"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/planning`)
                }
                className="hover:text-blue-600 transition"
              >
                Planning Workspace (SCR-221)
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Prepare AEP (SCR-222)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §7, §9.2, §11 · UID-200 v0.5 §8.3 · Phase 2 Planning · Controlled Document
            </span>
          </div>

          {/* Main Title and Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/planning`)
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                title="Back to Planning Workspace"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    SCR-222
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Prepare Audit Engagement Plan (AEP) & Engagement KPIs
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Formulate, review, approve, and version the strategic fieldwork plan, evidence requirements, and performance indicators for <strong>{formState.engagementNumber}</strong>.
                </p>
              </div>
            </div>

            {/* Header Actions */}
            <div className="flex items-center gap-2 shrink-0">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99]"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/planning`)
                }
              >
                Cancel
              </Button>

              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-1.5 transition active:scale-[0.99]"
                onClick={handleReset}
              >
                <RotateCcw className="h-3.5 w-3.5" />
                <span>Reset</span>
              </Button>

              <Button
                type="button"
                className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                disabled={!validationResult.isValid}
                onClick={() => handleSubmitReview()}
              >
                <Send className="h-4 w-4" />
                <span>Submit AEP for Review</span>
                <ArrowRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Main Content or Gated View */}
        {!gate.isPhase1Complete ? (
          <EngagementPhaseGate
            engagementNumber={formState.engagementNumber}
            requiredPhaseName="Phase 2: Audit Planning"
            requiredPhaseNumber={2}
            screenTitle="Audit Engagement Plan (SCR-222)"
          />
        ) : (
          /* 2-Column Main Form Grid */
          <form onSubmit={handleSubmitReview} className="space-y-3">
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
              {/* Left Column (8 cols): Controlled AEP Sections */}
              <div className="space-y-3 lg:col-span-8">
                {/* 1. AEP Top Context Banner */}
                <AepIdentityContextCard
                  aepId={formState.aepId}
                  engagementNumber={formState.engagementNumber}
                  engagementTitle={formState.engagementTitle}
                  office={formState.office}
                  version={formState.version}
                  effectiveDate={formState.effectiveDate}
                  status={formState.status}
                />

                {/* 2. Objectives, Refined Scope & Criteria Framework */}
                <AepObjectivesScopeSection
                  objectives={formState.objectives}
                  refinedScope={formState.refinedScope}
                  criteriaFramework={formState.criteriaFramework}
                  onChangeObjectives={(val) =>
                    setFormState((prev) => ({ ...prev, objectives: val }))
                  }
                  onChangeScope={(val) =>
                    setFormState((prev) => ({ ...prev, refinedScope: val }))
                  }
                  onToggleCriteria={handleToggleCriteria}
                />

                {/* 3. Methodology, Evidence & Resources */}
                <AepMethodologyEvidenceSection
                  riskMatrixSummaryRef={formState.riskMatrixSummaryRef}
                  methodology={formState.methodology}
                  evidenceRequirements={formState.evidenceRequirements}
                  assignedTeamRef={formState.assignedTeamRef}
                  requiredAuditTools={formState.requiredAuditTools}
                  onChangeMethodology={(val) =>
                    setFormState((prev) => ({ ...prev, methodology: val }))
                  }
                  onToggleEvidence={handleToggleEvidence}
                  onChangeTools={(val) =>
                    setFormState((prev) => ({ ...prev, requiredAuditTools: val }))
                  }
                />

                {/* 4. Milestones & KPIs */}
                <AepMilestonesKpisSection
                  milestones={formState.milestones}
                  kpis={formState.kpis}
                  onUpdateMilestone={handleUpdateMilestone}
                  onAddMilestone={handleAddMilestone}
                  onRemoveMilestone={handleRemoveMilestone}
                  onUpdateKpi={handleUpdateKpi}
                  onAddKpi={handleAddKpi}
                  onRemoveKpi={handleRemoveKpi}
                />

                {/* 5. Communication to Management & Approval Signatories */}
                <AepCommunicationApprovalSection
                  communicationToManagement={formState.communicationToManagement}
                  reviewingSupervisor={formState.reviewingSupervisor}
                  approvingAuthority={formState.approvingAuthority}
                  isAmendment={formState.isAmendment}
                  amendmentReason={formState.amendmentReason}
                  onChangeCommunication={(val) =>
                    setFormState((prev) => ({
                      ...prev,
                      communicationToManagement: val,
                    }))
                  }
                  onChangeSupervisor={(val) =>
                    setFormState((prev) => ({
                      ...prev,
                      reviewingSupervisor: val,
                    }))
                  }
                  onChangeApprover={(val) =>
                    setFormState((prev) => ({
                      ...prev,
                      approvingAuthority: val,
                    }))
                  }
                  onToggleAmendment={(val) =>
                    setFormState((prev) => ({ ...prev, isAmendment: val }))
                  }
                  onChangeAmendmentReason={(val) =>
                    setFormState((prev) => ({ ...prev, amendmentReason: val }))
                  }
                />
              </div>

              {/* Right Column (4 cols): System Controls & 6-Point Readiness Checklist */}
              <div className="space-y-3 lg:col-span-4">
                <div className="sticky top-4">
                  <AepSystemControlsCard
                    formState={formState}
                    isReady={validationResult.isValid}
                    onSaveDraft={handleSaveDraft}
                    onSubmitReview={() => handleSubmitReview()}
                  />
                </div>
              </div>
            </div>

            {/* Bottom Footer Card */}
            <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-1.5 text-xs text-slate-500">
                <CheckCircle2 size={14} className="text-blue-600 shrink-0" />
                <span>
                  AEP Status: <strong>{formState.status} ({formState.version})</strong> • Phase 2: <strong>Audit Planning</strong> • Governed under MDS-200 §7
                </span>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                  onClick={() =>
                    navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/planning`)
                  }
                >
                  Return to Planning (SCR-221)
                </Button>

                <Button
                  type="submit"
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99]"
                  disabled={!validationResult.isValid}
                >
                  <span>Submit AEP for Review</span>
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </div>
          </form>
        )}
      </div>
    </AdminLayout>
  );
}

export default AdminPrepareAepPage;
