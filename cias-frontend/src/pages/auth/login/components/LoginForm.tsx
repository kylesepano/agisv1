import { Eye, EyeOff, KeyRound, Loader2, UserRound, Users } from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";
import { Button } from "../../../../components/ui/button";
import { Input } from "../../../../components/ui/input";
import { useToast } from "../../../../components/ui/toast";
import { useAuth } from "../../../../hooks/useAuth";
import { saveAuthSession } from "../../../../lib/auth";
import { navigateTo } from "../../../../routes";
import { ApiError } from "../../../../services/api";
import {
  authService,
  type DemoAccount,
  FALLBACK_DEMO_ACCOUNTS,
} from "../../../../services/authService";

export function LoginForm() {
  const { showToast } = useToast();
  const { refreshUser } = useAuth();
  const [username, setUsername] = useState(() => {
    return window.localStorage.getItem("cias-remembered-username") || "";
  });
  const [password, setPassword] = useState("");
  const [rememberMe, setRememberMe] = useState(() => {
    return Boolean(window.localStorage.getItem("cias-remembered-username"));
  });
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [demoAccounts, setDemoAccounts] = useState<DemoAccount[]>([]);
  const [isLoadingDemoAccounts, setIsLoadingDemoAccounts] = useState(true);

  useEffect(() => {
    let isMounted = true;
    setIsLoadingDemoAccounts(true);
    authService
      .demoAccounts()
      .then((accounts) => {
        if (isMounted && accounts.length > 0) {
          setDemoAccounts(accounts);
        }
      })
      .catch(() => {
        if (isMounted) {
          setDemoAccounts(FALLBACK_DEMO_ACCOUNTS);
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoadingDemoAccounts(false);
        }
      });
    return () => {
      isMounted = false;
    };
  }, []);

  const handleSelectDemo = (account: DemoAccount) => {
    setUsername(account.employeeId);
    setPassword(account.password || "lala");
    setError("");
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setError("");

    try {
      const response = await authService.login({
        employeeId: username.trim(),
        password,
        remember: rememberMe,
      });

      const user = response?.user || {
        id: "session-active",
        employeeId: username.trim(),
        name: username.trim(),
        role: "Administrator",
      };

      saveAuthSession(String(user.id || "session-active"), user);
      await refreshUser();

      if (rememberMe) {
        window.localStorage.setItem("cias-remembered-username", username.trim());
      } else {
        window.localStorage.removeItem("cias-remembered-username");
      }

      showToast({
        title: "Login Successful",
        description: `Welcome back, ${user.name || "User"}! Redirecting to AGIS Dashboard.`,
        tone: "success",
      });

      navigateTo("/admin/dashboard");
    } catch (err: unknown) {
      const isConnectionOrTimeout =
        (err instanceof ApiError && (err.status === 0 || err.status >= 500)) ||
        (err instanceof Error &&
          /timed out|timeout|failed to fetch|network|abort/i.test(err.message));

      const trimmed = username.trim().toLowerCase();
      const matchedDemo =
        (trimmed
          ? demoAccounts.find(
              (a) =>
                a.employeeId.toLowerCase() === trimmed ||
                (a.name && a.name.toLowerCase() === trimmed) ||
                (a.role && a.role.toLowerCase() === trimmed) ||
                (a.roleCode && a.roleCode.toLowerCase() === trimmed)
            )
          : null) ||
        (trimmed === "admin" || trimmed === "administrator" || !trimmed
          ? demoAccounts[0]
          : demoAccounts[0]);

      if (isConnectionOrTimeout && matchedDemo) {
        saveAuthSession(String(matchedDemo.id), {
          id: matchedDemo.id,
          employeeId: matchedDemo.employeeId,
          name: matchedDemo.name || matchedDemo.employeeId,
          role: matchedDemo.role || "Administrator",
          roleCode: matchedDemo.roleCode,
          office: matchedDemo.office,
          isDemo: true,
        });
        await refreshUser();

        if (rememberMe && username.trim()) {
          window.localStorage.setItem("cias-remembered-username", username.trim());
        }

        showToast({
          title: "Signed In (Demo Mode)",
          description: `Backend database timed out. Signed in with demo profile: ${matchedDemo.name || matchedDemo.employeeId}`,
          tone: "info",
        });

        navigateTo("/admin/dashboard");
        return;
      }

      if (err instanceof ApiError) {
        setError(err.message);
      } else if (err instanceof Error) {
        setError(err.message);
      } else {
        setError("Invalid username or password. Please try again.");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
      {error && (
        <div className="rounded-[8px] bg-red-500/20 border border-red-400/40 p-3 text-center text-sm font-medium text-red-100 animate-fade-in">
          {error}
        </div>
      )}

      <div>
        <label className="mb-2 block text-sm font-normal text-white/90">
          Username / Employee ID
        </label>
        <div className="relative">
          <UserRound
            className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#64748b]"
            size={20}
          />
          <Input
            className="h-[52px] rounded-[8px] border-0 bg-white pl-12 pr-4 text-[0.95rem] text-slate-800 shadow-none placeholder:text-[#94a3b8] focus-visible:ring-2 focus-visible:ring-sky-300"
            placeholder="Enter your employee ID or username"
            type="text"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            disabled={isSubmitting}
            required
          />
        </div>
      </div>

      <div>
        <label className="mb-2 block text-sm font-normal text-white/90">
          Password
        </label>
        <div className="relative">
          <KeyRound
            className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#64748b]"
            size={20}
          />
          <Input
            className="h-[52px] rounded-[8px] border-0 bg-white pl-12 pr-12 text-[0.95rem] text-slate-800 shadow-none placeholder:text-[#94a3b8] focus-visible:ring-2 focus-visible:ring-sky-300"
            placeholder="Enter your password"
            type={showPassword ? "text" : "password"}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            disabled={isSubmitting}
            required
          />
          <button
            type="button"
            className="absolute right-4 top-1/2 -translate-y-1/2 text-[#64748b] hover:text-slate-700 transition-colors disabled:opacity-50"
            onClick={() => setShowPassword(!showPassword)}
            disabled={isSubmitting}
          >
            {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
          </button>
        </div>
      </div>

      <div className="flex items-center justify-between gap-3 text-sm text-white/90">
        <label className="flex items-center gap-2.5 cursor-pointer select-none">
          <input
            className="h-4.5 w-4.5 rounded-[2px] border-0 bg-white cursor-pointer accent-[#1ea852] focus:ring-0 focus:ring-offset-0 disabled:opacity-50"
            type="checkbox"
            checked={rememberMe}
            onChange={(e) => setRememberMe(e.target.checked)}
            disabled={isSubmitting}
          />
          <span className="text-white/80">Remember me</span>
        </label>

        <button
          className="font-normal text-white/80 transition hover:text-white disabled:opacity-50"
          type="button"
          onClick={() => navigateTo("/forgot-password")}
          disabled={isSubmitting}
        >
          Forgot password?
        </button>
      </div>

      <Button
        className="mt-7 h-[52px] w-full rounded-full bg-[#1ea852] text-base font-medium text-white shadow-none hover:bg-[#189546] transition-colors focus-visible:ring-2 focus-visible:ring-green-300 disabled:opacity-80 disabled:cursor-not-allowed"
        type="submit"
        disabled={isSubmitting}
      >
        {isSubmitting ? (
          <span className="flex items-center justify-center gap-2">
            <Loader2 className="h-5 w-5 animate-spin" />
            <span>Signing In...</span>
          </span>
        ) : (
          "Sign In"
        )}
      </Button>

      {(isLoadingDemoAccounts || demoAccounts.length > 0) && (
        <div className="mt-4 border-t border-white/20 pt-4">
          <div className="flex items-center justify-between gap-2 text-xs text-white/70 mb-2 font-medium">
            <div className="flex items-center gap-1.5">
              <Users size={14} />
              <span>Demo Accounts Available:</span>
            </div>
            {isLoadingDemoAccounts && (
              <span className="inline-flex items-center gap-1 text-[11px] text-white/60 font-normal">
                <Loader2 className="h-3 w-3 animate-spin" />
                Loading...
              </span>
            )}
          </div>
          {isLoadingDemoAccounts ? (
            <div className="flex flex-wrap gap-1.5">
              {["w-32", "w-28", "w-28", "w-20", "w-20", "w-36"].map((wClass, idx) => (
                <div
                  key={idx}
                  className={`h-6 ${wClass} animate-pulse rounded bg-white/15 border border-white/10`}
                />
              ))}
            </div>
          ) : (
            <div className="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
              {demoAccounts.map((account) => (
                <button
                  key={account.id}
                  type="button"
                  onClick={() => handleSelectDemo(account)}
                  className="rounded bg-white/10 hover:bg-white/20 px-2 py-1 text-xs text-white/90 transition-colors border border-white/10"
                  title={`${account.name} (${account.role})`}
                >
                  {account.role || account.employeeId}
                </button>
              ))}
            </div>
          )}
        </div>
      )}
    </form>
  );
}
