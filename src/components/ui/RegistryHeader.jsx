export default function RegistryHeader({
  icon: Icon,
  title,
  description,
  readOnly = false,
  actions,
}) {
  return (
    <header className="mb-5 flex min-w-0 flex-col gap-4 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0 flex-1">
        <div className="flex min-w-0 items-start gap-3">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-sky-100 to-cyan-50 text-sky-700 shadow-sm ring-1 ring-sky-200/70 sm:h-11 sm:w-11">
            <Icon size={22} />
          </span>
          <div className="min-w-0 flex-1">
            <h2 className="break-words text-xl font-bold leading-tight text-slate-800 sm:text-2xl">
              {title}
            </h2>
            <p className="mt-1 max-w-3xl text-xs leading-5 text-slate-500 sm:text-sm">
              {description}
            </p>
          </div>
        </div>
        {readOnly && (
          <span className="mt-3 inline-flex rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800 ring-1 ring-amber-200">
            Your role has view-only access.
          </span>
        )}
      </div>
      {actions && (
        <div className="flex w-full flex-wrap gap-2 sm:w-auto sm:max-w-[68%] sm:shrink-0 sm:justify-end [&>a]:w-full [&>button]:w-full sm:[&>a]:w-auto sm:[&>button]:w-auto">
          {actions}
        </div>
      )}
    </header>
  );
}
