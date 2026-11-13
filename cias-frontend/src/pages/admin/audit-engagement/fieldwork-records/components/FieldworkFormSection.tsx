import {
  Calendar,
  ExternalLink,
  FileCheck2,
  FolderLock,
  MapPin,
  MessageSquare,
  Paperclip,
  Plus,
  Trash2,
  Users,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type {
  FieldworkEvidenceLink,
  FieldworkRecord,
  FieldworkRecordType,
} from "../fieldwork-types";

type FieldworkFormSectionProps = {
  record: FieldworkRecord;
  onChangeField: (field: keyof FieldworkRecord, value: any) => void;
  onAddEvidenceLink: () => void;
  onUpdateEvidenceLink: (
    id: string,
    updates: Partial<FieldworkEvidenceLink>,
  ) => void;
  onRemoveEvidenceLink: (id: string) => void;
};

export function FieldworkFormSection({
  record,
  onChangeField,
  onAddEvidenceLink,
  onUpdateEvidenceLink,
  onRemoveEvidenceLink,
}: FieldworkFormSectionProps) {
  const fieldworkTypes: FieldworkRecordType[] = [
    "Interview / Inquiry",
    "Physical Inspection",
    "Process Walkthrough",
    "Observation / Spot Count",
    "Coordination Meeting",
    "Field Audit Notes",
  ];

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <FileCheck2 className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Fieldwork Event Details & Factual Findings (§7 Entity Fields)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          MDS-200 §7 Controlled Data
        </span>
      </div>

      <div className="space-y-3 text-xs">
        {/* 1. Type & Date & Location */}
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1">
              Fieldwork Record Type (MDS-200 §7)
            </label>
            <select
              value={record.type}
              onChange={(e) =>
                onChangeField("type", e.target.value as FieldworkRecordType)
              }
              className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900"
            >
              {fieldworkTypes.map((t) => (
                <option key={t} value={t}>
                  {t}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1 flex items-center gap-1">
              <Calendar size={11} className="text-slate-400" />
              <span>Event Date</span>
            </label>
            <input
              type="date"
              value={record.date}
              onChange={(e) => onChangeField("date", e.target.value)}
              className="h-8 w-full rounded border border-slate-200 bg-white px-2 font-mono text-xs text-slate-900"
              required
            />
          </div>

          <div>
            <label className="text-[11px] font-semibold text-slate-700 block mb-1 flex items-center gap-1">
              <MapPin size={11} className="text-slate-400" />
              <span>Physical Location / Site</span>
            </label>
            <input
              type="text"
              value={record.location}
              onChange={(e) => onChangeField("location", e.target.value)}
              placeholder="e.g. CGSO Central Warehouse Bay 2..."
              className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs text-slate-900"
              required
            />
          </div>
        </div>

        {/* 2. Persons Involved */}
        <div>
          <label className="text-[11px] font-semibold text-slate-700 block mb-1 flex items-center gap-1">
            <Users size={11} className="text-slate-400" />
            <span>Persons Involved (Auditors, Auditee Interviewees, Witnesses)</span>
          </label>
          <input
            type="text"
            value={record.personsInvolved}
            onChange={(e) => onChangeField("personsInvolved", e.target.value)}
            placeholder="List names, designations, and participating roles..."
            className="h-8 w-full rounded border border-slate-200 bg-white px-2.5 text-xs text-slate-900"
            required
          />
        </div>

        {/* 3. Purpose */}
        <div className="space-y-1">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-semibold text-slate-700 block">
              Fieldwork Purpose & Testing Objective
            </label>
            <span className="text-[10px] text-slate-400">
              Why this fieldwork occurred
            </span>
          </div>
          <textarea
            rows={2}
            value={record.purpose}
            onChange={(e) => onChangeField("purpose", e.target.value)}
            placeholder="State the specific audit objective and verification purpose..."
            className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 leading-relaxed focus:border-blue-500 focus:outline-hidden"
            required
          />
        </div>

        {/* 4. Facts Obtained (Mandatory Core Substance) */}
        <div className="space-y-1 pt-1 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-bold text-slate-800 block flex items-center gap-1">
              <FileCheck2 size={12} className="text-emerald-600" />
              <span>Facts Obtained (Factual Findings, Verifications & Physical Observations)</span>
            </label>
            <span className="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded">
              Core Substance (MDS-200 §7)
            </span>
          </div>
          <textarea
            rows={5}
            value={record.factsObtained}
            onChange={(e) => onChangeField("factsObtained", e.target.value)}
            placeholder="Record the direct factual evidence, sample counts, observed conditions, verified timestamps, and audit metrics..."
            className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-900 font-mono leading-relaxed focus:border-blue-500 focus:outline-hidden"
            required
          />
        </div>

        {/* 5. Auditor Notes & Impressions */}
        <div className="space-y-1 pt-1 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-semibold text-slate-700 block flex items-center gap-1">
              <MessageSquare size={11} className="text-slate-400" />
              <span>Auditor Impressions, Explanations & Risk Notes</span>
            </label>
            <span className="text-[10px] text-slate-400">
              Contextual observations
            </span>
          </div>
          <textarea
            rows={3}
            value={record.notes}
            onChange={(e) => onChangeField("notes", e.target.value)}
            placeholder="Document interviewee explanations, potential control weaknesses, or areas requiring follow-up testing..."
            className="w-full rounded border border-slate-200 bg-white p-2.5 text-xs text-slate-800 leading-relaxed focus:border-blue-500 focus:outline-hidden"
          />
        </div>

        {/* 6. Optional Evidence Links (Rule 15) */}
        <div className="space-y-2 pt-2 border-t border-slate-100">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-1.5">
              <Paperclip size={13} className="text-blue-600" />
              <label className="text-[11px] font-bold text-slate-800">
                Evidence Links & Supporting Files (MDS-200 §7.1, Rule 15)
              </label>
              <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-mono text-slate-500">
                Optional
              </span>
            </div>

            <Button
              type="button"
              variant="outline"
              onClick={onAddEvidenceLink}
              className="h-6 text-[11px] px-2 flex items-center gap-1 text-blue-600 hover:text-blue-700 cursor-pointer"
            >
              <Plus size={11} />
              <span>Add Evidence Link</span>
            </Button>
          </div>

          {record.evidenceLinks.length === 0 ? (
            <div className="rounded border border-dashed border-slate-200 bg-slate-50/50 p-2.5 text-center text-[11px] text-slate-400">
              No attached evidence files yet. Evidence links are optional per MDS-200 Rule 15.
            </div>
          ) : (
            <div className="space-y-2">
              {record.evidenceLinks.map((evd) => {
                return (
                  <div
                    key={evd.id}
                    className="rounded-md border border-slate-200 bg-slate-50/70 p-2.5 space-y-1.5 text-xs transition hover:border-slate-300"
                  >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div className="flex items-center gap-2 flex-1 min-w-[200px]">
                        <span className="font-mono text-[10px] font-bold text-blue-700 bg-white px-2 py-0.5 rounded border border-blue-200 shrink-0">
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
                          className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px] font-medium text-slate-900 focus:border-blue-500 focus:outline-hidden"
                        />
                      </div>

                      <button
                        type="button"
                        onClick={() => onRemoveEvidenceLink(evd.id)}
                        className="text-slate-400 hover:text-rose-600 p-1 transition cursor-pointer"
                        title="Remove link"
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
                          className="h-6 w-full rounded border border-slate-200 bg-white px-2 font-mono text-[10px] text-slate-600 focus:border-blue-500 focus:outline-hidden"
                        />
                      </div>

                      {evd.id === "EVD-2026-003" ||
                      evd.sourceUrlOrRef.toLowerCase().includes("accepted with limitation") ? (
                        <button
                          type="button"
                          onClick={() =>
                            navigateTo(
                              `/admin/audit-engagement/${record.engagementNumber}/execution/evidence/${evd.id}`,
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
                              `/admin/audit-engagement/${record.engagementNumber}/execution/evidence/${evd.id}`,
                            )
                          }
                          className="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2 py-0.5 rounded border border-emerald-300 shrink-0 cursor-pointer transition"
                          title="Formally indexed in SCR-229 Audit Evidence Vault (Click to view)"
                        >
                          <FolderLock size={10} />
                          <span>Indexed in SCR-229</span>
                        </button>
                      ) : evd.sourceUrlOrRef ? (
                        <span
                          className="inline-flex items-center gap-1 text-[10px] font-medium text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200 shrink-0"
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
