import { useMemo, useState } from "react";
import { CalendarDays, Download, Funnel } from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { Select2 } from "../../../../components/ui/select2";
import { Heading, Paragraph, Text } from "../../../../components/ui/typography";
import { AnnualAuditPlanStats, AuditCalendarView } from "../components";
import { annualAuditPlanYearOptions } from "../constants";
import {
  getAnnualAuditPlanRecords,
  getAuditCalendarItems,
} from "../service";
import { getAnnualAuditPlanningSummary } from "../annual-audit-planning-utils";

const defaultYear = "2025";

export function AdminAuditCalendarPage() {
  const [yearFilter, setYearFilter] = useState(defaultYear);

  const planRecords = getAnnualAuditPlanRecords();
  const allItems = getAuditCalendarItems();

  const yearRecords = useMemo(
    () => planRecords.filter((record) => record.year === yearFilter),
    [planRecords, yearFilter],
  );
  const yearItems = useMemo(
    () => allItems.filter((item) => item.year === yearFilter),
    [allItems, yearFilter],
  );
  const summary = useMemo(
    () => getAnnualAuditPlanningSummary(yearRecords),
    [yearRecords],
  );

  return (
    <AdminLayout>
      <div className="space-y-4 overflow-x-hidden">
        <div className="flex items-start justify-between gap-4 max-[960px]:flex-col">
          <div className="flex items-start gap-4">
            <div className="grid h-11 w-11 place-items-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm">
              <CalendarDays className="h-5 w-5" />
            </div>
            <div>
              <Heading as={2} className="text-slate-950">
                Audit Calendar
              </Heading>
              <Paragraph className="mt-1 max-w-2xl text-slate-600">
                View the schedule of planned audits for the selected audit year.
              </Paragraph>
            </div>
          </div>

          <div className="flex items-end gap-3 max-[760px]:w-full max-[760px]:flex-col max-[760px]:items-stretch">
            <div className="min-w-[140px]">
              <Text className="mb-1.5 text-xs font-semibold text-slate-500">
                Audit Year
              </Text>
              <Select2
                value={yearFilter}
                options={annualAuditPlanYearOptions.map((option) => ({
                  value: option,
                  label: option,
                }))}
                placeholder="Select year"
                onChange={setYearFilter}
              />
            </div>

            <Button className="h-10 rounded-xl px-4" variant="outline">
              <Funnel className="h-4 w-4" />
              Filters
            </Button>

            <Button className="h-10 rounded-xl px-4" variant="outline">
              <Download className="h-4 w-4" />
              Export
            </Button>
          </div>
        </div>

        <AnnualAuditPlanStats summary={summary} />
        <AuditCalendarView items={yearItems} initialDate={`${yearFilter}-06-01`} />
      </div>
    </AdminLayout>
  );
}

export default AdminAuditCalendarPage;
