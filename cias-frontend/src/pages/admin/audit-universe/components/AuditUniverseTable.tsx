import { Eye, SquarePen, Trash2 } from "lucide-react";
import {
  getCoreRowModel,
  getPaginationRowModel,
  useReactTable,
  type Row,
  type ColumnDef,
  type PaginationState,
} from "@tanstack/react-table";
import { useEffect, useState } from "react";
import { Badge } from "../../../../components/ui/badge";
import { Card, CardContent } from "../../../../components/ui/card";
import { Button } from "../../../../components/ui/button";
import { Text } from "../../../../components/ui/typography";
import { ResponsiveDataTable } from "../../../../components/shared/ResponsiveDataTable";
import { cn } from "../../../../utils/helpers";
import type { AuditUniverseRecord } from "../audit-universe-types";
import {
  AuditUniverseCategoryBadge,
  AuditUniverseRiskBadge,
} from "./AuditUniverseUtils";

type AuditUniverseTableProps = {
  records: AuditUniverseRecord[];
  selectedAuditCode: string | null;
  onView: (code: string) => void;
};

function AuditUniverseMobileCard({
  row,
  selected,
  onView,
}: {
  row: Row<AuditUniverseRecord>;
  selected: boolean;
  onView: (code: string) => void;
}) {
  const record = row.original;

  return (
    <div
      className={cn(
        "rounded-xl border border-slate-200 bg-white p-3 shadow-sm",
        selected && "border-blue-200 bg-blue-50/60",
      )}
    >
      <div className="space-y-1">
        <Text className="font-semibold text-slate-900">{record.name}</Text>
        <Text className="font-medium text-slate-500">{record.code}</Text>
        <Text className="text-slate-500">{record.description}</Text>
      </div>

      <div className="mt-3 grid gap-2.5 text-slate-600">
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Category</Text>
          <AuditUniverseCategoryBadge category={record.category} />
        </div>
        <div className="flex items-start justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Department</Text>
          <Text className="max-w-[65%] text-right max-[640px]:max-w-full max-[640px]:text-left">
            {record.department}
          </Text>
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Risk</Text>
          <AuditUniverseRiskBadge riskLevel={record.riskLevel} />
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Status</Text>
          <Badge tone="success">{record.status}</Badge>
        </div>
      </div>

      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        <Button
          className="h-8 rounded-lg"
          variant="outline"
          onClick={() => onView(record.code)}
        >
          <Eye size={15} />
          View
        </Button>
        <Button className="h-8 rounded-lg" variant="ghost">
          <SquarePen size={15} />
          Edit
        </Button>
      </div>
    </div>
  );
}

