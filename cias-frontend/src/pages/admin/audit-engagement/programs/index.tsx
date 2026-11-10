import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  FileSpreadsheet,
  Filter,
  Plus,
  RotateCcw,
  Send,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  AuditProgramCard,
  NewAuditProgramModal,
  ProgramIdentityBanner,
  ProgramsSummaryRail,
} from "./components";
import { defaultProgramsPackageState } from "./programs-data";
import type { AuditProgram, ProgramsPackageState } from "./programs-types";
import { evaluateProgramsReadiness } from "./programs-readiness";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";
import { EngagementPhaseGate } from "../components/EngagementPhaseGate";
import { localStore } from "../../../../lib/localStore";
import { auditAreaRegistry } from "../define-scope/define-scope-data";

export function AdminAuditProgramsPage() {
  const { showToast } = useToast();

  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  const engagementId = pathParts[3] || "AEMS-2026-010";

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementId);
  }, [engagementId]);

  const engagementNumber = existingEngagement?.code || engagementId;

  const [packageState, setPackageState] = useState<ProgramsPackageState>(() => {
    const savedPrograms = localStore.getProgramsState(engagementId);
    if (savedPrograms) {
      return savedPrograms;
    }

    const isSeeded = engagementNumber === "AEMS-2026-010";
    if (isSeeded) {
      return {
        ...defaultProgramsPackageState,
        engagementNumber: existingEngagement?.code || engagementId,
        engagementTitle: existingEngagement?.auditArea || existingEngagement?.subject || defaultProgramsPackageState.engagementTitle,
        office: existingEngagement?.office || defaultProgramsPackageState.office,
      };
    }

    return {
      engagementNumber,
      engagementTitle: existingEngagement?.auditArea || existingEngagement?.subject || "",
      office: existingEngagement?.office || "",
      phase: "Audit Planning",
      programs: [],
    };
  });

  const [isNewProgramModalOpen, setIsNewProgramModalOpen] = useState(false);

  // Derive available audit areas for this engagement's office (SCR-212 integration)
  const availableAuditAreas = useMemo(() => {
    const office = existingEngagement?.office || packageState.office;
    if (!office) return [];
    const matched = auditAreaRegistry.filter(
      (a) => a.officeName.toLowerCase() === office.toLowerCase(),
    );
    if (matched.length > 0) return matched;
    return [
      {
        id: "AA-ENG-01",
        code: "AA-001",
        name:
          existingEngagement?.auditArea ||
          existingEngagement?.subject ||
          "Core Operations Audit",
        officeName: office,
        category: "Operations",
        description: "Audit procedures and compliance testing.",
      },
    ];
  }, [existingEngagement, packageState.office]);

  // Derive team assignees from SCR-213 Team Assignment
  const teamState = useMemo(() => {
    return localStore.getTeamAssignment(engagementNumber);
  }, [engagementNumber]);

  const availableAssignees = useMemo(() => {
    if (teamState?.team && teamState.team.length > 0) {
      return teamState.team.map((t) => t.name);
    }
    return [
      "Juan Dela Cruz (Lead Auditor)",
      "Kristine Joy B. Ramos",
      "Engr. Daniel L. Torres",
    ];
  }, [teamState]);

  // Evaluate 5-point Programs Readiness Checklist (MDS-200 Rule 35, §7, §9.2, §19)
  const readiness = useMemo(
    () => evaluateProgramsReadiness(packageState.programs),
    [packageState.programs],
  );

  // Enforce Phase Gating (Phase 1 AEO required for Phase 2 Planning)
  const gate = evaluateEngagementGating(engagementNumber);

  const [selectedAreaFilter, setSelectedAreaFilter] = useState<string>("ALL");

  // Distinct Audit Areas
  const auditAreas = useMemo(() => {
    const areas = new Set<string>();
    packageState.programs.forEach((p) => areas.add(p.auditArea));
    return Array.from(areas);
  }, [packageState.programs]);

  // Filtered Programs
  const displayedPrograms = useMemo(() => {
    if (selectedAreaFilter === "ALL") {
      return packageState.programs;
    }
    return packageState.programs.filter(
      (p) => p.auditArea === selectedAreaFilter,
    );
  }, [packageState.programs, selectedAreaFilter]);

  const totalProcedures = readiness.totalProcedures;
  const totalManDays = readiness.totalManDays;

  const handleCreateProgram = (newProgram: AuditProgram) => {
    const updatedPrograms = [...packageState.programs, newProgram];
    const updatedState: ProgramsPackageState = {
      ...packageState,
      programs: updatedPrograms,
    };
    setPackageState(updatedState);
    localStore.saveProgramsState(packageState.engagementNumber, updatedState);

    showToast({
      title: "Audit Program Formulated",
      description: `Program ${newProgram.id} (${newProgram.programTitle}) added with 1 detailed procedure.`,
      tone: "success",
    });
  };

  const handleSaveDraft = () => {
    localStore.saveProgramsState(packageState.engagementNumber, packageState);
    showToast({
      title: "Audit Programs Saved",
      description: `Programs draft with ${packageState.programs.length} programs and ${totalProcedures} procedures saved.`,
      tone: "info",
    });
  };

  const handleSubmitReview = () => {
    if (!readiness.isReadyForSubmit) {
      showToast({
        title: "Readiness Checklist Incomplete",
        description: `Complete Programs Readiness Checklist before submitting (${readiness.passedCount}/${readiness.totalCount} complete). ${readiness.unmetList.join("; ")}`,
        tone: "warning",
      });
      return;
    }

    const updatedState: ProgramsPackageState = {
      ...packageState,
      programs: packageState.programs.map((p) => ({ ...p, status: "Under Review" })),
    };
    setPackageState(updatedState);
    localStore.saveProgramsState(packageState.engagementNumber, updatedState);

    showToast({
      title: "Audit Programs Package Submitted",
      description: `Fieldwork testing package transmitted to Supervising Auditor for review. Proceeding to Planning Workspace...`,
      tone: "success",
    });

    setTimeout(() => {
      navigateTo(`/admin/audit-engagement/${packageState.engagementNumber}/planning`);
    }, 400);
  };

  const handleReset = () => {
    setPackageState(defaultProgramsPackageState);
    setSelectedAreaFilter("ALL");
    showToast({
      title: "Programs Reset",
      description: "Reverted to default baseline programs.",
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
                    `/admin/audit-engagement/${packageState.engagementNumber}/planning`,
                  )
                }
                className="hover:text-blue-600 transition"
              >
                Planning Workspace (SCR-221)
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Audit Programs (SCR-223)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §7, §9.2, §11, §19 (OD-008) · UID-200 v0.5 §8.3 · Phase 2 Planning · Controlled Document
            </span>
          </div>

          {/* Main Title and Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/planning`,
                  )
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                title="Back to Planning Workspace"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    SCR-223
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Audit Programs & Detailed Testing Procedures
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Manage controlled Audit Programs by Audit Area, linked Risk Matrix items, criteria framework, and working paper testing procedures for <strong>{packageState.engagementNumber}</strong>.
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
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/planning`,
                  )
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
                disabled={!readiness.isReadyForSubmit}
                title={readiness.tooltipReason}
                className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={handleSubmitReview}
              >
                <Send className="h-4 w-4" />
                <span>Submit Programs for Review</span>
                <ArrowRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Main Content or Gated View */}
        {!gate.isPhase1Complete ? (
          <EngagementPhaseGate
            engagementNumber={packageState.engagementNumber}
            requiredPhaseName="Phase 2: Audit Planning"
            requiredPhaseNumber={2}
            screenTitle="Audit Programs (SCR-223)"
          />
        ) : (
          <>
            {/* Audit Area Filter Tabs (OD-008 Area Structure) */}
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white p-2 text-xs">
              <div className="flex flex-wrap items-center gap-1.5">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1 px-1">
                  <Filter size={11} className="text-blue-600" />
                  <span>Audit Area Filter:</span>
                </span>

                <button
                  type="button"
                  onClick={() => setSelectedAreaFilter("ALL")}
                  className={`rounded-md px-2.5 py-1 text-xs font-semibold transition cursor-pointer ${
                    selectedAreaFilter === "ALL"
                      ? "bg-blue-600 text-white shadow-2xs"
                      : "bg-slate-100 text-slate-700 hover:bg-slate-200"
                  }`}
                >
                  All Audit Areas · {packageState.programs.length} Programs
                </button>

                {auditAreas.map((area, idx) => {
                  const count = packageState.programs.filter(
                    (p) => p.auditArea === area,
                  ).length;
                  return (
                    <button
                      key={idx}
                      type="button"
                      onClick={() => setSelectedAreaFilter(area)}
                      className={`rounded-md px-2.5 py-1 text-xs font-semibold transition cursor-pointer max-w-[280px] truncate ${
                        selectedAreaFilter === area
                          ? "bg-blue-600 text-white shadow-2xs"
                          : "bg-slate-100 text-slate-700 hover:bg-slate-200"
                      }`}
                      title={area}
                    >
                      {area} ({count} {count === 1 ? "Program" : "Programs"})
                    </button>
                  );
                })}
              </div>

              <div className="flex items-center gap-2">
                <span className="text-[10px] text-slate-400 font-mono hidden sm:inline">
                  MDS-200 §19 OD-008
                </span>
                <Button
                  type="button"
                  onClick={() => setIsNewProgramModalOpen(true)}
                  className="h-8 rounded-lg text-xs px-3 flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-sm cursor-pointer transition active:scale-[0.99]"
                >
                  <Plus size={13} />
                  <span>New Audit Program</span>
                </Button>
              </div>
            </div>

            {/* 2-Column Main Form Grid */}
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
              {/* Left Column (8 cols): Controlled Programs Cards */}
              <div className="space-y-3 lg:col-span-8">
                {/* Top Identity Banner */}
                <ProgramIdentityBanner
                  engagementNumber={packageState.engagementNumber}
                  engagementTitle={packageState.engagementTitle}
                  office={existingEngagement?.office || packageState.office}
                  totalPrograms={packageState.programs.length}
                  totalProcedures={totalProcedures}
                  totalManDays={totalManDays}
                />

                {/* List of Audit Programs or Empty State */}
                {displayedPrograms.length === 0 ? (
                  <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center space-y-3.5 shadow-2xs">
                    <div className="grid h-12 w-12 place-items-center rounded-full bg-blue-50 text-blue-600 mx-auto">
                      <FileSpreadsheet className="h-6 w-6" />
                    </div>
                    <div className="space-y-1 max-w-md mx-auto">
                      <h3 className="text-sm font-bold text-slate-900">
                        No Audit Programs Defined Yet
                      </h3>
                      <p className="text-xs text-slate-500">
                        Formulate an audit program with detailed procedures, risk matrix linkages, and audit criteria for <strong>{packageState.engagementNumber}</strong> ({existingEngagement?.office || packageState.office}).
                      </p>
                    </div>
                    <Button
                      type="button"
                      onClick={() => setIsNewProgramModalOpen(true)}
                      className="h-9 px-4 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 mx-auto cursor-pointer transition active:scale-[0.99]"
                    >
                      <Plus className="h-4 w-4" />
                      <span>New Audit Program</span>
                    </Button>
                  </div>
                ) : (
                  displayedPrograms.map((prog) => (
                    <AuditProgramCard
                      key={prog.id}
                      program={prog}
                      engagementNumber={packageState.engagementNumber}
                    />
                  ))
                )}
              </div>

              {/* Right Column (4 cols): System Controls & 5-Point Readiness Checklist */}
              <div className="space-y-3 lg:col-span-4">
                <div className="sticky top-4">
                  <ProgramsSummaryRail
                    engagementNumber={packageState.engagementNumber}
                    programs={packageState.programs}
                    onSaveDraft={handleSaveDraft}
                    onSubmitReview={handleSubmitReview}
                  />
                </div>
              </div>
            </div>

            {/* Bottom Action Footer Card */}
            <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-1.5 text-xs text-slate-500">
                <CheckCircle2 size={14} className="text-blue-600 shrink-0" />
                <span>
                  Programs Package: <strong>{packageState.programs.length} Programs • {totalProcedures} Procedures • {totalManDays} Man-Days</strong> • Governed under MDS-200 §7, §9.2, §19 (OD-008)
                </span>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                  onClick={() =>
                    navigateTo(
                      `/admin/audit-engagement/${packageState.engagementNumber}/planning`,
                    )
                  }
                >
                  Return to Planning (SCR-221)
                </Button>

                <Button
                  type="button"
                  disabled={!readiness.isReadyForSubmit}
                  title={readiness.tooltipReason}
                  onClick={handleSubmitReview}
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span>Submit Programs for Review</span>
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </div>

            {/* New Audit Program Modal */}
            <NewAuditProgramModal
              isOpen={isNewProgramModalOpen}
              onClose={() => setIsNewProgramModalOpen(false)}
              engagementNumber={packageState.engagementNumber}
              engagementTitle={packageState.engagementTitle}
              office={existingEngagement?.office || packageState.office}
              defaultAuditType={existingEngagement?.auditType || "Operations Audit"}
              availableAuditAreas={availableAuditAreas}
              availableAssignees={availableAssignees}
              existingProgramsCount={packageState.programs.length}
              onSaveProgram={handleCreateProgram}
            />
          </>
        )}
      </div>
    </AdminLayout>
  );
}

export default AdminAuditProgramsPage;
