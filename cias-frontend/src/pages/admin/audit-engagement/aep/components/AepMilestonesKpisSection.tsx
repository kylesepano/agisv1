import {
  Calendar,
  Plus,
  Target,
  Trash2,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import type { AepKpiMetric, AepMilestone } from "../aep-types";

type AepMilestonesKpisSectionProps = {
  milestones: AepMilestone[];
  kpis: AepKpiMetric[];
  onUpdateMilestone: (id: string, updates: Partial<AepMilestone>) => void;
  onAddMilestone: () => void;
  onRemoveMilestone: (id: string) => void;
  onUpdateKpi: (id: string, updates: Partial<AepKpiMetric>) => void;
  onAddKpi: () => void;
  onRemoveKpi: (id: string) => void;
};

export function AepMilestonesKpisSection({
  milestones,
  kpis,
  onUpdateMilestone,
  onAddMilestone,
  onRemoveMilestone,
  onUpdateKpi,
  onAddKpi,
  onRemoveKpi,
}: AepMilestonesKpisSectionProps) {
  return (
    <div className="space-y-3">
      {/* 1. Engagement Key Milestones */}
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
          <div className="flex items-center gap-2">
            <Calendar className="h-4 w-4 text-blue-600" />
            <h3 className="text-[13px] font-bold text-slate-800">
              Key Engagement Milestones & Schedule (§7)
            </h3>
            <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
              {milestones.length} Phases
            </span>
          </div>

          <Button
            type="button"
            variant="outline"
            onClick={onAddMilestone}
            className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 cursor-pointer"
          >
            <Plus size={12} />
            <span>Add Milestone</span>
          </Button>
        </div>

        <div className="space-y-2 text-xs">
          {milestones.map((m, idx) => (
            <div
              key={m.id}
              className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-2 transition hover:bg-slate-50"
            >
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
                <div className="flex items-center gap-2 flex-1 min-w-[220px]">
                  <span className="grid h-5 w-5 shrink-0 place-items-center rounded bg-blue-600 text-[10px] font-bold text-white font-mono">
                    {idx + 1}
                  </span>
                  <input
                    type="text"
                    value={m.phaseTitle}
                    onChange={(e) =>
                      onUpdateMilestone(m.id, { phaseTitle: e.target.value })
                    }
                    placeholder="Milestone Phase Title..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 font-bold text-xs text-slate-900"
                    required
                  />
                </div>

                <div className="flex items-center gap-2">
                  <span className="text-[10px] text-slate-400 font-mono">{m.id}</span>
                  {milestones.length > 2 && (
                    <button
                      type="button"
                      onClick={() => onRemoveMilestone(m.id)}
                      className="text-slate-300 hover:text-rose-600 p-1 transition cursor-pointer"
                      title="Remove milestone"
                    >
                      <Trash2 size={13} />
                    </button>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-1 gap-2 sm:grid-cols-4 text-[11px]">
                {/* Start Date */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Target Start
                  </span>
                  <input
                    type="date"
                    value={m.targetStartDate}
                    onChange={(e) =>
                      onUpdateMilestone(m.id, { targetStartDate: e.target.value })
                    }
                    className="h-7 w-full rounded border border-slate-200 bg-white px-1.5 font-mono text-[11px]"
                    required
                  />
                </div>

                {/* End Date */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Target End
                  </span>
                  <input
                    type="date"
                    value={m.targetEndDate}
                    onChange={(e) =>
                      onUpdateMilestone(m.id, { targetEndDate: e.target.value })
                    }
                    className="h-7 w-full rounded border border-slate-200 bg-white px-1.5 font-mono text-[11px]"
                    required
                  />
                </div>

                {/* Lead */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Responsible Lead
                  </span>
                  <input
                    type="text"
                    value={m.responsibleLead}
                    onChange={(e) =>
                      onUpdateMilestone(m.id, { responsibleLead: e.target.value })
                    }
                    placeholder="Lead name..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
                    required
                  />
                </div>

                {/* Deliverable */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Key Deliverable
                  </span>
                  <input
                    type="text"
                    value={m.deliverable}
                    onChange={(e) =>
                      onUpdateMilestone(m.id, { deliverable: e.target.value })
                    }
                    placeholder="Deliverable artifact..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
                    required
                  />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 2. Engagement Key Performance Indicators (KPIs) */}
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
          <div className="flex items-center gap-2">
            <Target className="h-4 w-4 text-blue-600" />
            <h3 className="text-[13px] font-bold text-slate-800">
              Engagement KPIs & Performance Targets (§7)
            </h3>
            <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
              {kpis.length} Metrics
            </span>
          </div>

          <Button
            type="button"
            variant="outline"
            onClick={onAddKpi}
            className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 cursor-pointer"
          >
            <Plus size={12} />
            <span>Add KPI Metric</span>
          </Button>
        </div>

        <div className="space-y-2 text-xs">
          {kpis.map((kpi, idx) => (
            <div
              key={kpi.id}
              className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-2 transition hover:bg-slate-50"
            >
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
                <div className="flex items-center gap-2 flex-1 min-w-[220px]">
                  <span className="grid h-5 w-5 shrink-0 place-items-center rounded bg-emerald-600 text-[10px] font-bold text-white font-mono">
                    K{idx + 1}
                  </span>
                  <input
                    type="text"
                    value={kpi.metricName}
                    onChange={(e) =>
                      onUpdateKpi(kpi.id, { metricName: e.target.value })
                    }
                    placeholder="KPI Metric Name (e.g. Sampling Coverage Rate)..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 font-bold text-xs text-slate-900"
                    required
                  />
                </div>

                <div className="flex items-center gap-2">
                  <span className="text-[10px] text-slate-400 font-mono">{kpi.id}</span>
                  {kpis.length > 2 && (
                    <button
                      type="button"
                      onClick={() => onRemoveKpi(kpi.id)}
                      className="text-slate-300 hover:text-rose-600 p-1 transition cursor-pointer"
                      title="Remove KPI"
                    >
                      <Trash2 size={13} />
                    </button>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 text-[11px]">
                {/* Target Value */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Target Value / Benchmark
                  </span>
                  <input
                    type="text"
                    value={kpi.targetValue}
                    onChange={(e) =>
                      onUpdateKpi(kpi.id, { targetValue: e.target.value })
                    }
                    placeholder="e.g. 100% verification..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px] font-semibold text-emerald-800"
                    required
                  />
                </div>

                {/* Measurement Method */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Measurement Methodology
                  </span>
                  <input
                    type="text"
                    value={kpi.measurementMethod}
                    onChange={(e) =>
                      onUpdateKpi(kpi.id, { measurementMethod: e.target.value })
                    }
                    placeholder="How this metric will be measured..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
                    required
                  />
                </div>

                {/* Responsible Role */}
                <div>
                  <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                    Accountable Role
                  </span>
                  <input
                    type="text"
                    value={kpi.responsibleRole}
                    onChange={(e) =>
                      onUpdateKpi(kpi.id, { responsibleRole: e.target.value })
                    }
                    placeholder="e.g. Lead Auditor..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px]"
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
