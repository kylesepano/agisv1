import {
  AlertTriangle,
  FileCheck2,
  MessageSquare,
  Shield,
  UserCheck,
} from "lucide-react";

type AepCommunicationApprovalSectionProps = {
  communicationToManagement: string;
  reviewingSupervisor: string;
  approvingAuthority: string;
  isAmendment: boolean;
  amendmentReason: string;
  onChangeCommunication: (val: string) => void;
  onChangeSupervisor: (val: string) => void;
  onChangeApprover: (val: string) => void;
  onToggleAmendment: (val: boolean) => void;
  onChangeAmendmentReason: (val: string) => void;
};

export function AepCommunicationApprovalSection({
  communicationToManagement,
  reviewingSupervisor,
  approvingAuthority,
  isAmendment,
  amendmentReason,
  onChangeCommunication,
  onChangeSupervisor,
  onChangeApprover,
  onToggleAmendment,
  onChangeAmendmentReason,
}: AepCommunicationApprovalSectionProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <MessageSquare className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Communication Protocol & Approval Workflow (§7)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          Executive Governance & Stakeholder Alignment
        </span>
      </div>

      <div className="space-y-3 text-xs">
        {/* Communication to Management */}
        <div className="space-y-1">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-semibold text-slate-700 block">
              Communication Protocol to Management
            </label>
            <span className="text-[10px] text-slate-400">
              Briefing cadence & liaison arrangements
            </span>
          </div>
          <textarea
            rows={3}
            value={communicationToManagement}
            onChange={(e) => onChangeCommunication(e.target.value)}
            placeholder="Document scheduled executive briefings, weekly progress memos, and liaison protocols..."
            className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 leading-relaxed focus:border-blue-500 focus:outline-hidden"
            required
          />
        </div>

        {/* Approval Workflow & OD-001 Provisional Signatory Matrix */}
        <div className="space-y-2 pt-1 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-bold text-slate-800 flex items-center gap-1.5">
              <Shield size={13} className="text-blue-600" />
              <span>AEP Review & Approval Signatories (§7)</span>
            </label>
            <span className="text-[10px] font-bold text-amber-800 bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200">
              OD-001 Provisional Signatory
            </span>
          </div>

          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 rounded-lg border border-slate-200 bg-slate-50/60 p-2.5">
            {/* Reviewing Supervisor */}
            <div className="space-y-1">
              <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
                <UserCheck size={11} className="text-blue-600" />
                Reviewing Supervisor (MDS-200 §8)
              </span>
              <input
                type="text"
                value={reviewingSupervisor}
                onChange={(e) => onChangeSupervisor(e.target.value)}
                placeholder="Supervising Auditor name..."
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900"
                required
              />
            </div>

            {/* Approving Authority */}
            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
                  <FileCheck2 size={11} className="text-emerald-600" />
                  Approving Authority (Department Head)
                </span>
                <span className="text-[9px] font-bold text-amber-700 bg-amber-100/70 px-1 py-0.2 rounded">
                  OD-001
                </span>
              </div>
              <input
                type="text"
                value={approvingAuthority}
                onChange={(e) => onChangeApprover(e.target.value)}
                placeholder="Department Head / Chief Audit Executive..."
                className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900"
                required
              />
            </div>
          </div>
        </div>

        {/* Amendment Control (§7) */}
        <div className="space-y-2 pt-1 border-t border-slate-100">
          <label className="flex items-center gap-2 cursor-pointer font-bold text-slate-800">
            <input
              type="checkbox"
              checked={isAmendment}
              onChange={(e) => onToggleAmendment(e.target.checked)}
              className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
            />
            <span className="text-xs">
              This document is an Amendment / Superseding Revision to an Approved AEP
            </span>
          </label>

          {isAmendment && (
            <div className="rounded-lg border border-amber-300 bg-amber-50/70 p-2.5 space-y-1 text-xs text-amber-950">
              <label className="text-[11px] font-bold flex items-center gap-1 text-amber-900">
                <AlertTriangle size={12} className="text-amber-600" />
                <span>Amendment Justification & Scope Change Reason (Mandatory)</span>
              </label>
              <textarea
                rows={2}
                value={amendmentReason}
                onChange={(e) => onChangeAmendmentReason(e.target.value)}
                placeholder="Explain the authorized operational changes, unexpected audit constraints, or emerging risks requiring this AEP amendment..."
                className="w-full rounded border border-amber-300 bg-white p-2 text-xs text-slate-900 focus:border-amber-500 focus:outline-hidden"
                required
              />
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
