import {
  ChevronLeft,
  ChevronRight,
  EllipsisVertical,
  Eye,
  Pencil,
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
import { cn } from "../../../../utils/helpers";
import type { AnnualAuditPlanRecord } from "../annual-audit-planning-types";
import { formatAuditScheduleDate } from "../annual-audit-planning-utils";
import {
  AnnualAuditPlanCategoryBadge,
  AnnualAuditPlanRiskBadge,
} from "./AnnualAuditPlanBadges";
import { AnnualAuditPlanDetailsPanel } from "./AnnualAuditPlanDetailsPanel";
import { PlanStatusBadge } from "./PlanStatusBadge";

type AnnualAuditPlanTableRow = AnnualAuditPlanRecord & {
  rank: number;
};

type AnnualAuditPlanTableProps = {
  records: AnnualAuditPlanRecord[];
  selectedPlanId?: string | null;
  onSelectPlan?: (plan: AnnualAuditPlanRecord) => void;
  onView?: (plan: AnnualAuditPlanRecord) => void;
  onEdit?: (plan: AnnualAuditPlanRecord) => void;
};

function AnnualAuditPlanMobileCard({
  row,
  selected,
  onSelect,
  onView,
  onEdit,
}: {
  row: Row<AnnualAuditPlanTableRow>;
  selected: boolean;
  onSelect?: (plan: AnnualAuditPlanRecord) => void;
  onView?: (plan: AnnualAuditPlanRecord) => void;
  onEdit?: (plan: AnnualAuditPlanRecord) => void;
}) {
  const record = row.original;

  return (
    <button
      aria-label={`Select ${record.subject}`}
      className={cn(
        "w-full rounded-xl border border-slate-200 bg-white p-3.5 text-left shadow-sm transition",
        selected && "border-blue-300 bg-blue-50/40 ring-1 ring-blue-500/20",
      )}
      type="button"
      onClick={() => onSelect?.(record)}
    >
      <div className="flex items-start justify-between gap-2">
        <div>
          <span className="font-mono text-xs font-bold text-blue-600">
            {record.code}
          </span>
          <Text className="block mt-0.5 text-xs font-bold text-slate-900 leading-snug">
            {record.subject}
          </Text>
          <Text className="block text-[11px] font-medium text-slate-500">
            {record.office}
          </Text>
        </div>
        <PlanStatusBadge status={record.status} />
      </div>

      <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600 bg-slate-50/70 rounded-lg p-2 border border-slate-100">
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            CATEGORY
          </span>
          <div className="mt-0.5">
            <AnnualAuditPlanCategoryBadge category={record.category} />
          </div>
        </div>
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            RISK RATING
          </span>
          <div className="mt-0.5">
            <AnnualAuditPlanRiskBadge rating={record.riskRating} />
          </div>
        </div>
        <div className="col-span-2">
          <span className="text-[10px] text-slate-400 font-semibold block">
            TARGET TIMELINE
          </span>
          <span className="text-[11px] font-medium text-slate-700 block">
            {formatAuditScheduleDate(record.planStart)} – {formatAuditScheduleDate(record.planEnd)}
          </span>
        </div>
      </div>

      <div className="mt-3 flex items-center justify-end gap-2">
        {onView && (
          <Button
            className="h-7.5 rounded-lg px-3 text-xs"
            variant="outline"
            onClick={(e) => {
              e.stopPropagation();
              onView(record);
            }}
          >
            <Eye size={13} className="mr-1" />
            View
          </Button>
        )}
        {onEdit && (
          <Button
            className="h-7.5 rounded-lg px-3 text-xs"
            variant="outline"
            onClick={(e) => {
              e.stopPropagation();
              onEdit(record);
            }}
          >
            <Pencil size={13} className="mr-1" />
            Edit
          </Button>
        )}
      </div>
    </button>
  );
}

export function AnnualAuditPlanTable({
  records,
  selectedPlanId: controlledSelectedPlanId,
  onSelectPlan,
  onView,
  onEdit,
}: AnnualAuditPlanTableProps) {
  const [internalSelectedId, setInternalSelectedId] = useState<string | null>(null);
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const tableData = useMemo<AnnualAuditPlanTableRow[]>(
    () => records.map((record, index) => ({ ...record, rank: index + 1 })),
    [records],
  );

  const selectedPlanId = controlledSelectedPlanId ?? internalSelectedId;
  const selectedRecord =
    tableData.find(
      (record) => record.code === selectedPlanId || record.id === selectedPlanId,
    ) ??
    tableData[0] ??
    null;

  const handleSelect = (plan: AnnualAuditPlanRecord) => {
    setInternalSelectedId(plan.code);
    onSelectPlan?.(plan);
  };

  const columns = useMemo<ColumnDef<AnnualAuditPlanTableRow>[]>(
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
        header: "PLAN CODE",
        cell: ({ row }) => (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              handleSelect(row.original);
            }}
            className="font-mono text-xs font-bold text-[#0284c7] hover:underline cursor-pointer text-left"
            title="Inspect Plan Item Details"
          >
            {row.original.code}
          </button>
        ),
      },
      {
        accessorKey: "subject",
        header: "AUDIT SUBJECT",
        cell: ({ row }) => (
          <div className="max-w-[260px]">
            <p className="text-xs font-bold text-slate-900 truncate">
              {row.original.subject}
            </p>
            <p className="text-[11px] text-slate-500 truncate mt-0.5">
              FY {row.original.year} • {row.original.quarter}
            </p>
          </div>
        ),
      },
      {
        accessorKey: "office",
        header: "OFFICE / UNIT",
        cell: ({ row }) => (
          <span className="text-xs font-semibold text-slate-800 block truncate max-w-[200px]" title={row.original.office}>
            {row.original.office}
          </span>
        ),
      },
      {
        accessorKey: "category",
        header: "CATEGORY",
        cell: ({ row }) => (
          <AnnualAuditPlanCategoryBadge category={row.original.category} />
        ),
      },
      {
        accessorKey: "riskRating",
        header: "RISK RATING",
        cell: ({ row }) => (
          <AnnualAuditPlanRiskBadge rating={row.original.riskRating} />
        ),
      },
      {
        accessorKey: "planStart",
        header: "TARGET START",
        cell: ({ row }) => (
          <span className="text-xs text-slate-600">
            {formatAuditScheduleDate(row.original.planStart)}
          </span>
        ),
      },
      {
        accessorKey: "planEnd",
        header: "TARGET END",
        cell: ({ row }) => (
          <span className="text-xs text-slate-600">
            {formatAuditScheduleDate(row.original.planEnd)}
          </span>
        ),
      },
      {
        accessorKey: "status",
        header: "PLAN STATUS",
        cell: ({ row }) => <PlanStatusBadge status={row.original.status} />,
      },
      {
        id: "actions",
        header: "ACTIONS",
        cell: ({ row }) => {
          const plan = row.original;
          return (
            <div className="flex items-center justify-end gap-1.5 text-slate-400">
              <button
                aria-label={`View ${plan.subject}`}
                className="grid h-7 w-7 place-items-center rounded-lg hover:bg-slate-100 hover:text-blue-700 transition cursor-pointer"
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  handleSelect(plan);
                  onView?.(plan);
                }}
                title="View Plan Details"
              >
                <Eye size={14} />
              </button>
              <button
                aria-label={`Edit ${plan.subject}`}
                className="grid h-7 w-7 place-items-center rounded-lg hover:bg-slate-100 hover:text-blue-700 transition cursor-pointer"
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  onEdit?.(plan);
                }}
                title="Edit Plan Parameters"
              >
                <Pencil size={14} />
              </button>
              <button
                aria-label={`More actions for ${plan.subject}`}
                className="grid h-7 w-7 place-items-center rounded-lg hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer"
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  handleSelect(plan);
                }}
                title="More Options"
              >
                <EllipsisVertical size={14} />
              </button>
            </div>
          );
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [onEdit, onView],
  );

  // TanStack Table
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
          emptyTitle="No annual audit plans found."
          tableClassName="min-w-[980px]"
          getDesktopRowClassName={(row) =>
            cn(
              "cursor-pointer transition-colors duration-150",
              (row.original.code === selectedRecord?.code ||
                row.original.id === selectedRecord?.id)
                ? "bg-blue-50/70 hover:bg-blue-50/80"
                : "hover:bg-slate-50/80",
            )
          }
          renderMobileCard={(row) => (
            <AnnualAuditPlanMobileCard
              key={row.id}
              row={row}
              selected={
                row.original.code === selectedRecord?.code ||
                row.original.id === selectedRecord?.id
              }
              onSelect={handleSelect}
              onView={onView}
              onEdit={onEdit}
            />
          )}
        />

        {/* Pagination Footer */}
        <div className="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3.5 text-xs text-slate-500 max-[860px]:flex-col max-[860px]:items-start">
          <Text className="text-xs text-slate-500">
            Showing <strong className="text-slate-800">{pageStart}</strong> to{" "}
            <strong className="text-slate-800">{pageEnd}</strong> of{" "}
            <strong className="text-slate-800">{totalEntries}</strong> plan items
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
      <AnnualAuditPlanDetailsPanel
        record={selectedRecord}
        onView={onView}
        onEdit={onEdit}
      />
    </div>
  );
}
