export type AeoStatus =
  | "Draft"
  | "Submitted"
  | "Under Review"
  | "Returned for Correction"
  | "Approved"
  | "Issued"
  | "Amended"
  | "Superseded"
  | "Cancelled"
  | "Voided";

export interface AeoTeamMemberSnapshot {
  id: string;
  name: string;
  role: "Lead Auditor" | "Auditor" | "Technical Specialist" | "Supervising Auditor";
  department: string;
  email: string;
}

export interface AeoSourceBaselineSnapshot {
  snapshottedAt: string;
  snapshotVersion: string;
  engagementNumber: string;
  engagementTitle: string;
  originType: "Planned" | "Unplanned";
  iapSourceId?: string;
  authorityReference: string;
  directingAuthority: string;
  office: string;
  auditAreas: string[];
  auditFocuses: string[];
  periodStart: string;
  periodEnd: string;
  auditType: string;
  priority: "Critical" | "High" | "Medium" | "Low";
  confidentiality: "Normal" | "Restricted" | "Confidential";
  team: AeoTeamMemberSnapshot[];
}

export interface AeoRecipient {
  id: string;
  name: string;
  title: string;
  office: string;
  email: string;
  deliveryMethod: "Digital Notification" | "Official Courier" | "Physical Copy";
  distributionStatus: "Pending" | "Distributed" | "Acknowledged";
  acknowledgedAt?: string;
}

export interface AeoAuditTrailEntry {
  id: string;
  timestamp: string;
  action: string;
  actor: string;
  role: string;
  previousStatus: AeoStatus;
  newStatus: AeoStatus;
  version: string;
  remarks?: string;
}

export interface PrepareAeoFormState {
  aeoNumber: string;
  engagementNumber: string;
  version: string;
  status: AeoStatus;
  lifecyclePhase: "Engagement Initiation";
  
  // Controlled Source Baseline Snapshot
  baseline: AeoSourceBaselineSnapshot;
  
  // Drift Detection State
  isSourceDrifted: boolean;
  driftSummary?: string;

  // AEO Document Body Fields
  orderTitle: string;
  legalMandate: string;
  formalObjectives: string;
  formalScopeSummary: string;
  engagementDirectives: string;
  specialInstructions: string;
  targetDeliverables: string;
  confidentialityClassification: "Normal" | "Restricted" | "Confidential";

  // Signature & Approval Sign-offs (Simple confirmation fields per §20)
  reviewedBy: string;
  reviewedRole: string;
  reviewDate: string;
  reviewRemarks: string;
  
  approvedBy: string;
  approverRole: string;
  approvalDate: string;
  effectiveDate: string;

  // Return for correction reason
  returnReason?: string;

  // Distribution & Superseding
  recipients: AeoRecipient[];
  supersedingReference?: string;
  supersedingReason?: string;
  cancellationReason?: string;

  // Audit History Trail
  history: AeoAuditTrailEntry[];
}