export function AuditUniverseTable({
  records,
  selectedAuditCode,
  onView,
}: AuditUniverseTableProps) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const columns: ColumnDef<AuditUniverseRecord>[] = [
    { accessorKey: "code", header: "Code" },
    {
      accessorKey: "name",
      header: "Auditable Unit",
      cell: ({ row }) => (
        <div className="min-w-0 space-y-1.5">
          <Text className="block font-semibold text-slate-900">
            {row.original.name}
          </Text>
          <Text className="block max-w-[340px] text-xs leading-5 text-slate-500">
            {row.original.description}
          </Text>
        </div>
      ),
    },
    {
      accessorKey: "category",
      header: "Category",
      cell: ({ row }) => (
        <AuditUniverseCategoryBadge category={row.original.category} />
      ),
    },
    { accessorKey: "department", header: "Responsible Office" },
    {
      accessorKey: "riskLevel",
      header: "Risk Level",
      cell: ({ row }) => (
        <AuditUniverseRiskBadge riskLevel={row.original.riskLevel} />
      ),
    },
    { accessorKey: "lastAudit", header: "Last Audit" },
    { accessorKey: "nextAudit", header: "Next Audit" },
    {
      accessorKey: "status",
      header: "Status",
      cell: ({ row }) => <Badge tone="success">{row.original.status}</Badge>,
    },
    {
      id: "actions",
      header: "Actions",
      cell: ({ row }) => (
        <div className="flex items-center gap-3 text-slate-500">
          <button
            aria-label={`View ${row.original.name}`}
            className="transition hover:text-slate-950"
            type="button"
            onClick={() => onView(row.original.code)}
          >
            <Eye size={16} />
          </button>
          <button
            aria-label={`Edit ${row.original.name}`}
            className="transition hover:text-slate-950"
            type="button"
          >
            <SquarePen size={15} />
          </button>
          <button
            aria-label={`Delete ${row.original.name}`}
            className="transition hover:text-rose-600"
            type="button"
          >
            <Trash2 size={15} />
          </button>
        </div>
      ),
    },
  ];

  // TanStack Table manages its own imperative instance methods.
  // eslint-disable-next-line react-hooks/incompatible-library
  const table = useReactTable({
    data: records,
    columns,
    state: {
      pagination,
    },
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  });

  useEffect(() => {
    const maxPageIndex = Math.max(
      0,
      Math.ceil(records.length / pagination.pageSize) - 1,
    );

    if (pagination.pageIndex > maxPageIndex) {
      setPagination((current) => ({
        ...current,
        pageIndex: maxPageIndex,
      }));
    }
  }, [pagination.pageIndex, pagination.pageSize, records.length]);

  const pageCount = table.getPageCount();
  const currentPage = pagination.pageIndex + 1;
  const totalEntries = records.length;
  const pageStart =
    totalEntries === 0 ? 0 : pagination.pageIndex * pagination.pageSize + 1;
  const pageEnd =
    totalEntries === 0
      ? 0
      : Math.min(pageStart + pagination.pageSize - 1, totalEntries);

  const visiblePages = Array.from(
    { length: pageCount },
    (_, index) => index + 1,
  ).filter((page) => {
    if (pageCount <= 5) {
      return true;
    }

    return (
      Math.abs(page - currentPage) <= 1 || page === 1 || page === pageCount
    );
  });

  return (
    <Card className="rounded-2xl">
      <CardContent className="space-y-3 pt-4">
        <ResponsiveDataTable
          table={table}
          emptyTitle="No audit universe records found."
          getDesktopRowClassName={(row) =>
            cn(
              selectedAuditCode === row.original.code &&
                "bg-blue-50/70 hover:bg-blue-50/70",
            )
          }
          renderMobileCard={(row) => (
            <AuditUniverseMobileCard
              key={row.id}
              row={row}
              selected={selectedAuditCode === row.original.code}
              onView={onView}
            />
          )}
        />

        <div className="flex items-center justify-between gap-3 text-slate-500 max-[760px]:flex-col max-[760px]:items-start">
          <Text>
            Showing {pageStart} to {pageEnd} of {totalEntries} entries
          </Text>
          <div className="flex max-w-full items-center gap-2 overflow-x-auto pb-1">
            <button
              className="grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-500 disabled:cursor-not-allowed disabled:opacity-50"
              type="button"
              onClick={() => table.previousPage()}
              disabled={!table.getCanPreviousPage()}
            >
              {"<"}
            </button>
            {visiblePages.map((page, index) => {
              const previousPage = visiblePages[index - 1];
              const shouldShowEllipsis =
                previousPage != null && page - previousPage > 1;

              return (
                <div key={page} className="flex items-center gap-2">
                  {shouldShowEllipsis ? (
                    <Text className="px-1">...</Text>
                  ) : null}
                  <button
                    className={cn(
                      "grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-700",
                      currentPage === page &&
                        "border-blue-600 bg-blue-600 text-white",
                    )}
                    type="button"
                    onClick={() => table.setPageIndex(page - 1)}
                  >
                    {page}
                  </button>
                </div>
              );
            })}
            <button
              className="grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-500 disabled:cursor-not-allowed disabled:opacity-50"
              type="button"
              onClick={() => table.nextPage()}
              disabled={!table.getCanNextPage()}
            >
              {">"}
            </button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
