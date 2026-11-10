import {
  Calendar,
  Clock,
  Globe,
  ListOrdered,
  MapPin,
} from "lucide-react";
import type {
  ConferenceMode,
  ConferenceStatus,
  ConferenceType,
} from "../conference-types";

type ConferenceScheduleAgendaSectionProps = {
  type: ConferenceType;
  status: ConferenceStatus;
  scheduledDate: string;
  scheduledTime: string;
  venueOrLink: string;
  mode: ConferenceMode;
  agendaText: string;
  onChangeType: (type: ConferenceType) => void;
  onChangeStatus: (status: ConferenceStatus) => void;
  onChangeDate: (date: string) => void;
  onChangeTime: (time: string) => void;
  onChangeVenue: (venue: string) => void;
  onChangeMode: (mode: ConferenceMode) => void;
  onChangeAgenda: (agenda: string) => void;
};

export function ConferenceScheduleAgendaSection({
  type,
  status,
  scheduledDate,
  scheduledTime,
  venueOrLink,
  mode,
  agendaText,
  onChangeType,
  onChangeStatus,
  onChangeDate,
  onChangeTime,
  onChangeVenue,
  onChangeMode,
  onChangeAgenda,
}: ConferenceScheduleAgendaSectionProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Calendar className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Conference Schedule, Logistics & Agenda (§7)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          MDS-200 §7 Conference Entity
        </span>
      </div>

      <div className="space-y-3 text-xs">
        {/* Type & Status Selectors */}
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Conference Type
            </label>
            <select
              value={type}
              onChange={(e) => onChangeType(e.target.value as ConferenceType)}
              className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900"
            >
              <option value="Entry Conference">Entry Conference (Phase 3 Initiation)</option>
              <option value="Exit Conference">Exit Conference (Phase 7 Reporting)</option>
            </select>
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Conference Status
            </label>
            <select
              value={status}
              onChange={(e) => onChangeStatus(e.target.value as ConferenceStatus)}
              className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900"
            >
              <option value="Scheduled">Scheduled (Upcoming)</option>
              <option value="Conducted">Conducted (Meeting Complete)</option>
              <option value="Documented">Documented (Minutes & Agreements Finalized)</option>
            </select>
          </div>
        </div>

        {/* Date, Time, Mode & Venue */}
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1 flex items-center gap-1">
              <Calendar size={11} className="text-slate-400" />
              <span>Date</span>
            </label>
            <input
              type="date"
              value={scheduledDate}
              onChange={(e) => onChangeDate(e.target.value)}
              className="h-8 w-full rounded border border-slate-200 bg-white px-2 font-mono text-xs text-slate-900"
              required
            />
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1 flex items-center gap-1">
              <Clock size={11} className="text-slate-400" />
              <span>Time</span>
            </label>
            <input
              type="text"
              value={scheduledTime}
              onChange={(e) => onChangeTime(e.target.value)}
              placeholder="e.g. 09:30 AM"
              className="h-8 w-full rounded border border-slate-200 bg-white px-2 font-mono text-xs text-slate-900"
              required
            />
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1 flex items-center gap-1">
              <Globe size={11} className="text-slate-400" />
              <span>Meeting Mode</span>
            </label>
            <select
              value={mode}
              onChange={(e) => onChangeMode(e.target.value as ConferenceMode)}
              className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900"
            >
              <option value="In-person">In-person</option>
              <option value="Virtual">Virtual</option>
              <option value="Hybrid">Hybrid</option>
            </select>
          </div>
        </div>

        {/* Venue or Link */}
        <div>
          <label className="text-[11px] font-semibold text-slate-700 block mb-1 flex items-center gap-1">
            <MapPin size={11} className="text-slate-400" />
            <span>Venue / Meeting Link</span>
          </label>
          <input
            type="text"
            value={venueOrLink}
            onChange={(e) => onChangeVenue(e.target.value)}
            placeholder="e.g. CGSO Conference Room, 3rd Floor City Hall / MS Teams Link..."
            className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs text-slate-900"
            required
          />
        </div>

        {/* Agenda */}
        <div className="space-y-1 pt-1 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-bold text-slate-800 flex items-center gap-1">
              <ListOrdered size={12} className="text-blue-600" />
              <span>Conference Agenda Topics</span>
            </label>
            <span className="text-[10px] text-slate-400">
              Structured discussion points
            </span>
          </div>
          <textarea
            rows={4}
            value={agendaText}
            onChange={(e) => onChangeAgenda(e.target.value)}
            placeholder="List the agenda topics for this conference..."
            className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 font-mono leading-relaxed focus:border-blue-500 focus:outline-hidden"
            required
          />
        </div>
      </div>
    </div>
  );
}
