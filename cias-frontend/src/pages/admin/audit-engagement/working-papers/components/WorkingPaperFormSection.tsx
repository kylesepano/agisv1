import {
  CheckCircle2,
  ExternalLink,
  FileCheck2,
  FileSpreadsheet,
  FileText,
  FolderLock,
  Paperclip,
  Plus,
  ShieldAlert,
  Trash2,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type {
  WorkingPaper,
  WorkingPaperEvidenceLink,
  WorkingPaperType,
} from "../working-papers-types";

type WorkingPaperFormSectionProps = {
  workingPaper: WorkingPaper;
  onChangeField: (field: keyof WorkingPaper, value: any) => void;
  onAddEvidenceLink: () => void;
  onUpdateEvidenceLink: (
    id: string,
    updates: Partial<WorkingPaperEvidenceLink>,
  ) => void;
  onRemoveEvidenceLink: (id: string) => void;
};

export function WorkingPaperFormSection({
  workingPaper,
  onChangeField,
  onAddEvidenceLink,
  onUpdateEvidenceLink,
  onRemoveEvidenceLink,
}: WorkingPaperFormSectionProps) {
  const wpTypes: WorkingPaperType[] = [
    "Planning",
    "Sampling",
    "Execution",
    "Reporting",
  ];

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <FileSpreadsheet className="h-4 w-4 text-purple-700" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Working Paper Testing & Substantive Execution (§7 Entity Fields)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          MDS-200 §7 Controlled Data · Rule 13
        </span>
      </div>

      <div className="space-y-3.5 text-xs">
        {/* 1. Header Metadata: Type, Reference No, Audit Area */}
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Working Paper Type (MDS-200 §7)
            </label>
            <select
              value={workingPaper.type}
              onChange={(e) =>
                onChangeField("type", e.target.value as WorkingPaperType)
              }
              className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900 focus:border-purple-500 focus:outline-hidden"
            >
              {wpTypes.map((t) => (
                <option key={t} value={t}>
                  {t} Phase Working Paper
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Working Paper Reference No.
            </label>
            <input
              type="text"
              value={workingPaper.referenceNumber}
              onChange={(e) => onChangeField("referenceNumber", e.target.value)}
              placeholder="e.g. AEMS-2026-WP-003"
              className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 font-mono text-xs text-slate-900 focus:border-purple-500 focus:outline-hidden"
              required
            />
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Audit Focus / Area (SCR-212)
            </label>
            <input
              type="text"
              value={workingPaper.auditArea}
              onChange={(e) => onChangeField("auditArea", e.target.value)}
              placeholder="Focus Area from Scope..."
              className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs text-slate-900 focus:border-purple-500 focus:outline-hidden"
              required
            />
          </div>
        </div>

        {/* 2. Planning & Procedure References */}
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Planned Reference (AEP / Program Trace — SCR-222/223)
            </label>
            <input
              type="text"
              value={workingPaper.plannedReference}
              onChange={(e) => onChangeField("plannedReference", e.target.value)}
              placeholder="e.g. AEP-2026-010-01 / PROG-2026-003-WH"
              className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 font-mono text-xs text-slate-900 focus:border-purple-500 focus:outline-hidden"
              required
            />
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Procedure Title (SCR-224 Link)
            </label>
            <input
              type="text"
              value={workingPaper.procedureTitle}
              onChange={(e) => onChangeField("procedureTitle", e.target.value)}
              placeholder="Procedure title..."
              className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs text-slate-900 focus:border-purple-500 focus:outline-hidden"
              required
            />
          </div>
        </div>

        {/* 3. Purpose */}
        <div className="space-y-1 pt-1 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-semibold text-slate-700 block flex items-center gap-1">
              <FileText size={11} className="text-purple-600" />
              <span>Working Paper Purpose & Testing Scope</span>
            </label>
            <span className="text-[10px] text-slate-400">
              Audit objective & verification scope
            </span>
          </div>
          <textarea
            rows={2}
            value={workingPaper.purpose}
            onChange={(e) => onChangeField("purpose", e.target.value)}
            placeholder="Define the substantive testing objective and specific compliance scope..."
            className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 leading-relaxed focus:border-purple-500 focus:outline-hidden"
            required
          />
        </div>

        {/* 4. Work Performed (Rule 13 Substance) */}
        <div className="space-y-1 pt-1 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-bold text-slate-800 block flex items-center gap-1">
              <FileCheck2 size={12} className="text-purple-700" />
              <span>Work Performed (Audit Testing Methodology & Tests Executed)</span>
            </label>
            <span className="text-[10px] font-bold text-purple-800 bg-purple-50 px-1.5 py-0.2 rounded border border-purple-200">
              Mandatory Rule 13 Substance
            </span>
          </div>
          <textarea
            rows={5}
            value={workingPaper.workPerformed}
            onChange={(e) => onChangeField("workPerformed", e.target.value)}
            placeholder="Detail the actual audit steps, calculations, interviews, inspections, reconciliations, and testing methodologies performed..."
            className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-900 font-mono leading-relaxed focus:border-purple-500 focus:outline-hidden"
            required
          />
        </div>

        {/* 5. Results & Conclusion Grid */}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 pt-1 border-t border-slate-100">
          <div className="space-y-1">
            <div className="flex items-center justify-between">
              <label className="text-[11px] font-semibold text-slate-700 block flex items-center gap-1">
                <CheckCircle2 size={11} className="text-blue-600" />
                <span>Results (Testing Outcomes & Variances)</span>
              </label>
              <span className="text-[10px] text-slate-400">Quantitative & factual findings</span>
            </div>
            <textarea
              rows={4}
              value={workingPaper.results}
              onChange={(e) => onChangeField("results", e.target.value)}
              placeholder="State the factual outcomes, variance calculations, compliance rates, and exceptions noted..."
              className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 leading-relaxed focus:border-purple-500 focus:outline-hidden"
              required
            />
          </div>

          <div className="space-y-1">
            <div className="flex items-center justify-between">
              <label className="text-[11px] font-semibold text-slate-700 block flex items-center gap-1">
                <ShieldAlert size={11} className="text-emerald-600" />
                <span>Conclusion (Evaluative Auditor Opinion)</span>
              </label>
              <span className="text-[10px] text-slate-400">Compliance & control assessment</span>
            </div>
            <textarea
              rows={4}
              value={workingPaper.conclusion}
              onChange={(e) => onChangeField("conclusion", e.target.value)}
              placeholder="Provide the professional audit conclusion on internal control adequacy and recommendations..."
              className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 leading-relaxed focus:border-purple-500 focus:outline-hidden"
              required
            />
          </div>
        </div>

        {/* 6. Evidence Links (Structured Pattern per SCR-227 / Rule 15) */}
        <div className="space-y-2 pt-2 border-t border-slate-100">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-1.5">
              <Paperclip size={13} className="text-purple-700" />
              <label className="text-[11px] font-bold text-slate-800">
                Evidence Links & Supporting Files (MDS-200 §7.1, Rule 15)
              </label>
              <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-mono text-slate-500">
                Cross-Referenced Artifacts
              </span>
            </div>

            <Button
              type="button"
              variant="outline"
              onClick={onAddEvidenceLink}
              className="h-6 text-[11px] px-2 flex items-center gap-1 text-purple-700 hover:text-purple-800 cursor-pointer"
            >
              <Plus size={11} />
              <span>Add Evidence Link</span>
            </Button>
          </div>

          {workingPaper.evidenceLinks.length === 0 ? (
            <div className="rounded border border-dashed border-slate-200 bg-slate-50/50 p-2.5 text-center text-[11px] text-slate-400">
              No attached evidence references yet. Click &quot;Add Evidence Link&quot; to cross-reference evidence files or SCR-229 custody items.
            </div>
          ) : (
            <div className="space-y-2">
              {workingPaper.evidenceLinks.map((evd) => {
                return (
                  <div
                    key={evd.id}
                    className="rounded-md border border-slate-200 bg-slate-50/70 p-2.5 space-y-1.5 text-xs transition hover:border-slate-300"
                  >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div className="flex items-center gap-2 flex-1 min-w-[200px]">
                        <span className="font-mono text-[10px] font-bold text-purple-700 bg-white px-2 py-0.5 rounded border border-purple-200 shrink-0">
                          {evd.id}
                        </span>
                        <input
                          type="text"
                          value={evd.title}
                          onChange={(e) =>
                            onUpdateEvidenceLink(evd.id, {
                              title: e.target.value,
                            })
                          }
                          placeholder="Evidence Document Title / Description..."
                          className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px] font-medium text-slate-900 focus:border-purple-500 focus:outline-hidden"
                        />
                      </div>

                      <button
                        type="button"
                        onClick={() => onRemoveEvidenceLink(evd.id)}
                        className="text-slate-400 hover:text-rose-600 p-1 transition cursor-pointer"
                        title="Remove evidence link"
                      >
                        <Trash2 size={13} />
                      </button>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2 pl-0.5">
                      <div className="flex items-center gap-1.5 flex-1 min-w-[220px]">
                        <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider shrink-0">
                          Ref / URI:
                        </label>
                        <input
                          type="text"
                          value={evd.sourceUrlOrRef}
                          onChange={(e) =>
                            onUpdateEvidenceLink(evd.id, {
                              sourceUrlOrRef: e.target.value,
                            })
                          }
                          placeholder="e.g. WP-2026-003-WH-01/EVD-01.pdf or pending SCR-229 ref..."
                          className="h-6 w-full rounded border border-slate-200 bg-white px-2 font-mono text-[10px] text-slate-600 focus:border-purple-500 focus:outline-hidden"
                        />
                      </div>

                      {evd.id === "EVD-2026-003" ||
                      evd.sourceUrlOrRef.toLowerCase().includes("accepted with limitation") ? (
                        <button
                          type="button"
                          onClick={() =>
                            navigateTo(
                              `/admin/audit-engagement/${workingPaper.engagementNumber}/execution/evidence/${evd.id}`,
                            )
                          }
                          className="inline-flex items-center gap-1 text-[10px] font-medium text-amber-800 bg-amber-50 hover:bg-amber-100 px-2 py-0.5 rounded border border-amber-300 shrink-0 cursor-pointer transition"
                          title="Formally indexed in SCR-229 as Accepted with Limitation (Click to view)"
                        >
                          <FolderLock size={10} />
                          <span>Accepted w/ Limitation (SCR-229)</span>
                        </button>
                      ) : evd.id.startsWith("EVD-") ? (
                        <button
                          type="button"
                          onClick={() =>
                            navigateTo(
                              `/admin/audit-engagement/${workingPaper.engagementNumber}/execution/evidence/${evd.id}`,
                            )
                          }
                          className="inline-flex items-center gap-1 text-[10px] font-medium text-purple-800 bg-purple-50 hover:bg-purple-100 px-2 py-0.5 rounded border border-purple-300 shrink-0 cursor-pointer transition"
                          title="Formally indexed in SCR-229 Audit Evidence Vault (Click to view)"
                        >
                          <FolderLock size={10} />
                          <span>Indexed in SCR-229</span>
                        </button>
                      ) : evd.sourceUrlOrRef ? (
                        <span
                          className="inline-flex items-center gap-1 text-[10px] font-medium text-purple-700 bg-purple-50 px-2 py-0.5 rounded border border-purple-200 shrink-0"
                          title="Working Paper / File System Reference"
                        >
                          <ExternalLink size={10} />
                          <span>Linked Artifact</span>
                        </span>
                      ) : null}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
