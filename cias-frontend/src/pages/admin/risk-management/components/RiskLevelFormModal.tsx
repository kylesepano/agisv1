import {
  useState,
  type FormEvent,
  type InputHTMLAttributes,
  type ReactNode,
  type SelectHTMLAttributes,
} from "react";
import { ChevronDown } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Modal } from "../../../../components/ui/modal";
import { Text } from "../../../../components/ui/typography";
import { cn } from "../../../../utils/helpers";

export type RiskLevelFormValues = {
  code: string;
  name: string;
  scoreFrom: string;
  scoreTo: string;
  colorBadge: "Red" | "Orange" | "Yellow" | "Green";
  description: string;
  status: "Active" | "Inactive";
};

type RiskLevelFormModalProps = {
  isOpen: boolean;
  onClose: () => void;
  onSubmit?: (values: RiskLevelFormValues) => void;
  defaultValues?: Partial<RiskLevelFormValues>;
  title?: string;
  submitLabel?: string;
  cancelLabel?: string;
};

const initialValues: RiskLevelFormValues = {
  code: "",
  name: "",
  scoreFrom: "",
  scoreTo: "",
  colorBadge: "Red",
  description: "",
  status: "Active",
};

const colorBadgeTone: Record<RiskLevelFormValues["colorBadge"], string> = {
  Red: "bg-rose-500",
  Orange: "bg-orange-500",
  Yellow: "bg-amber-400",
  Green: "bg-emerald-500",
};

const colorBadgeFieldTone: Record<RiskLevelFormValues["colorBadge"], string> = {
  Red: "border-rose-200 bg-rose-50/70 text-rose-700",
  Orange: "border-orange-200 bg-orange-50/70 text-orange-700",
  Yellow: "border-amber-200 bg-amber-50/80 text-amber-800",
  Green: "border-emerald-200 bg-emerald-50/70 text-emerald-700",
};

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

function InputBase(props: InputHTMLAttributes<HTMLInputElement>) {
  const { className, ...rest } = props;

  return (
    <input
      className={cn(
        "flex h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100",
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
          "h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white px-3 pr-10 text-sm text-slate-900 shadow-sm transition-colors focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100",
          className,
        )}
        {...rest}
      >
        {children}
      </select>
      <ChevronDown
        className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
        size={16}
      />
    </div>
  );
}

export function RiskLevelFormModal({
  isOpen,
  onClose,
  onSubmit,
  defaultValues,
  title = "Add Risk Level",
  submitLabel = "Save Risk Level",
  cancelLabel = "Cancel",
}: RiskLevelFormModalProps) {
  const resolvedValues = {
    ...initialValues,
    ...defaultValues,
  };
  const formResetKey = JSON.stringify(resolvedValues);

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={title}
      size="lg"
      contentClassName="px-5 py-4"
      footer={
        <>
          <Button
            className="h-9 rounded-lg px-4"
            variant="outline"
            onClick={onClose}
          >
            {cancelLabel}
          </Button>
          <Button
            className="h-9 rounded-lg bg-[#2563eb] px-4 text-white hover:bg-[#1d4ed8]"
            form="risk-level-form-modal"
            type="submit"
          >
            {submitLabel}
          </Button>
        </>
      }
    >
      <RiskLevelFormFields
        key={formResetKey}
        initialFormValues={resolvedValues}
        onSubmit={onSubmit}
      />
    </Modal>
  );
}

function RiskLevelFormFields({
  initialFormValues,
  onSubmit,
}: {
  initialFormValues: RiskLevelFormValues;
  onSubmit?: (values: RiskLevelFormValues) => void;
}) {
  const [values, setValues] = useState<RiskLevelFormValues>(initialFormValues);

  const updateValue = <K extends keyof RiskLevelFormValues>(
    key: K,
    value: RiskLevelFormValues[K],
  ) => {
    setValues((current) => ({
      ...current,
      [key]: value,
    }));
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    onSubmit?.(values);
  };

  return (
    <form
      className="space-y-4"
      id="risk-level-form-modal"
      onSubmit={handleSubmit}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Risk Code" required>
          <InputBase
            placeholder="e.g., RL-005"
            value={values.code}
            onChange={(event) => updateValue("code", event.target.value)}
          />
        </Field>

        <Field label="Risk Level Name" required>
          <InputBase
            placeholder="e.g., Very High"
            value={values.name}
            onChange={(event) => updateValue("name", event.target.value)}
          />
        </Field>
      </div>

      <div className="grid items-start gap-4 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)]">
        <Field label="Score Range" required>
          <div className="space-y-1.5">
            <div className="grid grid-cols-[minmax(0,1fr)_20px_minmax(0,1fr)] gap-2">
              <div className="space-y-1">
                <Text className="text-xs text-slate-500">From</Text>
                <InputBase
                  placeholder="e.g., 16"
                  value={values.scoreFrom}
                  onChange={(event) =>
                    updateValue("scoreFrom", event.target.value)
                  }
                />
              </div>
              <div className="flex items-end justify-center pb-2 text-slate-400">
                -
              </div>
              <div className="space-y-1">
                <Text className="text-xs text-slate-500">To</Text>
                <InputBase
                  placeholder="e.g., 25"
                  value={values.scoreTo}
                  onChange={(event) =>
                    updateValue("scoreTo", event.target.value)
                  }
                />
              </div>
            </div>
            <Text className="text-xs text-slate-500">
              AGIS uses this range to automatically assign a risk level after
              impact and likelihood scores are totaled.
            </Text>
          </div>
        </Field>

        <Field label="Color Badge" required>
          <div className="space-y-1.5">
            <Text className="text-xs text-transparent select-none">Color</Text>
            <div className="relative">
              <span
                className={cn(
                  "pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 rounded-full",
                  colorBadgeTone[values.colorBadge],
                )}
              />
              <SelectBase
                className={cn(
                  "pl-10 font-medium",
                  colorBadgeFieldTone[values.colorBadge],
                )}
                value={values.colorBadge}
                onChange={(event) =>
                  updateValue(
                    "colorBadge",
                    event.target.value as RiskLevelFormValues["colorBadge"],
                  )
                }
              >
                <option value="Red">Red</option>
                <option value="Orange">Orange</option>
                <option value="Yellow">Yellow</option>
                <option value="Green">Green</option>
              </SelectBase>
            </div>
          </div>
        </Field>
      </div>

      <Field label="Description">
        <textarea
          className="min-h-[98px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100"
          maxLength={255}
          placeholder="Enter description of the risk level..."
          value={values.description}
          onChange={(event) => updateValue("description", event.target.value)}
        />
        <div className="mt-1 flex justify-end">
          <Text className="text-xs text-slate-400">
            {values.description.length} / 255
          </Text>
        </div>
      </Field>

      <Field label="Status" required>
        <SelectBase
          value={values.status}
          onChange={(event) =>
            updateValue(
              "status",
              event.target.value as RiskLevelFormValues["status"],
            )
          }
        >
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
        </SelectBase>
      </Field>
    </form>
  );
}

export type { RiskLevelFormModalProps };
