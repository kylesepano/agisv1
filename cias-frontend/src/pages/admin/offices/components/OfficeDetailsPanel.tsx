import {
  ArrowRight,
  Building2,
  CheckCircle2,
  Pencil,
  Trash2,
  X,
} from "lucide-react";
import { Button } from "../../../../components/ui/button";
import type { OfficeRecord } from "../offices-types";
import { OfficeStatusBadge } from "./OfficesUtils";

export function OfficeDetailsPanel({
  record,
  onEdit,
  onDeactivate,
  onClose,
}: {
  record: OfficeRecord | null;
  onEdit?: (office: OfficeRecord) => void;
  onDeactivate?: (office: OfficeRecord) => void;
  onClose?: () => void;
}) {
  if (!record) {
    return (
      <div className="flex h-full min-h-[300px] flex-col items-center justify-center rounded-lg border border-slate-200 bg-white p-4 text-center shadow-sm">
        <div className="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-slate-400">
          <Building2 size={18} />
        </div>
        <p className="mt-2 text-xs font-bold text-slate-700">No Office Selected</p>
        <p className="mt-0.5 text-[11px] text-slate-400">
          Select any row in the table to inspect details and linked universe coverage.
        </p>
      </div>
    );
  }

  const officeId = record.officeId || record.code;
  const officeName = record.officeName || record.name;
  const officeAcronym = record.officeAcronym || record.shortName;
  const isActive = record.status === "Active";

  return (
    <div className="flex flex-col rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2.5">
      {/* Top Header */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <span className="font-mono text-[13px] font-bold text-blue-700">
            {officeId}
          </span>
          <OfficeStatusBadge status={record.status} />
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

      {/* Office Title Card */}
      <div className="rounded-md bg-slate-50/90 p-2 border border-slate-100">
        <div className="flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wider">
          <span>Auditee Entity</span>
          <span className="text-blue-700 font-bold font-mono text-[10px] bg-blue-50 px-1 rounded border border-blue-200">
            {officeAcronym}
          </span>
        </div>
        <h4 className="mt-0.5 text-xs font-bold text-slate-900 leading-snug">
          {officeName}
        </h4>
        <div className="mt-1 flex items-center gap-1.5 text-[11px] text-slate-500 font-medium">
          <span className="rounded bg-slate-200/70 px-1.5 py-0.2 text-[9px] font-semibold text-slate-700">
            {record.officeCategory || "Line Department"}
          </span>
          <span>•</span>
          <span>MDS-000 §6</span>
        </div>
      </div>

      {/* 2-Column Key Attributes Grid */}
      <div className="grid grid-cols-2 gap-1.5 text-xs">
        {/* Category */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">CATEGORY</span>
          <span className="font-semibold text-slate-800 text-[11px] truncate block mt-0.5">
            {record.officeCategory || "Line Department"}
          </span>
        </div>

        {/* Participation */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">GOVERNANCE</span>
          <span className="font-semibold text-slate-800 text-[11px] truncate block mt-0.5">
            {isActive ? "Active Auditee" : "Deactivated"}
          </span>
        </div>

        {/* Linked Units */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">UNIVERSE COVERAGE</span>
          <span className="font-mono font-bold text-slate-800 text-[11px] block mt-0.5">
            {record.linkedUnits ?? 1} Linked Units
          </span>
        </div>

        {/* Users */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">ASSIGNED USERS</span>
          <span className="font-mono font-bold text-slate-800 text-[11px] block mt-0.5">
            {record.users ?? 5} Personnel
          </span>
        </div>
      </div>

      {/* Office Head & Contact */}
      <div className="space-y-1 text-xs">
        <div className="rounded-md bg-slate-50/60 p-2 border border-slate-100 text-[11px] space-y-1">
          <div className="flex items-center justify-between">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
              Department Head
            </span>
          </div>
          <p className="font-bold text-slate-900 leading-snug">
            {record.head || "Not Designated"}
          </p>
          {record.designation && (
            <p className="text-slate-500 font-medium text-[10px]">
              {record.designation}
            </p>
          )}
          {record.contactEmail && (
            <p className="font-mono text-slate-600 text-[10px] pt-0.5">
              {record.contactEmail}
            </p>
          )}
        </div>
      </div>

      {/* Description */}
      {record.description && (
        <div className="rounded-md bg-slate-50/60 p-2 border border-slate-100 text-[11px]">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
            Mandate & Operating Scope
          </span>
          <p className="mt-0.5 text-slate-700 leading-snug line-clamp-3">
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
            <span>Edit Office Profile</span>
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
                <span>Deactivate Office (Soft Delete)</span>
              </>
            ) : (
              <>
                <CheckCircle2 size={13} />
                <span>Reactivate Office</span>
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
