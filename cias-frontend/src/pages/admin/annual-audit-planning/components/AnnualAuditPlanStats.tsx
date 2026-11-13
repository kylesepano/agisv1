import {
  CalendarDays,
  CheckCircle2,
  ClipboardList,
  Clock3,
  XCircle,
  type LucideIcon,
} from "lucide-react";
import { cn } from "../../../../utils/helpers";
import type { AnnualAuditPlanningSummary } from "../annual-audit-planning-types";

type StatItem = {
  title: string;
  value: string;
  subtitle: string;
  icon: LucideIcon;
  surfaceClass: string;
  iconBgClass: string;
  badgeClass: string;
};

function StatCard({
  title,
  value,
  subtitle,
  icon: Icon,
  surfaceClass,
  iconBgClass,
  badgeClass,
}: StatItem) {
  return (
    <div
      className={cn(
        "flex flex-col justify-between rounded-lg border p-3 shadow-xs transition-all duration-150 hover:shadow-sm",
        surfaceClass,
      )}
    >
      <div className="flex items-center justify-between gap-2">
        <div
          className={cn(
            "grid h-8.5 w-8.5 shrink-0 place-items-center rounded-lg font-medium",
            iconBgClass,
          )}
        >
          <Icon size={17} strokeWidth={2} />
        </div>
        <span
          className={cn(
            "rounded px-1.5 py-0.2 text-[10px] font-bold border",
            badgeClass,
          )}
        >
          {title}
        </span>
      </div>

      <div className="mt-2">
        <div className="text-xl font-bold leading-none tracking-tight text-slate-900">
          {value}
        </div>
        <div className="mt-0.5 text-[11px] font-medium text-slate-500 truncate">
          {subtitle}
        </div>
      </div>
    </div>
  );
}

export function AnnualAuditPlanStats({
  summary,
}: {
  summary: AnnualAuditPlanningSummary;
}) {
  const stats: StatItem[] = [
    {
      title: "Total Planned",
      value: String(summary.totalPlannedAudits),
      subtitle: "For selected audit year",
      icon: ClipboardList,
      surfaceClass: "border-slate-200 bg-[#f8fbff]",
      iconBgClass: "bg-blue-100 text-blue-700",
      badgeClass: "bg-blue-50 text-blue-700 border-blue-200",
    },
    {
      title: "Completed",
      value: String(summary.completed),
      subtitle: "Finished planned audits",
      icon: CheckCircle2,
      surfaceClass: "border-emerald-200/70 bg-[#f4fcf6]",
      iconBgClass: "bg-emerald-100 text-emerald-700",
      badgeClass: "bg-emerald-50 text-emerald-700 border-emerald-200",
    },
    {
      title: "In Progress",
      value: String(summary.inProgress),
      subtitle: "Currently active audits",
      icon: Clock3,
      surfaceClass: "border-blue-200/70 bg-[#f8fbff]",
      iconBgClass: "bg-blue-100 text-blue-700",
      badgeClass: "bg-blue-50 text-blue-700 border-blue-200",
    },
    {
      title: "Planned",
      value: String(summary.planned),
      subtitle: "Upcoming scheduled",
      icon: CalendarDays,
      surfaceClass: "border-purple-200/70 bg-[#faf8ff]",
      iconBgClass: "bg-purple-100 text-purple-700",
      badgeClass: "bg-purple-50 text-purple-700 border-purple-200",
    },
    {
      title: "Cancelled",
      value: String(summary.cancelled),
      subtitle: "Deferred or cancelled",
      icon: XCircle,
      surfaceClass: "border-rose-200/70 bg-[#fff8f8]",
      iconBgClass: "bg-rose-100 text-rose-700",
      badgeClass: "bg-rose-50 text-rose-700 border-rose-200",
    },
  ];

  return (
    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
      {stats.map((stat) => (
        <StatCard key={stat.title} {...stat} />
      ))}
    </div>
  );
}
