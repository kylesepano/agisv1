import { RefreshCw } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import {
  AemModal,
  AfrModal,
  DashboardFooter,
  DashboardHero,
  DonutStatusCard,
  ModuleCardsGrid,
  OverdueRecommendationsPanel,
  QuickActionsPanel,
  RecentEngagementsPanel,
  TasksPanel,
  UpcomingActivitiesPanel,
} from "./components";
import {
  dashboardService,
  type CoreDashboardData,
} from "../../../services/dashboardService";

export function AdminDashboardPage() {
  const [isAfrModalOpen, setIsAfrModalOpen] = useState(false);
  const [isAemModalOpen, setIsAemModalOpen] = useState(false);
  const [dashboardData, setDashboardData] = useState<CoreDashboardData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const fetchDashboard = () => {
    setIsLoading(true);
    setLoadError(null);
    dashboardService
      .getCoreDashboard()
      .then((data) => {
        setDashboardData(data);
      })
      .catch((err) => {
        setLoadError(err?.message || "Failed to load dashboard data from database.");
      })
      .finally(() => {
        setIsLoading(false);
      });
  };

  useEffect(() => {
    fetchDashboard();
  }, []);

  const engagementSegments = useMemo(() => {
    if (!dashboardData?.engagementStatus) return [];
    const entries = Object.entries(dashboardData.engagementStatus);
    const colors = ["#3b82f6", "#22c55e", "#f59e0b", "#8b5cf6", "#94a3b8", "#ec4899"];
    return entries.map(([statusKey, val], index) => ({
      name: statusKey
        .replaceAll("_", " ")
        .toLowerCase()
        .replace(/^\w/, (c) => c.toUpperCase()),
      value: Number(val),
      color: colors[index % colors.length],
    }));
  }, [dashboardData]);

  const engagementTotal = useMemo(() => {
    return engagementSegments.reduce((sum, item) => sum + item.value, 0);
  }, [engagementSegments]);

  const recommendationSegments = useMemo(() => {
    if (!dashboardData?.recommendationStatus) return [];
    const raw = dashboardData.recommendationStatus;
    const entries = Array.isArray(raw) ? [] : Object.entries(raw);
    const colors = ["#22c55e", "#3b82f6", "#f59e0b", "#94a3b8", "#8b5cf6"];
    return entries.map(([statusKey, val], index) => ({
      name: statusKey
        .replaceAll("_", " ")
        .toLowerCase()
        .replace(/^\w/, (c) => c.toUpperCase()),
      value: Number(val),
      color: colors[index % colors.length],
    }));
  }, [dashboardData]);

  const recommendationTotal = useMemo(() => {
    return recommendationSegments.reduce((sum, item) => sum + item.value, 0);
  }, [recommendationSegments]);

  if (isLoading && !dashboardData) {
    return (
      <AdminLayout>
        <div className="flex min-h-full flex-col xl:h-full">
          <div className="flex flex-col space-y-2">
            {/* Hero Header stays visible with syncing badge */}
            <DashboardHero isSyncing />

            {/* 6 Module Cards Skeleton */}
            <div
              aria-busy="true"
              aria-label="Loading module summaries"
              className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6"
            >
              {[1, 2, 3, 4, 5, 6].map((idx) => (
                <div
                  key={idx}
                  className="animate-pulse rounded-xl border border-slate-200 bg-white p-3 shadow-xs"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="h-8 w-8 rounded-lg bg-slate-200" />
                    <div className="h-4 w-10 rounded bg-slate-100" />
                  </div>
                  <div className="mt-3 h-3 w-28 rounded bg-slate-200" />
                  <div className="mt-2 h-6 w-14 rounded bg-slate-200" />
                  <div className="mt-2 h-2.5 w-full rounded bg-slate-100" />
                </div>
              ))}
            </div>

            {/* 12-Column Dashboard Cards Grid Skeleton */}
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-12">
              {/* Row 1: Tasks (4), Upcoming Activities (4), Recent Engagements (4) */}
              {[1, 2, 3].map((panel) => (
                <div
                  key={`row1-${panel}`}
                  className={`animate-pulse rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs ${
                    panel === 3 ? "md:col-span-2 xl:col-span-4" : "xl:col-span-4"
                  }`}
                >
                  <div className="mb-3 flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div className="h-4 w-36 rounded bg-slate-200" />
                    <div className="h-4 w-16 rounded bg-slate-100" />
                  </div>
                  <div className="space-y-2.5">
                    {[1, 2, 3, 4].map((row) => (
                      <div
                        key={row}
                        className="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/60 p-2.5"
                      >
                        <div className="min-w-0 flex-1 space-y-1.5">
                          <div className="h-3 w-3/4 rounded bg-slate-200" />
                          <div className="h-2.5 w-1/2 rounded bg-slate-100" />
                        </div>
                        <div className="h-5 w-14 shrink-0 rounded-full bg-slate-200" />
                      </div>
                    ))}
                  </div>
                </div>
              ))}

              {/* Row 2 Left & Center: Two Donut Status Cards (4 cols each) */}
              {[1, 2].map((donut) => (
                <div
                  key={`donut-${donut}`}
                  className="animate-pulse rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs xl:col-span-4"
                >
                  <div className="mb-3 flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div className="h-4 w-44 rounded bg-slate-200" />
                    <div className="h-4 w-16 rounded bg-slate-100" />
                  </div>
                  <div className="flex items-center gap-5 py-3">
                    <div className="mx-auto h-32 w-32 shrink-0 rounded-full border-[14px] border-slate-200 bg-white" />
                    <div className="flex-1 space-y-2.5">
                      {[1, 2, 3, 4].map((legend) => (
                        <div key={legend} className="flex items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <div className="h-2.5 w-2.5 rounded-full bg-slate-200" />
                            <div className="h-2.5 w-20 rounded bg-slate-200" />
                          </div>
                          <div className="h-2.5 w-6 rounded bg-slate-100" />
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ))}

              {/* Row 2 Right 1: Overdue Recommendations (2 cols) */}
              <div className="animate-pulse rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs md:col-span-1 xl:col-span-2">
                <div className="mb-3 border-b border-slate-100 pb-2.5">
                  <div className="h-4 w-28 rounded bg-slate-200" />
                </div>
                <div className="flex flex-col items-center justify-center py-6 space-y-2.5">
                  <div className="h-12 w-12 rounded-xl bg-slate-200" />
                  <div className="h-7 w-12 rounded bg-slate-200" />
                  <div className="h-3 w-24 rounded bg-slate-100" />
                </div>
              </div>

              {/* Row 2 Right 2: Quick Actions (2 cols) */}
              <div className="animate-pulse rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs md:col-span-1 xl:col-span-2">
                <div className="mb-3 border-b border-slate-100 pb-2.5">
                  <div className="h-4 w-24 rounded bg-slate-200" />
                </div>
                <div className="space-y-2">
                  {[1, 2, 3, 4].map((action) => (
                    <div key={action} className="h-8 w-full rounded-lg bg-slate-100" />
                  ))}
                </div>
              </div>
            </div>
          </div>

          <div className="pt-2 xl:mt-auto">
            <DashboardFooter />
          </div>
        </div>
      </AdminLayout>
    );
  }

  if (loadError && !dashboardData) {
    return (
      <AdminLayout>
        <div className="flex min-h-[70vh] flex-col items-center justify-center p-8 text-center">
          <p className="text-sm font-semibold text-rose-600 mb-2">
            {loadError}
          </p>
          <button
            onClick={fetchDashboard}
            className="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700"
          >
            <RefreshCw size={14} />
            Retry
          </button>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout>
      <div className="flex min-h-full flex-col xl:h-full">
        <div className="flex flex-col space-y-2">
          <div className="dashboard-reveal motion-reduce:animate-none">
            <DashboardHero />
          </div>
          <div
            className="dashboard-reveal motion-reduce:animate-none"
            style={{ animationDelay: "70ms" }}
          >
            <ModuleCardsGrid
              modules={dashboardData?.modules}
              onOpenAfr={() => setIsAfrModalOpen(true)}
              onOpenAem={() => setIsAemModalOpen(true)}
            />
          </div>

          {/* 12-Column Dashboard Cards Grid for Pixel-Perfect Vertical Alignment */}
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-12">
            {/* Row 1: My Tasks (4 cols), Upcoming Activities (4 cols), Recent Audit Engagements (4 cols) */}
            <div
              className="dashboard-reveal xl:col-span-4 motion-reduce:animate-none"
              style={{ animationDelay: "120ms" }}
            >
              <TasksPanel tasks={dashboardData?.tasks} />
            </div>
            <div
              className="dashboard-reveal xl:col-span-4 motion-reduce:animate-none"
              style={{ animationDelay: "170ms" }}
            >
              <UpcomingActivitiesPanel />
            </div>
            <div
              className="dashboard-reveal md:col-span-2 xl:col-span-4 motion-reduce:animate-none"
              style={{ animationDelay: "220ms" }}
            >
              <RecentEngagementsPanel engagements={dashboardData?.recentEngagements} />
            </div>

            {/* Row 2: Audit Engagement Status (4 cols), Recommendation Status (4 cols), Overdue Recs (2 cols), Quick Actions (2 cols) */}
            <div
              className="dashboard-reveal xl:col-span-4 motion-reduce:animate-none"
              style={{ animationDelay: "270ms" }}
            >
              <DonutStatusCard
                title="Audit Engagement Status"
                action="View Report"
                data={engagementSegments}
                total={engagementTotal}
              />
            </div>
            <div
              className="dashboard-reveal xl:col-span-4 motion-reduce:animate-none"
              style={{ animationDelay: "320ms" }}
            >
              <DonutStatusCard
                title="Recommendation Status (CMS)"
                action="View Report"
                data={recommendationSegments}
                total={recommendationTotal}
              />
            </div>
            <div
              className="dashboard-reveal md:col-span-1 xl:col-span-2 motion-reduce:animate-none"
              style={{ animationDelay: "370ms" }}
            >
              <OverdueRecommendationsPanel count={dashboardData?.overdueRecommendations} />
            </div>
            <div
              className="dashboard-reveal md:col-span-1 xl:col-span-2 motion-reduce:animate-none"
              style={{ animationDelay: "420ms" }}
            >
              <QuickActionsPanel actions={dashboardData?.quickActions} />
            </div>
          </div>
        </div>

        <div className="pt-2 xl:mt-auto">
          <DashboardFooter />
        </div>

        <AfrModal
          isOpen={isAfrModalOpen}
          onClose={() => setIsAfrModalOpen(false)}
        />

        <AemModal
          isOpen={isAemModalOpen}
          onClose={() => setIsAemModalOpen(false)}
        />
      </div>
    </AdminLayout>
  );
}
