import { cn } from "../../../../utils/helpers";
import type { AuditAreaStatus } from "../audit-areas-types";

export function AuditAreaStatusBadge({ status }: { status: AuditAreaStatus }) {
  const isActive = status === "Active";

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold border",
        isActive
          ? "border-emerald-200 bg-emerald-50 text-emerald-700"
          : "border-slate-200 bg-slate-100 text-slate-600",
      )}
    >
      <span
        className={cn(
          "h-1.5 w-1.5 rounded-full",
          isActive ? "bg-emerald-500" : "bg-slate-400",
        )}
      />
      {status}
    </span>
  );
}
