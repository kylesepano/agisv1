import type { HTMLAttributes, ReactNode } from "react";
import { cn } from "../../../utils/helpers";

type StatsCardProps = HTMLAttributes<HTMLDivElement> & {
  children: ReactNode;
};

export function StatsCard({ children, className, ...props }: StatsCardProps) {
  return (
    <div
      className={cn(
        "rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md",
        className,
      )}
      {...props}
    >
      {children}
    </div>
  );
}
