import { ArrowRight, FileText, Layers, Users, X } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { navigateTo } from "../../../../routes";
import type { AuditEngagementRecord } from "../audit-engagement-types";
import { formatEngagementDate } from "../audit-engagement-utils";
import { EngagementAuditTypeBadge, EngagementRiskBadge } from "./EngagementBadges";
import { EngagementStatusBadge } from "./EngagementStatusBadge";

export function EngagementDetailsPanel({
  record,
  onClose,
}: {
  record: AuditEngagementRecord | null;
  onClose?: () => void;
}) {
  if (!record) {
    return (
      <div className="flex h-full min-h-[300px] flex-col items-center justify-center rounded-lg border border-slate-200 bg-white p-4 text-center shadow-sm">
        <div className="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-slate-400">
          <FileText size={18} />
        </div>
        <p className="mt-2 text-xs font-bold text-slate-700">No Engagement Selected</p>
        <p className="mt-0.5 text-[11px] text-slate-400">
          Select any row in the table to preview scope and parameters.
        </p>
      </div>
    );
  }

  const handleOpenDefineScope = () => {
    navigateTo(`/admin/audit-engagement/${record.code}/define-scope`);
  };

  return (
    <div className="flex flex-col rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2.5">
      {/* Top Header */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => navigateTo(`/admin/audit-engagement/${record.code}`)}
            className="font-mono text-[13px] font-bold text-blue-700 hover:text-blue-900 hover:underline cursor-pointer"
            title="Open Full Engagement Workspace (SCR-220)"
          >
            {record.code}
          </button>
          <EngagementStatusBadge status={record.status} showDot />
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
          <span>Audit Area</span>
          <span className="text-slate-500 font-mono text-[9px]">{record.year}</span>
        </div>
        <h4 className="mt-0.5 text-xs font-bold text-slate-900 leading-snug">
          {record.auditArea || record.subject}
        </h4>
        <p className="mt-0.5 text-[11px] text-slate-600 truncate font-medium">
          {record.office}
        </p>
      </div>

      {/* 2-Column Key Attributes Grid */}
      <div className="grid grid-cols-2 gap-1.5 text-xs">
        {/* Annual Plan Ref */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">PLAN REF</span>
          <span className="font-mono font-bold text-slate-800 text-[11px] truncate block">
            {record.annualPlanRef || "Unplanned"}
          </span>
        </div>

        {/* Audit Type */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">AUDIT TYPE</span>
          <div className="mt-0.5 truncate">
            <EngagementAuditTypeBadge auditType={record.auditType || record.category || "Operations Audit"} />
          </div>
        </div>

        {/* Risk Rating (OD-003) */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">RISK LEVEL</span>
          <div className="mt-0.5">
            <EngagementRiskBadge rating={record.riskRating} showProvisional />
          </div>
        </div>

        {/* Lead Auditor */}
        <div className="rounded-md bg-slate-50/50 p-1.5 border border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400 block">LEAD AUDITOR</span>
          <span className="font-semibold text-slate-800 text-[11px] truncate block mt-0.5">
            {record.leadAuditor}
          </span>
        </div>
      </div>

      {/* Period & Team Row */}
      <div className="space-y-1 text-xs">
        <div className="flex items-center justify-between rounded-md bg-slate-50/50 px-2 py-1 border border-slate-100 text-[11px]">
          <span className="font-semibold text-slate-500">Period:</span>
          <span className="font-mono text-slate-700 font-medium text-[10px]">
            {formatEngagementDate(record.startDate)} ~ {formatEngagementDate(record.endDate)}
          </span>
        </div>

        <div className="flex items-start gap-1.5 rounded-md bg-slate-50/50 p-1.5 border border-slate-100 text-[11px]">
          <Users size={13} className="text-slate-400 shrink-0 mt-0.5" />
          <div className="min-w-0 flex-1">
            <span className="text-[10px] font-semibold text-slate-400 block">AUDIT TEAM</span>
            <span className="text-slate-700 text-[11px] truncate block">
              {record.auditTeam}
            </span>
          </div>
        </div>
      </div>

      {/* Objectives & Scope Brief */}
      <div className="space-y-1.5 rounded-md bg-slate-50/60 p-2 border border-slate-100 text-[11px]">
        <div>
          <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
            Objective
          </span>
          <p className="mt-0.5 text-slate-700 leading-snug line-clamp-2">
            {record.objective}
          </p>
        </div>

        {record.remarks && (
          <div className="border-t border-slate-200/60 pt-1">
            <span className="text-[10px] font-bold text-blue-700 uppercase tracking-wider block">
              Remarks
            </span>
            <p className="mt-0.5 text-slate-600 leading-snug line-clamp-2">
              {record.remarks}
            </p>
          </div>
        )}
      </div>

      {/* Action CTA Button */}
      <div className="pt-1 space-y-2">
        <Button
          variant="outline"
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 shadow-2xs flex items-center justify-center gap-2 transition active:scale-[0.99]"
          onClick={() => navigateTo(`/admin/audit-engagement/${record.code}`)}
        >
          <FileText size={14} className="text-blue-600" />
          <span>Open Full Workspace (SCR-220)</span>
          <ArrowRight size={13} className="text-slate-400" />
        </Button>

        <Button
          className="h-10 w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-xs font-semibold text-white shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99]"
          onClick={handleOpenDefineScope}
        >
          <Layers size={14} />
          <span>Define Scope (SCR-212)</span>
          <ArrowRight size={14} />
        </Button>

        <div className="mt-2 flex items-center justify-between text-[10px] text-slate-400 px-0.5">
          <span>Updated: {record.lastUpdated}</span>
          <span>By: {record.updatedBy}</span>
        </div>
      </div>
    </div>
  );
}
