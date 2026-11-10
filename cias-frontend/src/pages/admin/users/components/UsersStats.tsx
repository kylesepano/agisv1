import {
  Activity,
  ShieldCheck,
  UserCog,
  Users,
  type LucideIcon,
} from "lucide-react";
import {
  StatsCard,
  StatsCardHeader,
  StatsCardIcon,
  StatsCardValue,
} from "../../../../components/ui/stats-card";
import { cn } from "../../../../utils/helpers";
import type { UserRecord } from "../users-types";

type UsersStatsProps = {
  records: UserRecord[];
};

type StatItem = {
  title: string;
  value: number;
  subtitle: string;
  trend: string;
  trendTone: string;
  icon: LucideIcon;
  iconClassName: string;
};

function StatCard({
  title,
  value,
  subtitle,
  trend,
  trendTone,
  icon: Icon,
  iconClassName,
}: StatItem) {
  return (
    <StatsCard className="rounded-3xl p-5">
      <div className="flex items-center gap-4">
        <StatsCardIcon
          className={cn(
            "h-14 w-14 rounded-full shadow-none",
            iconClassName,
          )}
        >
          <Icon size={24} strokeWidth={2.1} />
        </StatsCardIcon>

        <div className="min-w-0 flex-1">
          <StatsCardHeader className="mb-0 justify-start">
            <p className="text-xs font-semibold text-slate-500">{title}</p>
          </StatsCardHeader>

          <StatsCardValue className="mt-1">
            <span className="text-[2.2rem] font-bold tracking-[-0.04em] text-slate-950">
              {value}
            </span>
          </StatsCardValue>

          <div className="mt-2 flex items-center justify-between gap-3">
            <p className="text-xs text-slate-500">{subtitle}</p>
            <p className={cn("text-sm font-semibold", trendTone)}>{trend}</p>
          </div>
        </div>
      </div>
    </StatsCard>
  );
}

export function UsersStats({ records }: UsersStatsProps) {
  const activeUsers = records.filter((record) => record.status === "Active");
  const inactiveUsers = records.length - activeUsers.length;
  const administrators = records.filter(
    (record) => record.role === "Administrator",
  );

  const stats: StatItem[] = [
    {
      title: "Total Users",
      value: records.length,
      subtitle: "All system users",
      trend: "↑ 8%",
      trendTone: "text-emerald-600",
      icon: Users,
      iconClassName: "bg-blue-100 text-blue-600",
    },
    {
      title: "Active Users",
      value: activeUsers.length,
      subtitle: "Currently active",
      trend: "↑ 12%",
      trendTone: "text-emerald-600",
      icon: ShieldCheck,
      iconClassName: "bg-emerald-100 text-emerald-600",
    },
    {
      title: "Inactive Users",
      value: inactiveUsers,
      subtitle: "Deactivated accounts",
      trend: "↑ 2%",
      trendTone: "text-rose-600",
      icon: Activity,
      iconClassName: "bg-amber-100 text-amber-600",
    },
    {
      title: "Administrators",
      value: administrators.length,
      subtitle: "System administrators",
      trend: "↑ 0%",
      trendTone: "text-emerald-600",
      icon: UserCog,
      iconClassName: "bg-violet-100 text-violet-600",
    },
  ];

  return (
    <div className="grid gap-4 xl:grid-cols-4 md:grid-cols-2">
      {stats.map((stat) => (
        <StatCard key={stat.title} {...stat} />
      ))}
    </div>
  );
}
