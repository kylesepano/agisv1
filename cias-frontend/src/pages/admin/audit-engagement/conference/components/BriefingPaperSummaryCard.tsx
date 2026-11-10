import {
  Calendar,
  Compass,
  FileText,
  Shield,
  Users,
} from "lucide-react";
import type { BriefingPaperSummary } from "../conference-types";

type BriefingPaperSummaryCardProps = {
  briefingPaper: BriefingPaperSummary;
};

export function BriefingPaperSummaryCard({
  briefingPaper,
}: BriefingPaperSummaryCardProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <FileText className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Synthesized Briefing Paper & Presentation Baseline (§7)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          Auto-referenced from SCR-213, SCR-214 & SCR-222
        </span>
      </div>

      <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 text-xs">
        {/* AEO Authority & Mandate */}
        <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1">
          <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
            <Shield size={11} className="text-blue-600" />
            Issued AEO Authority (SCR-214)
          </span>
          <div className="font-mono font-bold text-blue-800 text-[11px]">
            {briefingPaper.aeoRef}
          </div>
          <p className="text-[11px] text-slate-600 leading-snug">
            {briefingPaper.authorityLegalBasis}
          </p>
        </div>

        {/* Assigned Audit Team */}
        <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1">
          <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
            <Users size={11} className="text-emerald-600" />
            Assigned Audit Team (SCR-213)
          </span>
          <p className="text-[11px] text-slate-800 font-medium leading-snug">
            {briefingPaper.assignedTeamSummary}
          </p>
        </div>

        {/* Objectives & Scope */}
        <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1">
          <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
            <Compass size={11} className="text-indigo-600" />
            Objectives & Scope Parameters (SCR-212 / AEP)
          </span>
          <p className="text-[11px] text-slate-700 leading-snug">
            {briefingPaper.objectivesScopeSummary}
          </p>
        </div>

        {/* Fieldwork Schedule */}
        <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1">
          <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
            <Calendar size={11} className="text-purple-600" />
            Fieldwork & Reporting Window (SCR-222)
          </span>
          <p className="text-[11px] text-slate-800 font-medium leading-snug">
            {briefingPaper.fieldworkTimeline}
          </p>
          <span className="text-[10px] text-slate-400 block">
            45 Man-Days Fieldwork Protocol
          </span>
        </div>
      </div>
    </div>
  );
}
