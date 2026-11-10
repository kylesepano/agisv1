import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  Lock,
  RotateCcw,
  Save,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  EngagementContextStrip,
  IndependenceAccessSection,
  TeamRosterSection,
  TeamSufficiencySection,
  TeamSystemControlsCard,
} from "./components";
import { auditAreaRegistry } from "../define-scope/define-scope-data";
import { defaultTeamAssignmentState } from "./team-assignment-data";
import { teamAssignmentSchema } from "./team-assignment-schema";
import type {
  AssignedTeamMember,
  TeamAssignmentFormState,
} from "./team-assignment-types";
import { localStore } from "../../../../lib/localStore";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";

export function AdminTeamAssignmentPage() {
  const { showToast } = useToast();

  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  const engagementId = pathParts[3] || "AEMS-2026-010";

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementId);
  }, [engagementId]);

  const [formState, setFormState] = useState<TeamAssignmentFormState>(() => {
    const savedTeam = localStore.getTeamAssignment(engagementId);
    if (savedTeam) return savedTeam;

    const savedScope = localStore.getScopeState(engagementId);
    const office = savedScope?.office || existingEngagement?.office || defaultTeamAssignmentState.office;
    let resolvedAreas = defaultTeamAssignmentState.auditAreas;

    if (savedScope && savedScope.selectedAuditAreaIds && savedScope.selectedAuditAreaIds.length > 0) {
      resolvedAreas = savedScope.selectedAuditAreaIds.map((id) => {
        const a = auditAreaRegistry.find((item) => item.id === id || item.code === id);
        return a ? `${a.code} — ${a.name}` : id;
      });
    } else if (office) {
      const officeAreas = auditAreaRegistry.filter(
        (a) => a.officeName.toLowerCase() === office.toLowerCase(),
      );
      if (officeAreas.length > 0) {
        resolvedAreas = officeAreas.map((a) => `${a.code} — ${a.name}`);
      }
    }

    if (existingEngagement) {
      const start = existingEngagement.startDate || "2026-02-01";
      const end = existingEngagement.endDate || "2026-04-30";
      const isSeeded = existingEngagement.code === "AEMS-2026-010";

      return {
        ...defaultTeamAssignmentState,
        engagementNumber: existingEngagement.code,
        title: existingEngagement.auditArea || existingEngagement.subject || "Engagement Audit",
        office: office,
        auditType: existingEngagement.auditType || existingEngagement.category || defaultTeamAssignmentState.auditType,
        auditAreas: resolvedAreas,
        periodStart: start,
        periodEnd: end,
        team: isSeeded
          ? defaultTeamAssignmentState.team.map((m) => ({
            ...m,
            assignmentStartDate: start,
            assignmentEndDate: end,
          }))
          : [
            {
              id: "TM-ROW-1",
              userId: "",
              name: "",
              role: "Lead Auditor",
              assignmentStartDate: start,
              assignmentEndDate: end,
              competencySummary: "",
              isAvailable: false,
              workloadNote: "",
              hasScheduleConflict: false,
              scheduleConflictNote: "",
              supervisorName: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
              hasConflictOfInterest: false,
              disclosedImpairments: "",
              safeguardsDocumented: "",
              hasSystemAccessConfirmed: false,
            },
          ],
        isCollectiveSufficiencyConfirmed: false,
        collectiveSufficiencyJustification: "",
      };
    }
    return defaultTeamAssignmentState;
  });

  // Validation
  const validationResult = useMemo(() => {
    const parsed = teamAssignmentSchema.safeParse(formState);
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

  // Handlers for Team Roster
  const handleUpdateMember = (id: string, updates: Partial<AssignedTeamMember>) => {
    if (isReadOnly) return;
    setFormState((prev) => ({
      ...prev,
      team: prev.team.map((m) => (m.id === id ? { ...m, ...updates } : m)),
    }));
  };

  const handleAddMember = () => {
    if (isReadOnly) return;
    const newId = `TM-ROW-${Date.now().toString().slice(-4)}`;
    const newMember: AssignedTeamMember = {
      id: newId,
      userId: "",
      name: "",
      role: "Auditor",
      assignmentStartDate: formState.periodStart,
      assignmentEndDate: formState.periodEnd,
      competencySummary: "",
      isAvailable: false,
      workloadNote: "",
      hasScheduleConflict: false,
      scheduleConflictNote: "",
      supervisorName: formState.team.find((m) => m.role === "Lead Auditor")?.name
        ? `${formState.team.find((m) => m.role === "Lead Auditor")?.name} (Lead Auditor)`
        : "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
      hasConflictOfInterest: false,
      disclosedImpairments: "",
      safeguardsDocumented: "",
      hasSystemAccessConfirmed: false,
    };

    setFormState((prev) => ({
      ...prev,
      team: [...prev.team, newMember],
    }));
  };

  const handleRemoveMember = (id: string) => {
    if (isReadOnly || formState.team.length <= 1) return;
    setFormState((prev) => ({
      ...prev,
      team: prev.team.filter((m) => m.id !== id),
    }));
  };

  const gate = useMemo(() => {
    return evaluateEngagementGating(engagementId);
  }, [engagementId]);

  const isReadOnly = gate.isTeamLocked;

  const handleReset = () => {
    if (isReadOnly) return;
    const start = existingEngagement?.startDate || formState.periodStart || "2026-02-01";
    const end = existingEngagement?.endDate || formState.periodEnd || "2026-04-30";
    setFormState({
      ...defaultTeamAssignmentState,
      engagementNumber: formState.engagementNumber,
      title: formState.title,
      office: formState.office,
      auditType: formState.auditType,
      periodStart: start,
      periodEnd: end,
      team: defaultTeamAssignmentState.team.map((m) => ({
        ...m,
        assignmentStartDate: start,
        assignmentEndDate: end,
      })),
      isCollectiveSufficiencyConfirmed: false,
      collectiveSufficiencyJustification: "",
    });
    showToast({
      title: "Form Reset",
      description: "Team assignments have been reverted to default state.",
      tone: "info",
    });
  };

  const handleSaveAndProceed = (e: React.FormEvent) => {
    e.preventDefault();
    if (isReadOnly) {
      navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/prepare-order`);
      return;
    }

    if (!validationResult.isValid) {
      showToast({
        title: "Validation Error",
        description: "Please complete all mandatory team, competency, and sufficiency fields.",
        tone: "warning",
      });
      return;
    }

    localStore.saveTeamAssignment(formState.engagementNumber, formState);

    if (existingEngagement) {
      const leadMember = formState.team.find((m) => m.role.toLowerCase().includes("lead"));
      const otherMembers = formState.team.filter((m) => !m.role.toLowerCase().includes("lead"));
      const teamSummary = otherMembers.map((m) => m.name).join(", ");

      localStore.saveEngagement({
        ...existingEngagement,
        leadAuditor: leadMember?.name || existingEngagement.leadAuditor,
        auditTeam: teamSummary || existingEngagement.auditTeam,
        startDate: formState.periodStart || existingEngagement.startDate,
        endDate: formState.periodEnd || existingEngagement.endDate,
        progress: Math.max(existingEngagement.progress || 0, 30),
      });
    }

    showToast({
      title: "Audit Team Validated & Assigned",
      description: `Team roster validated for ${formState.engagementNumber}. Proceeding to Prepare AEO (SCR-214)...`,
      tone: "success",
    });

    setTimeout(() => {
      navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/prepare-order`);
    }, 400);
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Unified Top Header Card matching SCR-211/212/214 */}
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
                  navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/define-scope`)
                }
                className="hover:text-blue-600 transition"
              >
                Define Scope ({formState.engagementNumber})
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Assign Audit Team (SCR-213)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §9.1 AEM-FR-008 · DGM-200-01 v0.2 · Engagement Foundation · Transaction
            </span>
          </div>

          {/* Main Title and Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/define-scope`)
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                title="Back to Define Scope"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    SCR-213
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Assign Audit Team & Lead Auditor
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Validate required roles, competency, collective sufficiency, availability, supervision, and independence declarations for <strong>{formState.engagementNumber}</strong>.
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
                  navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/define-scope`)
                }
              >
                Cancel
              </Button>

              {!isReadOnly ? (
                <>
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
                    onClick={handleSaveAndProceed}
                  >
                    <Save className="h-4 w-4" />
                    <span>Save & Proceed (SCR-214)</span>
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                </>
              ) : (
                <div className="flex items-center gap-2">
                  <div className="flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 border border-emerald-200">
                    <Lock size={13} />
                    <span>Team Locked (AEO Issued)</span>
                  </div>
                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                    onClick={() => navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/prepare-order`)}
                  >
                    <span>View AEO Order (SCR-214)</span>
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* 2-Column Form Layout */}
        <form onSubmit={handleSaveAndProceed} className="space-y-3">
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
            {/* Left Column (8 cols): Read-Only Scope Context + Team Roster + Sufficiency + Independence */}
            <div className="space-y-3 lg:col-span-8">
              {/* 1. Engagement Context Read-Only Strip */}
              <EngagementContextStrip
                engagementNumber={formState.engagementNumber}
                title={formState.title}
                office={formState.office}
                auditType={formState.auditType}
                auditAreas={formState.auditAreas}
                periodStart={formState.periodStart}
                periodEnd={formState.periodEnd}
              />

              {/* 2. Team Roster Repeatable Rows */}
              <TeamRosterSection
                team={formState.team}
                periodStart={formState.periodStart}
                periodEnd={formState.periodEnd}
                isReadOnly={isReadOnly}
                onUpdateMember={handleUpdateMember}
                onAddMember={handleAddMember}
                onRemoveMember={handleRemoveMember}
              />

              {/* 3. Collective Team Sufficiency Assessment */}
              <TeamSufficiencySection
                auditAreas={formState.auditAreas}
                isConfirmed={formState.isCollectiveSufficiencyConfirmed}
                justification={formState.collectiveSufficiencyJustification}
                isReadOnly={isReadOnly}
                onToggleConfirm={(confirmed) =>
                  !isReadOnly && setFormState((prev) => ({
                    ...prev,
                    isCollectiveSufficiencyConfirmed: confirmed,
                  }))
                }
                onUpdateJustification={(justification) =>
                  !isReadOnly && setFormState((prev) => ({
                    ...prev,
                    collectiveSufficiencyJustification: justification,
                  }))
                }
              />

              {/* 4. Independence, Objectivity & Access Controls */}
              <IndependenceAccessSection
                team={formState.team}
                isReadOnly={isReadOnly}
                onUpdateMember={handleUpdateMember}
              />
            </div>

            {/* Right Column (4 cols): System Controls & 7-Point Readiness Checklist */}
            <div className="space-y-3 lg:col-span-4">
              <div className="sticky top-4">
                <TeamSystemControlsCard
                  formState={formState}
                  isLocked={gate.isTeamLocked}
                  aeoStatus={gate.aeoStatus}
                />
              </div>
            </div>
          </div>

          {/* Bottom Footer Action Card */}
          <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-1.5 text-xs text-slate-500">
              <span className={`h-2 w-2 rounded-full shrink-0 ${isReadOnly ? "bg-emerald-500" : "bg-blue-600"}`} />
              <span>
                {isReadOnly
                  ? "Audit team assignment is locked under Issued AEO per MDS-200 Rule 31."
                  : "Status remains Draft • Phase: Engagement Initiation • AEO is prepared next (SCR-214)"}
              </span>
            </div>

            <div className="flex items-center gap-2 shrink-0">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/define-scope`)
                }
              >
                Return to Scope (SCR-212)
              </Button>
              {!isReadOnly ? (
                <Button
                  type="submit"
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99]"
                  disabled={!validationResult.isValid}
                >
                  <span>Save & Proceed to AEO (SCR-214)</span>
                  <ArrowRight className="h-4 w-4" />
                </Button>
              ) : (
                <Button
                  type="button"
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                  onClick={() => navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/prepare-order`)}
                >
                  <span>Proceed to Prepare AEO (SCR-214)</span>
                  <ArrowRight className="h-4 w-4" />
                </Button>
              )}
            </div>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
}

export default AdminTeamAssignmentPage;
