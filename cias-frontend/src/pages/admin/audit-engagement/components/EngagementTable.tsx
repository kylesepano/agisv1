import {
  ChevronLeft,
  ChevronRight,
  Eye,
  Layers,
} from "lucide-react";
import {
  getCoreRowModel,
  getPaginationRowModel,
  useReactTable,
  type ColumnDef,
  type PaginationState,
  type Row,
} from "@tanstack/react-table";
import { useMemo, useState } from "react";
import { ResponsiveDataTable } from "../../../../components/shared/ResponsiveDataTable";
import { Button } from "../../../../components/ui/button";
import { Select2 } from "../../../../components/ui/select2";
import { Text } from "../../../../components/ui/typography";
import { navigateTo } from "../../../../routes";
import { cn } from "../../../../utils/helpers";
import type { AuditEngagementRecord } from "../audit-engagement-types";
import { formatEngagementDate } from "../audit-engagement-utils";
import { EngagementAuditTypeBadge, EngagementRiskBadge } from "./EngagementBadges";
import { EngagementDetailsPanel } from "./EngagementDetailsPanel";
import { EngagementStatusBadge } from "./EngagementStatusBadge";

type EngagementTableRow = AuditEngagementRecord & {
  rank: number;
};

function EngagementMobileCard({
  row,
  selected,
  onSelect,
}: {
  row: Row<EngagementTableRow>;
  selected: boolean;
  onSelect: (record: AuditEngagementRecord) => void;
}) {
  const record = row.original;

  return (
    <button
      aria-label={`Select ${record.auditArea || record.subject}`}
      className={cn(
        "w-full rounded-xl border border-slate-200 bg-white p-3.5 text-left shadow-sm transition",
        selected && "border-blue-300 bg-blue-50/40 ring-1 ring-blue-500/20",
      )}
      type="button"
      onClick={() => onSelect(record)}
    >
      <div className="flex items-start justify-between gap-2">
        <div>
          <span className="font-mono text-xs font-bold text-blue-600">
            {record.code}
          </span>
          <Text className="block mt-0.5 text-xs font-bold text-slate-900">
            {record.auditArea || record.subject}
          </Text>
          <Text className="block text-[11px] font-medium text-slate-500">
            {record.office}
          </Text>
        </div>
        <EngagementStatusBadge status={record.status} />
      </div>

      <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600 bg-slate-50/70 rounded-lg p-2 border border-slate-100">
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            AUDIT TYPE
          </span>
          <span className="text-[11px] font-semibold text-slate-800">
            {record.auditType || record.category}
          </span>
        </div>
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            LEAD AUDITOR
          </span>
          <span className="text-[11px] font-medium text-slate-700">
            {record.leadAuditor}
          </span>
        </div>
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            PERIOD
          </span>
          <span className="font-mono text-[10px] text-slate-600">
            {formatEngagementDate(record.startDate)} ~ {formatEngagementDate(record.endDate)}
          </span>
        </div>
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            RISK (OD-003)
          </span>
          <EngagementRiskBadge rating={record.riskRating} showProvisional={false} />
        </div>
      </div>

      <div className="mt-3 flex items-center justify-end gap-2">
        <Button
          className="h-7.5 rounded-lg px-3 text-xs"
          variant="outline"
          onClick={(e) => {
            e.stopPropagation();
            navigateTo(`/admin/audit-engagement/${record.code}`);
          }}
        >
          <Eye size={13} className="mr-1" />
          Details (SCR-220)
        </Button>
        <Button
          className="h-7.5 rounded-lg px-3 text-xs bg-blue-600 text-white hover:bg-blue-700"
          onClick={(e) => {
            e.stopPropagation();
            navigateTo(`/admin/audit-engagement/${record.code}/define-scope`);
          }}
        >
          <Layers size={13} className="mr-1" />
          Scope (SCR-212)
        </Button>
      </div>
    </button>
  );
}

