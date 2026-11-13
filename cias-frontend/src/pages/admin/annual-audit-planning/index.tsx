import { navigateTo } from "../../../routes";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { Button } from "../../../components/ui/button";
import { Card } from "../../../components/ui/card";
import { Heading, Paragraph, Text } from "../../../components/ui/typography";
import { AnnualAuditPlanStats } from "./components";
import { getAnnualAuditPlanningSummaryMetrics } from "./service";

export function AdminAnnualAuditPlanningPage() {
  const summary = getAnnualAuditPlanningSummaryMetrics();

  return (
    <AdminLayout>
      <div className="space-y-4 overflow-x-hidden">
        <div>
          <Heading as={2} className="text-slate-950">
            Annual Audit Planning
          </Heading>
          <Paragraph className="mt-1 text-slate-600">
            Organize the yearly plan, track approval status, and monitor audit
            scheduling across the calendar.
          </Paragraph>
        </div>

        <AnnualAuditPlanStats summary={summary} />

        <div className="grid gap-3 md:grid-cols-2">
          <Card className="rounded-2xl p-4">
            <Heading as={4} className="text-slate-900">
              Annual Audit Plan
            </Heading>
            <Text className="mt-1 text-slate-600">
              Manage plan entries, target quarters, audit ownership, and
              approval status.
            </Text>
            <Button
              className="mt-4 h-9 rounded-lg px-4"
              onClick={() => navigateTo("/admin/annual-audit-plan")}
            >
              Open Annual Audit Plan
            </Button>
          </Card>

          <Card className="rounded-2xl p-4">
            <Heading as={4} className="text-slate-900">
              Audit Calendar
            </Heading>
            <Text className="mt-1 text-slate-600">
              Review scheduled audit activities grouped by quarter and timeline.
            </Text>
            <Button
              className="mt-4 h-9 rounded-lg px-4"
              variant="outline"
              onClick={() => navigateTo("/admin/audit-calendar")}
            >
              Open Audit Calendar
            </Button>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}

export { AdminAnnualAuditPlanPage } from "./annual-audit-plan";
export { AdminAuditCalendarPage } from "./audit-calendar";
