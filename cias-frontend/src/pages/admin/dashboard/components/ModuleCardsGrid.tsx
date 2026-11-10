import { ArrowRight } from "lucide-react";
import { moduleCards } from "../data";
import { cn } from "../../../../utils/helpers";
import { navigateTo } from "../../../../routes";
import type { DashboardModule } from "../../../../services/dashboardService";

type ModuleCardsGridProps = {
  onOpenAfr?: () => void;
  onOpenAem?: () => void;
  modules?: DashboardModule[];
};

export function ModuleCardsGrid({
  onOpenAfr,
  onOpenAem,
  modules,
}: ModuleCardsGridProps) {
  const liveMap = new Map((modules || []).map((m) => [m.key.toLowerCase(), m]));

  return (
    <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
      {moduleCards.map((card) => {
        const Icon = card.icon;
        const isAfr = card.title.includes("AFR");
        const isAem = card.title.includes("AEM");
        const isIap = card.title.includes("IAP");
        const isCms = card.title.includes("CMS");
        const isArms = card.title.includes("ARMS") || card.title.includes("ARMIS");
        const isAis = card.title.includes("AIS");

        const moduleKey = isIap
          ? "iap"
          : isAem
          ? "aem"
          : isAfr
          ? "afr"
          : isCms
          ? "cms"
          : isArms
          ? "armis"
          : isAis
          ? "ais"
          : "";

        const liveItem = liveMap.get(moduleKey);
        const displayValue = liveItem
          ? liveItem.value !== null && liveItem.value !== undefined
            ? String(liveItem.value)
            : "—"
          : card.value;
        const displayCaption = liveItem?.note || card.caption;

        const handleClick = () => {
          if (isAfr && onOpenAfr) {
            onOpenAfr();
          } else if (isAem && onOpenAem) {
            onOpenAem();
          } else if (isIap) {
            navigateTo("/admin/annual-audit-plan");
          } else if (isCms) {
            navigateTo("/admin/compliances");
          } else if (isArms) {
            navigateTo("/admin/team-assignment");
          } else if (isAis) {
            navigateTo("/admin/reports");
          }
        };

        return (
          <article
            key={card.title}
            className={cn(
              "group flex min-h-[220px] flex-col items-center rounded-lg border bg-gradient-to-b px-2.5 pb-3 pt-3.5 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] transition-all duration-200 hover:-translate-y-1 hover:shadow-lg sm:min-h-[236px] xl:min-h-[252px] cursor-pointer",
              card.surface,
              card.border,
            )}
            onClick={handleClick}
          >
            <div
              className={cn(
                "mb-3 flex h-16 w-16 items-center justify-center rounded-xl transition-transform duration-200 group-hover:scale-105",
                card.iconWrap,
              )}
            >
              <Icon size={36} strokeWidth={1.8} />
            </div>
            <h3
              className={cn(
                "min-h-[32px] max-w-[160px] text-[0.76rem] font-bold leading-tight tracking-tight sm:min-h-[36px] sm:text-[0.78rem]",
                card.accent,
              )}
            >
              {card.title}
            </h3>
            <div
              className={cn(
                "mt-3 text-[1rem] font-bold leading-none tracking-tight sm:text-[1.1rem]",
                card.accent,
              )}
            >
              {displayValue}
            </div>
            <p className="mt-2 text-[10px] text-slate-500 sm:text-[11px]">{displayCaption}</p>
            <button
              className={cn(
                "relative mt-auto flex w-full items-center justify-center rounded-lg border bg-white/80 px-2 py-1.5 text-[10px] font-semibold shadow-sm transition-all duration-200 hover:bg-white group-hover:shadow-md sm:px-2.5 sm:text-[11px] cursor-pointer",
                card.accent,
              )}
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                handleClick();
              }}
            >
              <span>{card.action}</span>
              <ArrowRight className="absolute right-2.5 top-1/2 -translate-y-1/2" size={13} />
            </button>
          </article>
        );
      })}
    </div>
  );
}
