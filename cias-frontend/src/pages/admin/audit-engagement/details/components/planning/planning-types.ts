export type RiskRating = "Critical" | "High" | "Medium" | "Low";

export type ControlEffectiveness = "Effective" | "Partially Effective" | "Ineffective" | "Not Implemented";

export type ProcessFlowStep = {
  stepNumber: number;
  title: string;
  responsibleRole: string;
  description: string;
  controlPoint: string;
  identifiedRisks: string[];
};

export type EngagementRiskItem = {
  id: string;
  auditFocusArea: string;
  processArea: string;
  riskEvent: string;
  inherentRisk: RiskRating;
  existingControls: string;
  controlEffectiveness: ControlEffectiveness;
  residualRisk: RiskRating;
  linkedProcedureRef: string;
};

export type AuditProgramItem = {
  id: string;
  programTitle: string;
  auditArea: string;
  proceduresCount: number;
  workingPaperRef: string;
  samplingMethod: string;
  plannedHours: number;
  assignedAuditor: string;
};

export type AepStrategyDetails = {
  aepNumber: string;
  version: string;
  auditStrategySummary: string;
  plannedManDays: number;
  fieldworkStartDate: string;
  fieldworkEndDate: string;
  reportingTargetDate: string;
  keyKpiTarget: string;
};

export type EntryConferenceInfo = {
  conferenceId: string;
  type: "Entry Conference" | "Exit Conference";
  status: "Scheduled" | "Conducted" | "Documented" | "Waived";
  date: string;
  isConductedOrWaived: boolean;
};

export type PlanningPackageState = {
  version: string;
  status: "Draft" | "Under Review" | "Approved";
  preparedBy: string;
  reviewer: string;
  approver: string;
  processFlows: ProcessFlowStep[];
  risks: EngagementRiskItem[];
  aep: AepStrategyDetails;
  auditPrograms: AuditProgramItem[];
  entryConference?: EntryConferenceInfo;
};
