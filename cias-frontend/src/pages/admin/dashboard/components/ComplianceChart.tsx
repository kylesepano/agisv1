import { ArrowRight } from "lucide-react";
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from "recharts";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "../../../../components/ui/card";
import { Badge } from "../../../../components/ui/badge";
import { Heading, Text } from "../../../../components/ui/typography";

const riskDistributionData = [
  { name: "High Risk", value: 48, color: "#ff4456" },
  { name: "Medium Risk", value: 82, color: "#ffb11f" },
  { name: "Low Risk", value: 56, color: "#4ade80" },
];

const highRiskOffices = [
  { name: "CMISID", value: 24, color: "#3b82f6" },
  { name: "HRMO", value: 18, color: "#4ade80" },
  { name: "CBO", value: 17, color: "#8b5cf6" },
  { name: "Accounting", value: 15, color: "#fbbf24" },
  { name: "Engineering", value: 13, color: "#fb7185" },
];

const recentRiskAssessments = [
  {
    auditUnit: "Procurement Process",
    office: "BAC Secretariat",
    rating: "High",
    assessedOn: "May 20, 2025",
  },
  {
    auditUnit: "Payroll Process",
    office: "HRMO",
    rating: "High",
    assessedOn: "May 19, 2025",
  },
  {
    auditUnit: "General Fund",
    office: "Treasurer's Office",
    rating: "Medium",
    assessedOn: "May 19, 2025",
  },
  {
    auditUnit: "Revenue Collection",
    office: "CBO",
    rating: "Medium",
    assessedOn: "May 18, 2025",
  },
  {
    auditUnit: "IT Network Infrastructure",
    office: "CMISID",
    rating: "Low",
    assessedOn: "May 18, 2025",
  },
];

function total(items: Array<{ value: number }>) {
  return items.reduce((sum, item) => sum + item.value, 0);
}

function percent(value: number, sum: number) {
  return `${((value / sum) * 100).toFixed(1)}%`;
}

function ReportLink({ label = "View full report" }: { label?: string }) {
  return (
    <button
      className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition hover:text-blue-700"
      type="button"
    >
      {label}
      <ArrowRight size={14} />
    </button>
  );
}

function riskBadgeTone(rating: string) {
  if (rating === "High") return "bg-rose-100 text-rose-700";
  if (rating === "Medium") return "bg-amber-100 text-amber-700";
  return "bg-emerald-100 text-emerald-700";
}

function DonutCenter({ value, label }: { value: string; label: string }) {
  return (
    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
      <Heading as={3} className="text-slate-950">
        {value}
      </Heading>
      <Text className="text-slate-700">{label}</Text>
    </div>
  );
}

function RiskDistributionLegend() {
  const sum = total(riskDistributionData);

  return (
    <div className="space-y-3">
      {riskDistributionData.map((item) => (
        <div
          key={item.name}
          className="flex items-center justify-between gap-3"
        >
          <div className="flex items-center gap-2.5">
            <span
              className="h-2.5 w-2.5 rounded-full"
              style={{ backgroundColor: item.color }}
            />
            <Text className="text-slate-700">{item.name}</Text>
          </div>
          <Text className="font-semibold text-slate-700">
            {item.value} ({percent(item.value, sum)})
          </Text>
        </div>
      ))}
    </div>
  );
}

export function ComplianceChart() {
  return (
    <div className="grid gap-3 xl:grid-cols-[1.05fr_0.95fr_1.5fr]">
      <Card className="rounded-2xl p-0">
        <CardHeader className="px-3.5 pb-0 pt-3.5">
          <CardTitle className="text-sm uppercase tracking-[0.06em] text-slate-700">
            Risk Distribution
          </CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-3 px-3.5 pb-3.5 pt-2.5 sm:flex-row sm:items-center">
          <div className="relative h-44 w-full max-w-[190px] shrink-0">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={riskDistributionData}
                  dataKey="value"
                  innerRadius={42}
                  outerRadius={72}
                  paddingAngle={0}
                  strokeWidth={0}
                >
                  {riskDistributionData.map((entry) => (
                    <Cell key={entry.name} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
            <DonutCenter value="186" label="Total" />
          </div>

          <div className="min-w-0 flex-1">
            <RiskDistributionLegend />
            <ReportLink />
          </div>
        </CardContent>
      </Card>

      <Card className="rounded-2xl p-0">
        <CardHeader className="px-3.5 pb-0 pt-3.5">
          <CardTitle className="text-sm uppercase tracking-[0.06em] text-slate-700">
            Top High-Risk Offices
          </CardTitle>
        </CardHeader>
        <CardContent className="px-3.5 pb-3.5 pt-2.5">
          <div className="space-y-3.5">
            {highRiskOffices.map((entry) => (
              <div
                key={entry.name}
                className="grid grid-cols-[84px_minmax(0,1fr)_26px] items-center gap-3"
              >
                <Text className="font-semibold text-slate-700">
                  {entry.name}
                </Text>
                <div className="h-2.5 overflow-hidden rounded-full bg-slate-100">
                  <div
                    className="h-full rounded-full"
                    style={{
                      width: `${(entry.value / highRiskOffices[0].value) * 100}%`,
                      backgroundColor: entry.color,
                    }}
                  />
                </div>
                <Text className="text-right font-semibold text-slate-700">
                  {entry.value}
                </Text>
              </div>
            ))}
          </div>

          <ReportLink />
        </CardContent>
      </Card>

      <Card className="rounded-2xl p-0">
        <CardHeader className="px-3.5 pb-0 pt-3.5">
          <CardTitle className="text-sm uppercase tracking-[0.06em] text-slate-700">
            Recent Risk Assessments
          </CardTitle>
        </CardHeader>
        <CardContent className="px-3.5 pb-3.5 pt-2.5">
          <div className="overflow-x-auto rounded-xl border border-slate-100">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-50 text-[11px] uppercase tracking-[0.06em] text-slate-500">
                <tr>
                  <th className="px-3 py-2 font-semibold">Audit Unit</th>
                  <th className="px-3 py-2 font-semibold">Office</th>
                  <th className="px-3 py-2 font-semibold">Risk Rating</th>
                  <th className="px-3 py-2 font-semibold">Assessed On</th>
                </tr>
              </thead>
              <tbody>
                {recentRiskAssessments.map((item) => (
                  <tr
                    key={`${item.auditUnit}-${item.assessedOn}`}
                    className="border-t border-slate-100"
                  >
                    <td className="px-3 py-2 text-slate-800">
                      {item.auditUnit}
                    </td>
                    <td className="px-3 py-2 text-slate-600">{item.office}</td>
                    <td className="px-3 py-2">
                      <Badge
                        className={riskBadgeTone(item.rating)}
                        tone="neutral"
                      >
                        {item.rating}
                      </Badge>
                    </td>
                    <td className="px-3 py-2 text-slate-600">
                      {item.assessedOn}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <ReportLink label="View all assessments" />
        </CardContent>
      </Card>
    </div>
  );
}
