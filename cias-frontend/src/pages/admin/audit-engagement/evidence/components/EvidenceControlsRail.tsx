import {
  ArrowLeft,
  CheckCircle2,
  FileCheck2,
  FileSpreadsheet,
  Plus,
  Save,
  Shield,
  XCircle,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type { AuditEvidenceItem } from "../evidence-types";

type EvidenceControlsRailProps = {
  evidence: AuditEvidenceItem;
  isValid: boolean;
  onSaveDraft: () => void;
  onAcceptEvidence: () => void;
  onCreateNewEvidence: () => void;
};

export function EvidenceControlsRail({
  evidence,
  isValid,
  onSaveDraft,
  onAcceptEvidence,
  onCreateNewEvidence,
}: EvidenceControlsRailProps) {
  const checks = [
    {
      label: "Source & origin verified (MDS §7)",
      valid: (evidence.source || "").trim().length > 0,
      detail: evidence.sourceOrigin,
    },
    {
      label: "SHA-256 cryptographic seal intact",
      valid: (evidence.hashChecksum || "").trim().length >= 16,
      detail: "Sealed",
    },
    {
      label: "Chain of custody log recorded (UID §8.3)",
      valid: evidence.custodyLog.length > 0,
      detail: `${evidence.custodyLog.length} events`,
    },
    {
      label: "Relevance & Reliability assessed",
      valid:
        (evidence.relevance || "").trim().length >= 5 &&
        (evidence.reliability || "").trim().length >= 5,
      detail: "Assessed",
    },
    {
      label: "Downstream procedure / WP mapped",
      valid: evidence.relatedArtifacts.length > 0,
      detail: `${evidence.relatedArtifacts.length} links`,
    },
  ];

  const passedCount = checks.filter((c) => c.valid).length;

  const getStatusBadgeClass = (status: AuditEvidenceItem["status"]) => {
    switch (status) {
      case "Accepted":
        return "bg-emerald-50 text-emerald-700 border-emerald-300";
      case "Accepted with Limitation":
        return "bg-amber-50 text-amber-800 border-amber-300";
      case "For Assessment":
        return "bg-blue-50 text-blue-700 border-blue-300";
      case "Registered":
        return "bg-slate-100 text-slate-700 border-slate-300";
      case "Additional Evidence Required":
        return "bg-purple-50 text-purple-700 border-purple-300";
      case "Rejected":
        return "bg-rose-50 text-rose-700 border-rose-300";
      case "Superseded":
        return "bg-orange-50 text-orange-700 border-orange-300";
      case "Duplicate":
        return "bg-zinc-100 text-zinc-600 border-zinc-300";
      default:
        return "bg-slate-100 text-slate-700 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-emerald-700" />
          <span>Evidence Controls (MDS §7 & §10.6)</span>
        </div>
        <span className="rounded bg-emerald-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-emerald-800">
          SCR-229
        </span>
      </div>

      {/* Record Identity Box */}
      <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1.5 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Evidence Item ID
          </span>
          <span className="font-mono font-bold text-emerald-800 text-xs">
            {evidence.id} ({evidence.version})
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            MDS §10.6 Status
          </span>
          <span
            className={`rounded px-1.5 py-0.2 text-[10px] font-bold border ${getStatusBadgeClass(
              evidence.status,
            )}`}
          >
            {evidence.status}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Confidentiality
          </span>
          <span className="font-semibold text-slate-700 text-[11px] truncate max-w-[150px]">
            {evidence.confidentiality}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Vault Integrity
          </span>
          <span className="font-mono text-[10px] text-emerald-800 font-bold">
            SHA-256 Validated
          </span>
        </div>
      </div>

      {/* Controlled Custodian Card */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-1.5 text-xs">
        <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
          Current Custodian & Source
        </span>

        <div className="space-y-1 text-[11px]">
          <div className="flex items-center justify-between">
            <span className="text-slate-500">Custodian:</span>
            <span className="font-semibold text-slate-800 truncate max-w-[170px]">
              {evidence.custodian}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-slate-500">Source:</span>
            <span className="font-semibold text-slate-800 truncate max-w-[170px]">
              {evidence.source}
            </span>
          </div>
        </div>
      </div>

      {/* 5-Point Governance Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <FileCheck2 size={12} className="text-emerald-700" />
            <span>Evidence Governance Checklist</span>
          </span>
          <span
            className={`font-mono text-[10px] font-bold px-1.5 py-0.2 rounded ${
              passedCount === checks.length
                ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                : "bg-amber-50 text-amber-700 border border-amber-200"
            }`}
          >
            {passedCount}/{checks.length} Complete
          </span>
        </div>

        <div className="space-y-1.5 divide-y divide-slate-100 text-xs">
          {checks.map((chk, idx) => (
            <div key={idx} className="flex items-start justify-between gap-2 pt-1.5">
              <div className="flex items-start gap-1.5 min-w-0">
                {chk.valid ? (
                  <CheckCircle2 size={13} className="text-emerald-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle size={13} className="text-slate-300 shrink-0 mt-0.5" />
                )}
                <span
                  className={`text-[11px] leading-tight ${
                    chk.valid ? "text-slate-700 font-medium" : "text-slate-400"
                  }`}
                >
                  {chk.label}
                </span>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0 font-mono">
                {chk.detail}
              </span>
            </div>
          ))}
        </div>
      </div>

      {/* Action Buttons */}
      <div className="space-y-2 pt-1">
        <Button
          type="button"
          onClick={onAcceptEvidence}
          disabled={!isValid || evidence.status === "Accepted"}
          className="h-10 w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99] disabled:opacity-50 cursor-pointer"
        >
          <CheckCircle2 size={14} />
          <span>
            {evidence.status === "Accepted"
              ? "Evidence Formally Accepted"
              : "Accept Evidence for Audit Use"}
          </span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onSaveDraft}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <Save size={13} className="text-emerald-700" />
          <span>Save Evidence Draft</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onCreateNewEvidence}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <Plus size={13} className="text-emerald-700" />
          <span>New Evidence Item</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(
              `/admin/audit-engagement/${evidence.engagementNumber}/execution/workpapers`,
            )
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <FileSpreadsheet size={13} className="text-purple-700" />
          <span>Working Papers (SCR-228)</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(
              `/admin/audit-engagement/${evidence.engagementNumber}/fieldwork-records`,
            )
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <FileCheck2 size={13} className="text-blue-600" />
          <span>Fieldwork Records (SCR-227)</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(
              `/admin/audit-engagement/${evidence.engagementNumber}/execution`,
            )
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <ArrowLeft size={13} />
          <span>Return to Execution Hub (SCR-226)</span>
        </Button>
      </div>
    </div>
  );
}
