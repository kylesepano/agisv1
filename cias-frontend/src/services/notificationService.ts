import { api } from "./api";

export type NotificationPriority = "LOW" | "NORMAL" | "HIGH" | "URGENT" | "CRITICAL";
export type NotificationCategory = "WORKFLOW" | "ASSIGNMENT" | "DUE_DATE" | "SYSTEM" | "OVERDUE";
export type NotificationModule = "CORE" | "IAP" | "AEM" | "AFR" | "CMS" | "ARMIS" | "AIS";

export interface BackendNotification {
  id: number;
  type: string;
  category: NotificationCategory;
  priority: NotificationPriority;
  moduleCode: NotificationModule | string;
  title: string;
  message: string;
  actionUrl?: string | null;
  actionLabel?: string | null;
  subjectType?: string | null;
  subjectId?: number | null;
  subjectCode?: string | null;
  metadata?: Record<string, unknown> | null;
  actor?: {
    id: number;
    employeeId: string;
    name: string;
    initials: string;
  } | null;
  isRead: boolean;
  readAt?: string | null;
  isArchived: boolean;
  archivedAt?: string | null;
  expiresAt?: string | null;
  createdAt: string;
}

export interface NotificationRecentResponse {
  notifications: BackendNotification[];
  unreadCount: number;
}

export interface NotificationListParams {
  search?: string;
  category?: NotificationCategory;
  priority?: NotificationPriority;
  module?: string;
  readStatus?: "UNREAD" | "READ";
  includeArchived?: boolean;
  page?: number;
  perPage?: number;
}

export interface NotificationPagination {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

export interface NotificationSummary {
  total: number;
  unread: number;
  actionRequired: number;
  overdue: number;
  archived: number;
}

export interface NotificationPreferences {
  inAppEnabled: boolean;
  workflowEnabled: boolean;
  assignmentsEnabled: boolean;
  dueDatesEnabled: boolean;
  systemEnabled: boolean;
  emailEnabled: boolean;
  digestFrequency: "IMMEDIATE" | "DAILY" | "WEEKLY";
  quietHoursStart: string | null;
  quietHoursEnd: string | null;
}

export interface NotificationListResponse {
  notifications: BackendNotification[];
  pagination: NotificationPagination;
  summary: NotificationSummary;
  preferences: NotificationPreferences;
  options: {
    categories: string[];
    priorities: string[];
    modules: string[];
    users: Array<{ id: number; employee_id: string; name: string }>;
    roles: Array<{ id: number; code: string; name: string }>;
    offices: Array<{ id: number; code: string; name: string }>;
  };
}

export interface DeliverNotificationPayload {
  title: string;
  message: string;
  category: NotificationCategory;
  priority: NotificationPriority;
  moduleCode: string;
  actionUrl?: string;
  actionLabel?: string;
  recipientIds?: number[];
  roleCodes?: string[];
  officeIds?: number[];
  allUsers?: boolean;
}

let inFlightRecentPromise: Promise<NotificationRecentResponse> | null = null;
let recentCache: { data: NotificationRecentResponse; expiry: number } | null = null;

export const notificationService = {
  recent: async (): Promise<NotificationRecentResponse> => {
    const now = Date.now();
    if (recentCache && recentCache.expiry > now) {
      return recentCache.data;
    }

    if (inFlightRecentPromise) {
      return inFlightRecentPromise;
    }

    inFlightRecentPromise = api
      .get<NotificationRecentResponse>("/api/notifications/recent")
      .then((res) => {
        recentCache = { data: res, expiry: Date.now() + 15000 };
        return res;
      })
      .finally(() => {
        inFlightRecentPromise = null;
      });

    return inFlightRecentPromise;
  },

  list: async (params: NotificationListParams = {}): Promise<NotificationListResponse> => {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        query.set(key, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
      }
    });
    const queryString = query.toString();
    return api.get<NotificationListResponse>(`/api/notifications${queryString ? `?${queryString}` : ""}`);
  },

  markRead: async (id: number | string): Promise<{ notification: BackendNotification }> => {
    recentCache = null;
    return api.post<{ notification: BackendNotification }>(`/api/notifications/${id}/read`, {}, { csrf: true });
  },

  markUnread: async (id: number | string): Promise<{ notification: BackendNotification }> => {
    recentCache = null;
    return api.post<{ notification: BackendNotification }>(`/api/notifications/${id}/unread`, {}, { csrf: true });
  },

  markAllRead: async (): Promise<{ updated: number }> => {
    recentCache = null;
    return api.post<{ updated: number }>("/api/notifications/read-all", {}, { csrf: true });
  },

  archive: async (id: number | string): Promise<void> => {
    recentCache = null;
    return api.delete<void>(`/api/notifications/${id}`, { csrf: true });
  },

  restore: async (id: number | string): Promise<{ notification: BackendNotification }> => {
    recentCache = null;
    return api.post<{ notification: BackendNotification }>(`/api/notifications/${id}/restore`, {}, { csrf: true });
  },

  updatePreferences: async (preferences: NotificationPreferences): Promise<NotificationPreferences> => {
    return api.put<NotificationPreferences>("/api/notifications/preferences", preferences, { csrf: true });
  },

  deliver: async (payload: DeliverNotificationPayload): Promise<{ delivered: number }> => {
    recentCache = null;
    return api.post<{ delivered: number }>("/api/notifications", payload, { csrf: true });
  },
};

