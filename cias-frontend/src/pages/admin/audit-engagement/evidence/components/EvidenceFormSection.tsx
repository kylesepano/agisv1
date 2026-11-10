import {
  Binary,
  Copy,
  FileText,
  Fingerprint,
  Lock,
  Scale,
} from "lucide-react";
import { useState } from "react";
import type {
  AccessRestriction,
  ArchivalCondition,
  AuditEvidenceItem,
  AuditProcedureMethod,
  ConfidentialityLevel,
  EvidenceNature,
  EvidenceStatus,
  PhysicalForm,
} from "../evidence-types";

type EvidenceFormSectionProps = {
  evidence: AuditEvidenceItem;
  onChangeField: (field: keyof AuditEvidenceItem, value: any) => void;
};

export function EvidenceFormSection({
  evidence,
  onChangeField,
}: EvidenceFormSectionProps) {
  const [copiedHash, setCopiedHash] = useState(false);

  const evidenceNatures: EvidenceNature[] = [
    "Scanned Document / Voucher",
    "Digital Photo / Image",
    "Electronic File / Extract",
    "Physical Sample / Artifact",
    "Interview Transcript / Record",
    "Third-Party Confirmation",
  ];

  const procedureMethods: AuditProcedureMethod[] = [
    "Inspection",
    "Observation",
    "Inquiry",
    "Confirmation",
    "Recalculation",
    "Reperformance",
    "Analytical Procedure",
  ];

  const physicalForms: PhysicalForm[] = [
    "Original",
    "Certified True Copy",
    "Scanned Copy",
    "Native Electronic Record",
  ];

  const confidentialityLevels: ConfidentialityLevel[] = [
    "Unclassified / Public",
    "Internal Audit Confidential",
    "Restricted / Statutory Protected",
  ];

  const accessRestrictions: AccessRestriction[] = [
    "Audit Team Only",
    "Engagement Team & Reviewers",
    "Publicly Disclosable",
  ];

  const archivalConditions: ArchivalCondition[] = [
    "Active Repository",
    "Cold Storage",
    "Permanent Audit Archive",
  ];

  const statusOptions: EvidenceStatus[] = [
    "Registered",
    "For Assessment",
    "Accepted",
    "Accepted with Limitation",
    "Additional Evidence Required",
    "Rejected",
    "Superseded",
    "Duplicate",
  ];

  const handleCopyHash = () => {
    navigator.clipboard.writeText(evidence.hashChecksum);
    setCopiedHash(true);
    setTimeout(() => setCopiedHash(false), 2000);
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-4">
      {/* Section Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Fingerprint className="h-4 w-4 text-emerald-700" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Audit Evidence Specification & Assessment (§7 & §10.6 Fields)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          MDS-200 §7 Entity Core · MDS-200 §10.6 Status
        </span>
      </div>

      <div className="space-y-4 text-xs">
        {/* 1. Core Source & Acquisition Metadata */}
        <div className="space-y-2.5">
          <div className="flex items-center gap-1 text-[11px] font-bold text-slate-700 uppercase tracking-wider">
            <FileText size={12} className="text-emerald-700" />
            <span>1. Core Evidence Acquisition & Source Attributes</span>
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Evidence Title / Description
              </label>
              <input
                type="text"
                value={evidence.title}
                onChange={(e) => onChangeField("title", e.target.value)}
                placeholder="Formal title of evidence item..."
                className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Evidence Type / Nature (MDS §7)
              </label>
              <select
                value={evidence.evidenceType}
                onChange={(e) =>
                  onChangeField("evidenceType", e.target.value as EvidenceNature)
                }
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              >
                {evidenceNatures.map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Audit Procedure Method (MDS §7)
              </label>
              <select
                value={evidence.auditProcedureMethod}
                onChange={(e) =>
                  onChangeField(
                    "auditProcedureMethod",
                    e.target.value as AuditProcedureMethod,
                  )
                }
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              >
                {procedureMethods.map((m) => (
                  <option key={m} value={m}>
                    {m}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-4">
            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Source Entity / Person
              </label>
              <input
                type="text"
                value={evidence.source}
                onChange={(e) => onChangeField("source", e.target.value)}
                placeholder="Source origin..."
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Source Origin
              </label>
              <select
                value={evidence.sourceOrigin}
                onChange={(e) => onChangeField("sourceOrigin", e.target.value)}
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              >
                <option value="Internal">Internal (City Office / Auditee)</option>
                <option value="External">External (Third-Party / Portal)</option>
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Acquisition Mode
              </label>
              <select
                value={evidence.acquisitionMode}
                onChange={(e) => onChangeField("acquisitionMode", e.target.value)}
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              >
                <option value="Direct (Auditor-Observed/Gathered)">
                  Direct (Auditor-Observed)
                </option>
                <option value="Indirect (Provided by Auditee/Third-Party)">
                  Indirect (Provided by Auditee)
                </option>
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Physical Form
              </label>
              <select
                value={evidence.physicalForm}
                onChange={(e) =>
                  onChangeField("physicalForm", e.target.value as PhysicalForm)
                }
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              >
                {physicalForms.map((f) => (
                  <option key={f} value={f}>
                    {f}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Related Request ID
              </label>
              <input
                type="text"
                value={evidence.relatedRequestId || ""}
                onChange={(e) => onChangeField("relatedRequestId", e.target.value)}
                placeholder="e.g. EVR-2026-001 or N/A..."
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 font-mono text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              />
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Current Custodian
              </label>
              <input
                type="text"
                value={evidence.custodian}
                onChange={(e) => onChangeField("custodian", e.target.value)}
                placeholder="Custodian name & role..."
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Date Received / Obtained
              </label>
              <input
                type="date"
                value={evidence.dateReceived}
                onChange={(e) => onChangeField("dateReceived", e.target.value)}
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 font-mono text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>
          </div>
        </div>

        {/* 2. File Reference & Cryptographic Hash Integrity (UID-200 §8.3) */}
        <div className="space-y-2 pt-2 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1">
              <Binary size={12} className="text-emerald-700" />
              <span>2. File URI & Cryptographic Hash Seal (UID-200 §8.3 Hash Integrity)</span>
            </span>
            <span className="text-[10px] font-mono text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
              Algorithm: {evidence.hashAlgorithm}
            </span>
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-12">
            <div className="sm:col-span-6">
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                File / URI Reference (Repository Path)
              </label>
              <input
                type="text"
                value={evidence.fileReference}
                onChange={(e) => onChangeField("fileReference", e.target.value)}
                placeholder="e.g. WP-2026-003-WH-01/EVD-01.pdf"
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 font-mono text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>

            <div className="sm:col-span-6">
              <label className="text-[11px] font-semibold text-slate-700 block mb-1 flex items-center justify-between">
                <span>Cryptographic SHA-256 Hash Digest</span>
                <button
                  type="button"
                  onClick={handleCopyHash}
                  className="text-[10px] text-emerald-700 hover:underline flex items-center gap-0.5 cursor-pointer font-mono"
                >
                  <Copy size={10} />
                  <span>{copiedHash ? "Copied!" : "Copy"}</span>
                </button>
              </label>
              <input
                type="text"
                value={evidence.hashChecksum}
                onChange={(e) => onChangeField("hashChecksum", e.target.value)}
                placeholder="64-character hex hash digest..."
                className="h-8 w-full rounded border border-slate-200 bg-slate-50 px-2 font-mono text-[10px] text-emerald-900 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>
          </div>
        </div>

        {/* 3. Qualitative Assessment Attributes (MDS-200 §7) */}
        <div className="space-y-2.5 pt-2 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1">
              <Scale size={12} className="text-emerald-700" />
              <span>3. Qualitative Evidence Assessment Attributes (MDS-200 §7)</span>
            </span>
            <span className="text-[10px] text-slate-400">
              Relevance, Reliability & Sufficiency Evaluation
            </span>
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Related Objective / Criterion / Control / Risk (MDS §7)
            </label>
            <input
              type="text"
              value={evidence.relatedCriteria}
              onChange={(e) => onChangeField("relatedCriteria", e.target.value)}
              placeholder="e.g. Objective 3: Warehouse Custody Controls; RA 7160 Sec. 375; Risk RMI-2026-003..."
              className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              required
            />
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <label className="text-[11px] font-semibold text-slate-700 block">
                  Relevance (Testing Nexus)
                </label>
                <span className="text-[10px] text-slate-400">Logical connection to test</span>
              </div>
              <textarea
                rows={2}
                value={evidence.relevance}
                onChange={(e) => onChangeField("relevance", e.target.value)}
                placeholder="Explain the direct relevance of this evidence to audit objectives..."
                className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <label className="text-[11px] font-semibold text-slate-700 block">
                  Reliability / Competence
                </label>
                <span className="text-[10px] text-slate-400">Source credibility & independence</span>
              </div>
              <textarea
                rows={2}
                value={evidence.reliability}
                onChange={(e) => onChangeField("reliability", e.target.value)}
                placeholder="Evaluate source credibility, independent verification, and countersignatures..."
                className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Sufficiency Contribution
              </label>
              <textarea
                rows={2}
                value={evidence.sufficiencyContribution}
                onChange={(e) =>
                  onChangeField("sufficiencyContribution", e.target.value)
                }
                placeholder="Quantum and sample coverage..."
                className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>

            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Corroboration
              </label>
              <textarea
                rows={2}
                value={evidence.corroboration}
                onChange={(e) => onChangeField("corroboration", e.target.value)}
                placeholder="Corroborating records or photos..."
                className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>

            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Contradictory Evidence (if any)
              </label>
              <textarea
                rows={2}
                value={evidence.contradictoryEvidence}
                onChange={(e) =>
                  onChangeField("contradictoryEvidence", e.target.value)
                }
                placeholder="Document any conflicting observations or variance..."
                className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-hidden"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Accuracy and Completeness Procedures
              </label>
              <input
                type="text"
                value={evidence.accuracyCompletenessProcedures}
                onChange={(e) =>
                  onChangeField("accuracyCompletenessProcedures", e.target.value)
                }
                placeholder="e.g. Recount verification, footing, cross-referencing ledger..."
                className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>

            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Authenticity Verification
              </label>
              <input
                type="text"
                value={evidence.authenticity}
                onChange={(e) => onChangeField("authenticity", e.target.value)}
                placeholder="e.g. Verified original wet-ink signatures and seal stamps..."
                className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
                required
              />
            </div>
          </div>
        </div>

        {/* 4. Security, Limitation & Status Model (MDS-200 §10.6) */}
        <div className="space-y-2.5 pt-2 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1">
              <Lock size={12} className="text-emerald-700" />
              <span>4. Governance, Limitations & Status Model (MDS-200 §10.6)</span>
            </span>
            <span className="text-[10px] text-slate-400">
              §10.6 Codified Status & Conditions
            </span>
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-4">
            <div>
              <label className="text-[11px] font-bold text-slate-800 block mb-1">
                Evidence Status (MDS §10.6)
              </label>
              <select
                value={evidence.status}
                onChange={(e) =>
                  onChangeField("status", e.target.value as EvidenceStatus)
                }
                className="h-8 w-full rounded border border-emerald-300 bg-emerald-50/50 px-2 text-xs font-bold text-emerald-950 focus:border-emerald-600 focus:outline-hidden"
              >
                {statusOptions.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Confidentiality Level
              </label>
              <select
                value={evidence.confidentiality}
                onChange={(e) =>
                  onChangeField(
                    "confidentiality",
                    e.target.value as ConfidentialityLevel,
                  )
                }
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              >
                {confidentialityLevels.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Access Restriction
              </label>
              <select
                value={evidence.accessRestriction}
                onChange={(e) =>
                  onChangeField(
                    "accessRestriction",
                    e.target.value as AccessRestriction,
                  )
                }
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              >
                {accessRestrictions.map((a) => (
                  <option key={a} value={a}>
                    {a}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-700 block mb-1">
                Archival Condition
              </label>
              <select
                value={evidence.archivalCondition}
                onChange={(e) =>
                  onChangeField(
                    "archivalCondition",
                    e.target.value as ArchivalCondition,
                  )
                }
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-hidden"
              >
                {archivalConditions.map((ac) => (
                  <option key={ac} value={ac}>
                    {ac}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="space-y-1">
            <div className="flex items-center justify-between">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Limitation / Conditional Usage Notes
              </label>
              <span className="text-[10px] text-amber-700">
                Documented limitation if &quot;Accepted with Limitation&quot;
              </span>
            </div>
            <textarea
              rows={2}
              value={evidence.limitation}
              onChange={(e) => onChangeField("limitation", e.target.value)}
              placeholder="State any specific scope restrictions, timing delays, or limitations affecting the use of this evidence..."
              className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-hidden"
            />
          </div>

          <div className="space-y-1">
            <div className="flex items-center justify-between">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Assessment Result / Auditor Disposition
              </label>
              <span className="text-[10px] text-slate-400">Formal disposition conclusion</span>
            </div>
            <textarea
              rows={2}
              value={evidence.assessmentResult}
              onChange={(e) => onChangeField("assessmentResult", e.target.value)}
              placeholder="Formal audit disposition and Working Paper substantiation determination..."
              className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-hidden"
              required
            />
          </div>
        </div>
      </div>
    </div>
  );
}
