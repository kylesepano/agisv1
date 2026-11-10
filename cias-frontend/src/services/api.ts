export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]>;

  constructor(
    message: string,
    {
      status = 0,
      errors = {},
    }: { status?: number; errors?: Record<string, string[]> } = {}
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

function getCookie(name: string): string {
  const prefix = `${name}=`;
  const cookie = document.cookie
    .split(";")
    .map((c) => c.trim())
    .find((c) => c.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : "";
}

const baseURL = (import.meta.env.VITE_API_BASE_URL || "/api").replace(/\/$/, "");
const serviceOrigin = /^https?:\/\//i.test(baseURL)
  ? new URL(baseURL).origin
  : "";

export function apiPath(path: string): string {
  if (path.startsWith("/api")) {
    return `${baseURL}${path.slice("/api".length)}`;
  }
  if (path.startsWith("/sanctum")) {
    return `${serviceOrigin}${path}`;
  }
  if (!path.startsWith("http://") && !path.startsWith("https://")) {
    return `${baseURL}${path.startsWith("/") ? path : `/${path}`}`;
  }
  return path;
}

export async function ensureCsrfCookie(): Promise<void> {
  let response: Response;
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000);

    response = await fetch(apiPath("/sanctum/csrf-cookie"), {
      credentials: "include",
      signal: controller.signal,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    clearTimeout(timeoutId);
  } catch (error: any) {
    if (error?.name === "AbortError") {
      throw new ApiError(
        "Connection timed out connecting to the server. Please check your backend and database."
      );
    }
    throw new ApiError(
      "Unable to connect to AGIS backend. Please make sure the server is running."
    );
  }

  if (!response.ok) {
    throw new ApiError("Failed to initialize security session (CSRF).", {
      status: response.status,
    });
  }
}

export interface RequestOptions extends Omit<RequestInit, "body"> {
  body?: unknown;
  csrf?: boolean;
  timeoutMs?: number;
}

export async function request<T = unknown>(
  path: string,
  options: RequestOptions = {}
): Promise<T> {
  const {
    method = "GET",
    body,
    csrf = false,
    timeoutMs = 25000,
    headers: customHeaders,
    ...rest
  } = options;

  if (csrf) {
    await ensureCsrfCookie();
  }

  const isFormData = body instanceof FormData;
  const headers: Record<string, string> = isFormData
    ? {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      }
    : {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      };

  const xsrfToken = getCookie("XSRF-TOKEN");
  if (xsrfToken) {
    headers["X-XSRF-TOKEN"] = xsrfToken;
  }

  if (customHeaders) {
    Object.assign(headers, customHeaders);
  }

  let response: Response;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

  try {
    response = await fetch(apiPath(path), {
      method,
      credentials: "include",
      signal: controller.signal,
      headers,
      body:
        body === undefined
          ? undefined
          : isFormData
          ? (body as FormData)
          : JSON.stringify(body),
      ...rest,
    });
    clearTimeout(timeoutId);
  } catch (error: any) {
    clearTimeout(timeoutId);
    if (error?.name === "AbortError") {
      throw new ApiError(
        "Request timed out. The backend server or database took too long to respond."
      );
    }
    throw new ApiError(
      error instanceof Error
        ? error.message
        : "Unable to connect to the AGIS server.",
      { status: 0 }
    );
  }

  if (response.status === 204) {
    return null as T;
  }

  const contentType = response.headers.get("content-type") || "";
  let payload: any = null;
  if (contentType.includes("application/json")) {
    try {
      payload = await response.json();
    } catch {
      payload = null;
    }
  }

  if (!response.ok) {
    const errors: Record<string, string[]> = payload?.errors || {};
    const firstValidationError = Object.values(errors)
      .flat()
      .find((msg) => typeof msg === "string") as string | undefined;

    const message =
      firstValidationError ||
      payload?.message ||
      (response.status === 401
        ? "Your session has expired. Please sign in again."
        : response.status === 403
        ? "You do not have permission to perform this action."
        : response.status === 423
        ? "Account is temporarily locked. Please try again later."
        : "The request could not be completed.");

    throw new ApiError(message, { status: response.status, errors });
  }

  return (payload?.data !== undefined ? payload.data : payload) as T;
}

export const api = {
  get: <T = unknown>(path: string, options?: RequestOptions) =>
    request<T>(path, { ...options, method: "GET" }),
  post: <T = unknown>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, { ...options, method: "POST", body }),
  put: <T = unknown>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, { ...options, method: "PUT", body }),
  patch: <T = unknown>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, { ...options, method: "PATCH", body }),
  delete: <T = unknown>(path: string, options?: RequestOptions) =>
    request<T>(path, { ...options, method: "DELETE" }),
};
