import { ShieldX } from "lucide-react";
import { Link } from "react-router";

/**
 * Provides a safe destination when an authenticated user reaches a route that
 * is outside the permissions granted by their active roles and scopes.
 */
export default function UnauthorizedPage() {
  return (
    <div className="grid min-h-[calc(100vh-6rem)] place-items-center p-5">
      <section className="w-full max-w-xl rounded-2xl border border-red-100 bg-white p-10 text-center shadow-sm">
        <span className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-red-50 text-red-600">
          <ShieldX size={40} />
        </span>
        <h2 className="mt-6 text-3xl font-bold text-slate-800">
          Access denied
        </h2>
        <p className="mt-3 text-sm leading-6 text-slate-500">
          Your role does not have permission to view this page. It has also been
          removed from your sidebar navigation.
        </p>
        <Link
          className="mt-7 inline-flex items-center gap-2 rounded-lg bg-[#28598f] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1f4978]"
          to="/dashboard"
        >
          Return to dashboard
        </Link>
      </section>
    </div>
  );
}
