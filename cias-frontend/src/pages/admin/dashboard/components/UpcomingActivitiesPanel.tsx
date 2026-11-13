import { CalendarDays } from "lucide-react";
import { upcomingActivities } from "../data";
import { DashboardPanel } from "./DashboardPanel";

export function UpcomingActivitiesPanel() {
  return (
    <DashboardPanel title="Upcoming Activities" action="View Calendar">
      <div className="flex h-full flex-col space-y-2.5">
        {upcomingActivities.map(([title, subtitle, date, time]) => (
          <div
            key={title}
            className="group flex cursor-pointer items-start justify-between gap-2.5 rounded-md border border-slate-100 px-2.5 py-2 transition-all duration-200 hover:-translate-y-[1px] hover:border-blue-100 hover:bg-slate-50/80 hover:shadow-sm"
          >
            <div className="flex min-w-0 items-start gap-2.5">
              <div className="mt-0.5 rounded-md bg-blue-50 p-1.5 text-blue-600 transition-transform duration-200 group-hover:scale-105">
                <CalendarDays size={16} />
              </div>
              <div className="min-w-0">
                <p className="truncate text-[13px] font-semibold text-slate-700 transition-colors duration-200 group-hover:text-slate-900">{title}</p>
                <p className="truncate text-[11px] text-slate-400">{subtitle}</p>
              </div>
            </div>
            <div className="shrink-0 text-right">
              <p className="text-[11px] font-semibold text-slate-500">{date}</p>
              <p className="text-[11px] text-slate-400">{time}</p>
            </div>
          </div>
        ))}
      </div>
    </DashboardPanel>
  );
}
