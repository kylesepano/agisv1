import { api, ApiError } from "../../../../services/api";
import { clearEngagementsCache, fetchAuditEngagementRecords } from "../service";

export type BackendOffice = {
  id: number;
  code: string;
  name: string;
  acronym?: string | null;
  head?: {
    id: number;
    employeeId?: string;
    name: string;
    position?: string | null;
  } | null;
  auditAreas?: Array<{
    id: number;
    code: string;
    name: string;
    description?: string | null;
  }>;
  isActive?: boolean;
};

export type BackendAuditArea = {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  responsibleOfficeId?: number | null;
  isActive?: boolean;
  isArchived?: boolean;
  offices?: Array<{
    id: number;
    code: string;
    name: string;
  }>;
};

export type BackendUser = {
  id: number;
  employeeId: string;
  name: string;
  position?: string | null;
  officeId?: number | null;
  isOfficeHead?: boolean;
  isActive?: boolean;
  role?: {
    id: number;
    code: string;
    name: string;
  } | null;
};

export type BackendAuditTypeOption = {
  id: number;
  code: string;
  label: string;
  description?: string | null;
  isActive?: boolean;
};

export type BackendIapImportOption = {
  id: number;
  engagementCode: string;
  title: string;
  plan: {
    id: number;
    planCode: string;
    title: string;
    fiscalYear: number;
    status: string;
    revisionNumber?: number;
    approvedAt?: string | null;
    approvedBy?: {
      id: number;
      name: string;
    } | null;
    periodStart?: string | null;
    periodEnd?: string | null;
  };
  offices: Array<{ id: number; code: string; name: string }>;
  auditAreas: Array<{ id: number; code: string; name: string }>;
  auditFocuses: Array<{ id: number; code: string; name: string; audit_area_id?: number }>;
  auditType?: { id: number; code: string; label: string } | null;
  objectives?: string | null;
  scope?: string | null;
  background?: string | null;
  exclusions?: string | null;
  plannedStartDate?: string | null;
  plannedEndDate?: string | null;
  expectedReportDate?: string | null;
  plannedPersonDays?: number | null;
};

export type CreateEngagementBootstrapData = {
  iapEngagements: BackendIapImportOption[];
  offices: BackendOffice[];
  auditAreas: BackendAuditArea[];
  users: BackendUser[];
  auditTypes: BackendAuditTypeOption[];
  existingCount: number;
  canCreateEngagement: boolean;
};

export type InitialTeamPlanPayload = {
  departmentHeadId?: number | null;
  teamLeaderId?: number | null;
  teamMemberIds?: number[];
  supportStaffIds?: number[];
  aeoReference?: string | null;
  aeoDate?: string | null;
};

export type ImportIapEngagementPayload = {
  iapPlanEngagementId: number;
  engagementCode?: string;
  initialTeamPlan?: InitialTeamPlanPayload;
};

export type CreateSpecialEngagementPayload = {
  title: string;
  specialAuthorityReference: string;
  specialAuthorityTypeCode?: string;
  specialAuthorityClass?: "SPECIAL" | "EMERGENCY";
  specialAuthorityDate: string;
  specialAuthorityReceivedDate?: string;
  specialAuthorityApprovedBy: number;
  auditTypeId?: number;
  auditTypeIds?: number[];
  requestingOfficeId?: number;
  auditYear?: number;
  objectives?: string;
  scope?: string;
  periodCoveredStartDate?: string;
  periodCoveredEndDate?: string;
  plannedStartDate: string;
  plannedEndDate: string;
  plannedPersonDays: number;
  officeIds?: number[];
  auditAreaIds?: number[];
  initialTeamPlan?: InitialTeamPlanPayload;
};

function appendFormDataValue(formData: FormData, key: string, value: unknown) {
  if (value === undefined || value === null || value === "") {
    return;
  }
  if (Array.isArray(value)) {
    value.forEach((item, index) => {
      appendFormDataValue(formData, `${key}[${index}]`, item);
    });
    return;
  }
  if (typeof value === "object" && !(value instanceof File) && !(value instanceof Blob)) {
    Object.entries(value as Record<string, unknown>).forEach(([subKey, subVal]) => {
      appendFormDataValue(formData, `${key}[${subKey}]`, subVal);
    });
    return;
  }
  formData.append(key, String(value));
}

// Cache and in-flight deduplication prevent React StrictMode double-fetches
// and single-threaded php artisan serve queue timeouts.
let cachedBootstrap: { data: CreateEngagementBootstrapData; fetchedAt: number } | null = null;
let inflightBootstrapPromise: Promise<CreateEngagementBootstrapData> | null = null;
const BOOTSTRAP_CACHE_TTL_MS = 60_000;

export function clearCreateEngagementBootstrapCache() {
  cachedBootstrap = null;
  inflightBootstrapPromise = null;
}

if (typeof window !== "undefined") {
  window.addEventListener("cias:auth-cleared", clearCreateEngagementBootstrapCache);
}

