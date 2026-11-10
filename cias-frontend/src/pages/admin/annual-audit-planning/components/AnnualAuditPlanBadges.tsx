import { cn } from "../../../../utils/helpers";
import type {
  AuditPlanCategory,
  AuditPlanRiskRating,
} from "../annual-audit-planning-types";

export function AnnualAuditPlanCategoryBadge({
  category,
}: {
  category: AuditPlanCategory;
}) {
  const getCategoryStyles = () => {
    switch (category) {
      case "Process":
        return "border-emerald-200 bg-emerald-50 text-emerald-700";
      case "System":
        return "border-blue-200 bg-blue-50 text-blue-700";
      case "Project":
        return "border-purple-200 bg-purple-50 text-purple-700";
      case "Fund":
        return "border-amber-200 bg-amber-50 text-amber-700";
      case "Asset":
        return "border-slate-200 bg-slate-100 text-slate-700";
      default:
        return "border-slate-200 bg-slate-50 text-slate-700";
    }
  };

  return (
    <span
      className={cn(
        "inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold border",
        getCategoryStyles(),
      )}
    >
      {category}
    </span>
  );
}

export function AnnualAuditPlanRiskBadge({
  rating,
}: {
  rating: AuditPlanRiskRating;
}) {
  const getRatingStyles = () => {
    switch (rating) {
      case "Critical":
        return "border-rose-200 bg-rose-50 text-rose-700";
      case "High":
        return "border-orange-200 bg-orange-50 text-orange-700";
      case "Medium":
        return "border-amber-200 bg-amber-50 text-amber-700";
      case "Low":
        return "border-emerald-200 bg-emerald-50 text-emerald-700";
      default:
        return "border-slate-200 bg-slate-50 text-slate-700";
    }
  };

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold border",
        getRatingStyles(),
      )}
    >
      <span
        className={cn(
          "h-1.5 w-1.5 rounded-full",
          rating === "Critical" && "bg-rose-500",
          rating === "High" && "bg-orange-500",
          rating === "Medium" && "bg-amber-500",
          rating === "Low" && "bg-emerald-500",
        )}
      />
      {rating}
    </span>
  );
}
