import { api } from "./api";
import { fetchAuditEngagementRecords } from "../pages/admin/audit-engagement/service";

export interface PreliminarySurveyMeetingData {
  proposedAt?: string | null;
  venue?: string | null;
  focalPerson?: string | null;
  ciasParticipants?: string | null;
  ciasParticipantIds?: number[];
  auditeeParticipants?: string | null;
  mattersToDiscuss?: string | null;
  documentsNeeded?: string | null;
  noticeDocumentVersionId?: number | null;
  actualDate?: string | null;
  mattersDiscussed?: string | null;
}

export interface PreliminarySurveyAuditeeData {
  mandate?: string | null;
  mission?: string | null;
  vision?: string | null;
  objectives?: string | null;
  strategicGoals?: string | null;
  annualBudget?: number | string | null;
  personnelBudget?: number | string | null;
  mooeBudget?: number | string | null;
  capitalBudget?: number | string | null;
  sppBudget?: number | string | null;
  budgetDocumentVersionId?: number | null;
  programs?: Array<{
    title?: string;
    objectives?: string;
    beneficiaries?: string;
    fundingSource?: string;
    budget?: number | string;
    implementationPeriod?: string;
    collaborators?: string;
    status?: string;
  }>;
}

export interface PreliminarySurveyPpspData {
  mandate?: string | null;
  purpose?: string | null;
  scope?: string | null;
  expectedResults?: string | null;
  responsibleUnit?: string | null;
  targetUsers?: string | null;
  resources?: string | null;
  measures?: string | null;
  flowchartDocumentVersionId?: number | null;
  steps?: Array<{
    activity?: string;
    responsible?: string;
    inputOutput?: string;
    controlRecord?: string;
    source?: string;
  }>;
  sources?: Array<{
    documentPersonMethod?: string;
    dateCustodian?: string;
    supportsLimitation?: string;
  }>;
  questions?: Array<{
    informationGap?: string;
    verificationNeeded?: string;
    ownerDue?: string;
    taskLink?: string;
  }>;
  risks?: Array<{
    riskDescription?: string;
    riskCategory?: string;
    likelihood?: number | null;
    likelihoodJustification?: string;
    impact?: number | null;
    impactJustification?: string;
    riskRating?: number | null;
    controlActivity?: string;
    controlType?: string;
    controlOwner?: string;
    controlFrequency?: string;
    operatingEffectiveness?: string;
    residualRiskRating?: string;
  }>;
}

export interface PreliminarySurveyIcsData {
  assessments?: Array<{
    principleId: number;
    preliminaryView?: string;
    basisSource?: string;
    keyMatters?: string;
    preliminaryAssessment?: string;
    nextAction?: string;
  }>;
}

export interface PreliminarySurveyWorkspace {
  survey: {
    meeting?: PreliminarySurveyMeetingData;
    auditee?: PreliminarySurveyAuditeeData;
    ppsp?: PreliminarySurveyPpspData;
    ics?: PreliminarySurveyIcsData;
  };
  lockVersion: number | null;
  packageStatus: string;
  canEdit: boolean;
  sectionReviews: Record<string, any>;
  sectionCapabilities: Record<string, any>;
  attachments: Array<{ id: number; fileName: string }>;
  participantOptions: Array<{
    id: number;
    name: string;
    position?: string;
    employeeId?: string;
  }>;
}

const codeToIdCache: Record<string, number> = {};

export async function resolveNumericEngagementId(
  idOrCode: string | number
): Promise<number | string> {
  const str = String(idOrCode).trim();
  if (/^\d+$/.test(str)) {
    return Number(str);
  }

  const upper = str.toUpperCase();
  if (codeToIdCache[upper]) {
    return codeToIdCache[upper];
  }

  try {
    const list = await fetchAuditEngagementRecords();
    for (const item of list) {
      if (item.code) codeToIdCache[item.code.toUpperCase()] = Number(item.id);
      if (item.id) codeToIdCache[String(item.id)] = Number(item.id);
    }
    if (codeToIdCache[upper]) {
      return codeToIdCache[upper];
    }
  } catch {
    // Ignore error
  }

  // Fallback: extract trailing digits if present
  const trailingDigits = str.match(/(\d+)$/);
  if (trailingDigits) {
    return parseInt(trailingDigits[1], 10);
  }

  return idOrCode;
}

