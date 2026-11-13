import { Layers, ShieldCheck, XCircle } from "lucide-react";

type TeamSufficiencySectionProps = {
  auditAreas: string[];
  isConfirmed: boolean;
  justification: string;
  isReadOnly?: boolean;
  onToggleConfirm: (confirmed: boolean) => void;
  onUpdateJustification: (text: string) => void;
};

export function TeamSufficiencySection({
  auditAreas,
  isConfirmed,
  justification,
  isReadOnly = false,
  onToggleConfirm,
  onUpdateJustification,
}: TeamSufficiencySectionProps) {
  const justLen = (justification || "").trim().length;
  const isJustValid = justLen >= 20;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <ShieldCheck className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Collective Team Sufficiency Assessment (§9.1 AEM-FR-008)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-medium">
          Whole-Team Scope Coverage
        </span>
      </div>

      <div className="space-y-2 text-xs">
        <div className="rounded-md border border-blue-100 bg-blue-50/50 p-2.5 space-y-1">
          <div className="flex items-center gap-1.5 font-bold text-blue-950">
            <Layers size={13} className="text-blue-600" />
            <span>Target Audit Scope Coverage:</span>
          </div>
          <p className="text-[11px] text-blue-900 leading-snug">
            {auditAreas.length > 0
              ? auditAreas.join(" • ")
              : "Engagement scope boundaries & focus areas"}
          </p>
        </div>

        <div className="space-y-1">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-semibold text-slate-700 flex items-center gap-1">
              <span>Collective Competency & Sufficiency Justification</span>
              <span className="text-rose-500">*</span>
            </label>
            <span
              className={`text-[10px] px-1.5 py-0.2 rounded border font-mono ${
                isJustValid || isReadOnly
                  ? "text-emerald-700 bg-emerald-50 border-emerald-200"
                  : "text-amber-700 bg-amber-50 border-amber-200"
              }`}
            >
              {justLen}/20 chars min
            </span>
          </div>
          <textarea
            rows={3}
            value={justification}
            disabled={isReadOnly}
            onChange={(e) => onUpdateJustification(e.target.value)}
            placeholder="e.g. The designated team collectively possesses the required legal knowledge (RA 9184), internal controls, civil engineering, and forensic auditing qualifications..."
            className={`w-full rounded border p-2.5 text-xs text-slate-800 leading-relaxed focus:outline-hidden disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed resize-none ${
              !isJustValid && justLen > 0 && !isReadOnly
                ? "border-amber-300 bg-amber-50/20"
                : !isJustValid && !isReadOnly
                ? "border-slate-200 bg-white"
                : "border-emerald-300 bg-white focus:border-emerald-500"
            }`}
            required
          />
          {!isJustValid && !isReadOnly && (
            <p className="text-[10px] text-amber-700">
              Explain how the combined qualifications of all team members are collectively sufficient to execute the audit scope (min. 20 chars).
            </p>
          )}
        </div>

        {/* Formal Affirmation Checkbox */}
        <label
          className={`flex items-start gap-2.5 rounded-lg border p-2.5 transition ${
            isReadOnly
              ? "border-emerald-200 bg-emerald-50/50 cursor-default"
              : isConfirmed
              ? "border-emerald-300 bg-emerald-50/70 cursor-pointer"
              : "border-rose-200 bg-rose-50/40 hover:bg-rose-50 cursor-pointer"
          }`}
        >
          <input
            type="checkbox"
            checked={isConfirmed}
            disabled={isReadOnly}
            onChange={(e) => onToggleConfirm(e.target.checked)}
            className={`mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 ${
              isReadOnly ? "cursor-not-allowed" : "cursor-pointer"
            }`}
          />
          <div className="space-y-0.5 text-xs">
            <span className={`font-bold block ${isConfirmed || isReadOnly ? "text-emerald-950" : "text-rose-950"}`}>
              Formal Collective Team Sufficiency Affirmation *
            </span>
            <p className={`text-[11px] leading-snug ${isConfirmed || isReadOnly ? "text-emerald-900" : "text-rose-900"}`}>
              I formally affirm that the designated audit team collectively possesses the requisite skills, technical knowledge, experience, and authority to satisfy the audit objectives and evaluate all tagged audit focus areas.
            </p>
            {!isConfirmed && !isReadOnly && (
              <p className="text-[10px] text-rose-700 font-semibold pt-0.5 flex items-center gap-1">
                <XCircle size={11} className="shrink-0" />
                <span>Affirmation is mandatory before saving team assignment.</span>
              </p>
            )}
          </div>
        </label>
      </div>
    </div>
  );
}
