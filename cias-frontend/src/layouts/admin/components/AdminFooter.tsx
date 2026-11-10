import { useAuth } from "../../../hooks/useAuth";

export function AdminFooter() {
  const { runtimeConfig } = useAuth();
  const year = typeof runtimeConfig?.currentFiscalYear === "number" ? runtimeConfig.currentFiscalYear : new Date().getFullYear();
  const orgName = typeof runtimeConfig?.organizationName === "string" ? runtimeConfig.organizationName : "City Government of Cagayan de Oro";
  const shortName = typeof runtimeConfig?.systemShortName === "string" ? runtimeConfig.systemShortName : "AGIS";
  const version = typeof runtimeConfig?.systemVersion === "string" ? runtimeConfig.systemVersion : "1.0.0";

  return (
    <footer className="flex min-h-9 shrink-0 flex-wrap items-center gap-x-5 gap-y-1 border-t border-slate-200 bg-white px-6 py-2.5 text-[11px] text-slate-500 max-[640px]:px-4">
      <span className="font-semibold text-slate-700">
        {shortName} v{version}
      </span>
      <span>City Internal Audit Service (CIAS)</span>
      <span className="sm:ml-auto">
        © {year} {orgName}. All rights reserved.
      </span>
    </footer>
  );
}
