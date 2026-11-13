import {
  useState,
  type FormEvent,
  type InputHTMLAttributes,
  type ReactNode,
  type SelectHTMLAttributes,
} from "react";
import { Building2, ChevronDown, Sparkles } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Modal } from "../../../../components/ui/modal";
import { cn } from "../../../../utils/helpers";
import type { OfficeCategory, OfficeRecord, OfficeStatus } from "../offices-types";
import { officeCategories } from "../offices-data";

export type OfficeFormValues = {
  officeId: string;
  officeName: string;
  officeAcronym: string;
  officeCategory: OfficeCategory;
  head: string;
  designation: string;
  contactEmail: string;
  description: string;
  status: OfficeStatus;
};

type OfficeFormModalProps = {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (values: OfficeFormValues) => { success: boolean; error?: string };
  defaultValues?: Partial<OfficeRecord> | null;
  existingOffices: OfficeRecord[];
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
    <label className="block space-y-1">
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
    </label>
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

export function OfficeFormModal({
  isOpen,
  onClose,
  onSubmit,
  defaultValues,
  existingOffices,
  isEditing = false,
}: OfficeFormModalProps) {
  const resolvedValues: OfficeFormValues = {
    officeId: defaultValues?.officeId || defaultValues?.code || "",
    officeName: defaultValues?.officeName || defaultValues?.name || "",
    officeAcronym: defaultValues?.officeAcronym || defaultValues?.shortName || "",
    officeCategory: defaultValues?.officeCategory || "Line Department",
    head: defaultValues?.head || "",
    designation: defaultValues?.designation || "",
    contactEmail: defaultValues?.contactEmail || "",
    description: defaultValues?.description || "",
    status: defaultValues?.status || "Active",
  };

  const formResetKey = JSON.stringify({
    isOpen,
    officeId: resolvedValues.officeId,
    officeName: resolvedValues.officeName,
  });

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? "Edit Auditee Office Profile" : "Register New Auditee Office"}
      size="lg"
      contentClassName="px-5 py-4"
    >
      <OfficeFormFields
        key={formResetKey}
        initialFormValues={resolvedValues}
        isEditing={isEditing}
        existingOffices={existingOffices}
        onClose={onClose}
        onSubmit={onSubmit}
      />
    </Modal>
  );
}

