import { Badge } from "../../../../components/ui/badge";
import { cn } from "../../../../utils/helpers";
import type { RiskLevelRecord } from "../risk-management-types";

const levelToneClass: Record<RiskLevelRecord["name"], string> = {
  Low: "bg-emerald-100 text-emerald-700",
  Medium: "bg-amber-100 text-amber-700",
  High: "bg-orange-100 text-orange-700",
  Critical: "bg-rose-100 text-rose-700",
};

export function RiskLevelBadge({
  level,
}: {
  level: RiskLevelRecord["name"];
}) {
  return (
    <Badge className={cn(levelToneClass[level])} tone="neutral">
      <span className="mr-1.5 inline-block h-2 w-2 rounded-full bg-current opacity-85" />
      {level}
    </Badge>
  );
}

export function RiskWeightBadge({
  weight,
  level,
}: {
  weight: number;
  level: RiskLevelRecord["name"];
}) {
  return (
    <Badge className={cn(levelToneClass[level])} tone="neutral">
      {level} ({weight})
    </Badge>
  );
}

export function RiskStatusBadge({
  status,
}: {
  status: RiskLevelRecord["status"];
}) {
  return (
    <Badge tone={status === "Active" ? "success" : "neutral"}>{status}</Badge>
  );
}
