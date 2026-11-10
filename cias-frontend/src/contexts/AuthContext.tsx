import {
  createContext,
  useCallback,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import {
  clearAuthSession,
  getStoredUser,
  isAuthenticated as checkIsAuthenticated,
  isDemoSession,
  saveAuthSession,
  type StoredUser,
} from "../lib/auth";
import { authService, type RuntimeConfig } from "../services/authService";

export const DEFAULT_RUNTIME_CONFIG: RuntimeConfig = {
  systemName: "Audit Governance Information System",
  systemShortName: "AGIS",
  organizationName: "City Government of Cagayan de Oro",
  systemVersion: "1.0.0",
  paginationSize: 25,
  dateFormat: "MMMM d, yyyy",
  timezone: "Asia/Manila",
  sessionTimeoutMinutes: 30,
  passwordMinLength: 8,
  fiscalYearStartMonth: 1,
  currentFiscalYear: 2026,
  documentUploadMaxMb: 25,
  notificationRefreshSeconds: 60,
  logoUrl: "/logo.png",
  defaultRiskLevelCode: "MEDIUM",
  defaultWorkflowSlaHours: 72,
  armisProviderMode: "ARMIS_AUTHORITATIVE",
  mailEnabled: false,
};

export type AuthContextValue = {
  user: StoredUser | null;
  isAuthenticated: boolean;
  loading: boolean;
  runtimeConfig: RuntimeConfig;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  refreshRuntimeConfig: () => Promise<void>;
};

export const AuthContext = createContext<AuthContextValue>({
  user: null,
  isAuthenticated: false,
  loading: true,
  runtimeConfig: DEFAULT_RUNTIME_CONFIG,
  logout: async () => {},
  refreshUser: async () => {},
  refreshRuntimeConfig: async () => {},
});

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<StoredUser | null>(() => getStoredUser());
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(() =>
    checkIsAuthenticated()
  );
  const [loading, setLoading] = useState(true);
  const [runtimeConfig, setRuntimeConfig] = useState<RuntimeConfig>(DEFAULT_RUNTIME_CONFIG);

  const refreshRuntimeConfig = useCallback(async () => {
    try {
      const config = await authService.runtimeConfiguration();
      if (config && Object.keys(config).length > 0) {
        setRuntimeConfig((prev) => ({ ...prev, ...config }));
      }
    } catch {
      // Retain safe defaults if network request fails
    }
  }, []);

  const refreshUser = useCallback(async () => {
    if (!checkIsAuthenticated()) {
      setLoading(false);
      return;
    }

    if (isDemoSession()) {
      setUser(getStoredUser());
      setIsAuthenticated(true);
      setLoading(false);
      return;
    }

    try {
      const liveUser = await authService.me();
      if (liveUser) {
        saveAuthSession(String(liveUser.id), liveUser);
        setUser(getStoredUser());
        setIsAuthenticated(true);
      } else {
        clearAuthSession();
        setUser(null);
        setIsAuthenticated(false);
      }
    } catch {
      setIsAuthenticated(checkIsAuthenticated());
      setUser(getStoredUser());
    } finally {
      setLoading(false);
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await authService.logout();
    } catch {
      // Silently catch network errors on logout
    } finally {
      clearAuthSession();
      setUser(null);
      setIsAuthenticated(false);
    }
  }, []);

  useEffect(() => {
    refreshUser();
    refreshRuntimeConfig();
  }, [refreshUser, refreshRuntimeConfig]);

  useEffect(() => {
    const handleAuthCleared = () => {
      setUser(null);
      setIsAuthenticated(false);
    };

    window.addEventListener("cias:auth-cleared", handleAuthCleared);
    return () => window.removeEventListener("cias:auth-cleared", handleAuthCleared);
  }, []);

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated,
        loading,
        runtimeConfig,
        logout,
        refreshUser,
        refreshRuntimeConfig,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
