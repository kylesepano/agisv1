import { AlertTriangle, CheckCircle2, Layers, ShieldAlert } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Modal } from "../../../../components/ui/modal";
import type { AuditAreaRecord } from "../audit-areas-types";

type AuditAreaDeleteConfirmModalProps = {
  isOpen: boolean;
  area: AuditAreaRecord | null;
  referencingEngagements: string[];
  onClose: () => void;
  onConfirmDeactivate: () => void;
};

export function AuditAreaDeleteConfirmModal({
  isOpen,
  area,
  referencingEngagements,
  onClose,
  onConfirmDeactivate,
}: AuditAreaDeleteConfirmModalProps) {
  if (!area) return null;

  const hasActiveReferences = referencingEngagements.length > 0;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={hasActiveReferences ? "Deactivate Audit Area" : "Deactivate Audit Area Record"}
      size="md"
      contentClassName="p-5 space-y-4"
      footer={
        <div className="flex items-center justify-end gap-2 w-full">
          <Button variant="outline" className="h-9 px-3 text-xs" onClick={onClose}>
            Cancel
          </Button>
          <Button
            className="h-9 px-4 text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white"
            onClick={onConfirmDeactivate}
          >
            Confirm Deactivation (Soft Delete)
          </Button>
        </div>
      }
    >
      <div className="space-y-3">
        {/* Area Summary Header */}
        <div className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
          <div className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-blue-100 text-blue-700">
            <Layers size={18} />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-1.5">
              <span className="font-mono text-xs font-bold text-blue-700">
                {area.auditAreaId || area.code}
              </span>
              <span className="text-[10px] font-semibold text-slate-500 bg-white px-1.5 py-0.2 rounded border border-slate-200">
                {area.auditAreaCategory}
              </span>
            </div>
            <h4 className="text-sm font-bold text-slate-900 leading-snug">
              {area.auditAreaName || area.name}
            </h4>
            <p className="text-xs text-slate-500 font-medium">
              Parent Office: {area.officeName}
            </p>
          </div>
        </div>

        {/* Referential Warning */}
        {hasActiveReferences ? (
          <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 space-y-2">
            <div className="flex items-center gap-2 text-amber-900 font-bold text-xs">
              <ShieldAlert size={16} className="text-amber-600 shrink-0" />
              <span>Active Audit Engagements Reference This Area ({referencingEngagements.length})</span>
            </div>
            <p className="text-[11px] text-amber-800 leading-relaxed">
              Per <strong>MDS-000 §6 / Rule 7</strong>, this audit area cannot be permanently hard-deleted because it is actively scoped into one or more audit engagements in the workspace. Deactivating will mark this area as <strong>Inactive</strong>, preventing new engagement scope selections while preserving existing audit records.
            </p>

            <div className="max-h-32 overflow-y-auto rounded border border-amber-200 bg-white/80 p-2 text-[11px] space-y-1">
              <span className="font-semibold text-slate-600 block text-[10px] uppercase tracking-wider">
                Referencing Engagements:
              </span>
              {referencingEngagements.map((eng) => (
                <div key={eng} className="flex items-center gap-1.5 text-slate-700">
                  <CheckCircle2 size={11} className="text-amber-600 shrink-0" />
                  <span className="truncate font-mono">{eng}</span>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 space-y-1.5">
            <div className="flex items-center gap-1.5 font-bold text-slate-800">
              <AlertTriangle size={15} className="text-amber-500" />
              <span>Deactivation Confirmation</span>
            </div>
            <p className="text-[11px] text-slate-500 leading-snug">
              Deactivating this audit area will mark its status as <strong>Inactive</strong> and exclude it from active scope selectors across AEM.
            </p>
          </div>
        )}
      </div>
    </Modal>
  );
}
