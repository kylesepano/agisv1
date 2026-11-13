import { engagements as defaultEngagements } from "../data";
import { DashboardPanel } from "./DashboardPanel";
import { getAuditEngagementRecords } from "../../audit-engagement/service";
import { navigateTo } from "../../../../routes";
import type { DashboardRecentEngagement } from "../../../../services/dashboardService";

type RecentEngagementsPanelProps = {
  engagements?: DashboardRecentEngagement[];
};

export function RecentEngagementsPanel({ engagements: liveEngagements }: RecentEngagementsPanelProps) {
  let items: Array<{
    id: string;
    number: string;
    office: string;
    status: string;
    progress: string;
    risk: string;
  }> = [];

  if (liveEngagements && liveEngagements.length > 0) {
    items = liveEngagements.map((item) => ({
      id: item.number,
      number: item.number,
      office: item.office || "City Office",
      status: item.phase || item.status.replaceAll("_", " "),
      progress: item.phase === "COMPLETION" ? "100%" : item.phase === "REPORTING" ? "80%" : item.phase === "EXECUTION" ? "50%" : "25%",
      risk: "Medium",
    }));
  } else {
    const records = getAuditEngagementRecords();
    if (records.length > 0) {
      items = records.slice(0, 5).map((r) => ({
        id: r.id,
        number: r.code || `AEM-${r.id}`,
        office: r.office || "City Office",
        status: r.phase || r.status || "Planning",
        progress: `${r.progress ?? 45}%`,
        risk: r.riskRating || "Medium",
      }));
    } else {
      items = defaultEngagements.map(([number, office, status, progress, risk]) => ({
        id: number,
        number,
        office,
        status,
        progress,
        risk,
      }));
    }
  }

  const handleRowClick = (id: string) => {
    navigateTo(`/admin/audit-engagement/${encodeURIComponent(id)}`);
  };

  return (
    <DashboardPanel
      title="Recent Audit Engagements"
      action="View All"
      onActionClick={() => navigateTo("/admin/audit-engagements")}
    >
      <div className="hidden overflow-x-auto md:block">
        <table className="min-w-[620px] text-left text-[12px] xl:min-w-full">
          <thead className="text-[10px] uppercase tracking-[0.08em] text-slate-400">
            <tr>
              <th className="pb-2.5 font-semibold">Engagement No.</th>
              <th className="pb-2.5 font-semibold">Office</th>
              <th className="pb-2.5 font-semibold">Status</th>
              <th className="pb-2.5 font-semibold">Progress</th>
              <th className="pb-2.5 font-semibold">Risk Level</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr
                key={item.number}
                onClick={() => handleRowClick(item.id)}
                className="cursor-pointer border-t border-slate-100 text-slate-600 transition-colors duration-200 hover:bg-slate-50"
              >
                <td className="py-2.5 pr-3 text-[11px] font-medium text-blue-600">
                  {item.number}
                </td>
                <td className="py-2.5 pr-3">{item.office}</td>
                <td className="py-2.5 pr-3">{item.status}</td>
                <td className="py-2.5 pr-3">{item.progress}</td>
                <td className="py-2.5 text-[11px] font-semibold text-slate-500">
                  {item.risk}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="space-y-2.5 md:hidden">
        {items.map((item) => (
          <article
            key={item.number}
            onClick={() => handleRowClick(item.id)}
            className="cursor-pointer rounded-md border border-slate-100 px-2.5 py-2 text-[11px] text-slate-600 transition-all duration-200 hover:-translate-y-[1px] hover:border-blue-100 hover:bg-slate-50/80 hover:shadow-sm"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-blue-600">{item.number}</p>
                <p className="mt-0.5 text-slate-700">{item.office}</p>
              </div>
              <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                {item.risk}
              </span>
            </div>
            <div className="mt-2 grid grid-cols-2 gap-2 text-[10px] uppercase tracking-[0.06em] text-slate-400">
              <span>Status</span>
              <span>Progress</span>
            </div>
            <div className="mt-1 grid grid-cols-2 gap-2 text-[11px] font-medium text-slate-700">
              <span>{item.status}</span>
              <span>{item.progress}</span>
            </div>
          </article>
        ))}
      </div>
    </DashboardPanel>
  );
}
