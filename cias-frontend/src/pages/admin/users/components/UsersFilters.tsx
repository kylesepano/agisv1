import { RotateCcw, Search } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Card, CardContent } from "../../../../components/ui/card";
import { Input } from "../../../../components/ui/input";

type UsersFiltersProps = {
  search: string;
  roleFilter: string;
  departmentFilter: string;
  statusFilter: string;
  roleOptions: string[];
  departmentOptions: string[];
  statusOptions: string[];
  onSearchChange: (value: string) => void;
  onRoleChange: (value: string) => void;
  onDepartmentChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onClear: () => void;
};

type FilterSelectProps = {
  label: string;
  value: string;
  options: string[];
  onChange: (value: string) => void;
};

function FilterSelect({
  label,
  value,
  options,
  onChange,
}: FilterSelectProps) {
  return (
    <div className="space-y-2">
      <label className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
        {label}
      </label>
      <select
        className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-300"
        value={value}
        onChange={(event) => onChange(event.target.value)}
      >
        {options.map((option) => (
          <option key={option} value={option}>
            {option}
          </option>
        ))}
      </select>
    </div>
  );
}

export function UsersFilters({
  search,
  roleFilter,
  departmentFilter,
  statusFilter,
  roleOptions,
  departmentOptions,
  statusOptions,
  onSearchChange,
  onRoleChange,
  onDepartmentChange,
  onStatusChange,
  onClear,
}: UsersFiltersProps) {
  return (
    <Card className="rounded-3xl">
      <CardContent className="pt-6">
        <div className="grid gap-4 xl:grid-cols-[1.35fr_repeat(3,minmax(0,0.8fr))_auto] md:grid-cols-2">
          <div className="space-y-2">
            <label className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
              Search
            </label>
            <div className="relative">
              <Input
                className="pr-10"
                placeholder="Search by name, email, or username..."
                value={search}
                onChange={(event) => onSearchChange(event.target.value)}
              />
              <Search
                className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                size={16}
              />
            </div>
          </div>

          <FilterSelect
            label="Role"
            value={roleFilter}
            options={roleOptions}
            onChange={onRoleChange}
          />
          <FilterSelect
            label="Department"
            value={departmentFilter}
            options={departmentOptions}
            onChange={onDepartmentChange}
          />
          <FilterSelect
            label="Status"
            value={statusFilter}
            options={statusOptions}
            onChange={onStatusChange}
          />

          <div className="flex items-end md:col-span-2 xl:col-span-1">
            <Button
              className="h-10 w-full rounded-xl"
              variant="outline"
              onClick={onClear}
            >
              <RotateCcw size={14} />
              Clear Filters
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
