export const engagementStatusOptions = [
  "All Statuses",
  "Active",
  "For Approval",
  "Approved",
  "On Hold",
  "Completed",
  "Closed",
  "Draft",
  "Cancelled",
] as const;

export const engagementAuditTypeOptions = [
  "All Audit Types",
  "Compliance Audit",
  "Operations Audit",
  "Financial Audit",
  "Information Systems Audit",
  "Process Audit",
  "Asset Audit",
  "Value for Money / Performance Audit",
  "Special / Management Audit",
] as const;

// Backward-compatible alias for category
export const engagementCategoryOptions = engagementAuditTypeOptions;

export const engagementYearOptions = ["2026", "2025", "2024", "2023"] as const;
