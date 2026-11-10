import { z } from "zod";

export const workingPaperEvidenceLinkSchema = z.object({
  id: z.string(),
  title: z.string().min(2, "Evidence title required"),
  type: z.string().min(2, "Evidence type required"),
  sourceUrlOrRef: z.string().min(2, "Source reference required"),
});

export const workingPaperReviewNoteRefSchema = z.object({
  id: z.string(),
  note: z.string(),
  reviewer: z.string(),
  response: z.string().optional(),
  resolution: z.string().optional(),
  clearedBy: z.string().optional(),
  clearanceDate: z.string().optional(),
  status: z.enum(["Open", "In Discussion", "Cleared"]),
});

export const workingPaperSchema = z.object({
  id: z.string().min(1, "Working Paper ID is required"),
  referenceNumber: z.string().min(1, "Reference Number is required"),
  engagementNumber: z.string().min(1, "Engagement Number is required"),

  type: z.enum(["Planning", "Sampling", "Execution", "Reporting"]),
  plannedReference: z.string().min(1, "Planned Reference is required (SCR-222/223)"),
  procedureId: z.string().min(1, "Procedure ID link is mandatory (SCR-224)"),
  procedureTitle: z.string().min(1, "Procedure Title is required"),
  riskMatrixItemId: z.string().min(1, "Risk Matrix Item ID is required (SCR-221)"),
  riskMatrixTitle: z.string().min(1, "Risk Matrix Title is required"),
  auditArea: z.string().min(1, "Audit Area is required"),

  purpose: z.string().min(10, "Purpose must be specified (min 10 chars)"),
  workPerformed: z.string().min(20, "Work performed narrative is mandatory (Rule 13, min 20 chars)"),

  sourceFieldworkRecordId: z.string().optional(),
  sourceFieldworkType: z.string().optional(),

  evidenceLinks: z.array(workingPaperEvidenceLinkSchema),

  results: z.string().min(15, "Results summary is required (min 15 chars)"),
  conclusion: z.string().min(15, "Conclusion is required (min 15 chars)"),

  preparer: z.string().min(2, "Preparer signatory is required"),
  reviewer: z.string().min(2, "Reviewing signatory is required"),

  reviewNotes: z.array(workingPaperReviewNoteRefSchema),

  reviewStatus: z.enum(["Draft", "Submitted", "Reviewed", "Cleared"]),
  version: z.string(),
});

export type WorkingPaperValidationResult = {
  isValid: boolean;
  errors: Record<string, string>;
};
