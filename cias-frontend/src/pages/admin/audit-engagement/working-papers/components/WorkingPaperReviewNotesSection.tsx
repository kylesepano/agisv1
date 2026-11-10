import {
  AlertCircle,
  CheckCircle2,
  Clock,
  MessageSquare,
  ShieldCheck,
} from "lucide-react";
import type { WorkingPaper } from "../working-papers-types";

type WorkingPaperReviewNotesSectionProps = {
  workingPaper: WorkingPaper;
  onClearReviewNotes?: () => void;
};

export function WorkingPaperReviewNotesSection({
  workingPaper,
  onClearReviewNotes,
}: WorkingPaperReviewNotesSectionProps) {
  const hasOpenNotes = workingPaper.reviewNotes.some(
    (rn) => rn.status !== "Cleared",
  );

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <MessageSquare className="h-4 w-4 text-purple-700" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Supervisory Review Notes & Clearance Tracking (SCR-226 Linkage)
          </h3>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="font-mono text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded">
            {workingPaper.reviewNotes.length} Note{workingPaper.reviewNotes.length === 1 ? "" : "s"} Tracked
          </span>
          {workingPaper.reviewStatus === "Cleared" && (
            <span className="font-mono text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
              WP Cleared
            </span>
          )}
        </div>
      </div>

      {workingPaper.reviewNotes.length === 0 ? (
        <div className="rounded border border-dashed border-slate-200 bg-slate-50/50 p-3 text-center text-xs text-slate-500">
          <p className="text-slate-600 font-medium">
            No supervisory review notes currently logged against this working paper.
          </p>
          <span className="text-[11px] text-slate-400">
            Review notes issued in SCR-226 will automatically cross-reference and populate here.
          </span>
        </div>
      ) : (
        <div className="space-y-2.5">
          {workingPaper.reviewNotes.map((rn) => (
            <div
              key={rn.id}
              className={`rounded-md border p-3 text-xs space-y-2 transition ${
                rn.status === "Cleared"
                  ? "border-emerald-200 bg-emerald-50/40"
                  : rn.status === "In Discussion"
                    ? "border-amber-200 bg-amber-50/40"
                    : "border-slate-200 bg-slate-50/60"
              }`}
            >
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-1.5">
                <div className="flex items-center gap-2">
                  <span className="font-mono text-[10px] font-bold text-purple-700 bg-white px-2 py-0.5 rounded border border-purple-200">
                    {rn.id}
                  </span>
                  <span className="text-[11px] font-semibold text-slate-800">
                    Reviewer: {rn.reviewer}
                  </span>
                </div>

                <div className="flex items-center gap-1.5">
                  {rn.status === "Cleared" ? (
                    <span className="inline-flex items-center gap-1 font-mono text-[10px] font-bold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded border border-emerald-300">
                      <CheckCircle2 size={11} />
                      <span>Cleared</span>
                    </span>
                  ) : rn.status === "In Discussion" ? (
                    <span className="inline-flex items-center gap-1 font-mono text-[10px] font-bold text-amber-800 bg-amber-100/70 px-2 py-0.5 rounded border border-amber-300">
                      <Clock size={11} />
                      <span>In Discussion</span>
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 font-mono text-[10px] font-bold text-rose-700 bg-rose-100/70 px-2 py-0.5 rounded border border-rose-300">
                      <AlertCircle size={11} />
                      <span>Open Note</span>
                    </span>
                  )}
                </div>
              </div>

              {/* Note body */}
              <div className="space-y-1 text-slate-700">
                <div className="text-[11px]">
                  <strong className="text-slate-900">Query / Directive:</strong> {rn.note}
                </div>
                {rn.response && (
                  <div className="text-[11px] text-slate-600 pl-2 border-l-2 border-slate-300 py-0.5 bg-white/70 rounded-r">
                    <strong className="text-slate-800">Auditor Response:</strong> {rn.response}
                  </div>
                )}
                {rn.resolution && (
                  <div className="text-[11px] text-slate-600 pl-2 border-l-2 border-emerald-400 py-0.5 bg-white/70 rounded-r">
                    <strong className="text-emerald-900">Resolution / Action:</strong> {rn.resolution}
                  </div>
                )}
              </div>

              {/* Cleared metadata */}
              {rn.clearedBy && (
                <div className="flex items-center justify-between pt-1 border-t border-slate-100 text-[10px] text-slate-500">
                  <span>
                    Cleared by: <strong>{rn.clearedBy}</strong>
                  </span>
                  <span>Date: {rn.clearanceDate || "2026-03-16"}</span>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Formal Supervisory Clearance Signoff Box */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2 text-xs">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1.5 font-bold text-slate-800">
            <ShieldCheck size={14} className="text-emerald-600" />
            <span>Formal Supervisory Clearance (MDS-200 §7)</span>
          </div>
          <span className="text-[10px] font-mono text-slate-400">
            Phase 4 Review Signoff
          </span>
        </div>

        {workingPaper.clearance ? (
          <div className="rounded-md border border-emerald-200 bg-emerald-50/60 p-2.5 space-y-1">
            <div className="flex flex-wrap items-center justify-between gap-1 text-[11px]">
              <span className="text-emerald-900">
                Cleared by: <strong>{workingPaper.clearance.clearedBy}</strong>
              </span>
              <span className="font-mono text-emerald-800 font-medium">
                {workingPaper.clearance.clearanceDate}
              </span>
            </div>
            {workingPaper.clearance.clearanceRemarks && (
              <p className="text-[10px] text-emerald-800 italic">
                &ldquo;{workingPaper.clearance.clearanceRemarks}&rdquo;
              </p>
            )}
          </div>
        ) : (
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-2 rounded bg-white border border-slate-200">
            <div className="text-[11px] text-slate-600">
              {hasOpenNotes ? (
                <span className="text-amber-800 font-medium">
                  Review notes are currently active. Complete discussions and resolutions to qualify for supervisory clearance.
                </span>
              ) : (
                <span>
                  Ready for quality review signoff and clearance by <strong>{workingPaper.reviewer}</strong>.
                </span>
              )}
            </div>

            {onClearReviewNotes && (
              <button
                type="button"
                onClick={onClearReviewNotes}
                className="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded bg-purple-600 text-white hover:bg-purple-700 transition cursor-pointer shrink-0"
              >
                <CheckCircle2 size={12} />
                <span>Mark Review Notes Cleared</span>
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
