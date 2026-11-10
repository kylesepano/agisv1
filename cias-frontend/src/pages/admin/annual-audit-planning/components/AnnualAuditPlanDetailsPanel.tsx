import {
  ArrowRight,
  Building2,
  CalendarDays,
  ClipboardList,
  Eye,
  Pencil,
  ShieldAlert,
  X,
} from "lucide-react";
import { Button } from "../../../../components/ui/button";
import type { AnnualAuditPlanRecord } from "../annual-audit-planning-types";
import { formatAuditScheduleDate } from "../annual-audit-planning-utils";
import {
  AnnualAuditPlanCategoryBadge,
  AnnualAuditPlanRiskBadge,
} from "./AnnualAuditPlanBadges";
import { PlanStatusBadge } from "./PlanStatusBadge";

export function AnnualAuditPlanDetailsPanel({
  record,
  onView,
  onEdit,
  onClose,
}: {
  record: AnnualAuditPlanRecord | null;
  onView?: (plan: AnnualAuditPlanRecord) => void;
  onEdit?: (plan: AnnualAuditPlanRecord) => void;
  onClose?: () => void;
}) {
  if (!record) {
    return (
      <div className="flex h-full min-h-[300px] flex-col items-center justify-center rounded-lg border border-slate-200 bg-white p-4 text-center shadow-sm">
        <div className="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-slate-400">
          <ClipboardList size={18} />
        </div>
        <p className="mt-2 text-xs font-bold text-slate-700">No Plan Item Selected</p>
        <p className="mt-0.5 text-[11px] text-slate-400">
          Select any row in the table to inspect planning parameters, timelines, and risk rating.
        </p>
      </div>
    );
  }

  return (
    <div className="flex flex-col rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2.5">
      {/* Top Header */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <span className="font-mono text-[13px] font-bold text-blue-700">
            {record.code}
          </span>
          <PlanStatusBadge status={record.status} />
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

      {/* Plan Item Title Card */}
      <div className="rounded-md bg-slate-50/90 p-2 border border-slate-100">
        <div className="flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wider">
          <span>Annual Audit Plan Item</span>
          <span className="text-slate-500 font-mono text-[9px]">FY {record.year} • {record.quarter}</span>
        </div>
        <h4 className="mt-0.5 text-xs font-bold text-slate-900 leading-snug">
          {record.subject}
        </h4>
        <div className="mt-1 flex items-center gap-1.5 text-[11px] text-slate-600 font-medium truncate">
          <Building2 size={12} className="text-slate-400 shrink-0" />
          <span className="truncate">{record.office}</span>
        </div>
      </div>

      {/* 2-Column Key Attributes Grid */}
      <div className="grid grid-cols-2 gap-1.5 text-xs">
        {/* Category */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">CATEGORY</span>
          <div className="mt-0.5">
            <AnnualAuditPlanCategoryBadge category={record.category} />
          </div>
        </div>

        {/* Risk Rating */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">RISK RATING</span>
          <div className="mt-0.5">
            <AnnualAuditPlanRiskBadge rating={record.riskRating} />
          </div>
        </div>

        {/* Target Timeline */}
        <div className="col-span-2 rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">PLANNED AUDIT PERIOD</span>
          <div className="mt-0.5 flex items-center gap-1.5 text-[11px] font-medium text-slate-800">
            <CalendarDays size={12} className="text-slate-400 shrink-0" />
            <span>{formatAuditScheduleDate(record.planStart)} – {formatAuditScheduleDate(record.planEnd)}</span>
          </div>
        </div>

        {/* Governance / Mandate */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">MANDATE SOURCE</span>
          <span className="font-semibold text-slate-800 text-[11px] block mt-0.5">
            Approved IAP Plan
          </span>
        </div>

        {/* Execution Status */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">ACTIVATION STATUS</span>
          <span className="font-semibold text-slate-800 text-[11px] block mt-0.5">
            {record.status === "Completed" ? "Audit Finalized" : record.status === "In Progress" ? "In Execution (AEM)" : "Pending Scoping"}
          </span>
        </div>
      </div>

      {/* Scope / Strategic Context Card */}
      <div className="rounded-md bg-slate-50/60 p-2 border border-slate-100 text-[11px]">
        <div className="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
          <ShieldAlert size={12} className="text-blue-600" />
          <span>Strategic Alignment & Scope</span>
        </div>
        <p className="mt-0.5 text-slate-700 leading-snug">
          Statutory internal audit coverage scheduled under the Approved Annual Internal Audit Plan for {record.office}, prioritizing {record.category.toLowerCase()} controls and {record.riskRating.toLowerCase()} risk areas.
        </p>
      </div>

      {/* Action CTA Buttons */}
      <div className="pt-1 space-y-2">
        {onView && (
          <Button
            variant="outline"
            className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 shadow-2xs flex items-center justify-center gap-2 transition active:scale-[0.99]"
            onClick={() => onView(record)}
          >
            <Eye size={13} className="text-blue-600" />
            <span>View Full Plan Details</span>
            <ArrowRight size={13} className="text-slate-400" />
          </Button>
        )}

        {onEdit && (
          <Button
            variant="outline"
            className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 shadow-2xs flex items-center justify-center gap-2 transition active:scale-[0.99]"
            onClick={() => onEdit(record)}
          >
            <Pencil size={13} className="text-slate-600" />
            <span>Edit Plan Parameters</span>
          </Button>
        )}

        <div className="mt-2 flex items-center justify-between text-[10px] text-slate-400 px-0.5">
          <span>Audit Year: FY {record.year}</span>
          <span>Quarter: {record.quarter}</span>
        </div>
      </div>
    </div>
  );
}
