import { DonutStatusCard } from "./DonutStatusCard";
import { OverdueRecommendationsPanel } from "./OverdueRecommendationsPanel";
import { QuickActionsPanel } from "./QuickActionsPanel";
import { engagementStatus, recommendationStatus } from "../data";

export function StatusOverviewSection() {
  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="lg:col-span-4">
        <DonutStatusCard
          title="Audit Engagement Status"
          action="View Report"
          data={engagementStatus}
          total={18}
        />
      </div>
      <div className="lg:col-span-4">
        <DonutStatusCard
          title="Recommendation Status (CMS)"
          action="View Report"
          data={recommendationStatus}
          total={21}
        />
      </div>
      <div className="lg:col-span-2 sm:col-span-6">
        <OverdueRecommendationsPanel />
      </div>
      <div className="lg:col-span-2 sm:col-span-6">
        <QuickActionsPanel />
      </div>
    </div>
  );
}
