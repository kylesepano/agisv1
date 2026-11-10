import { useState } from "react";
import { CheckCheck, Mail, Plus, Send, Trash2, Users } from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import type { AeoRecipient } from "../prepare-order-types";

type RecipientsDistributionProps = {
  recipients: AeoRecipient[];
  isReadOnly?: boolean;
  onAddRecipient: (recipient: AeoRecipient) => void;
  onRemoveRecipient: (id: string) => void;
  onToggleStatus: (id: string) => void;
};

export function RecipientsDistribution({
  recipients,
  isReadOnly = false,
  onAddRecipient,
  onRemoveRecipient,
  onToggleStatus,
}: RecipientsDistributionProps) {
  const [showAddForm, setShowAddForm] = useState(false);
  const [newName, setNewName] = useState("");
  const [newTitle, setNewTitle] = useState("");
  const [newOffice, setNewOffice] = useState("");
  const [newEmail, setNewEmail] = useState("");
  const [newMethod, setNewMethod] = useState<"Digital Notification" | "Official Courier" | "Physical Copy">(
    "Digital Notification",
  );

  const handleAdd = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName.trim() || !newOffice.trim()) return;

    onAddRecipient({
      id: `REC-${Date.now().toString().slice(-4)}`,
      name: newName.trim(),
      title: newTitle.trim() || "Recipient",
      office: newOffice.trim(),
      email: newEmail.trim() || "recipient@cagayandeoro.gov.ph",
      deliveryMethod: newMethod,
      distributionStatus: "Pending",
    });

    setNewName("");
    setNewTitle("");
    setNewOffice("");
    setNewEmail("");
    setShowAddForm(false);
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5">
          <Send className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            AEO Recipients & Distribution List
          </h3>
          <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-bold text-slate-600">
            {recipients.length}
          </span>
        </div>
        {!isReadOnly && (
          <Button
            type="button"
            variant="outline"
            onClick={() => setShowAddForm(!showAddForm)}
            className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700"
          >
            <Plus size={12} />
            <span>Add Recipient</span>
          </Button>
        )}
      </div>

      {showAddForm && (
        <form onSubmit={handleAdd} className="rounded-lg border border-blue-200 bg-blue-50/40 p-3 space-y-2 text-xs">
          <div className="flex items-center justify-between font-bold text-blue-900">
            <span>Add New Official AEO Recipient</span>
            <button
              type="button"
              onClick={() => setShowAddForm(false)}
              className="text-slate-400 hover:text-slate-600 text-xs cursor-pointer"
            >
              Cancel
            </button>
          </div>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <input
              type="text"
              placeholder="Recipient Name (e.g. Atty. Jane Doe)"
              className="h-8 rounded border border-slate-200 bg-white px-2 text-xs"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              required
            />
            <input
              type="text"
              placeholder="Title / Designation"
              className="h-8 rounded border border-slate-200 bg-white px-2 text-xs"
              value={newTitle}
              onChange={(e) => setNewTitle(e.target.value)}
            />
            <input
              type="text"
              placeholder="Office / Department"
              className="h-8 rounded border border-slate-200 bg-white px-2 text-xs"
              value={newOffice}
              onChange={(e) => setNewOffice(e.target.value)}
              required
            />
            <input
              type="email"
              placeholder="Email address"
              className="h-8 rounded border border-slate-200 bg-white px-2 text-xs"
              value={newEmail}
              onChange={(e) => setNewEmail(e.target.value)}
            />
          </div>
          <div className="flex items-center justify-between pt-1">
            <select
              value={newMethod}
              onChange={(e) =>
                setNewMethod(
                  e.target.value as "Digital Notification" | "Official Courier" | "Physical Copy",
                )
              }
              className="h-7 rounded border border-slate-200 bg-white px-2 text-[11px] text-slate-700"
            >
              <option value="Digital Notification">Digital Notification</option>
              <option value="Official Courier">Official Courier</option>
              <option value="Physical Copy">Physical Copy</option>
            </select>
            <Button type="submit" className="h-7 bg-blue-600 text-white text-xs px-3">
              Save Recipient
            </Button>
          </div>
        </form>
      )}

      {/* Recipient Table / List */}
      <div className="divide-y divide-slate-100 rounded-lg border border-slate-200 overflow-hidden">
        {recipients.map((rec) => (
          <div
            key={rec.id}
            className="flex flex-col sm:flex-row sm:items-center sm:justify-between p-2.5 text-xs hover:bg-slate-50/50 transition gap-2"
          >
            <div className="flex items-start gap-2.5 min-w-0">
              <div className="grid h-7 w-7 shrink-0 place-items-center rounded bg-slate-100 text-slate-600 mt-0.5">
                <Users size={13} />
              </div>
              <div className="min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-bold text-slate-900">{rec.name}</span>
                  <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] text-slate-600 border border-slate-200">
                    {rec.title}
                  </span>
                </div>
                <div className="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5">
                  <span>{rec.office}</span>
                  <span>•</span>
                  <span className="font-mono text-[10px]">{rec.email}</span>
                </div>
              </div>
            </div>

            <div className="flex items-center justify-between sm:justify-end gap-2 shrink-0">
              <span className="rounded bg-slate-50 px-2 py-0.5 text-[10px] text-slate-500 border border-slate-200">
                {rec.deliveryMethod}
              </span>

              <button
                type="button"
                onClick={() => onToggleStatus(rec.id)}
                title="Click to toggle acknowledgment status"
                className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold border transition cursor-pointer ${
                  rec.distributionStatus === "Acknowledged"
                    ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                    : rec.distributionStatus === "Distributed"
                    ? "bg-blue-50 text-blue-700 border-blue-200"
                    : "bg-amber-50 text-amber-700 border-amber-200"
                }`}
              >
                {rec.distributionStatus === "Acknowledged" && <CheckCheck size={10} />}
                {rec.distributionStatus === "Distributed" && <Mail size={10} />}
                <span>{rec.distributionStatus}</span>
              </button>

              {!isReadOnly && (
                <button
                  type="button"
                  onClick={() => onRemoveRecipient(rec.id)}
                  className="text-slate-300 hover:text-rose-600 p-1 transition cursor-pointer"
                  title="Remove recipient"
                >
                  <Trash2 size={13} />
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
