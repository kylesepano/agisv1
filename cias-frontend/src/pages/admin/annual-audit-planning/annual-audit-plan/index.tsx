import { useMemo, useState } from "react";
import { ClipboardList, Download, Plus } from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { Select2 } from "../../../../components/ui/select2";
import { Heading, Paragraph } from "../../../../components/ui/typography";
import {
  AnnualAuditPlanFilters,
  AnnualAuditPlanStats,
  AnnualAuditPlanTable,
} from "../components";
import {
  annualAuditPlanCategoryOptions,
  annualAuditPlanStatusOptions,
  annualAuditPlanYearOptions,
} from "../constants";
import { getAnnualAuditPlanRecords } from "../service";
import { getAnnualAuditPlanningSummary } from "../annual-audit-planning-utils";
import type { AnnualAuditPlanRecord } from "../annual-audit-planning-types";

const defaultStatus = "All Statuses";
const defaultCategory = "All Categories";
const defaultOffice = "All Offices";
const defaultYear = "2025";

export function AdminAnnualAuditPlanPage() {
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState(defaultStatus);
  const [categoryFilter, setCategoryFilter] = useState(defaultCategory);
  const [officeFilter, setOfficeFilter] = useState(defaultOffice);
  const [yearFilter, setYearFilter] = useState(defaultYear);
  const [selectedPlanId, setSelectedPlanId] = useState<string | null>(null);

  const records = getAnnualAuditPlanRecords();

  const yearRecords = useMemo(
    () => records.filter((record) => record.year === yearFilter),
    [records, yearFilter],
  );

  const filteredRecords = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return yearRecords.filter((record) => {
      const matchesSearch =
        normalizedSearch.length === 0 ||
        [record.code, record.subject, record.office, record.category].some((value) =>
          value.toLowerCase().includes(normalizedSearch),
        );

      const matchesStatus =
        statusFilter === defaultStatus || record.status === statusFilter;
      const matchesCategory =
        categoryFilter === defaultCategory || record.category === categoryFilter;
      const matchesOffice =
        officeFilter === defaultOffice || record.office === officeFilter;
      const matchesYear = record.year === yearFilter;

      return (
        matchesSearch &&
        matchesStatus &&
        matchesCategory &&
        matchesOffice &&
        matchesYear
      );
    });
  }, [categoryFilter, officeFilter, search, statusFilter, yearFilter, yearRecords]);

  const summary = useMemo(
    () => getAnnualAuditPlanningSummary(yearRecords),
    [yearRecords],
  );

  const statusOptions = annualAuditPlanStatusOptions.map((option) => ({
    value: option,
    label: option === "All Statuses" ? "All Status" : option,
  }));
  const categoryOptions = annualAuditPlanCategoryOptions.map((option) => ({
    value: option,
    label: option,
  }));
  const officeOptions = [
    { value: defaultOffice, label: defaultOffice },
    ...Array.from(new Set(records.map((record) => record.office))).map((option) => ({
      value: option,
      label: option,
    })),
  ];
  const yearOptions = annualAuditPlanYearOptions.map((option) => ({
    value: option,
    label: option,
  }));

  const handleResetFilters = () => {
    setSearch("");
    setStatusFilter(defaultStatus);
    setCategoryFilter(defaultCategory);
    setOfficeFilter(defaultOffice);
  };

  const handleSelectPlan = (plan: AnnualAuditPlanRecord) => {
    setSelectedPlanId(plan.code);
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8 overflow-x-hidden">
        {/* Page Header matching SCR-210 & SCR-201/202/203 */}
        <div className="flex items-start justify-between gap-3 max-[960px]:flex-col border-b border-slate-200 pb-2.5">
          <div className="flex items-start gap-2.5">
            <div className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600 border border-blue-100">
              <ClipboardList className="h-4.5 w-4.5" />
            </div>
            <div>
              <div className="flex items-center gap-1.5">
                <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                  SCR-101
                </span>
                <Heading as={2} className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                  Annual Audit Plan (IAP)
                </Heading>
              </div>
              <Paragraph className="mt-0.5 text-[11px] text-slate-500">
                Manage and govern planned internal audit engagements for the approved audit calendar year.
              </Paragraph>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2 max-[760px]:w-full">
            <div className="w-[120px]">
              <Select2
                value={yearFilter}
                options={yearOptions}
                placeholder="Audit Year"
                onChange={setYearFilter}
              />
            </div>

            <Button className="h-10 rounded-lg bg-blue-600 hover:bg-blue-700 px-3.5 text-xs font-semibold text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer">
              <Plus className="h-4 w-4" />
              <span>Add Plan Item</span>
            </Button>

            <Button
              className="h-10 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-3.5 text-xs font-semibold text-slate-700 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
              variant="outline"
              type="button"
            >
              <Download className="h-4 w-4" />
              <span>Export</span>
            </Button>
          </div>
        </div>

        {/* 5 Tinted Stat Cards */}
        <AnnualAuditPlanStats summary={summary} />

        {/* Single-Row Filter Toolbar with Reset */}
        <AnnualAuditPlanFilters
          search={search}
          statusFilter={statusFilter}
          categoryFilter={categoryFilter}
          officeFilter={officeFilter}
          statusOptions={statusOptions}
          categoryOptions={categoryOptions}
          officeOptions={officeOptions}
          onSearchChange={setSearch}
          onStatusChange={setStatusFilter}
          onCategoryChange={setCategoryFilter}
          onOfficeChange={setOfficeFilter}
          onResetFilters={handleResetFilters}
        />

        {/* Master Table + Right-Rail Detail Panel */}
        <AnnualAuditPlanTable
          records={filteredRecords}
          selectedPlanId={selectedPlanId}
          onSelectPlan={handleSelectPlan}
        />
      </div>
    </AdminLayout>
  );
}

export default AdminAnnualAuditPlanPage;
