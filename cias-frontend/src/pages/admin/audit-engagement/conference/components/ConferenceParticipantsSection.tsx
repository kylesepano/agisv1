import {
  Plus,
  Trash2,
  Users,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import type {
  AttendanceStatus,
  ConferenceMode,
  ConferenceParticipant,
} from "../conference-types";

type ConferenceParticipantsSectionProps = {
  participants: ConferenceParticipant[];
  onUpdateParticipant: (
    id: string,
    updates: Partial<ConferenceParticipant>,
  ) => void;
  onAddParticipant: () => void;
  onRemoveParticipant: (id: string) => void;
};

export function ConferenceParticipantsSection({
  participants,
  onUpdateParticipant,
  onAddParticipant,
  onRemoveParticipant,
}: ConferenceParticipantsSectionProps) {
  const ciasCount = participants.filter((p) =>
    p.office.toLowerCase().includes("audit") || p.office.toLowerCase().includes("cias"),
  ).length;
  const auditeeCount = participants.length - ciasCount;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Users className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Conference Participants Roster (§7 Conference Participant)
          </h3>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
            {participants.length} Total ({ciasCount} CIAS • {auditeeCount} Auditee)
          </span>
        </div>

        <Button
          type="button"
          variant="outline"
          onClick={onAddParticipant}
          className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 cursor-pointer"
        >
          <Plus size={12} />
          <span>Add Participant</span>
        </Button>
      </div>

      <div className="space-y-2 text-xs">
        {participants.map((p, idx) => (
          <div
            key={p.id}
            className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-2 transition hover:bg-slate-50"
          >
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
              <div className="flex items-center gap-2 flex-1 min-w-[200px]">
                <span className="grid h-5 w-5 shrink-0 place-items-center rounded bg-blue-600 text-[10px] font-bold text-white font-mono">
                  {idx + 1}
                </span>
                <input
                  type="text"
                  value={p.name}
                  onChange={(e) =>
                    onUpdateParticipant(p.id, { name: e.target.value })
                  }
                  placeholder="Participant Full Name..."
                  className="h-7 w-full rounded border border-slate-200 bg-white px-2 font-bold text-xs text-slate-900"
                  required
                />
              </div>

              <div className="flex items-center gap-2">
                <span className="text-[10px] text-slate-400 font-mono">{p.id}</span>
                {participants.length > 2 && (
                  <button
                    type="button"
                    onClick={() => onRemoveParticipant(p.id)}
                    className="text-slate-300 hover:text-rose-600 p-1 transition cursor-pointer"
                    title="Remove participant"
                  >
                    <Trash2 size={13} />
                  </button>
                )}
              </div>
            </div>

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-4 text-[11px]">
              {/* Office */}
              <div>
                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                  Office / Organization
                </span>
                <input
                  type="text"
                  value={p.office}
                  onChange={(e) =>
                    onUpdateParticipant(p.id, { office: e.target.value })
                  }
                  placeholder="e.g. CIAS, CGSO..."
                  className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
                  required
                />
              </div>

              {/* Position */}
              <div>
                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                  Position / Title
                </span>
                <input
                  type="text"
                  value={p.position}
                  onChange={(e) =>
                    onUpdateParticipant(p.id, { position: e.target.value })
                  }
                  placeholder="e.g. Lead Auditor, Department Head..."
                  className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
                  required
                />
              </div>

              {/* Attendance Status */}
              <div>
                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                  Attendance Status
                </span>
                <select
                  value={p.attendanceStatus}
                  onChange={(e) =>
                    onUpdateParticipant(p.id, {
                      attendanceStatus: e.target.value as AttendanceStatus,
                    })
                  }
                  className="h-7 w-full rounded border border-slate-200 bg-white px-1.5 text-[11px] font-semibold"
                >
                  <option value="Confirmed">Confirmed</option>
                  <option value="Attended">Attended</option>
                  <option value="Excused">Excused</option>
                  <option value="Absent">Absent</option>
                </select>
              </div>

              {/* Mode */}
              <div>
                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                  Participation Mode
                </span>
                <select
                  value={p.mode}
                  onChange={(e) =>
                    onUpdateParticipant(p.id, {
                      mode: e.target.value as ConferenceMode,
                    })
                  }
                  className="h-7 w-full rounded border border-slate-200 bg-white px-1.5 text-[11px]"
                >
                  <option value="In-person">In-person</option>
                  <option value="Virtual">Virtual</option>
                  <option value="Hybrid">Hybrid</option>
                </select>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
