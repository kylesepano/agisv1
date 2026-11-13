import { Eye, ChevronLeft, ChevronRight, Pencil, Trash2 } from "lucide-react";
import {
  getCoreRowModel,
  getPaginationRowModel,
  useReactTable,
  type Row,
  type ColumnDef,
  type PaginationState,
} from "@tanstack/react-table";
import { useEffect, useMemo, useState } from "react";
import { ResponsiveDataTable } from "../../../../components/shared/ResponsiveDataTable";
import { Button } from "../../../../components/ui/button";
import { Card, CardContent } from "../../../../components/ui/card";
import { cn } from "../../../../utils/helpers";
import type { UserRecord } from "../users-types";
import {
  getUserAvatarClass,
  getUserInitials,
  UserRoleBadge,
  UserStatusBadge,
} from "./UsersUtils";

type UsersTableProps = {
  records: UserRecord[];
};

function UsersMobileCard({ row }: { row: Row<UserRecord> }) {
  const record = row.original;

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-start gap-3">
        <div
          className={cn(
            "grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-bold",
            getUserAvatarClass(record),
          )}
        >
          {getUserInitials(record.name)}
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-base font-semibold text-slate-900">{record.name}</p>
          <p className="text-sm text-slate-500">{record.title}</p>
        </div>
      </div>

      <div className="mt-4 grid gap-3 text-sm text-slate-600">
        <div className="flex items-start justify-between gap-4">
          <span className="font-medium text-slate-500">Username</span>
          <span>{record.username}</span>
        </div>
        <div className="flex items-start justify-between gap-4">
          <span className="font-medium text-slate-500">Email</span>
          <span className="max-w-[62%] text-right break-all">{record.email}</span>
        </div>
        <div className="flex items-center justify-between gap-4">
          <span className="font-medium text-slate-500">Role</span>
          <UserRoleBadge role={record.role} />
        </div>
        <div className="flex items-start justify-between gap-4">
          <span className="font-medium text-slate-500">Department</span>
          <span className="max-w-[62%] text-right">{record.department}</span>
        </div>
        <div className="flex items-center justify-between gap-4">
          <span className="font-medium text-slate-500">Status</span>
          <UserStatusBadge status={record.status} />
        </div>
      </div>

      <div className="mt-4 flex gap-2">
        <Button className="h-10 flex-1 rounded-xl" variant="outline">
          <Eye size={15} />
          View
        </Button>
        <Button className="h-10 flex-1 rounded-xl" variant="ghost">
          <Pencil size={15} />
          Edit
        </Button>
      </div>
    </div>
  );
}

export function UsersTable({ records }: UsersTableProps) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  useEffect(() => {
    setPagination((current) => ({ ...current, pageIndex: 0 }));
  }, [records]);

  const columns = useMemo<ColumnDef<UserRecord>[]>(
    () => [
      {
        accessorKey: "name",
        header: "Name",
        cell: ({ row }) => (
          <div className="flex items-center gap-3">
            <div
              className={cn(
                "grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-bold",
                getUserAvatarClass(row.original),
              )}
            >
              {getUserInitials(row.original.name)}
            </div>
            <div className="min-w-0">
              <p className="font-semibold text-slate-800">{row.original.name}</p>
              <p className="text-xs text-slate-500">{row.original.title}</p>
            </div>
          </div>
        ),
      },
      { accessorKey: "username", header: "Username" },
      {
        accessorKey: "email",
        header: "Email",
        cell: ({ row }) => (
          <span className="text-sm text-slate-600">{row.original.email}</span>
        ),
      },
      {
        accessorKey: "role",
        header: "Role",
        cell: ({ row }) => <UserRoleBadge role={row.original.role} />,
      },
      {
        accessorKey: "department",
        header: "Department",
        cell: ({ row }) => (
          <span className="text-sm leading-6 text-slate-600">
            {row.original.department}
          </span>
        ),
      },
      {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => <UserStatusBadge status={row.original.status} />,
      },
      {
        id: "lastLogin",
        header: "Last Login",
        cell: ({ row }) => (
          <div>
            <p className="text-sm font-medium text-slate-700">
              {row.original.lastLoginDate}
            </p>
            <p className="text-xs text-slate-500">{row.original.lastLoginTime}</p>
          </div>
        ),
      },
      {
        id: "actions",
        header: "Actions",
        cell: ({ row }) => (
          <div className="flex items-center gap-3 text-slate-400">
            <button
              aria-label={`View ${row.original.name}`}
              className="transition hover:text-slate-900"
              type="button"
            >
              <Eye size={15} />
            </button>
            <button
              aria-label={`Edit ${row.original.name}`}
              className="transition hover:text-slate-900"
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

  const pageCount = table.getPageCount();
  const currentPage = pagination.pageIndex + 1;
  const totalEntries = records.length;
  const pageStart =
    totalEntries === 0 ? 0 : pagination.pageIndex * pagination.pageSize + 1;
  const pageEnd =
    totalEntries === 0
      ? 0
      : Math.min(pageStart + pagination.pageSize - 1, totalEntries);

  return (
    <Card className="rounded-3xl">
      <CardContent className="space-y-4 pt-6">
        <ResponsiveDataTable
          table={table}
          emptyTitle="No users found."
          tableClassName="min-w-[980px]"
          renderMobileCard={(row) => <UsersMobileCard key={row.id} row={row} />}
        />

        <div className="flex items-center justify-between gap-4 text-sm text-slate-500 max-[760px]:flex-col max-[760px]:items-start">
          <p>
            Showing {pageStart} to {pageEnd} of {totalEntries} users
          </p>

          <div className="flex items-center gap-2">
            <button
              className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 disabled:cursor-not-allowed disabled:opacity-50"
              type="button"
              onClick={() => table.previousPage()}
              disabled={!table.getCanPreviousPage()}
            >
              <ChevronLeft size={15} />
            </button>

            {Array.from({ length: pageCount }, (_, index) => index + 1).map(
              (page) => (
                <button
                  key={page}
                  className={cn(
                    "grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700",
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

            <button
              className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 disabled:cursor-not-allowed disabled:opacity-50"
              type="button"
              onClick={() => table.nextPage()}
              disabled={!table.getCanNextPage()}
            >
              <ChevronRight size={15} />
            </button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
