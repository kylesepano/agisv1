import { z } from "zod";

export const fieldworkEvidenceLinkSchema = z.object({
  id: z.string(),
  title: z.string().min(2, "Evidence title required"),
  type: z.string().min(2, "Evidence type required"),
  sourceUrlOrRef: z.string().min(2, "Source reference required"),
});

export const fieldworkRecordSchema = z.object({
  id: z.string().min(1, "Record ID is required"),
  engagementNumber: z.string().min(1, "Engagement Number is required"),
  procedureId: z.string().min(1, "Procedure ID link is mandatory (MDS-200 §7.1)"),
  procedureTitle: z.string().min(1, "Procedure Title is required"),
  auditArea: z.string().min(1, "Audit Area is required"),
  workingPaperRef: z.string().min(1, "Working Paper Reference is required (Rule 13)"),

  type: z.enum([
    "Interview / Inquiry",
    "Physical Inspection",
    "Process Walkthrough",
    "Observation / Spot Count",
    "Coordination Meeting",
    "Field Audit Notes",
  ]),
  date: z.string().min(1, "Fieldwork date is required"),
  location: z.string().min(3, "Fieldwork location is required (min 3 chars)"),
  personsInvolved: z.string().min(3, "Persons involved is required (min 3 chars)"),
  purpose: z.string().min(10, "Purpose must be specified (min 10 chars)"),
  factsObtained: z.string().min(15, "Facts obtained is mandatory (min 15 chars)"),
  notes: z.string().min(5, "Auditor notes required (min 5 chars)"),

  evidenceLinks: z.array(fieldworkEvidenceLinkSchema),

  preparer: z.string().min(2, "Preparer auditor is required"),
  reviewer: z.string().min(2, "Reviewing auditor is required"),
  status: z.enum(["Draft", "Submitted", "Reviewed"]),
  version: z.string(),
});

export type FieldworkValidationResult = {
  isValid: boolean;
  errors: Record<string, string>;
};
