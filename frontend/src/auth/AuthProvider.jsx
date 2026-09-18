import { useCallback, useEffect, useMemo, useState } from "react";
import { ApiError, authApi, runtimeConfigurationApi } from "../services/api";
import { AuthContext } from "./auth-context";

const DEFAULT_RUNTIME_CONFIGURATION = {
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
  currentFiscalYear: new Date().getFullYear(),
  documentUploadMaxMb: 25,
  notificationRefreshSeconds: 60,
  iapDefaultAnnualPersonDays: 180,
  logoUrl: "/logo.png",
  defaultRiskLevelCode: "MEDIUM",
  defaultWorkflowSlaHours: 72,
  mailEnabled: false,
};

export default function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [sessionError, setSessionError] = useState("");
  const [demoAccounts, setDemoAccounts] = useState([]);
  const [demoLoading, setDemoLoading] = useState(true);
  const [demoError, setDemoError] = useState("");
  const [runtimeConfig, setRuntimeConfig] = useState(
    DEFAULT_RUNTIME_CONFIGURATION,
  );
  const [runtimeLoading, setRuntimeLoading] = useState(true);

  const refreshRuntimeConfiguration = useCallback(async () => {
    const configuration = await runtimeConfigurationApi.show();
    const merged = { ...DEFAULT_RUNTIME_CONFIGURATION, ...configuration };
    setRuntimeConfig(merged);
    document.title = `${merged.systemShortName} | ${merged.systemName}`;
    document
      .querySelector('meta[name="description"]')
      ?.setAttribute("content", merged.systemName);
    document
      .querySelector('link[rel="icon"]')
      ?.setAttribute("href", merged.logoUrl);
    return merged;
  }, []);

  useEffect(() => {
    let active = true;

    async function restoreSession() {
      try {
        const currentUser = await authApi.me();
        if (active) setUser(currentUser);
      } catch (error) {
        if (active && (!(error instanceof ApiError) || error.status !== 401)) {
          setSessionError(error.message);
        }
      } finally {
        if (active) setLoading(false);
      }
    }

    async function loadDemoAccounts() {
      try {
        const accounts = await authApi.demoAccounts();
        if (active) setDemoAccounts(accounts);
      } catch (error) {
        if (!active) return;

        if (error instanceof ApiError && error.status === 404) {
          setDemoAccounts([]);
        } else {
          setDemoError(
            error instanceof Error
              ? error.message
              : "Demo accounts could not be loaded.",
          );
        }
      } finally {
        if (active) setDemoLoading(false);
      }
    }

    async function loadRuntimeConfiguration() {
      try {
        await refreshRuntimeConfiguration();
      } catch {
        // Safe defaults keep the login screen usable while the API recovers.
      } finally {
        if (active) setRuntimeLoading(false);
      }
    }

    restoreSession();
    loadDemoAccounts();
    loadRuntimeConfiguration();

    return () => {
      active = false;
    };
  }, [refreshRuntimeConfiguration]);

  const value = useMemo(
    () => ({
      user,
      loading: loading || runtimeLoading,
      runtimeConfig,
      sessionError,
      demoAccounts,
      demoLoading,
      demoError,
      async login(credentials) {
        const authenticatedUser = await authApi.login(credentials);

        if (!authenticatedUser) {
          throw new ApiError(
            "The server did not return an authenticated user.",
          );
        }

        setSessionError("");
        setUser(authenticatedUser);
        return authenticatedUser;
      },
      async logout() {
        try {
          await authApi.logout();
          setSessionError("");
          setUser(null);
        } catch (error) {
          if (error instanceof ApiError && error.status === 401) {
            setSessionError("");
            setUser(null);
            return;
          }

          throw error;
        }
      },
      async refreshUser() {
        const currentUser = await authApi.me();
        setUser(currentUser);
        return currentUser;
      },
      refreshRuntimeConfiguration,
    }),
    [
      demoAccounts,
      demoError,
      demoLoading,
      loading,
      refreshRuntimeConfiguration,
      runtimeConfig,
      runtimeLoading,
      sessionError,
      user,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
