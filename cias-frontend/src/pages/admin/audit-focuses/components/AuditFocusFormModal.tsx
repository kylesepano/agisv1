import {
  useState,
  type FormEvent,
  type InputHTMLAttributes,
  type ReactNode,
  type SelectHTMLAttributes,
} from "react";
import { ChevronDown, FileCheck2, Sparkles } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Modal } from "../../../../components/ui/modal";
import { Select2 } from "../../../../components/ui/select2";
import { cn } from "../../../../utils/helpers";
import type { AuditAreaRecord } from "../../audit-areas/audit-areas-types";
import type {
  AuditFocusRecord,
  AuditFocusStatus,
} from "../audit-focuses-types";

export type AuditFocusFormValues = {
  auditFocusId: string;
  auditAreaId: string;
  auditAreaName: string;
  officeId?: string;
  officeName?: string;
  auditFocusName: string;
  description: string;
  status: AuditFocusStatus;
};

type AuditFocusFormModalProps = {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (values: AuditFocusFormValues) => { success: boolean; error?: string };
  defaultValues?: Partial<AuditFocusRecord> | null;
  existingFocuses: AuditFocusRecord[];
  auditAreas: AuditAreaRecord[];
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

export function AuditFocusFormModal({
  isOpen,
  onClose,
  onSubmit,
  defaultValues,
  existingFocuses,
  auditAreas,
  isEditing = false,
}: AuditFocusFormModalProps) {
  const resolvedValues: AuditFocusFormValues = {
    auditFocusId: defaultValues?.auditFocusId || defaultValues?.code || defaultValues?.id || "",
    auditAreaId: defaultValues?.auditAreaId || auditAreas[0]?.auditAreaId || "",
    auditAreaName: defaultValues?.auditAreaName || auditAreas[0]?.auditAreaName || "",
    officeId: defaultValues?.officeId || auditAreas[0]?.officeId || "",
    officeName: defaultValues?.officeName || auditAreas[0]?.officeName || "",
    auditFocusName: defaultValues?.auditFocusName || defaultValues?.name || "",
    description: defaultValues?.description || "",
    status: defaultValues?.status || "Active",
  };

  const formResetKey = JSON.stringify({
    isOpen,
    auditFocusId: resolvedValues.auditFocusId,
    auditAreaId: resolvedValues.auditAreaId,
  });

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? "Edit Audit Focus Profile" : "Register Governed Audit Focus"}
      size="lg"
      contentClassName="px-5 py-4"
    >
      <AuditFocusFormFields
        key={formResetKey}
        initialFormValues={resolvedValues}
        isEditing={isEditing}
        existingFocuses={existingFocuses}
        auditAreas={auditAreas}
        onClose={onClose}
        onSubmit={onSubmit}
      />
    </Modal>
  );
}

