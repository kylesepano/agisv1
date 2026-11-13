import {
  AlertTriangle,
  FileCheck2,
  Info,
} from "lucide-react";
import type { AuthorizedWaiverState } from "../conference-types";

type AuthorizedWaiverSectionProps = {
  waiver: AuthorizedWaiverState;
  onChangeWaiver: (updates: Partial<AuthorizedWaiverState>) => void;
};

export function AuthorizedWaiverSection({
  waiver,
  onChangeWaiver,
}: AuthorizedWaiverSectionProps) {
  return (
    <div className="rounded-lg border border-amber-300 bg-amber-50/70 p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 pb-2">
        <div className="flex items-center gap-2">
          <AlertTriangle className="h-4 w-4 text-amber-600" />
          <h3 className="text-[13px] font-bold text-amber-950">
            Authorized Waiver / Alternative Commencement (MDS-200 §11 Rule 11 & AEM-FR-018)
          </h3>
        </div>
        <span className="text-[10px] font-bold text-amber-800 bg-amber-100 px-1.5 py-0.2 rounded border border-amber-300">
          OD-001 Provisional Approver
        </span>
      </div>

      <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-white/80 p-2.5 text-[11px] text-amber-900 leading-snug">
        <Info size={13} className="text-amber-600 shrink-0 mt-0.5" />
        <div>
          <strong>MDS-200 §11 Module Rule 11 Invariant:</strong> Fieldwork shall not begin without required authority, planning approval, and an Entry Conference OR an authorized waiver / alternative procedure, except under documented emergency or special executive authorization.
        </div>
      </div>

      <div className="space-y-3 text-xs">
        {/* Waiver Justification Reason */}
        <div className="space-y-1">
          <label className="text-[11px] font-bold text-amber-950 block">
            Waiver Justification Reason (Mandatory)
          </label>
          <textarea
            rows={3}
            value={waiver.waiverReason}
            onChange={(e) => onChangeWaiver({ waiverReason: e.target.value })}
            placeholder="Document the formal justification, operational constraints, or legal basis for waiving the standard Entry Conference..."
            className="w-full rounded border border-amber-300 bg-white p-2.5 text-xs text-slate-900 leading-relaxed focus:border-amber-500 focus:outline-hidden"
            required
          />
        </div>

        {/* Alternative Commencement Procedure */}
        <div className="space-y-1">
          <label className="text-[11px] font-bold text-amber-950 block">
            Alternative Commencement Procedure Specification (Mandatory)
          </label>
          <textarea
            rows={3}
            value={waiver.alternativeProcedure}
            onChange={(e) =>
              onChangeWaiver({ alternativeProcedure: e.target.value })
            }
            placeholder="Specify what formal communication protocol, transmittal memo, or electronic notification procedure is executed in lieu of the in-person/virtual conference..."
            className="w-full rounded border border-amber-300 bg-white p-2.5 text-xs text-slate-900 leading-relaxed focus:border-amber-500 focus:outline-hidden"
            required
          />
        </div>

        {/* Approving Authority & Date */}
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 pt-1 border-t border-amber-200">
          <div className="space-y-1">
            <div className="flex items-center justify-between">
              <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
                <FileCheck2 size={11} className="text-amber-700" />
                Approving Executive Authority
              </span>
              <span className="text-[9px] font-bold text-amber-800 bg-amber-100 px-1 py-0.2 rounded">
                OD-001
              </span>
            </div>
            <input
              type="text"
              value={waiver.approvingAuthority}
              onChange={(e) =>
                onChangeWaiver({ approvingAuthority: e.target.value })
              }
              placeholder="e.g. Director Manuel C. Tan, CPA, CFE..."
              className="h-8 w-full rounded border border-amber-300 bg-white px-2 text-xs font-semibold text-slate-900"
              required
            />
          </div>

          <div className="space-y-1">
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
              Authorization Approval Date
            </span>
            <input
              type="date"
              value={waiver.approvalDate}
              onChange={(e) =>
                onChangeWaiver({ approvalDate: e.target.value })
              }
              className="h-8 w-full rounded border border-amber-300 bg-white px-2 font-mono text-xs text-slate-900"
              required
            />
          </div>
        </div>

        {/* Formal Approval Toggle */}
        <div className="pt-1">
          <label className="flex items-center gap-2 cursor-pointer font-bold text-amber-950">
            <input
              type="checkbox"
              checked={waiver.isApproved}
              onChange={(e) => onChangeWaiver({ isApproved: e.target.checked })}
              className="h-4 w-4 rounded border-amber-400 text-amber-600 focus:ring-amber-500 cursor-pointer"
            />
            <span className="text-xs">
              Formally Affirm Authorized Waiver & Special Commencement Clearance
            </span>
          </label>
        </div>
      </div>
    </div>
  );
}
