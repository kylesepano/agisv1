import {
  Clock,
  History,
  Plus,
  Trash2,
  UserCheck,
} from "lucide-react";
import { useState } from "react";
import { Button } from "../../../../../components/ui/button";
import type {
  AuditEvidenceItem,
  EvidenceCustodyEvent,
} from "../evidence-types";

type EvidenceCustodyLogSectionProps = {
  evidence: AuditEvidenceItem;
  onAddCustodyEvent: (event: EvidenceCustodyEvent) => void;
  onRemoveCustodyEvent: (id: string) => void;
};

export function EvidenceCustodyLogSection({
  evidence,
  onAddCustodyEvent,
  onRemoveCustodyEvent,
}: EvidenceCustodyLogSectionProps) {
  const [showAddModal, setShowAddModal] = useState(false);
  const [newAction, setNewAction] = useState("");
  const [newPerson, setNewPerson] = useState("");
  const [newRole, setNewRole] = useState("");
  const [newNotes, setNewNotes] = useState("");

  const handleCreateEvent = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAction || !newPerson) return;

    const event: EvidenceCustodyEvent = {
      id: `CST-00${evidence.custodyLog.length + 1}`,
      date: new Date().toISOString().replace("T", " ").substring(0, 16),
      action: newAction,
      person: newPerson,
      role: newRole || "Auditor / Custodian",
      notes: newNotes,
    };

    onAddCustodyEvent(event);
    setNewAction("");
    setNewPerson("");
    setNewRole("");
    setNewNotes("");
    setShowAddModal(false);
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <History className="h-4 w-4 text-emerald-700" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Chain of Custody & Transfer Audit Trail (UID-200 §8.3 Mandate)
          </h3>
        </div>
        <div className="flex items-center gap-2">
          <span className="font-mono text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded">
            {evidence.custodyLog.length} Event{evidence.custodyLog.length === 1 ? "" : "s"}
          </span>
          <Button
            type="button"
            variant="outline"
            onClick={() => setShowAddModal(!showAddModal)}
            className="h-6 text-[11px] px-2 flex items-center gap-1 text-emerald-700 hover:text-emerald-800 cursor-pointer"
          >
            <Plus size={11} />
            <span>Log Custody Event</span>
          </Button>
        </div>
      </div>

      {/* Add Custody Event Modal / Inline Form */}
      {showAddModal && (
        <form
          onSubmit={handleCreateEvent}
          className="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 space-y-2 text-xs"
        >
          <div className="flex items-center justify-between font-bold text-emerald-950">
            <span>Log New Chain of Custody Transfer / Verification</span>
            <button
              type="button"
              onClick={() => setShowAddModal(false)}
              className="text-slate-400 hover:text-slate-600 text-xs"
            >
              Cancel
            </button>
          </div>

          <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <div>
              <label className="text-[10px] font-semibold text-slate-700 block mb-0.5">
                Custody Action / Transfer
              </label>
              <input
                type="text"
                value={newAction}
                onChange={(e) => setNewAction(e.target.value)}
                placeholder="e.g. Custody Transfer, Hash Re-verification..."
                className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-xs"
                required
              />
            </div>

            <div>
              <label className="text-[10px] font-semibold text-slate-700 block mb-0.5">
                Person Responsible
              </label>
              <input
                type="text"
                value={newPerson}
                onChange={(e) => setNewPerson(e.target.value)}
                placeholder="Name of recipient / auditor..."
                className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-xs"
                required
              />
            </div>

            <div>
              <label className="text-[10px] font-semibold text-slate-700 block mb-0.5">
                Official Role / Designation
              </label>
              <input
                type="text"
                value={newRole}
                onChange={(e) => setNewRole(e.target.value)}
                placeholder="e.g. Lead Auditor, Vault Custodian..."
                className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-xs"
              />
            </div>
          </div>

          <div>
            <label className="text-[10px] font-semibold text-slate-700 block mb-0.5">
              Custody Notes & Physical Storage Location
            </label>
            <input
              type="text"
              value={newNotes}
              onChange={(e) => setNewNotes(e.target.value)}
              placeholder="Record transfer circumstances or secure storage bin..."
              className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-xs"
            />
          </div>

          <div className="flex justify-end pt-1">
            <Button
              type="submit"
              className="h-7 px-3 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer"
            >
              Record Custody Entry
            </Button>
          </div>
        </form>
      )}

      {/* Custody Log Timeline */}
      {evidence.custodyLog.length === 0 ? (
        <div className="rounded border border-dashed border-slate-200 bg-slate-50/50 p-3 text-center text-xs text-slate-400">
          No custody events logged yet.
        </div>
      ) : (
        <div className="space-y-2">
          {evidence.custodyLog.map((event, idx) => (
            <div
              key={event.id}
              className="rounded-md border border-slate-200 bg-slate-50/60 p-2.5 space-y-1 text-xs transition hover:border-slate-300"
            >
              <div className="flex flex-wrap items-center justify-between gap-1">
                <div className="flex items-center gap-2">
                  <span className="font-mono text-[10px] font-bold text-emerald-800 bg-white px-1.5 py-0.2 rounded border border-emerald-200">
                    Step {idx + 1}
                  </span>
                  <span className="font-semibold text-slate-900">
                    {event.action}
                  </span>
                </div>

                <div className="flex items-center gap-2 text-[11px] text-slate-500">
                  <span className="flex items-center gap-1 font-mono text-[10px]">
                    <Clock size={11} className="text-slate-400" />
                    <span>{event.date}</span>
                  </span>

                  {evidence.custodyLog.length > 1 && (
                    <button
                      type="button"
                      onClick={() => onRemoveCustodyEvent(event.id)}
                      className="text-slate-300 hover:text-rose-600 p-0.5 cursor-pointer"
                      title="Remove custody entry"
                    >
                      <Trash2 size={11} />
                    </button>
                  )}
                </div>
              </div>

              <div className="flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-600 pl-1">
                <div className="flex items-center gap-1.5">
                  <UserCheck size={11} className="text-emerald-700" />
                  <span>
                    <strong>{event.person}</strong> ({event.role})
                  </span>
                </div>

                {event.notes && (
                  <p className="text-[11px] text-slate-500 italic">
                    &ldquo;{event.notes}&rdquo;
                  </p>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
