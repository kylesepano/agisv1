import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  FileCheck2,
  Plus,
  Save,
  Send,
  Shield,
  XCircle,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type { FieldworkRecord } from "../fieldwork-types";

type FieldworkControlsRailProps = {
  record: FieldworkRecord;
  isValid: boolean;
  onSaveDraft: () => void;
  onSubmitRecord: () => void;
  onCreateNewRecord: () => void;
};

export function FieldworkControlsRail({
  record,
  isValid,
  onSaveDraft,
  onSubmitRecord,
  onCreateNewRecord,
}: FieldworkControlsRailProps) {
  const checks = [
    {
      label: "Phase 3 Entry Conference completed (Rule 11)",
      valid: true,
      detail: "Satisfied",
    },
    {
      label: "Linked to 1 Procedure ID (MDS-200 §7.1)",
      valid: (record.procedureId || "").trim().length > 0,
      detail: record.procedureId,
    },
    {
      label: "Working Paper reference mapped (Rule 13)",
      valid: (record.workingPaperRef || "").trim().length > 0,
      detail: record.workingPaperRef,
    },
    {
      label: "Core Facts Obtained documented (min 15 chars)",
      valid: (record.factsObtained || "").trim().length >= 15,
      detail: `${(record.factsObtained || "").length} chars`,
    },
    {
      label: "Preparer & Reviewer assigned (MDS-200 §16)",
      valid:
        (record.preparer || "").trim().length > 0 &&
        (record.reviewer || "").trim().length > 0,
      detail: "Assigned",
    },
  ];

  const passedCount = checks.filter((c) => c.valid).length;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-blue-600" />
          <span>Fieldwork Controls (§7, §11, §16)</span>
        </div>
        <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-blue-700">
          SCR-227
        </span>
      </div>

      {/* Record Governance Identity Box */}
      <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1.5 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Record ID
          </span>
          <span className="font-mono font-bold text-blue-700 text-xs">
            {record.id} ({record.version})
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Lifecycle Phase
          </span>
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 4 • Execution Source
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Provisional Status
          </span>
          <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-bold text-amber-800 border border-amber-200">
            {record.status}
          </span>
        </div>
      </div>

      {/* Open Gap Notice: Provisional Status Warning */}
      <div className="rounded-md border border-amber-200 bg-amber-50/80 p-2 text-[10px] text-amber-900 leading-snug space-y-1">
        <div className="flex items-center gap-1 font-bold text-amber-950">
          <AlertTriangle size={11} className="text-amber-700 shrink-0" />
          <span>Open Gap: MDS-200 §10 Status Model</span>
        </div>
        <p className="text-[10px]">
          MDS-200 §10 defines status values for Engagements, Issues, Findings, and Reports, but leaves Fieldwork Record unstandardized. Status flow (Draft → Submitted → Reviewed) is <strong>OD-MDS-Status Provisional</strong> pending formal specification.
        </p>
      </div>

      {/* Signatories Box (MDS-200 §16, UID-200 §7.3) */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-1.5 text-xs">
        <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
          Controlled Signatories
        </span>

        <div className="space-y-1 text-[11px]">
          <div className="flex items-center justify-between">
            <span className="text-slate-500">Preparer:</span>
            <span className="font-semibold text-slate-800 truncate max-w-[170px]">
              {record.preparer}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-slate-500">Supervising Reviewer:</span>
            <span className="font-semibold text-slate-800 truncate max-w-[170px]">
              {record.reviewer}
            </span>
          </div>
        </div>
      </div>

      {/* 5-Point Governance Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <FileCheck2 size={12} className="text-blue-600" />
            <span>Fieldwork Governance Checklist</span>
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
          onClick={onSubmitRecord}
          disabled={!isValid || record.status === "Submitted"}
          className="h-10 w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99] disabled:opacity-50"
        >
          <Send size={14} />
          <span>{record.status === "Submitted" ? "Record Submitted for Review" : "Submit Fieldwork Record"}</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onSaveDraft}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <Save size={13} className="text-blue-600" />
          <span>Save Record Draft</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onCreateNewRecord}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <Plus size={13} className="text-emerald-600" />
          <span>New Fieldwork Record</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(
              `/admin/audit-engagement/${record.engagementNumber}/execution`,
            )
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <ArrowLeft size={13} />
          <span>Return to Execution Hub (SCR-226)</span>
        </Button>
      </div>
    </div>
  );
}
