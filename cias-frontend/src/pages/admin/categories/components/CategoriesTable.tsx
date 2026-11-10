import { Eye, Pencil, Trash2 } from "lucide-react";
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
import { Card, CardContent } from "../../../../components/ui/card";
import { Text } from "../../../../components/ui/typography";
import { cn } from "../../../../utils/helpers";
import type { CategoryRecord } from "../categories-types";
import { CategoryScopeBadge, CategoryStatusBadge } from "./CategoriesUtils";

type CategoriesTableProps = {
  records: CategoryRecord[];
};

function CategoriesMobileCard({ row }: { row: Row<CategoryRecord> }) {
  const record = row.original;

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
      <div className="space-y-1">
        <Text className="font-semibold text-slate-900">{record.name}</Text>
        <Text className="font-medium text-slate-500">{record.code}</Text>
        <Text className="text-slate-500">{record.description}</Text>
      </div>

      <div className="mt-3 grid gap-2.5 text-slate-600">
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Category Type</Text>
          <CategoryScopeBadge scope={record.scope} />
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Linked Units</Text>
          <Text>{record.linkedUnits}</Text>
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Status</Text>
          <CategoryStatusBadge status={record.status} />
        </div>
      </div>

      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        <Button className="h-8 rounded-lg" variant="outline">
          <Eye size={15} />
          View
        </Button>
        <Button className="h-8 rounded-lg" variant="ghost">
          <Pencil size={15} />
          Edit
        </Button>
      </div>
    </div>
  );
}

export function CategoriesTable({ records }: CategoriesTableProps) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const columns = useMemo<ColumnDef<CategoryRecord>[]>(
    () => [
      { accessorKey: "code", header: "Code" },
      {
        accessorKey: "name",
        header: "Category",
        cell: ({ row }) => (
          <div className="min-w-0 space-y-1.5">
            <Text className="block font-semibold text-slate-900">
              {row.original.name}
            </Text>
            <Text className="block max-w-[300px] leading-6 text-slate-500">
              {row.original.description}
            </Text>
          </div>
        ),
      },
      {
        accessorKey: "scope",
        header: "Category Type",
        cell: ({ row }) => <CategoryScopeBadge scope={row.original.scope} />,
      },
      { accessorKey: "linkedUnits", header: "Linked Units" },
      {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => <CategoryStatusBadge status={row.original.status} />,
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
            >
              <Eye size={15} />
            </button>
            <button
              aria-label={`Edit ${row.original.name}`}
              className="transition hover:text-slate-950"
              type="button"
            >
              <Pencil size={15} />
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
    ],
    [],
  );

  // TanStack Table manages imperative instance methods internally.
  // eslint-disable-next-line react-hooks/incompatible-library
  const table = useReactTable({
    data: records,
    columns,
    state: { pagination },
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  });

  const totalEntries = records.length;
  const pageCount = table.getPageCount();
  const currentPage = pagination.pageIndex + 1;
  const pageStart =
    totalEntries === 0 ? 0 : pagination.pageIndex * pagination.pageSize + 1;
  const pageEnd =
    totalEntries === 0
      ? 0
      : Math.min(pageStart + pagination.pageSize - 1, totalEntries);

  return (
    <Card className="rounded-2xl">
      <CardContent className="space-y-3 pt-4">
        <ResponsiveDataTable
          table={table}
          emptyTitle="No categories found."
          tableClassName="min-w-[920px]"
          renderMobileCard={(row) => (
            <CategoriesMobileCard key={row.id} row={row} />
          )}
        />

        <div className="flex items-center justify-between gap-3 text-slate-500 max-[760px]:flex-col max-[760px]:items-start">
          <Text>
            Showing {pageStart} to {pageEnd} of {totalEntries} categories
          </Text>

          <div className="flex max-w-full items-center gap-2 overflow-x-auto pb-1">
            {Array.from({ length: pageCount }, (_, index) => index + 1).map(
              (page) => (
                <button
                  key={page}
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
              ),
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
