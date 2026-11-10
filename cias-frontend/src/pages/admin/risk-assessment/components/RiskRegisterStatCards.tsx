import {
  ChartNoAxesCombined,
  CircleAlert,
  LayoutGrid,
  ShieldAlert,
  ShieldCheck,
  ShieldX,
  type LucideIcon,
} from "lucide-react";
import { Card } from "../../../../components/ui/card";
import {
  Caption,
  Heading,
  Text,
} from "../../../../components/ui/typography";
import { cn } from "../../../../utils/helpers";

type RiskRegisterStatCardsProps = {
  totalUnits: number;
  auditUniverseTotal: number;
  critical: number;
  high: number;
  medium: number;
  low: number;
  averageScore: number;
};

type StatItem = {
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
          <Text className="mt-1 text-slate-500">{subtitle}</Text>
        </div>
      </div>
    </Card>
  );
}

function toPercentage(value: number, total: number) {
  if (total === 0) {
    return "0.0%";
  }

  return `${((value / total) * 100).toFixed(1)}%`;
}

export function RiskRegisterStatCards({
  totalUnits,
  auditUniverseTotal,
  critical,
  high,
  medium,
  low,
  averageScore,
}: RiskRegisterStatCardsProps) {
  const stats: StatItem[] = [
    {
      title: "Total Assessed Units",
      value: String(totalUnits),
      subtitle:
        auditUniverseTotal === 0
          ? "No audit universe records"
          : `${toPercentage(totalUnits, auditUniverseTotal)} of audit universe`,
      icon: LayoutGrid,
      iconClassName: "bg-blue-100 text-blue-600",
    },
    {
      title: "Critical Risk",
      value: String(critical),
      subtitle: toPercentage(critical, totalUnits),
      icon: ShieldX,
      iconClassName: "bg-rose-100 text-rose-600",
    },
    {
      title: "High Risk",
      value: String(high),
      subtitle: toPercentage(high, totalUnits),
      icon: ShieldAlert,
      iconClassName: "bg-orange-100 text-orange-600",
    },
    {
      title: "Medium Risk",
      value: String(medium),
      subtitle: toPercentage(medium, totalUnits),
      icon: CircleAlert,
      iconClassName: "bg-amber-100 text-amber-600",
    },
    {
      title: "Low Risk",
      value: String(low),
      subtitle: toPercentage(low, totalUnits),
      icon: ShieldCheck,
      iconClassName: "bg-emerald-100 text-emerald-600",
    },
    {
      title: "Average Score",
      value: `${averageScore.toFixed(1)} / 35`,
      subtitle: "Across all assessed units",
      icon: ChartNoAxesCombined,
      iconClassName: "bg-indigo-100 text-indigo-600",
    },
  ];

  return (
    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
      {stats.map((stat) => (
        <StatCard key={stat.title} {...stat} />
      ))}
    </div>
  );
}
