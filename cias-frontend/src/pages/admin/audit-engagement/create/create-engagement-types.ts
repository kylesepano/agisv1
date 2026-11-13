export type EngagementOriginType = "Planned" | "Unplanned";

export type AuditTypeOption =
  | "Operations Audit"
  | "Compliance Audit"
  | "Financial Audit"
  | "Information Systems Audit"
  | "Special / Management Audit"
  | "Value for Money / Performance Audit"
  | "Process Audit"
  | "Asset Audit";

export type EngagementPriority = "Critical" | "High" | "Medium" | "Low";

export type EngagementConfidentiality = "Normal" | "Restricted" | "Confidential";

export type IapSourceItem = {
  id: string;
  code: string;
  title: string;
  office: string;
  auditType: AuditTypeOption;
  priority: EngagementPriority;
  year: string;
  quarter: string;
  periodStart: string;
  periodEnd: string;
  objective?: string;
  scopeSummary?: string;
  authorityReference?: string;
  directingAuthority?: string;
  dateReceived?: string;
  status?: string;
  provisional_iap_link?: boolean;
  isAlreadyActivated?: boolean;
  activatedEngagementCode?: string;
};

export type CreateEngagementFormState = {
  engagementNumber: string;
  originType: EngagementOriginType;
  iapSourceId: string;
  title: string;
  auditType: AuditTypeOption | "";
  authorityReference: string;
  directingAuthority: string;
  // DGM-200-01 / MDS-200 §5.1: Date directive/authority reference was received
  dateReceived: string;
  // MDS-200 §7 (Audit Engagement Entity: Authorization Decision)
  // For Unplanned path: records the specific authorization decision / disposition
  authorizationDecision?: string;
  startDate: string;
  endDate: string;
  priority: EngagementPriority;
  confidentiality: EngagementConfidentiality;
  // Hand-off fields to SCR-212 (optional background context)
  office?: string;
  objectives?: string;
  scopeSummary?: string;
  // System-controlled read-only fields
  phase: "Engagement Initiation";
  status: "Draft";
};

export type DuplicateConflict = {
  engagementId: string;
  code: string;
  title: string;
  office: string;
  auditType: string;
  startDate: string;
  endDate: string;
  status: string;
  iapRef?: string;
  reason?: string;
};
