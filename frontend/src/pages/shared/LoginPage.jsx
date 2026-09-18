import { useState } from "react";
import { Eye, EyeOff, KeyRound, UserRound } from "lucide-react";
import { useLocation, useNavigate } from "react-router";
import { useAuth } from "../../auth/auth-context";

/**
 * Authenticates users by employee ID and displays seeded demo accounts without
 * exposing authentication implementation details to the dashboard shell.
 */
export default function LoginPage() {
  const {
    login,
    sessionError,
    demoAccounts,
    demoLoading,
    demoError,
    runtimeConfig,
  } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [employeeId, setEmployeeId] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(true);
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setError("");
    setBusy(true);

    try {
      await login({ employeeId: employeeId.trim(), password, remember });
      const requestedPath = location.state?.from;
      navigate(
        typeof requestedPath === "string" && requestedPath !== "/login"
          ? requestedPath
          : "/dashboard",
        { replace: true },
      );
    } catch (loginError) {
      setError(
        loginError instanceof Error
          ? loginError.message
          : "Unable to sign in. Please try again.",
      );
    } finally {
      setBusy(false);
    }
  }

  function chooseDemo(account) {
    setEmployeeId(account.employeeId);
    setPassword(account.password);
    setError("");
  }

  const showDemoSection =
    demoLoading || demoAccounts.length > 0 || Boolean(demoError);

  return (
    <main className="flex min-h-screen flex-col bg-[#084c86] font-['Segoe_UI',Arial,sans-serif] text-white">
      <div className="relative grid flex-1 overflow-hidden bg-[linear-gradient(90deg,rgba(8,112,188,.3),rgba(3,58,111,.72)),url('/loginbackground.jpg')] bg-cover bg-center lg:grid-cols-[56%_44%]">
        <section className="relative hidden min-h-[720px] lg:block">
          <div className="absolute inset-x-0 bottom-10 flex items-end gap-7 px-10 xl:px-14">
            <img
              className="h-28 w-28 object-contain drop-shadow-xl"
              src="/cdologo.png"
              alt="City of Cagayan de Oro"
            />
            <img
              className="h-24 w-auto max-w-64 object-contain drop-shadow-xl"
              src="/rise.png"
              alt="CdeO RISE governance platform"
            />
          </div>
        </section>

        <section className="relative z-10 flex items-center justify-center p-4 sm:p-8 lg:justify-start lg:pl-0 lg:pr-7">
          <div className="w-full max-w-[590px] rounded-md border border-white/70 bg-[linear-gradient(145deg,rgba(8,85,145,.96),rgba(8,67,125,.94))] px-6 py-7 shadow-2xl backdrop-blur-sm transition-transform sm:px-11 sm:py-9 lg:translate-x-4 xl:translate-x-6">
            <div className="text-center">
              <img
                className="mx-auto h-24 w-24 object-contain sm:h-28 sm:w-28"
                src={runtimeConfig.logoUrl}
                alt="City Internal Audit Department"
              />
              <h1 className="mt-4 text-lg font-bold sm:text-xl">
                {runtimeConfig.systemName}
              </h1>
              <div className="mx-auto mt-4 h-px w-full bg-white/75" />
              <h2 className="mt-7 text-2xl font-normal">
                Sign in to your account
              </h2>
              <p className="mt-1 text-sm text-blue-100">
                Enter your credentials to access {runtimeConfig.systemShortName}
                .
              </p>
            </div>

            <form className="mt-7 space-y-4" onSubmit={handleSubmit}>
              <div>
                <label
                  className="mb-2 block text-sm font-medium"
                  htmlFor="employee-id"
                >
                  Employee ID
                </label>
                <div className="flex h-14 items-center rounded-md border border-transparent bg-white text-slate-700 shadow-sm transition focus-within:border-slate-200 focus-within:ring-0">
                  <span className="pl-4 text-slate-500">
                    <UserRound size={22} />
                  </span>
                  <input
                    id="employee-id"
                    className="login-input h-full min-w-0 flex-1 bg-transparent px-4 text-base outline-none placeholder:text-slate-400"
                    autoComplete="username"
                    value={employeeId}
                    onChange={(event) => setEmployeeId(event.target.value)}
                    placeholder="Enter your Employee ID"
                    required
                  />
                </div>
              </div>

              <div>
                <label
                  className="mb-2 block text-sm font-medium"
                  htmlFor="password"
                >
                  Password
                </label>
                <div className="flex h-14 items-center rounded-md border border-transparent bg-white text-slate-700 shadow-sm transition focus-within:border-slate-200 focus-within:ring-0">
                  <span className="pl-4 text-slate-500">
                    <KeyRound size={22} />
                  </span>
                  <input
                    id="password"
                    className="login-input h-full min-w-0 flex-1 bg-transparent px-4 text-base outline-none placeholder:text-slate-400"
                    type={showPassword ? "text" : "password"}
                    autoComplete="current-password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    placeholder="Enter your password"
                    required
                  />
                  <button
                    className="login-control grid h-full w-14 place-items-center text-slate-500 transition hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-cyan-400"
                    type="button"
                    onClick={() => setShowPassword((current) => !current)}
                    aria-label={
                      showPassword ? "Hide password" : "Show password"
                    }
                  >
                    {showPassword ? <EyeOff size={21} /> : <Eye size={21} />}
                  </button>
                </div>
              </div>

              <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                <label className="flex items-center gap-2">
                  <input
                    className="h-4 w-4 accent-emerald-500"
                    type="checkbox"
                    checked={remember}
                    onChange={(event) => setRemember(event.target.checked)}
                  />
                  Remember me
                </label>
                <button
                  className="rounded underline-offset-4 transition hover:text-cyan-200 hover:underline focus-visible:outline-2 focus-visible:outline-cyan-300"
                  type="button"
                  onClick={() =>
                    setError(
                      `Please contact your ${runtimeConfig.systemShortName} administrator to reset your password.`,
                    )
                  }
                >
                  Forgot password?
                </button>
              </div>

              {(error || sessionError) && (
                <div
                  className="rounded-md border border-red-200/60 bg-red-950/35 px-4 py-3 text-sm text-red-50"
                  role="alert"
                >
                  {error || sessionError}
                </div>
              )}

              <button
                className="flex h-14 w-full items-center justify-center rounded-full bg-emerald-500 text-base font-semibold shadow-lg transition duration-200 hover:-translate-y-0.5 hover:bg-emerald-400 hover:shadow-xl disabled:cursor-wait disabled:opacity-70"
                type="submit"
                disabled={busy}
              >
                {busy ? (
                  <span className="h-6 w-6 animate-spin rounded-full border-2 border-white/40 border-t-white" />
                ) : (
                  "Sign in"
                )}
              </button>
            </form>

            {showDemoSection && (
              <section className="mt-6" aria-labelledby="demo-accounts-title">
                <div className="flex items-center gap-3 text-xs uppercase tracking-wider text-blue-100">
                  <span className="h-px flex-1 bg-white/35" />
                  <h3 id="demo-accounts-title">Demo accounts</h3>
                  <span className="h-px flex-1 bg-white/35" />
                </div>

                <div className="mt-4 grid gap-2 sm:grid-cols-3">
                  {demoAccounts.map((account) => (
                    <button
                      className="group flex min-w-0 items-center gap-2 rounded-lg border border-white/25 bg-white/10 p-3 text-left transition duration-200 hover:-translate-y-1 hover:border-white/60 hover:bg-white/20 disabled:cursor-wait disabled:opacity-60 sm:flex-col sm:text-center"
                      type="button"
                      key={account.id}
                      onClick={() => chooseDemo(account)}
                      disabled={busy}
                    >
                      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-xs font-bold text-blue-800 shadow">
                        {account.initials}
                      </span>
                      <span className="min-w-0">
                        <strong className="block truncate text-xs">
                          {account.role}
                        </strong>
                        <span className="mt-1 block truncate text-[11px] text-blue-100">
                          {account.name}
                        </span>
                        <span className="mt-1 block truncate text-[10px] text-cyan-100">
                          {account.employeeId} / {account.password}
                        </span>
                      </span>
                    </button>
                  ))}
                </div>

                {demoLoading && (
                  <p className="mt-3 text-center text-xs text-blue-100">
                    Loading demo accounts...
                  </p>
                )}
                {!demoLoading && demoError && (
                  <p className="mt-3 text-center text-xs text-red-100">
                    {demoError}
                  </p>
                )}
              </section>
            )}
          </div>
        </section>
      </div>

      <footer className="flex min-h-10 flex-wrap items-center justify-between gap-2 bg-[#0b3974] px-4 py-2 text-[11px] text-blue-100 sm:px-7">
        <div className="flex flex-wrap items-center gap-3">
          <span>
            {runtimeConfig.systemShortName} v{runtimeConfig.systemVersion}
          </span>
          <span>City Internal Audit Service (CIAS)</span>
          <span>Privacy Policy</span>
          <span>Terms of Use</span>
        </div>
        <span>
          © {new Date().getFullYear()} {runtimeConfig.organizationName}. All
          rights reserved.
        </span>
      </footer>
    </main>
  );
}
