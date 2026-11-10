import {
  flexRender,
  type Row,
  type Table as TanStackTable,
} from "@tanstack/react-table";
import { useEffect, useState, type ReactNode } from "react";
import { EmptyState } from "./EmptyState";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "../ui/table";
import { cn } from "../../utils/helpers";

type ResponsiveDataTableProps<TData> = {
  table: TanStackTable<TData>;
  renderMobileCard: (row: Row<TData>) => ReactNode;
  getDesktopRowClassName?: (row: Row<TData>) => string | undefined;
  tableClassName?: string;
  emptyTitle?: string;
};

const MOBILE_TABLE_BREAKPOINT = 1100;

function useMediaQuery(query: string) {
  const [matches, setMatches] = useState(() => {
    if (typeof window === "undefined") {
      return false;
    }

    return window.matchMedia(query).matches;
  });

  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }

    const mediaQuery = window.matchMedia(query);
    const handleChange = (event: MediaQueryListEvent) => {
      setMatches(event.matches);
    };

    setMatches(mediaQuery.matches);
    mediaQuery.addEventListener("change", handleChange);

    return () => {
      mediaQuery.removeEventListener("change", handleChange);
    };
  }, [query]);

  return matches;
}

export function ResponsiveDataTable<TData>({
  table,
  renderMobileCard,
  getDesktopRowClassName,
  tableClassName,
  emptyTitle = "No records found.",
}: ResponsiveDataTableProps<TData>) {
  const isMobile = useMediaQuery(
    `(max-width: ${MOBILE_TABLE_BREAKPOINT}px)`,
  );
  const rows = table.getRowModel().rows;

  if (rows.length === 0) {
    return (
      <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-10">
        <EmptyState title={emptyTitle} />
      </div>
    );
  }

  if (isMobile) {
    return <div className="space-y-3">{rows.map((row) => renderMobileCard(row))}</div>;
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200">
      <Table className={tableClassName}>
        <TableHeader className="bg-slate-50">
          {table.getHeaderGroups().map((headerGroup) => (
            <TableRow key={headerGroup.id} className="hover:bg-slate-50">
              {headerGroup.headers.map((header) => (
                <TableHead key={header.id}>
                  {header.isPlaceholder
                    ? null
                    : flexRender(
                        header.column.columnDef.header,
                        header.getContext(),
                      )}
                </TableHead>
              ))}
            </TableRow>
          ))}
        </TableHeader>
        <TableBody>
          {rows.map((row) => (
            <TableRow
              key={row.id}
              className={cn(getDesktopRowClassName?.(row))}
            >
              {row.getVisibleCells().map((cell) => (
                <TableCell key={cell.id}>
                  {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </TableCell>
              ))}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
