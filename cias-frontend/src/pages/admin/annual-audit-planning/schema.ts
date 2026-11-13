import { z } from "zod";

export const annualAuditPlanSchema = z.object({
  code: z.string().min(1, "Plan code is required."),
  subject: z.string().min(1, "Audit subject is required."),
  office: z.string().min(1, "Office is required."),
  category: z.enum(["Process", "System", "Project", "Fund", "Asset"]),
  riskRating: z.enum(["Critical", "High", "Medium", "Low"]),
  year: z.string().min(1, "Year is required."),
  quarter: z.enum(["Q1", "Q2", "Q3", "Q4"]),
  planStart: z.string().min(1, "Plan start date is required."),
  planEnd: z.string().min(1, "Plan end date is required."),
  status: z.enum(["Completed", "In Progress", "Planned", "Cancelled"]),
});

export type AnnualAuditPlanSchema = z.infer<typeof annualAuditPlanSchema>;
