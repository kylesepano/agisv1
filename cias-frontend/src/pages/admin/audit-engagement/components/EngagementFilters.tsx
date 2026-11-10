import { RotateCcw, Search } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Input } from "../../../../components/ui/input";
import { Select2 } from "../../../../components/ui/select2";

type Option = { value: string; label: string };

export function EngagementFilters({
  search,
  statusFilter,
  auditTypeFilter,
  officeFilter,
  auditorFilter,
  statusOptions,
  auditTypeOptions,
  officeOptions,
  auditorOptions,
  onSearchChange,
  onStatusChange,
  onAuditTypeChange,
  onOfficeChange,
  onAuditorChange,
  onResetFilters,
}: {
  search: string;
  statusFilter: string;
  auditTypeFilter: string;
  officeFilter: string;
  auditorFilter: string;
  statusOptions: Option[];
  auditTypeOptions: Option[];
  officeOptions: Option[];
  auditorOptions: Option[];
  onSearchChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onAuditTypeChange: (value: string) => void;
  onOfficeChange: (value: string) => void;
  onAuditorChange: (value: string) => void;
  onResetFilters?: () => void;
}) {
  return (
    <div className="relative z-20 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
      <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-[150px_160px_160px_150px_1fr_auto]">
        {/* Status Filter */}
        <Select2
          value={statusFilter}
          options={statusOptions}
          placeholder="All Statuses"
          onChange={onStatusChange}
        />

        {/* Audit Type Filter (standardized from category) */}
        <Select2
          value={auditTypeFilter}
          options={auditTypeOptions}
          placeholder="All Audit Types"
          onChange={onAuditTypeChange}
        />

        {/* Office Filter */}
        <Select2
          value={officeFilter}
          options={officeOptions}
          placeholder="All Offices"
          onChange={onOfficeChange}
        />

        {/* Lead Auditor Filter */}
        <Select2
          value={auditorFilter}
          options={auditorOptions}
          placeholder="All Lead Auditors"
          onChange={onAuditorChange}
        />

        {/* Search by Area, Code, Office */}
        <div className="relative">
          <Input
            className="h-10 rounded-lg pl-9 pr-3 text-xs placeholder:text-slate-400 border-slate-200 focus:border-blue-500"
            placeholder="Search engagement no., audit area, office..."
            value={search}
            onChange={(event) => onSearchChange(event.target.value)}
          />
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        </div>

        {/* Reset Filters */}
        {onResetFilters && (
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
        )}
      </div>
    </div>
  );
}
