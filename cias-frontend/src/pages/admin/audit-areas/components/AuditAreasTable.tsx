import {
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Pencil,
  Power,
  Trash2,
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
import type { AuditAreaRecord } from "../audit-areas-types";
import { AuditAreaDetailsPanel } from "./AuditAreaDetailsPanel";
import { AuditAreaStatusBadge } from "./AuditAreasUtils";

type AuditAreaTableRow = AuditAreaRecord & {
  rank: number;
};

type AuditAreasTableProps = {
  records: AuditAreaRecord[];
  selectedAreaId: string | null;
  onSelectArea: (area: AuditAreaRecord) => void;
  onEdit: (area: AuditAreaRecord) => void;
  onDeactivate: (area: AuditAreaRecord) => void;
};

function AuditAreaMobileCard({
  row,
  selected,
  onSelect,
  onEdit,
  onDeactivate,
}: {
  row: Row<AuditAreaTableRow>;
  selected: boolean;
  onSelect: (area: AuditAreaRecord) => void;
  onEdit: (area: AuditAreaRecord) => void;
  onDeactivate: (area: AuditAreaRecord) => void;
}) {
  const record = row.original;

  return (
    <button
      aria-label={`Select ${record.auditAreaName || record.name}`}
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
            {record.auditAreaId || record.code}
          </span>
          <Text className="block mt-0.5 text-xs font-bold text-slate-900 leading-snug">
            {record.auditAreaName || record.name}
          </Text>
          <Text className="block text-[11px] font-medium text-slate-500">
            {record.officeName}
          </Text>
        </div>
        <AuditAreaStatusBadge status={record.status} />
      </div>

      <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600 bg-slate-50/70 rounded-lg p-2 border border-slate-100">
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            CATEGORY
          </span>
          <span className="text-[11px] font-semibold text-slate-800">
            {record.auditAreaCategory}
          </span>
        </div>
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            GOVERNANCE
          </span>
          <span className="text-[11px] font-medium text-slate-700 truncate block">
            MDS-000 Rule 7
          </span>
        </div>
      </div>

      <div className="mt-3 flex items-center justify-end gap-2">
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
        <Button
          className={cn(
            "h-7.5 rounded-lg px-3 text-xs",
            record.status === "Active"
              ? "text-rose-700 hover:bg-rose-50 border-rose-200"
              : "text-emerald-700 hover:bg-emerald-50 border-emerald-200",
          )}
          variant="outline"
          onClick={(e) => {
            e.stopPropagation();
            onDeactivate(record);
          }}
        >
          <Power size={13} className="mr-1" />
          {record.status === "Active" ? "Deactivate" : "Activate"}
        </Button>
      </div>
    </button>
  );
}

export function AuditAreasTable({
  records,
  selectedAreaId,
  onSelectArea,
  onEdit,
  onDeactivate,
}: AuditAreasTableProps) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const tableData = useMemo<AuditAreaTableRow[]>(
    () => records.map((record, index) => ({ ...record, rank: index + 1 })),
    [records],
  );

  const selectedRecord =
    tableData.find(
      (record) =>
        record.auditAreaId === selectedAreaId || record.code === selectedAreaId,
    ) ??
    tableData[0] ??
    null;

  const columns = useMemo<ColumnDef<AuditAreaTableRow>[]>(
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
        accessorKey: "auditAreaId",
        header: "AREA ID",
        cell: ({ row }) => (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onSelectArea(row.original);
            }}
            className="font-mono text-xs font-bold text-[#0284c7] hover:underline cursor-pointer text-left"
            title="Inspect Audit Area Details"
          >
            {row.original.auditAreaId || row.original.code}
          </button>
        ),
      },
      {
        accessorKey: "auditAreaName",
        header: "AUDIT AREA & DESCRIPTION",
        cell: ({ row }) => (
          <div className="max-w-[280px]">
            <p className="text-xs font-bold text-slate-900 truncate">
              {row.original.auditAreaName || row.original.name}
            </p>
            {row.original.description && (
              <p className="text-[11px] text-slate-500 truncate mt-0.5">
                {row.original.description}
              </p>
            )}
          </div>
        ),
      },
      {
        accessorKey: "officeName",
        header: "PARENT OFFICE",
        cell: ({ row }) => (
          <div className="text-xs space-y-0.5 max-w-[200px]">
            <span className="font-semibold text-slate-800 block truncate" title={row.original.officeName}>
              {row.original.officeName || "—"}
            </span>
            <span className="text-[10px] text-blue-600 font-mono block">
              {row.original.officeId || "—"}
            </span>
          </div>
        ),
      },
      {
        accessorKey: "auditAreaCategory",
        header: "CATEGORY",
        cell: ({ row }) => (
          <span className="text-xs font-medium text-slate-700">
            {row.original.auditAreaCategory}
          </span>
        ),
      },
      {
        accessorKey: "status",
        header: "STATUS",
        cell: ({ row }) => (
          <AuditAreaStatusBadge status={row.original.status} />
        ),
      },
      {
        id: "actions",
        header: "ACTIONS",
        cell: ({ row }) => {
          const area = row.original;
          const isActive = area.status === "Active";

          return (
            <div className="flex items-center justify-end gap-1.5 text-slate-400">
              <button
                aria-label={`Edit ${area.auditAreaName || area.name}`}
                className="grid h-7 w-7 place-items-center rounded-lg hover:bg-slate-100 hover:text-blue-700 transition cursor-pointer"
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  onEdit(area);
                }}
                title="Edit Audit Area Profile"
              >
                <Pencil size={14} />
              </button>
              <button
                aria-label={isActive ? "Deactivate audit area" : "Reactivate audit area"}
                className={cn(
                  "grid h-7 w-7 place-items-center rounded-lg transition cursor-pointer",
                  isActive
                    ? "hover:bg-rose-50 hover:text-rose-600"
                    : "hover:bg-emerald-50 hover:text-emerald-700 text-emerald-600",
                )}
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  onDeactivate(area);
                }}
                title={isActive ? "Deactivate Area (Soft Delete)" : "Reactivate Area"}
              >
                {isActive ? <Trash2 size={14} /> : <CheckCircle2 size={14} />}
              </button>
            </div>
          );
        },
      },
    ],
    [onDeactivate, onEdit, onSelectArea],
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
          emptyTitle="No audit areas match the selected filters."
          tableClassName="min-w-[840px]"
          getDesktopRowClassName={(row) =>
            cn(
              "cursor-pointer transition-colors duration-150",
              (row.original.auditAreaId === selectedRecord?.auditAreaId ||
                row.original.code === selectedRecord?.code)
                ? "bg-blue-50/70 hover:bg-blue-50/80"
                : "hover:bg-slate-50/80",
            )
          }
          renderMobileCard={(row) => (
            <AuditAreaMobileCard
              key={row.id}
              row={row}
              selected={
                row.original.auditAreaId === selectedRecord?.auditAreaId ||
                row.original.code === selectedRecord?.code
              }
              onSelect={onSelectArea}
              onEdit={onEdit}
              onDeactivate={onDeactivate}
            />
          )}
        />

        {/* Pagination Footer */}
        <div className="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3.5 text-xs text-slate-500 max-[860px]:flex-col max-[860px]:items-start">
          <Text className="text-xs text-slate-500">
            Showing <strong className="text-slate-800">{pageStart}</strong> to{" "}
            <strong className="text-slate-800">{pageEnd}</strong> of{" "}
            <strong className="text-slate-800">{totalEntries}</strong> audit areas
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
      <AuditAreaDetailsPanel
        record={selectedRecord}
        onEdit={onEdit}
        onDeactivate={onDeactivate}
      />
    </div>
  );
}
