import {
  FolderKanban,
  ShieldCheck,
  ShieldOff,
  Waypoints,
  type LucideIcon,
} from "lucide-react";
import { Card } from "../../../../components/ui/card";
import { Caption, Heading, Text } from "../../../../components/ui/typography";
import { cn } from "../../../../utils/helpers";
import type { CategoryRecord } from "../categories-types";

type CategoriesStatsProps = {
  records: CategoryRecord[];
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
    <Card className="rounded-2xl p-0">
      <div className="flex items-center gap-3 p-4">
        <div
          className={cn(
            "flex h-11 w-11 items-center justify-center rounded-full",
            iconClassName,
          )}
        >
          <Icon size={24} strokeWidth={2.1} />
        </div>

        <div className="min-w-0 flex-1">
          <Caption className="text-slate-500">{title}</Caption>

          <Heading as={3} className="mt-0.5 text-slate-950">
            {value}
          </Heading>

          <div className="mt-1 flex items-center justify-between gap-3">
            <Text className="text-slate-500">{subtitle}</Text>
            <Text className={cn("font-semibold", trendTone)}>{trend}</Text>
          </div>
        </div>
      </div>
    </Card>
  );
}

export function CategoriesStats({ records }: CategoriesStatsProps) {
  const activeCategories = records.filter((record) => record.status === "Active");
  const inactiveCategories = records.length - activeCategories.length;
  const totalLinkedUnits = records.reduce(
    (count, record) => count + record.linkedUnits,
    0,
  );

  const stats: StatItem[] = [
    {
      title: "Total Categories",
      value: records.length,
      subtitle: "Defined classifications",
      trend: "+2",
      trendTone: "text-emerald-600",
      icon: FolderKanban,
      iconClassName: "bg-blue-100 text-blue-600",
    },
    {
      title: "Active Categories",
      value: activeCategories.length,
      subtitle: "Currently available",
      trend: "Stable",
      trendTone: "text-emerald-600",
      icon: ShieldCheck,
      iconClassName: "bg-emerald-100 text-emerald-600",
    },
    {
      title: "Inactive Categories",
      value: inactiveCategories,
      subtitle: "Not currently in use",
      trend: String(inactiveCategories),
      trendTone: "text-slate-600",
      icon: ShieldOff,
      iconClassName: "bg-slate-100 text-slate-600",
    },
    {
      title: "Linked Units",
      value: totalLinkedUnits,
      subtitle: "Mapped audit records",
      trend: "+18",
      trendTone: "text-amber-600",
      icon: Waypoints,
      iconClassName: "bg-amber-100 text-amber-600",
    },
  ];

  return (
    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      {stats.map((stat) => (
        <StatCard key={stat.title} {...stat} />
      ))}
    </div>
  );
}
