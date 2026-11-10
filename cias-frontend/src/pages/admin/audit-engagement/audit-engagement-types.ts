export type EngagementStatus =
  | "Draft"
  | "For Approval"
  | "Approved"
  | "Active"
  | "On Hold"
  | "Completed"
  | "Closed"
  | "Cancelled";

export type EngagementAuditType =
  | "Compliance Audit"
  | "Operations Audit"
  | "Financial Audit"
  | "Information Systems Audit"
  | "Process Audit"
  | "Asset Audit"
  | "Value for Money / Performance Audit"
  | "Special / Management Audit";

// Legacy alias for compatibility
export type EngagementCategory = EngagementAuditType;

export type EngagementRiskRating = "Critical" | "High" | "Medium" | "Low";

export type AuditEngagementRecord = {
  id: string;
  code: string;
  auditArea: string; // Standardized per MDS-200 (was subject)
  subject?: string; // Backward compatibility alias
  office: string;
  auditType: EngagementAuditType; // Standardized per MDS-200 §7 (was category)
  auditTypes?: EngagementAuditType[];
  category?: EngagementAuditType; // Backward compatibility alias
  riskRating: EngagementRiskRating; // Flagged as provisional per OD-003
  annualPlanRef: string;
  leadAuditor: string;
  auditTeam: string;
  startDate: string;
  endDate: string;
  status: EngagementStatus;
  objective: string;
  scope: string;
  remarks: string;
  updatedBy: string;
  lastUpdated: string;
  year: string;
  progress?: number;
  dateReceived?: string;
  supportingDocumentName?: string;
  periodCoveredStart?: string;
  periodCoveredEnd?: string;
  aeoNumber?: string;
  aeoDate?: string;
  departmentHead?: string;
  supportStaff?: string[];
  aeoDocumentName?: string;
  authorityReference?: string;
  directingAuthority?: string;
  originType?: "Planned" | "Unplanned";
  title?: string;
  phase?: string;
  health?: "On Track" | "Delayed" | "At Risk";
};

export type AuditEngagementSummary = {
  totalEngagements: number;
  active: number;
  forApproval: number;
  onHold: number;
  completed: number;
  closed: number;
  draft: number;
  approved: number;
  cancelled: number;
  // Legacy aliases for compatibility
  planned?: number;
  ongoing?: number;
};
