import { z } from "zod";

export const prepareAeoSchema = z.object({
  aeoNumber: z.string().min(1, "AEO Number is required."),
  orderTitle: z
    .string()
    .trim()
    .min(3, "Official Order Title is required."),
  legalMandate: z
    .string()
    .trim()
    .min(3, "Statutory & Legal Mandate is required."),
  formalObjectives: z
    .string()
    .trim()
    .min(3, "Formal Audit Objectives are required."),
  formalScopeSummary: z
    .string()
    .trim()
    .min(3, "Formal Scope Summary is required."),
  engagementDirectives: z
    .string()
    .trim()
    .min(3, "Specific Directives to Auditee Office are required."),
  specialInstructions: z.string().optional().default(""),
  targetDeliverables: z.string().optional().default(""),
  confidentialityClassification: z.enum(["Normal", "Restricted", "Confidential"]),
  reviewedBy: z.string().min(1, "Reviewer name is required."),
  approvedBy: z.string().min(1, "Approver name is required."),
});

export type PrepareAeoSchemaType = z.infer<typeof prepareAeoSchema>;