const inFlightEngagementMap = new Map<string, Promise<any>>();
const engagementCache = new Map<string, { data: any; expiry: number }>();

const inFlightWorkspaceMap = new Map<string, Promise<PreliminarySurveyWorkspace>>();
const workspaceCache = new Map<string, { data: PreliminarySurveyWorkspace; expiry: number }>();

export const preliminarySurveyService = {
  async getEngagement(idOrCode: string | number): Promise<any> {
    const id = await resolveNumericEngagementId(idOrCode);
    const key = String(id);
    const now = Date.now();

    const cached = engagementCache.get(key);
    if (cached && cached.expiry > now) {
      return cached.data;
    }

    if (inFlightEngagementMap.has(key)) {
      return inFlightEngagementMap.get(key)!;
    }

    const promise = (async () => {
      try {
        const res = await api.get<{ engagement: any }>(`/api/aems/engagements/${id}`);
        const data = (res as any)?.engagement || res;
        engagementCache.set(key, { data, expiry: Date.now() + 15000 });
        return data;
      } finally {
        inFlightEngagementMap.delete(key);
      }
    })();

    inFlightEngagementMap.set(key, promise);
    return promise;
  },

  async getWorkspace(
    engagementId: string | number
  ): Promise<PreliminarySurveyWorkspace> {
    const id = await resolveNumericEngagementId(engagementId);
    const key = String(id);
    const now = Date.now();

    const cached = workspaceCache.get(key);
    if (cached && cached.expiry > now) {
      return cached.data;
    }

    if (inFlightWorkspaceMap.has(key)) {
      return inFlightWorkspaceMap.get(key)!;
    }

    const promise = (async () => {
      try {
        const data = await api.get<PreliminarySurveyWorkspace>(
          `/api/aems/engagements/${id}/preliminary-survey`
        );
        workspaceCache.set(key, { data, expiry: Date.now() + 10000 });
        return data;
      } finally {
        inFlightWorkspaceMap.delete(key);
      }
    })();

    inFlightWorkspaceMap.set(key, promise);
    return promise;
  },

  async saveSection(
    engagementId: string | number,
    section: "meeting" | "auditee" | "ppsp" | "ics",
    data: Record<string, unknown>,
    lockVersion: number | null = 1
  ): Promise<PreliminarySurveyWorkspace> {
    const id = await resolveNumericEngagementId(engagementId);
    const key = String(id);
    workspaceCache.delete(key);
    engagementCache.delete(key);

    return api.put<PreliminarySurveyWorkspace>(
      `/api/aems/engagements/${id}/preliminary-survey`,
      {
        section,
        lockVersion: lockVersion ?? 1,
        data,
      },
      { csrf: true }
    );
  },

  async uploadAttachment(
    engagementId: string | number,
    file: File
  ): Promise<{ id: number; fileName: string }> {
    const id = await resolveNumericEngagementId(engagementId);
    const key = String(id);
    workspaceCache.delete(key);

    const formData = new FormData();
    formData.append("file", file);
    return api.post<{ id: number; fileName: string }>(
      `/api/aems/engagements/${id}/preliminary-survey/attachments`,
      formData,
      { csrf: true }
    );
  },

  async downloadTemplate(engagementId: string | number): Promise<void> {
    const id = await resolveNumericEngagementId(engagementId);
    const url = `/api/aems/engagements/${id}/preliminary-survey/attachments/template`;
    const response = await fetch(url, { credentials: "include" });
    if (!response.ok) {
      throw new Error("Unable to download meeting notice template.");
    }
    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = downloadUrl;
    a.download = "Notice_of_Preliminary_Meeting_Template.docx";
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },

  async reviewSection(
    engagementId: string | number,
    section: "meeting" | "auditee" | "ppsp" | "ics",
    action: "SUBMIT" | "APPROVE" | "RETURN",
    lockVersion: number = 1,
    comment?: string
  ): Promise<PreliminarySurveyWorkspace> {
    const id = await resolveNumericEngagementId(engagementId);
    return api.post<PreliminarySurveyWorkspace>(
      `/api/aems/engagements/${id}/preliminary-survey/review`,
      {
        section,
        action,
        lockVersion,
        comment: comment || null,
      },
      { csrf: true }
    );
  },
};

