import {
  useMemo,
  useState,
  type InputHTMLAttributes,
  type ReactNode,
} from "react";
import {
  AlertTriangle,
  Building2,
  CalendarDays,
  CheckCircle2,
  Eye,
  FolderKanban,
  Hash,
  Info,
  Layers3,
  Save,
} from "lucide-react";

import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Breadcrumb } from "../../../../components/ui/breadcrumb";
import { Button } from "../../../../components/ui/button";
import { Card } from "../../../../components/ui/card";
import { Badge } from "../../../../components/ui/badge";
import { Select2 } from "../../../../components/ui/select2";
import { Heading, Paragraph, Text } from "../../../../components/ui/typography";
import { navigateTo } from "../../../../routes";
import { cn } from "../../../../utils/helpers";
import { categoryRecords } from "../../categories/categories-data";
import { localStore } from "../../../../lib/localStore";
import { riskLevelRecords } from "../../risk-management/risk-management-data";
import type { AuditUniverseCategory, RiskLevel } from "../audit-universe-types";

type CategoryOption = AuditUniverseCategory;
type RiskOption = RiskLevel;
type StatusOption = "Active";

type FormState = {
  code: string;
  name: string;
  description: string;
  category: CategoryOption;
  responsibleOffice: string;
  riskLevel: RiskOption;
  lastAuditDate: string;
  nextAuditDate: string;
  status: StatusOption;
};

const riskOptionSet = new Set<RiskOption>(["High", "Medium", "Low"]);
const statusOptions: StatusOption[] = ["Active"];

const initialFormState: FormState = {
  code: "AU-2025-009",
  name: "Payroll Process",
  description:
    "Payroll generation, approval, and disbursement process for all LGU personnel.",
  category: "Process",
  responsibleOffice: "Human Resource Management Office",
  riskLevel: "High",
  lastAuditDate: "2024-06-15",
  nextAuditDate: "2027-06-15",
  status: "Active",
};

