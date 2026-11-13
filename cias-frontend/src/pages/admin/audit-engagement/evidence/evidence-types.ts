export type EvidenceStatus =
  | "Registered"
  | "For Assessment"
  | "Accepted"
  | "Accepted with Limitation"
  | "Additional Evidence Required"
  | "Rejected"
  | "Superseded"
  | "Duplicate";

export type EvidenceNature =
  | "Scanned Document / Voucher"
  | "Digital Photo / Image"
  | "Electronic File / Extract"
  | "Physical Sample / Artifact"
  | "Interview Transcript / Record"
  | "Third-Party Confirmation";

export type AuditProcedureMethod =
  | "Inspection"
  | "Observation"
  | "Inquiry"
  | "Confirmation"
  | "Recalculation"
  | "Reperformance"
  | "Analytical Procedure";

export type SourceOrigin = "Internal" | "External";

export type AcquisitionMode =
  | "Direct (Auditor-Observed/Gathered)"
  | "Indirect (Provided by Auditee/Third-Party)";

export type PhysicalForm =
  | "Original"
  | "Certified True Copy"
  | "Scanned Copy"
  | "Native Electronic Record";

export type ConfidentialityLevel =
  | "Unclassified / Public"
  | "Internal Audit Confidential"
  | "Restricted / Statutory Protected";

export type AccessRestriction =
  | "Audit Team Only"
  | "Engagement Team & Reviewers"
  | "Publicly Disclosable";

export type ArchivalCondition =
  | "Active Repository"
  | "Cold Storage"
  | "Permanent Audit Archive";

export type EvidenceCustodyEvent = {
  id: string;
  date: string;
  action: string;
  person: string;
  role: string;
  notes: string;
};

export type RelatedArtifactLink = {
  id: string;
  type: "Procedure" | "Working Paper" | "Fieldwork Record" | "Issue / AFR" | "Audit Report";
  ref: string;
  title: string;
};

export type AuditEvidenceItem = {
  id: string; // e.g. EVD-2026-001
  title: string;
  engagementNumber: string;
  relatedRequestId?: string; // MDS-200 §7 Related Request ID

  // MDS-200 §7 Entity Core Fields
  evidenceType: EvidenceNature;
  auditProcedureMethod: AuditProcedureMethod;
  source: string;
  sourceOrigin: SourceOrigin;
  acquisitionMode: AcquisitionMode;
  physicalForm: PhysicalForm;
  custodian: string;
  dateReceived: string;
  fileReference: string; // File path or URI
  fileSize?: string;
  fileMimeType?: string;

  // Hash & Integrity (UID-200 §8.3)
  hashAlgorithm: string; // e.g. SHA-256
  hashChecksum: string; // Cryptographic digest

  // Qualitative & Evaluation Attributes (MDS-200 §7)
  relatedCriteria: string; // Objective / Criterion / Control / Risk
  sufficiencyContribution: string;
  appropriateness: "High" | "Moderate" | "Low";
  relevance: string;
  reliability: string;
  accuracyCompletenessProcedures: string;
  corroboration: string;
  contradictoryEvidence: string;
  authenticity: string;

  // Security, Governance & Condition Attributes (MDS-200 §10.6 distinct from status)
  confidentiality: ConfidentialityLevel;
  limitation: string;
  accessRestriction: AccessRestriction;
  legalHold: boolean;
  archivalCondition: ArchivalCondition;
  assessmentResult: string;

  // Multi-link downstream artifacts (Procedure, WP, FWR, AFR)
  relatedArtifacts: RelatedArtifactLink[];

  // Chain of Custody Log
  custodyLog: EvidenceCustodyEvent[];

  // Status per MDS-200 §10.6
  status: EvidenceStatus;
  version: string;
  createdAt: string;
  updatedAt: string;
};

export type EvidencePackageState = {
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  activeEvidenceId: string;
  evidenceItems: AuditEvidenceItem[];
};
