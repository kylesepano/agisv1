import { Eye, Plus, SquarePen } from "lucide-react";
import { Badge } from "../../../../components/ui/badge";
import { Button } from "../../../../components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "../../../../components/ui/card";

const recentAuditUniverseRows = [
  {
    code: "AU-2025-186",
    name: "City Government Network Infrastructure",
    category: "System",
    department: "CMISID",
    riskLevel: "High",
    status: "Active",
  },
  {
    code: "AU-2025-185",
    name: "Payroll Process",
    category: "Process",
    department: "HRMO",
    riskLevel: "High",
    status: "Active",
  },
  {
    code: "AU-2025-184",
    name: "General Fund",
    category: "Fund",
    department: "Treasurer's Office",
    riskLevel: "Medium",
    status: "Active",
  },
  {
    code: "AU-2025-183",
    name: "Procurement Process",
    category: "Process",
    department: "BAC Secretariat",
    riskLevel: "Medium",
    status: "Active",
  },
  {
    code: "AU-2025-182",
    name: "Collection of Real Property Tax",
    category: "Process",
    department: "City Treasurer's Office",
    riskLevel: "Medium",
    status: "Active",
  },
];

const recentRiskAssessmentRows = [
  {
    auditUnit: "Procurement Process",
    office: "BAC Secretariat",
    riskScore: 23,
    riskRating: "High",
    assessor: "Juan Dela Cruz",
    assessedOn: "May 20, 2025",
  },
  {
    auditUnit: "Payroll Process",
    office: "HRMO",
    riskScore: 22,
    riskRating: "High",
    assessor: "Juan Dela Cruz",
    assessedOn: "May 19, 2025",
  },
  {
    auditUnit: "General Fund",
    office: "Treasurer's Office",
    riskScore: 16,
    riskRating: "Medium",
    assessor: "Maria Santos",
    assessedOn: "May 19, 2025",
  },
  {
    auditUnit: "Revenue Collection",
    office: "CBO",
    riskScore: 15,
    riskRating: "Medium",
    assessor: "Maria Santos",
    assessedOn: "May 18, 2025",
  },
  {
    auditUnit: "IT Network Infrastructure",
    office: "CMISID",
    riskScore: 9,
    riskRating: "Low",
    assessor: "Juan Dela Cruz",
    assessedOn: "May 18, 2025",
  },
];

function categoryTone(category: string) {
  if (category === "System") return "bg-blue-100 text-blue-700";
  if (category === "Process") return "bg-emerald-100 text-emerald-700";
  if (category === "Fund") return "bg-amber-100 text-amber-700";
  if (category === "Project") return "bg-violet-100 text-violet-700";
  return "bg-slate-100 text-slate-700";
}

function riskTone(riskLevel: string) {
  if (riskLevel === "High") return "bg-rose-100 text-rose-700";
  if (riskLevel === "Medium") return "bg-amber-100 text-amber-700";
  return "bg-emerald-100 text-emerald-700";
}

function DashboardTableShell({
  title,
  action,
  children,
  footerLabel,
}: {
  title: string;
  action?: React.ReactNode;
  children: React.ReactNode;
  footerLabel: string;
}) {
  return (
    <Card className="rounded-2xl p-0">
      <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 px-3.5 pb-0 pt-3.5">
        <CardTitle className="text-sm uppercase tracking-[0.06em] text-slate-700">
          {title}
        </CardTitle>
        {action}
      </CardHeader>
      <CardContent className="px-3.5 pb-3.5 pt-2.5">
        {children}
        <button
          className="mt-2.5 inline-flex items-center gap-2 text-sm font-semibold text-blue-600 transition hover:text-blue-700"
          type="button"
        >
          {footerLabel}
        </button>
      </CardContent>
    </Card>
  );
}

export function RecentActivities() {
  return (
    <div className="grid gap-3 xl:grid-cols-2">
      <DashboardTableShell
        title="Recently Added Audit Universe"
        footerLabel="View all audit universe"
        action={
          <Button className="h-7 rounded-lg px-2.5 text-xs" variant="primary">
            <Plus size={13} />
            Add New
          </Button>
        }
      >
        <div className="overflow-x-auto rounded-xl border border-slate-100">
          <table className="min-w-[860px] text-left text-sm">
            <thead className="bg-slate-50 text-[11px] uppercase tracking-[0.06em] text-slate-500">
              <tr>
                <th className="px-3 py-2 font-semibold">Code</th>
                <th className="px-3 py-2 font-semibold">Name</th>
                <th className="px-3 py-2 font-semibold">Category</th>
                <th className="px-3 py-2 font-semibold">Department</th>
                <th className="px-3 py-2 font-semibold">Risk Level</th>
                <th className="px-3 py-2 font-semibold">Status</th>
                <th className="px-3 py-2 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              {recentAuditUniverseRows.map((row) => (
                <tr key={row.code} className="border-t border-slate-100">
                  <td className="px-3 py-2 text-slate-700">{row.code}</td>
                  <td className="px-3 py-2 font-medium text-slate-800">{row.name}</td>
                  <td className="px-3 py-2">
                    <Badge className={categoryTone(row.category)} tone="neutral">
                      {row.category}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 text-slate-600">{row.department}</td>
                  <td className="px-3 py-2">
                    <Badge className={riskTone(row.riskLevel)} tone="neutral">
                      {row.riskLevel}
                    </Badge>
                  </td>
                  <td className="px-3 py-2">
                    <Badge tone="success">{row.status}</Badge>
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex items-center gap-2.5 text-slate-500">
                      <button className="transition hover:text-slate-900" type="button" aria-label={`View ${row.name}`}>
                        <Eye size={15} />
                      </button>
                      <button className="transition hover:text-slate-900" type="button" aria-label={`Edit ${row.name}`}>
                        <SquarePen size={14} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </DashboardTableShell>

      <DashboardTableShell
        title="Recent Risk Assessments (All)"
        footerLabel="View all risk assessments"
        action={
          <Button className="h-7 rounded-lg px-2.5 text-xs" variant="secondary">
            Go to Risk Register
          </Button>
        }
      >
        <div className="overflow-x-auto rounded-xl border border-slate-100">
          <table className="min-w-[760px] text-left text-sm">
            <thead className="bg-slate-50 text-[11px] uppercase tracking-[0.06em] text-slate-500">
              <tr>
                <th className="px-3 py-2 font-semibold">Audit Unit</th>
                <th className="px-3 py-2 font-semibold">Office</th>
                <th className="px-3 py-2 font-semibold">Risk Score</th>
                <th className="px-3 py-2 font-semibold">Risk Rating</th>
                <th className="px-3 py-2 font-semibold">Assessor</th>
                <th className="px-3 py-2 font-semibold">Assessed On</th>
              </tr>
            </thead>
            <tbody>
              {recentRiskAssessmentRows.map((row) => (
                <tr key={`${row.auditUnit}-${row.assessedOn}`} className="border-t border-slate-100">
                  <td className="px-3 py-2 font-medium text-slate-800">{row.auditUnit}</td>
                  <td className="px-3 py-2 text-slate-600">{row.office}</td>
                  <td className="px-3 py-2 text-slate-700">{row.riskScore}</td>
                  <td className="px-3 py-2">
                    <Badge className={riskTone(row.riskRating)} tone="neutral">
                      {row.riskRating}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 text-slate-600">{row.assessor}</td>
                  <td className="px-3 py-2 text-slate-600">{row.assessedOn}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </DashboardTableShell>
    </div>
  );
}
