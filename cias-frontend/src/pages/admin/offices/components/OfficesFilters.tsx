import { RotateCcw, Search } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Input } from "../../../../components/ui/input";
import { Select2 } from "../../../../components/ui/select2";
import { officeCategories } from "../offices-data";

type OfficesFiltersProps = {
  search: string;
  categoryFilter: string;
  statusFilter: string;
  onSearchChange: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onResetFilters: () => void;
};

export function OfficesFilters({
  search,
  categoryFilter,
  statusFilter,
  onSearchChange,
  onCategoryChange,
  onStatusChange,
  onResetFilters,
}: OfficesFiltersProps) {
  const categoryOptions = [
    { value: "All Categories", label: "All Categories" },
    ...officeCategories.map((cat) => ({
      value: cat,
      label: cat,
    })),
  ];

  const statusOptions = [
    { value: "All Statuses", label: "All Statuses" },
    { value: "Active", label: "Active Only" },
    { value: "Inactive", label: "Inactive Only" },
  ];

  return (
    <div className="relative z-20 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
      <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-[160px_180px_1fr_auto]">
        {/* Status Filter */}
        <Select2
          value={statusFilter}
          options={statusOptions}
          placeholder="All Statuses"
          onChange={onStatusChange}
        />

        {/* Category Filter */}
        <Select2
          value={categoryFilter}
          options={categoryOptions}
          placeholder="All Categories"
          onChange={onCategoryChange}
        />

        {/* Search by Code, Name, Acronym */}
        <div className="relative">
          <Input
            className="h-10 rounded-lg pl-9 pr-3 text-xs placeholder:text-slate-400 border-slate-200 focus:border-blue-500"
            placeholder="Search office ID, acronym, office name, or head..."
            value={search}
            onChange={(event) => onSearchChange(event.target.value)}
          />
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        </div>

        {/* Reset Filters */}
        <Button
          className="h-10 rounded-lg px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50 border-slate-200 flex items-center gap-1.5"
          type="button"
          variant="outline"
          onClick={onResetFilters}
          title="Reset all filters"
        >
          <RotateCcw className="h-4 w-4" />
          <span className="hidden sm:inline">Reset</span>
        </Button>
      </div>
    </div>
  );
}