function formatDate(value: string) {
  if (!value) {
    return "Not set";
  }

  const date = new Date(`${value}T00:00:00`);
  return new Intl.DateTimeFormat("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

function InputShell({
  label,
  hint,
  required = false,
  children,
}: {
  label: string;
  hint?: string;
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
      {hint ? <Text className="text-xs text-slate-500">{hint}</Text> : null}
    </label>
  );
}

function NativeInput(
  props: InputHTMLAttributes<HTMLInputElement> & { icon?: ReactNode },
) {
  const { className, icon, ...rest } = props;

  return (
    <div className="relative">
      <input
        className={cn(
          "flex h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100",
          icon ? "pr-10" : "",
          className,
        )}
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

function SectionCard({
  number,
  title,
  icon,
  iconClassName,
  children,
  className,
}: {
  number: string;
  title: string;
  icon: ReactNode;
  iconClassName: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <Card
      className={cn("relative overflow-visible rounded-2xl p-4", className)}
    >
      <div className="mb-4 flex items-center gap-2.5 border-b border-slate-100 pb-3">
        <div
          className={cn(
            "flex h-8 w-8 items-center justify-center rounded-full",
            iconClassName,
          )}
        >
          {icon}
        </div>
        <div className="min-w-0">
          <Text className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-700">
            {number}. {title}
          </Text>
        </div>
      </div>
      {children}
    </Card>
  );
}

function SummaryRow({
  icon,
  iconClassName,
  label,
  value,
  valueClassName,
}: {
  icon: ReactNode;
  iconClassName: string;
  label: string;
  value: ReactNode;
  valueClassName?: string;
}) {
  return (
    <div className="flex items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0">
      <div
        className={cn(
          "mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full",
          iconClassName,
        )}
      >
        {icon}
      </div>
      <div className="min-w-0">
        <Text className="text-xs font-semibold text-slate-500">{label}</Text>
        <div
          className={cn(
            "mt-0.5 text-sm leading-5 font-medium text-slate-800",
            valueClassName,
          )}
        >
          {value}
        </div>
      </div>
    </div>
  );
}

export function CreateAuditableUnitPage() {
  const [form, setForm] = useState<FormState>(initialFormState);

  const categoryOptions = useMemo(
    () =>
      categoryRecords
        .filter((record) => record.status === "Active")
        .map((record) => ({
          value: record.name as CategoryOption,
          label: record.name,
        })),
    [],
  );

  const officeOptions = useMemo(
    () =>
      localStore
        .getOffices()
        .filter((record) => record.status === "Active")
        .map((record) => {
          const name = record.officeName || record.name || "";
          const acronym = record.officeAcronym || record.shortName || "";
          return {
            value: name,
            label: acronym ? `${acronym} - ${name}` : name,
          };
        }),
    [],
  );

  const riskOptions = useMemo(
    () =>
      riskLevelRecords
        .filter(
          (record) =>
            record.status === "Active" &&
            riskOptionSet.has(record.name as RiskOption),
        )
        .map((record) => ({
          value: record.name as RiskOption,
          label: `${record.name} (${record.scoreRange})`,
        })),
    [],
  );

  const statusSelectOptions = useMemo(
    () => statusOptions.map((option) => ({ value: option, label: option })),
    [],
  );

  const summaryItems = useMemo(
    () => [
      {
        label: "Code",
        value: form.code || "Not set",
        icon: <Hash size={18} className="text-blue-600" />,
        iconClassName: "bg-blue-50",
      },
      {
        label: "Auditable Unit Name",
        value: form.name || "Not set",
        icon: <Building2 size={18} className="text-emerald-600" />,
        iconClassName: "bg-emerald-50",
      },
      {
        label: "Category",
        value: form.category,
        icon: <Layers3 size={18} className="text-violet-600" />,
        iconClassName: "bg-violet-50",
      },
      {
        label: "Responsible Office",
        value: form.responsibleOffice,
        icon: <FolderKanban size={18} className="text-amber-600" />,
        iconClassName: "bg-amber-50",
      },
      {
        label: "Risk Level",
        value: form.riskLevel,
        icon: <AlertTriangle size={18} className="text-rose-500" />,
        iconClassName: "bg-rose-50",
        valueClassName:
          form.riskLevel === "High"
            ? "text-rose-600"
            : form.riskLevel === "Medium"
              ? "text-amber-600"
              : "text-emerald-600",
      },
      {
        label: "Last Audit Date",
        value: formatDate(form.lastAuditDate),
        icon: <CalendarDays size={18} className="text-blue-600" />,
        iconClassName: "bg-blue-50",
      },
      {
        label: "Next Audit Date",
        value: formatDate(form.nextAuditDate),
        icon: <CalendarDays size={18} className="text-blue-600" />,
        iconClassName: "bg-blue-50",
      },
      {
        label: "Status",
        value: <Badge tone="success">{form.status}</Badge>,
        icon: <CheckCircle2 size={18} className="text-emerald-600" />,
        iconClassName: "bg-emerald-50",
      },
    ],
    [form],
  );

  const updateForm = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((current) => ({ ...current, [key]: value }));

  return (
    <AdminLayout>
      <form
        className="space-y-4"
        onSubmit={(event) => {
          event.preventDefault();
        }}
      >
        <div className="space-y-1.5">
          <Breadcrumb
            items={[
              { label: "Dashboard", href: "/admin/dashboard" },
              { label: "Audit Universe", href: "/admin/audit-universe" },
              { label: "Add Auditable Unit" },
            ]}
          />

          <div>
            <Heading as={2} className="text-slate-950">
              Add Auditable Unit
            </Heading>
            <Paragraph className="mt-0.5 text-slate-600">
              Create a new auditable entity within the LGU.
            </Paragraph>
          </div>
        </div>

        <div className="grid gap-3 overflow-visible xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_320px]">
          <div className="space-y-3 overflow-visible xl:col-span-2">
            <div className="grid gap-3 overflow-visible lg:grid-cols-2">
              <SectionCard
                number="1"
                title="Basic Information"
                icon={<Info size={18} className="text-blue-600" />}
                iconClassName="bg-blue-50"
                className="z-30"
              >
                <div className="space-y-3">
                  <InputShell
                    label="Code"
                    hint="Unique code for this auditable unit."
                    required
                  >
                    <NativeInput
                      value={form.code}
                      onChange={(event) =>
                        updateForm("code", event.target.value)
                      }
                    />
                  </InputShell>

                  <InputShell
                    label="Auditable Unit Name"
                    hint="Name of the auditable unit."
                    required
                  >
                    <NativeInput
                      value={form.name}
                      onChange={(event) =>
                        updateForm("name", event.target.value)
                      }
                    />
                  </InputShell>

                  <InputShell
                    label="Description"
                    hint="Provide a brief description of the auditable unit."
                  >
                    <textarea
                      className="min-h-[92px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100"
                      value={form.description}
                      onChange={(event) =>
                        updateForm("description", event.target.value)
                      }
                    />
                  </InputShell>
                </div>
              </SectionCard>

              <SectionCard
                number="2"
                title="Classification"
                icon={<Building2 size={18} className="text-emerald-600" />}
                iconClassName="bg-emerald-50"
                className="z-40"
              >
                <div className="space-y-3">
                  <InputShell
                    label="Category"
                    hint="Select the category that best describes this unit."
                    required
                  >
                    <Select2
                      value={form.category}
                      options={categoryOptions}
                      placeholder="Select category"
                      searchPlaceholder="Search category..."
                      emptyMessage="No categories found."
                      onChange={(value) =>
                        updateForm("category", value as CategoryOption)
                      }
                    />
                  </InputShell>

                  <InputShell
                    label="Responsible Office"
                    hint="Office or department responsible for this unit."
                    required
                  >
                    <Select2
                      value={form.responsibleOffice}
                      options={officeOptions}
                      placeholder="Select office"
                      searchPlaceholder="Search office..."
                      emptyMessage="No offices found."
                      onChange={(value) =>
                        updateForm("responsibleOffice", value)
                      }
                    />
                  </InputShell>
                </div>
              </SectionCard>

              <SectionCard
                number="3"
                title="Risk Information"
                icon={<AlertTriangle size={18} className="text-rose-500" />}
                iconClassName="bg-rose-50"
                className="z-20"
              >
                <InputShell
                  label="Risk Level"
                  hint="Select the inherent risk level of this auditable unit."
                  required
                >
                  <Select2
                    value={form.riskLevel}
                    options={riskOptions}
                    placeholder="Select risk level"
                    searchPlaceholder="Search risk level..."
                    emptyMessage="No risk levels found."
                    onChange={(value) =>
                      updateForm("riskLevel", value as RiskOption)
                    }
                  />
                </InputShell>
              </SectionCard>

              <SectionCard
                number="4"
                title="Audit Information"
                icon={<CalendarDays size={18} className="text-violet-600" />}
                iconClassName="bg-violet-50"
                className="z-10"
              >
                <div className="grid gap-3 sm:grid-cols-2">
                  <InputShell
                    label="Last Audit Date"
                    hint="Date when this unit was last audited."
                  >
                    <NativeInput
                      type="date"
                      value={form.lastAuditDate}
                      icon={<CalendarDays size={16} />}
                      onChange={(event) =>
                        updateForm("lastAuditDate", event.target.value)
                      }
                    />
                  </InputShell>

                  <InputShell
                    label="Next Audit Date"
                    hint="Scheduled date for next audit."
                  >
                    <NativeInput
                      type="date"
                      value={form.nextAuditDate}
                      icon={<CalendarDays size={16} />}
                      onChange={(event) =>
                        updateForm("nextAuditDate", event.target.value)
                      }
                    />
                  </InputShell>
                </div>
              </SectionCard>
            </div>

            <SectionCard
              number="5"
              title="Status"
              icon={<CheckCircle2 size={18} className="text-amber-500" />}
              iconClassName="bg-amber-50"
              className="z-0"
            >
              <div className="max-w-md">
                <InputShell
                  label="Status"
                  hint="Set the current status of this auditable unit."
                  required
                >
                  <Select2
                    value={form.status}
                    options={statusSelectOptions}
                    placeholder="Select status"
                    searchPlaceholder="Search status..."
                    emptyMessage="No status found."
                    onChange={(value) =>
                      updateForm("status", value as StatusOption)
                    }
                  />
                </InputShell>
              </div>
            </SectionCard>

            <Card className="rounded-2xl p-4">
              <div className="flex items-center justify-end gap-3">
                <Button
                  className="h-9 rounded-lg px-4"
                  type="button"
                  variant="outline"
                  onClick={() => navigateTo("/admin/audit-universe")}
                >
                  Cancel
                </Button>
                <Button
                  className="h-9 rounded-lg bg-[#0f5fcb] px-4 text-white hover:bg-[#0a56b7]"
                  type="submit"
                >
                  <Save size={16} />
                  Save Auditable Unit
                </Button>
              </div>
            </Card>
          </div>

          <div className="min-w-0">
            <Card className="rounded-2xl p-0 xl:sticky xl:top-5">
              <div className="border-b border-slate-100 px-4 py-3.5">
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100">
                    <Eye size={16} className="text-slate-600" />
                  </div>
                  <div>
                    <Text className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-700">
                      Auditable Unit Summary
                    </Text>
                    <Text className="mt-0.5 text-xs text-slate-500">
                      Preview of the information to be saved
                    </Text>
                  </div>
                </div>
              </div>

              <div className="mx-4 mt-4 h-0.5 rounded-full bg-blue-100">
                <div className="h-full w-full rounded-full bg-[#2c6af2]" />
              </div>

              <div className="m-4 overflow-hidden rounded-2xl border border-slate-100 bg-white">
                {summaryItems.map((item) => (
                  <SummaryRow key={item.label} {...item} />
                ))}
              </div>

              <div className="px-4 pb-4">
                <div className="flex items-start gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3 py-3 text-blue-700">
                  <Info size={16} className="mt-0.5 shrink-0" />
                  <Text className="text-xs font-medium text-blue-700">
                    Please review all information before saving.
                  </Text>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </form>
    </AdminLayout>
  );
}

export default CreateAuditableUnitPage;
