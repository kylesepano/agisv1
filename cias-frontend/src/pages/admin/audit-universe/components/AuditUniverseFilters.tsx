import { Search, X } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Card, CardContent } from "../../../../components/ui/card";
import { Combobox } from "../../../../components/ui/combobox";
import { Input } from "../../../../components/ui/input";
import { Caption, Text } from "../../../../components/ui/typography";
import { categoryRecords } from "../../../admin/categories/categories-data";

type AuditUniverseFiltersProps = {
  search: string;
  categoryFilter: string;
  departmentFilter: string;
  riskFilter: string;
  statusFilter: string;
  totalRecords: number;
  highRiskCount: number;
  mediumRiskCount: number;
  lowRiskCount: number;
  onSearchChange: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onDepartmentChange: (value: string) => void;
  onRiskChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onClear: () => void;
};

export function AuditUniverseFilters({
  search,
  categoryFilter,
  departmentFilter,
  riskFilter,
  statusFilter,
  totalRecords,
  highRiskCount,
  mediumRiskCount,
  lowRiskCount,
  onSearchChange,
  onCategoryChange,
  onDepartmentChange,
  onRiskChange,
  onStatusChange,
  onClear,
}: AuditUniverseFiltersProps) {
  const categoryOptions = [
    { value: "All Categories", label: "All Categories" },
    ...categoryRecords
      .filter((category) => category.status === "Active")
      .map((category) => ({
        value: category.name,
        label: category.name,
      })),
  ];

  const filters = [
    {
      label: "Category",
      value: categoryFilter,
      onChange: onCategoryChange,
      options: categoryOptions,
    },
    {
      label: "Responsible Office",
      value: departmentFilter,
      onChange: onDepartmentChange,
      options: [
        { value: "All Offices", label: "All Offices" },
        { value: "HRMO", label: "HRMO" },
        { value: "CMISID", label: "CMISID" },
        { value: "CGSO", label: "CGSO" },
        { value: "Accounting Office", label: "Accounting Office" },
        { value: "Budget Office", label: "Budget Office" },
        { value: "Treasurer's Office", label: "Treasurer's Office" },
      ],
    },
    {
      label: "Risk Level",
      value: riskFilter,
      onChange: onRiskChange,
      options: [
        { value: "All Risk Levels", label: "All Risk Levels" },
        { value: "High", label: "High" },
        { value: "Medium", label: "Medium" },
        { value: "Low", label: "Low" },
      ],
    },
    {
      label: "Status",
      value: statusFilter,
      onChange: onStatusChange,
      options: [
        { value: "All Statuses", label: "All Statuses" },
        { value: "Active", label: "Active" },
      ],
    },
  ];

  return (
    <Card className="relative z-20 w-full rounded-2xl p-0">
      <CardContent className="space-y-3 px-4 pb-4 pt-4">
        <div className="grid gap-2.5 md:grid-cols-2 xl:grid-cols-[minmax(0,1.25fr)_repeat(4,minmax(0,0.9fr))]">
          <div className="min-w-0 space-y-1.5 md:col-span-2 xl:col-span-1">
            <Caption className="text-slate-500">Search</Caption>
            <div className="relative min-w-0">
              <Input
                className="h-9 w-full min-w-0 pr-10"
                placeholder="Search by code or name..."
                value={search}
                onChange={(event) => onSearchChange(event.target.value)}
              />
              <Search
                className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                size={16}
              />
            </div>
          </div>

          {filters.map((filter) => (
            <div key={filter.label} className="min-w-0 space-y-1.5">
              <Caption className="text-slate-500">{filter.label}</Caption>
              <Combobox
                value={filter.value}
                options={filter.options}
                placeholder={filter.label}
                emptyMessage={`No ${filter.label.toLowerCase()} found.`}
                onChange={filter.onChange}
              />
            </div>
          ))}

          <div className="flex min-w-0 items-end md:col-span-2 xl:col-span-1">
            <Button
              className="h-9 w-full rounded-lg"
              variant="outline"
              onClick={onClear}
            >
              <X size={14} />
              Clear Filters
            </Button>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 max-[640px]:flex-col max-[640px]:items-start">
          <Text className="font-semibold text-slate-700">
            Total Records: {totalRecords}
          </Text>
          <Text className="font-semibold text-rose-600">
            High Risk: {highRiskCount}
          </Text>
          <Text className="font-semibold text-amber-600">
            Medium Risk: {mediumRiskCount}
          </Text>
          <Text className="font-semibold text-emerald-600">
            Low Risk: {lowRiskCount}
          </Text>
        </div>
      </CardContent>
    </Card>
  );
}
