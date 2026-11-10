import { Badge } from "../../../../components/ui/badge";
import { cn } from "../../../../utils/helpers";
import type {
  RiskAssessmentRating,
  RiskRegisterCategory,
} from "../risk-assessment-types";

export function RiskRegisterCategoryBadge({
  category,
}: {
  category: RiskRegisterCategory;
}) {
  return (
    <Badge
      tone="neutral"
      className={cn(
        category === "Process" && "bg-emerald-100 text-emerald-700",
        category === "Fund" && "bg-amber-100 text-amber-700",
        category === "System" && "bg-blue-100 text-blue-700",
        category === "Asset" && "bg-slate-200 text-slate-700",
        category === "Department" && "bg-violet-100 text-violet-700",
        category === "Program" && "bg-cyan-100 text-cyan-700",
        category === "Project" && "bg-fuchsia-100 text-fuchsia-700",
      )}
    >
      {category}
    </Badge>
  );
}

export function RiskRegisterPriorityBadge({
  priority,
  rating,
}: {
  priority: number;
  rating: RiskAssessmentRating;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold",
        rating === "Critical" && "bg-rose-100 text-rose-700",
        rating === "High" && "bg-orange-100 text-orange-700",
        rating === "Medium" && "bg-amber-100 text-amber-700",
        rating === "Low" && "bg-slate-100 text-slate-700",
      )}
    >
      {`Priority ${priority}`}
    </span>
  );
}
