export type FieldworkRecordType =
  | "Interview / Inquiry"
  | "Physical Inspection"
  | "Process Walkthrough"
  | "Observation / Spot Count"
  | "Coordination Meeting"
  | "Field Audit Notes";

// Provisional status per open documentation gap in MDS-200 §10
export type FieldworkRecordStatus = "Draft" | "Submitted" | "Reviewed";

export type FieldworkEvidenceLink = {
  id: string; // e.g. EVD-2026-001
  title: string;
  type: string;
  sourceUrlOrRef: string;
};

export type FieldworkRecord = {
  id: string; // e.g. FWR-2026-001
  engagementNumber: string;
  procedureId: string; // Mandatory 1-to-1 link per MDS-200 §7.1
  procedureTitle: string;
  auditArea: string;
  workingPaperRef: string; // Rule 13 linkage toward SCR-228

  type: FieldworkRecordType;
  date: string;
  location: string;
  personsInvolved: string; // Names, titles, organizations
  purpose: string;
  factsObtained: string; // Core factual findings / observed metrics
  notes: string; // Auditor impressions and potential risk flags

  evidenceLinks: FieldworkEvidenceLink[]; // Optional per Rule 15

  preparer: string; // e.g. Engr. Daniel L. Torres
  reviewer: string; // e.g. Atty. Fernando M. Gomez, CPA
  status: FieldworkRecordStatus;
  version: string;
  createdAt: string;
  updatedAt: string;
};

export type FieldworkPackageState = {
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  activeRecordId: string;
  records: FieldworkRecord[];
};
