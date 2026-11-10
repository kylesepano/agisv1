import { z } from "zod";

export const assessmentMatrixSchema = z.object({
  auditableUnit: z.string().min(1, "Auditable unit is required."),
  office: z.string().min(1, "Office is required."),
  assessor: z.string().min(1, "Assessor is required."),
  assessmentDate: z.string().min(1, "Assessment date is required."),
  impact: z.number().min(1).max(5),
  likelihood: z.number().min(1).max(5),
  remarks: z.string().max(500),
});

export type AssessmentMatrixSchema = z.infer<typeof assessmentMatrixSchema>;
