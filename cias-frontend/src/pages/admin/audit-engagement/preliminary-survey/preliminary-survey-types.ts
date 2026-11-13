export type PreliminaryMeetingFormState = {
  proposedDate: string;
  venueOnlineLink: string;
  auditeeContactPerson: string;
  ciasParticipants: string;
  auditeeParticipants: string;
  keyMattersToDiscuss: string;
  initialDocumentsNeeded: string;
  actualMeetingDate: string;
  keyMattersDiscussed: string;
  noticeFileName?: string;
  noticeDocumentVersionId?: number | null;
  noticeFileUploadedAt?: string;
};

export const defaultPreliminaryMeetingState: PreliminaryMeetingFormState = {
  proposedDate: "",
  venueOnlineLink: "",
  auditeeContactPerson: "",
  ciasParticipants: "",
  auditeeParticipants: "",
  keyMattersToDiscuss: "",
  initialDocumentsNeeded: "",
  actualMeetingDate: "",
  keyMattersDiscussed: "",
  noticeFileName: "",
};

