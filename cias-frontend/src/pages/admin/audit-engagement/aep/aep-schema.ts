import { z } from "zod";

export const aepMilestoneSchema = z.object({
  id: z.string(),
  phaseTitle: z.string().min(2, "Phase title is required"),
  targetStartDate: z.string().min(1, "Start date required"),
  targetEndDate: z.string().min(1, "End date required"),
  responsibleLead: z.string().min(2, "Responsible lead required"),
  deliverable: z.string().min(2, "Deliverable required"),
});

export const aepKpiSchema = z.object({
  id: z.string(),
  metricName: z.string().min(2, "Metric name is required"),
  targetValue: z.string().min(1, "Target value is required"),
  measurementMethod: z.string().min(2, "Measurement method is required"),
  responsibleRole: z.string().min(2, "Responsible role is required"),
});

export const aepSchema = z
  .object({
    aepId: z.string(),
    engagementNumber: z.string(),
    version: z.string(),
    effectiveDate: z.string().min(1, "Effective date is required"),
    status: z.enum(["Draft", "Under Review", "Approved", "Amended"]),
    isAmendment: z.boolean(),
    amendmentReason: z.string().optional(),

    objectives: z.string().min(10, "Audit objectives must be documented"),
    refinedScope: z.string().min(10, "Refined audit scope must be documented"),
    criteriaFramework: z.array(z.string()).min(1, "At least one criteria standard must be selected"),

    riskMatrixSummaryRef: z.string().min(3, "Risk matrix reference required"),
    methodology: z.string().min(20, "Detailed audit methodology is required"),
    evidenceRequirements: z.array(z.string()).min(1, "At least one evidence category is required"),

    assignedTeamRef: z.string().min(3, "Assigned team reference required"),
    requiredAuditTools: z.string().min(3, "Audit tools must be noted"),

    milestones: z.array(aepMilestoneSchema).min(2, "At least two milestone phases are required"),
    kpis: z.array(aepKpiSchema).min(2, "At least two engagement KPIs are required"),

    communicationToManagement: z.string().min(10, "Communication protocol with management must be documented"),
    reviewingSupervisor: z.string().min(2, "Reviewing supervisor is required"),
    approvingAuthority: z.string().min(2, "Approving authority is required"),
  })
  .refine(
    (data) => {
      if (data.isAmendment && (!data.amendmentReason || data.amendmentReason.trim().length < 5)) {
        return false;
      }
      return true;
    },
    {
      message: "Amendment justification is mandatory when creating an AEP amendment",
      path: ["amendmentReason"],
    },
  );

export type AepValidationResult = {
  isValid: boolean;
  errors: Record<string, string>;
};