function OfficeFormFields({
  initialFormValues,
  isEditing,
  existingOffices,
  onClose,
  onSubmit,
}: {
  initialFormValues: OfficeFormValues;
  isEditing: boolean;
  existingOffices: OfficeRecord[];
  onClose: () => void;
  onSubmit: (values: OfficeFormValues) => { success: boolean; error?: string };
}) {
  const [values, setValues] = useState<OfficeFormValues>(initialFormValues);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [generalError, setGeneralError] = useState("");

  const updateValue = <K extends keyof OfficeFormValues>(
    key: K,
    value: OfficeFormValues[K],
  ) => {
    setValues((current) => ({
      ...current,
      [key]: value,
    }));
    if (errors[key]) {
      setErrors((prev) => ({ ...prev, [key]: "" }));
    }
  };

  const validate = (): boolean => {
    const errs: Record<string, string> = {};
    const name = values.officeName.trim();
    const acronym = values.officeAcronym.trim();

    if (!name || name.length < 3) {
      errs.officeName = "Office name must be at least 3 characters.";
    }

    if (!acronym || acronym.length < 2) {
      errs.officeAcronym = "Acronym / short code is required (min 2 chars).";
    }

    // Check duplicate name
    const duplicateName = existingOffices.find(
      (o) =>
        (o.officeId !== values.officeId && o.code !== values.officeId) &&
        (o.officeName?.toLowerCase().trim() === name.toLowerCase() ||
          o.name?.toLowerCase().trim() === name.toLowerCase()),
    );
    if (duplicateName) {
      errs.officeName = `An office with this name already exists (${duplicateName.officeId || duplicateName.code}).`;
    }

    // Check duplicate acronym
    const duplicateAcronym = existingOffices.find(
      (o) =>
        (o.officeId !== values.officeId && o.code !== values.officeId) &&
        (o.officeAcronym?.toLowerCase().trim() === acronym.toLowerCase() ||
          o.shortName?.toLowerCase().trim() === acronym.toLowerCase()),
    );
    if (duplicateAcronym) {
      errs.officeAcronym = `Acronym "${acronym}" is already in use by ${duplicateAcronym.officeName || duplicateAcronym.name}.`;
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
      {/* Spec Badge Header */}
      <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2 text-xs">
        <div className="flex items-center gap-2 text-slate-700 font-semibold">
          <Building2 size={15} className="text-blue-600" />
          <span>{isEditing ? `Modifying ${values.officeId}` : "MDS-000 §6 Core Reference Entity"}</span>
        </div>
        <span className="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200 font-mono">
          Single Source of Truth
        </span>
      </div>

      {generalError && (
        <div className="rounded-lg border border-rose-300 bg-rose-50 p-2.5 text-xs text-rose-700">
          {generalError}
        </div>
      )}

      {/* Row 1: Office ID (if editing / preview) & Category */}
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Office Identifier (ID)">
          <div className="flex items-center justify-between h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs text-slate-700 font-mono font-semibold select-none">
            <span>{values.officeId || "Auto-assigned (e.g. OFF-037)"}</span>
            <span className="text-[10px] text-slate-400 font-normal">system ID</span>
          </div>
        </Field>

        <Field label="Office Category" required>
          <SelectBase
            value={values.officeCategory}
            onChange={(e) => updateValue("officeCategory", e.target.value as OfficeCategory)}
          >
            {officeCategories.map((cat) => (
              <option key={cat} value={cat}>
                {cat}
              </option>
            ))}
          </SelectBase>
        </Field>
      </div>

      {/* Row 2: Office Name & Acronym */}
      <div className="grid gap-3 sm:grid-cols-3">
        <div className="sm:col-span-2">
          <Field label="Full Office Name" required error={errors.officeName}>
            <InputBase
              hasError={Boolean(errors.officeName)}
              placeholder="e.g. City General Services Office"
              value={values.officeName}
              onChange={(e) => updateValue("officeName", e.target.value)}
            />
          </Field>
        </div>

        <Field label="Office Acronym" required error={errors.officeAcronym}>
          <InputBase
            hasError={Boolean(errors.officeAcronym)}
            placeholder="e.g. CGSO"
            value={values.officeAcronym}
            onChange={(e) => updateValue("officeAcronym", e.target.value)}
          />
        </Field>
      </div>

      {/* Row 3: Office Head & Designation */}
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Department Head / Chief Officer">
          <InputBase
            placeholder="e.g. Atty. Joeffrey D. Namalata"
            value={values.head}
            onChange={(e) => updateValue("head", e.target.value)}
          />
        </Field>

        <Field label="Official Designation">
          <InputBase
            placeholder="e.g. City General Services Officer"
            value={values.designation}
            onChange={(e) => updateValue("designation", e.target.value)}
          />
        </Field>
      </div>

      {/* Row 4: Contact Email & Status */}
      <div className="grid gap-3 sm:grid-cols-3">
        <div className="sm:col-span-2">
          <Field label="Official Department Email">
            <InputBase
              placeholder="e.g. cgso@cagayandeoro.gov.ph"
              type="email"
              value={values.contactEmail}
              onChange={(e) => updateValue("contactEmail", e.target.value)}
            />
          </Field>
        </div>

        <Field label="Operational Status" required>
          <SelectBase
            value={values.status}
            onChange={(e) => updateValue("status", e.target.value as OfficeStatus)}
          >
            <option value="Active">Active (Participating)</option>
            <option value="Inactive">Inactive (Deactivated)</option>
          </SelectBase>
        </Field>
      </div>

      {/* Row 5: Description & Mandate Notes */}
      <Field label="Mandate / Governance Description">
        <textarea
          rows={2}
          className="w-full rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/20 transition resize-y shadow-xs"
          placeholder="Summary of statutory mandate, audit universe boundaries, or operating scope..."
          value={values.description}
          onChange={(e) => updateValue("description", e.target.value)}
        />
      </Field>

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
          {isEditing ? "Save Office Changes" : "Register Auditee Office"}
        </Button>
      </div>
    </form>
  );
}

export type { OfficeFormModalProps };
