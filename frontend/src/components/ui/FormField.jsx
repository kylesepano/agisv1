export default function FormField({
  label,
  htmlFor,
  error,
  required = false,
  hint,
  children,
}) {
  return (
    <div>
      <label
        className="mb-1.5 block text-sm font-semibold text-slate-700"
        htmlFor={htmlFor}
      >
        {label}
        {required && <span className="ml-1 text-red-500">*</span>}
      </label>
      {children}
      {error ? (
        <p className="mt-1.5 text-xs font-medium text-red-600">{error}</p>
      ) : (
        hint && <p className="mt-1.5 text-xs text-slate-500">{hint}</p>
      )}
    </div>
  );
}
