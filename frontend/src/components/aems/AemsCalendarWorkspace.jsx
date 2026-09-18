import { useCallback, useEffect, useState } from "react";
import { CalendarDays, CheckCircle2, Plus, RefreshCw } from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsClosureApi } from "../../services/api";

export default function AemsCalendarWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [form, setForm] = useState({
    milestoneCode: "",
    title: "",
    dueDate: "",
    categoryCode: "GENERAL",
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const canManage = hasPermission(user, "aems.calendar.manage");
  const load = useCallback(async () => {
    setError("");
    try {
      setData(await aemsClosureApi.calendar(engagementId));
    } catch (reason) {
      setError(reason.message);
    }
  }, [engagementId]);
  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);
  async function add() {
    setBusy(true);
    setError("");
    try {
      await aemsClosureApi.createMilestone(engagementId, form);
      setForm({
        milestoneCode: "",
        title: "",
        dueDate: "",
        categoryCode: "GENERAL",
      });
      await load();
    } catch (reason) {
      setError(reason.message);
    } finally {
      setBusy(false);
    }
  }
  async function complete(milestone) {
    setBusy(true);
    setError("");
    try {
      await aemsClosureApi.transitionMilestone(engagementId, milestone.id, {
        status: "COMPLETED",
        lockVersion: milestone.lockVersion,
      });
      await load();
    } catch (reason) {
      setError(reason.message);
    } finally {
      setBusy(false);
    }
  }
  if (!data)
    return (
      <div className="grid min-h-64 place-items-center">
        <RefreshCw className="animate-spin text-sky-700" size={26} />
      </div>
    );
  return (
    <div className="space-y-5" data-testid="aems-calendar-workspace">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex items-start gap-3">
          <span className="grid h-10 w-10 place-items-center rounded-xl bg-sky-50 text-sky-700">
            <CalendarDays size={20} />
          </span>
          <div>
            <h3 className="font-bold text-slate-900">
              Audit Calendar and Milestones
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              Track required engagement dates, owners, completion, and overdue
              blockers.
            </p>
          </div>
        </div>
        {error && (
          <p className="mt-3 rounded-lg bg-rose-50 p-3 text-sm font-semibold text-rose-700">
            {error}
          </p>
        )}
        <div className="mt-4 grid gap-3 sm:grid-cols-4">
          <div className="rounded-lg bg-slate-50 p-3">
            <b>{data.summary.total}</b>
            <span className="ml-2 text-xs text-slate-500">total</span>
          </div>
          <div className="rounded-lg bg-amber-50 p-3">
            <b>{data.summary.open}</b>
            <span className="ml-2 text-xs text-slate-500">open</span>
          </div>
          <div className="rounded-lg bg-rose-50 p-3">
            <b>{data.summary.overdue}</b>
            <span className="ml-2 text-xs text-slate-500">overdue</span>
          </div>
          <div className="rounded-lg bg-emerald-50 p-3">
            <b>{data.summary.completed}</b>
            <span className="ml-2 text-xs text-slate-500">completed</span>
          </div>
        </div>
      </section>
      {canManage && (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="mb-3 flex items-center gap-2 font-bold text-slate-800">
            <Plus size={17} /> Add milestone
          </div>
          <div className="grid gap-3 md:grid-cols-4">
            <input
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
              placeholder="Code"
              value={form.milestoneCode}
              onChange={(e) =>
                setForm({ ...form, milestoneCode: e.target.value })
              }
            />
            <input
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
              placeholder="Milestone title"
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
            />
            <input
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
              type="date"
              value={form.dueDate}
              onChange={(e) => setForm({ ...form, dueDate: e.target.value })}
            />
          </div>
          <button
            className="mt-3 rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
            disabled={busy || !form.milestoneCode || !form.title}
            onClick={() => void add()}
            type="button"
          >
            Save milestone
          </button>
        </section>
      )}
      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h3 className="font-bold text-slate-800">Milestone register</h3>
          <button
            className="rounded-lg border border-slate-300 p-2"
            onClick={() => void load()}
            type="button"
          >
            <RefreshCw size={15} />
          </button>
        </div>
        <div className="divide-y divide-slate-100">
          {data.milestones.length === 0 && (
            <p className="p-5 text-sm text-slate-500">
              No milestones have been registered.
            </p>
          )}
          {data.milestones.map((milestone) => (
            <div
              className="flex flex-wrap items-center justify-between gap-3 p-4"
              key={milestone.id}
            >
              <div>
                <p className="text-xs font-bold uppercase text-slate-400">
                  {milestone.milestoneCode} · {milestone.categoryCode}
                </p>
                <p className="font-semibold text-slate-800">
                  {milestone.title}
                </p>
                <p className="text-xs text-slate-500">
                  Due {milestone.dueDate || "—"} · {milestone.statusCode}
                </p>
              </div>
              {canManage &&
                !["COMPLETED", "WAIVED", "CANCELLED"].includes(
                  milestone.statusCode,
                ) && (
                  <button
                    className="inline-flex items-center gap-1 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white"
                    disabled={busy}
                    onClick={() => void complete(milestone)}
                    type="button"
                  >
                    <CheckCircle2 size={14} /> Complete
                  </button>
                )}
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}
