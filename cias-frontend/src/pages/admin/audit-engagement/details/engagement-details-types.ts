export type EngagementDetailsTab =
  | "details"
  | "overview"
  | "planning"
  | "execution"
  | "audit-issues"
  | "afrs"
  | "conferences"
  | "audit-reports"
  | "completion-transfer"
  | "activity";

export type EngagementLifecyclePhase =
  | "1. Engagement Initiation and Authorization"
  | "2. Audit Planning"
  | "3. Entry Conference or Authorized Commencement"
  | "4. Audit Execution and Documentation"
  | "5. Audit Issue Evaluation and Findings Development"
  | "6. Management Comment and Auditor Evaluation"
  | "7. Exit Conference and Final Validation"
  | "8. Audit Reporting and Issuance"
  | "9. Engagement Completion and CMS Transfer"
  | "10. Administrative Closure";

export type EngagementAdminStatus =
  | "Draft"
  | "For Approval"
  | "Approved"
  | "Active"
  | "On Hold"
  | "Completed"
  | "Closed"
  | "Cancelled";

export interface EngagementTeamMember {
  id: string;
  name: string;
  role: "Lead Auditor" | "Auditor" | "Supervising Auditor" | "Technical Specialist";
  department: string;
  email: string;
}

export interface EngagementAeoReference {
  aeoNumber: string;
  status: "Issued" | "Approved" | "Draft";
  version: string;
  issuedDate: string;
  effectiveDate: string;
  approver: string;
}

export interface EngagementOperationalCounts {
  workingPapersCount: number;
  evidenceItemsCount: number;
  auditIssuesCount: number;
  afrsCount: number;
  conferencesCount: number;
  reportsCount: number;
  openActionItemsCount: number;
}

export interface EngagementActivityEvent {
  id: string;
  timestamp: string;
  title: string;
  description: string;
  actor: string;
  role: string;
  phase: string;
  status: string;
  type: "initiation" | "scope" | "team" | "order" | "planning" | "review";
}

export interface FullEngagementDetails {
  id: string;
  code: string;
  title: string;
  auditType: string;
  office: string;
  phase: EngagementLifecyclePhase;
  phaseNumber: number;
  status: EngagementAdminStatus;
  originType: "Planned" | "Unplanned";
  annualPlanRef?: string;
  authorityReference: string;
  directingAuthority: string;
  dateAuthorized: string;
  priority: "Critical" | "High" | "Medium" | "Low";
  confidentiality: "Normal" | "Restricted" | "Confidential";
  startDate: string;
  endDate: string;
  lastUpdated?: string;
  plannedManDays: number;
  progressPercent: number;
  
  // Scope & Objectives
  objectives: string;
  scopeSummary: string;
  auditAreas: string[];
  auditFocuses: string[];
  boundaries: string;
  exclusions: string;
  knownLimitations: string;

  // Team & AEO
  team: EngagementTeamMember[];
  aeo?: EngagementAeoReference;

  // Counts & Activity
  counts: EngagementOperationalCounts;
  recentActivity: EngagementActivityEvent[];
}
