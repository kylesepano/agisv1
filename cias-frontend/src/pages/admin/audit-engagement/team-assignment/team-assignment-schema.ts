import { z } from "zod";

export const assignedMemberSchema = z
  .object({
    id: z.string(),
    userId: z.string().min(1, "Please select an auditor from the registry"),
    name: z.string().min(1, "Auditor name is required"),
    role: z.enum(["Lead Auditor", "Auditor", "Technical Specialist"]),
    assignmentStartDate: z.string().min(1, "Assignment start date is required"),
    assignmentEndDate: z.string().min(1, "Assignment end date is required"),
    competencySummary: z
      .string()
      .min(20, "Documented competency must be at least 20 characters"),
    isAvailable: z.boolean().refine((val) => val === true, {
      message: "Availability must be confirmed (zero workload overload)",
    }),
    workloadNote: z.string().optional(),
    hasScheduleConflict: z.boolean(),
    scheduleConflictNote: z.string().optional(),
    supervisorName: z.string().min(1, "Supervisor designation is required"),
    hasConflictOfInterest: z.boolean(),
    disclosedImpairments: z.string().optional(),
    safeguardsDocumented: z
      .string()
      .min(5, "Documented safeguards or independence affirmation required"),
    hasSystemAccessConfirmed: z.boolean().refine((val) => val === true, {
      message: "System & document access must be confirmed",
    }),
  })
  .refine(
    (data) => {
      if (
        data.hasConflictOfInterest &&
        (!data.disclosedImpairments ||
          data.disclosedImpairments.trim().length < 10)
      ) {
        return false;
      }
      return true;
    },
    {
      message:
        "Disclosed impairment description (min 10 chars) is mandatory when Conflict of Interest is declared",
      path: ["disclosedImpairments"],
    },
  )
  .refine(
    (data) => {
      if (
        data.hasConflictOfInterest &&
        (!data.safeguardsDocumented ||
          data.safeguardsDocumented.trim().length < 10)
      ) {
        return false;
      }
      return true;
    },
    {
      message:
        "Documented safeguards (min 10 chars) are mandatory when an impairment is declared",
      path: ["safeguardsDocumented"],
    },
  )
  .refine(
    (data) => {
      if (data.assignmentStartDate && data.assignmentEndDate) {
        return data.assignmentStartDate <= data.assignmentEndDate;
      }
      return true;
    },
    {
      message: "Assignment end date cannot be earlier than start date",
      path: ["assignmentEndDate"],
    },
  );

export const teamAssignmentSchema = z
  .object({
    engagementNumber: z.string(),
    periodStart: z.string().optional(),
    periodEnd: z.string().optional(),
    team: z
      .array(assignedMemberSchema)
      .min(1, "At least one team member must be assigned to the roster"),
    isCollectiveSufficiencyConfirmed: z.boolean().refine((val) => val === true, {
      message: "Formal Collective Team Sufficiency Affirmation is required",
    }),
    collectiveSufficiencyJustification: z
      .string()
      .min(20, "Collective sufficiency justification must be at least 20 characters"),
  })
  .refine(
    (data) => {
      const leadAuditors = data.team.filter((m) => m.role === "Lead Auditor");
      return leadAuditors.length === 1;
    },
    {
      message: "Exactly one Lead Auditor must be designated in the team roster",
      path: ["teamLead"],
    },
  )
  .refine(
    (data) => {
      if (!data.periodStart || !data.periodEnd) return true;
      return data.team.every(
        (m) =>
          !m.assignmentStartDate ||
          !m.assignmentEndDate ||
          (m.assignmentStartDate >= data.periodStart! &&
            m.assignmentEndDate <= data.periodEnd!),
      );
    },
    {
      message:
        "All member assignment dates must fall within the engagement Period Covered window",
      path: ["teamDateRange"],
    },
  );

export type TeamAssignmentValidationResult = {
  isValid: boolean;
  errors: Record<string, string>;
};
