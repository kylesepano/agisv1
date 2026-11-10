import { ChevronRight, FileSearch } from "lucide-react";
import { Badge } from "../../../../components/ui/badge";
import { Heading, Text } from "../../../../components/ui/typography";
import type { AuditUniverseRecord } from "../audit-universe-types";
import {
  AuditUniverseCategoryBadge,
  AuditUniverseRiskBadge,
} from "./AuditUniverseUtils";

type AuditUniverseDetailsContentProps = {
  audit: AuditUniverseRecord;
};

type AuditHistoryItem = {
  auditNo: string;
  title: string;
  auditDate: string;
  status: "Completed";
  auditor: string;
};

const auditHistoryByCode: Record<string, AuditHistoryItem[]> = {
  "AU-2025-001": [
    {
      auditNo: "AUD-2024-001",
      title: "HRMO Payroll Audit 2024",
      auditDate: "2024-06-15",
      status: "Completed",
      auditor: "CIAS Audit Team A",
    },
    {
      auditNo: "AUD-2022-003",
      title: "HRMO Compliance Audit 2022",
      auditDate: "2022-04-10",
      status: "Completed",
      auditor: "CIAS Audit Team B",
    },
    {
      auditNo: "AUD-2020-005",
      title: "HRMO Operations Review 2020",
      auditDate: "2020-02-18",
      status: "Completed",
      auditor: "CIAS Audit Team C",
    },
  ],
  "AU-2025-002": [
    {
      auditNo: "AUD-2024-004",
      title: "Payroll Process Audit 2024",
      auditDate: "2024-04-10",
      status: "Completed",
      auditor: "CIAS Audit Team A",
    },
    {
      auditNo: "AUD-2021-011",
      title: "Payroll Controls Review 2021",
      auditDate: "2021-05-06",
      status: "Completed",
      auditor: "CIAS Audit Team B",
    },
  ],
};

export function AuditUniverseDetailsContent({
  audit,
}: AuditUniverseDetailsContentProps) {
  const auditHistory = auditHistoryByCode[audit.code] ?? [];

  const detailRows = [
    { label: "Code", value: audit.code },
    { label: "Name", value: audit.name },
    {
      label: "Category",
      value: <AuditUniverseCategoryBadge category={audit.category} />,
    },
    { label: "Department", value: audit.department },
    { label: "Business Owner", value: audit.businessOwner },
    { label: "Technical Owner", value: audit.technicalOwner },
    {
      label: "Risk Level",
      value: <AuditUniverseRiskBadge riskLevel={audit.riskLevel} />,
    },
    { label: "Last Audit", value: audit.lastAudit },
    { label: "Next Audit", value: audit.nextAudit },
  ];

  return (
    <div className="space-y-3">
      {detailRows.map((item) => (
        <div
          key={item.label}
          className="grid grid-cols-[92px_1fr] gap-3 border-b border-slate-100 pb-2.5"
        >
          <Text className="text-xs font-semibold text-slate-600">
            {item.label}
          </Text>
          <div className="text-sm text-slate-800">{item.value}</div>
        </div>
      ))}

      <div className="overflow-hidden rounded-2xl border border-slate-200">
        <div className="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5">
          <Heading as={5} className="text-sm text-slate-800">
            Audit History
          </Heading>
          <Text className="text-xs font-semibold text-slate-500">
            Total Audits Conducted: {auditHistory.length}
          </Text>
        </div>

        {auditHistory.length > 0 ? (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full border-collapse">
                <thead>
                  <tr className="border-b border-slate-100 bg-white text-left">
                    {[
                      "Audit No.",
                      "Audit Title",
                      "Audit Date",
                      "Status",
                      "Auditor",
                    ].map((heading) => (
                      <th
                        key={heading}
                        className="px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500"
                      >
                        {heading}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {auditHistory.map((item) => (
                    <tr
                      key={item.auditNo}
                      className="border-b border-slate-100 last:border-b-0"
                    >
                      <td className="px-4 py-2.5 text-xs font-medium text-slate-700">
                        {item.auditNo}
                      </td>
                      <td className="px-4 py-2.5 text-xs text-slate-700">
                        {item.title}
                      </td>
                      <td className="px-4 py-2.5 text-xs text-slate-700">
                        {item.auditDate}
                      </td>
                      <td className="px-4 py-2.5">
                        <Badge tone="success">{item.status}</Badge>
                      </td>
                      <td className="px-4 py-2.5 text-xs text-slate-700">
                        {item.auditor}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <button
              className="flex w-full items-center justify-center gap-1 border-t border-slate-100 bg-white px-4 py-2.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50"
              type="button"
            >
              View Complete History
              <ChevronRight size={15} />
            </button>
          </>
        ) : (
          <div className="px-4 py-6">
            <div className="flex flex-col items-center justify-center rounded-xl border border-blue-100 bg-gradient-to-b from-slate-50 to-white px-4 py-6 text-center">
              <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                <FileSearch size={18} />
              </div>
              <Text className="text-sm font-semibold text-slate-700">
                No audit records available.
              </Text>
              <Text className="mt-1 max-w-[230px] text-xs leading-5 text-slate-500">
                Audit history will appear once audits have been conducted for
                this unit.
              </Text>
            </div>
          </div>
        )}
      </div>

      <div className="grid grid-cols-[92px_1fr] gap-3 border-b border-slate-100 pb-2.5">
        <Text className="text-xs font-semibold text-slate-600">Status</Text>
        <div className="text-sm text-slate-800">
          <Badge tone="success">Active</Badge>
        </div>
      </div>

      <div className="space-y-1">
        <Heading as={5} className="text-sm text-slate-700">
          Description
        </Heading>
        <Text className="leading-6 text-slate-600">{audit.description}</Text>
      </div>

      <div className="space-y-1">
        <Heading as={5} className="text-sm text-slate-700">
          Remarks
        </Heading>
        <Text className="leading-6 text-slate-600">{audit.remarks}</Text>
      </div>

      <div className="space-y-1.5 border-t border-slate-100 pt-3">
        {[
          ["Created By", audit.createdBy],
          ["Date Created", audit.createdAt],
          ["Last Updated", audit.updatedAt],
        ].map(([label, value]) => (
          <div key={label} className="grid grid-cols-[92px_1fr] gap-3">
            <Text className="text-xs font-semibold text-slate-600">
              {label}
            </Text>
            <Text className="text-sm text-slate-800">{value}</Text>
          </div>
        ))}
      </div>
    </div>
  );
}
