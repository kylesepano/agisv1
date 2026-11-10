export function DashboardFooter() {
  return (
    <footer className="flex w-full flex-col items-start gap-1.5 border-t border-slate-200/60 bg-[#eef4fb] px-0.5 pt-2 text-[11px] leading-normal text-slate-400 md:flex-row md:items-center md:justify-between">
      <div className="flex w-full flex-col gap-0.5 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 md:w-auto">
        <span className="font-semibold text-slate-500">AGIS v1.0.0</span>
        <span>City Internal Audit Service (CIAS)</span>
      </div>
      <p className="w-full text-left break-words md:w-auto md:text-right">
        &copy; 2026 City Government of Cagayan de Oro. All rights reserved.
      </p>
    </footer>
  );
}