function AuditFocusFormFields({
  initialFormValues,
  isEditing,
  existingFocuses,
  auditAreas,
  onClose,
  onSubmit,
}: {
  initialFormValues: AuditFocusFormValues;
  isEditing: boolean;
  existingFocuses: AuditFocusRecord[];
  auditAreas: AuditAreaRecord[];
  onClose: () => void;
  onSubmit: (values: AuditFocusFormValues) => { success: boolean; error?: string };
}) {
  const [values, setValues] = useState<AuditFocusFormValues>(initialFormValues);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [generalError, setGeneralError] = useState("");

  const updateValue = <K extends keyof AuditFocusFormValues>(
    key: K,
    value: AuditFocusFormValues[K],
  ) => {
    setValues((current) => ({
      ...current,
      [key]: value,
    }));
    if (errors[key]) {
      setErrors((prev) => ({ ...prev, [key]: "" }));
    }
  };

  const handleAreaChange = (areaId: string) => {
    const selectedArea = auditAreas.find(
      (a) => a.auditAreaId === areaId || a.code === areaId || a.id === areaId,
    );
    setValues((current) => ({
      ...current,
      auditAreaId: areaId,
      auditAreaName: selectedArea?.auditAreaName || selectedArea?.name || "",
      officeId: selectedArea?.officeId || "",
      officeName: selectedArea?.officeName || "",
    }));
    if (errors.auditAreaId) {
      setErrors((prev) => ({ ...prev, auditAreaId: "" }));
    }
  };

  const validate = (): boolean => {
    const errs: Record<string, string> = {};
    const title = values.auditFocusName.trim();

    if (!values.auditAreaId) {
      errs.auditAreaId = "Parent Audit Area is required (MDS-200 §80).";
    }

    if (!title || title.length < 3) {
      errs.auditFocusName = "Audit Focus title must be at least 3 characters.";
    }

    if (!values.description || values.description.trim().length < 10) {
      errs.description = "Examination scope description must be at least 10 characters.";
    }

    // Check duplicate title under the SAME audit area
    const duplicate = existingFocuses.find(
      (f) =>
        (f.auditFocusId !== values.auditFocusId && f.code !== values.auditFocusId) &&
        (f.auditAreaId === values.auditAreaId || f.auditAreaName === values.auditAreaName) &&
        (f.auditFocusName?.toLowerCase().trim() === title.toLowerCase() ||
          f.name?.toLowerCase().trim() === title.toLowerCase()),
    );
    if (duplicate) {
      errs.auditFocusName = `A focus item with this title already exists under this audit area (${duplicate.auditFocusId || duplicate.code}).`;
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
          <FileCheck2 size={15} className="text-blue-600" />
          <span>{isEditing ? `Modifying ${values.auditFocusId}` : "MDS-000 §6 / MDS-200 §80 Governed Hierarchy"}</span>
        </div>
        <span className="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200 font-mono">
          Area → Focus
        </span>
      </div>

      {generalError && (
        <div className="rounded-lg border border-rose-300 bg-rose-50 p-2.5 text-xs text-rose-700">
          {generalError}
        </div>
      )}

      {/* Row 1: Parent Audit Area Selection */}
      <Field label="Parent Audit Area" required error={errors.auditAreaId}>
        <Select2
          value={values.auditAreaId}
          placeholder="Select Parent Audit Area..."
          searchPlaceholder="Search audit area ID, title, or office..."
          options={auditAreas.map((a) => {
            const id = a.auditAreaId || a.code || a.id || "";
            const name = a.auditAreaName || a.name || "";
            const office = a.officeName || "";
            return {
              value: id,
              label: `${id} — ${name} (${office})`,
            };
          })}
          onChange={handleAreaChange}
        />
      </Field>

      {/* Contextual Parent Office Pill (Auto-derived) */}
      {values.officeName && (
        <div className="flex items-center gap-2 rounded-md bg-slate-50 px-2.5 py-1 text-[11px] text-slate-600 border border-slate-200">
          <span className="font-semibold text-slate-400">Parent Auditee Office:</span>
          <span className="font-semibold text-slate-800">{values.officeName}</span>
        </div>
      )}

      {/* Row 2: Focus ID & Availability Status */}
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Audit Focus Code / ID">
          <div className="flex items-center justify-between h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs text-slate-700 font-mono font-semibold select-none">
            <span>{values.auditFocusId || "Auto-assigned (e.g. AF-039)"}</span>
            <span className="text-[10px] text-slate-400 font-normal">system ID</span>
          </div>
        </Field>

        <Field label="Availability Status" required>
          <SelectBase
            value={values.status}
            onChange={(e) => updateValue("status", e.target.value as AuditFocusStatus)}
          >
            <option value="Active">Active (Available in Scope)</option>
            <option value="Inactive">Inactive (Deactivated)</option>
          </SelectBase>
        </Field>
      </div>

      {/* Row 3: Title */}
      <Field label="Audit Focus Title" required error={errors.auditFocusName}>
        <InputBase
          hasError={Boolean(errors.auditFocusName)}
          placeholder="e.g. Quick Response Fund (QRF 30%) Declaration & Cash Drawdowns"
          value={values.auditFocusName}
          onChange={(e) => updateValue("auditFocusName", e.target.value)}
        />
      </Field>

      {/* Row 4: Description */}
      <Field label="Examination Scope & Concern Detail" required error={errors.description}>
        <textarea
          rows={3}
          className={cn(
            "w-full rounded-lg border bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 transition resize-y shadow-xs",
            errors.description
              ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
              : "border-slate-200 focus:border-blue-500 focus:ring-blue-500/20",
          )}
          placeholder="Describe the specific control, risk, transactions, or compliance criteria examined under this focus..."
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
          {isEditing ? "Save Focus Changes" : "Register Audit Focus"}
        </Button>
      </div>
    </form>
  );
}

export type { AuditFocusFormModalProps };
