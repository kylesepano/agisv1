import {
  useState,
  type FormEvent,
  type InputHTMLAttributes,
  type ReactNode,
  type SelectHTMLAttributes,
} from "react";
import { ChevronDown, Layers, Sparkles } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Modal } from "../../../../components/ui/modal";
import { Select2 } from "../../../../components/ui/select2";
import { cn } from "../../../../utils/helpers";
import type { OfficeRecord } from "../../offices/offices-types";
import type {
  AuditAreaCategory,
  AuditAreaRecord,
  AuditAreaStatus,
} from "../audit-areas-types";
import { auditAreaCategories } from "../audit-areas-data";

export type AuditAreaFormValues = {
  auditAreaId: string;
  officeId: string;
  officeName: string;
  auditAreaName: string;
  auditAreaCategory: AuditAreaCategory;
  description: string;
  status: AuditAreaStatus;
};

type AuditAreaFormModalProps = {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (values: AuditAreaFormValues) => { success: boolean; error?: string };
  defaultValues?: Partial<AuditAreaRecord> | null;
  existingAreas: AuditAreaRecord[];
  offices: OfficeRecord[];
  isEditing?: boolean;
};

function Field({
  label,
  required = false,
  error,
  children,
}: {
  label: string;
  required?: boolean;
  error?: string;
  children: ReactNode;
}) {
  return (
    <div className="block space-y-1">
      <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1">
        <div className="flex items-center gap-1">
          <span>{label}</span>
          {required ? <span className="text-rose-500">*</span> : null}
        </div>
      </div>
      {children}
      {error && (
        <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
          {error}
        </p>
      )}
    </div>
  );
}

function InputBase(props: InputHTMLAttributes<HTMLInputElement> & { hasError?: boolean }) {
  const { className, hasError, ...rest } = props;

  return (
    <input
      className={cn(
        "flex h-9 w-full rounded-lg border bg-white px-3 text-xs text-slate-900 shadow-xs transition placeholder:text-slate-400 focus:outline-none focus:ring-1",
        hasError
          ? "border-rose-400 focus:border-rose-500 bg-rose-50/20 focus:ring-rose-500/20"
          : "border-slate-200 focus:border-blue-500 focus:ring-blue-500/20",
        className,
      )}
      {...rest}
    />
  );
}

function SelectBase(props: SelectHTMLAttributes<HTMLSelectElement>) {
  const { className, children, ...rest } = props;

  return (
    <div className="relative">
      <select
        className={cn(
          "h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white px-3 pr-8 text-xs text-slate-900 shadow-xs transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/20",
          className,
        )}
        {...rest}
      >
        {children}
      </select>
      <ChevronDown
        className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400"
        size={14}
      />
    </div>
  );
}

export function AuditAreaFormModal({
  isOpen,
  onClose,
  onSubmit,
  defaultValues,
  existingAreas,
  offices,
  isEditing = false,
}: AuditAreaFormModalProps) {
  const resolvedValues: AuditAreaFormValues = {
    auditAreaId: defaultValues?.auditAreaId || defaultValues?.code || defaultValues?.id || "",
    officeId: defaultValues?.officeId || offices[0]?.officeId || "",
    officeName: defaultValues?.officeName || offices[0]?.officeName || "",
    auditAreaName: defaultValues?.auditAreaName || defaultValues?.name || "",
    auditAreaCategory: defaultValues?.auditAreaCategory || (defaultValues?.category as any) || "Compliance & Procurement",
    description: defaultValues?.description || "",
    status: defaultValues?.status || "Active",
  };

  const formResetKey = JSON.stringify({
    isOpen,
    auditAreaId: resolvedValues.auditAreaId,
    officeId: resolvedValues.officeId,
  });

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? "Edit Audit Area Profile" : "Register Governed Audit Area"}
      size="lg"
      contentClassName="px-5 py-4"
    >
      <AuditAreaFormFields
        key={formResetKey}
        initialFormValues={resolvedValues}
        isEditing={isEditing}
        existingAreas={existingAreas}
        offices={offices}
        onClose={onClose}
        onSubmit={onSubmit}
      />
    </Modal>
  );
}

