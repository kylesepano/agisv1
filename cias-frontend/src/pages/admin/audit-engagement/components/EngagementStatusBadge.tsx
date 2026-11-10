import { cn } from "../../../../utils/helpers";
import type { EngagementStatus } from "../audit-engagement-types";

const statusConfig: Record<
  EngagementStatus,
  { label: string; className: string; dotClassName: string }
> = {
  Draft: {
    label: "Draft",
    className: "bg-slate-100 text-slate-700 border-slate-200",
    dotClassName: "bg-slate-500",
  },
  "For Approval": {
    label: "For Approval",
    className: "bg-amber-50 text-amber-800 border-amber-300 ring-1 ring-amber-400/20",
    dotClassName: "bg-amber-500",
  },
  Approved: {
    label: "Approved",
    className: "bg-sky-50 text-sky-700 border-sky-200",
    dotClassName: "bg-sky-500",
  },
  Active: {
    label: "Active",
    className: "bg-emerald-50 text-emerald-700 border-emerald-300 ring-1 ring-emerald-400/20",
    dotClassName: "bg-emerald-500",
  },
  "On Hold": {
    label: "On Hold",
    className: "bg-orange-50 text-orange-800 border-orange-300 ring-1 ring-orange-400/20",
    dotClassName: "bg-orange-500",
  },
  Completed: {
    label: "Completed",
    className: "bg-indigo-50 text-indigo-700 border-indigo-200",
    dotClassName: "bg-indigo-500",
  },
  Closed: {
    label: "Closed",
    className: "bg-purple-50 text-purple-700 border-purple-200",
    dotClassName: "bg-purple-500",
  },
  Cancelled: {
    label: "Cancelled",
    className: "bg-rose-50 text-rose-700 border-rose-200",
    dotClassName: "bg-rose-500",
  },
};

export function EngagementStatusBadge({
  status,
  showDot = false,
  className,
}: {
  status: EngagementStatus;
  showDot?: boolean;
  className?: string;
}) {
  const config = statusConfig[status] ?? {
    label: status,
    className: "bg-slate-100 text-slate-700 border-slate-200",
    dotClassName: "bg-slate-400",
  };

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-[11px] font-semibold transition-colors",
        config.className,
        className,
      )}
    >
      {showDot && (
        <span
          className={cn("h-1.5 w-1.5 rounded-full", config.dotClassName)}
        />
      )}
      <span>{config.label}</span>
    </span>
  );
}
