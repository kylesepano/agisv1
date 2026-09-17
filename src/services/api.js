const JSON_HEADERS = {
  Accept: "application/json",
  "Content-Type": "application/json",
  "X-Requested-With": "XMLHttpRequest",
};

// Production uses the same Render origin, so the default remains the relative
// `/api` path. A non-default value is available for explicitly configured
// environments without hard-coding a deployment hostname.
const baseURL = (import.meta.env.VITE_API_BASE_URL || "/api").replace(/\/$/, "");

function apiPath(path) {
  return path.startsWith("/api")
    ? `${baseURL}${path.slice("/api".length)}`
    : path;
}

export class ApiError extends Error {
  constructor(message, { status = 0, errors = {} } = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

function getCookie(name) {
  const prefix = `${name}=`;
  const cookie = document.cookie
    .split(";")
    .map((value) => value.trim())
    .find((value) => value.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : "";
}

function errorFromResponse(payload, status) {
  const errors = payload?.errors ?? {};
  const firstValidationError = Object.values(errors)
    .flat()
    .find((message) => typeof message === "string");
  const rawMessage =
    firstValidationError ||
    payload?.message ||
    (status === 401
      ? "Your session has expired. Please sign in again."
      : "The request could not be completed.");

  // Never surface database/driver diagnostics in the browser. Production
  // exception handling should already redact these, but this boundary also
  // protects the UI when an upstream proxy or misconfigured environment
  // returns a raw SQL error payload.
  const message =
    typeof rawMessage === "string" &&
    /(SQLSTATE|PDOException|Undefined (?:column|table)|relation .* does not exist|syntax error at or near|database error)/i.test(
      rawMessage,
    )
      ? "The request could not be completed."
      : rawMessage;

  return new ApiError(message, { status, errors });
}

async function parseResponse(response) {
  if (response.status === 204) return null;

  const contentType = response.headers.get("content-type") || "";
  if (!contentType.includes("application/json")) return null;

  try {
    return await response.json();
  } catch {
    return null;
  }
}

function queryFrom(filters = {}) {
  const query = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value === "" || value === null || value === undefined) return;
    query.set(
      key,
      typeof value === "boolean" ? (value ? "1" : "0") : String(value),
    );
  });
  return query;
}

