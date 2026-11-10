import { overdueRecommendations } from "../data";
import { DashboardPanel } from "./DashboardPanel";

type OverdueRecommendationsPanelProps = {
  count?: number;
};

export function OverdueRecommendationsPanel({ count }: OverdueRecommendationsPanelProps) {
  const displayCount = count !== undefined ? count : 4;

  return (
    <DashboardPanel title="Overdue Recommendations">
      <div className="flex h-full flex-col">
        <p className="text-[1.65rem] font-bold leading-none text-red-600">{displayCount}</p>
        <p className="mt-0.5 text-[11px] font-medium text-slate-400">Overdue Items</p>
        <div className="mt-3 space-y-2.5">
          {displayCount === 0 ? (
            <p className="text-[11px] text-slate-400">No overdue items</p>
          ) : (
            overdueRecommendations.map(([code, office, days]) => (
              <div key={code} className="flex items-center justify-between text-[11px]">
                <div className="flex min-w-0 items-center gap-1.5">
                  <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-red-600" />
                  <span className="font-semibold text-slate-700">{code}</span>
                  <span className="text-slate-400">{office}</span>
                </div>
                <span className="shrink-0 font-semibold text-red-500">{days}</span>
              </div>
            ))
          )}
        </div>
      </div>
    </DashboardPanel>
  );
}
