import {
  Compass,
  FolderOpen,
  Link,
  Users,
  Wrench,
} from "lucide-react";
import { defaultEvidenceCategories } from "../aep-data";

type AepMethodologyEvidenceSectionProps = {
  riskMatrixSummaryRef: string;
  methodology: string;
  evidenceRequirements: string[];
  assignedTeamRef: string;
  requiredAuditTools: string;
  onChangeMethodology: (val: string) => void;
  onToggleEvidence: (category: string) => void;
  onChangeTools: (val: string) => void;
};

export function AepMethodologyEvidenceSection({
  riskMatrixSummaryRef,
  methodology,
  evidenceRequirements,
  assignedTeamRef,
  requiredAuditTools,
  onChangeMethodology,
  onToggleEvidence,
  onChangeTools,
}: AepMethodologyEvidenceSectionProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Compass className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Methodology, Evidence Requirements & Resources (§7)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          Fieldwork Operational Approach
        </span>
      </div>

      <div className="space-y-3 text-xs">
        {/* Risk Matrix Reference Banner */}
        <div className="rounded-md border border-amber-200 bg-amber-50/60 p-2.5 flex items-start gap-2 text-[11px] text-amber-900">
          <Link size={13} className="text-amber-600 shrink-0 mt-0.5" />
          <div className="space-y-0.5">
            <span className="font-bold block">
              Engagement Risk Matrix Linkage (SCR-221 Controlled Artifact):
            </span>
            <p className="leading-snug">{riskMatrixSummaryRef}</p>
          </div>
        </div>

        {/* Detailed Methodology */}
        <div className="space-y-1">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-semibold text-slate-700 block">
              Audit Methodology & Testing Procedures
            </label>
            <span className="text-[10px] text-slate-400">
              Sampling, substantive testing, physical inspection
            </span>
          </div>
          <textarea
            rows={4}
            value={methodology}
            onChange={(e) => onChangeMethodology(e.target.value)}
            placeholder="Detail the step-by-step audit methodology, sample selection formulas, interviews, and verification techniques..."
            className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 font-mono leading-relaxed focus:border-blue-500 focus:outline-hidden"
            required
          />
        </div>

        {/* Evidence Requirements */}
        <div className="space-y-2 pt-1 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-bold text-slate-800 flex items-center gap-1.5">
              <FolderOpen size={13} className="text-blue-600" />
              <span>Evidence Requirements & Working Paper Artifacts (§7)</span>
            </label>
            <span className="text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
              {evidenceRequirements.length} Categories
            </span>
          </div>

          <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2 rounded-lg border border-slate-200 bg-slate-50/60 p-2.5">
            {defaultEvidenceCategories.map((cat, idx) => {
              const isChecked = evidenceRequirements.includes(cat);
              return (
                <label
                  key={idx}
                  className={`flex items-start gap-2 rounded-md p-1.5 text-[11px] cursor-pointer transition border ${
                    isChecked
                      ? "border-emerald-200 bg-white text-slate-900 shadow-2xs font-medium"
                      : "border-transparent bg-transparent text-slate-600 hover:bg-slate-100/80"
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={isChecked}
                    onChange={() => onToggleEvidence(cat)}
                    className="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                  />
                  <span className="leading-tight">{cat}</span>
                </label>
              );
            })}
          </div>
        </div>

        {/* Resources & Audit Tools */}
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 pt-1 border-t border-slate-100">
          {/* Team Roster Reference */}
          <div className="rounded-md bg-slate-50/80 p-2.5 border border-slate-200 space-y-1">
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
              <Users size={11} className="text-blue-600" />
              Assigned Audit Team (SCR-213)
            </span>
            <p className="text-[11px] text-slate-800 leading-snug">{assignedTeamRef}</p>
          </div>

          {/* Required Tools */}
          <div className="rounded-md bg-slate-50/80 p-2.5 border border-slate-200 space-y-1">
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
              <Wrench size={11} className="text-indigo-600" />
              Required Tools & Equipment
            </span>
            <input
              type="text"
              value={requiredAuditTools}
              onChange={(e) => onChangeTools(e.target.value)}
              placeholder="e.g. CIAS e-Sampling Tool, Document Scanner..."
              className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px] text-slate-800"
            />
          </div>
        </div>
      </div>
    </div>
  );
}
