import { Calendar, Loader2 } from "lucide-react";
import { getStoredUser } from "../../../../lib/auth";

type DashboardHeroProps = {
  isSyncing?: boolean;
};

export function DashboardHero({ isSyncing }: DashboardHeroProps) {
  const user = getStoredUser();
  const displayName = user?.name || user?.username || "Administrator";

  const todayFormatted = new Intl.DateTimeFormat("en-US", {
    weekday: "long",
    month: "long",
    day: "numeric",
    year: "numeric",
  }).format(new Date());

  return (
    <section className="px-0.5 py-0.5">
      <div className="flex items-start justify-between gap-3 max-[760px]:flex-col">
        <div>
          <div className="flex items-center gap-2.5">
            <h2 className="text-[1.2rem] font-bold tracking-tight text-slate-900 leading-snug">
              Welcome back, {displayName}!
            </h2>
            {isSyncing ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 border border-blue-100">
                <Loader2 className="h-2.5 w-2.5 animate-spin" />
                Syncing live database...
              </span>
            ) : null}
          </div>
          <p className="mt-0.5 text-[11px] text-slate-600">
            Here&apos;s what&apos;s happening with your audit activities today.
          </p>
        </div>

        <div className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 shadow-sm">
          <Calendar size={13} className="text-slate-500" />
          {todayFormatted}
        </div>
      </div>
    </section>
  );
}
