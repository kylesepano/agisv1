import {
  CheckCircle2,
  FileCheck2,
  Layers,
  PowerOff,
  type LucideIcon,
} from "lucide-react";
import { cn } from "../../../../utils/helpers";
import type { AuditFocusRecord } from "../audit-focuses-types";

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

export function AuditFocusesStats({ records }: { records: AuditFocusRecord[] }) {
  const activeFocuses = records.filter((record) => record.status === "Active");
  const inactiveFocuses = records.filter((record) => record.status === "Inactive");
  const distinctAreas = new Set(
    records.map((r) => r.auditAreaId || r.auditAreaName).filter(Boolean),
  );

  const stats: StatItem[] = [
    {
      title: "Total Audit Focuses",
      value: String(records.length),
      subtitle: "Granular scope items",
      icon: FileCheck2,
      surfaceClass: "border-slate-200 bg-[#f8fbff]",
      iconBgClass: "bg-blue-100 text-blue-700",
      badgeClass: "bg-blue-50 text-blue-700 border-blue-200",
    },
    {
      title: "Active Focuses",
      value: String(activeFocuses.length),
      subtitle: `${records.length > 0 ? Math.round((activeFocuses.length / records.length) * 100) : 0}% available for scope`,
      icon: CheckCircle2,
      surfaceClass: "border-emerald-200/70 bg-[#f4fcf6]",
      iconBgClass: "bg-emerald-100 text-emerald-700",
      badgeClass: "bg-emerald-50 text-emerald-700 border-emerald-200",
    },
    {
      title: "Inactive / Deactivated",
      value: String(inactiveFocuses.length),
      subtitle: "Excluded from engagement scope",
      icon: PowerOff,
      surfaceClass: "border-rose-200/70 bg-[#fff8f8]",
      iconBgClass: "bg-rose-100 text-rose-700",
      badgeClass: "bg-rose-50 text-rose-700 border-rose-200",
    },
    {
      title: "Areas with Focus Coverage",
      value: String(distinctAreas.size),
      subtitle: "Parent audit areas covered",
      icon: Layers,
      surfaceClass: "border-indigo-200/70 bg-[#f8f9ff]",
      iconBgClass: "bg-indigo-100 text-indigo-700",
      badgeClass: "bg-indigo-50 text-indigo-700 border-indigo-200",
    },
  ];

  return (
    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
      {stats.map((stat) => (
        <StatCard key={stat.title} {...stat} />
      ))}
    </div>
  );
}