export function EngagementTable({
  records,
  selectedEngagementId,
  onSelectEngagement,
}: {
  records: AuditEngagementRecord[];
  selectedEngagementId: string | null;
  onSelectEngagement: (record: AuditEngagementRecord) => void;
}) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const tableData = useMemo<EngagementTableRow[]>(
    () => records.map((record, index) => ({ ...record, rank: index + 1 })),
    [records],
  );

  const selectedRecord =
    tableData.find((record) => record.id === selectedEngagementId || record.code === selectedEngagementId) ??
    tableData[0] ??
    null;

  const columns = useMemo<ColumnDef<EngagementTableRow>[]>(
    () => [
      {
        accessorKey: "rank",
        header: "#",
        cell: ({ row }) => (
          <span className="text-[11px] text-slate-400 font-mono">
            {row.original.rank}
          </span>
        ),
      },
      {
        accessorKey: "code",
        header: "ENGAGEMENT NO.",
        cell: ({ row }) => (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              navigateTo(`/admin/audit-engagement/${row.original.code}`);
            }}
            className="font-mono text-xs font-bold text-[#0284c7] hover:underline cursor-pointer text-left"
            title="Open Engagement Details (SCR-220)"
          >
            {row.original.code}
          </button>
        ),
      },
      {
        accessorKey: "auditArea",
        header: "AUDIT AREA / OFFICE",
        cell: ({ row }) => (
          <div className="max-w-[280px]">
            <p className="text-xs font-bold text-slate-900 truncate">
              {row.original.auditArea || row.original.subject}
            </p>
            <p className="text-[11px] text-slate-500 truncate mt-0.5">
              {row.original.office}
            </p>
          </div>
        ),
      },
      {
        accessorKey: "auditType",
        header: "AUDIT TYPE",
        cell: ({ row }) => (
          <EngagementAuditTypeBadge
            auditType={row.original.auditType || row.original.category || "Operations Audit"}
          />
        ),
      },
      {
        accessorKey: "leadAuditor",
        header: "LEAD AUDITOR",
        cell: ({ row }) => (
          <span className="text-xs font-medium text-slate-700">
            {row.original.leadAuditor}
          </span>
        ),
      },
      {
        accessorKey: "startDate",
        header: "PERIOD COVERED",
        cell: ({ row }) => (
          <span className="font-mono text-[11px] text-slate-600">
            {formatEngagementDate(row.original.startDate)} — {formatEngagementDate(row.original.endDate)}
          </span>
        ),
      },
      {
        accessorKey: "status",
        header: "STATUS",
        cell: ({ row }) => (
          <EngagementStatusBadge status={row.original.status} showDot />
        ),
      },
      {
        id: "actions",
        header: "ACTIONS",
        cell: ({ row }) => (
          <div className="flex items-center justify-end gap-1.5 text-slate-400">
            <button
              aria-label={`Open Full Workspace for ${row.original.code}`}
              className="grid h-7 w-7 place-items-center rounded-lg hover:bg-slate-100 hover:text-blue-700 transition cursor-pointer"
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                navigateTo(`/admin/audit-engagement/${row.original.code}`);
              }}
              title="Open Full Engagement Workspace (SCR-220)"
            >
              <Eye size={14} />
            </button>
            <button
              aria-label={`Define Scope for ${row.original.code}`}
              className="grid h-7 w-7 place-items-center rounded-lg hover:bg-blue-50 hover:text-blue-700 transition"
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                navigateTo(`/admin/audit-engagement/${row.original.code}/define-scope`);
              }}
              title="Proceed to Define Scope (SCR-212)"
            >
              <Layers size={14} />
            </button>
          </div>
        ),
      },
    ],
    [onSelectEngagement],
  );

  // TanStack Table manages imperative instance methods internally.
  // eslint-disable-next-line react-hooks/incompatible-library
  const table = useReactTable({
    data: tableData,
    columns,
    state: { pagination },
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  });

  const totalEntries = tableData.length;
  const pageCount = table.getPageCount();
  const currentPage = pagination.pageIndex + 1;
  const pageStart =
    totalEntries === 0 ? 0 : pagination.pageIndex * pagination.pageSize + 1;
  const pageEnd =
    totalEntries === 0
      ? 0
      : Math.min(pageStart + pagination.pageSize - 1, totalEntries);

  return (
    <div className="grid gap-2.5 xl:grid-cols-[minmax(0,1fr)_330px]">
      {/* Table Container */}
      <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <ResponsiveDataTable
          table={table}
          emptyTitle="No audit engagements match the selected filters."
          tableClassName="min-w-[940px]"
          getDesktopRowClassName={(row) =>
            cn(
              "cursor-pointer transition-colors duration-150",
              (row.original.id === selectedRecord?.id || row.original.code === selectedRecord?.code)
                ? "bg-blue-50/70 hover:bg-blue-50/80"
                : "hover:bg-slate-50/80",
            )
          }
          renderMobileCard={(row) => (
            <EngagementMobileCard
              key={row.id}
              row={row}
              selected={row.original.id === selectedRecord?.id || row.original.code === selectedRecord?.code}
              onSelect={onSelectEngagement}
            />
          )}
        />

        {/* Pagination Footer */}
        <div className="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3.5 text-xs text-slate-500 max-[860px]:flex-col max-[860px]:items-start">
          <Text className="text-xs text-slate-500">
            Showing <strong className="text-slate-800">{pageStart}</strong> to{" "}
            <strong className="text-slate-800">{pageEnd}</strong> of{" "}
            <strong className="text-slate-800">{totalEntries}</strong> engagements
          </Text>

          <div className="flex max-w-full items-center gap-1.5 overflow-x-auto pb-1">
            <button
              className="grid h-7 w-7 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 disabled:opacity-40 hover:bg-slate-50 transition"
              type="button"
              disabled={!table.getCanPreviousPage()}
              onClick={() => table.previousPage()}
              title="Previous Page"
            >
              <ChevronLeft size={14} />
            </button>

            {Array.from({ length: pageCount }, (_, index) => index + 1).map(
              (page) => (
                <button
                  key={page}
                  className={cn(
                    "grid h-7 w-7 place-items-center rounded-lg border text-xs font-semibold transition",
                    currentPage === page
                      ? "border-blue-600 bg-blue-600 text-white"
                      : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50",
                  )}
                  type="button"
                  onClick={() => table.setPageIndex(page - 1)}
                >
                  {page}
                </button>
              ),
            )}

            <button
              className="grid h-7 w-7 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 disabled:opacity-40 hover:bg-slate-50 transition"
              type="button"
              disabled={!table.getCanNextPage()}
              onClick={() => table.nextPage()}
              title="Next Page"
            >
              <ChevronRight size={14} />
            </button>

            <div className="ml-2 min-w-[90px]">
              <Select2
                value={String(pagination.pageSize)}
                options={[
                  { value: "10", label: "10 / page" },
                  { value: "20", label: "20 / page" },
                  { value: "50", label: "50 / page" },
                ]}
                onChange={(value) =>
                  setPagination((current) => ({
                    ...current,
                    pageIndex: 0,
                    pageSize: Number(value),
                  }))
                }
              />
            </div>
          </div>
        </div>
      </div>

      {/* Side Details Panel */}
      <EngagementDetailsPanel
        record={selectedRecord}
        onClose={() => {}}
      />
    </div>
  );
}