function AuditAreaFormFields({
  initialFormValues,
  isEditing,
  existingAreas,
  offices,
  onClose,
  onSubmit,
}: {
  initialFormValues: AuditAreaFormValues;
  isEditing: boolean;
  existingAreas: AuditAreaRecord[];
  offices: OfficeRecord[];
  onClose: () => void;
  onSubmit: (values: AuditAreaFormValues) => { success: boolean; error?: string };
}) {
  const [values, setValues] = useState<AuditAreaFormValues>(initialFormValues);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [generalError, setGeneralError] = useState("");

  const updateValue = <K extends keyof AuditAreaFormValues>(
    key: K,
    value: AuditAreaFormValues[K],
  ) => {
    setValues((current) => ({
      ...current,
      [key]: value,
    }));
    if (errors[key]) {
      setErrors((prev) => ({ ...prev, [key]: "" }));
    }
  };

  const handleOfficeChange = (officeId: string) => {
    const selectedOffice = offices.find((o) => o.officeId === officeId || o.code === officeId);
    setValues((current) => ({
      ...current,
      officeId,
      officeName: selectedOffice?.officeName || selectedOffice?.name || "",
    }));
    if (errors.officeId) {
      setErrors((prev) => ({ ...prev, officeId: "" }));
    }
  };

  const validate = (): boolean => {
    const errs: Record<string, string> = {};
    const title = values.auditAreaName.trim();

    if (!values.officeId) {
      errs.officeId = "Parent Auditee Office is required (MDS-000 Rule 7).";
    }

    if (!title || title.length < 3) {
      errs.auditAreaName = "Audit Area title must be at least 3 characters.";
    }

    if (!values.description || values.description.trim().length < 10) {
      errs.description = "Coverage scope description must be at least 10 characters.";
    }

    // Check duplicate title under the SAME office
    const duplicate = existingAreas.find(
      (a) =>
        (a.auditAreaId !== values.auditAreaId && a.code !== values.auditAreaId) &&
        (a.officeId === values.officeId || a.officeName === values.officeName) &&
        (a.auditAreaName?.toLowerCase().trim() === title.toLowerCase() ||
          a.name?.toLowerCase().trim() === title.toLowerCase()),
    );
    if (duplicate) {
      errs.auditAreaName = `An audit area with this title already exists under this office (${duplicate.auditAreaId || duplicate.code}).`;
    }

    setErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setGeneralError("");

    if (!validate()) {
      return;
    }

    const res = onSubmit(values);
    if (!res.success && res.error) {
      setGeneralError(res.error);
    }
  };

  return (
    <form className="space-y-3.5" onSubmit={handleSubmit}>
      {/* Hierarchy Rule Citation Header */}
      <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2 text-xs">
        <div className="flex items-center gap-2 text-slate-700 font-semibold">
          <Layers size={15} className="text-blue-600" />
          <span>{isEditing ? `Modifying ${values.auditAreaId}` : "MDS-000 §6 / Rule 7 Governed Hierarchy"}</span>
        </div>
        <span className="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200 font-mono">
          Office → Audit Area
        </span>
      </div>

      {generalError && (
        <div className="rounded-lg border border-rose-300 bg-rose-50 p-2.5 text-xs text-rose-700">
          {generalError}
        </div>
      )}

      {/* Row 1: Parent Auditee Office Selection */}
      <Field label="Parent Auditee Office" required error={errors.officeId}>
        <Select2
          value={values.officeId}
          placeholder="Select Parent Office..."
          searchPlaceholder="Search office ID, name, or acronym..."
          options={offices.map((o) => {
            const id = o.officeId || o.code || "";
            const name = o.officeName || o.name || "";
            const acronym = o.officeAcronym || o.shortName || "";
            return {
              value: id,
              label: `${name} ${acronym ? `(${acronym})` : ""} — ${id}`,
            };
          })}
          onChange={handleOfficeChange}
        />
      </Field>

      {/* Row 2: Audit Area ID & Category */}
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Audit Area Code / ID">
          <div className="flex items-center justify-between h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs text-slate-700 font-mono font-semibold select-none">
            <span>{values.auditAreaId || "Auto-assigned (e.g. AA-044)"}</span>
            <span className="text-[10px] text-slate-400 font-normal">system ID</span>
          </div>
        </Field>

        <Field label="Audit Area Category" required>
          <SelectBase
            value={values.auditAreaCategory}
            onChange={(e) => updateValue("auditAreaCategory", e.target.value as AuditAreaCategory)}
          >
            {auditAreaCategories.map((cat) => (
              <option key={cat} value={cat}>
                {cat}
              </option>
            ))}
          </SelectBase>
        </Field>
      </div>

      {/* Row 3: Title */}
      <Field label="Audit Area Title" required error={errors.auditAreaName}>
        <InputBase
          hasError={Boolean(errors.auditAreaName)}
          placeholder="e.g. Procurement Process Compliance & Bidding Controls"
          value={values.auditAreaName}
          onChange={(e) => updateValue("auditAreaName", e.target.value)}
        />
      </Field>

      {/* Row 4: Description */}
      <Field label="Coverage Scope & Mandate Description" required error={errors.description}>
        <textarea
          rows={3}
          className={cn(
            "w-full rounded-lg border bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 transition resize-y shadow-xs",
            errors.description
              ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
              : "border-slate-200 focus:border-blue-500 focus:ring-blue-500/20",
          )}
          placeholder="Describe the operational boundaries, statutory mandates, or internal control focus of this audit area..."
          value={values.description}
          onChange={(e) => updateValue("description", e.target.value)}
        />
      </Field>

      {/* Row 5: Status */}
      <div className="max-w-[240px]">
        <Field label="Availability Status" required>
          <SelectBase
            value={values.status}
            onChange={(e) => updateValue("status", e.target.value as AuditAreaStatus)}
          >
            <option value="Active">Active (Available in Scope)</option>
            <option value="Inactive">Inactive (Deactivated)</option>
          </SelectBase>
        </Field>
      </div>

      {/* Modal Actions */}
      <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        <Button
          type="button"
          variant="outline"
          className="h-9 px-3.5 text-xs"
          onClick={onClose}
        >
          Cancel
        </Button>
        <Button
          type="submit"
          className="h-9 px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-xs"
        >
          <Sparkles size={13} className="mr-1" />
          {isEditing ? "Save Area Changes" : "Register Audit Area"}
        </Button>
      </div>
    </form>
  );
}

export type { AuditAreaFormModalProps };