async function ensureCsrfCookie() {
  let response;

  try {
    response = await fetch("/sanctum/csrf-cookie", {
      credentials: "include",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
  } catch {
    throw new ApiError(
      "Unable to connect to AGIS. Please check that the server is running.",
    );
  }

  if (!response.ok) {
    const payload = await parseResponse(response);
    throw errorFromResponse(payload, response.status);
  }
}

async function request(path, { method = "GET", body, csrf = false } = {}) {
  if (csrf) await ensureCsrfCookie();

  const isFormData = body instanceof FormData;
  const headers = isFormData
    ? {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      }
    : { ...JSON_HEADERS };
  const xsrfToken = getCookie("XSRF-TOKEN");
  if (xsrfToken) headers["X-XSRF-TOKEN"] = xsrfToken;

  let response;

  try {
    response = await fetch(apiPath(path), {
      method,
      credentials: "include",
      headers,
      body:
        body === undefined
          ? undefined
          : isFormData
            ? body
            : JSON.stringify(body),
    });
  } catch {
    throw new ApiError(
      "Unable to connect to AGIS. Please check that the server is running.",
    );
  }

  const payload = await parseResponse(response);
  if (!response.ok || payload?.success === false) {
    throw errorFromResponse(payload, response.status);
  }

  return payload?.data ?? null;
}

export const authApi = {
  async demoAccounts() {
    const data = await request("/api/demo-accounts");
    return Array.isArray(data) ? data : [];
  },

  async login(credentials) {
    const data = await request("/api/login", {
      method: "POST",
      body: credentials,
      csrf: true,
    });
    return data?.user ?? null;
  },

  async me() {
    const data = await request("/api/me");
    return data?.user ?? null;
  },

  async logout() {
    await request("/api/logout", { method: "POST", csrf: true });
  },
};

export const runtimeConfigurationApi = {
  async show() {
    const data = await request("/api/runtime-configuration");
    return data?.configuration ?? {};
  },
};

export const coreDashboardApi = {
  async show() {
    return request("/api/dashboard");
  },
};

export const aisContractApi = {
  async show() {
    return request("/api/ais/contract");
  },
  async hardening() {
    return request("/api/ais/hardening");
  },
  async integration() {
    return request("/api/ais/integration-contract");
  },
  async integrationHealth() {
    return request("/api/ais/integration-health");
  },
  async integrationSnapshots() {
    return request("/api/ais/integration-health/snapshots");
  },
  async captureIntegrationSnapshot() {
    return request("/api/ais/integration-health/snapshots", { method: "POST", csrf: true });
  },
};

export const aisAggregationApi = {
  async overview() {
    return request("/api/ais/aggregations");
  },
  async snapshots() {
    const data = await request("/api/ais/aggregations/snapshots");
    return data?.snapshots || [];
  },
  async generate() {
    const data = await request("/api/ais/aggregations/snapshots", {
      method: "POST",
      csrf: true,
    });
    return data?.snapshot || null;
  },
};

export const aisDashboardApi = {
  async show() {
    return request("/api/ais/dashboard");
  },
};

export const aisReportApi = {
  async catalog() {
    return request("/api/ais/reports");
  },
  async alerts() {
    return request("/api/ais/alerts");
  },
  async runs() {
    const data = await request("/api/ais/reports/runs");
    return data?.runs || [];
  },
  async generate(code, filters = {}) {
    const data = await request(`/api/ais/reports/${code}/generate`, {
      method: "POST",
      body: filters,
      csrf: true,
    });
    return data?.run || null;
  },
  async export(runId, format) {
    const data = await request(`/api/ais/reports/runs/${runId}/exports`, {
      method: "POST",
      body: { format },
      csrf: true,
    });
    return data?.export || null;
  },
  async download(exportRecord) {
    const response = await fetch(`/api/ais/report-exports/${exportRecord.id}/download`, {
      credentials: "include",
      headers: { Accept: exportRecord.mimeType || "application/octet-stream" },
    });
    if (!response.ok) throw new Error("The AIS export download could not be completed.");
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = exportRecord.fileName || "ais-report-export";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const coreAdministrativeReportApi = {
  async catalog() {
    return request("/api/administrative-reports");
  },
  async runs() {
    const data = await request("/api/administrative-reports/runs");
    return data?.runs || [];
  },
  async generate(code, filters = {}) {
    const data = await request(`/api/administrative-reports/${code}/generate`, {
      method: "POST",
      body: filters,
      csrf: true,
    });
    return data?.run || null;
  },
  async export(runId, format) {
    const data = await request(`/api/administrative-reports/runs/${runId}/exports`, {
      method: "POST",
      body: { format },
      csrf: true,
    });
    return data?.export || null;
  },
  async download(exportRecord) {
    const response = await fetch(
      `/api/administrative-report-exports/${exportRecord.id}/download`,
      {
        credentials: "include",
        headers: { Accept: "application/octet-stream", "X-Requested-With": "XMLHttpRequest" },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = exportRecord.fileName || "administrative-report";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const workflowApi = {
  async list({ includeArchived = false, includeCompleted = true } = {}) {
    const query = queryFrom({
      include_archived: includeArchived,
      include_completed: includeCompleted,
    });
    return request(`/api/workflows?${query.toString()}`);
  },
  async create(payload) {
    const data = await request("/api/workflows", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/workflows/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async publish(id) {
    const data = await request(`/api/workflows/${id}/publish`, {
      method: "POST",
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async createRevision(id) {
    const data = await request(`/api/workflows/${id}/revisions`, {
      method: "POST",
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async archive(id) {
    await request(`/api/workflows/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/workflows/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async start(payload) {
    const data = await request("/api/workflow-instances", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.instance ?? null;
  },
  async showInstance(id) {
    const data = await request(`/api/workflow-instances/${id}`);
    return data?.instance ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(
      `/api/workflow-instances/${id}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.instance ?? null;
  },
  async cancel(id, payload) {
    const data = await request(`/api/workflow-instances/${id}/cancel`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.instance ?? null;
  },
};

export const notificationApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    return request(
      `/api/notifications${query.size ? `?${query.toString()}` : ""}`,
    );
  },
  async recent() {
    return request("/api/notifications/recent");
  },
  async markRead(id) {
    const data = await request(`/api/notifications/${id}/read`, {
      method: "POST",
      csrf: true,
    });
    return data?.notification ?? null;
  },
  async markUnread(id) {
    const data = await request(`/api/notifications/${id}/unread`, {
      method: "POST",
      csrf: true,
    });
    return data?.notification ?? null;
  },
  async markAllRead() {
    return request("/api/notifications/read-all", {
      method: "POST",
      csrf: true,
    });
  },
  async archive(id) {
    await request(`/api/notifications/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/notifications/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.notification ?? null;
  },
  async updatePreferences(payload) {
    const data = await request("/api/notifications/preferences", {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.preferences ?? null;
  },
  async deliver(payload) {
    return request("/api/notifications", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
};

export const officeApi = {
  async list({ includeArchived = false } = {}) {
    const data = await request(
      `/api/offices${includeArchived ? "?include_archived=1" : ""}`,
    );
    return Array.isArray(data?.offices) ? data.offices : [];
  },

  async create(office) {
    const data = await request("/api/offices", {
      method: "POST",
      body: office,
      csrf: true,
    });
    return data?.office ?? null;
  },

  async update(id, office) {
    const data = await request(`/api/offices/${id}`, {
      method: "PUT",
      body: office,
      csrf: true,
    });
    return data?.office ?? null;
  },

  async remove(id) {
    await request(`/api/offices/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/offices/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.office ?? null;
  },
};

export const demoApi = {
  async reset() {
    return request("/api/demo/reset", {
      method: "POST",
      csrf: true,
    });
  },
};

function crudApi(path, collectionKey, itemKey) {
  return {
    async list({ includeArchived = false } = {}) {
      const data = await request(
        `${path}${includeArchived ? "?include_archived=1" : ""}`,
      );
      return Array.isArray(data?.[collectionKey]) ? data[collectionKey] : [];
    },
    async create(payload) {
      const data = await request(path, {
        method: "POST",
        body: payload,
        csrf: true,
      });
      return data?.[itemKey] ?? null;
    },
    async update(id, payload) {
      const data = await request(`${path}/${id}`, {
        method: "PUT",
        body: payload,
        csrf: true,
      });
      return data?.[itemKey] ?? null;
    },
    async remove(id) {
      await request(`${path}/${id}`, { method: "DELETE", csrf: true });
    },
    async restore(id) {
      const data = await request(`${path}/${id}/restore`, {
        method: "POST",
        csrf: true,
      });
      return data?.[itemKey] ?? null;
    },
  };
}

export const auditAreaApi = crudApi(
  "/api/audit-areas",
  "auditAreas",
  "auditArea",
);

export const auditFocusApi = crudApi(
  "/api/audit-focuses",
  "auditFocuses",
  "auditFocus",
);

export const userApi = {
  ...crudApi("/api/users", "users", "user"),
  async show(id) {
    const data = await request(`/api/users/${id}`);
    return data?.user ?? null;
  },
  async activate(id) {
    const data = await request(`/api/users/${id}/activate`, {
      method: "POST",
      csrf: true,
    });
    return data?.user ?? null;
  },
  async disable(id) {
    const data = await request(`/api/users/${id}/disable`, {
      method: "POST",
      csrf: true,
    });
    return data?.user ?? null;
  },
  async lock(id) {
    const data = await request(`/api/users/${id}/lock`, {
      method: "POST",
      csrf: true,
    });
    return data?.user ?? null;
  },
  async unlock(id) {
    const data = await request(`/api/users/${id}/unlock`, {
      method: "POST",
      csrf: true,
    });
    return data?.user ?? null;
  },
  async resetPassword(id, password) {
    await request(`/api/users/${id}/password`, {
      method: "PUT",
      body: {
        password,
        password_confirmation: password,
      },
      csrf: true,
    });
  },
};

export const roleApi = {
  async list({ includeArchived = false } = {}) {
    const data = await request(
      `/api/roles${includeArchived ? "?include_archived=1" : ""}`,
    );
    return Array.isArray(data?.roles) ? data.roles : [];
  },
  async create(payload) {
    const data = await request("/api/roles", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.role ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/roles/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.role ?? null;
  },
  async clone(id, payload) {
    const data = await request(`/api/roles/${id}/clone`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.role ?? null;
  },
  async remove(id) {
    await request(`/api/roles/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/roles/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.role ?? null;
  },
};

export const permissionApi = {
  async list() {
    const data = await request("/api/permissions");
    return Array.isArray(data?.permissions) ? data.permissions : [];
  },
};

export const documentApi = {
  async list({ includeArchived = false } = {}) {
    const data = await request(
      `/api/documents${includeArchived ? "?include_archived=1" : ""}`,
    );
    return {
      documents: Array.isArray(data?.documents) ? data.documents : [],
      documentTypes: Array.isArray(data?.documentTypes)
        ? data.documentTypes
        : [],
      confidentialityLevels: Array.isArray(data?.confidentialityLevels)
        ? data.confidentialityLevels
        : [],
      linkOptions: Array.isArray(data?.linkOptions) ? data.linkOptions : [],
      linkModules: data?.linkModules ?? {},
    };
  },
  async create(formData) {
    const data = await request("/api/documents", {
      method: "POST",
      body: formData,
      csrf: true,
    });
    return data?.document ?? null;
  },
  async update(id, formData) {
    formData.set("_method", "PUT");
    const data = await request(`/api/documents/${id}`, {
      method: "POST",
      body: formData,
      csrf: true,
    });
    return data?.document ?? null;
  },
  async createVersion(id, formData) {
    const data = await request(`/api/documents/${id}/versions`, {
      method: "POST",
      body: formData,
      csrf: true,
    });
    return data?.document ?? null;
  },
  async remove(id) {
    await request(`/api/documents/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/documents/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.document ?? null;
  },
  async download(document) {
    return this.downloadFile(
      `/api/documents/${document.id}/download`,
      document.fileName,
    );
  },
  async downloadVersion(document, version) {
    return this.downloadFile(
      `/api/documents/${document.id}/versions/${version.id}/download`,
      version.fileName,
    );
  },
  async downloadFile(urlPath, fileName) {
    let response;
    try {
      response = await fetch(urlPath, {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
    } catch {
      throw new ApiError(
        "Unable to connect to AGIS. Please check that the server is running.",
      );
    }

    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = window.document.createElement("a");
    link.href = url;
    link.download = fileName;
    window.document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const masterListApi = {
  async list({ configurableOnly = false } = {}) {
    const data = await request(
      `/api/master-lists${configurableOnly ? "?configurableOnly=1" : ""}`,
    );
    return Array.isArray(data?.masterLists) ? data.masterLists : [];
  },
  async create(payload) {
    const data = await request("/api/master-lists", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.masterList ?? null;
  },
  async update(id, payload) {
    await request(`/api/master-lists/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
};

export const configurationApi = {
  async list() {
    const data = await request("/api/system-configurations");
    return Array.isArray(data?.configurations) ? data.configurations : [];
  },
    async update(configurations) {
      const data = await request("/api/system-configurations", {
        method: "PUT",
        body: { configurations },
        csrf: true,
      });
      return data?.configuration ?? null;
    },
    async uploadLogo(file) {
      const body = new FormData();
      body.set("logo", file);
      const data = await request("/api/system-configurations/logo", {
        method: "POST",
        body,
        csrf: true,
      });
      return data?.configuration ?? null;
    },
    async testEmail(recipient) {
      await request("/api/system-configurations/test-email", {
        method: "POST",
        body: { recipient },
        csrf: true,
      });
    },
  };

export const activityLogApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    return request(`/api/activity-logs${query.size ? `?${query}` : ""}`);
  },
};

export const viewActivityApi = {
  async record(payload) {
    return request("/api/record-views", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
};

export const logApi = {
  async list(mode, filters = {}) {
    const query = queryFrom(filters);
    const path = mode === "audit" ? "audit-logs" : "activity-logs";
    return request(`/api/${path}${query.size ? `?${query}` : ""}`);
  },
  exportUrl(mode, filters = {}) {
    const query = queryFrom(filters);
    const path = mode === "audit" ? "audit-logs" : "activity-logs";
    return `/api/${path}/export?${query.toString()}`;
  },
};

export const iapApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/plans${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      plans: Array.isArray(data?.plans) ? data.plans : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/plans/${id}`);
    return data?.plan ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/plans", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/plans/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async archive(id) {
    await request(`/api/iap/plans/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/plans/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async completeness(id) {
    const data = await request(`/api/iap/plans/${id}/completeness`);
    return data?.completeness ?? { complete: false, errors: [] };
  },
  async transition(id, action, payload) {
    const data = await request(`/api/iap/plans/${id}/transitions/${action}`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async createRevision(id, payload) {
    const data = await request(`/api/iap/plans/${id}/revisions`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async connectPrioritization(id, payload) {
    const data = await request(`/api/iap/plans/${id}/prioritization`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async createEngagement(planId, payload) {
    const data = await request(`/api/iap/plans/${planId}/engagements`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.engagement ?? null;
  },
  async listRiskAssessments(planId, { includeArchived = false } = {}) {
    const data = await request(
      `/api/iap/plans/${planId}/risk-assessments${
        includeArchived ? "?includeArchived=1" : ""
      }`,
    );
    return Array.isArray(data?.riskAssessments)
      ? data.riskAssessments
      : [];
  },
  async createRiskAssessment(planId, payload) {
    const data = await request(`/api/iap/plans/${planId}/risk-assessments`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.riskAssessment ?? null;
  },
  async updateRiskAssessment(planId, assessmentId, payload) {
    const data = await request(
      `/api/iap/plans/${planId}/risk-assessments/${assessmentId}`,
      {
        method: "PUT",
        body: payload,
        csrf: true,
      },
    );
    return data?.riskAssessment ?? null;
  },
  async archiveRiskAssessment(planId, assessmentId) {
    await request(
      `/api/iap/plans/${planId}/risk-assessments/${assessmentId}`,
      {
        method: "DELETE",
        csrf: true,
      },
    );
  },
  async restoreRiskAssessment(planId, assessmentId) {
    const data = await request(
      `/api/iap/plans/${planId}/risk-assessments/${assessmentId}/restore`,
      {
        method: "POST",
        csrf: true,
      },
    );
    return data?.riskAssessment ?? null;
  },
};

export const iapDashboardApi = {
  async show() {
    return request("/api/iap/dashboard");
  },
};

function reportQuery(filters = {}) {
  return queryFrom(filters);
}

export const iapReportApi = {
  async catalog() {
    return request("/api/iap/reports");
  },
  async preview(reportCode, filters = {}) {
    const query = reportQuery(filters);
    const data = await request(
      `/api/iap/reports/${reportCode}${
        query.size ? `?${query.toString()}` : ""
      }`,
    );
    return data?.report ?? null;
  },
  async download(reportCode, format, filters = {}) {
    const query = reportQuery({ ...filters, format });
    const response = await fetch(
      `/api/iap/reports/${reportCode}/export?${query.toString()}`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const blob = await response.blob();
    const disposition = response.headers.get("content-disposition") ?? "";
    const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    const simpleName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
    const fileName = encodedName
      ? decodeURIComponent(encodedName)
      : simpleName || `${reportCode}.${format === "excel" ? "xls" : format}`;
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
  printUrl(reportCode, filters = {}) {
    const query = reportQuery({ ...filters, format: "print", autoprint: 1 });
    return `/api/iap/reports/${reportCode}/export?${query.toString()}`;
  },
};

export const iapSupportingRecordsApi = {
  async show(planId, { includeArchived = false } = {}) {
    return (
      (await request(
        `/api/iap/plans/${planId}/supporting-records${
          includeArchived ? "?includeArchived=1" : ""
        }`,
      )) ?? {
        attachments: [],
        comments: [],
        attachmentTypes: [],
        riskAssessments: [],
        engagements: [],
        capabilities: {},
      }
    );
  },
  async upload(planId, payload) {
    const body = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      if (value !== "" && value !== null && value !== undefined) {
        body.append(key, value);
      }
    });
    const data = await request(`/api/iap/plans/${planId}/attachments`, {
      method: "POST",
      body,
      csrf: true,
    });
    return data?.attachment ?? null;
  },
  async download(planId, attachment) {
    const response = await fetch(
      `/api/iap/plans/${planId}/attachments/${attachment.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = attachment.fileName || attachment.displayName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
  async archive(planId, attachmentId) {
    await request(`/api/iap/plans/${planId}/attachments/${attachmentId}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(planId, attachmentId) {
    await request(
      `/api/iap/plans/${planId}/attachments/${attachmentId}/restore`,
      {
        method: "POST",
        csrf: true,
      },
    );
  },
  async addComment(planId, payload) {
    const data = await request(`/api/iap/plans/${planId}/comments`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.comment ?? null;
  },
};

export const siapApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/strategic-plans${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      strategicPlans: Array.isArray(data?.strategicPlans)
        ? data.strategicPlans
        : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/strategic-plans/${id}`);
    return data?.strategicPlan ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/strategic-plans", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.strategicPlan ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/strategic-plans/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.strategicPlan ?? null;
  },
  async archive(id) {
    await request(`/api/iap/strategic-plans/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/strategic-plans/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.strategicPlan ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(
      `/api/iap/strategic-plans/${id}/transitions/${action}`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.strategicPlan ?? null;
  },
  async createRevision(id, payload) {
    const data = await request(`/api/iap/strategic-plans/${id}/revisions`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.strategicPlan ?? null;
  },
};

export const auditUniverseApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/audit-universe${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      items: Array.isArray(data?.auditUniverse) ? data.auditUniverse : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async create(payload) {
    const data = await request("/api/iap/audit-universe", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.auditUniverseItem ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/audit-universe/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.auditUniverseItem ?? null;
  },
  async archive(id) {
    await request(`/api/iap/audit-universe/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/audit-universe/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.auditUniverseItem ?? null;
  },
};

export const baicsApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(`/api/iap/baics${query.size ? `?${query.toString()}` : ""}`);
    return {
      assessments: Array.isArray(data?.assessments) ? data.assessments : [],
      pagination: data?.pagination ?? { currentPage: 1, lastPage: 1, perPage: 25, total: 0 },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/baics/${id}`);
    return data?.assessment ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/baics", { method: "POST", body: payload, csrf: true });
    return data?.assessment ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/baics/${id}`, { method: "PUT", body: payload, csrf: true });
    return data?.assessment ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(`/api/iap/baics/${id}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.assessment ?? null;
  },
  async revision(id) {
    const data = await request(`/api/iap/baics/${id}/revisions`, { method: "POST", body: {}, csrf: true });
    return data?.assessment ?? null;
  },
  async versions(id) {
    const data = await request(`/api/iap/baics/${id}/versions`);
    return Array.isArray(data?.versions) ? data.versions : [];
  },
  async assign(id, payload) {
    const data = await request(`/api/iap/baics/${id}/assignments`, { method: "POST", body: payload, csrf: true });
    return data?.assignment ?? null;
  },
  async endAssignment(id, assignmentId) {
    await request(`/api/iap/baics/${id}/assignments/${assignmentId}`, { method: "DELETE", csrf: true });
  },
  async archive(id) { await request(`/api/iap/baics/${id}`, { method: "DELETE", csrf: true }); },
  async restore(id) {
    const data = await request(`/api/iap/baics/${id}/restore`, { method: "POST", csrf: true });
    return data?.assessment ?? null;
  },
  async readiness(id) {
    const data = await request(`/api/iap/baics/${id}/readiness`);
    return data?.readiness ?? null;
  },
  async components(id) {
    const data = await request(`/api/iap/baics/${id}/components`);
    return { components: Array.isArray(data?.components) ? data.components : [], readiness: data?.readiness ?? null };
  },
  async component(id, componentId) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}`);
    return data?.component ?? null;
  },
  async updateComponent(id, componentId, payload) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}`, { method: "PUT", body: payload, csrf: true });
    return data?.component ?? null;
  },
  async transitionComponent(id, componentId, action, payload) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.component ?? null;
  },
  async methods(id, componentId) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}/methods`);
    return { methods: Array.isArray(data?.methods) ? data.methods : [], readiness: data?.readiness ?? null };
  },
  async createMethod(id, componentId, payload) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}/methods`, { method: "POST", body: payload, csrf: true });
    return data?.method ?? null;
  },
  async updateMethod(id, componentId, methodId, payload) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}/methods/${methodId}`, { method: "PUT", body: payload, csrf: true });
    return data?.method ?? null;
  },
  async transitionMethod(id, componentId, methodId, action, payload) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}/methods/${methodId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.method ?? null;
  },
  async evidence(id, componentId) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}/evidence`);
    return Array.isArray(data?.evidence) ? data.evidence : [];
  },
  async linkEvidence(id, componentId, payload) {
    const data = await request(`/api/iap/baics/${id}/components/${componentId}/evidence`, { method: "POST", body: payload, csrf: true });
    return data?.evidence ?? null;
  },
  async unlinkEvidence(id, componentId, linkId) {
    await request(`/api/iap/baics/${id}/components/${componentId}/evidence/${linkId}`, { method: "DELETE", csrf: true });
  },
  async exceptions(id) {
    const data = await request(`/api/iap/baics/${id}/exceptions`);
    return Array.isArray(data?.exceptions) ? data.exceptions : [];
  },
  async createException(id, payload) {
    const data = await request(`/api/iap/baics/${id}/exceptions`, { method: "POST", body: payload, csrf: true });
    return data?.exception ?? null;
  },
  async updateException(id, exceptionId, payload) {
    const data = await request(`/api/iap/baics/${id}/exceptions/${exceptionId}`, { method: "PUT", body: payload, csrf: true });
    return data?.exception ?? null;
  },
  async transitionException(id, exceptionId, action, payload) {
    const data = await request(`/api/iap/baics/${id}/exceptions/${exceptionId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.exception ?? null;
  },
  async controls(id) {
    const data = await request(`/api/iap/baics/${id}/controls`);
    return { controls: Array.isArray(data?.controls) ? data.controls : [], readiness: data?.readiness ?? null };
  },
  async createControl(id, payload) {
    const data = await request(`/api/iap/baics/${id}/controls`, { method: "POST", body: payload, csrf: true });
    return data?.control ?? null;
  },
  async updateControl(id, controlId, payload) {
    const data = await request(`/api/iap/baics/${id}/controls/${controlId}`, { method: "PUT", body: payload, csrf: true });
    return data?.control ?? null;
  },
  async transitionControl(id, controlId, action, payload) {
    const data = await request(`/api/iap/baics/${id}/controls/${controlId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.control ?? null;
  },
  async interimAnalyses(id) {
    const data = await request(`/api/iap/baics/${id}/interim-analyses`);
    return Array.isArray(data?.interimAnalyses) ? data.interimAnalyses : [];
  },
  async createInterimAnalysis(id, payload) {
    const data = await request(`/api/iap/baics/${id}/interim-analyses`, { method: "POST", body: payload, csrf: true });
    return data?.interimAnalysis ?? null;
  },
  async updateInterimAnalysis(id, analysisId, payload) {
    const data = await request(`/api/iap/baics/${id}/interim-analyses/${analysisId}`, { method: "PUT", body: payload, csrf: true });
    return data?.interimAnalysis ?? null;
  },
  async transitionInterimAnalysis(id, analysisId, action, payload) {
    const data = await request(`/api/iap/baics/${id}/interim-analyses/${analysisId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.interimAnalysis ?? null;
  },
  async reports(id) {
    const data = await request(`/api/iap/baics/${id}/reports`);
    return { reports: Array.isArray(data?.reports) ? data.reports : [], readiness: data?.readiness ?? null };
  },
  async createReport(id, payload) {
    const data = await request(`/api/iap/baics/${id}/reports`, { method: "POST", body: payload, csrf: true });
    return data?.report ?? null;
  },
  async updateReport(id, reportId, payload) {
    const data = await request(`/api/iap/baics/${id}/reports/${reportId}`, { method: "PUT", body: payload, csrf: true });
    return data?.report ?? null;
  },
  async transitionReport(id, reportId, action, payload) {
    const data = await request(`/api/iap/baics/${id}/reports/${reportId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.report ?? null;
  },
  async downloadReport(id, reportId, format) {
    const response = await fetch(`/api/iap/baics/${id}/reports/${reportId}/export?format=${encodeURIComponent(format)}`, { credentials: "include", headers: { Accept: "application/octet-stream", "X-Requested-With": "XMLHttpRequest" } });
    if (!response.ok) { const payload = await parseResponse(response); throw errorFromResponse(payload, response.status); }
    const blob = await response.blob(); const disposition = response.headers.get("content-disposition") ?? ""; const match = disposition.match(/filename\*?=(?:UTF-8''|"?)([^;"]+)/i); const fileName = match ? decodeURIComponent(match[1]) : `baseline-assessment-report.${format}`; const url = URL.createObjectURL(blob); const link = document.createElement("a"); link.href = url; link.download = fileName; document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
  },
  async integrationCandidates() {
    const data = await request("/api/iap/baics/integrations/candidates");
    return data ?? {};
  },
  async integrations(id) {
    const data = await request(`/api/iap/baics/${id}/integrations`);
    return Array.isArray(data?.integrations) ? data.integrations : [];
  },
  async createIntegration(id, payload) {
    const data = await request(`/api/iap/baics/${id}/integrations`, { method: "POST", body: payload, csrf: true });
    return data?.integration ?? null;
  },
  async updateIntegration(id, integrationId, payload) {
    const data = await request(`/api/iap/baics/${id}/integrations/${integrationId}`, { method: "PUT", body: payload, csrf: true });
    return data?.integration ?? null;
  },
  async transitionIntegration(integrationId, action, payload) {
    const data = await request(`/api/iap/baics/integrations/${integrationId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.integration ?? null;
  },
  async integrationReadiness(consumerType, consumerId) {
    const query = new URLSearchParams({ consumerType, consumerId: String(consumerId) });
    const data = await request(`/api/iap/baics/integrations/readiness?${query.toString()}`);
    return data?.readiness ?? null;
  },
};

export const riskPeriodApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/risk-periods${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      riskPeriods: Array.isArray(data?.riskPeriods) ? data.riskPeriods : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/risk-periods/${id}`);
    return data?.riskPeriod ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/risk-periods", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.riskPeriod ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/risk-periods/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.riskPeriod ?? null;
  },
  async archive(id) {
    await request(`/api/iap/risk-periods/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/risk-periods/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.riskPeriod ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(
      `/api/iap/risk-periods/${id}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.riskPeriod ?? null;
  },
  async createAssessment(periodId, payload) {
    const data = await request(
      `/api/iap/risk-periods/${periodId}/assessments`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.riskPeriod ?? null;
  },
  async updateAssessment(periodId, assessmentId, payload) {
    const data = await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.riskPeriod ?? null;
  },
  async archiveAssessment(periodId, assessmentId) {
    await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}`,
      { method: "DELETE", csrf: true },
    );
  },
  async restoreAssessment(periodId, assessmentId) {
    const data = await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}/restore`,
      { method: "POST", csrf: true },
    );
    return data?.riskPeriod ?? null;
  },
  async uploadEvidence(periodId, assessmentId, file) {
    const formData = new FormData();
    formData.append("file", file);
    await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}/evidence`,
      { method: "POST", body: formData, csrf: true },
    );
  },
  async removeEvidence(periodId, assessmentId, evidenceId) {
    await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}/evidence/${evidenceId}`,
      { method: "DELETE", csrf: true },
    );
  },
  async downloadEvidence(periodId, assessmentId, evidence) {
    const response = await fetch(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}/evidence/${evidence.id}`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = window.document.createElement("a");
    link.href = url;
    link.download = evidence.fileName;
    window.document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const prioritizationApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/prioritizations${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      prioritizations: Array.isArray(data?.prioritizations)
        ? data.prioritizations
        : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/prioritizations/${id}`);
    return data?.prioritization ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/prioritizations", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.prioritization ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/prioritizations/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.prioritization ?? null;
  },
  async updateItem(runId, itemId, payload) {
    const data = await request(
      `/api/iap/prioritizations/${runId}/items/${itemId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.prioritization ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(
      `/api/iap/prioritizations/${id}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.prioritization ?? null;
  },
  async archive(id) {
    await request(`/api/iap/prioritizations/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/prioritizations/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.prioritization ?? null;
  },
};

export const schedulingApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/schedules${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      schedules: Array.isArray(data?.schedules) ? data.schedules : [],
      plans: Array.isArray(data?.plans) ? data.plans : [],
      auditors: Array.isArray(data?.auditors) ? data.auditors : [],
      teamRoles: Array.isArray(data?.teamRoles) ? data.teamRoles : [],
      capacities: Array.isArray(data?.capacities) ? data.capacities : [],
    };
  },
  async checkConflicts(engagementId, payload) {
    const data = await request(
      `/api/iap/schedules/${engagementId}/conflicts`,
      { method: "POST", body: payload, csrf: true },
    );
    return Array.isArray(data?.conflicts) ? data.conflicts : [];
  },
  async update(engagementId, payload) {
    const data = await request(`/api/iap/schedules/${engagementId}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return {
      schedule: data?.schedule ?? null,
      conflicts: Array.isArray(data?.conflicts) ? data.conflicts : [],
    };
  },
  async cancel(engagementId, payload) {
    await request(`/api/iap/schedules/${engagementId}/cancel`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateCapacity(userId, payload) {
    await request(`/api/iap/schedules/capacities/${userId}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
};

export const resourceCapacityApi = {
  async show(fiscalYear = "") {
    const query = fiscalYear ? `?fiscalYear=${fiscalYear}` : "";
    const data = await request(`/api/iap/resources${query}`);
    return {
      fiscalYear: data?.fiscalYear ?? new Date().getFullYear(),
      years: Array.isArray(data?.years) ? data.years : [],
      auditors: Array.isArray(data?.auditors) ? data.auditors : [],
      engagements: Array.isArray(data?.engagements) ? data.engagements : [],
      specializations: Array.isArray(data?.specializations)
        ? data.specializations
        : [],
      unavailabilityTypes: Array.isArray(data?.unavailabilityTypes)
        ? data.unavailabilityTypes
        : [],
      proficiencyLevels: Array.isArray(data?.proficiencyLevels)
        ? data.proficiencyLevels
        : [],
      summary: data?.summary ?? {},
    };
  },
  async updateCapacity(userId, payload) {
    await request(`/api/iap/resources/auditors/${userId}/capacity`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async createUnavailability(userId, payload) {
    await request(`/api/iap/resources/auditors/${userId}/unavailability`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateUnavailability(id, payload) {
    await request(`/api/iap/resources/unavailability/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async archiveUnavailability(id) {
    await request(`/api/iap/resources/unavailability/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restoreUnavailability(id) {
    await request(`/api/iap/resources/unavailability/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
  },
  async syncSkills(userId, skills) {
    await request(`/api/iap/resources/auditors/${userId}/skills`, {
      method: "PUT",
      body: { skills },
      csrf: true,
    });
  },
  async syncRequirements(engagementId, requirements) {
    await request(
      `/api/iap/resources/engagements/${engagementId}/requirements`,
      {
        method: "PUT",
        body: { requirements },
        csrf: true,
      },
    );
  },
};

export const aemsDashboardApi = {
  async show(filters = {}) {
    const query = queryFrom(filters);
    return request(
      `/api/aems/dashboard${query.size ? `?${query.toString()}` : ""}`,
    );
  },
  async export(filters = {}) {
    const query = queryFrom(filters);
    const response = await fetch(
      `/api/aems/dashboard/export${query.size ? `?${query.toString()}` : ""}`,
      {
        credentials: "include",
        headers: {
          Accept: "text/csv",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const blob = await response.blob();
    const disposition = response.headers.get("content-disposition") ?? "";
    const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    const simpleName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
    const fileName = encodedName
      ? decodeURIComponent(encodedName)
      : simpleName || "aems-engagement-progress.csv";
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
  async exportQueues(filters = {}) {
    const query = queryFrom(filters);
    const response = await fetch(
      `/api/aems/dashboard/queues/export${query.size ? `?${query.toString()}` : ""}`,
      {
        credentials: "include",
        headers: {
          Accept: "text/csv",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const blob = await response.blob();
    const disposition = response.headers.get("content-disposition") ?? "";
    const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    const simpleName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
    const fileName = encodedName
      ? decodeURIComponent(encodedName)
      : simpleName || "aems-work-queues.csv";
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsEngagementApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/aems/engagements${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      engagements: Array.isArray(data?.engagements) ? data.engagements : [],
      summary: data?.summary ?? {
        total: 0,
        planned: 0,
        special: 0,
        ongoing: 0,
        archived: 0,
      },
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/aems/engagements/${id}`);
    return data?.engagement ?? null;
  },
  async scope(id) {
    const data = await request(`/api/aems/engagements/${id}/scope`);
    return {
      scope: data?.scope ?? null,
      contract: data?.contract ?? {},
    };
  },
  async updateScope(id, payload) {
    const data = await request(`/api/aems/engagements/${id}/scope`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.scope ?? null;
  },
  async importOptions() {
    const data = await request("/api/aems/engagements/import-options");
    return Array.isArray(data?.iapEngagements) ? data.iapEngagements : [];
  },
  async importFromIap(payload) {
    const data = await request("/api/aems/engagements/import", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.engagement ?? null;
  },
  async createSpecial(payload) {
    const data = await request("/api/aems/engagements", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.engagement ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/aems/engagements/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.engagement ?? null;
  },
  async archive(id) {
    await request(`/api/aems/engagements/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/aems/engagements/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.engagement ?? null;
  },
};

export const aemsTeamApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/team`);
  },
  async assign(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/team`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.teamMember ?? null;
  },
  async update(engagementId, memberId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/team/${memberId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.teamMember ?? null;
  },
  async reassign(engagementId, memberId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/team/${memberId}/reassign`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.teamMember ?? null;
  },
  async end(engagementId, memberId, reason) {
    await request(
      `/api/aems/engagements/${engagementId}/team/${memberId}`,
      { method: "DELETE", body: { reason }, csrf: true },
    );
  },
};

/** Provider-aware team safeguards, declarations, and independent approval. */
export const aemsTeamSafeguardApi = {
  async show(engagementId) {
    const data = await request(`/api/aems/engagements/${engagementId}/team/safeguards`);
    return data?.data ?? data ?? null;
  },
  async assess(engagementId) {
    const data = await request(`/api/aems/engagements/${engagementId}/team/safeguards/assess`, {
      method: "POST",
      csrf: true,
    });
    return data?.assessment ?? data?.data?.assessment ?? null;
  },
  async approve(engagementId, payload = {}) {
    const data = await request(`/api/aems/engagements/${engagementId}/team/safeguards/approve`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.assessment ?? data?.data?.assessment ?? null;
  },
  async submitDeclaration(engagementId, memberId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/team/${memberId}/safeguards/declarations`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.declaration ?? data?.data?.declaration ?? null;
  },
  async reviewDeclaration(engagementId, memberId, declarationId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/team/${memberId}/safeguards/declarations/${declarationId}/review`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.declaration ?? data?.data?.declaration ?? null;
  },
};

export const aemsAeoApi = {
  async recipientAcknowledgements() {
    const data = await request("/api/aems/aeo-acknowledgements");
    return data?.data ?? data ?? { distributions: [] };
  },
  async acknowledgeRecipient(distributionId, payload) {
    const data = await request(
      `/api/aems/aeo-acknowledgements/${distributionId}/acknowledge`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.distribution ?? data?.data?.distribution ?? null;
  },
  async downloadRecipientPdf(distributionId, filename = `issued-aeo-${distributionId}.pdf`) {
    const response = await fetch(
      `/api/aems/aeo-acknowledgements/${distributionId}/pdf`,
      {
        credentials: "include",
        headers: {
          Accept: "application/pdf",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/aeo`);
  },
  async create(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/aeo`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.order ?? null;
  },
  async update(engagementId, orderId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.order ?? null;
  },
  async transition(engagementId, orderId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.order ?? null;
  },
  async revise(engagementId, orderId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.order ?? null;
  },
  async amend(engagementId, orderId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}/amend`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.order ?? null;
  },
  async distribution(engagementId, orderId) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}/distribution`,
    );
    return data?.data ?? data ?? null;
  },
  async distribute(engagementId, orderId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}/distribution`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.distribution ?? null;
  },
  async acknowledgeDistribution(engagementId, orderId, distributionId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}/distribution/${distributionId}/acknowledge`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.distribution ?? null;
  },
  async downloadPdf(engagementId, order) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/aeo/${order.id}/pdf`,
      {
        credentials: "include",
        headers: {
          Accept: "application/pdf",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = `${order.orderCode}-approved.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsAepApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/aep`);
  },
  async create(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/aep`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async update(engagementId, planId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aep/${planId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.plan ?? null;
  },
  async transition(engagementId, planId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aep/${planId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.plan ?? null;
  },
  async revise(engagementId, planId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aep/${planId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.plan ?? null;
  },
};

export const aemsPlanningPackageApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/planning-package`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/planning-package`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.package ?? null;
  },
  async update(engagementId, packageId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/planning-package/${packageId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.package ?? null;
  },
  async transition(engagementId, packageId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/planning-package/${packageId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.package ?? null;
  },
  async revise(engagementId, packageId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/planning-package/${packageId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.package ?? null;
  },
};

export const aemsProgramApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/programs`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.program ?? null;
  },
  async update(engagementId, programId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.program ?? null;
  },
  async transition(engagementId, programId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.program ?? null;
  },
  async revise(engagementId, programId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.program ?? null;
  },
  async addProcedure(engagementId, programId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.procedure ?? null;
  },
  async updateProcedure(engagementId, programId, procedureId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures/${procedureId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.procedure ?? null;
  },
  async removeProcedure(engagementId, programId, procedureId, payload) {
    await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures/${procedureId}`,
      { method: "DELETE", body: payload, csrf: true },
    );
  },
  async progressProcedure(engagementId, programId, procedureId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures/${procedureId}/progress`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.procedure ?? null;
  },
  async reviewProcedure(engagementId, programId, procedureId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures/${procedureId}/review`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.procedure ?? null;
  },
};

export const aemsFieldworkApi = {
  async show(engagementId) {
    const data = await request(`/api/aems/engagements/${engagementId}/fieldwork`);
    return data ?? null;
  },
  async create(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/fieldwork`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.fieldworkRecord ?? null;
  },
  async update(engagementId, recordId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/fieldwork/${recordId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.fieldworkRecord ?? null;
  },
  async transition(engagementId, recordId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/fieldwork/${recordId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.fieldworkRecord ?? null;
  },
};

export const aemsWorkingPaperApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/working-papers`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/working-papers`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.workingPaper ?? null;
  },
  async update(engagementId, paperId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/working-papers/${paperId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.workingPaper ?? null;
  },
  async transition(engagementId, paperId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/working-papers/${paperId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.workingPaper ?? null;
  },
  async revise(engagementId, paperId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/working-papers/${paperId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.workingPaper ?? null;
  },
};

function evidenceForm(payload) {
  const body = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    body.append(
      key,
      Array.isArray(value) ? JSON.stringify(value) : value,
    );
  });
  return body;
}

export const aemsEvidenceApi = {
  async upload(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.evidence ?? null;
  },
  async replace(engagementId, evidenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence/${evidenceId}/revisions`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.evidence ?? null;
  },
  async transition(engagementId, evidenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence/${evidenceId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.evidence ?? null;
  },
  async download(engagementId, evidence) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/evidence/${evidence.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = evidence.fileName || `${evidence.evidenceCode}.bin`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
  async linkReport(engagementId, evidenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence/${evidenceId}/report-links`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.evidence ?? null;
  },
};

export const aemsEvidenceRequestApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/evidence-requests`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence-requests`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.evidenceRequest ?? null;
  },
  async update(engagementId, requestId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence-requests/${requestId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.evidenceRequest ?? null;
  },
  async transition(engagementId, requestId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence-requests/${requestId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.evidenceRequest ?? null;
  },
  async receive(engagementId, requestId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence-requests/${requestId}/evidence`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.evidenceRequest ?? null;
  },
  async assess(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence-assessments`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async approveException(engagementId, assessmentId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence-assessments/${assessmentId}/approve-exception`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
};

export const cmsEvidenceRequestApi = {
  async list() {
    const data = await request("/api/cms/evidence-requests");
    return data?.requests ?? [];
  },
  async acknowledge(requestId, payload) {
    const data = await request(
      `/api/cms/evidence-requests/${requestId}/acknowledge`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.evidenceRequest ?? null;
  },
  async submitResponse(requestId, payload) {
    const data = await request(
      `/api/cms/evidence-requests/${requestId}/responses`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.response ?? null;
  },
};

export const aemsFindingApi = {
  async engagements() {
    const data = await request("/api/aems/findings-workspaces");
    return data?.engagements ?? [];
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/findings-workspace`);
  },
  async createIssue(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/issues`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.issue ?? null;
  },
  async updateIssue(engagementId, issueId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/issues/${issueId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.issue ?? null;
  },
  async transitionIssue(engagementId, issueId, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/issues/${issueId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
  },
  async createTransmittal(engagementId, findingId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/transmittals`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.transmittal ?? null;
  },
  async transitionTransmittalRecipient(
    engagementId,
    findingId,
    transmittalId,
    recipientId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/transmittals/${transmittalId}/recipients/${recipientId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.recipient ?? null;
  },
  async createFinding(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.finding ?? null;
  },
  async updateFinding(engagementId, findingId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.finding ?? null;
  },
  async transitionFinding(engagementId, findingId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.finding ?? null;
  },
  async reviseFinding(engagementId, findingId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/revisions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.finding ?? null;
  },
  async saveRecommendation(engagementId, findingId, recommendationId, payload) {
    const suffix = recommendationId ? `/${recommendationId}` : "";
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/recommendations${suffix}`,
      {
        method: recommendationId ? "PUT" : "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.recommendation ?? null;
  },
  async removeRecommendation(engagementId, findingId, recommendationId, payload) {
    await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/recommendations/${recommendationId}`,
      { method: "DELETE", body: payload, csrf: true },
    );
  },
  async createResponse(engagementId, findingId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.response ?? null;
  },
  async updateResponse(engagementId, findingId, responseId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.response ?? null;
  },
  async transitionResponse(engagementId, findingId, responseId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.response ?? null;
  },
  async reviseResponse(engagementId, findingId, responseId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/revisions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.response ?? null;
  },
  async uploadResponseAttachment(
    engagementId,
    findingId,
    responseId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/attachments`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.attachment ?? null;
  },
  async saveRejoinder(
    engagementId,
    findingId,
    responseId,
    rejoinderId,
    payload,
  ) {
    const suffix = rejoinderId ? `/${rejoinderId}` : "";
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/rejoinders${suffix}`,
      {
        method: rejoinderId ? "PUT" : "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.rejoinder ?? null;
  },
  async finalizeRejoinder(
    engagementId,
    findingId,
    responseId,
    rejoinderId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/rejoinders/${rejoinderId}/finalize`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.rejoinder ?? null;
  },
  async uploadRejoinderAttachment(
    engagementId,
    findingId,
    responseId,
    rejoinderId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/rejoinders/${rejoinderId}/attachments`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.attachment ?? null;
  },
  async downloadAttachment(engagementId, findingId, attachment) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/dialogue-attachments/${attachment.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = attachment.fileName || `${attachment.attachmentCode}.bin`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsExitConferenceApi = {
  async engagements() {
    const data = await request("/api/aems/exit-conference-workspaces");
    return data?.engagements ?? [];
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/exit-conferences`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async update(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async complete(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/complete`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async transition(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async uploadAttachment(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/attachments`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.attachment ?? null;
  },
  async acknowledge(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/acknowledgements`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.acknowledgement ?? null;
  },
  async downloadAttachment(engagementId, conferenceId, attachment) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/attachments/${attachment.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = attachment.fileName || `${attachment.attachmentCode}.bin`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsLifecycleApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/lifecycle`);
  },
  async transition(engagementId, action, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
  },
};

export const aemsEntryConferenceApi = {
  async engagements() {
    const data = await request("/api/aems/entry-conference-workspaces");
    return data?.engagements ?? [];
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/entry-conference`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async update(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async transition(engagementId, conferenceId, action, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async acknowledge(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}/acknowledgements`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.acknowledgement ?? null;
  },
  async uploadAttachment(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}/attachments`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.attachment ?? null;
  },
  async downloadAttachment(engagementId, conferenceId, attachment) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}/attachments/${attachment.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = attachment.fileName || `${attachment.attachmentCode}.bin`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

/** AEMS-7A/7B engagement work queue and dialogue exchange contract. */
export const aemsWorkQueueApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/work-queue`);
  },
  async createTask(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/tasks`, { method: "POST", body: payload, csrf: true });
    return data?.task ?? null;
  },
  async updateTask(engagementId, taskId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/tasks/${taskId}`, { method: "PUT", body: payload, csrf: true });
    return data?.task ?? null;
  },
  async transitionTask(engagementId, taskId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/tasks/${taskId}/transition`, { method: "POST", body: payload, csrf: true });
    return data?.task ?? null;
  },
  async createReviewNote(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/review-notes`, { method: "POST", body: payload, csrf: true });
    return data?.reviewNote ?? null;
  },
  async updateReviewNote(engagementId, noteId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/review-notes/${noteId}`, { method: "PUT", body: payload, csrf: true });
    return data?.reviewNote ?? null;
  },
  async transitionReviewNote(engagementId, noteId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/review-notes/${noteId}/transition`, { method: "POST", body: payload, csrf: true });
    return data?.reviewNote ?? null;
  },
  async recordDueProcess(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/due-process`, { method: "POST", body: payload, csrf: true });
    return data?.dueProcess ?? null;
  },
  async reviewEscalationCandidate(engagementId, candidateId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/escalation-candidates/${candidateId}/review`, { method: "POST", body: payload, csrf: true });
    return data?.candidate ?? null;
  },
};

export const aemsCompletionAssessmentApi = {
  async show(engagementId) {
    return request(
      `/api/aems/engagements/${engagementId}/completion-assessments`,
    );
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async update(engagementId, assessmentId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments/${assessmentId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async transition(engagementId, assessmentId, action, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments/${assessmentId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async acceptBlocker(
    engagementId,
    assessmentId,
    itemId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments/${assessmentId}/items/${itemId}/accept-blocker`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async revise(engagementId, assessmentId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments/${assessmentId}/revisions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
};

/** AEMS-9A completion, CMS transfer, and ARMIS effort reconciliation contract. */
export const aemsCompletionTransferApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/completion-transfer`);
  },
  async reconcile(engagementId) {
    return request(
      `/api/aems/engagements/${engagementId}/completion-transfer/reconcile`,
      { method: "POST", body: {}, csrf: true },
    );
  },
  async approve(engagementId, type, id, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/completion-transfer/${type}/${id}/approve`,
      { method: "POST", body: payload, csrf: true },
    );
  },
};

export const aemsClosureApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/closure`);
  },
  async create(engagementId, payload) {
    return request(`/api/aems/engagements/${engagementId}/closure`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async update(engagementId, closureId, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/closures/${closureId}`,
      { method: "PUT", body: payload, csrf: true },
    );
  },
  async refreshChecklist(engagementId, closureId) {
    return request(
      `/api/aems/engagements/${engagementId}/closures/${closureId}/refresh-checklist`,
      { method: "POST", csrf: true },
    );
  },
  async transition(engagementId, closureId, action, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/closures/${closureId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
  },
  async saveRetention(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/retention`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.retention ?? null;
  },
  async approveRetention(engagementId, retentionId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/retention/${retentionId}/approve`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.retention ?? null;
  },
  async records(engagementId, query = "") {
    const suffix = query ? `?q=${encodeURIComponent(query)}` : "";
    return request(`/api/aems/engagements/${engagementId}/records${suffix}`);
  },
  async calendar(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/calendar`);
  },
  async createMilestone(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/calendar/milestones`, { method: "POST", body: payload, csrf: true });
    return data?.milestone ?? null;
  },
  async updateMilestone(engagementId, milestoneId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/calendar/milestones/${milestoneId}`, { method: "PUT", body: payload, csrf: true });
    return data?.milestone ?? null;
  },
  async transitionMilestone(engagementId, milestoneId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/calendar/milestones/${milestoneId}/transition`, { method: "POST", body: payload, csrf: true });
    return data?.milestone ?? null;
  },
  async archiveRetention(engagementId, retentionId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/retention/${retentionId}/archive`, { method: "POST", body: payload, csrf: true });
    return data?.retention ?? null;
  },
  async releaseLegalHold(engagementId, retentionId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/retention/${retentionId}/legal-hold-release`, { method: "POST", body: payload, csrf: true });
    return data?.retention ?? null;
  },
  async reviewDestruction(engagementId, retentionId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/retention/${retentionId}/destruction-review`, { method: "POST", body: payload, csrf: true });
    return data ?? null;
  },
  async recordDisposition(engagementId, retentionId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/retention/${retentionId}/disposition`, { method: "POST", body: payload, csrf: true });
    return data?.retention ?? null;
  },
  async addLesson(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/lessons-learned`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.lesson ?? null;
  },
  async excludeRecommendation(engagementId, recommendationId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/recommendations/${recommendationId}/cms-exclusion`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.recommendation ?? null;
  },
};

export const aemsDocumentIndexApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/document-index`);
  },
  async refresh(engagementId) {
    return request(
      `/api/aems/engagements/${engagementId}/document-index/refresh`,
      { method: "POST", csrf: true },
    );
  },
  async add(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/document-index`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.item ?? null;
  },
  async exclude(engagementId, itemId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/document-index/${itemId}/exclude`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.item ?? null;
  },
  exportUrl(engagementId) {
    return `/api/aems/engagements/${engagementId}/document-index/export`;
  },
};

export const aemsReopenApi = {
  async list(engagementId) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reopen-requests`,
    );
    return data?.requests ?? [];
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reopen-requests`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.request ?? null;
  },
  async transition(engagementId, requestId, action, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reopen-requests/${requestId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.request ?? null;
  },
};

export const aemsReportApi = {
  async engagements() {
    const data = await request("/api/aems/report-workspaces");
    return data?.engagements ?? [];
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/reports`);
  },
  async create(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.report ?? null;
  },
  async createInterim(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports/interim`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.report ?? null;
  },
  async revise(engagementId, reportId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/versions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.report ?? null;
  },
  async createFinal(engagementId, reportId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/final`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.report ?? null;
  },
  async transition(engagementId, reportId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.report ?? null;
  },
  async transferRecommendations(engagementId, reportId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/cms-transfer`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.transfers ?? [];
  },
  async distributionDecision(engagementId, reportId, versionId, recipientId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/versions/${versionId}/recipients/${recipientId}/decision`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.decision ?? null;
  },
  async successor(engagementId, reportId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports/${reportId}/successors`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.report ?? null;
  },
  async withdraw(engagementId, reportId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports/${reportId}/withdraw`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.report ?? null;
  },
  async authorityDecision(engagementId, reportId, versionId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports/${reportId}/versions/${versionId}/authority-decisions`, { method: "POST", body: payload, csrf: true });
    return data?.authorityDecision ?? null;
  },
  async signatory(engagementId, reportId, versionId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports/${reportId}/versions/${versionId}/signatories`, { method: "POST", body: payload, csrf: true });
    return data?.signatory ?? null;
  },
  async transmittal(engagementId, reportId, versionId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports/${reportId}/versions/${versionId}/transmittals`, { method: "POST", body: payload, csrf: true });
    return data?.transmittal ?? null;
  },
  async administrativeClose(engagementId, reportId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports/${reportId}/administrative-close`, { method: "POST", body: payload, csrf: true });
    return data?.report ?? null;
  },
  async export(engagementId, reportId, version, format) {
    const response = await fetch(`/api/aems/engagements/${engagementId}/reports/${reportId}/versions/${version.id}/exports/${format}`, { credentials: "include", headers: { Accept: format === "PDF" ? "application/pdf" : "text/csv", "X-Requested-With": "XMLHttpRequest" } });
    if (!response.ok) { const payload = await parseResponse(response); throw errorFromResponse(payload, response.status); }
    const url = URL.createObjectURL(await response.blob()); const link = document.createElement("a"); link.href = url; link.download = `${reportId}-v${version.versionNumber}.${format.toLowerCase()}`; document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
  },
  async download(engagementId, reportId, version) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/versions/${version.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/pdf",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download =
      version.pdfFileName ||
      `audit-report-v${version.versionNumber}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const armisApi = {
  async getMetadata() {
    return request("/api/armis/metadata");
  },
  async getFoundation() {
    return request("/api/armis/foundation");
  },
  async getIdentities() {
    return request("/api/armis/identities");
  },
  async getResources(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/armis/resources${query ? `?${query}` : ""}`);
  },
  async getResource(profileId) {
    return request(`/api/armis/resources/${profileId}`);
  },
  async getResourceEvents(profileId) {
    return request(`/api/armis/resources/${profileId}/events`);
  },
  async createResource(payload) {
    return request("/api/armis/resources", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateResource(profileId, payload) {
    return request(`/api/armis/resources/${profileId}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async transitionResource(profileId, payload) {
    return request(`/api/armis/resources/${profileId}/transition`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async restoreResource(profileId, lockVersion) {
    return request(`/api/armis/resources/${profileId}/restore`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async getCompetencyMetadata() {
    return request("/api/armis/competencies/metadata");
  },
  async getCompetencies(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/armis/competencies${query ? `?${query}` : ""}`);
  },
  async getCompetency(competencyId) {
    return request(`/api/armis/competencies/${competencyId}`);
  },
  async getCompetencyEvents(competencyId) {
    return request(`/api/armis/competencies/${competencyId}/events`);
  },
  async createCompetency(payload) {
    return request("/api/armis/competencies", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateCompetency(competencyId, payload) {
    return request(`/api/armis/competencies/${competencyId}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async submitCompetency(competencyId, lockVersion) {
    return request(`/api/armis/competencies/${competencyId}/submit`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async reviewCompetency(competencyId, payload) {
    return request(`/api/armis/competencies/${competencyId}/review`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async reviseCompetency(competencyId, payload) {
    return request(`/api/armis/competencies/${competencyId}/revisions`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async getPlanningMetadata() {
    return request("/api/armis/planning/metadata");
  },
  async getAvailability(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/armis/availability${query ? `?${query}` : ""}`);
  },
  async createAvailability(payload) {
    return request("/api/armis/availability", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateAvailability(id, payload) {
    return request(`/api/armis/availability/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async reviseAvailability(id, payload) {
    return request(`/api/armis/availability/${id}/revisions`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async submitAvailability(id, lockVersion) {
    return request(`/api/armis/availability/${id}/submit`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async reviewAvailability(id, payload) {
    return request(`/api/armis/availability/${id}/review`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async lockAvailability(id, lockVersion) {
    return request(`/api/armis/availability/${id}/lock`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async getCapacity(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/armis/capacity${query ? `?${query}` : ""}`);
  },
  async createCapacity(payload) {
    return request("/api/armis/capacity", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateCapacity(id, payload) {
    return request(`/api/armis/capacity/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async submitCapacity(id, lockVersion) {
    return request(`/api/armis/capacity/${id}/submit`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async reviewCapacity(id, payload) {
    return request(`/api/armis/capacity/${id}/review`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async lockCapacity(id, lockVersion) {
    return request(`/api/armis/capacity/${id}/lock`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async getWorkload(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/armis/workload${query ? `?${query}` : ""}`);
  },
  async createWorkload(payload) {
    return request("/api/armis/workload", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateWorkload(id, payload) {
    return request(`/api/armis/workload/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async submitWorkload(id, lockVersion) {
    return request(`/api/armis/workload/${id}/submit`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async reviewWorkload(id, payload) {
    return request(`/api/armis/workload/${id}/review`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async lockWorkload(id, lockVersion) {
    return request(`/api/armis/workload/${id}/lock`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async getUtilization(fiscalYear, resourceProfileId = null) {
    const query = queryFrom({ fiscalYear, resourceProfileId }).toString();
    return request(`/api/armis/utilization?${query}`);
  },
  async getAssignmentMetadata() {
    return request("/api/armis/assignments/metadata");
  },
  async getAssignments(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/armis/assignments${query ? `?${query}` : ""}`);
  },
  async getAssignment(id) {
    return request(`/api/armis/assignments/${id}`);
  },
  async createAssignment(payload) {
    return request("/api/armis/assignments", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateAssignment(id, payload) {
    return request(`/api/armis/assignments/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async submitAssignment(id, lockVersion) {
    return request(`/api/armis/assignments/${id}/submit`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async reviewAssignment(id, payload) {
    return request(`/api/armis/assignments/${id}/review`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async lockAssignment(id, lockVersion) {
    return request(`/api/armis/assignments/${id}/lock`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async reviseAssignment(id, payload) {
    return request(`/api/armis/assignments/${id}/revisions`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async getAssignmentConflicts(id) {
    return request(`/api/armis/assignments/${id}/conflicts`);
  },
  async getActuals(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/armis/actuals${query ? `?${query}` : ""}`);
  },
  async getActual(id) {
    return request(`/api/armis/actuals/${id}`);
  },
  async createActual(payload) {
    return request("/api/armis/actuals", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateActual(id, payload) {
    return request(`/api/armis/actuals/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async submitActual(id, lockVersion) {
    return request(`/api/armis/actuals/${id}/submit`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async reviewActual(id, payload) {
    return request(`/api/armis/actuals/${id}/review`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async lockActual(id, lockVersion) {
    return request(`/api/armis/actuals/${id}/lock`, {
      method: "POST",
      body: { lockVersion },
      csrf: true,
    });
  },
  async reviseActual(id, payload) {
    return request(`/api/armis/actuals/${id}/revisions`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
};

export const armisReportApi = {
  async getCatalog() {
    return request("/api/armis/reports");
  },
  async getRuns() {
    const data = await request("/api/armis/reports/runs");
    return data?.runs ?? [];
  },
  async getRun(runId) {
    const data = await request(`/api/armis/reports/runs/${runId}`);
    return data?.run ?? null;
  },
  async generate(reportCode, filters = {}) {
    const data = await request(`/api/armis/reports/${reportCode}/generate`, {
      method: "POST",
      body: filters,
      csrf: true,
    });
    return data?.run ?? null;
  },
  async createExport(runId, format) {
    const data = await request(`/api/armis/reports/runs/${runId}/exports`, {
      method: "POST",
      body: { format },
      csrf: true,
    });
    return data?.export ?? null;
  },
  async downloadExport(exportId, fileName) {
    return documentApi.downloadFile(
      `/api/armis/report-exports/${exportId}/download`,
      fileName || `armis-report-export-${exportId}`,
    );
  },
  async getAdministration() {
    return request("/api/armis/administration");
  },
};

export const armisProviderApi = {
  async getStatus() {
    return request("/api/armis/provider/status");
  },
  async getRuns() {
    const data = await request("/api/armis/provider/reconciliations");
    return data?.runs ?? [];
  },
  async getRun(runId) {
    const data = await request(`/api/armis/provider/reconciliations/${runId}`);
    return data?.run ?? null;
  },
  async generate(fiscalYear) {
    const data = await request("/api/armis/provider/reconciliations", {
      method: "POST",
      body: fiscalYear ? { fiscalYear: Number(fiscalYear) } : {},
      csrf: true,
    });
    return data?.run ?? null;
  },
  async review(runId, payload) {
    const data = await request(`/api/armis/provider/reconciliations/${runId}/review`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.review ?? null;
  },
  async activate(runId, reason) {
    const data = await request(`/api/armis/provider/reconciliations/${runId}/activate`, {
      method: "POST",
      body: { reason },
      csrf: true,
    });
    return data?.decision ?? null;
  },
  async rollback(reason) {
    const data = await request("/api/armis/provider/rollback", {
      method: "POST",
      body: { reason },
      csrf: true,
    });
    return data?.decision ?? null;
  },
};

export const armisProviderMonitoringApi = {
  async getStatus() {
    return request("/api/armis/provider/monitoring/status");
  },
  async getChecks() {
    const data = await request("/api/armis/provider/monitoring/checks");
    return data?.checks ?? [];
  },
  async getCheck(checkId) {
    const data = await request(`/api/armis/provider/monitoring/checks/${checkId}`);
    return data?.check ?? null;
  },
  async runCheck() {
    const data = await request("/api/armis/provider/monitoring/checks", {
      method: "POST",
      body: {},
      csrf: true,
    });
    return data?.check ?? null;
  },
};

export const cmsApi = {
  async getDashboard(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/cms/dashboard${query ? `?${query}` : ""}`);
  },
  async getReportCatalog() {
    return request("/api/cms/reports");
  },
  async getReportRuns() {
    const data = await request("/api/cms/reports/runs");
    return data?.runs ?? [];
  },
  async getReportRun(runId) {
    const data = await request(`/api/cms/reports/runs/${runId}`);
    return data?.run ?? null;
  },
  async generateReport(reportCode, filters = {}) {
    const data = await request(`/api/cms/reports/${reportCode}/generate`, {
      method: "POST",
      body: filters,
      csrf: true,
    });
    return data?.run ?? null;
  },
  async createReportExport(runId, format) {
    const data = await request(`/api/cms/reports/runs/${runId}/exports`, {
      method: "POST",
      body: { format },
      csrf: true,
    });
    return data?.export ?? null;
  },
  async downloadReportExport(exportId, fileName) {
    return documentApi.downloadFile(
      `/api/cms/report-exports/${exportId}/download`,
      fileName || `cms-report-export-${exportId}`,
    );
  },
  async getAutomationDashboard() {
    return request("/api/cms/automation/dashboard");
  },
  async getAutomationRules() {
    const data = await request("/api/cms/automation/rules");
    return data?.rules ?? [];
  },
  async createAutomationRule(payload) {
    const data = await request("/api/cms/automation/rules", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.rule ?? null;
  },
  async updateAutomationRule(ruleId, payload) {
    const data = await request(`/api/cms/automation/rules/${ruleId}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.rule ?? null;
  },
  async runAutomation(ruleCode) {
    const data = await request("/api/cms/automation/run", {
      method: "POST",
      body: ruleCode ? { ruleCode } : {},
      csrf: true,
    });
    return data ?? {};
  },
  async getAutomationRuns() {
    const data = await request("/api/cms/automation/runs");
    return data?.runs ?? [];
  },
  async getAutomationCandidates() {
    return request("/api/cms/automation/candidates");
  },
  async reviewClosureCandidate(candidateId, action, payload = {}) {
    const data = await request(`/api/cms/automation/closure-candidates/${candidateId}/review`, {
      method: "POST",
      body: { ...payload, action },
      csrf: true,
    });
    return data?.candidate ?? null;
  },
  async reviewEscalationCandidate(candidateId, action, payload = {}) {
    const data = await request(`/api/cms/automation/escalation-candidates/${candidateId}/review`, {
      method: "POST",
      body: { ...payload, action },
      csrf: true,
    });
    return data?.candidate ?? null;
  },
  async getAutomationReadiness(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/closure-readiness`);
  },
  async getRecommendations(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/cms/recommendations${query ? `?${query}` : ""}`);
  },
  async getRecommendation(recommendationId) {
    const data = await request(
      `/api/cms/recommendations/${recommendationId}`,
    );
    return data?.recommendation ?? null;
  },
  async getClosureOptions(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/closure-options`);
  },
  async getDispositionOptions(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/disposition-options`);
  },
  async getDispositions(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/dispositions`);
  },
  async createDisposition(recommendationId, payload) {
    const data = await request(`/api/cms/recommendations/${recommendationId}/dispositions`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async getDisposition(dispositionId) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}`);
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async updateDisposition(dispositionId, versionId, payload) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}`, { method: "PUT", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async submitDisposition(dispositionId, versionId, payload) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}/transitions/submit`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async startDispositionReview(dispositionId, versionId, payload) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}/transitions/start-review`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async returnDisposition(dispositionId, versionId, payload) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}/transitions/return`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async recommendDisposition(dispositionId, versionId, payload) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}/transitions/recommend`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async approveDisposition(dispositionId, versionId, payload) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}/transitions/approve`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async rejectDisposition(dispositionId, versionId, payload) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}/transitions/reject`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async createDispositionRevision(dispositionId, versionId, payload) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}/revisions`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async uploadDispositionEvidence(dispositionId, versionId, formData) {
    const data = await request(`/api/cms/disposition-requests/${dispositionId}/versions/${versionId}/evidence`, { method: "POST", body: formData, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async downloadDispositionEvidence(evidenceId, fileName = "disposition-evidence") {
    return documentApi.downloadFile(`/api/cms/disposition-evidence/${evidenceId}/download`, fileName);
  },
  async removeDispositionEvidence(evidenceId, payload) {
    const data = await request(`/api/cms/disposition-evidence/${evidenceId}`, { method: "DELETE", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async getReopeningOptions(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/reopening-options`);
  },
  async getReopeningRequests(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/reopenings`);
  },
  async createReopeningRequest(recommendationId, payload) {
    const data = await request(`/api/cms/recommendations/${recommendationId}/reopenings`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async getReopeningRequest(reopeningRequestId) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}`);
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async updateReopeningRequest(reopeningRequestId, versionId, payload) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}`, { method: "PUT", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async submitReopeningRequest(reopeningRequestId, versionId, payload) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}/transitions/submit`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async startReopeningReview(reopeningRequestId, versionId, payload = {}) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}/transitions/start-review`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async returnReopeningRequest(reopeningRequestId, versionId, payload) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}/transitions/return`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async recommendReopening(reopeningRequestId, versionId, payload) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}/transitions/recommend`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async approveReopening(reopeningRequestId, versionId, payload) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}/transitions/approve`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async rejectReopening(reopeningRequestId, versionId, payload) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}/transitions/reject`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async createReopeningRevision(reopeningRequestId, versionId, payload) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}/revisions`, { method: "POST", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async uploadReopeningEvidence(reopeningRequestId, versionId, formData) {
    const data = await request(`/api/cms/reopening-requests/${reopeningRequestId}/versions/${versionId}/evidence`, { method: "POST", body: formData, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async downloadReopeningEvidence(evidenceId, fileName = "reopening-evidence") {
    return documentApi.downloadFile(`/api/cms/reopening-evidence/${evidenceId}/download`, fileName);
  },
  async removeReopeningEvidence(evidenceId, payload) {
    const data = await request(`/api/cms/reopening-evidence/${evidenceId}`, { method: "DELETE", body: payload, csrf: true });
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async getClosureRequests(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/closure-requests`);
  },
  async createClosureRequest(recommendationId, payload) {
    const data = await request(`/api/cms/recommendations/${recommendationId}/closure-requests`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async getClosureRequest(closureRequestId) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}`);
    return data?.request ? { request: data.request, caseContext: data.caseContext } : null;
  },
  async updateClosureRequest(closureRequestId, versionId, payload) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}`, { method: "PUT", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async submitClosureRequest(closureRequestId, versionId, payload) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}/transitions/submit`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async startClosureReview(closureRequestId, versionId, payload) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}/transitions/start-review`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async returnClosureRequest(closureRequestId, versionId, payload) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}/transitions/return`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async recommendClosure(closureRequestId, versionId, payload) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}/transitions/recommend`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async approveClosure(closureRequestId, versionId, payload) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}/transitions/approve`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async rejectClosure(closureRequestId, versionId, payload) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}/transitions/reject`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async createClosureRevision(closureRequestId, versionId, payload) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}/revisions`, { method: "POST", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async uploadClosureEvidence(closureRequestId, versionId, formData) {
    const data = await request(`/api/cms/closure-requests/${closureRequestId}/versions/${versionId}/evidence`, { method: "POST", body: formData, csrf: true });
    return data?.request ?? null;
  },
  async downloadClosureEvidence(evidenceId, fileName = "closure-evidence") {
    return documentApi.downloadFile(`/api/cms/closure-evidence/${evidenceId}/download`, fileName);
  },
  async removeClosureEvidence(evidenceId, payload) {
    const data = await request(`/api/cms/closure-evidence/${evidenceId}`, { method: "DELETE", body: payload, csrf: true });
    return data?.request ?? null;
  },
  async getProgressUpdates(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/progress-updates`,
    );
  },
  async createProgressUpdate(recommendationId, payload) {
    const data = await request(
      `/api/cms/recommendations/${recommendationId}/progress-updates`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.progressUpdate ?? null;
  },
  async getProgressUpdate(progressUpdateId) {
    const data = await request(`/api/cms/progress-updates/${progressUpdateId}`);
    return data?.progressUpdate ?? null;
  },
  async updateProgressUpdate(progressUpdateId, versionId, payload) {
    const data = await request(
      `/api/cms/progress-updates/${progressUpdateId}/versions/${versionId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.progressUpdate ?? null;
  },
  async submitProgressUpdate(progressUpdateId, versionId, payload) {
    const data = await request(
      `/api/cms/progress-updates/${progressUpdateId}/versions/${versionId}/transitions/submit`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.progressUpdate ?? null;
  },
  async startProgressReview(progressUpdateId, versionId, payload) {
    const data = await request(
      `/api/cms/progress-updates/${progressUpdateId}/versions/${versionId}/transitions/start-review`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.progressUpdate ?? null;
  },
  async returnProgressUpdate(progressUpdateId, versionId, payload) {
    const data = await request(
      `/api/cms/progress-updates/${progressUpdateId}/versions/${versionId}/transitions/return`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.progressUpdate ?? null;
  },
  async recordProgressUpdate(progressUpdateId, versionId, payload) {
    const data = await request(
      `/api/cms/progress-updates/${progressUpdateId}/versions/${versionId}/transitions/record`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.progressUpdate ?? null;
  },
  async createProgressRevision(progressUpdateId, versionId, payload) {
    const data = await request(
      `/api/cms/progress-updates/${progressUpdateId}/versions/${versionId}/revisions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.progressUpdate ?? null;
  },
  async uploadProgressEvidence(progressUpdateId, versionId, formData) {
    const data = await request(
      `/api/cms/progress-updates/${progressUpdateId}/versions/${versionId}/evidence`,
      { method: "POST", body: formData, csrf: true },
    );
    return data?.evidence ?? null;
  },
  async downloadProgressEvidence(evidenceId, fileName = "supporting-evidence") {
    return documentApi.downloadFile(
      `/api/cms/progress-evidence/${evidenceId}/download`,
      fileName,
    );
  },
  async removeProgressEvidence(evidenceId, payload) {
    const data = await request(`/api/cms/progress-evidence/${evidenceId}`, {
      method: "DELETE",
      body: payload,
      csrf: true,
    });
    return data?.progressUpdate ?? null;
  },
  async getValidations(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/validations`,
    );
  },
  async getValidationOptions(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/validation-options`,
    );
  },
  async getExtensionOptions(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/extension-options`,
    );
  },
  async getExtensions(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/extensions`,
    );
  },
  async getExtensionHistory(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/extensions/history`,
    );
  },
  async createExtension(recommendationId, payload) {
    const data = await request(
      `/api/cms/recommendations/${recommendationId}/extensions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async getExtension(extensionId) {
    return request(`/api/cms/extensions/${extensionId}`);
  },
  async updateExtension(extensionId, versionId, payload) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async submitExtension(extensionId, versionId, payload) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}/transitions/submit`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async startExtensionReview(extensionId, versionId, payload) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}/transitions/start-review`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async returnExtension(extensionId, versionId, payload) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}/transitions/return`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async recommendExtension(extensionId, versionId, payload) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}/transitions/recommend`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async approveExtension(extensionId, versionId, payload) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}/transitions/approve`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async rejectExtension(extensionId, versionId, payload) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}/transitions/reject`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async createExtensionRevision(extensionId, versionId, payload) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}/revisions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.extension ?? null;
  },
  async uploadExtensionEvidence(extensionId, versionId, formData) {
    const data = await request(
      `/api/cms/extensions/${extensionId}/versions/${versionId}/evidence`,
      { method: "POST", body: formData, csrf: true },
    );
    return data?.extension ?? null;
  },
  async downloadExtensionEvidence(evidenceId, fileName = "extension-evidence") {
    return documentApi.downloadFile(
      `/api/cms/extension-evidence/${evidenceId}/download`,
      fileName,
    );
  },
  async removeExtensionEvidence(evidenceId, payload) {
    const data = await request(`/api/cms/extension-evidence/${evidenceId}`, {
      method: "DELETE",
      body: payload,
      csrf: true,
    });
    return data?.extension ?? null;
  },
  async createValidation(recommendationId, payload) {
    const data = await request(
      `/api/cms/recommendations/${recommendationId}/validations`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async getValidation(validationId) {
    const data = await request(`/api/cms/validations/${validationId}`);
    return data?.validation ?? null;
  },
  async getValidationAssignments(validationId) {
    return request(`/api/cms/validations/${validationId}/assignments`);
  },
  async assignValidator(validationId, payload) {
    const data = await request(
      `/api/cms/validations/${validationId}/assignments`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async endValidatorAssignment(validationId, assignmentId, payload) {
    const data = await request(
      `/api/cms/validations/${validationId}/assignments/${assignmentId}/end`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async updateValidation(validationId, versionId, payload) {
    const data = await request(
      `/api/cms/validations/${validationId}/versions/${versionId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async submitValidation(validationId, versionId, payload) {
    const data = await request(
      `/api/cms/validations/${validationId}/versions/${versionId}/transitions/submit`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async startValidationReview(validationId, versionId, payload) {
    const data = await request(
      `/api/cms/validations/${validationId}/versions/${versionId}/transitions/start-review`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async returnValidation(validationId, versionId, payload) {
    const data = await request(
      `/api/cms/validations/${validationId}/versions/${versionId}/transitions/return`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async finalizeValidation(validationId, versionId, payload) {
    const data = await request(
      `/api/cms/validations/${validationId}/versions/${versionId}/transitions/finalize`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async createValidationRevision(validationId, versionId, payload) {
    const data = await request(
      `/api/cms/validations/${validationId}/versions/${versionId}/revisions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.validation ?? null;
  },
  async uploadValidationEvidence(validationId, versionId, formData) {
    const data = await request(
      `/api/cms/validations/${validationId}/versions/${versionId}/evidence`,
      { method: "POST", body: formData, csrf: true },
    );
    return data?.evidence ?? null;
  },
  async downloadValidationEvidence(evidenceId, fileName = "validation-evidence") {
    return documentApi.downloadFile(
      `/api/cms/validation-evidence/${evidenceId}/download`,
      fileName,
    );
  },
  async removeValidationEvidence(evidenceId, payload) {
    const data = await request(`/api/cms/validation-evidence/${evidenceId}`, {
      method: "DELETE",
      body: payload,
      csrf: true,
    });
    return data?.validation ?? null;
  },
  async getAssignments(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/assignments`,
    );
  },
  async assignMonitor(recommendationId, payload) {
    return request(`/api/cms/recommendations/${recommendationId}/assignments`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async endMonitorAssignment(recommendationId, assignmentId, payload) {
    return request(
      `/api/cms/recommendations/${recommendationId}/assignments/${assignmentId}/end`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
  },
  async getActionPlanForRecommendation(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/action-plan`,
    );
  },
  async createActionPlan(recommendationId, payload) {
    const data = await request(
      `/api/cms/recommendations/${recommendationId}/action-plans`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async getActionPlan(actionPlanId) {
    const data = await request(`/api/cms/action-plans/${actionPlanId}`);
    return data?.actionPlan ?? null;
  },
  async updateActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}`,
      {
        method: "PUT",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async submitActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/transitions/submit`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async startActionPlanReview(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/transitions/start-review`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async returnActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/transitions/return`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async acceptActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/transitions/accept`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async reviseActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/revisions`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async getEscalations(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/escalations`);
  },
  async getEscalationOptions(recommendationId) {
    return request(`/api/cms/recommendations/${recommendationId}/escalation-options`);
  },
  async createEscalation(recommendationId, payload) {
    const data = await request(`/api/cms/recommendations/${recommendationId}/escalations`, { method: "POST", body: payload, csrf: true });
    return data?.escalation ?? null;
  },
  async getEscalation(escalationId) {
    const data = await request(`/api/cms/escalations/${escalationId}`);
    return data?.escalation ?? null;
  },
  async updateEscalationNotice(escalationId, versionId, payload) {
    const data = await request(`/api/cms/escalations/${escalationId}/notice-versions/${versionId}`, { method: "PUT", body: payload, csrf: true });
    return data?.escalation ?? null;
  },
  async submitEscalationNotice(escalationId, versionId, payload) { return this.escalationNoticeTransition(escalationId, versionId, "submit", payload); },
  async startEscalationNoticeReview(escalationId, versionId, payload) { return this.escalationNoticeTransition(escalationId, versionId, "start-review", payload); },
  async returnEscalationNotice(escalationId, versionId, payload) { return this.escalationNoticeTransition(escalationId, versionId, "return", payload); },
  async issueEscalationNotice(escalationId, versionId, payload) { return this.escalationNoticeTransition(escalationId, versionId, "issue", payload); },
  async escalationNoticeTransition(escalationId, versionId, action, payload) {
    const data = await request(`/api/cms/escalations/${escalationId}/notice-versions/${versionId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.escalation ?? null;
  },
  async createEscalationNoticeRevision(escalationId, versionId, payload) {
    const data = await request(`/api/cms/escalations/${escalationId}/notice-versions/${versionId}/revisions`, { method: "POST", body: payload, csrf: true });
    return data?.escalation ?? null;
  },
  async acknowledgeEscalation(escalationId, payload) {
    const data = await request(`/api/cms/escalations/${escalationId}/acknowledgements`, { method: "POST", body: payload, csrf: true });
    return data?.escalation ?? null;
  },
  async getEscalationResponse(escalationId) {
    const data = await request(`/api/cms/escalations/${escalationId}/response`);
    return data?.response ?? null;
  },
  async createEscalationResponse(escalationId, payload) {
    const data = await request(`/api/cms/escalations/${escalationId}/response`, { method: "POST", body: payload, csrf: true });
    return data?.response ?? null;
  },
  async updateEscalationResponse(responseId, versionId, payload) {
    const data = await request(`/api/cms/escalation-responses/${responseId}/versions/${versionId}`, { method: "PUT", body: payload, csrf: true });
    return data?.response ?? null;
  },
  async escalationResponseTransition(responseId, versionId, action, payload) {
    const data = await request(`/api/cms/escalation-responses/${responseId}/versions/${versionId}/transitions/${action}`, { method: "POST", body: payload, csrf: true });
    return data?.response ?? null;
  },
  async submitEscalationResponse(responseId, versionId, payload) { return this.escalationResponseTransition(responseId, versionId, "submit", payload); },
  async startEscalationResponseReview(responseId, versionId, payload) { return this.escalationResponseTransition(responseId, versionId, "start-review", payload); },
  async returnEscalationResponse(responseId, versionId, payload) { return this.escalationResponseTransition(responseId, versionId, "return", payload); },
  async acceptEscalationResponse(responseId, versionId, payload) { return this.escalationResponseTransition(responseId, versionId, "accept", payload); },
  async createEscalationResponseRevision(responseId, versionId, payload) {
    const data = await request(`/api/cms/escalation-responses/${responseId}/versions/${versionId}/revisions`, { method: "POST", body: payload, csrf: true });
    return data?.response ?? null;
  },
  async resolveEscalation(escalationId, payload) {
    const data = await request(`/api/cms/escalations/${escalationId}/resolve`, { method: "POST", body: payload, csrf: true });
    return data?.escalation ?? null;
  },
  async uploadEscalationNoticeEvidence(escalationId, versionId, formData) {
    const data = await request(`/api/cms/escalations/${escalationId}/notice-versions/${versionId}/evidence`, { method: "POST", body: formData, csrf: true });
    return data?.escalation ?? null;
  },
  async downloadEscalationNoticeEvidence(evidenceId, fileName = "escalation-notice-evidence") { return documentApi.downloadFile(`/api/cms/escalation-notice-evidence/${evidenceId}/download`, fileName); },
  async removeEscalationNoticeEvidence(evidenceId, payload) {
    const data = await request(`/api/cms/escalation-notice-evidence/${evidenceId}`, { method: "DELETE", body: payload, csrf: true });
    return data?.escalation ?? null;
  },
  async uploadEscalationResponseEvidence(responseId, versionId, formData) {
    const data = await request(`/api/cms/escalation-responses/${responseId}/versions/${versionId}/evidence`, { method: "POST", body: formData, csrf: true });
    return data?.response ?? null;
  },
  async downloadEscalationResponseEvidence(evidenceId, fileName = "escalation-response-evidence") { return documentApi.downloadFile(`/api/cms/escalation-response-evidence/${evidenceId}/download`, fileName); },
  async removeEscalationResponseEvidence(evidenceId, payload) {
    const data = await request(`/api/cms/escalation-response-evidence/${evidenceId}`, { method: "DELETE", body: payload, csrf: true });
    return data?.response ?? null;
  },
};

export const profileApi = {
  async show() {
    const data = await request("/api/profile");
    return data?.profile ?? null;
  },
  async update(profile) {
    return request("/api/profile", {
      method: "PUT",
      body: profile,
      csrf: true,
    });
  },
  async changePassword(passwords) {
    await request("/api/profile/password", {
      method: "PUT",
      body: passwords,
      csrf: true,
    });
  },
};
