import { z } from "zod";

export const conferenceParticipantSchema = z.object({
  id: z.string(),
  name: z.string().min(2, "Name is required"),
  office: z.string().min(2, "Office is required"),
  position: z.string().min(2, "Position is required"),
  role: z.string().min(2, "Role is required"),
  attendanceStatus: z.enum(["Confirmed", "Attended", "Excused", "Absent"]),
  mode: z.enum(["In-person", "Virtual", "Hybrid"]),
});

export const infoRequestSchema = z.object({
  id: z.string(),
  documentTitle: z.string().min(2, "Document title required"),
  targetOffice: z.string().min(2, "Target office required"),
  focalPerson: z.string().min(2, "Focal person required"),
  deadline: z.string().min(1, "Deadline required"),
  status: z.enum(["Pending", "Received", "Partial"]),
});

export const followUpActionSchema = z.object({
  id: z.string(),
  actionItem: z.string().min(2, "Action item required"),
  responsiblePerson: z.string().min(2, "Responsible person required"),
  targetDate: z.string().min(1, "Target date required"),
});

export const conferenceSchema = z
  .object({
    conferenceId: z.string(),
    engagementNumber: z.string(),
    type: z.enum(["Entry Conference", "Exit Conference"]),
    status: z.enum(["Scheduled", "Conducted", "Documented", "Waived"]),

    scheduledDate: z.string(),
    scheduledTime: z.string(),
    venueOrLink: z.string(),
    mode: z.enum(["In-person", "Virtual", "Hybrid"]),

    agendaText: z.string(),
    minutesAndNotes: z.string(),
    keyAgreements: z.string(),

    participants: z.array(conferenceParticipantSchema),
    informationRequests: z.array(infoRequestSchema),
    followUpActions: z.array(followUpActionSchema),

    waiver: z.object({
      isWaiverActive: z.boolean(),
      waiverReason: z.string(),
      alternativeProcedure: z.string(),
      approvingAuthority: z.string(),
      approvalDate: z.string(),
      isApproved: z.boolean(),
    }),
  })
  .superRefine((data, ctx) => {
    if (data.waiver.isWaiverActive) {
      if (!data.waiver.waiverReason || data.waiver.waiverReason.trim().length < 10) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Waiver justification reason is mandatory (minimum 10 characters)",
          path: ["waiver", "waiverReason"],
        });
      }
      if (!data.waiver.alternativeProcedure || data.waiver.alternativeProcedure.trim().length < 10) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Alternative commencement procedure is mandatory when waiving conference",
          path: ["waiver", "alternativeProcedure"],
        });
      }
      if (!data.waiver.approvingAuthority || data.waiver.approvingAuthority.trim().length < 2) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Approving authority is mandatory for authorized waiver",
          path: ["waiver", "approvingAuthority"],
        });
      }
    } else {
      if (!data.scheduledDate || data.scheduledDate.trim().length === 0) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Conference scheduled date is required",
          path: ["scheduledDate"],
        });
      }
      if (!data.venueOrLink || data.venueOrLink.trim().length < 3) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Meeting venue or virtual link is required",
          path: ["venueOrLink"],
        });
      }
      if (!data.agendaText || data.agendaText.trim().length < 10) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Conference agenda topics must be documented",
          path: ["agendaText"],
        });
      }
      if (data.participants.length < 2) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "At least two conference participants must be listed",
          path: ["participants"],
        });
      }
    }
  });

export type ConferenceValidationResult = {
  isValid: boolean;
  errors: Record<string, string>;
};
