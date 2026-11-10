import { FileText, Lock } from "lucide-react";
import { Select2 } from "../../../../../components/ui/select2";
import { cn } from "../../../../../utils/helpers";
import type { PrepareAeoFormState } from "../prepare-order-types";

type AeoDocumentSectionProps = {
  formState: PrepareAeoFormState;
  errors: Record<string, string>;
  touched: Record<string, boolean>;
  isSubmitted: boolean;
  isReadOnly?: boolean;
  onChangeField: <K extends keyof PrepareAeoFormState>(
    field: K,
    value: PrepareAeoFormState[K],
  ) => void;
  onBlurField: (field: string) => void;
};

export function AeoDocumentSection({
  formState,
  errors,
  touched,
  isSubmitted,
  isReadOnly = false,
  onChangeField,
  onBlurField,
}: AeoDocumentSectionProps) {
  const showError = (field: string) =>
    (touched[field] || isSubmitted) && Boolean(errors[field]);

  const classificationOptions = [
    { value: "Normal", label: "Normal (Standard Departmental Access)" },
    { value: "Restricted", label: "Restricted (Designated Team & Office Head Only)" },
    { value: "Confidential", label: "Confidential (Executive & CIAS Leadership Only)" },
  ];

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <div className="flex items-center gap-1.5">
          <FileText className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Audit Engagement Order (AEO) Body & Directives
          </h3>
        </div>
        <div className="flex items-center gap-2">
          {isReadOnly && (
            <span className="flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">
              <Lock size={11} /> Read-Only ({formState.status})
            </span>
          )}
          <span className="text-[10px] text-slate-400 font-medium">
            MDS-200 §7 AEO Document Entity
          </span>
        </div>
      </div>

      {/* AEO Number & Confidentiality Header Row */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <label className="block">
            <span className="text-xs font-semibold text-slate-700 mb-1 block">
              AEO Document Number / Series
            </span>
            <div className="flex items-center h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-mono font-bold text-blue-700">
              {formState.aeoNumber}
            </div>
          </label>
        </div>

        <div>
          <label className="block">
            <span className="text-xs font-semibold text-slate-700 mb-1 block">
              Confidentiality Classification
            </span>
            <Select2
              value={formState.confidentialityClassification}
              options={classificationOptions}
              disabled={isReadOnly}
              onChange={(val) =>
                onChangeField(
                  "confidentialityClassification",
                  val as "Normal" | "Restricted" | "Confidential",
                )
              }
            />
          </label>
        </div>
      </div>

      {/* Order Title */}
      <div>
        <label className="block">
          <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1">
            <span>
              Official Order Title <span className="text-rose-500">*</span>
            </span>
          </div>
          <input
            type="text"
            disabled={isReadOnly}
            placeholder="e.g. AUDIT ENGAGEMENT ORDER FOR THE INFORMATION SYSTEMS AUDIT OF CITY GENERAL SERVICES OFFICE"
            className={cn(
              "h-10 w-full rounded-lg border bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition",
              isReadOnly && "bg-slate-50 text-slate-700 cursor-not-allowed border-slate-200",
              showError("orderTitle")
                ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                : "border-slate-200 focus:border-blue-500",
            )}
            value={formState.orderTitle}
            onChange={(e) => onChangeField("orderTitle", e.target.value)}
            onBlur={() => onBlurField("orderTitle")}
          />
          {showError("orderTitle") && (
            <p className="mt-1 text-[11px] font-medium text-rose-600">
              {errors.orderTitle}
            </p>
          )}
        </label>
      </div>

      {/* Statutory & Legal Mandate */}
      <div>
        <label className="block">
          <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1">
            <span>
              Statutory & Legal Mandate <span className="text-rose-500">*</span>
            </span>
            <span className="text-[10px] text-slate-400 font-normal">Legal authority basis</span>
          </div>
          <textarea
            rows={2}
            disabled={isReadOnly}
            placeholder="e.g. Pursuant to Executive Order No. 2024-002, Republic Act No. 7160 (Local Government Code of 1991), and the Approved Annual Internal Audit Plan for FY 2026, this Audit Engagement Order is hereby issued..."
            className={cn(
              "w-full min-h-[72px] rounded-lg border bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition",
              isReadOnly && "bg-slate-50 text-slate-700 cursor-not-allowed border-slate-200",
              showError("legalMandate")
                ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                : "border-slate-200 focus:border-blue-500",
            )}
            value={formState.legalMandate}
            onChange={(e) => onChangeField("legalMandate", e.target.value)}
            onBlur={() => onBlurField("legalMandate")}
          />
          {showError("legalMandate") && (
            <p className="mt-1 text-[11px] font-medium text-rose-600">
              {errors.legalMandate}
            </p>
          )}
        </label>
      </div>

      {/* Formal Objectives & Scope Summary 2-Col Grid */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <label className="block">
            <span className="text-xs font-semibold text-slate-700 mb-1 block">
              Formal Audit Objectives <span className="text-rose-500">*</span>
            </span>
            <textarea
              rows={3}
              disabled={isReadOnly}
              placeholder="e.g. Assess statutory compliance, operational efficiency, governance controls, and regulatory compliance..."
              className={cn(
                "w-full min-h-[96px] rounded-lg border bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition",
                isReadOnly && "bg-slate-50 text-slate-700 cursor-not-allowed border-slate-200",
                showError("formalObjectives")
                  ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                  : "border-slate-200 focus:border-blue-500",
              )}
              value={formState.formalObjectives}
              onChange={(e) => onChangeField("formalObjectives", e.target.value)}
              onBlur={() => onBlurField("formalObjectives")}
            />
            {showError("formalObjectives") && (
              <p className="mt-1 text-[11px] font-medium text-rose-600">
                {errors.formalObjectives}
              </p>
            )}
          </label>
        </div>

        <div>
          <label className="block">
            <span className="text-xs font-semibold text-slate-700 mb-1 block">
              Formal Scope Summary <span className="text-rose-500">*</span>
            </span>
            <textarea
              rows={3}
              disabled={isReadOnly}
              placeholder="e.g. Covers designated operational systems, transaction records, and workflows for the designated audit period..."
              className={cn(
                "w-full min-h-[96px] rounded-lg border bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition",
                isReadOnly && "bg-slate-50 text-slate-700 cursor-not-allowed border-slate-200",
                showError("formalScopeSummary")
                  ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                  : "border-slate-200 focus:border-blue-500",
              )}
              value={formState.formalScopeSummary}
              onChange={(e) => onChangeField("formalScopeSummary", e.target.value)}
              onBlur={() => onBlurField("formalScopeSummary")}
            />
            {showError("formalScopeSummary") && (
              <p className="mt-1 text-[11px] font-medium text-rose-600">
                {errors.formalScopeSummary}
              </p>
            )}
          </label>
        </div>
      </div>

      {/* Engagement Directives to Auditee */}
      <div>
        <label className="block">
          <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1">
            <span>
              Specific Directives to Auditee Office <span className="text-rose-500">*</span>
            </span>
            <span className="text-[10px] text-slate-400 font-normal">Cooperation & record access clauses</span>
          </div>
          <textarea
            rows={3}
            disabled={isReadOnly}
            placeholder="e.g. 1. Full cooperation and unhindered access to all relevant files, accounts, facilities, personnel, and electronic systems shall be extended to the designated Audit Team.&#10;2. Designate a primary liaison officer within two (2) working days of receipt of this Order.&#10;3. An Entry Conference shall be scheduled pursuant to CIAS protocol."
            className={cn(
              "w-full min-h-[96px] rounded-lg border bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition",
              isReadOnly && "bg-slate-50 text-slate-700 cursor-not-allowed border-slate-200",
              showError("engagementDirectives")
                ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                : "border-slate-200 focus:border-blue-500",
            )}
            value={formState.engagementDirectives}
            onChange={(e) => onChangeField("engagementDirectives", e.target.value)}
            onBlur={() => onBlurField("engagementDirectives")}
          />
          {showError("engagementDirectives") && (
            <p className="mt-1 text-[11px] font-medium text-rose-600">
              {errors.engagementDirectives}
            </p>
          )}
        </label>
      </div>

      {/* Special Instructions & Target Deliverables */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <label className="block">
            <span className="text-xs font-semibold text-slate-700 mb-1 block">
              Special Instructions & Security Controls
            </span>
            <textarea
              rows={2}
              disabled={isReadOnly}
              placeholder="e.g. All working papers, data extracts, interview records, and audit observations generated during this engagement are confidential and subject to the Data Privacy Act of 2012 and CIAS Information Security Protocols."
              className={cn(
                "w-full min-h-[72px] rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition",
                isReadOnly && "bg-slate-50 text-slate-700 cursor-not-allowed border-slate-200",
              )}
              value={formState.specialInstructions}
              onChange={(e) => onChangeField("specialInstructions", e.target.value)}
            />
          </label>
        </div>

        <div>
          <label className="block">
            <span className="text-xs font-semibold text-slate-700 mb-1 block">
              Target Outputs & Deliverables
            </span>
            <textarea
              rows={2}
              disabled={isReadOnly}
              placeholder="e.g. 1. Audit Engagement Plan (AEP) & Testing Programs&#10;2. Interim Audit Observations & Management Feedback&#10;3. Final Audit Report & Corrective Action Plan (CAP)"
              className={cn(
                "w-full min-h-[72px] rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition",
                isReadOnly && "bg-slate-50 text-slate-700 cursor-not-allowed border-slate-200",
              )}
              value={formState.targetDeliverables}
              onChange={(e) => onChangeField("targetDeliverables", e.target.value)}
            />
          </label>
        </div>
      </div>
    </div>
  );
}
