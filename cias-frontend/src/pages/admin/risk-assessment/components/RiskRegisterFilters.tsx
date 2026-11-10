import { Funnel, Search } from "lucide-react";
import { Card } from "../../../../components/ui/card";
import { Select2 } from "../../../../components/ui/select2";
import { Input } from "../../../../components/ui/input";
import { Button } from "../../../../components/ui/button";

type RiskRegisterFiltersProps = {
  search: string;
  categoryFilter: string;
  officeFilter: string;
  ratingFilter: string;
  assessorFilter: string;
  categoryOptions: { value: string; label: string }[];
  officeOptions: { value: string; label: string }[];
  ratingOptions: { value: string; label: string }[];
  assessorOptions: { value: string; label: string }[];
  onSearchChange: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onOfficeChange: (value: string) => void;
  onRatingChange: (value: string) => void;
  onAssessorChange: (value: string) => void;
};

export function RiskRegisterFilters({
  search,
  categoryFilter,
  officeFilter,
  ratingFilter,
  assessorFilter,
  categoryOptions,
  officeOptions,
  ratingOptions,
  assessorOptions,
  onSearchChange,
  onCategoryChange,
  onOfficeChange,
  onRatingChange,
  onAssessorChange,
}: RiskRegisterFiltersProps) {
  return (
    <Card className="relative z-20 rounded-[22px] border border-slate-200 bg-white p-0 shadow-sm">
      <div className="grid gap-3 px-4 py-4 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
        <div className="relative">
          <Input
            className="h-10 rounded-xl border-slate-200 bg-white pl-10 pr-3"
            placeholder="Search audit unit, code, office, category..."
            value={search}
            onChange={(event) => onSearchChange(event.target.value)}
          />
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        </div>

        <Select2
          value={categoryFilter}
          options={categoryOptions}
          placeholder="All Categories"
          onChange={onCategoryChange}
        />
        <Select2
          value={officeFilter}
          options={officeOptions}
          placeholder="All Offices"
          onChange={onOfficeChange}
        />
        <Select2
          value={ratingFilter}
          options={ratingOptions}
          placeholder="All Risk Ratings"
          onChange={onRatingChange}
        />
        <Select2
          value={assessorFilter}
          options={assessorOptions}
          placeholder="All Assessors"
          onChange={onAssessorChange}
        />

        <Button
          className="h-10 rounded-xl px-4"
          type="button"
          variant="outline"
        >
          <Funnel className="h-4 w-4" />
          Filters
        </Button>
      </div>
    </Card>
  );
}
