import type { AuthUser } from "../services/authService";

const AUTH_STORAGE_KEY = "cias-auth-session";
const AUTH_USER_KEY = "cias-user";

export type StoredUser = {
  id?: number | string;
  username: string;
  role: string;
  name?: string;
  employeeId?: string;
  email?: string;
  office?: string;
  officeCode?: string;
  permissions?: string[];
  isDemo?: boolean;
  [key: string]: unknown;
};

export type AuthSession = {
  token: string;
  loggedInAt: string;
  user?: StoredUser;
};

export function saveAuthSession(
  token: string,
  user?: Partial<AuthUser> & { username?: string; role?: string; isDemo?: boolean }
) {
  const normalizedUser: StoredUser = {
    id: user?.id,
    username: user?.username || user?.employeeId || "admin",
    employeeId: user?.employeeId || user?.username || "admin",
    name: user?.name || user?.username || "User",
    role:
      typeof user?.role === "string"
        ? user.role
        : user?.roleCode || "Administrator",
    roleCode: user?.roleCode,
    email: user?.email,
    office: user?.office,
    officeCode: user?.officeCode,
    permissions: user?.permissions || [],
    isDemo: Boolean(user?.isDemo),
  };

  const session: AuthSession = {
    token,
    loggedInAt: new Date().toISOString(),
    user: normalizedUser,
  };

  window.localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(session));
  window.localStorage.setItem(AUTH_USER_KEY, JSON.stringify(session.user));
}

export function getAuthSession(): AuthSession | null {
  const raw = window.localStorage.getItem(AUTH_STORAGE_KEY);

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as AuthSession;
  } catch {
    window.localStorage.removeItem(AUTH_STORAGE_KEY);
    return null;
  }
}

export function getStoredUser(): StoredUser | null {
  const raw = window.localStorage.getItem(AUTH_USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as StoredUser;
  } catch {
    return null;
  }
}

export function isDemoSession(): boolean {
  const user = getStoredUser();
  return Boolean(user?.isDemo);
}

export function clearAuthSession() {
  window.localStorage.removeItem(AUTH_STORAGE_KEY);
  window.localStorage.removeItem(AUTH_USER_KEY);
  window.localStorage.removeItem("cias-remembered-username");
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event("cias:auth-cleared"));
  }
}

export function isAuthenticated() {
  return Boolean(getAuthSession()?.token);
}