export async function fetchCreateEngagementBootstrap(
  options: { canCreateHint?: boolean; forceRefresh?: boolean } = {},
): Promise<CreateEngagementBootstrapData> {
  const { canCreateHint = true, forceRefresh = false } = options;

  if (
    !forceRefresh &&
    cachedBootstrap &&
    Date.now() - cachedBootstrap.fetchedAt < BOOTSTRAP_CACHE_TTL_MS
  ) {
    return cachedBootstrap.data;
  }

  if (inflightBootstrapPromise) {
    return inflightBootstrapPromise;
  }

  inflightBootstrapPromise = (async () => {
    // 1. Offices (includes each office's linked auditAreas, avoiding a slow extra /api/audit-areas call)
    let offices: BackendOffice[] = [];
    try {
      const officesRes = await api.get<{ offices?: BackendOffice[] } | BackendOffice[]>(
        "/api/offices",
        { timeoutMs: 45000 },
      );
      offices = Array.isArray(officesRes)
        ? officesRes
        : Array.isArray(officesRes?.offices)
        ? officesRes.offices
        : [];
    } catch (err) {
      console.warn("Could not load offices:", err);
    }

    // Derive deduplicated audit areas with office back-links from the loaded offices
    const areaMap = new Map<number, BackendAuditArea>();
    offices.forEach((office) => {
      (office.auditAreas || []).forEach((area) => {
        const existing = areaMap.get(area.id);
        const officeRef = { id: office.id, code: office.code, name: office.name };
        if (existing) {
          existing.offices = existing.offices || [];
          if (!existing.offices.some((o) => o.id === office.id)) {
            existing.offices.push(officeRef);
          }
        } else {
          areaMap.set(area.id, {
            id: area.id,
            code: area.code,
            name: area.name,
            description: area.description,
            isActive: true,
            offices: [officeRef],
          });
        }
      });
    });
    const auditAreas = Array.from(areaMap.values()).sort((a, b) =>
      a.name.localeCompare(b.name),
    );

    // 2. Users (for Team Leader, Team Members, Department Head, Support Staff, Directing Authority)
    let users: BackendUser[] = [];
    try {
      const usersRes = await api.get<{ users?: BackendUser[] } | BackendUser[]>(
        "/api/users",
        { timeoutMs: 45000 },
      );
      const rawUsers = Array.isArray(usersRes)
        ? usersRes
        : Array.isArray(usersRes?.users)
        ? usersRes.users
        : [];
      users = rawUsers.filter((u) => u.isActive !== false);
    } catch (err) {
      console.warn("Could not load users:", err);
    }

    // 3. Master Lists (Audit Types from IAP_ENGAGEMENT_TYPE)
    let auditTypes: BackendAuditTypeOption[] = [];
    try {
      const listsRes = await api.get<{
        masterLists?: Array<{ code: string; items?: BackendAuditTypeOption[] }>;
      }>("/api/master-lists", { timeoutMs: 45000 });
      const typeList = listsRes?.masterLists?.find(
        (list) => list.code === "IAP_ENGAGEMENT_TYPE",
      );
      if (typeList && Array.isArray(typeList.items)) {
        auditTypes = typeList.items.filter((item) => item.isActive !== false);
      }
    } catch (err) {
      console.warn("Could not load master lists:", err);
    }

    // 4. Eligible IAP engagements (only call if user has create permission; gracefully handle 403)
    let iapEngagements: BackendIapImportOption[] = [];
    let canCreateEngagement = canCreateHint;
    if (canCreateHint) {
      try {
        const importRes = await api.get<{
          iapEngagements?: BackendIapImportOption[];
        }>("/api/aems/engagements/import-options", { timeoutMs: 45000 });
        iapEngagements = Array.isArray(importRes?.iapEngagements)
          ? importRes.iapEngagements
          : [];
      } catch (err) {
        if (err instanceof ApiError && err.status === 403) {
          canCreateEngagement = false;
        } else {
          console.warn("Could not load IAP import options:", err);
        }
      }
    }

    // 5. Existing engagements count (reuses cached list when available -> 0 extra latency)
    let existingCount = 0;
    try {
      const existing = await fetchAuditEngagementRecords(false);
      existingCount = existing.length;
    } catch {
      existingCount = 0;
    }

    const data: CreateEngagementBootstrapData = {
      iapEngagements,
      offices,
      auditAreas,
      users,
      auditTypes,
      existingCount,
      canCreateEngagement,
    };

    cachedBootstrap = { data, fetchedAt: Date.now() };
    return data;
  })().finally(() => {
    inflightBootstrapPromise = null;
  });

  return inflightBootstrapPromise;
}

export async function importIapEngagement(payload: ImportIapEngagementPayload) {
  const response = await api.post<{ engagement: any }>(
    "/api/aems/engagements/import",
    payload,
    { csrf: true, timeoutMs: 45000 },
  );
  clearEngagementsCache();
  clearCreateEngagementBootstrapCache();
  return response.engagement;
}

export async function createSpecialEngagement(
  payload: CreateSpecialEngagementPayload,
  supportingFile?: File | null,
) {
  let body: unknown = payload;

  if (supportingFile) {
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      appendFormDataValue(formData, key, value);
    });
    formData.append("supportingDocument", supportingFile);
    body = formData;
  }

  const response = await api.post<{ engagement: any }>(
    "/api/aems/engagements",
    body,
    { csrf: true, timeoutMs: 45000 },
  );
  clearEngagementsCache();
  clearCreateEngagementBootstrapCache();
  return response.engagement;
}
