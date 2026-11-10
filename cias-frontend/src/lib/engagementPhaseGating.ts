import { localStore } from "./localStore";
import type { AuditEngagementRecord } from "../pages/admin/audit-engagement/audit-engagement-types";

export type EngagementGatingCheck = {
  isAeoIssued: boolean;
  isScopeLocked: boolean;
  isTeamLocked: boolean;
  aeoStatus: string;
  isPhase1Complete: boolean;
  isPhase2Complete: boolean;
  isPhase3Complete: boolean;
  currentPhaseNumber: number;
  currentPhaseName: string;
  currentStepTitle: string;
  currentStepRoute: string;
  engagement: AuditEngagementRecord | null;
  reasons: string[];
};

export function evaluateEngagementGating(engagementIdOrCode: string): EngagementGatingCheck {
  const engagement = localStore.getEngagement(engagementIdOrCode);
  const aeo = localStore.getAeoState(engagementIdOrCode);
  const team = localStore.getTeamAssignment(engagementIdOrCode);
  const aep = localStore.getAepState(engagementIdOrCode);
  const conference = localStore.getConferenceState(engagementIdOrCode, "entry");

  const isSeededSample = engagement?.code === "AEMS-2026-010";

  // Single Source of Truth for AEO Issuance
  const aeoStatus = aeo?.status || (isSeededSample ? "Issued" : "Draft");
  const isAeoIssued = Boolean(
    aeoStatus === "Issued" ||
    aeoStatus === "Amended" ||
    aeoStatus === "Superseded" ||
    engagement?.status === "Active" ||
    engagement?.status === "Approved" ||
    engagement?.status === "Completed" ||
    engagement?.status === "Closed" ||
    (engagement?.progress ?? 0) >= 25 ||
    isSeededSample
  );

  // Once AEO is Issued, upstream Phase 1 definitions (Scope SCR-212 and Team SCR-213) are officially locked (MDS-200 Rule 31)
  const isScopeLocked = isAeoIssued;
  const isTeamLocked = isAeoIssued;

  // Phase 1: Initiation
  // Checks: Scope (SCR-212), Team (SCR-213), AEO Issuance (SCR-214)
  const hasScope = Boolean(engagement?.office && (engagement.auditArea || engagement.subject));
  const hasTeam = Boolean(engagement?.leadAuditor || team?.isCollectiveSufficiencyConfirmed || team?.team?.some(t => t.role === "Lead Auditor"));

  const isPhase1Complete = isAeoIssued;

  // Next route in Phase 1 if incomplete:
  let currentStepTitle = "Define Scope (SCR-212)";
  let currentStepRoute = `/admin/audit-engagement/${engagement?.code || engagementIdOrCode}/define-scope`;

  if (!hasScope) {
    currentStepTitle = "Define Scope (SCR-212)";
    currentStepRoute = `/admin/audit-engagement/${engagement?.code || engagementIdOrCode}/define-scope`;
  } else if (!hasTeam) {
    currentStepTitle = "Assign Audit Team (SCR-213)";
    currentStepRoute = `/admin/audit-engagement/${engagement?.code || engagementIdOrCode}/team-assignment`;
  } else if (!isAeoIssued) {
    currentStepTitle = "Prepare & Issue AEO (SCR-214)";
    currentStepRoute = `/admin/audit-engagement/${engagement?.code || engagementIdOrCode}/prepare-order`;
  }

  // Phase 2: Planning
  const isPhase2Complete = isPhase1Complete && (Boolean(aep) || (engagement?.progress ?? 0) >= 35 || isSeededSample);

  // Phase 3: Entry Conference (MDS-200 Module Rule 11)
  const isPhase3Complete = isPhase1Complete && (
    conference?.status === "Conducted" ||
    conference?.status === "Documented" ||
    conference?.status === "Waived" ||
    conference?.waiver?.isWaiverActive === true ||
    (engagement?.progress ?? 0) >= 40 ||
    isSeededSample
  );

  let currentPhaseNumber = 1;
  let currentPhaseName = "Phase 1: Engagement Initiation";

  if (isPhase3Complete) {
    currentPhaseNumber = 4;
    currentPhaseName = "Phase 4: Fieldwork Execution";
    currentStepTitle = "Execution Hub (SCR-226)";
    currentStepRoute = `/admin/audit-engagement/${engagement?.code || engagementIdOrCode}/execution`;
  } else if (isPhase2Complete) {
    currentPhaseNumber = 3;
    currentPhaseName = "Phase 3: Entry Conference";
    currentStepTitle = "Conduct Entry Conference (SCR-225)";
    currentStepRoute = `/admin/audit-engagement/${engagement?.code || engagementIdOrCode}/conference/entry`;
  } else if (isPhase1Complete) {
    currentPhaseNumber = 2;
    currentPhaseName = "Phase 2: Audit Planning";
    currentStepTitle = "Prepare AEP (SCR-222)";
    currentStepRoute = `/admin/audit-engagement/${engagement?.code || engagementIdOrCode}/prepare-aep`;
  }

  const reasons: string[] = [];
  if (!isPhase1Complete) {
    reasons.push("Phase 1 Engagement Initiation is incomplete: Audit Engagement Order (AEO) has not been issued.");
  }
  if (isPhase1Complete && !isPhase3Complete) {
    reasons.push("MDS-200 Module Rule 11 Gating: Entry Conference has not been conducted and no waiver has been documented.");
  }

  return {
    isAeoIssued,
    isScopeLocked,
    isTeamLocked,
    aeoStatus,
    isPhase1Complete,
    isPhase2Complete,
    isPhase3Complete,
    currentPhaseNumber,
    currentPhaseName,
    currentStepTitle,
    currentStepRoute,
    engagement,
    reasons,
  };
}
