import { useState } from "react";
import {
  Calendar,
  CheckCircle2,
  Clock,
  MessageSquareCheck,
  Plus,
  User,
  UserCheck,
  X,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import type { ReviewNote, ReviewNoteStatus } from "../execution-types";

type ReviewNotesSectionProps = {
  reviewNotes: ReviewNote[];
  availableRecords?: string[];
  supervisors?: string[];
  onAddReviewNote?: (noteData: Omit<ReviewNote, "id">) => void;
};

export function ReviewNotesSection({
  reviewNotes,
  availableRecords = [],
  supervisors = [
    "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
    "Director Manuel C. Tan, CPA, CFE (Department Head)",
    "Mark Anthony R. Santos, CPA, CIA (Senior Reviewer)",
  ],
  onAddReviewNote,
}: ReviewNotesSectionProps) {
  const [showForm, setShowForm] = useState(false);
  const [reviewer, setReviewer] = useState(supervisors[0] || "Supervising Auditor");
  const [relatedEntity, setRelatedEntity] = useState(
    availableRecords[0] || "Procedure / Working Paper",
  );
  const [noteText, setNoteText] = useState("");
  const [status, setStatus] = useState<ReviewNoteStatus>("Open");
  const [dueDate, setDueDate] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() + 7);
    return d.toISOString().split("T")[0];
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!noteText.trim() || !onAddReviewNote) return;

    onAddReviewNote({
      reviewer,
      relatedEntity,
      note: noteText.trim(),
      status,
      dueDate,
      response: "",
      resolution: "",
    });

    setNoteText("");
    setShowForm(false);
  };

  const getStatusBadge = (s: ReviewNoteStatus) => {
    switch (s) {
      case "Cleared":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "In Discussion":
        return "bg-amber-50 text-amber-800 border-amber-200";
      case "Open":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "Waived":
        return "bg-slate-100 text-slate-600 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <MessageSquareCheck className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Supervisory Review Notes (§7 Review Note Entity)
          </h3>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
            {reviewNotes.length} Review Notes Logged
          </span>
        </div>

        {onAddReviewNote && (
          <Button
            type="button"
            onClick={() => {
              if (availableRecords.length > 0) {
                setRelatedEntity(availableRecords[0]);
              }
              setShowForm(!showForm);
            }}
            className="h-8 rounded-lg text-xs px-3 flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-sm cursor-pointer transition active:scale-[0.99]"
          >
            <Plus size={13} />
            <span>Add Review Note</span>
          </Button>
        )}
      </div>

      {/* Review Note Creation Form Card */}
      {showForm && (
        <form
          onSubmit={handleSubmit}
          className="rounded-xl border border-blue-200 bg-blue-50/40 p-4 space-y-3.5 text-xs"
        >
          <div className="flex items-center justify-between font-bold text-blue-950 border-b border-blue-100 pb-2">
            <span>Issue Supervisory Review Note / Quality Directive</span>
            <button
              type="button"
              onClick={() => setShowForm(false)}
              className="text-slate-400 hover:text-slate-600 cursor-pointer p-0.5 rounded"
            >
              <X size={15} />
            </button>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div className="sm:col-span-2">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Reviewing Supervisor <span className="text-rose-500">*</span>
                </span>
                <select
                  value={reviewer}
                  onChange={(e) => setReviewer(e.target.value)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                >
                  {supervisors.map((s, idx) => (
                    <option key={idx} value={s}>
                      {s}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <div className="sm:col-span-2 lg:col-span-1">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Related Record / Working Paper <span className="text-rose-500">*</span>
                </span>
                <select
                  value={relatedEntity}
                  onChange={(e) => setRelatedEntity(e.target.value)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs font-mono text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  required
                >
                  {availableRecords.map((rec, idx) => (
                    <option key={idx} value={rec}>
                      {rec}
                    </option>
                  ))}
                  {availableRecords.length === 0 && (
                    <option value="PROC-2026-001-01">PROC-2026-001-01</option>
                  )}
                </select>
              </label>
            </div>

            <div className="sm:col-span-1">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Initial Status
                </span>
                <select
                  value={status}
                  onChange={(e) => setStatus(e.target.value as ReviewNoteStatus)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                >
                  <option value="Open">Open</option>
                  <option value="In Discussion">In Discussion</option>
                </select>
              </label>
            </div>

            <div className="sm:col-span-1">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Due Date
                </span>
                <input
                  type="date"
                  value={dueDate}
                  onChange={(e) => setDueDate(e.target.value)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                />
              </label>
            </div>

            <div className="sm:col-span-4">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Review Query / Directive & Quality Guidance <span className="text-rose-500">*</span>
                </span>
                <textarea
                  rows={2}
                  required
                  placeholder="Document specific supervisory inquiry, sampling expansion directive, or clarification required from the audit team..."
                  value={noteText}
                  onChange={(e) => setNoteText(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                />
              </label>
            </div>
          </div>

          <div className="flex items-center justify-end gap-2.5 pt-2.5 border-t border-blue-100">
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowForm(false)}
              className="h-9 px-4 rounded-lg text-xs font-semibold border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              className="h-9 px-5 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-1.5 cursor-pointer transition active:scale-[0.99]"
            >
              <span>Save Review Note</span>
            </Button>
          </div>
        </form>
      )}

      {reviewNotes.length === 0 && !showForm ? (
        <div className="rounded-lg border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400">
          No supervisory review notes logged yet. Click "+ Add Review Note" to record quality review feedback.
        </div>
      ) : (

      <div className="space-y-2 text-xs">
        {reviewNotes.map((note) => {
          const isCleared = note.status === "Cleared";

          return (
            <div
              key={note.id}
              className="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2 transition hover:bg-slate-50"
            >
              {/* Header row */}
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/70 pb-1.5">
                <div className="flex items-center gap-2">
                  <span className="font-mono text-xs font-bold text-blue-700 bg-white px-1.5 py-0.2 rounded border border-blue-200">
                    {note.id}
                  </span>
                  <span className="font-mono text-[11px] font-bold text-purple-700 bg-purple-50 px-1.5 py-0.2 rounded border border-purple-200">
                    {note.relatedEntity}
                  </span>
                </div>

                <div className="flex items-center gap-2">
                  <div className="flex items-center gap-1 text-[11px] text-slate-500 font-mono">
                    <Calendar size={11} className="text-slate-400" />
                    <span>Due {note.dueDate}</span>
                  </div>
                  <span
                    className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-bold border ${getStatusBadge(
                      note.status,
                    )}`}
                  >
                    {isCleared ? (
                      <CheckCircle2 size={10} className="shrink-0" />
                    ) : (
                      <Clock size={10} className="shrink-0" />
                    )}
                    <span>{note.status}</span>
                  </span>
                </div>
              </div>

              {/* Note Content */}
              <div className="space-y-1.5 text-xs">
                <div className="rounded bg-white p-2 border border-slate-200/80 space-y-0.5">
                  <div className="flex items-center justify-between text-[10px] text-slate-400 font-semibold">
                    <span className="flex items-center gap-1">
                      <User size={10} className="text-blue-600" />
                      Reviewer Note ({note.reviewer})
                    </span>
                  </div>
                  <p className="text-slate-800 text-[11px] leading-relaxed">
                    {note.note}
                  </p>
                </div>

                {note.response && (
                  <div className="rounded bg-slate-50 p-2 border border-slate-200 space-y-0.5 text-[11px]">
                    <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                      Auditor Response & Actions
                    </span>
                    <p className="text-slate-700 leading-snug">{note.response}</p>
                  </div>
                )}

                {note.resolution && (
                  <div className="flex items-center justify-between text-[10px] text-slate-600 bg-slate-100/70 px-2 py-1 rounded">
                    <span>
                      <strong>Resolution:</strong> {note.resolution}
                    </span>
                    {note.clearedBy && (
                      <span className="flex items-center gap-1 font-mono font-bold text-emerald-700">
                        <UserCheck size={11} />
                        <span>Cleared by {note.clearedBy}</span>
                      </span>
                    )}
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>
      )}
    </div>
  );
}
