import { useMemo } from "react";
import { RotateCcw, Search } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Input } from "../../../../components/ui/input";
import { Select2 } from "../../../../components/ui/select2";
import type { OfficeRecord } from "../../offices/offices-types";
import type { AuditAreaRecord } from "../../audit-areas/audit-areas-types";

type AuditFocusesFiltersProps = {
  search: string;
  officeFilter: string;
  areaFilter: string;
  statusFilter: string;
  offices: OfficeRecord[];
  auditAreas: AuditAreaRecord[];
  onSearchChange: (value: string) => void;
  onOfficeChange: (value: string) => void;
  onAreaChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onResetFilters: () => void;
};

export function AuditFocusesFilters({
  search,
  officeFilter,
  areaFilter,
  statusFilter,
  offices,
  auditAreas,
  onSearchChange,
  onOfficeChange,
  onAreaChange,
  onStatusChange,
  onResetFilters,
}: AuditFocusesFiltersProps) {
  const statusOptions = [
    { value: "All Statuses", label: "All Statuses" },
    { value: "Active", label: "Active Only" },
    { value: "Inactive", label: "Inactive Only" },
  ];

  const officeOptions = [
    { value: "All Offices", label: "All Auditee Offices" },
    ...offices.map((o) => {
      const name = o.officeName || o.name || "";
      const acronym = o.officeAcronym || o.shortName || "";
      return {
        value: o.officeId || o.code || name,
        label: acronym ? `${name} (${acronym})` : name,
      };
    }),
  ];

  // Cascading: filter areas by selected office
  const filteredAreas = useMemo(() => {
    if (officeFilter === "All Offices") {
      return auditAreas;
    }
    return auditAreas.filter(
      (a) =>
        a.officeId === officeFilter ||
        a.officeName === officeFilter,
    );
  }, [auditAreas, officeFilter]);

  const areaOptions = [
    { value: "All Areas", label: "All Audit Areas" },
    ...filteredAreas.map((a) => {
      const id = a.auditAreaId || a.code || a.id || "";
      const name = a.auditAreaName || a.name || "";
      return {
        value: id,
        label: `${id} — ${name}`,
      };
    }),
  ];

  return (
    <div className="relative z-20 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
      <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-[140px_180px_200px_1fr_auto]">
        {/* Status Filter */}
        <Select2
          value={statusFilter}
          options={statusOptions}
          placeholder="All Statuses"
          onChange={onStatusChange}
        />

        {/* Parent Office Filter */}
        <Select2
          value={officeFilter}
          options={officeOptions}
          placeholder="All Offices"
          onChange={onOfficeChange}
        />

        {/* Audit Area Filter (cascading) */}
        <Select2
          value={areaFilter}
          options={areaOptions}
          placeholder="All Audit Areas"
          onChange={onAreaChange}
        />

        {/* Search Input */}
        <div className="relative">
          <Input
            className="h-10 rounded-lg pl-9 pr-3 text-xs placeholder:text-slate-400 border-slate-200 focus:border-blue-500"
            placeholder="Search focus ID, title, parent area, or scope..."
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
