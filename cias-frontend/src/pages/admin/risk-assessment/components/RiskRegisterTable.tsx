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
import { Button } from "../../../../components/ui/button";
import { Card, CardContent } from "../../../../components/ui/card";
import { Text } from "../../../../components/ui/typography";
import { ResponsiveDataTable } from "../../../../components/shared/ResponsiveDataTable";
import { RiskRatingBadge } from "./RiskRatingBadge";
import {
  RiskRegisterCategoryBadge,
  RiskRegisterPriorityBadge,
} from "./RiskRegisterUtils";
import { formatAssessmentDate } from "../risk-assessment-utils";
import { cn } from "../../../../utils/helpers";
import type { RiskRegisterRecord } from "../risk-assessment-types";

type RiskRegisterTableRow = RiskRegisterRecord & {
  priority: number;
  rank: number;
};

function RiskRegisterMobileCard({ row }: { row: Row<RiskRegisterTableRow> }) {
  const record = row.original;

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
      <div className="space-y-1">
        <Text className="block font-semibold text-slate-900">
          {record.auditableUnit}
        </Text>
        <Text className="block font-medium text-slate-500">
          #{record.rank} / {record.auditCode}
        </Text>
      </div>

      <div className="mt-3 grid gap-2.5 text-slate-600">
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Category</Text>
          <RiskRegisterCategoryBadge category={record.category} />
        </div>
        <div className="flex items-start justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Office</Text>
          <Text className="max-w-[65%] text-right max-[640px]:max-w-full max-[640px]:text-left">
            {record.office}
          </Text>
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Score</Text>
          <Text className="font-semibold text-slate-800">{record.score}</Text>
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Risk Rating</Text>
          <RiskRatingBadge rating={record.rating} />
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Priority</Text>
          <RiskRegisterPriorityBadge
            priority={record.priority}
            rating={record.rating}
          />
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Assessment Date</Text>
          <Text>{formatAssessmentDate(record.assessmentDate)}</Text>
        </div>
        <div className="flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-medium text-slate-500">Assessor</Text>
          <Text>{record.assessor}</Text>
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

export function RiskRegisterTable({
  records,
}: {
  records: RiskRegisterRecord[];
}) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const tableData = useMemo<RiskRegisterTableRow[]>(
    () =>
      records.map((record, index) => ({
        ...record,
        priority: index + 1,
        rank: index + 1,
      })),
    [records],
  );

  const columns = useMemo<ColumnDef<RiskRegisterTableRow>[]>(
    () => [
      {
        accessorKey: "rank",
        header: () => <span className="whitespace-nowrap">Rank</span>,
        cell: ({ row }) => (
          <Text className="font-semibold text-slate-700">{row.original.rank}</Text>
        ),
      },
      {
        accessorKey: "auditableUnit",
        header: () => <span className="whitespace-nowrap">Audit Unit</span>,
        cell: ({ row }) => (
          <Text className="font-semibold text-blue-700">
            {row.original.auditableUnit}
          </Text>
        ),
      },
      { accessorKey: "auditCode", header: "Audit Code" },
      {
        accessorKey: "category",
        header: "Category",
        cell: ({ row }) => (
          <RiskRegisterCategoryBadge category={row.original.category} />
        ),
      },
      { accessorKey: "office", header: "Office" },
      {
        accessorKey: "score",
        header: () => <span className="whitespace-nowrap">Total Score</span>,
        cell: ({ row }) => (
          <Text className="font-semibold text-slate-800">{row.original.score}</Text>
        ),
      },
      {
        accessorKey: "rating",
        header: () => <span className="whitespace-nowrap">Risk Rating</span>,
        cell: ({ row }) => <RiskRatingBadge rating={row.original.rating} />,
      },
      {
        accessorKey: "priority",
        header: "Priority",
        cell: ({ row }) => (
          <RiskRegisterPriorityBadge
            priority={row.original.priority}
            rating={row.original.rating}
          />
        ),
      },
      {
        accessorKey: "assessmentDate",
        header: () => <span className="whitespace-nowrap">Assessment Date</span>,
        cell: ({ row }) => formatAssessmentDate(row.original.assessmentDate),
      },
      { accessorKey: "assessor", header: "Assessor" },
      {
        id: "actions",
        header: "Actions",
        cell: ({ row }) => (
          <div className="flex items-center gap-3 text-slate-500">
            <button
              aria-label={`View ${row.original.auditableUnit}`}
              className="transition hover:text-slate-950"
              type="button"
            >
              <Eye className="h-4 w-4" />
            </button>
            <button
              aria-label={`Edit ${row.original.auditableUnit}`}
              className="transition hover:text-slate-950"
              type="button"
            >
              <Pencil className="h-4 w-4" />
            </button>
            <button
              aria-label={`Delete ${row.original.auditableUnit}`}
              className="transition hover:text-rose-600"
              type="button"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          </div>
        ),
      },
    ],
    [],
  );

  // TanStack Table manages its own imperative instance methods.
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
    <Card className="rounded-2xl">
      <CardContent className="space-y-3 pt-4">
        <ResponsiveDataTable
          table={table}
          emptyTitle="No risk register results found."
          tableClassName="min-w-[1320px]"
          renderMobileCard={(row) => (
            <RiskRegisterMobileCard key={row.id} row={row} />
          )}
        />

        <div className="flex items-center justify-between gap-3 text-slate-500 max-[760px]:flex-col max-[760px]:items-start">
          <Text>
            Showing {pageStart} to {pageEnd} of {totalEntries} results
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
