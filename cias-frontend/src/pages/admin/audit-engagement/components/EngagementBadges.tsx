import { cn } from "../../../../utils/helpers";
import type {
  EngagementAuditType,
  EngagementRiskRating,
} from "../audit-engagement-types";

export function EngagementAuditTypeBadge({
  auditType,
  className,
}: {
  auditType: EngagementAuditType | string;
  className?: string;
}) {
  const getBadgeStyle = (type: string) => {
    if (type.includes("Compliance")) {
      return "bg-blue-50 text-blue-700 border-blue-200";
    }
    if (type.includes("Operations")) {
      return "bg-emerald-50 text-emerald-700 border-emerald-200";
    }
    if (type.includes("Financial")) {
      return "bg-amber-50 text-amber-700 border-amber-200";
    }
    if (type.includes("Information") || type.includes("System")) {
      return "bg-cyan-50 text-cyan-700 border-cyan-200";
    }
    if (type.includes("Process")) {
      return "bg-indigo-50 text-indigo-700 border-indigo-200";
    }
    if (type.includes("Asset")) {
      return "bg-slate-100 text-slate-700 border-slate-200";
    }
    if (type.includes("Performance") || type.includes("Value")) {
      return "bg-purple-50 text-purple-700 border-purple-200";
    }
    return "bg-slate-50 text-slate-700 border-slate-200";
  };

  return (
    <span
      className={cn(
        "inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold",
        getBadgeStyle(auditType),
        className,
      )}
    >
      {auditType}
    </span>
  );
}

// Backward compatibility alias for Category badge
export const EngagementCategoryBadge = EngagementAuditTypeBadge;

export function EngagementRiskBadge({
  rating,
  showProvisional = false,
  className,
}: {
  rating: EngagementRiskRating;
  showProvisional?: boolean;
  className?: string;
}) {
  const getRiskStyle = (r: EngagementRiskRating) => {
    switch (r) {
      case "Critical":
        return "bg-rose-50 text-rose-700 border-rose-200";
      case "High":
        return "bg-orange-50 text-orange-700 border-orange-200";
      case "Medium":
        return "bg-amber-50 text-amber-700 border-amber-200";
      case "Low":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      default:
        return "bg-slate-100 text-slate-700 border-slate-200";
    }
  };

  return (
    <div className="inline-flex items-center gap-1.5 flex-wrap">
      <span
        className={cn(
          "inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold",
          getRiskStyle(rating),
          className,
        )}
      >
        {rating}
      </span>
      {showProvisional && (
        <span
          className="inline-flex items-center rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 border border-amber-200"
          title="MDS-200 §19 / OD-003: Risk Rating is provisional pending formal Risk Matrix ruling"
        >
          OD-003 Provisional
        </span>
      )}
    </div>
  );
}
