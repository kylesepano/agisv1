import { Award, CheckCircle2, Lock, UserCheck } from "lucide-react";
import type { PrepareAeoFormState } from "../prepare-order-types";

type WorkflowSignoffSectionProps = {
  formState: PrepareAeoFormState;
  onChangeField: <K extends keyof PrepareAeoFormState>(
    field: K,
    value: PrepareAeoFormState[K],
  ) => void;
};

export function WorkflowSignoffSection({
  formState,
  onChangeField,
}: WorkflowSignoffSectionProps) {
  const isReviewerActive =
    formState.status === "Submitted" || formState.status === "Under Review";
  const isApproverActive =
    formState.status === "Approved" || formState.status === "Issued";

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5">
          <Award className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Workflow Sign-off & Issuance Authority
          </h3>
        </div>
        <div className="flex items-center gap-2">
          <span
            title="MDS-200 v0.8 §19 Open Decision 1: Signatory & Approval Authority matrix is provisional pending formal executive determination."
            className="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200 font-mono"
          >
            OD-001 Provisional
          </span>
          <span className="text-[10px] text-slate-400 font-medium">
            MDS-200 §10.9 Role Separation
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {/* Reviewer Block */}
        <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2.5">
          <div className="flex items-center justify-between">
            <span className="flex items-center gap-1 text-xs font-bold text-slate-800">
              <UserCheck size={13} className="text-blue-600" />
              1. Reviewer Recommendation
            </span>
            <span className="flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.2 text-[9px] font-bold text-slate-600 border border-slate-200">
              <Lock size={9} /> Designated Role
            </span>
          </div>

          <div className="space-y-1.5">
            <div>
              <span className="text-[11px] font-semibold text-slate-600 block">
                Reviewed By (Supervising Auditor / Manager)
              </span>
              <input
                type="text"
                disabled
                className="h-9 w-full rounded-md border border-slate-200 bg-slate-100/80 px-2.5 text-xs text-slate-700 font-medium cursor-not-allowed"
                value={formState.reviewedBy || "Atty. Fernando M. Gomez, CPA"}
                onChange={(e) => onChangeField("reviewedBy", e.target.value)}
              />
            </div>

            <div>
              <span className="text-[11px] font-semibold text-slate-600 block">
                Official Designation
              </span>
              <input
                type="text"
                disabled
                className="h-9 w-full rounded-md border border-slate-200 bg-slate-100/80 px-2.5 text-xs text-slate-600 cursor-not-allowed"
                value={formState.reviewedRole || "Engagement Manager / Supervising Auditor"}
                onChange={(e) => onChangeField("reviewedRole", e.target.value)}
              />
            </div>

            <div>
              <span className="text-[11px] font-semibold text-slate-600 block">
                Review Remarks
              </span>
              <textarea
                rows={2}
                disabled={!isReviewerActive && formState.status !== "Draft"}
                placeholder="e.g. Verified source parameters, audit scope boundaries, and team sufficiency. Ready for CIAS Department Head approval."
                className={`w-full rounded-md border border-slate-200 p-2 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 resize-y transition ${
                  !isReviewerActive && formState.status !== "Draft"
                    ? "bg-slate-100/80 text-slate-600 cursor-not-allowed"
                    : "bg-white"
                }`}
                value={formState.reviewRemarks}
                onChange={(e) => onChangeField("reviewRemarks", e.target.value)}
              />
            </div>
          </div>
        </div>

        {/* Approver / Department Head Block */}
        <div className="rounded-lg border border-indigo-200/80 bg-indigo-50/40 p-3 space-y-2.5">
          <div className="flex items-center justify-between">
            <span className="flex items-center gap-1 text-xs font-bold text-indigo-950">
              <CheckCircle2 size={13} className="text-indigo-600" />
              2. Approval & Order Issuance
            </span>
            <span className="flex items-center gap-1 rounded bg-indigo-100 px-1.5 py-0.2 text-[9px] font-bold text-indigo-800 border border-indigo-200">
              <Lock size={9} /> Executive Authority
            </span>
          </div>

          <div className="space-y-1.5">
            <div>
              <span className="text-[11px] font-semibold text-indigo-900 block">
                Approved By (CIAS Department Head)
              </span>
              <input
                type="text"
                disabled
                className="h-9 w-full rounded-md border border-indigo-200/80 bg-indigo-100/50 px-2.5 text-xs text-indigo-950 font-medium cursor-not-allowed"
                value={formState.approvedBy || "Director Manuel C. Tan, CPA, CFE"}
                onChange={(e) => onChangeField("approvedBy", e.target.value)}
              />
            </div>

            <div>
              <span className="text-[11px] font-semibold text-indigo-900 block">
                Executive Designation
              </span>
              <input
                type="text"
                disabled
                className="h-9 w-full rounded-md border border-indigo-200/80 bg-indigo-100/50 px-2.5 text-xs text-indigo-900 cursor-not-allowed"
                value={formState.approverRole || "Department Head, City Internal Audit Service"}
                onChange={(e) => onChangeField("approverRole", e.target.value)}
              />
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <span className="text-[11px] font-semibold text-indigo-900 block">
                  Approval Date
                </span>
                <input
                  type="date"
                  disabled={!isApproverActive}
                  className={`h-9 w-full rounded-md border border-indigo-200 px-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-500 ${
                    !isApproverActive ? "bg-indigo-100/50 cursor-not-allowed text-slate-500" : "bg-white"
                  }`}
                  value={formState.approvalDate || new Date().toISOString().split("T")[0]}
                  onChange={(e) => onChangeField("approvalDate", e.target.value)}
                />
              </div>

              <div>
                <span className="text-[11px] font-semibold text-indigo-900 block">
                  Effective Date
                </span>
                <input
                  type="date"
                  disabled={!isApproverActive}
                  className={`h-9 w-full rounded-md border border-indigo-200 px-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-500 ${
                    !isApproverActive ? "bg-indigo-100/50 cursor-not-allowed text-slate-500" : "bg-white"
                  }`}
                  value={formState.effectiveDate || new Date().toISOString().split("T")[0]}
                  onChange={(e) => onChangeField("effectiveDate", e.target.value)}
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="flex items-center gap-1.5 text-[10px] text-slate-400 italic">
        <span>Note: Per MDS-200 §5.1, §7, signature confirmation metadata is formally tracked without requiring external e-signature device hardware.</span>
      </div>
    </div>
  );
}
