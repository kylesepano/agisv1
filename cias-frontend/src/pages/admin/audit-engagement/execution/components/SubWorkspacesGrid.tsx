import {
  ArrowRight,
  ClipboardList,
  FileSpreadsheet,
  FolderLock,
  Layers,
} from "lucide-react";
import { navigateTo } from "../../../../../routes";

type SubWorkspacesGridProps = {
  engagementNumber: string;
};

export function SubWorkspacesGrid({
  engagementNumber,
}: SubWorkspacesGridProps) {
  const subWorkspaces = [
    {
      id: "SCR-227",
      title: "Fieldwork Records",
      description: "Manage physical inspection logs, interviews, audit notes, and daily progress logs.",
      icon: ClipboardList,
      color: "text-blue-600 bg-blue-50 border-blue-200",
      route: `/admin/audit-engagement/${engagementNumber}/execution/records`,
      badge: "SCR-227",
    },
    {
      id: "SCR-228",
      title: "Working Papers (WPs)",
      description: "Index, document, cross-reference, review, and archive audit testing working papers.",
      icon: FileSpreadsheet,
      color: "text-purple-600 bg-purple-50 border-purple-200",
      route: `/admin/audit-engagement/${engagementNumber}/execution/working-papers`,
      badge: "SCR-228",
    },
    {
      id: "SCR-229",
      title: "Audit Evidence & Custody",
      description: "Store electronic evidence files, scanned vouchers, chain of custody logs, and hash integrity.",
      icon: FolderLock,
      color: "text-emerald-600 bg-emerald-50 border-emerald-200",
      route: `/admin/audit-engagement/${engagementNumber}/execution/evidence`,
      badge: "SCR-229",
    },
  ];

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <h4 className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
          <Layers size={13} className="text-blue-600" />
          <span>Execution Sub-Workspaces (SCR-227 ~ SCR-229 Coordinates)</span>
        </h4>
        <span className="text-[10px] text-slate-400 font-mono">
          UID-200 §8.3 Sub-modules
        </span>
      </div>

      <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
        {subWorkspaces.map((ws) => {
          const Icon = ws.icon;

          return (
            <div
              key={ws.id}
              onClick={() => navigateTo(ws.route)}
              className="group rounded-lg border border-slate-200 bg-white p-3 shadow-sm hover:border-blue-300 hover:shadow-md transition cursor-pointer flex flex-col justify-between space-y-2"
            >
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <div className={`grid h-7 w-7 place-items-center rounded-md border ${ws.color}`}>
                    <Icon size={15} />
                  </div>
                  <span className="font-mono text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded">
                    {ws.badge}
                  </span>
                </div>

                <h5 className="font-bold text-slate-900 text-xs group-hover:text-blue-600 transition">
                  {ws.title}
                </h5>

                <p className="text-[11px] text-slate-500 leading-snug">
                  {ws.description}
                </p>
              </div>

              <div className="flex items-center justify-between text-[11px] font-semibold text-blue-600 group-hover:text-blue-700 pt-1 border-t border-slate-100">
                <span>Open Workspace</span>
                <ArrowRight size={12} className="group-hover:translate-x-0.5 transition" />
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
