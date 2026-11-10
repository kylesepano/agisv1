import type { FC } from "react";
import {
  Construction,
  LayoutDashboard,
  ShieldCheck,
  ArrowLeft,
  Clock,
  Sparkles,
  Database,
} from "lucide-react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { navigateTo } from "../../../routes";

interface UnderDevelopmentPageProps {
  title?: string;
  moduleCode?: string;
  description?: string;
}

export const UnderDevelopmentPage: FC<UnderDevelopmentPageProps> = ({
  title = "Module Under Development",
  moduleCode,
  description,
}) => {
  return (
    <AdminLayout>
      <div className="py-4 sm:py-6">
        {/* Top Breadcrumb / Status */}
        <div className="mb-4 flex items-center justify-between">
          <button
            onClick={() => navigateTo("/admin/dashboard")}
            className="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 hover:text-slate-900 transition-colors"
          >
            <ArrowLeft className="h-3.5 w-3.5" />
            Back to Dashboard
          </button>

          <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
            <Clock className="h-3 w-3 text-amber-600" />
            Under Active Development
          </span>
        </div>

        {/* Main Content Card */}
        <div className="mx-auto max-w-2xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          {/* Header */}
          <div className="border-b border-slate-100 bg-gradient-to-r from-blue-50/60 via-slate-50 to-indigo-50/40 px-6 py-8 text-center sm:px-8">
            <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/20">
              <Construction className="h-7 w-7" strokeWidth={2} />
            </div>

            {moduleCode && (
              <span className="mb-2 inline-block rounded-md bg-blue-100 px-2.5 py-0.5 text-xs font-bold tracking-wide text-blue-700">
                {moduleCode}
              </span>
            )}

            <h1 className="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
              {title}
            </h1>

            <p className="mx-auto mt-2 max-w-lg text-xs leading-relaxed text-slate-600 sm:text-sm">
              {description ||
                "This module is currently in development. Our team is actively prioritizing the Audit Engagement Management (AEM) and Executive Dashboard modules with live database integration."}
            </p>
          </div>

          {/* Development Focus & Roadmap Status */}
          <div className="p-5 sm:p-6">
            <div className="rounded-lg border border-slate-200/80 bg-slate-50/60 p-4">
              <h2 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                <Sparkles className="h-3.5 w-3.5 text-blue-600" />
                Current System Roadmap
              </h2>

              <div className="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <div className="flex items-start gap-2.5 rounded-lg border border-emerald-200/60 bg-emerald-50/50 p-2.5">
                  <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-md bg-emerald-100 text-emerald-700">
                    <ShieldCheck className="h-3.5 w-3.5" />
                  </div>
                  <div>
                    <div className="text-xs font-semibold text-emerald-900">
                      AEM & Dashboard
                    </div>
                    <div className="text-[11px] text-emerald-700">
                      Active & prioritized with real database APIs
                    </div>
                  </div>
                </div>

                <div className="flex items-start gap-2.5 rounded-lg border border-blue-200/60 bg-blue-50/50 p-2.5">
                  <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-md bg-blue-100 text-blue-700">
                    <Database className="h-3.5 w-3.5" />
                  </div>
                  <div>
                    <div className="text-xs font-semibold text-blue-900">
                      Other Modules
                    </div>
                    <div className="text-[11px] text-blue-700">
                      Will be connected following AEM completion
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Quick Actions */}
            <div className="mt-6 flex flex-col items-center justify-center gap-2.5 sm:flex-row">
              <button
                onClick={() => navigateTo("/admin/dashboard")}
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:w-auto"
              >
                <LayoutDashboard className="h-3.5 w-3.5" />
                Go to Dashboard
              </button>

              <button
                onClick={() => navigateTo("/admin/audit-engagements")}
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
              >
                <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                Go to Audit Engagements (AEM)
              </button>
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
};

export default UnderDevelopmentPage;
