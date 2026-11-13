export type ConferenceType = "Entry Conference" | "Exit Conference";

export type ConferenceMode = "In-person" | "Virtual" | "Hybrid";

export type ConferenceStatus = "Scheduled" | "Conducted" | "Documented" | "Waived";

export type AttendanceStatus = "Confirmed" | "Attended" | "Excused" | "Absent";

export type ConferenceParticipant = {
  id: string;
  name: string;
  office: string;
  position: string;
  role: string;
  attendanceStatus: AttendanceStatus;
  mode: ConferenceMode;
};

export type InfoRequestItem = {
  id: string;
  documentTitle: string;
  targetOffice: string;
  focalPerson: string;
  deadline: string;
  status: "Pending" | "Received" | "Partial";
};

export type FollowUpActionItem = {
  id: string;
  actionItem: string;
  responsiblePerson: string;
  targetDate: string;
};

export type BriefingPaperSummary = {
  aeoRef: string;
  authorityLegalBasis: string;
  assignedTeamSummary: string;
  objectivesScopeSummary: string;
  fieldworkTimeline: string;
};

export type AuthorizedWaiverState = {
  isWaiverActive: boolean;
  waiverReason: string;
  alternativeProcedure: string;
  approvingAuthority: string; // OD-001 Provisional
  approvalDate: string;
  isApproved: boolean;
};

export type ConferenceFormState = {
  // Identity & Type
  conferenceId: string;
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  type: ConferenceType;
  status: ConferenceStatus;

  // Logistics
  scheduledDate: string;
  scheduledTime: string;
  venueOrLink: string;
  mode: ConferenceMode;

  // Briefing Reference (§7)
  briefingPaper: BriefingPaperSummary;

  // Agenda & Minutes (§7)
  agendaText: string;
  minutesAndNotes: string;
  keyAgreements: string;

  // Repeatable Records (§7)
  participants: ConferenceParticipant[];
  informationRequests: InfoRequestItem[];
  followUpActions: FollowUpActionItem[];

  // Waiver Path (§11, AEM-FR-018)
  waiver: AuthorizedWaiverState;
};
