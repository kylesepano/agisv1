export type TaskPriority = "Low" | "Medium" | "High" | "Urgent";
export type TaskStatus = "Pending" | "In Progress" | "Completed" | "Blocked";
export type TaskType =
  | "Fieldwork Sampling"
  | "Voucher Verification"
  | "Physical Inventory Inspection"
  | "Liaison & Inquiries"
  | "Working Paper Review";

export type EngagementTask = {
  id: string; // e.g. TSK-2026-001
  type: TaskType;
  title: string;
  assignee: string; // From SCR-213 team
  relatedRecord: string; // e.g. PROC-2026-001-01, WP-2026-001-BID
  dueDate: string;
  priority: TaskPriority;
  status: TaskStatus;
};

export type ReviewNoteStatus = "Open" | "In Discussion" | "Cleared" | "Waived";

export type ReviewNote = {
  id: string; // e.g. RN-2026-001
  relatedEntity: string; // e.g. PROC-2026-001-02 / WP-2026-001-BID-02
  reviewer: string; // e.g. Atty. Fernando M. Gomez, CPA (Supervising Auditor)
  note: string;
  dueDate: string;
  response: string;
  resolution: string;
  clearedBy?: string;
  status: ReviewNoteStatus;
};

export type ProcedureExecutionStatus =
  | "Pending Execution"
  | "In Progress"
  | "Completed"
  | "Blocked";

export type ProcedureProgressItem = {
  id: string; // e.g. PROC-2026-001-01
  programId: string;
  auditArea: string;
  title: string;
  purpose: string;
  auditMethod: string;
  assignedAuditor: string;
  plannedManDays: number;
  actualManDays: number;
  workingPaperRef: string;
  dueDate: string;
  status: ProcedureExecutionStatus;
};

export type ExecutionBlocker = {
  id: string;
  title: string;
  description: string;
  impactLevel: "Low" | "Moderate" | "Critical";
  reportedBy: string;
  reportedDate: string;
  status: "Active" | "Resolved";
  resolutionNotes?: string;
};

export type ExecutionWorkspaceState = {
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  phaseNumber: number;
  phaseName: string;
  status: string;
  fieldworkStartDate: string;
  fieldworkEndDate: string;
  totalPlannedManDays: number;
  totalActualManDays: number;
  procedures: ProcedureProgressItem[];
  tasks: EngagementTask[];
  reviewNotes: ReviewNote[];
  blockers: ExecutionBlocker[];
};
