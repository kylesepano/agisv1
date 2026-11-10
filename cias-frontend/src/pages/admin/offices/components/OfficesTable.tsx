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
import type { OfficeRecord } from "../offices-types";
import { OfficeDetailsPanel } from "./OfficeDetailsPanel";
import { OfficeStatusBadge } from "./OfficesUtils";

type OfficeTableRow = OfficeRecord & {
  rank: number;
};

type OfficesTableProps = {
  records: OfficeRecord[];
  selectedOfficeId: string | null;
  onSelectOffice: (office: OfficeRecord) => void;
  onEdit: (office: OfficeRecord) => void;
  onDeactivate: (office: OfficeRecord) => void;
};

function OfficesMobileCard({
  row,
  selected,
  onSelect,
  onEdit,
  onDeactivate,
}: {
  row: Row<OfficeTableRow>;
  selected: boolean;
  onSelect: (office: OfficeRecord) => void;
  onEdit: (office: OfficeRecord) => void;
  onDeactivate: (office: OfficeRecord) => void;
}) {
  const record = row.original;

  return (
    <button
      aria-label={`Select ${record.officeName || record.name}`}
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
            {record.officeId || record.code}
          </span>
          <Text className="block mt-0.5 text-xs font-bold text-slate-900 leading-snug">
            {record.officeName || record.name}
          </Text>
          <Text className="block text-[11px] font-medium text-slate-500">
            {record.officeAcronym || record.shortName}
          </Text>
        </div>
        <OfficeStatusBadge status={record.status} />
      </div>

      <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600 bg-slate-50/70 rounded-lg p-2 border border-slate-100">
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            CATEGORY
          </span>
          <span className="text-[11px] font-semibold text-slate-800">
            {record.officeCategory || "Line Department"}
          </span>
        </div>
        <div>
          <span className="text-[10px] text-slate-400 font-semibold block">
            DEPARTMENT HEAD
          </span>
          <span className="text-[11px] font-medium text-slate-700 truncate block">
            {record.head || "—"}
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

export function OfficesTable({
  records,
  selectedOfficeId,
  onSelectOffice,
  onEdit,
  onDeactivate,
}: OfficesTableProps) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const tableData = useMemo<OfficeTableRow[]>(
    () => records.map((record, index) => ({ ...record, rank: index + 1 })),
    [records],
  );

  const selectedRecord =
    tableData.find(
      (record) =>
        record.officeId === selectedOfficeId || record.code === selectedOfficeId,
    ) ??
    tableData[0] ??
    null;

  const columns = useMemo<ColumnDef<OfficeTableRow>[]>(
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
        accessorKey: "officeId",
        header: "OFFICE ID",
        cell: ({ row }) => (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onSelectOffice(row.original);
            }}
            className="font-mono text-xs font-bold text-[#0284c7] hover:underline cursor-pointer text-left"
            title="Inspect Office Details"
          >
            {row.original.officeId || row.original.code}
          </button>
        ),
      },
      {
        accessorKey: "officeName",
        header: "OFFICE / DEPARTMENT",
        cell: ({ row }) => (
          <div className="max-w-[280px]">
            <p className="text-xs font-bold text-slate-900 truncate">
              {row.original.officeName || row.original.name}
            </p>
            <p className="text-[11px] text-slate-500 truncate mt-0.5">
              <span className="font-semibold text-blue-600">
                {row.original.officeAcronym || row.original.shortName}
              </span>
              {row.original.description && (
                <span className="text-slate-400"> — {row.original.description}</span>
              )}
            </p>
          </div>
        ),
      },
      {
        accessorKey: "officeCategory",
        header: "CATEGORY",
        cell: ({ row }) => (
          <span className="text-xs font-medium text-slate-700">
            {row.original.officeCategory || "Line Department"}
          </span>
        ),
      },
      {
        accessorKey: "head",
        header: "DEPARTMENT HEAD",
        cell: ({ row }) => (
          <div className="text-xs space-y-0.5">
            <span className="font-semibold text-slate-800 block">
              {row.original.head || "—"}
            </span>
            <span className="text-[11px] text-slate-400 font-mono block">
              {row.original.contactEmail || "—"}
            </span>
          </div>
        ),
      },
      {
        accessorKey: "status",
        header: "STATUS",
        cell: ({ row }) => (
          <OfficeStatusBadge status={row.original.status} />
        ),
      },
      {
        id: "actions",
        header: "ACTIONS",
        cell: ({ row }) => {
          const office = row.original;
          const isActive = office.status === "Active";

          return (
            <div className="flex items-center justify-end gap-1.5 text-slate-400">
              <button
                aria-label={`Edit ${office.officeName || office.name}`}
                className="grid h-7 w-7 place-items-center rounded-lg hover:bg-slate-100 hover:text-blue-700 transition cursor-pointer"
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  onEdit(office);
                }}
                title="Edit Office Profile"
              >
                <Pencil size={14} />
              </button>
              <button
                aria-label={isActive ? "Deactivate office" : "Reactivate office"}
                className={cn(
                  "grid h-7 w-7 place-items-center rounded-lg transition cursor-pointer",
                  isActive
                    ? "hover:bg-rose-50 hover:text-rose-600"
                    : "hover:bg-emerald-50 hover:text-emerald-700 text-emerald-600",
                )}
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  onDeactivate(office);
                }}
                title={isActive ? "Deactivate Office (Soft Delete)" : "Reactivate Office"}
              >
                {isActive ? <Trash2 size={14} /> : <CheckCircle2 size={14} />}
              </button>
            </div>
          );
        },
      },
    ],
    [onDeactivate, onEdit, onSelectOffice],
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
          emptyTitle="No auditee offices match the selected filters."
          tableClassName="min-w-[840px]"
          getDesktopRowClassName={(row) =>
            cn(
              "cursor-pointer transition-colors duration-150",
              (row.original.officeId === selectedRecord?.officeId ||
                row.original.code === selectedRecord?.code)
                ? "bg-blue-50/70 hover:bg-blue-50/80"
                : "hover:bg-slate-50/80",
            )
          }
          renderMobileCard={(row) => (
            <OfficesMobileCard
              key={row.id}
              row={row}
              selected={
                row.original.officeId === selectedRecord?.officeId ||
                row.original.code === selectedRecord?.code
              }
              onSelect={onSelectOffice}
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
            <strong className="text-slate-800">{totalEntries}</strong> auditee offices
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
      <OfficeDetailsPanel
        record={selectedRecord}
        onEdit={onEdit}
        onDeactivate={onDeactivate}
      />
    </div>
  );
}
