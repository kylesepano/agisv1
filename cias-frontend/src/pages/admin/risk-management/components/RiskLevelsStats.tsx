import {
  Ban,
  BarChart3,
  CheckCircle2,
  Shield,
  type LucideIcon,
} from "lucide-react";
import { Card } from "../../../../components/ui/card";
import { Caption, Heading, Text } from "../../../../components/ui/typography";
import { cn } from "../../../../utils/helpers";
import type { RiskLevelRecord } from "../risk-management-types";

type RiskLevelsStatsProps = {
  records: RiskLevelRecord[];
};

type StatItem = {
  title: string;
  value: string;
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
          <Icon size={20} strokeWidth={2.1} />
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

export function RiskLevelsStats({ records }: RiskLevelsStatsProps) {
  const activeCount = records.filter((record) => record.status === "Active").length;
  const inactiveCount = records.length - activeCount;
  const averageScoreRange = "6.25";

  const stats: StatItem[] = [
    {
      title: "Total Risk Levels",
      value: String(records.length),
      subtitle: "Defined risk levels",
      trend: "+1",
      trendTone: "text-emerald-600",
      icon: Shield,
      iconClassName: "bg-blue-100 text-blue-600",
    },
    {
      title: "Active Risk Levels",
      value: String(activeCount),
      subtitle: "Currently active",
      trend: "100%",
      trendTone: "text-emerald-600",
      icon: CheckCircle2,
      iconClassName: "bg-emerald-100 text-emerald-600",
    },
    {
      title: "Inactive Risk Levels",
      value: String(inactiveCount),
      subtitle: "Currently inactive",
      trend: "0%",
      trendTone: "text-amber-600",
      icon: Ban,
      iconClassName: "bg-amber-100 text-amber-600",
    },
    {
      title: "Average Score Range",
      value: averageScoreRange,
      subtitle: "Across all levels",
      trend: "",
      trendTone: "text-violet-600",
      icon: BarChart3,
      iconClassName: "bg-violet-100 text-violet-600",
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
