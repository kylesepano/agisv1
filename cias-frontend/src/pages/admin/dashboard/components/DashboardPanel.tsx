import type { ReactNode } from "react";
import { cn } from "../../../../utils/helpers";

type DashboardPanelProps = {
  title: string;
  action?: string;
  onActionClick?: () => void;
  children: ReactNode;
  className?: string;
};

export function DashboardPanel({
  title,
  action,
  onActionClick,
  children,
  className,
}: DashboardPanelProps) {
  return (
    <section
      className={cn(
        "flex h-full flex-col rounded-lg border border-slate-200 bg-white p-3 shadow-sm",
        className,
      )}
    >
      <div className="mb-2.5 flex items-center justify-between gap-3">
        <h2 className="text-[13px] font-bold text-slate-700">{title}</h2>
        {action ? (
          <button
            className="text-[11px] font-semibold text-blue-600 transition hover:underline cursor-pointer"
            type="button"
            onClick={onActionClick}
          >
            {action}
          </button>
        ) : null}
      </div>
      <div className="flex flex-1 flex-col">{children}</div>
    </section>
  );
}
