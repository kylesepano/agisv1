import { ChevronRight } from "lucide-react";
import { navigateTo } from "../../../routes";
import { cn } from "../../../utils/helpers";

type BreadcrumbItem = {
  label: string;
  href?: string;
};

type BreadcrumbProps = {
  items: BreadcrumbItem[];
  className?: string;
};

export function Breadcrumb({ items, className }: BreadcrumbProps) {
  return (
    <nav
      aria-label="Breadcrumb"
      className={cn(
        "flex flex-wrap items-center gap-1.5 text-xs text-slate-500",
        className,
      )}
    >
      {items.map((item, index) => {
        const isLast = index === items.length - 1;

        return (
          <div
            key={`${item.label}-${index}`}
            className="flex items-center gap-1.5"
          >
            {item.href && !isLast ? (
              <a
                className="font-medium text-blue-600"
                href={item.href}
                onClick={(event) => {
                  event.preventDefault();
                  navigateTo(item.href!);
                }}
              >
                {item.label}
              </a>
            ) : (
              <span
                aria-current={isLast ? "page" : undefined}
                className={cn(!isLast && "font-medium text-slate-700")}
              >
                {item.label}
              </span>
            )}

            {!isLast ? (
              <ChevronRight size={14} className="text-slate-400" />
            ) : null}
          </div>
        );
      })}
    </nav>
  );
}

export type { BreadcrumbItem, BreadcrumbProps };
