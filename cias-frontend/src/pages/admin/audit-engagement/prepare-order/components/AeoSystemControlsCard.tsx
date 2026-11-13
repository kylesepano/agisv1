import { useState } from "react";
import {
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  FileCheck2,
  History,
  Lock,
  Printer,
  Sparkles,
} from "lucide-react";
import type { PrepareAeoFormState } from "../prepare-order-types";

type AeoSystemControlsCardProps = {
  formState: PrepareAeoFormState;
  isReadyForIssuance: boolean;
  onExportPdf?: () => void;
};

export function AeoSystemControlsCard({
  formState,
  isReadyForIssuance,
  onExportPdf,
}: AeoSystemControlsCardProps) {
  const [showHistory, setShowHistory] = useState(true);

  // Compute Readiness Checklist
  const checks = [
    {
      label: "Office & Scope Defined (SCR-212)",
      completed: Boolean(formState.baseline.office && formState.baseline.auditAreas.length > 0),
    },
    {
      label: "Team Validated (SCR-213)",
      completed: Boolean(
        formState.baseline.team.some((t) => t.role === "Lead Auditor") &&
          formState.baseline.team.length >= 2,
      ),
    },
    {
      label: "Authority Reference Verified",
      completed: Boolean(formState.baseline.authorityReference?.trim()),
    },
    {
      label: "No Unresolved Source Drift",
      completed: !formState.isSourceDrifted,
    },
    {
      label: "Recipients List Populated",
      completed: Boolean(formState.recipients.length > 0),
    },
  ];

  const completedCount = checks.filter((c) => c.completed).length;
  const totalCount = checks.length;
  const progressPercent = Math.round((completedCount / totalCount) * 100);

  const getStatusColor = (status: string) => {
    switch (status) {
      case "Issued":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "Approved":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "Under Review":
      case "Submitted":
        return "bg-indigo-50 text-indigo-700 border-indigo-200";
      case "Returned for Correction":
        return "bg-rose-50 text-rose-700 border-rose-200";
      default:
        return "bg-amber-50 text-amber-700 border-amber-200";
    }
  };

  return (
    <div className="space-y-2.5">
      {/* System Governance Card */}
      <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2.5">
        <div className="flex items-center justify-between border-b border-slate-100 pb-2">
          <div className="flex items-center gap-1.5">
            <Lock className="h-3.5 w-3.5 text-blue-600" />
            <h4 className="text-[13px] font-bold text-slate-800">
              System Controls (Controlled Doc)
            </h4>
          </div>
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[9px] font-bold text-indigo-700 border border-indigo-200">
            MDS-200 §5.1
          </span>
        </div>

        <div className="space-y-2">
          {/* Document Status */}
          <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2 text-xs">
            <div className="flex items-center justify-between text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              <span>AEO Document Status</span>
              <span className="text-[10px] font-mono text-slate-500">{formState.version}</span>
            </div>
            <div className="mt-0.5 flex items-center justify-between">
              <span
                className={`rounded px-2 py-0.5 text-[11px] font-bold border ${getStatusColor(
                  formState.status,
                )}`}
              >
                {formState.status}
              </span>
              <span className="font-mono text-[10px] text-slate-500 font-bold">
                {formState.aeoNumber}
              </span>
            </div>
          </div>

          {/* Lifecycle Phase */}
          <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2 text-xs">
            <div className="flex items-center justify-between text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              <span>Lifecycle Phase</span>
              <span className="text-indigo-600 font-semibold text-[10px]">Phase 1 Close</span>
            </div>
            <div className="mt-0.5 flex items-center gap-1.5">
              <span className="inline-flex items-center gap-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-700 border border-indigo-200">
                <Sparkles className="h-3 w-3 text-indigo-500" />
                Engagement Initiation
              </span>
            </div>
          </div>

          {/* Engagement Code Reference */}
          <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2 text-xs">
            <div className="flex items-center justify-between text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              <span>Master Engagement</span>
              <span className="text-emerald-600 font-semibold">Active Anchor</span>
            </div>
            <div className="mt-0.5 flex items-center justify-between">
              <span className="font-mono text-[12px] font-bold text-blue-700">
                {formState.engagementNumber}
              </span>
              <span className="text-[10px] text-slate-400">SCR-220 Master</span>
            </div>
          </div>

          {/* Export / Print Action — Visible only on Issued (MDS-200 §18) */}
          {(formState.status === "Issued" || formState.status === "Amended" || formState.status === "Superseded") && onExportPdf && (
            <div className="pt-1">
              <button
                type="button"
                onClick={onExportPdf}
                className="w-full flex items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100 hover:border-blue-300 transition active:scale-[0.99] shadow-2xs cursor-pointer"
                title="Generate and download official PDF record per MDS-200 §18"
              >
                <Printer size={14} className="text-blue-600" />
                <span>Export / Print Official AEO</span>
              </button>
              <div className="mt-1 flex items-center justify-between text-[9.5px] text-slate-400">
                <span>MDS-200 §18 Records Control</span>
                <span className="font-mono font-bold text-slate-500">PDF • {formState.version}</span>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Issuance Readiness Checklist */}
      <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
        <div className="flex items-center justify-between border-b border-slate-100 pb-1.5">
          <h4 className="text-[13px] font-bold text-slate-800">
            AEO Issuance Readiness
          </h4>
          <span className="text-[11px] font-bold text-slate-600 font-mono">
            {completedCount}/{totalCount}
          </span>
        </div>

        {/* Progress Bar */}
        <div>
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
            <div
              className={`h-full transition-all duration-300 ${
                isReadyForIssuance ? "bg-emerald-500" : "bg-blue-500"
              }`}
              style={{ width: `${progressPercent}%` }}
            />
          </div>
        </div>

        <ul className="space-y-1 text-[11px]">
          {checks.map((c) => (
            <li
              key={c.label}
              className="flex items-center justify-between text-slate-600"
            >
              <span className={c.completed ? "text-slate-700 font-medium" : "text-slate-400"}>
                {c.label}
              </span>
              <CheckCircle2
                size={13}
                className={c.completed ? "text-emerald-600" : "text-slate-200"}
              />
            </li>
          ))}
        </ul>
      </div>

      {/* Downstream Fieldwork Unlocking Gate */}
      <div className="rounded-lg border border-indigo-100 bg-indigo-50/50 p-2.5 text-xs">
        <div className="flex items-center gap-1.5 text-indigo-900 font-bold text-[11px]">
          <FileCheck2 size={13} className="text-indigo-600" />
          <span>Issuance Gate: MDS-200 §9.1 AEM-FR-010</span>
        </div>
        <p className="mt-0.5 text-[10px] text-indigo-800/90 leading-snug">
          Issuing this AEO formally completes Phase 1 and transitions the engagement to <strong>Planning & Fieldwork in SCR-220</strong>.
        </p>
      </div>

      {/* Audit Trail Drawer */}
      <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
        <button
          type="button"
          onClick={() => setShowHistory(!showHistory)}
          className="flex items-center justify-between w-full text-left cursor-pointer"
        >
          <div className="flex items-center gap-1.5">
            <History size={13} className="text-slate-500" />
            <h4 className="text-[12px] font-bold text-slate-800">
              Audit History Trail (§10.9)
            </h4>
          </div>
          {showHistory ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
        </button>

        {showHistory && (
          <div className="space-y-2 pt-1.5 border-t border-slate-100 text-[11px]">
            {formState.history.map((h) => (
              <div key={h.id} className="rounded bg-slate-50 p-2 border border-slate-100 space-y-1">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-slate-800">{h.action}</span>
                  <span className="font-mono text-[9px] text-slate-400">{h.timestamp}</span>
                </div>
                <div className="flex items-center justify-between text-[10px] text-slate-500">
                  <span>{h.actor} ({h.role})</span>
                  <span className="font-mono font-bold text-blue-700">{h.version}</span>
                </div>
                {h.remarks && (
                  <p className="text-[10px] text-slate-600 italic">"{h.remarks}"</p>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
