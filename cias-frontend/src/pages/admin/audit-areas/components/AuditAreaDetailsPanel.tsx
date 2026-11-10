import {
  ArrowRight,
  Building2,
  CheckCircle2,
  Layers,
  Pencil,
  Trash2,
  X,
} from "lucide-react";
import { Button } from "../../../../components/ui/button";
import type { AuditAreaRecord } from "../audit-areas-types";
import { AuditAreaStatusBadge } from "./AuditAreasUtils";

export function AuditAreaDetailsPanel({
  record,
  onEdit,
  onDeactivate,
  onClose,
}: {
  record: AuditAreaRecord | null;
  onEdit?: (area: AuditAreaRecord) => void;
  onDeactivate?: (area: AuditAreaRecord) => void;
  onClose?: () => void;
}) {
  if (!record) {
    return (
      <div className="flex h-full min-h-[300px] flex-col items-center justify-center rounded-lg border border-slate-200 bg-white p-4 text-center shadow-sm">
        <div className="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-slate-400">
          <Layers size={18} />
        </div>
        <p className="mt-2 text-xs font-bold text-slate-700">No Audit Area Selected</p>
        <p className="mt-0.5 text-[11px] text-slate-400">
          Select any row in the table to inspect coverage scope and parent auditee details.
        </p>
      </div>
    );
  }

  const areaId = record.auditAreaId || record.code || record.id;
  const areaName = record.auditAreaName || record.name;
  const officeName = record.officeName;
  const isActive = record.status === "Active";

  return (
    <div className="flex flex-col rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2.5">
      {/* Top Header */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <span className="font-mono text-[13px] font-bold text-blue-700">
            {areaId}
          </span>
          <AuditAreaStatusBadge status={record.status} />
        </div>

        {onClose && (
          <button
            aria-label="Close details"
            className="grid h-6 w-6 place-items-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
            type="button"
            onClick={onClose}
          >
            <X size={14} />
          </button>
        )}
      </div>

      {/* Audit Area Title Card */}
      <div className="rounded-md bg-slate-50/90 p-2 border border-slate-100">
        <div className="flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wider">
          <span>Audit Universe Area</span>
          <span className="text-slate-500 font-mono text-[9px]">{record.auditAreaCategory}</span>
        </div>
        <h4 className="mt-0.5 text-xs font-bold text-slate-900 leading-snug">
          {areaName}
        </h4>
        <div className="mt-1 flex items-center gap-1.5 text-[11px] text-slate-600 font-medium truncate">
          <Building2 size={12} className="text-slate-400 shrink-0" />
          <span className="truncate">{officeName || "Unassigned Office"}</span>
        </div>
      </div>

      {/* 2-Column Key Attributes Grid */}
      <div className="grid grid-cols-2 gap-1.5 text-xs">
        {/* Parent Office */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">PARENT OFFICE</span>
          <span className="font-semibold text-slate-800 text-[11px] truncate block mt-0.5" title={officeName}>
            {officeName || "—"}
          </span>
        </div>

        {/* Category */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">CATEGORY</span>
          <span className="font-semibold text-slate-800 text-[11px] truncate block mt-0.5">
            {record.auditAreaCategory}
          </span>
        </div>

        {/* Hierarchy Rule */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">GOVERNANCE</span>
          <span className="font-semibold text-slate-800 text-[11px] block mt-0.5">
            MDS-000 Rule 7
          </span>
        </div>

        {/* Availability */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">AVAILABILITY</span>
          <span className="font-semibold text-slate-800 text-[11px] block mt-0.5">
            {isActive ? "Available in Scope" : "Deactivated"}
          </span>
        </div>
      </div>

      {/* Scope / Coverage Description */}
      {record.description && (
        <div className="rounded-md bg-slate-50/60 p-2 border border-slate-100 text-[11px]">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
            Coverage Scope & Controls Focus
          </span>
          <p className="mt-0.5 text-slate-700 leading-snug">
            {record.description}
          </p>
        </div>
      )}

      {/* Action CTA Buttons */}
      <div className="pt-1 space-y-2">
        {onEdit && (
          <Button
            variant="outline"
            className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 shadow-2xs flex items-center justify-center gap-2 transition active:scale-[0.99]"
            onClick={() => onEdit(record)}
          >
            <Pencil size={13} className="text-blue-600" />
            <span>Edit Audit Area</span>
            <ArrowRight size={13} className="text-slate-400" />
          </Button>
        )}

        {onDeactivate && (
          <Button
            className={
              isActive
                ? "h-9 w-full rounded-lg border border-rose-200 bg-white hover:bg-rose-50 text-xs font-semibold text-rose-700 shadow-2xs flex items-center justify-center gap-2 transition active:scale-[0.99]"
                : "h-9 w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-xs font-semibold text-white shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99]"
            }
            onClick={() => onDeactivate(record)}
          >
            {isActive ? (
              <>
                <Trash2 size={13} />
                <span>Deactivate Audit Area (Soft Delete)</span>
              </>
            ) : (
              <>
                <CheckCircle2 size={13} />
                <span>Reactivate Audit Area</span>
              </>
            )}
          </Button>
        )}

        <div className="mt-2 flex items-center justify-between text-[10px] text-slate-400 px-0.5">
          <span>Created: {record.createdAt || "2026-01-01"}</span>
          <span>Updated: {record.updatedAt || "2026-01-15"}</span>
        </div>
      </div>
    </div>
  );
}
