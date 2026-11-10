import { ClipboardList } from "lucide-react";
import { tasks as defaultTasks } from "../data";
import { DashboardPanel } from "./DashboardPanel";
import type { DashboardTask } from "../../../../services/dashboardService";

type TasksPanelProps = {
  tasks?: DashboardTask[];
};

export function TasksPanel({ tasks: liveTasks }: TasksPanelProps) {
  const items: Array<[string, string, string]> =
    liveTasks && liveTasks.length > 0
      ? liveTasks.map((t) => [t.title, t.code, t.due || "Open"])
      : (defaultTasks as unknown as Array<[string, string, string]>);

  return (
    <DashboardPanel title="My Tasks" action="View All">
      <div className="flex h-full flex-col space-y-2.5">
        {items.map(([title, code, date]) => (
          <div
            key={title}
            className="group flex cursor-pointer items-start justify-between gap-2.5 rounded-md border border-slate-100 px-2.5 py-2 transition-all duration-200 hover:-translate-y-[1px] hover:border-blue-100 hover:bg-slate-50/80 hover:shadow-sm"
          >
            <div className="flex min-w-0 items-start gap-2.5">
              <div className="mt-0.5 rounded-md bg-slate-100 p-1.5 text-slate-500 transition-colors duration-200 group-hover:bg-blue-50 group-hover:text-blue-600">
                <ClipboardList size={16} />
              </div>
              <div className="min-w-0">
                <p className="truncate text-[13px] font-semibold text-slate-700 transition-colors duration-200 group-hover:text-slate-900">{title}</p>
                <p className="text-[11px] text-slate-400">{code}</p>
              </div>
            </div>
            <span className="shrink-0 text-[11px] font-semibold text-slate-400">{date}</span>
          </div>
        ))}
      </div>
    </DashboardPanel>
  );
}
