import {
  AlertTriangle,
  Building2,
  CalendarClock,
  Layers3,
  Waypoints,
  type LucideIcon,
} from "lucide-react";

import { Card } from "../../../../components/ui/card";
import { Caption, Heading, Text } from "../../../../components/ui/typography";
import { cn } from "../../../../utils/helpers";

type AuditUniverseStat = {
  title: string;
  value: string;
  subtitle: string;
  icon: LucideIcon;
  iconClassName: string;
};

type AuditUniverseStatOverride = Omit<AuditUniverseStat, "icon">;

type StatCardProps = {
  title: string;
  value: string;
  subtitle: string;
  icon: LucideIcon;
  iconClassName: string;
};

function StatCard({
  title,
  value,
  subtitle,
  icon: Icon,
  iconClassName,
}: StatCardProps) {
  return (
    <Card className="rounded-2xl p-0">
      <div className="flex items-center gap-3 p-4">
        <div
          className={cn(
            "flex h-11 w-11 items-center justify-center rounded-full",
            iconClassName,
          )}
        >
          <Icon size={20} strokeWidth={2.1} />
        </div>

        <div className="min-w-0 flex-1">
          <Caption className="text-slate-500">{title}</Caption>

          <Heading as={3} className="mt-0.5 text-slate-950">
            {value}
          </Heading>

          <Text className="mt-1 text-slate-500">{subtitle}</Text>
        </div>
      </div>
    </Card>
  );
}
const defaultStats: AuditUniverseStat[] = [
  {
    title: "Total Auditable Units",
    value: "186",
    subtitle: "All active auditable units",
    icon: Waypoints,
    iconClassName: "bg-blue-100 text-blue-600",
  },
  {
    title: "Responsible Offices",
    value: "28",
    subtitle: "Offices covered",
    icon: Building2,
    iconClassName: "bg-emerald-100 text-emerald-600",
  },
  {
    title: "High Risk Units",
    value: "48",
    subtitle: "Priority audit areas",
    icon: AlertTriangle,
    iconClassName: "bg-rose-100 text-rose-600",
  },
  {
    title: "Upcoming Audits",
    value: "37",
    subtitle: "Scheduled within 12 months",
    icon: CalendarClock,
    iconClassName: "bg-amber-100 text-amber-600",
  },
  {
    title: "Systems",
    value: "55",
    subtitle: "Auditable information systems",
    icon: Layers3,
    iconClassName: "bg-violet-100 text-violet-600",
  },
];

export function AuditUniverseStats({
  stats,
}: {
  stats?: AuditUniverseStatOverride[];
}) {
  const resolvedStats =
    stats?.map((stat, index) => ({
      ...defaultStats[index],
      ...stat,
    })) ?? defaultStats;

  return (
    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
      {resolvedStats.map((stat) => (
        <StatCard key={stat.title} {...stat} />
      ))}
    </div>
  );
}
