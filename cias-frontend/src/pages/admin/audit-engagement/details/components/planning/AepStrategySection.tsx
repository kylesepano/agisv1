import {
  ArrowRight,
  Calendar,
  Clock,
  Compass,
  FileCheck2,
  FileText,
  Target,
} from "lucide-react";
import { Button } from "../../../../../../components/ui/button";
import { navigateTo } from "../../../../../../routes";
import type { AepStrategyDetails } from "./planning-types";

type AepStrategySectionProps = {
  aep: AepStrategyDetails;
  engagementNumber?: string;
};

export function AepStrategySection({
  aep,
  engagementNumber = "AEMS-2026-010",
}: AepStrategySectionProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Compass className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Audit Engagement Plan (AEP) & Engagement KPIs (SCR-222)
          </h3>
          <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
            {aep.aepNumber} {aep.version}
          </span>
        </div>

        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={() =>
              navigateTo(`/admin/audit-engagement/${engagementNumber}/planning/aep`)
            }
            className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 border-blue-200 hover:bg-blue-50 cursor-pointer"
          >
            <FileText size={12} />
            <span>Open Full AEP (SCR-222)</span>
            <ArrowRight size={11} />
          </Button>
          <span className="text-[10px] text-slate-400 font-mono hidden sm:inline">
            UID-200 §5.5, §8.3
          </span>
        </div>
      </div>

      <div className="space-y-2.5 text-xs">
        {/* Strategy Summary */}
        <div className="space-y-1">
          <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
            Overall Audit Strategy & Fieldwork Approach
          </span>
          <p className="text-[11px] text-slate-700 leading-relaxed rounded-md bg-slate-50/70 p-2.5 border border-slate-100">
            {aep.auditStrategySummary}
          </p>
        </div>

        {/* 4 Stat Indicators: Man-Days, Fieldwork Window, Reporting Target, KPI */}
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
          <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2 space-y-0.5">
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
              Planned Man-Days
            </span>
            <div className="flex items-center gap-1 text-slate-900 font-mono font-bold text-sm">
              <Clock size={13} className="text-blue-600" />
              <span>{aep.plannedManDays} Days</span>
            </div>
            <span className="text-[9px] text-slate-500 block">3 Auditors Allocated</span>
          </div>

          <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2 space-y-0.5">
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
              Fieldwork Schedule
            </span>
            <div className="flex items-center gap-1 text-slate-900 font-mono font-bold text-[11px]">
              <Calendar size={13} className="text-indigo-600 shrink-0" />
              <span>{aep.fieldworkStartDate} ~ {aep.fieldworkEndDate}</span>
            </div>
            <span className="text-[9px] text-slate-500 block">Active On-Site Testing</span>
          </div>

          <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2 space-y-0.5">
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
              Draft Report Target
            </span>
            <div className="flex items-center gap-1 text-slate-900 font-mono font-bold text-[11px]">
              <FileCheck2 size={13} className="text-emerald-600 shrink-0" />
              <span>{aep.reportingTargetDate}</span>
            </div>
            <span className="text-[9px] text-slate-500 block">Transmission to Auditee</span>
          </div>

          <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2 space-y-0.5">
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
              Key KPI / Target Scope
            </span>
            <div className="flex items-center gap-1 text-slate-900 font-medium text-[10px] leading-tight">
              <Target size={13} className="text-rose-600 shrink-0" />
              <span className="line-clamp-2">{aep.keyKpiTarget}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
