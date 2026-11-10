import { z } from "zod";

export const auditEngagementSchema = z.object({
  code: z.string().min(1, "Engagement number is required."),
  auditArea: z.string().min(1, "Audit area is required."),
  office: z.string().min(1, "Office is required."),
  auditType: z.enum([
    "Compliance Audit",
    "Operations Audit",
    "Financial Audit",
    "Information Systems Audit",
    "Process Audit",
    "Asset Audit",
    "Value for Money / Performance Audit",
    "Special / Management Audit",
  ]),
  riskRating: z.enum(["Critical", "High", "Medium", "Low"]),
  annualPlanRef: z.string().min(1, "Annual plan reference is required."),
  leadAuditor: z.string().min(1, "Lead auditor is required."),
  startDate: z.string().min(1, "Start date is required."),
  endDate: z.string().min(1, "End date is required."),
  status: z.enum([
    "Draft",
    "For Approval",
    "Approved",
    "Active",
    "On Hold",
    "Completed",
    "Closed",
    "Cancelled",
  ]),
});

export type AuditEngagementSchema = z.infer<typeof auditEngagementSchema>;
