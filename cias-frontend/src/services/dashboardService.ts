import { api } from "./api";

export interface DashboardModule {
  key: string;
  code: string;
  label: string;
  value: number | null;
  note: string;
  path: string;
  tone: string;
  available?: boolean;
}

export interface DashboardTask {
  title: string;
  code: string;
  due?: string | null;
  path: string;
}

export interface DashboardActivity {
  title?: string;
  code?: string;
  date?: string;
  time?: string;
  [key: string]: unknown;
}

export interface DashboardRecentEngagement {
  number: string;
  office?: string | null;
  status: string;
  phase: string;
  updatedAt?: string | null;
  path: string;
}

export interface DashboardQuickAction {
  label: string;
  path: string;
  permission?: string;
}

export interface CoreDashboardData {
  asOf: string;
  scope: {
    office: string | number | null;
    engagement: string;
  };
  modules: DashboardModule[];
  tasks: DashboardTask[];
  upcomingActivities: DashboardActivity[];
  recentEngagements: DashboardRecentEngagement[];
  engagementStatus: Record<string, number>;
  recommendationStatus: Record<string, number> | Array<unknown>;
  overdueRecommendations: number;
  quickActions: DashboardQuickAction[];
}

let inFlightDashboardPromise: Promise<CoreDashboardData> | null = null;

export const dashboardService = {
  getCoreDashboard: async (): Promise<CoreDashboardData> => {
    if (inFlightDashboardPromise) {
      return inFlightDashboardPromise;
    }

    inFlightDashboardPromise = api
      .get<CoreDashboardData>("/api/dashboard")
      .finally(() => {
        inFlightDashboardPromise = null;
      });

    return inFlightDashboardPromise;
  },
};

