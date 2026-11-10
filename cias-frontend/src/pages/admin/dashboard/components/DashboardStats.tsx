import {
  ArrowUpRight,
  CheckCircle2,
  ClipboardList,
  ShieldAlert,
  SquareChartGantt,
  type LucideIcon,
} from "lucide-react";
import { Card } from "../../../../components/ui/card";
import { Caption, Heading, Text } from "../../../../components/ui/typography";
import { cn } from "../../../../utils/helpers";

type StatItem = {
  title: string;
  value: string;
  subtitle: string;
  change: string;
  changeLabel: string;
  changeTone: string;
  icon: LucideIcon;
  iconClassName: string;
};

const statItems: StatItem[] = [
  {
    title: "Total Auditable Units",
    value: "186",
    subtitle: "All active records",
    change: "12%",
    changeLabel: "vs last month",
    changeTone: "text-emerald-600",
    icon: SquareChartGantt,
    iconClassName: "bg-blue-600 text-white",
  },
  {
    title: "High Risk Units",
    value: "48",
    subtitle: "High risk auditable units",
    change: "8%",
    changeLabel: "vs last month",
    changeTone: "text-rose-600",
    icon: ShieldAlert,
    iconClassName: "bg-rose-500 text-white",
  },
  {
    title: "Pending Assessments",
    value: "72",
    subtitle: "For risk assessment",
    change: "15%",
    changeLabel: "vs last month",
    changeTone: "text-amber-600",
    icon: ClipboardList,
    iconClassName: "bg-amber-500 text-white",
  },
  {
    title: "Completed Assessments",
    value: "66",
    subtitle: "Completed this year",
    change: "10%",
    changeLabel: "vs last month",
    changeTone: "text-emerald-600",
    icon: CheckCircle2,
    iconClassName: "bg-emerald-500 text-white",
  },
];

function DashboardStatCard({
  title,
  value,
  subtitle,
  change,
  changeLabel,
  changeTone,
  icon: Icon,
  iconClassName,
}: StatItem) {
  return (
    <Card className="cursor-pointer rounded-2xl p-0 transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">
      <div className="flex items-center gap-3 px-3.5 py-3">
        <div
          className={cn(
            "flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-sm transition-transform duration-200",
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
          <div className="mt-1 flex items-end justify-between gap-2">
            <Text className="text-slate-600">{subtitle}</Text>
            <div className="text-right">
              <Text className={cn("font-semibold", changeTone)}>
                <ArrowUpRight className="mr-0.5 inline-block" size={13} />
                {change}
              </Text>
              <Text className="mt-0.5 text-[11px] text-slate-500">
                {changeLabel}
              </Text>
            </div>
          </div>
        </div>
      </div>
    </Card>
  );
}

export function DashboardStats() {
  return (
    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      {statItems.map((item) => (
        <DashboardStatCard key={item.title} {...item} />
      ))}
    </div>
  );
}
