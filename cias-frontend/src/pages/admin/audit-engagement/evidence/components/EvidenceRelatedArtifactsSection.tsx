import {
  ArrowUpRight,
  Link2,
} from "lucide-react";
import { navigateTo } from "../../../../../routes";
import type { AuditEvidenceItem } from "../evidence-types";

type EvidenceRelatedArtifactsSectionProps = {
  evidence: AuditEvidenceItem;
  engagementNumber: string;
};

export function EvidenceRelatedArtifactsSection({
  evidence,
  engagementNumber,
}: EvidenceRelatedArtifactsSectionProps) {
  const getArtifactRoute = (type: string, ref: string) => {
    switch (type) {
      case "Working Paper":
        return `/admin/audit-engagement/${engagementNumber}/execution/workpapers/${ref}`;
      case "Fieldwork Record":
        return `/admin/audit-engagement/${engagementNumber}/execution/records`;
      case "Procedure":
        return `/admin/audit-engagement/${engagementNumber}/planning/procedures/${ref}`;
      default:
        return `/admin/audit-engagement/${engagementNumber}/execution`;
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Link2 className="h-4 w-4 text-emerald-700" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Downstream Cross-Referenced Artifacts (MDS-200 §7 Multi-Link)
          </h3>
        </div>
        <span className="text-[10px] font-mono text-slate-400">
          Working Papers, Procedures & Fieldwork Records
        </span>
      </div>

      <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
        {evidence.relatedArtifacts.map((art) => (
          <div
            key={art.id}
            className="rounded-md border border-slate-200 bg-slate-50/70 p-2.5 space-y-1 text-xs transition hover:border-emerald-300 hover:bg-emerald-50/20"
          >
            <div className="flex items-center justify-between">
              <span className="font-mono text-[10px] font-bold text-emerald-800 bg-white px-1.5 py-0.2 rounded border border-emerald-200">
                {art.type}
              </span>

              <button
                type="button"
                onClick={() => navigateTo(getArtifactRoute(art.type, art.ref))}
                className="inline-flex items-center gap-0.5 text-[10px] font-semibold text-emerald-700 hover:underline cursor-pointer"
              >
                <span>Open</span>
                <ArrowUpRight size={10} />
              </button>
            </div>

            <div className="font-mono text-xs font-bold text-slate-800">
              {art.ref}
            </div>

            <p className="text-[11px] text-slate-500 truncate" title={art.title}>
              {art.title}
            </p>
          </div>
        ))}
      </div>
    </div>
  );
}
