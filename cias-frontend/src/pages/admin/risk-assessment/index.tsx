import { navigateTo } from "../../../routes";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { Button } from "../../../components/ui/button";
import { Card } from "../../../components/ui/card";
import { Heading, Paragraph, Text } from "../../../components/ui/typography";
import { getAssessmentSummaryMetrics } from "./service";

export function AdminRiskAssessmentPage() {
  const summaryMetrics = getAssessmentSummaryMetrics();

  return (
    <AdminLayout>
      <div className="w-full space-y-3 overflow-x-hidden">
        <div>
          <Heading as={2} className="text-slate-950">
            Risk Assessment
          </Heading>
          <Paragraph className="mt-0.5 text-slate-600">
            Score auditable units, monitor assessment results, and review the
            current risk register.
          </Paragraph>
        </div>

        <div className="grid gap-3 sm:grid-cols-3">
          {summaryMetrics.map((item) => (
            <Card key={item.label} className="rounded-2xl p-4">
              <Text className="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">
                {item.label}
              </Text>
              <Text className="mt-1 text-2xl font-bold text-slate-950">
                {item.value}
              </Text>
              <Text className="mt-1 text-slate-600">{item.subtitle}</Text>
            </Card>
          ))}
        </div>

        <div className="grid gap-3 md:grid-cols-2">
          <Card className="rounded-2xl p-4">
            <Heading as={4} className="text-slate-900">
              Assessment Matrix
            </Heading>
            <Text className="mt-1 text-slate-600">
              Evaluate the selected audit unit across key risk criteria and
              generate its overall rating.
            </Text>
            <div className="mt-3 space-y-1 text-sm text-slate-600">
              <Text>Uses 7 criteria with 1 to 5 scoring.</Text>
              <Text>Produces Low, Medium, High, or Critical risk ratings.</Text>
            </div>
            <Button
              className="mt-4 h-9 rounded-lg px-4"
              onClick={() => navigateTo("/admin/risk-assessment/assessment-matrix")}
            >
              Open Assessment Matrix
            </Button>
          </Card>

          <Card className="rounded-2xl p-4">
            <Heading as={4} className="text-slate-900">
              Risk Register
            </Heading>
            <Text className="mt-1 text-slate-600">
              Review completed assessments, calculated scores, ratings, and
              current progress status.
            </Text>
            <div className="mt-3 space-y-1 text-sm text-slate-600">
              <Text>Filter by status, assessor, and auditable unit.</Text>
              <Text>Track pending, in-progress, and completed assessments.</Text>
            </div>
            <Button
              className="mt-4 h-9 rounded-lg px-4"
              variant="outline"
              onClick={() => navigateTo("/admin/risk-assessment/risk-register")}
            >
              Open Risk Register
            </Button>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}

export { AdminAssessmentMatrixPage } from "./assessment-matrix";
export { AdminRiskRegisterPage } from "./risk-register";
