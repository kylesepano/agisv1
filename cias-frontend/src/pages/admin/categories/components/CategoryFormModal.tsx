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
import { cn } from "../../../../utils/helpers";

export type CategoryFormValues = {
  code: string;
  name: string;
  description: string;
  status: "Active" | "Inactive";
};

type CategoryFormModalProps = {
  isOpen: boolean;
  onClose: () => void;
  onSubmit?: (values: CategoryFormValues) => void;
  defaultValues?: Partial<CategoryFormValues>;
  title?: string;
  submitLabel?: string;
  cancelLabel?: string;
};

const initialValues: CategoryFormValues = {
  code: "",
  name: "",
  description: "",
  status: "Active",
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

export function CategoryFormModal({
  isOpen,
  onClose,
  onSubmit,
  defaultValues,
  title = "Add Category",
  submitLabel = "Save Category",
  cancelLabel = "Cancel",
}: CategoryFormModalProps) {
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
      size="md"
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
            form="category-form-modal"
            type="submit"
          >
            {submitLabel}
          </Button>
        </>
      }
    >
      <CategoryFormFields
        key={formResetKey}
        initialFormValues={resolvedValues}
        onSubmit={onSubmit}
      />
    </Modal>
  );
}

function CategoryFormFields({
  initialFormValues,
  onSubmit,
}: {
  initialFormValues: CategoryFormValues;
  onSubmit?: (values: CategoryFormValues) => void;
}) {
  const [values, setValues] = useState<CategoryFormValues>(initialFormValues);

  const updateValue = <K extends keyof CategoryFormValues>(
    key: K,
    value: CategoryFormValues[K],
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
      id="category-form-modal"
      onSubmit={handleSubmit}
    >
      <Field label="Category Code" required>
        <InputBase
          placeholder="Enter category code (e.g., CAT-007)"
          value={values.code}
          onChange={(event) => updateValue("code", event.target.value)}
        />
      </Field>

      <Field label="Category Name" required>
        <InputBase
          placeholder="Enter category name"
          value={values.name}
          onChange={(event) => updateValue("name", event.target.value)}
        />
      </Field>

      <Field label="Description" required>
        <textarea
          className="min-h-[94px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100"
          placeholder="Enter description of the category"
          value={values.description}
          onChange={(event) => updateValue("description", event.target.value)}
        />
      </Field>

      <Field label="Status" required>
        <SelectBase
          value={values.status}
          onChange={(event) =>
            updateValue(
              "status",
              event.target.value as CategoryFormValues["status"],
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

export type { CategoryFormModalProps };
