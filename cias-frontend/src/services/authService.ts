import { api } from "./api";

export interface LoginCredentials {
  employeeId: string;
  password: string;
  remember?: boolean;
}

export interface AuthUser {
  id: number | string;
  employeeId: string;
  email?: string;
  name: string;
  firstName?: string;
  lastName?: string;
  role?: string;
  roleCode?: string;
  roles?: Array<{ id: number | string; code: string; name: string }>;
  permissions?: string[];
  office?: string;
  officeCode?: string;
  position?: string;
  [key: string]: unknown;
}

export interface DemoAccount {
  id: number | string;
  employeeId: string;
  password?: string;
  name: string;
  initials?: string;
  role: string;
  roleCode?: string;
  office?: string;
}

export interface RuntimeConfig {
  systemName?: string;
  systemShortName?: string;
  organizationName?: string;
  systemVersion?: string;
  [key: string]: unknown;
}

export const FALLBACK_DEMO_ACCOUNTS: DemoAccount[] = [
  {
    id: 1,
    employeeId: "AGIS-PLATFORM-001",
    password: "lala",
    name: "Juan dela Cruz",
    initials: "JD",
    role: "Platform Administrator",
    roleCode: "platform_admin",
    office: "AGIS System Administration",
  },
  {
    id: 2,
    employeeId: "AGIS-ADMIN-001",
    password: "lala",
    name: "Kim V. Lao",
    initials: "KL",
    role: "AGIS Administrator",
    roleCode: "agis_admin",
    office: "AGIS System Administration",
  },
  {
    id: 3,
    employeeId: "CIAS-HEAD-001",
    password: "lala",
    name: "Cherrybelle A. Lao",
    initials: "CL",
    role: "CIAS Management",
    roleCode: "cias_management",
    office: "City Internal Audit Office",
  },
  {
    id: 7,
    employeeId: "CIAS-AUD-001",
    password: "lala",
    name: "Charry Bagongon",
    initials: "CB",
    role: "AGIS User",
    roleCode: "agis_user",
    office: "City Internal Audit Office",
  },
  {
    id: 5,
    employeeId: "AUDITEE-001",
    password: "lala",
    name: "Maria L. Santos",
    initials: "MS",
    role: "Auditee Representative",
    roleCode: "auditee_representative",
    office: "Business Permits and Licensing Division",
  },
  {
    id: 6,
    employeeId: "MAYOR-001",
    password: "lala",
    name: "City Mayor",
    initials: "CM",
    role: "Read Only User",
    roleCode: "read_only",
    office: "Secretary to the Mayor",
  },
];

let inFlightRuntimeConfigPromise: Promise<RuntimeConfig> | null = null;
let inFlightMePromise: Promise<AuthUser | null> | null = null;
let cachedDemoAccounts: DemoAccount[] | null = null;
let inFlightDemoAccountsPromise: Promise<DemoAccount[]> | null = null;

export const authService = {
  async login(credentials: LoginCredentials): Promise<{ user: AuthUser }> {
    const data = await api.post<{ user: AuthUser }>("/api/login", credentials, {
      csrf: true,
    });
    return data;
  },

  async me(): Promise<AuthUser | null> {
    if (inFlightMePromise) {
      return inFlightMePromise;
    }
    inFlightMePromise = (async () => {
      try {
        const data = await api.get<{ user: AuthUser }>("/api/me");
        return data?.user || null;
      } catch {
        return null;
      } finally {
        inFlightMePromise = null;
      }
    })();
    return inFlightMePromise;
  },

  async logout(): Promise<void> {
    try {
      await api.post("/api/logout", {}, { csrf: true, timeoutMs: 5000 });
    } finally {
      inFlightMePromise = null;
    }
  },

  async demoAccounts(): Promise<DemoAccount[]> {
    if (cachedDemoAccounts) {
      return cachedDemoAccounts;
    }
    if (inFlightDemoAccountsPromise) {
      return inFlightDemoAccountsPromise;
    }

    inFlightDemoAccountsPromise = (async () => {
      try {
        const data = await api.get<DemoAccount[]>("/api/demo-accounts", {
          timeoutMs: 20000,
        });
        if (Array.isArray(data) && data.length > 0) {
          cachedDemoAccounts = data;
          return data;
        }
      } catch (err) {
        console.warn("Failed to load demo accounts from backend, using fallback:", err);
      } finally {
        inFlightDemoAccountsPromise = null;
      }
      return FALLBACK_DEMO_ACCOUNTS;
    })();

    return inFlightDemoAccountsPromise;
  },

  async runtimeConfiguration(): Promise<RuntimeConfig> {
    if (inFlightRuntimeConfigPromise) {
      return inFlightRuntimeConfigPromise;
    }
    inFlightRuntimeConfigPromise = (async () => {
      try {
        const data = await api.get<{ configuration: RuntimeConfig }>(
          "/api/runtime-configuration"
        );
        return data?.configuration || {};
      } catch {
        return {};
      } finally {
        inFlightRuntimeConfigPromise = null;
      }
    })();
    return inFlightRuntimeConfigPromise;
  },
};
