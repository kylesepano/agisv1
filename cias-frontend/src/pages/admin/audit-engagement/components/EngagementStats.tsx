import {
  Activity,
  Archive,
  BriefcaseBusiness,
  CheckCircle2,
  FileClock,
  PauseCircle,
  type LucideIcon,
} from "lucide-react";
import { cn } from "../../../../utils/helpers";
import type { AuditEngagementSummary } from "../audit-engagement-types";

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

export function EngagementStats({
  summary,
}: {
  summary: AuditEngagementSummary;
}) {
  const stats: StatItem[] = [
    {
      title: "Total",
      value: String(summary.totalEngagements),
      subtitle: "All records for year",
      icon: BriefcaseBusiness,
      surfaceClass: "border-slate-200 bg-[#f8fbff]",
      iconBgClass: "bg-blue-100 text-blue-700",
      badgeClass: "bg-blue-50 text-blue-700 border-blue-200",
    },
    {
      title: "Active",
      value: String(summary.active),
      subtitle: "Fieldwork in progress",
      icon: Activity,
      surfaceClass: "border-emerald-200/70 bg-[#f4fcf6]",
      iconBgClass: "bg-emerald-100 text-emerald-700",
      badgeClass: "bg-emerald-50 text-emerald-700 border-emerald-200",
    },
    {
      title: "For Approval",
      value: String(summary.forApproval),
      subtitle: "Pending review action",
      icon: FileClock,
      surfaceClass: "border-amber-200/70 bg-[#fffdf9]",
      iconBgClass: "bg-amber-100 text-amber-700",
      badgeClass: "bg-amber-50 text-amber-700 border-amber-200",
    },
    {
      title: "On Hold",
      value: String(summary.onHold),
      subtitle: "Awaiting resolution",
      icon: PauseCircle,
      surfaceClass: "border-orange-200/70 bg-[#fff8f5]",
      iconBgClass: "bg-orange-100 text-orange-700",
      badgeClass: "bg-orange-50 text-orange-700 border-orange-200",
    },
    {
      title: "Completed",
      value: String(summary.completed),
      subtitle: "Reports finalized",
      icon: CheckCircle2,
      surfaceClass: "border-indigo-200/70 bg-[#f8f9ff]",
      iconBgClass: "bg-indigo-100 text-indigo-700",
      badgeClass: "bg-indigo-50 text-indigo-700 border-indigo-200",
    },
    {
      title: "Closed",
      value: String(summary.closed),
      subtitle: "Archived & monitored",
      icon: Archive,
      surfaceClass: "border-purple-200/70 bg-[#faf8ff]",
      iconBgClass: "bg-purple-100 text-purple-700",
      badgeClass: "bg-purple-50 text-purple-700 border-purple-200",
    },
  ];

  return (
    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
      {stats.map((stat) => (
        <StatCard key={stat.title} {...stat} />
      ))}
    </div>
  );
}
