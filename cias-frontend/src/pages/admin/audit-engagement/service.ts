import { getAuditEngagementSummary } from "./audit-engagement-utils";
import { api } from "../../../services/api";
import type { AuditEngagementRecord } from "./audit-engagement-types";

export function mapBackendEngagementToRecord(backend: any): AuditEngagementRecord {
  const leadMember =
    backend.teamMembers?.find(
      (m: any) =>
        m.assignmentRoleCode === "TEAM_LEAD" ||
        m.assignmentRoleCode === "LEAD_AUDITOR"
    ) || backend.teamMembers?.[0];

  const primaryOffice =
    backend.offices?.find((o: any) => o.isPrimary) || backend.offices?.[0];
  const primaryArea = backend.auditAreas?.[0];

  const phaseRaw = backend.phase || "PLANNING";
  const phase =
    phaseRaw.charAt(0).toUpperCase() + phaseRaw.slice(1).toLowerCase();

  const statusRaw = backend.status || "ACTIVE";
  const status =
    statusRaw.replace(/_/g, " ").replace(/\b\w/g, (c: string) => c.toUpperCase());

  const auditType =
    backend.auditType?.label ||
    backend.auditType?.code ||
    "Compliance Audit";

  return {
    id: String(backend.id),
    code: backend.engagementCode || `AEM-${backend.id}`,
    title: backend.title || "Audit Engagement",
    auditArea: primaryArea?.name || backend.title || "Audit Area",
    subject: primaryArea?.name || backend.title,
    office: primaryOffice?.name || primaryOffice?.code || "City Office",
    auditType: auditType as any,
    riskRating: "Medium",
    annualPlanRef: backend.iapPlanId ? `IAP-${backend.iapPlanId}` : "N/A",
    leadAuditor: leadMember?.user?.name || "Unassigned",
    auditTeam:
      backend.teamMembers
        ?.map((m: any) => m.user?.name)
        .filter(Boolean)
        .join(", ") || "Audit Team",
    startDate:
      backend.plannedStartDate ||
      backend.periodCoveredStartDate ||
      new Date().toISOString().split("T")[0],
    endDate:
      backend.plannedEndDate ||
      backend.periodCoveredEndDate ||
      new Date().toISOString().split("T")[0],
    status: (status === "Active" ? "Active" : status) as any,
    phase: phase,
    health: "On Track",
    objective: backend.objectives || "",
    scope: backend.scope || "",
    remarks: "",
    updatedBy: backend.updater?.name || "System",
    lastUpdated: backend.updatedAt
      ? new Date(backend.updatedAt).toLocaleDateString("en-US", {
          month: "short",
          day: "numeric",
          year: "numeric",
        })
      : "Today",
    year: String(backend.auditYear || new Date().getFullYear()),
  };
}

export type AemsDashboardEngagementStage = {
  key: string;
  label: string;
  status: string;
  percent: number;
  detail?: string;
};

export type AemsDashboardEngagement = {
  id: number;
  engagementCode: string;
  title: string;
  status: string;
  phase: string;
  health: string;
  overallProgress: number;
  plannedStartDate?: string | null;
  plannedEndDate?: string | null;
  expectedReportDate?: string | null;
  alerts?: string[];
  stages?: AemsDashboardEngagementStage[];
};

export type AemsDashboardSummary = {
  asOf?: string;
  cards: {
    activeEngagements: number;
    engagementsInPlanning: number;
    engagementsInFieldwork: number;
    overdueProcedures: number;
    workingPapersAwaitingReview: number;
    findingsAwaitingReview: number;
    reportsPendingApproval: number;
    engagementsReadyForClosure: number;
    reviewNotesAwaitingReview: number;
    openTasks: number;
    escalationCandidates: number;
  };
  engagements: AemsDashboardEngagement[];
};

let cachedEngagements: AuditEngagementRecord[] | null = null;
let inflightEngagementsPromise: Promise<AuditEngagementRecord[]> | null = null;

let cachedAemsDashboard: { data: AemsDashboardSummary; fetchedAt: number } | null = null;
let inflightAemsDashboardPromise: Promise<AemsDashboardSummary | null> | null = null;
const AEMS_DASHBOARD_CACHE_TTL_MS = 60_000;

export function clearEngagementsCache() {
  cachedEngagements = null;
  cachedAemsDashboard = null;
}

if (typeof window !== "undefined") {
  window.addEventListener("cias:auth-cleared", clearEngagementsCache);
}

export function getCachedAemsDashboard(): AemsDashboardSummary | null {
  return cachedAemsDashboard?.data ?? null;
}

export async function fetchAemsDashboardSummary(
  forceRefresh = false,
): Promise<AemsDashboardSummary | null> {
  if (
    !forceRefresh &&
    cachedAemsDashboard &&
    Date.now() - cachedAemsDashboard.fetchedAt < AEMS_DASHBOARD_CACHE_TTL_MS
  ) {
    return cachedAemsDashboard.data;
  }

  if (inflightAemsDashboardPromise) {
    return inflightAemsDashboardPromise;
  }

  inflightAemsDashboardPromise = (async () => {
    try {
      const data = await api.get<AemsDashboardSummary>(
        "/api/aems/dashboard?perPage=50",
        { timeoutMs: 45000 },
      );
      if (data && data.cards) {
        cachedAemsDashboard = { data, fetchedAt: Date.now() };
        return data;
      }
    } catch (error) {
      console.warn("Could not load AEMS dashboard summary:", error);
    } finally {
      inflightAemsDashboardPromise = null;
    }
    return cachedAemsDashboard?.data ?? null;
  })();

  return inflightAemsDashboardPromise;
}

export function getAuditEngagementRecords(): AuditEngagementRecord[] {
  return cachedEngagements || [];
}

export async function fetchAuditEngagementRecords(forceRefresh = false): Promise<AuditEngagementRecord[]> {
  if (!forceRefresh && cachedEngagements && cachedEngagements.length > 0) {
    return cachedEngagements;
  }

  if (inflightEngagementsPromise) {
    return inflightEngagementsPromise;
  }

  inflightEngagementsPromise = (async () => {
    try {
      const data = await api.get<any>("/api/aems/engagements?perPage=100");
      const rawList = Array.isArray(data)
        ? data
        : data?.engagements || data?.data?.engagements || [];

      if (Array.isArray(rawList) && rawList.length > 0) {
        const mapped = rawList.map(mapBackendEngagementToRecord);
        cachedEngagements = mapped;
        return mapped;
      }
    } catch (error) {
      console.warn("Could not fetch engagements from backend:", error);
    } finally {
      inflightEngagementsPromise = null;
    }

    return cachedEngagements || [];
  })();

  return inflightEngagementsPromise;
}

export function getAuditEngagementSummaryMetrics() {
  const records = getAuditEngagementRecords();
  return getAuditEngagementSummary(records);
}
