import { Badge } from "../../../../components/ui/badge";
import { cn } from "../../../../utils/helpers";
import type { RiskAssessmentRating } from "../risk-assessment-types";

export function RiskRatingBadge({
  rating,
}: {
  rating: RiskAssessmentRating;
}) {
  return (
    <Badge
      className={cn(
        rating === "Critical" && "bg-rose-100 text-rose-700",
        rating === "High" && "bg-red-100 text-red-700",
        rating === "Medium" && "bg-amber-100 text-amber-700",
        rating === "Low" && "bg-emerald-100 text-emerald-700",
      )}
      tone="neutral"
    >
      {rating}
    </Badge>
  );
}
