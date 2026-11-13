export type PlanStatus =
  | "Completed"
  | "In Progress"
  | "Planned"
  | "Cancelled";

export type AuditQuarter = "Q1" | "Q2" | "Q3" | "Q4";

export type AuditPlanCategory =
  | "Process"
  | "System"
  | "Project"
  | "Fund"
  | "Asset";

export type AuditPlanRiskRating = "Critical" | "High" | "Medium" | "Low";

export type AnnualAuditPlanRecord = {
  id: string;
  code: string;
  subject: string;
  office: string;
  category: AuditPlanCategory;
  riskRating: AuditPlanRiskRating;
  year: string;
  quarter: AuditQuarter;
  planStart: string;
  planEnd: string;
  status: PlanStatus;
};

export type AuditCalendarItem = {
  id: string;
  code: string;
  title: string;
  office: string;
  category: AuditPlanCategory;
  riskRating: AuditPlanRiskRating;
  planStart: string;
  planEnd: string;
  auditTeam: string;
  year: string;
  scheduleDate: string;
  quarter: AuditQuarter;
  status: PlanStatus;
};

export type AnnualAuditPlanningSummary = {
  totalPlannedAudits: number;
  completed: number;
  inProgress: number;
  planned: number;
  cancelled: number;
};
