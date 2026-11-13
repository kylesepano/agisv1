import {
  Calendar,
  FileText,
  Lock,
} from "lucide-react";
import {
  auditTypeOptions,
  authorizationDecisionOptions,
  confidentialityOptions,
  plannedAuthorityChips,
  plannedDirectingAuthoritySuggestions,
  priorityOptions,
  unplannedAuthorityChips,
  unplannedDirectingAuthoritySuggestions,
} from "../create-engagement-data";
import type {
  AuditTypeOption,
  CreateEngagementFormState,
  EngagementConfidentiality,
  EngagementPriority,
} from "../create-engagement-types";
import { Select2 } from "../../../../../components/ui/select2";
import { cn } from "../../../../../utils/helpers";

type EngagementFormSectionProps = {
  formState: CreateEngagementFormState;
  errors: Record<string, string>;
  touched: Record<string, boolean>;
  isSubmitted: boolean;
  onChangeField: <K extends keyof CreateEngagementFormState>(
    field: K,
    value: CreateEngagementFormState[K],
  ) => void;
  onBlurField: (field: string) => void;
};

export function EngagementFormSection({
  formState,
  errors,
  touched,
  isSubmitted,
  onChangeField,
  onBlurField,
}: EngagementFormSectionProps) {
  const auditTypeSelectOptions = auditTypeOptions.map((opt) => ({
    value: opt.value,
    label: opt.label,
  }));

  const prioritySelectOptions = priorityOptions.map((opt) => ({
    value: opt.value,
    label: opt.label,
  }));

  const confidentialitySelectOptions = confidentialityOptions.map((opt) => ({
    value: opt.value,
    label: opt.label,
  }));

  const isPlanned = formState.originType === "Planned";
  const authorityChips = isPlanned ? plannedAuthorityChips : unplannedAuthorityChips;
  const directingSuggestions = isPlanned
    ? plannedDirectingAuthoritySuggestions
    : unplannedDirectingAuthoritySuggestions;
  const authorityPlaceholder = isPlanned
    ? "e.g. AAP-2025-001 / IAP-2026-001"
    : "e.g. SP Resolution No. 2026-088";
  const directingPlaceholder = isPlanned
    ? "e.g. Annual Internal Audit Plan (IAP-2026 Mandate)"
    : "e.g. City Mayor / Sangguniang Panlungsod";

  const showError = (field: string) =>
    (touched[field] || isSubmitted) && Boolean(errors[field]);

  return (
    <div className="space-y-3">
      {/* Parameters Card */}
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
        <div className="flex items-center justify-between border-b border-slate-100 pb-2.5">
          <div className="flex items-center gap-1.5">
            <FileText className="h-4 w-4 text-blue-600" />
            <h3 className="text-[13px] font-bold text-slate-800">
              Audit Engagement Mandate & Parameters
            </h3>
          </div>
          <span className="text-[10px] text-slate-400 font-medium hidden sm:inline">
            MDS-200 §7 Entity Fields
          </span>
        </div>

        {/* Title */}
        <div>
          <label className="block">
            <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1.5">
              <span className="flex items-center gap-1">
                Engagement Title / Audit Area Description
                <span className="text-rose-500">*</span>
              </span>
            </div>
            <input
              type="text"
              className={cn(
                "h-10 w-full rounded-lg border bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition",
                showError("title")
                  ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                  : "border-slate-200 focus:border-blue-500",
              )}
              placeholder="e.g. Audit of Cash and Revenue Collection Management System"
              value={formState.title}
              onChange={(e) => onChangeField("title", e.target.value)}
              onBlur={() => onBlurField("title")}
            />
            {showError("title") && (
              <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                {errors.title}
              </p>
            )}
          </label>
        </div>

        {/* Audit Type & Directing Authority */}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {/* Audit Type */}
          <div>
            <label className="block">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1.5">
                <span className="flex items-center gap-1">
                  Audit Type
                  <span className="text-rose-500">*</span>
                </span>
                <span className="text-[10px] text-slate-400 font-normal">Reference list</span>
              </div>
              <Select2
                value={formState.auditType}
                options={auditTypeSelectOptions}
                placeholder="Select audit type..."
                onChange={(val) => {
                  onChangeField("auditType", val as AuditTypeOption);
                  onBlurField("auditType");
                }}
              />
              {showError("auditType") && (
                <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                  {errors.auditType}
                </p>
              )}
            </label>
          </div>

          {/* Directing / Requesting Authority */}
          <div>
            <label className="block">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1.5">
                <span className="flex items-center gap-1">
                  Directing / Requesting Authority
                  {isPlanned ? (
                    <span className="text-slate-400 text-[10px] font-normal ml-1">(auto-filled from IAP)</span>
                  ) : (
                    <span className="text-rose-500">*</span>
                  )}
                </span>
                <span className="text-[10px] text-slate-400 font-normal">Originating body</span>
              </div>

              {isPlanned ? (
                <div
                  className={cn(
                    "flex items-center justify-between h-10 w-full rounded-lg border px-3 text-xs bg-slate-50 border-slate-200 text-slate-700 font-medium select-none",
                    !formState.iapSourceId && "text-slate-400 font-normal italic",
                  )}
                >
                  <span className="truncate">
                    {formState.directingAuthority || "Select an approved IAP item above..."}
                  </span>
                  <span className="inline-flex items-center gap-1 rounded bg-slate-200/80 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 shrink-0">
                    <Lock size={10} className="text-slate-500" />
                    Locked
                  </span>
                </div>
              ) : (
                <>
                  <input
                    type="text"
                    className={cn(
                      "h-10 w-full rounded-lg border bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition",
                      showError("directingAuthority")
                        ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                        : "border-slate-200 focus:border-blue-500",
                    )}
                    placeholder={directingPlaceholder}
                    value={formState.directingAuthority}
                    onChange={(e) => onChangeField("directingAuthority", e.target.value)}
                    onBlur={() => onBlurField("directingAuthority")}
                  />
                  {showError("directingAuthority") && (
                    <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                      {errors.directingAuthority}
                    </p>
                  )}
                  <div className="mt-1.5 flex flex-wrap items-center gap-1">
                    <span className="text-[10px] text-slate-400">Suggestions:</span>
                    {directingSuggestions.slice(0, 3).map((sug) => (
                      <button
                        key={sug}
                        type="button"
                        onClick={() => {
                          onChangeField("directingAuthority", sug);
                          onBlurField("directingAuthority");
                        }}
                        className="rounded bg-slate-100 hover:bg-slate-200 px-1.5 py-0.5 text-[10px] text-slate-600 font-medium transition"
                      >
                        {sug.split("/")[0]}
                      </button>
                    ))}
                  </div>
                </>
              )}
            </label>
          </div>
        </div>

        {/* Authority Reference & Date Received */}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {/* Authority Reference */}
          <div>
            <label className="block">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1.5">
                <span className="flex items-center gap-1">
                  Authority Reference
                  {isPlanned ? (
                    <span className="text-slate-400 text-[10px] font-normal ml-1">(auto-filled from IAP)</span>
                  ) : (
                    <span className="text-rose-500">*</span>
                  )}
                </span>
                <span className="text-[10px] text-slate-400 font-normal">
                  {isPlanned ? "Plan Item Reference" : "Resolution / Order No."}
                </span>
              </div>

              {isPlanned ? (
                <div
                  className={cn(
                    "flex items-center justify-between h-10 w-full rounded-lg border px-3 text-xs bg-slate-50 border-slate-200 text-slate-700 font-medium select-none",
                    !formState.iapSourceId && "text-slate-400 font-normal italic",
                  )}
                >
                  <span className="truncate">
                    {formState.authorityReference || "Select an approved IAP item above..."}
                  </span>
                  <span className="inline-flex items-center gap-1 rounded bg-slate-200/80 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 shrink-0">
                    <Lock size={10} className="text-slate-500" />
                    Locked
                  </span>
                </div>
              ) : (
                <>
                  <input
                    type="text"
                    className={cn(
                      "h-10 w-full rounded-lg border bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition",
                      showError("authorityReference")
                        ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                        : "border-slate-200 focus:border-blue-500",
                    )}
                    placeholder={authorityPlaceholder}
                    value={formState.authorityReference}
                    onChange={(e) => onChangeField("authorityReference", e.target.value)}
                    onBlur={() => onBlurField("authorityReference")}
                  />
                  {showError("authorityReference") && (
                    <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                      {errors.authorityReference}
                    </p>
                  )}
                  <div className="mt-1.5 flex flex-wrap items-center gap-1">
                    <span className="text-[10px] text-slate-400">Quick fill:</span>
                    {authorityChips.map((chip) => (
                      <button
                        key={chip.label}
                        type="button"
                        onClick={() => {
                          onChangeField("authorityReference", chip.value);
                          onBlurField("authorityReference");
                        }}
                        className="rounded bg-slate-100 hover:bg-slate-200 px-1.5 py-0.5 text-[10px] text-slate-600 font-medium transition"
                      >
                        {chip.label}
                      </button>
                    ))}
                  </div>
                </>
              )}
            </label>
          </div>

          {/* Date Received */}
          <div>
            <label className="block">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1.5">
                <span className="flex items-center gap-1">
                  Date Received
                  {isPlanned ? (
                    <span className="text-slate-400 text-[10px] font-normal ml-1">(auto-filled from IAP)</span>
                  ) : (
                    <span className="text-rose-500">*</span>
                  )}
                </span>
                <span className="text-[10px] text-slate-400 font-normal">
                  {isPlanned ? "Plan Approval Date" : "Directive Received Date"}
                </span>
              </div>

              {isPlanned ? (
                <div
                  className={cn(
                    "flex items-center justify-between h-10 w-full rounded-lg border px-3 text-xs bg-slate-50 border-slate-200 text-slate-700 font-medium select-none",
                    !formState.iapSourceId && "text-slate-400 font-normal italic",
                  )}
                >
                  <span className="truncate">
                    {formState.dateReceived || "Select an approved IAP item above..."}
                  </span>
                  <span className="inline-flex items-center gap-1 rounded bg-slate-200/80 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 shrink-0">
                    <Lock size={10} className="text-slate-500" />
                    Locked
                  </span>
                </div>
              ) : (
                <>
                  <input
                    type="date"
                    className={cn(
                      "h-10 w-full rounded-lg border bg-white px-2.5 text-xs text-slate-900 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition",
                      showError("dateReceived")
                        ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                        : "border-slate-200 focus:border-blue-500",
                    )}
                    value={formState.dateReceived}
                    onChange={(e) => onChangeField("dateReceived", e.target.value)}
                    onBlur={() => onBlurField("dateReceived")}
                  />
                  {showError("dateReceived") && (
                    <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                      {errors.dateReceived}
                    </p>
                  )}
                  <p className="mt-1.5 text-[10px] text-slate-400">
                    Date the SP Resolution, Executive Order, or Directive was received.
                  </p>
                </>
              )}
            </label>
          </div>
        </div>

        {/* MDS-200 §7 Authorization Decision (Unplanned Engagements Only) */}
        {/* MDS-200 §7 lists 'Authorization Decision' as a distinct field on the Audit Engagement entity to record how/whether the Directing/Requesting Authority actually approved this unplanned engagement (distinct from the Authority Reference citation document). Planned engagements inherit authorization directly from the approved IAP item. */}
        {!isPlanned && (
          <div>
            <label className="block">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1.5">
                <span className="flex items-center gap-1">
                  Authorization Decision
                  <span className="text-rose-500">*</span>
                  <span className="text-[10px] text-indigo-600 font-normal bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100 font-mono">
                    MDS-200 §7
                  </span>
                </span>
                <span className="text-[10px] text-slate-400 font-normal">Unplanned approval basis</span>
              </div>
              <input
                type="text"
                className={cn(
                  "h-10 w-full rounded-lg border bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition",
                  showError("authorizationDecision")
                    ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                    : "border-slate-200 focus:border-blue-500",
                )}
                placeholder="e.g. Approved by Sanggunian Resolution / Special Executive Order Signed"
                value={formState.authorizationDecision || ""}
                onChange={(e) => onChangeField("authorizationDecision", e.target.value)}
                onBlur={() => onBlurField("authorizationDecision")}
              />
              {showError("authorizationDecision") && (
                <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                  {errors.authorizationDecision}
                </p>
              )}
            </label>
            <div className="mt-1.5 flex flex-wrap items-center gap-1">
              <span className="text-[10px] text-slate-400">Quick fill:</span>
              {authorizationDecisionOptions.map((opt) => (
                <button
                  key={opt.value}
                  type="button"
                  onClick={() => {
                    onChangeField("authorizationDecision", opt.value);
                    onBlurField("authorizationDecision");
                  }}
                  className="rounded bg-slate-100 hover:bg-slate-200 px-1.5 py-0.5 text-[10px] text-slate-600 font-medium transition"
                >
                  {opt.label}
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Execution Window (Start & End Date) */}
        <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2">
          <div className="flex items-center gap-1.5">
            <Calendar className="h-3.5 w-3.5 text-blue-600" />
            <span className="text-xs font-bold text-slate-800">
              Period Covered (Execution Window)
            </span>
            <span className="text-rose-500 text-xs">*</span>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <span className="text-[11px] font-semibold text-slate-600 mb-1 block">
                Start Date
              </span>
              <input
                type="date"
                className={cn(
                  "h-10 w-full rounded-lg border bg-white px-2.5 text-xs text-slate-900 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition",
                  showError("startDate") ? "border-rose-400" : "border-slate-200",
                )}
                value={formState.startDate}
                onChange={(e) => onChangeField("startDate", e.target.value)}
                onBlur={() => onBlurField("startDate")}
              />
              {showError("startDate") && (
                <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                  {errors.startDate}
                </p>
              )}
            </div>

            <div>
              <span className="text-[11px] font-semibold text-slate-600 mb-1 block">
                Target Completion End Date
              </span>
              <input
                type="date"
                className={cn(
                  "h-10 w-full rounded-lg border bg-white px-2.5 text-xs text-slate-900 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition",
                  showError("endDate") ? "border-rose-400" : "border-slate-200",
                )}
                value={formState.endDate}
                onChange={(e) => onChangeField("endDate", e.target.value)}
                onBlur={() => onBlurField("endDate")}
              />
              {showError("endDate") && (
                <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                  {errors.endDate}
                </p>
              )}
            </div>
          </div>
        </div>

        {/* Priority & Confidentiality */}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className="block">
              <span className="text-xs font-semibold text-slate-700 mb-1.5 block">
                Priority Level
              </span>
              <Select2
                value={formState.priority}
                options={prioritySelectOptions}
                placeholder="Select priority..."
                onChange={(val) => onChangeField("priority", val as EngagementPriority)}
              />
            </label>
          </div>

          <div>
            <label className="block">
              <span className="text-xs font-semibold text-slate-700 mb-1.5 block">
                Confidentiality Level
              </span>
              <Select2
                value={formState.confidentiality}
                options={confidentialitySelectOptions}
                placeholder="Select confidentiality..."
                onChange={(val) => onChangeField("confidentiality", val as EngagementConfidentiality)}
              />
            </label>
          </div>
        </div>
      </div>
    </div>
  );
}
