import { useMemo, type InputHTMLAttributes, type ReactNode } from "react";
import { CalendarDays } from "lucide-react";
import { Card } from "../../../../components/ui/card";
import { Select2 } from "../../../../components/ui/select2";
import { Text } from "../../../../components/ui/typography";
import { Badge } from "../../../../components/ui/badge";
import { auditUniverseRecords } from "../../audit-universe/audit-universe-data";
import { riskRegisterRecords } from "../risk-assessment-data";
import type { AssessmentMatrixValues } from "../risk-assessment-types";

function Field({
  label,
  required = false,
  children,
}: {
  label: string;
  required?: boolean;
  children: ReactNode;
}) {
  return (
    <label className="block space-y-1.5">
      <div className="flex items-center gap-1 text-xs font-semibold text-slate-600">
        <span>{label}</span>
        {required ? <span className="text-rose-500">*</span> : null}
      </div>
      {children}
    </label>
  );
}

function InputBase(
  props: InputHTMLAttributes<HTMLInputElement> & { icon?: ReactNode },
) {
  const { className, icon, ...rest } = props;

  return (
    <div className="relative">
      <input
        className={[
          "flex h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100",
          icon ? "pr-10" : "",
          className,
        ]
          .filter(Boolean)
          .join(" ")}
        {...rest}
      />
      {icon ? (
        <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
          {icon}
        </span>
      ) : null}
    </div>
  );
}

function DetailRow({
  label,
  value,
}: {
  label: string;
  value: ReactNode;
}) {
  return (
    <div className="grid grid-cols-[108px_1fr] gap-2.5 text-sm">
      <Text className="font-semibold text-slate-600">{label}</Text>
      <div className="text-slate-800">{value}</div>
    </div>
  );
}

function formatLastAudit(value: string) {
  const date = new Date(`${value}T00:00:00`);
  return new Intl.DateTimeFormat("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

function getTimeSinceLastAudit(value: string) {
  const from = new Date(`${value}T00:00:00`);
  const now = new Date("2025-05-20T00:00:00");
  const monthDiff =
    (now.getFullYear() - from.getFullYear()) * 12 +
    now.getMonth() -
    from.getMonth();
  const years = Math.floor(monthDiff / 12);
  const months = monthDiff % 12;

  return `${years} year${years === 1 ? "" : "s"}, ${months} month${months === 1 ? "" : "s"}`;
}

export function AssessmentMatrixForm({
  values,
  onChange,
}: {
  values: AssessmentMatrixValues;
  onChange: <K extends keyof AssessmentMatrixValues>(
    key: K,
    value: AssessmentMatrixValues[K],
  ) => void;
}) {
  const auditableUnitOptions = useMemo(
    () =>
      auditUniverseRecords
        .filter((record) => record.status === "Active")
        .map((record) => ({
          value: record.name,
          label: record.name,
        })),
    [],
  );

  const assessorOptions = useMemo(
    () =>
      Array.from(new Set(riskRegisterRecords.map((record) => record.assessor))).map(
        (assessor) => ({
          value: assessor,
          label: assessor,
        }),
      ),
    [],
  );

  const yearOptions = useMemo(
    () =>
      ["2025", "2024", "2023"].map((year) => ({
        value: year,
        label: year,
      })),
    [],
  );

  const selectedRecord =
    auditUniverseRecords.find((record) => record.name === values.auditableUnit) ??
    auditUniverseRecords[0];

  return (
    <Card className="w-full rounded-2xl p-4">
      <Text className="text-sm font-bold text-slate-900">
        1. Select Audit Unit
      </Text>

      <div className="mt-4 space-y-4">
        <Field label="Audit Unit" required>
          <Select2
            value={values.auditableUnit}
            options={auditableUnitOptions}
            placeholder="Select audit unit"
            searchPlaceholder="Search audit unit..."
            onChange={(value) => onChange("auditableUnit", value)}
          />
        </Field>

        <div className="space-y-3 border-b border-slate-100 pb-4">
          <DetailRow label="Audit Code" value={selectedRecord.code} />
          <DetailRow
            label="Category"
            value={<Badge tone="success">{selectedRecord.category}</Badge>}
          />
          <DetailRow
            label="Responsible Office"
            value={selectedRecord.department}
          />
          <DetailRow label="Business Owner" value={selectedRecord.businessOwner} />
          <DetailRow label="Technical Owner" value={selectedRecord.technicalOwner} />
          <DetailRow
            label="Date Last Audited"
            value={formatLastAudit(selectedRecord.lastAudit)}
          />
          <DetailRow
            label="Time Since Last Audit"
            value={
              <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                {getTimeSinceLastAudit(selectedRecord.lastAudit)}
              </span>
            }
          />
        </div>

        <Field label="Assessment Period" required>
          <Select2
            value={values.assessmentYear}
            options={yearOptions}
            placeholder="Select year"
            onChange={(value) => onChange("assessmentYear", value)}
          />
        </Field>

        <Field label="Assessor" required>
          <Select2
            value={values.assessor}
            options={assessorOptions}
            placeholder="Select assessor"
            searchPlaceholder="Search assessor..."
            onChange={(value) => onChange("assessor", value)}
          />
        </Field>

        <Field label="Assessment Date">
          <InputBase
            type="date"
            value={values.assessmentDate}
            icon={<CalendarDays size={16} />}
            readOnly
          />
        </Field>

        <div className="rounded-xl border border-blue-100 bg-blue-50 px-3 py-3">
          <Text className="text-xs font-medium text-blue-700">
            Assessment date is system-generated. Audit unit information is
            auto-populated from Audit Universe.
          </Text>
        </div>
      </div>
    </Card>
  );
}
