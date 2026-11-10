import {
  AlertTriangle,
  FileKey2,
  Scale,
  ShieldAlert,
  UserCheck,
} from "lucide-react";
import type { AssignedTeamMember } from "../team-assignment-types";

type IndependenceAccessSectionProps = {
  team: AssignedTeamMember[];
  isReadOnly?: boolean;
  onUpdateMember: (id: string, updates: Partial<AssignedTeamMember>) => void;
};

export function IndependenceAccessSection({
  team,
  isReadOnly = false,
  onUpdateMember,
}: IndependenceAccessSectionProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Scale className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Independence, Disclosed Impairments & Access Controls (§9.1 AEM-FR-008)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          COI & Safeguard Declarations
        </span>
      </div>

      <div className="divide-y divide-slate-100 space-y-3">
        {team.map((member, idx) => {
          const isAccessConfirmed = member.hasSystemAccessConfirmed;
          const impairmentLen = (member.disclosedImpairments || "").trim().length;
          const safeguardLen = (member.safeguardsDocumented || "").trim().length;
          const isCoiValid =
            !member.hasConflictOfInterest ||
            (impairmentLen >= 10 && safeguardLen >= 10);
          const isSafeguardValid = safeguardLen >= 5;

          return (
            <div key={member.id} className={`pt-3 space-y-2.5 ${idx === 0 ? "pt-0" : ""}`}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                  <UserCheck size={14} className="text-slate-500" />
                  <span className="font-bold text-slate-900 text-xs">
                    {member.name || `Member #${idx + 1}`}
                  </span>
                  <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-semibold text-slate-600 border border-slate-200">
                    {member.role}
                  </span>
                </div>

                {/* Authority & System Access Toggle */}
                <div className="flex flex-col items-end gap-0.5">
                  <label
                    className={`flex items-center gap-1.5 rounded px-2 py-1 border text-xs transition ${
                      isReadOnly
                        ? isAccessConfirmed
                          ? "bg-emerald-50 text-emerald-800 border-emerald-200 cursor-default"
                          : "bg-slate-100 text-slate-600 border-slate-200 cursor-default"
                        : isAccessConfirmed
                        ? "bg-emerald-50 text-emerald-800 border-emerald-200 cursor-pointer"
                        : "bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100 cursor-pointer"
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={member.hasSystemAccessConfirmed}
                      disabled={isReadOnly}
                      onChange={(e) =>
                        onUpdateMember(member.id, {
                          hasSystemAccessConfirmed: e.target.checked,
                        })
                      }
                      className={`h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 ${
                        isReadOnly ? "cursor-not-allowed" : "cursor-pointer"
                      }`}
                    />
                    <FileKey2 size={12} className={isAccessConfirmed || isReadOnly ? "text-emerald-600" : "text-rose-600"} />
                    <span className="text-[11px] font-bold">
                      System & Document Access Confirmed *
                    </span>
                  </label>
                  {!isAccessConfirmed && !isReadOnly && (
                    <span className="text-[9px] text-rose-600 font-medium">
                      Required for AEO authorization
                    </span>
                  )}
                </div>
              </div>

              {/* COI Declaration Row */}
              <div
                className={`rounded-lg border p-2.5 space-y-2 text-xs transition ${
                  isCoiValid || isReadOnly
                    ? "border-slate-200 bg-slate-50/60"
                    : "border-rose-300 bg-rose-50/20"
                }`}
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-semibold text-slate-800 text-[11px] flex items-center gap-1">
                    <ShieldAlert size={12} className="text-slate-500" />
                    Conflict of Interest (COI) / Objectivity Impairment Declaration
                  </span>

                  <div className="flex items-center gap-3">
                    <label className={`flex items-center gap-1 ${isReadOnly ? "cursor-default opacity-80" : "cursor-pointer"}`}>
                      <input
                        type="radio"
                        name={`coi_${member.id}`}
                        checked={!member.hasConflictOfInterest}
                        disabled={isReadOnly}
                        onChange={() =>
                          onUpdateMember(member.id, {
                            hasConflictOfInterest: false,
                            disclosedImpairments: "",
                          })
                        }
                        className={`h-3.5 w-3.5 text-emerald-600 ${isReadOnly ? "cursor-not-allowed" : "cursor-pointer"}`}
                      />
                      <span className="text-[11px] font-bold text-emerald-700">
                        No Impairment (Clear)
                      </span>
                    </label>

                    <label className={`flex items-center gap-1 ${isReadOnly ? "cursor-default opacity-80" : "cursor-pointer"}`}>
                      <input
                        type="radio"
                        name={`coi_${member.id}`}
                        checked={member.hasConflictOfInterest}
                        disabled={isReadOnly}
                        onChange={() =>
                          onUpdateMember(member.id, {
                            hasConflictOfInterest: true,
                          })
                        }
                        className={`h-3.5 w-3.5 text-rose-600 ${isReadOnly ? "cursor-not-allowed" : "cursor-pointer"}`}
                      />
                      <span className="text-[11px] font-bold text-rose-700">
                        Impairment Declared
                      </span>
                    </label>
                  </div>
                </div>

                {/* Disclosed Impairment Field (Required if conflict declared) */}
                {member.hasConflictOfInterest && (
                  <div className="space-y-1 rounded border border-rose-200 bg-rose-50/50 p-2 text-xs">
                    <div className="flex items-center justify-between">
                      <label className="text-[11px] font-bold text-rose-900 flex items-center gap-1">
                        <AlertTriangle size={11} className="text-rose-600" />
                        <span>Disclosed Impairment Description (Mandatory per MDS §7) *</span>
                      </label>
                      <span className="text-[10px] font-mono text-rose-700">
                        {impairmentLen}/10 chars min
                      </span>
                    </div>
                    <textarea
                      rows={2}
                      value={member.disclosedImpairments}
                      disabled={isReadOnly}
                      onChange={(e) =>
                        onUpdateMember(member.id, {
                          disclosedImpairments: e.target.value,
                        })
                      }
                      placeholder="Detail the nature of the declared personal, financial, familial, or prior operational tie to the auditee..."
                      className={`w-full rounded border p-1.5 text-xs text-rose-950 focus:outline-hidden disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed resize-none ${
                        impairmentLen < 10 && !isReadOnly
                          ? "border-rose-300 bg-white focus:border-rose-500"
                          : "border-emerald-300 bg-white"
                      }`}
                      required
                    />
                    {impairmentLen < 10 && !isReadOnly && (
                      <p className="text-[10px] text-rose-700">
                        Please describe the impairment in detail (at least 10 characters).
                      </p>
                    )}
                  </div>
                )}

                {/* Safeguards & Independence Mitigation */}
                <div className="space-y-1">
                  <div className="flex items-center justify-between">
                    <label className="text-[11px] font-semibold text-slate-700 flex items-center gap-1">
                      <span>Documented Safeguards & Independence Affirmed</span>
                      <span className="text-rose-500">*</span>
                    </label>
                    <span className="text-[10px] font-mono text-slate-400">
                      {safeguardLen}/5 chars min
                    </span>
                  </div>
                  <input
                    type="text"
                    value={member.safeguardsDocumented}
                    disabled={isReadOnly}
                    onChange={(e) =>
                      onUpdateMember(member.id, {
                        safeguardsDocumented: e.target.value,
                      })
                    }
                    placeholder="e.g. Standard annual independence declaration signed; excluded from auditing sub-vouchers from related suppliers..."
                    className={`h-7 w-full rounded border px-2 text-[11px] text-slate-800 focus:outline-hidden disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed ${
                      !isSafeguardValid && !isReadOnly
                        ? "border-amber-300 bg-amber-50/20"
                        : "border-slate-200 bg-white focus:border-blue-500"
                    }`}
                    required
                  />
                  {!isSafeguardValid && !isReadOnly && (
                    <p className="text-[10px] text-amber-700">
                      {member.hasConflictOfInterest
                        ? "Documented safeguards (min. 10 chars) are mandatory when an impairment is declared."
                        : "Enter documented safeguards or standard independence affirmation."}
                    </p>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
