import { cn } from "../../../../utils/helpers";
import type { PlanStatus } from "../annual-audit-planning-types";

export function PlanStatusBadge({ status }: { status: PlanStatus }) {
  const getStatusStyles = () => {
    switch (status) {
      case "Completed":
        return {
          container: "border-emerald-200 bg-emerald-50 text-emerald-700",
          dot: "bg-emerald-500",
        };
      case "In Progress":
        return {
          container: "border-blue-200 bg-blue-50 text-blue-700",
          dot: "bg-blue-500",
        };
      case "Planned":
        return {
          container: "border-purple-200 bg-purple-50 text-purple-700",
          dot: "bg-purple-500",
        };
      case "Cancelled":
        return {
          container: "border-rose-200 bg-rose-50 text-rose-700",
          dot: "bg-rose-500",
        };
      default:
        return {
          container: "border-slate-200 bg-slate-50 text-slate-700",
          dot: "bg-slate-400",
        };
    }
  };

  const { container, dot } = getStatusStyles();

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold border",
        container,
      )}
    >
      <span className={cn("h-1.5 w-1.5 rounded-full", dot)} />
      {status}
    </span>
  );
}
