export type WorkingPaperType =
  | "Planning"
  | "Sampling"
  | "Execution"
  | "Reporting";

export type WorkingPaperReviewStatus =
  | "Draft"
  | "Submitted"
  | "Reviewed"
  | "Cleared";

export type WorkingPaperEvidenceLink = {
  id: string; // e.g. EVD-2026-001
  title: string;
  type: string;
  sourceUrlOrRef: string;
};

export type WorkingPaperReviewNoteRef = {
  id: string; // e.g. RN-2026-001
  note: string;
  reviewer: string;
  response?: string;
  resolution?: string;
  clearedBy?: string;
  clearanceDate?: string;
  status: "Open" | "In Discussion" | "Cleared";
};

export type WorkingPaperClearance = {
  clearedBy: string;
  clearanceDate: string;
  clearanceRemarks?: string;
};

export type WorkingPaper = {
  id: string; // e.g. WP-2026-003-WH-01
  referenceNumber: string; // e.g. AEMS-2026-WP-003
  engagementNumber: string;

  // MDS-200 §7 Entity Fields
  type: WorkingPaperType;
  plannedReference: string; // Link to AEP / Program (SCR-222/223)
  procedureId: string; // Link to Procedure (SCR-224)
  procedureTitle: string;
  riskMatrixItemId: string; // Link to Risk Matrix Item (SCR-221)
  riskMatrixTitle: string;
  auditArea: string; // Carried from SCR-212 Focus Area

  purpose: string;
  workPerformed: string; // Substantive methodology & tests executed (Rule 13)

  // Source Fieldwork Record Cross-Reference (Rule 13 Linkage)
  sourceFieldworkRecordId?: string; // e.g. FWR-2026-001
  sourceFieldworkType?: string;

  evidenceLinks: WorkingPaperEvidenceLink[]; // Structured evidence list (Rule 15)

  results: string; // Detailed findings, metrics, reconciliations
  conclusion: string; // Formal auditor evaluative conclusion

  // Controlled Signatories (MDS-200 §16, UID-200 §7.3)
  preparer: string;
  reviewer: string;

  // Review Notes & Clearance Tracking (SCR-226 Cross-Reference)
  reviewNotes: WorkingPaperReviewNoteRef[];
  clearance?: WorkingPaperClearance;

  reviewStatus: WorkingPaperReviewStatus;
  version: string;
  createdAt: string;
  updatedAt: string;
};

export type WorkingPaperPackageState = {
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  activeWorkingPaperId: string;
  workingPapers: WorkingPaper[];
};
