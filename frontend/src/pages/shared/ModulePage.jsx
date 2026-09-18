import { ChevronLeft, LayoutGrid } from "lucide-react";
import { Link, useLocation } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { pageForPath } from "../../config/navigation";

/**
 * Provides the protected placeholder experience for AGIS operational modules
 * whose complete workflows have not yet been implemented.
 */
export default function ModulePage() {
  const { user } = useAuth();
  const location = useLocation();
  const page = pageForPath(location.pathname);
  const PageIcon = page?.icon ?? LayoutGrid;
  const permissionPrefix = page?.permission?.split(".")[0];
  const allowedActions = user.permissions
    .filter((permission) => permission.startsWith(`${permissionPrefix}.`))
    .map((permission) => permission.split(".")[1].replaceAll("_", " "));

  return (
    <div className="grid min-h-[calc(100vh-6rem)] place-items-center p-5">
      <section className="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-7 text-center shadow-sm sm:p-12">
        <span className="mx-auto grid h-24 w-24 place-items-center rounded-3xl bg-blue-50 text-sky-700 shadow-inner">
          <PageIcon size={48} strokeWidth={1.5} />
        </span>
        <span className="mt-7 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-700">
          Coming soon
        </span>
        <h2 className="mt-4 text-3xl font-bold text-slate-800">
          {page?.label ?? "AGIS Module"}
        </h2>
        <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-500">
          This route is ready and protected by your AGIS role. The module
          workflow, forms, tables, and server endpoints will be implemented in
          the next phase.
        </p>

        <div className="mx-auto mt-7 max-w-xl rounded-xl border border-slate-200 bg-slate-50 p-4 text-left">
          <h3 className="text-sm font-bold text-slate-700">
            Your allowed actions
          </h3>
          <div className="mt-3 flex flex-wrap gap-2">
            {allowedActions.map((action) => (
              <span
                className="rounded-full bg-white px-3 py-1.5 text-xs capitalize text-slate-600 shadow-sm ring-1 ring-slate-200"
                key={action}
              >
                {action}
              </span>
            ))}
          </div>
        </div>

        <Link
          className="mt-8 inline-flex items-center gap-2 rounded-lg bg-[#28598f] px-5 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#1f4978] hover:shadow-lg"
          to="/dashboard"
        >
          <ChevronLeft size={17} />
          Back to dashboard
        </Link>
      </section>
    </div>
  );
}
