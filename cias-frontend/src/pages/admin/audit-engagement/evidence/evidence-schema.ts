import { z } from "zod";

export const evidenceCustodyEventSchema = z.object({
  id: z.string(),
  date: z.string(),
  action: z.string(),
  person: z.string(),
  role: z.string(),
  notes: z.string(),
});

export const relatedArtifactLinkSchema = z.object({
  id: z.string(),
  type: z.enum([
    "Procedure",
    "Working Paper",
    "Fieldwork Record",
    "Issue / AFR",
    "Audit Report",
  ]),
  ref: z.string(),
  title: z.string(),
});

export const auditEvidenceSchema = z.object({
  id: z.string().min(1, "Evidence ID is required"),
  title: z.string().min(3, "Title must be at least 3 characters"),
  engagementNumber: z.string().min(1, "Engagement Number is required"),
  relatedRequestId: z.string().optional(),

  evidenceType: z.enum([
    "Scanned Document / Voucher",
    "Digital Photo / Image",
    "Electronic File / Extract",
    "Physical Sample / Artifact",
    "Interview Transcript / Record",
    "Third-Party Confirmation",
  ]),
  auditProcedureMethod: z.enum([
    "Inspection",
    "Observation",
    "Inquiry",
    "Confirmation",
    "Recalculation",
    "Reperformance",
    "Analytical Procedure",
  ]),
  source: z.string().min(2, "Source entity/person is required"),
  sourceOrigin: z.enum(["Internal", "External"]),
  acquisitionMode: z.enum([
    "Direct (Auditor-Observed/Gathered)",
    "Indirect (Provided by Auditee/Third-Party)",
  ]),
  physicalForm: z.enum([
    "Original",
    "Certified True Copy",
    "Scanned Copy",
    "Native Electronic Record",
  ]),
  custodian: z.string().min(2, "Current custodian is required"),
  dateReceived: z.string().min(1, "Date received/obtained is required"),
  fileReference: z.string().min(2, "File / Reference path is required"),

  hashAlgorithm: z.string().min(1, "Hash algorithm required"),
  hashChecksum: z.string().min(8, "Cryptographic hash digest required"),

  relatedCriteria: z.string().min(5, "Related Objective/Criterion/Risk required"),
  sufficiencyContribution: z.string().min(5, "Sufficiency contribution required"),
  appropriateness: z.enum(["High", "Moderate", "Low"]),
  relevance: z.string().min(5, "Relevance evaluation required"),
  reliability: z.string().min(5, "Reliability evaluation required"),
  accuracyCompletenessProcedures: z.string().min(5, "Accuracy/Completeness procedures required"),
  corroboration: z.string().min(3, "Corroboration notes required"),
  contradictoryEvidence: z.string(),
  authenticity: z.string().min(5, "Authenticity verification required"),

  confidentiality: z.enum([
    "Unclassified / Public",
    "Internal Audit Confidential",
    "Restricted / Statutory Protected",
  ]),
  limitation: z.string(),
  accessRestriction: z.enum([
    "Audit Team Only",
    "Engagement Team & Reviewers",
    "Publicly Disclosable",
  ]),
  legalHold: z.boolean(),
  archivalCondition: z.enum([
    "Active Repository",
    "Cold Storage",
    "Permanent Audit Archive",
  ]),
  assessmentResult: z.string().min(5, "Assessment result is required"),

  relatedArtifacts: z.array(relatedArtifactLinkSchema),
  custodyLog: z.array(evidenceCustodyEventSchema),

  status: z.enum([
    "Registered",
    "For Assessment",
    "Accepted",
    "Accepted with Limitation",
    "Additional Evidence Required",
    "Rejected",
    "Superseded",
    "Duplicate",
  ]),
  version: z.string(),
});

export type AuditEvidenceValidationResult = {
  isValid: boolean;
  errors: Record<string, string>;
};
