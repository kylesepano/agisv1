import { z } from "zod";
import { localStore } from "../../../../lib/localStore";

export const defineScopeSchema = z
  .object({
    engagementNumber: z.string().min(1, "Engagement Number is required."),
    originType: z.enum(["Planned", "Unplanned"]).optional().default("Planned"),
    office: z.string().min(1, "Please select an auditee Office from the registry."),
    isOfficeChangedFromIap: z.boolean().default(false),
    sourceVarianceReason: z.string().optional().default(""),
    selectedAuditAreaIds: z
      .array(z.string())
      .min(1, "Please select at least one Audit Area for this engagement."),
    coverageNotes: z.record(z.string(), z.string()).optional().default({}),
    selectedAuditFocusIds: z.array(z.string()).optional().default([]),
    objectives: z
      .string()
      .trim()
      .min(10, "Please provide engagement-level audit objectives (minimum 10 characters)."),
    boundaries: z.string().optional().default(""),
    exclusions: z.string().optional().default(""),
    knownLimitations: z.string().optional().default(""),
  })
  .refine(
    (data) => {
      if (data.originType === "Planned" && data.isOfficeChangedFromIap) {
        return (
          typeof data.sourceVarianceReason === "string" &&
          data.sourceVarianceReason.trim().length >= 10
        );
      }
      return true;
    },
    {
      message:
        "Authorized Source Variance Reason is required when changing the auditee Office from the approved IAP plan (minimum 10 characters).",
      path: ["sourceVarianceReason"],
    },
  )
  .refine(
    (data) => {
      if (!data.office || data.office.trim().length === 0) return true;
      if (!data.selectedAuditAreaIds || data.selectedAuditAreaIds.length === 0) return true;

      const allAreas = localStore.getAuditAreas();
      const officeTrimmed = data.office.trim().toLowerCase();
      const validAreas = allAreas.filter(
        (a) =>
          (a.officeName || "").trim().toLowerCase() === officeTrimmed ||
          (a.officeId || "").trim().toLowerCase() === officeTrimmed,
      );

      const validKeys = new Set(
        validAreas.flatMap((a) => [
          (a.auditAreaId || "").toLowerCase(),
          (a.code || "").toLowerCase(),
          (a.id || "").toLowerCase(),
        ].filter(Boolean)),
      );

      return data.selectedAuditAreaIds.every((id) =>
        validKeys.has(id.trim().toLowerCase()),
      );
    },
    {
      message:
        "Every selected Audit Area must belong to the chosen Target Auditee Office (MDS-000 Rule 7).",
      path: ["selectedAuditAreaIds"],
    },
  );

export type DefineScopeSchemaType = z.infer<typeof defineScopeSchema>;
