import {
  FolderOpen,
  ListChecks,
  MessageSquare,
  Plus,
  Trash2,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import type { FollowUpActionItem, InfoRequestItem } from "../conference-types";

type ConferenceMinutesAgreementsSectionProps = {
  minutesAndNotes: string;
  keyAgreements: string;
  informationRequests: InfoRequestItem[];
  followUpActions: FollowUpActionItem[];
  onChangeMinutes: (val: string) => void;
  onChangeAgreements: (val: string) => void;
  onUpdateInfoRequest: (id: string, updates: Partial<InfoRequestItem>) => void;
  onAddInfoRequest: () => void;
  onRemoveInfoRequest: (id: string) => void;
  onUpdateFollowUp: (id: string, updates: Partial<FollowUpActionItem>) => void;
  onAddFollowUp: () => void;
  onRemoveFollowUp: (id: string) => void;
};

export function ConferenceMinutesAgreementsSection({
  minutesAndNotes,
  keyAgreements,
  informationRequests,
  followUpActions,
  onChangeMinutes,
  onChangeAgreements,
  onUpdateInfoRequest,
  onAddInfoRequest,
  onRemoveInfoRequest,
  onUpdateFollowUp,
  onAddFollowUp,
  onRemoveFollowUp,
}: ConferenceMinutesAgreementsSectionProps) {
  return (
    <div className="space-y-3">
      {/* 1. Minutes & Key Agreements */}
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
          <div className="flex items-center gap-2">
            <MessageSquare className="h-4 w-4 text-blue-600" />
            <h3 className="text-[13px] font-bold text-slate-800">
              Conference Proceedings, Minutes & Key Agreements (§7)
            </h3>
          </div>
          <span className="text-[10px] text-slate-400 font-mono">
            Formal Discussion Record
          </span>
        </div>

        <div className="space-y-3 text-xs">
          {/* Minutes & Notes */}
          <div className="space-y-1">
            <div className="flex items-center justify-between">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Conference Minutes & Meeting Summary
              </label>
              <span className="text-[10px] text-slate-400">
                Official dialogue & discussion summary
              </span>
            </div>
            <textarea
              rows={4}
              value={minutesAndNotes}
              onChange={(e) => onChangeMinutes(e.target.value)}
              placeholder="Record the official conference proceedings, discussion points, and attendee remarks..."
              className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 leading-relaxed focus:border-blue-500 focus:outline-hidden"
              required
            />
          </div>

          {/* Key Mutual Agreements */}
          <div className="space-y-1 pt-1 border-t border-slate-100">
            <div className="flex items-center justify-between">
              <label className="text-[11px] font-semibold text-slate-700 block">
                Key Agreements & Coordination Protocols Reached
              </label>
              <span className="text-[10px] text-slate-400">
                Turnaround SLA, workstation, liaison cadence
              </span>
            </div>
            <textarea
              rows={3}
              value={keyAgreements}
              onChange={(e) => onChangeAgreements(e.target.value)}
              placeholder="Document the mutual agreements on document access, weekly meetings, and point persons..."
              className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 leading-relaxed focus:border-blue-500 focus:outline-hidden"
              required
            />
          </div>
        </div>
      </div>

      {/* 2. Initial Information Requests & Document Demands */}
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
          <div className="flex items-center gap-2">
            <FolderOpen className="h-4 w-4 text-blue-600" />
            <h3 className="text-[13px] font-bold text-slate-800">
              Initial Information Requests & Document Demands (§7)
            </h3>
            <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
              {informationRequests.length} Records Requested
            </span>
          </div>

          <Button
            type="button"
            variant="outline"
            onClick={onAddInfoRequest}
            className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 cursor-pointer"
          >
            <Plus size={12} />
            <span>Add Document Request</span>
          </Button>
        </div>

        <div className="space-y-2 text-xs">
          {informationRequests.map((req) => (
            <div
              key={req.id}
              className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-2 transition hover:bg-slate-50"
            >
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
                <div className="flex items-center gap-2 flex-1 min-w-[220px]">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-white px-1.5 py-0.2 rounded border border-blue-200">
                    {req.id}
                  </span>
                  <input
                    type="text"
                    value={req.documentTitle}
                    onChange={(e) =>
                      onUpdateInfoRequest(req.id, {
                        documentTitle: e.target.value,
                      })
                    }
                    placeholder="Document Title / Record Description..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 font-bold text-xs text-slate-900"
                    required
                  />
                </div>

                <div className="flex items-center gap-2">
                  <select
                    value={req.status}
                    onChange={(e) =>
                      onUpdateInfoRequest(req.id, {
                        status: e.target.value as "Pending" | "Received" | "Partial",
                      })
                    }
                    className="h-7 rounded border border-slate-200 bg-white px-1.5 text-[11px] font-semibold"
                  >
                    <option value="Pending">Pending Delivery</option>
                    <option value="Received">Received & Logged</option>
                    <option value="Partial">Partially Provided</option>
                  </select>

                  {informationRequests.length > 1 && (
                    <button
                      type="button"
                      onClick={() => onRemoveInfoRequest(req.id)}
                      className="text-slate-300 hover:text-rose-600 p-1 transition cursor-pointer"
                      title="Remove request"
                    >
                      <Trash2 size={13} />
                    </button>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 text-[11px]">
                {/* Target Office */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Target Custodian Unit
                  </span>
                  <input
                    type="text"
                    value={req.targetOffice}
                    onChange={(e) =>
                      onUpdateInfoRequest(req.id, {
                        targetOffice: e.target.value,
                      })
                    }
                    placeholder="e.g. BAC Secretariat..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
                    required
                  />
                </div>

                {/* Focal Person */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Focal Person Accountable
                  </span>
                  <input
                    type="text"
                    value={req.focalPerson}
                    onChange={(e) =>
                      onUpdateInfoRequest(req.id, {
                        focalPerson: e.target.value,
                      })
                    }
                    placeholder="Responsible person..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
                    required
                  />
                </div>

                {/* Deadline */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Turnaround Deadline
                  </span>
                  <input
                    type="date"
                    value={req.deadline}
                    onChange={(e) =>
                      onUpdateInfoRequest(req.id, { deadline: e.target.value })
                    }
                    className="h-7 w-full rounded border border-slate-200 bg-white px-1.5 font-mono text-[11px]"
                    required
                  />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 3. Action Items & Follow-up Tasks */}
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
          <div className="flex items-center gap-2">
            <ListChecks className="h-4 w-4 text-blue-600" />
            <h3 className="text-[13px] font-bold text-slate-800">
              Follow-up Action Items & Milestones (§7)
            </h3>
            <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
              {followUpActions.length} Action Items
            </span>
          </div>

          <Button
            type="button"
            variant="outline"
            onClick={onAddFollowUp}
            className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 cursor-pointer"
          >
            <Plus size={12} />
            <span>Add Action Item</span>
          </Button>
        </div>

        <div className="space-y-2 text-xs">
          {followUpActions.map((act) => (
            <div
              key={act.id}
              className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-2 transition hover:bg-slate-50"
            >
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
                <div className="flex items-center gap-2 flex-1 min-w-[220px]">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-white px-1.5 py-0.2 rounded border border-blue-200">
                    {act.id}
                  </span>
                  <input
                    type="text"
                    value={act.actionItem}
                    onChange={(e) =>
                      onUpdateFollowUp(act.id, { actionItem: e.target.value })
                    }
                    placeholder="Action Item Specification..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 font-bold text-xs text-slate-900"
                    required
                  />
                </div>

                {followUpActions.length > 1 && (
                  <button
                    type="button"
                    onClick={() => onRemoveFollowUp(act.id)}
                    className="text-slate-300 hover:text-rose-600 p-1 transition cursor-pointer"
                    title="Remove action"
                  >
                    <Trash2 size={13} />
                  </button>
                )}
              </div>

              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 text-[11px]">
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Responsible Person
                  </span>
                  <input
                    type="text"
                    value={act.responsiblePerson}
                    onChange={(e) =>
                      onUpdateFollowUp(act.id, {
                        responsiblePerson: e.target.value,
                      })
                    }
                    placeholder="e.g. Lead Auditor..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
                    required
                  />
                </div>

                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Target Completion Date
                  </span>
                  <input
                    type="date"
                    value={act.targetDate}
                    onChange={(e) =>
                      onUpdateFollowUp(act.id, { targetDate: e.target.value })
                    }
                    className="h-7 w-full rounded border border-slate-200 bg-white px-1.5 font-mono text-[11px]"
                    required
                  />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
