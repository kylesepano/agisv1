import { z } from "zod";

export const createEngagementSchema = z
  .object({
    engagementNumber: z.string().min(1, "Engagement Number is required."),
    originType: z.enum(["Planned", "Unplanned"]),
    iapSourceId: z.string().optional(),
    title: z.string().trim().min(3, "Title must be at least 3 characters long."),
    auditType: z.string().min(1, "Please select an Audit Type."),
    authorityReference: z.string().optional().default(""),
    directingAuthority: z.string().optional().default(""),
    dateReceived: z.string().min(1, "Date Received is required."),
    // MDS-200 §7 Authorization Decision (optional / Unplanned specific)
    authorizationDecision: z.string().optional().default(""),
    startDate: z.string().min(1, "Start Date is required."),
    endDate: z.string().min(1, "End Date is required."),
    priority: z.enum(["Critical", "High", "Medium", "Low"]),
    confidentiality: z.enum(["Normal", "Restricted", "Confidential"]),
    phase: z.literal("Engagement Initiation"),
    status: z.literal("Draft"),
  })
  .refine(
    (data) => {
      if (data.originType === "Unplanned") {
        return Boolean(data.authorityReference && data.authorityReference.trim().length > 0);
      }
      return true;
    },
    {
      message: "Authority Reference is required for unplanned engagements.",
      path: ["authorityReference"],
    },
  )
  .refine(
    (data) => {
      if (data.originType === "Unplanned") {
        return Boolean(data.directingAuthority && data.directingAuthority.trim().length > 0);
      }
      return true;
    },
    {
      message: "Directing Authority is required for unplanned engagements.",
      path: ["directingAuthority"],
    },
  )
  .refine(
    (data) => {
      if (data.originType === "Unplanned") {
        return Boolean(data.authorizationDecision && data.authorizationDecision.trim().length > 0);
      }
      return true;
    },
    {
      message: "Authorization Decision is required for unplanned engagements.",
      path: ["authorizationDecision"],
    },
  )
  .refine(
    (data) => {
      if (data.originType === "Planned") {
        return Boolean(data.iapSourceId && data.iapSourceId.trim().length > 0);
      }
      return true;
    },
    {
      message: "Please select an approved IAP source item for planned engagements.",
      path: ["iapSourceId"],
    },
  )
  .refine(
    (data) => {
      if (data.startDate && data.endDate) {
        return new Date(data.startDate) <= new Date(data.endDate);
      }
      return true;
    },
    {
      message: "End Date must be equal to or after Start Date.",
      path: ["endDate"],
    },
  );

export type CreateEngagementSchemaType = z.infer<typeof createEngagementSchema>;
