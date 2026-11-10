import {
  FileText,
  Layers,
  Sparkles,
  Users,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type { FullEngagementDetails } from "../engagement-details-types";

type QuickActionsBarProps = {
  details: FullEngagementDetails;
};

export function QuickActionsBar({ details }: QuickActionsBarProps) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-blue-100 bg-blue-50/40 p-2.5">
      <div className="flex items-center gap-1.5 text-xs text-blue-950 font-bold">
        <Sparkles size={13} className="text-blue-600" />
        <span>Context Actions:</span>
        <span className="text-[11px] font-normal text-blue-800 hidden sm:inline">
          Ready for Planning & Fieldwork Execution
        </span>
      </div>

      <div className="flex items-center gap-1.5 flex-wrap">
        <Button
          type="button"
          className="h-8 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 flex items-center gap-1.5 shadow-2xs cursor-pointer"
          onClick={() => navigateTo(`/admin/audit-engagement/${details.code}/prepare-order`)}
        >
          <FileText size={12} />
          <span>AEO Document (SCR-214)</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          className="h-8 rounded-md bg-white border-slate-200 text-slate-700 text-xs font-semibold px-2.5 flex items-center gap-1.5 hover:bg-slate-50 cursor-pointer"
          onClick={() => navigateTo(`/admin/audit-engagement/${details.code}/define-scope`)}
        >
          <Layers size={12} className="text-slate-500" />
          <span>Scope (SCR-212)</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          className="h-8 rounded-md bg-white border-slate-200 text-slate-700 text-xs font-semibold px-2.5 flex items-center gap-1.5 hover:bg-slate-50 cursor-pointer"
          onClick={() => navigateTo(`/admin/audit-engagement/${details.code}/team-assignment`)}
        >
          <Users size={12} className="text-slate-500" />
          <span>Team (SCR-213)</span>
        </Button>
      </div>
    </div>
  );
}
