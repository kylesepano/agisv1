export type AuditProgramStatus = "Draft" | "Under Review" | "Approved" | "Amended";

export type ProcedureStatus = "Pending Execution" | "In Progress" | "Completed";

export type AuditMethod =
  | "Inspection"
  | "Sampling & Vouching"
  | "Recalculation"
  | "Confirmation"
  | "Inquiry & Interview"
  | "Analytical Review"
  | "Observation";

export type DetailedProcedureItem = {
  id: string; // e.g. "PROC-2026-BID-01"
  riskMatrixItemId: string; // e.g. "RMI-2026-002"
  pfdStageRef: string; // e.g. "S2: BAC Pre-Procurement Conference & PhilGEPS Posting"
  procedureTitle: string;
  purpose: string;
  auditMethod: AuditMethod;
  expectedEvidence: string;
  personResponsible: string;
  plannedManDays: number;
  workingPaperRef: string;
  dueDate: string;
  status: ProcedureStatus;
};

export type AuditProgram = {
  id: string; // e.g. "PROG-2026-001-BID"
  auditArea: string; // e.g. "Procurement Process & Supply Chain Compliance Audit"
  programTitle: string;
  description: string;
  auditType: string;
  auditPeriod: string;
  linkedRmiIds: string[]; // e.g. ["RMI-2026-002", "RMI-2026-004"]
  objectivesTrace: string; // references AEP
  criteriaFrameworkRefs: string[]; // references AEP Criteria
  preparedBy: string;
  reviewedBy: string;
  approvedBy: string; // OD-001 Provisional
  effectiveDate: string;
  version: string;
  status: AuditProgramStatus;
  procedures: DetailedProcedureItem[];
};

export type ProgramsPackageState = {
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  phase: string;
  programs: AuditProgram[];
};
