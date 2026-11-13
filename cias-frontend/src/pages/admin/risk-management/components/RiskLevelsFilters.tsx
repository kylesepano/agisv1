import { Search } from "lucide-react";
import { Combobox } from "../../../../components/ui/combobox";
import { Input } from "../../../../components/ui/input";

type RiskLevelsFiltersProps = {
  search: string;
  statusFilter: string;
  onSearchChange: (value: string) => void;
  onStatusChange: (value: string) => void;
};

const statusOptions = [
  {
    value: "All Statuses",
    label: "All Statuses",
  },
  {
    value: "Active",
    label: "Active",
  },
  {
    value: "Inactive",
    label: "Inactive",
  },
];

export function RiskLevelsFilters({
  search,
  statusFilter,
  onSearchChange,
  onStatusChange,
}: RiskLevelsFiltersProps) {
  return (
    <div className="border-b border-slate-100 px-4 pb-4 pt-4">
      <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px]">
        <div className="relative min-w-0">
          <Input
            className="h-9 w-full pr-10"
            placeholder="Search risk level by name or code..."
            value={search}
            onChange={(event) => onSearchChange(event.target.value)}
          />

          <Search
            className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
            size={16}
          />
        </div>

        <Combobox
          value={statusFilter}
          options={statusOptions}
          placeholder="All Statuses"
          emptyMessage="No status found."
          onChange={onStatusChange}
        />
      </div>
    </div>
  );
}
