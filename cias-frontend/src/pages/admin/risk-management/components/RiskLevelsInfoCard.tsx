import { Info } from "lucide-react";
import { Card } from "../../../../components/ui/card";
import { Heading, Text } from "../../../../components/ui/typography";

export function RiskLevelsInfoCard() {
  return (
    <Card className="rounded-2xl border-blue-100 bg-blue-50/50 p-0">
      <div className="flex items-start gap-3 px-4 py-4">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-blue-600 shadow-sm">
          <Info size={18} />
        </div>
        <div className="min-w-0">
          <Heading as={5} className="text-slate-800">
            About Risk Levels
          </Heading>
          <Text className="mt-1 text-slate-600">
            Risk levels are used to classify and prioritize auditable units
            based on their risk scores.
          </Text>
          <Text className="mt-0.5 text-slate-600">
            These levels help in determining the audit frequency and resource
            allocation.
          </Text>
          <div className="mt-3 rounded-xl border border-blue-100 bg-white/80 px-3 py-3">
            <Text className="font-semibold text-slate-700">
              Example
            </Text>
            <Text className="mt-1 text-slate-600">
              Payroll Process: Impact Score = 5, Likelihood Score = 4, Total
              Score = 9.
            </Text>
            <Text className="mt-0.5 text-slate-600">
              Since 9 falls within the High range of 9 - 15, AGIS automatically
              assigns High Risk.
            </Text>
          </div>
          <Text className="mt-3 text-slate-600">
            Without score ranges, Low, Medium, High, and Critical are only
            labels. With ranges like 1 - 4, 5 - 8, and 9 - 15, the system can
            determine the correct classification automatically.
          </Text>
        </div>
      </div>
    </Card>
  );
}
