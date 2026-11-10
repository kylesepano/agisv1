import { FileText, Plus, Search } from "lucide-react";
import { quickActions as defaultQuickActions } from "../data";
import { cn } from "../../../../utils/helpers";
import { navigateTo } from "../../../../routes";
import { DashboardPanel } from "./DashboardPanel";
import type { DashboardQuickAction } from "../../../../services/dashboardService";

const quickActionIcons = [Plus, Plus, Search, FileText];

type QuickActionsPanelProps = {
  actions?: DashboardQuickAction[];
};

export function QuickActionsPanel({ actions: liveActions }: QuickActionsPanelProps) {
  const handleAction = (label: string, path?: string) => {
    if (path) {
      if (path.startsWith("/audit-engagement-management")) {
        navigateTo("/admin/audit-engagements");
        return;
      }
      if (path.startsWith("/document-management")) {
        navigateTo("/admin/documents");
        return;
      }
      if (path.startsWith("/administrative-reports")) {
        navigateTo("/admin/reports");
        return;
      }
      navigateTo(path);
      return;
    }

    if (label.includes("Audit Engagement")) {
      navigateTo("/admin/audit-engagement/create");
    } else if (label.includes("Documents")) {
      navigateTo("/admin/documents");
    } else if (label.includes("Report")) {
      navigateTo("/admin/reports");
    } else if (label.includes("Finding")) {
      navigateTo("/admin/audit-engagements");
    }
  };

  const actionItems =
    liveActions && liveActions.length > 0
      ? liveActions.map((a, i) => ({
          label: a.label,
          path: a.path,
          tone: ["bg-emerald-100 text-emerald-700", "bg-blue-100 text-blue-700", "bg-cyan-100 text-cyan-700", "bg-orange-100 text-orange-700"][i % 4],
        }))
      : defaultQuickActions.map(([label, tone]) => ({ label, tone, path: undefined }));

  return (
    <DashboardPanel title="Quick Actions">
      <div className="flex h-full flex-col gap-2">
        {actionItems.map((item, idx) => {
          const IconComp = quickActionIcons[idx] || Plus;
          return (
            <button
              key={item.label}
              className={cn(
                "flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-[11px] font-semibold transition hover:brightness-95 cursor-pointer",
                item.tone,
              )}
              type="button"
              onClick={() => handleAction(item.label, item.path)}
            >
              <IconComp size={14} className="shrink-0" />
              <span className="truncate">{item.label}</span>
            </button>
          );
        })}
      </div>
    </DashboardPanel>
  );
}
