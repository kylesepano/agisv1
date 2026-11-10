import { useState } from "react";
import {
  AlertTriangle,
  Calendar,
  CheckCircle2,
  Clock,
  ListTodo,
  Plus,
  Trash2,
  User,
  X,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type {
  EngagementTask,
  TaskPriority,
  TaskStatus,
  TaskType,
} from "../execution-types";

type EngagementTasksSectionProps = {
  tasks: EngagementTask[];
  availableProcedures: Array<{ id: string; title: string }>;
  availableAssignees: string[];
  engagementNumber: string;
  onAddTask: (task: Omit<EngagementTask, "id">) => void;
  onRemoveTask: (id: string) => void;
  onToggleStatus: (id: string) => void;
};

export function EngagementTasksSection({
  tasks,
  availableProcedures,
  availableAssignees,
  engagementNumber,
  onAddTask,
  onRemoveTask,
  onToggleStatus,
}: EngagementTasksSectionProps) {
  const [showForm, setShowForm] = useState(false);
  const [title, setTitle] = useState("");
  const [type, setType] = useState<TaskType>("Voucher Verification");
  const [assignee, setAssignee] = useState(availableAssignees[0] || "Lead Auditor");
  const [relatedRecord, setRelatedRecord] = useState(
    availableProcedures[0]?.id || "",
  );
  const [dueDate, setDueDate] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() + 7);
    return d.toISOString().split("T")[0];
  });
  const [priority, setPriority] = useState<TaskPriority>("Medium");

  const hasProcedures = availableProcedures.length > 0;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !relatedRecord) return;

    if (title.trim().length < 5 || /^(asd|asdad|asdasd|sample|test)+$/i.test(title.trim())) {
      alert("Please provide a genuine, descriptive Fieldwork Task Title rather than placeholder test characters.");
      return;
    }

    onAddTask({
      title: title.trim(),
      type,
      assignee,
      relatedRecord,
      dueDate,
      priority,
      status: "Pending",
    });

    setTitle("");
    setShowForm(false);
  };

  const getPriorityBadge = (p: TaskPriority) => {
    switch (p) {
      case "Urgent":
        return "bg-rose-50 text-rose-700 border-rose-200";
      case "High":
        return "bg-amber-50 text-amber-800 border-amber-200";
      case "Medium":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "Low":
        return "bg-slate-100 text-slate-600 border-slate-200";
    }
  };

  const getStatusBadge = (s: TaskStatus) => {
    switch (s) {
      case "Completed":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "In Progress":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "Blocked":
        return "bg-rose-50 text-rose-700 border-rose-200";
      case "Pending":
        return "bg-slate-100 text-slate-600 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <ListTodo className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Engagement Fieldwork Tasks (§7 Engagement Task Entity)
          </h3>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
            {tasks.length} Tasks Scheduled
          </span>
        </div>

        <Button
          type="button"
          disabled={!hasProcedures}
          title={
            !hasProcedures
              ? "Add procedures in Planning (SCR-223) before creating fieldwork tasks."
              : "Create New Fieldwork Task"
          }
          onClick={() => {
            if (hasProcedures) {
              setRelatedRecord(availableProcedures[0]?.id || "");
              setShowForm(!showForm);
            }
          }}
          className="h-8 rounded-lg text-xs px-3 flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-sm cursor-pointer transition active:scale-[0.99] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Plus size={13} />
          <span>Add Task</span>
        </Button>
      </div>

      {/* Warning banner when no procedures exist */}
      {!hasProcedures && (
        <div className="rounded-lg border border-amber-200 bg-amber-50/60 p-3 text-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 text-amber-900">
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
            <span>
              No audit procedures defined for this engagement. Add procedures in <strong>Planning (SCR-223)</strong> before creating fieldwork tasks.
            </span>
          </div>
          <Button
            type="button"
            variant="outline"
            onClick={() =>
              navigateTo(
                `/admin/audit-engagement/${engagementNumber}/planning/programs`,
              )
            }
            className="h-7 text-xs px-2.5 border-amber-300 bg-white text-amber-900 hover:bg-amber-100 shrink-0"
          >
            <span>Open Programs (SCR-223)</span>
          </Button>
        </div>
      )}

      {/* Task Creation Form Card */}
      {showForm && hasProcedures && (
        <form
          onSubmit={handleSubmit}
          className="rounded-xl border border-blue-200 bg-blue-50/40 p-4 space-y-3.5 text-xs"
        >
          <div className="flex items-center justify-between font-bold text-blue-950 border-b border-blue-100 pb-2">
            <span>Schedule New Fieldwork Task</span>
            <button
              type="button"
              onClick={() => setShowForm(false)}
              className="text-slate-400 hover:text-slate-600 cursor-pointer p-0.5 rounded"
            >
              <X size={15} />
            </button>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div className="sm:col-span-2 lg:col-span-3">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Task Title & Scope <span className="text-rose-500">*</span>
                </span>
                <input
                  type="text"
                  required
                  placeholder="e.g. Sample 20 disbursement vouchers for RA 9184 compliance"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                />
              </label>
            </div>

            <div className="sm:col-span-2 lg:col-span-1">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Task Type
                </span>
                <select
                  value={type}
                  onChange={(e) => setType(e.target.value as TaskType)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                >
                  <option value="Voucher Verification">Voucher Verification</option>
                  <option value="Fieldwork Sampling">Fieldwork Sampling</option>
                  <option value="Physical Inventory Inspection">
                    Physical Inventory Inspection
                  </option>
                  <option value="Liaison & Inquiries">Liaison & Inquiries</option>
                  <option value="Working Paper Review">Working Paper Review</option>
                </select>
              </label>
            </div>

            <div className="sm:col-span-2 lg:col-span-1">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Related Procedure (SCR-223) <span className="text-rose-500">*</span>
                </span>
                <select
                  value={relatedRecord}
                  onChange={(e) => setRelatedRecord(e.target.value)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs font-mono text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  required
                >
                  {availableProcedures.map((proc) => (
                    <option key={proc.id} value={proc.id}>
                      {proc.id} — {proc.title}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <div className="sm:col-span-2 lg:col-span-1">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Assigned Auditor
                </span>
                <select
                  value={assignee}
                  onChange={(e) => setAssignee(e.target.value)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                >
                  {availableAssignees.map((name, idx) => (
                    <option key={idx} value={name}>
                      {name}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <div className="sm:col-span-1">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Due Date
                </span>
                <input
                  type="date"
                  value={dueDate}
                  onChange={(e) => setDueDate(e.target.value)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                />
              </label>
            </div>

            <div className="sm:col-span-1">
              <label className="block">
                <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                  Priority
                </span>
                <select
                  value={priority}
                  onChange={(e) => setPriority(e.target.value as TaskPriority)}
                  className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                >
                  <option value="Low">Low</option>
                  <option value="Medium">Medium</option>
                  <option value="High">High</option>
                  <option value="Urgent">Urgent</option>
                </select>
              </label>
            </div>
          </div>

          <div className="flex items-center justify-end gap-2.5 pt-2.5 border-t border-blue-100">
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowForm(false)}
              className="h-9 px-4 rounded-lg text-xs font-semibold border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              className="h-9 px-5 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-1.5 cursor-pointer transition active:scale-[0.99]"
            >
              <span>Save Task</span>
            </Button>
          </div>
        </form>
      )}

      <div className="overflow-x-auto">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-slate-200 bg-slate-50/70 text-[10px] font-bold uppercase tracking-wider text-slate-500">
              <th className="py-2 px-2.5">Task ID</th>
              <th className="py-2 px-2.5">Task Title & Type</th>
              <th className="py-2 px-2.5">Assignee</th>
              <th className="py-2 px-2.5">Related Record</th>
              <th className="py-2 px-2.5">Due Date</th>
              <th className="py-2 px-2.5 text-center">Priority</th>
              <th className="py-2 px-2.5 text-center">Status</th>
              <th className="py-2 px-2.5 text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {tasks.length === 0 ? (
              <tr>
                <td
                  colSpan={8}
                  className="py-6 text-center text-xs text-slate-400 italic"
                >
                  No fieldwork tasks scheduled yet. Click "+ Add Task" to schedule testing activities.
                </td>
              </tr>
            ) : (
              tasks.map((task) => (
                <tr key={task.id} className="hover:bg-slate-50/80 transition">
                  <td className="py-2.5 px-2.5 font-mono text-[11px] font-bold text-blue-700 whitespace-nowrap">
                    {task.id}
                  </td>
                  <td className="py-2.5 px-2.5 max-w-[260px]">
                    <span className="font-semibold text-slate-900 block leading-snug">
                      {task.title}
                    </span>
                    <span className="text-[10px] text-slate-400 block">
                      Type: {task.type}
                    </span>
                  </td>
                  <td className="py-2.5 px-2.5 text-slate-700 whitespace-nowrap">
                    <div className="flex items-center gap-1 text-[11px] font-medium">
                      <User size={11} className="text-slate-400" />
                      <span>{task.assignee}</span>
                    </div>
                  </td>
                  <td className="py-2.5 px-2.5 font-mono text-[10px] font-bold text-slate-600 whitespace-nowrap">
                    {task.relatedRecord}
                  </td>
                  <td className="py-2.5 px-2.5 font-mono text-[11px] text-slate-700 whitespace-nowrap">
                    <div className="flex items-center gap-1">
                      <Calendar size={11} className="text-slate-400" />
                      <span>{task.dueDate}</span>
                    </div>
                  </td>
                  <td className="py-2.5 px-2.5 text-center whitespace-nowrap">
                    <span
                      className={`inline-block rounded px-1.5 py-0.2 text-[10px] font-bold border ${getPriorityBadge(
                        task.priority,
                      )}`}
                    >
                      {task.priority}
                    </span>
                  </td>
                  <td className="py-2.5 px-2.5 text-center whitespace-nowrap">
                    <button
                      type="button"
                      onClick={() => onToggleStatus(task.id)}
                      title="Click to toggle status"
                      className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-bold border transition cursor-pointer ${getStatusBadge(
                        task.status,
                      )}`}
                    >
                      {task.status === "Completed" ? (
                        <CheckCircle2 size={10} className="shrink-0" />
                      ) : (
                        <Clock size={10} className="shrink-0" />
                      )}
                      <span>{task.status}</span>
                    </button>
                  </td>
                  <td className="py-2.5 px-2.5 text-right whitespace-nowrap">
                    <button
                      type="button"
                      onClick={() => onRemoveTask(task.id)}
                      className="text-slate-300 hover:text-rose-600 p-1 transition cursor-pointer"
                      title="Remove task"
                    >
                      <Trash2 size={13} />
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

