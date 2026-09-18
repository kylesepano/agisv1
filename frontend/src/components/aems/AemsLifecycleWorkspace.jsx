import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  Check,
  Circle,
  ExternalLink,
  GitBranch,
  LoaderCircle,
  LockKeyhole,
} from "lucide-react";
import { Link } from "react-router";
import Modal from "../ui/Modal";
import StatusBadge from "../ui/StatusBadge";
import { aemsLifecycleApi } from "../../services/api";

const requiredDetails = {
  SUSPEND: [
    "authority",
    "effectiveDate",
    "expectedReviewDate",
    "resumeRequirements",
  ],
  CANCEL: ["authority", "effectOnIap", "workProductDisposition"],
};

const fieldLabels = {
  authority: "Approving authority",
  effectiveDate: "Effective date",
  expectedReviewDate: "Expected review date",
  resumeRequirements: "Resume requirements",
  effectOnIap: "Effect on IAP",
  workProductDisposition: "Disposition of work products and documents",
};

function pretty(value) {
  return value
    ?.replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function AemsLifecycleWorkspace({ engagementId }) {
  const [workspace, setWorkspace] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [conflict, setConflict] = useState(false);
  const [selected, setSelected] = useState(null);
  const [details, setDetails] = useState({});
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setWorkspace(await aemsLifecycleApi.show(engagementId));
      setConflict(false);
    } catch (reason) {
      setError(reason.message);
    } finally {
      setLoading(false);
    }
  }, [engagementId]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  async function submit() {
    setSaving(true);
    setError("");
    try {
      setWorkspace(
        await aemsLifecycleApi.transition(engagementId, selected.action, {
          ...details,
          lockVersion: workspace.engagement.lockVersion,
        }),
      );
      setSelected(null);
      setDetails({});
    } catch (reason) {
      setError(reason.message);
      setConflict(
        reason.status === 409 ||
          Object.prototype.hasOwnProperty.call(
            reason.errors ?? {},
            "lockVersion",
          ),
      );
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <div
        className="grid min-h-72 place-items-center"
        data-testid="lifecycle-loading"
      >
        <LoaderCircle className="animate-spin text-sky-700" size={30} />
      </div>
    );
  }

  if (!workspace) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700">
        {error || "The lifecycle workspace could not be loaded."}
      </div>
    );
  }

  const currentIndex = workspace.states.indexOf(workspace.engagement.status);

  return (
    <div className="space-y-5" data-testid="aems-lifecycle-workspace">
      {error && (
        <div
          className={`rounded-xl border p-4 text-sm font-semibold ${
            conflict
              ? "border-amber-300 bg-amber-50 text-amber-800"
              : "border-red-200 bg-red-50 text-red-700"
          }`}
          role="alert"
        >
          {conflict && (
            <strong className="mb-1 block">Stale-state conflict</strong>
          )}
          {error}
          {conflict && (
            <button className="ml-2 underline" onClick={load} type="button">
              Refresh lifecycle
            </button>
          )}
        </div>
      )}

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
              Aggregate engagement lifecycle
            </p>
            <h2 className="mt-1 text-lg font-bold text-slate-900">
              {workspace.engagement.engagementCode}
            </h2>
          </div>
          <StatusBadge tone="info">
            {pretty(workspace.engagement.status)}
          </StatusBadge>
        </div>
        <ol
          aria-label="Engagement status timeline"
          className="mt-6 grid gap-2 sm:grid-cols-2 xl:grid-cols-5"
          data-testid="lifecycle-timeline"
        >
          {workspace.states.map((state, index) => {
            const active = state === workspace.engagement.status;
            const passed = currentIndex >= 0 && index < currentIndex;
            return (
              <li
                className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold ${
                  active
                    ? "border-sky-500 bg-sky-50 text-sky-800"
                    : passed
                      ? "border-emerald-200 bg-emerald-50 text-emerald-700"
                      : "border-slate-200 text-slate-500"
                }`}
                key={state}
              >
                {passed ? (
                  <Check size={15} />
                ) : (
                  <Circle fill={active ? "currentColor" : "none"} size={14} />
                )}
                {pretty(state)}
              </li>
            );
          })}
        </ol>
      </section>

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center gap-2">
            <GitBranch className="text-sky-700" size={19} />
            <h3 className="font-bold text-slate-900">
              Allowed actions and gates
            </h3>
          </div>
          <div className="mt-4 space-y-3" data-testid="lifecycle-actions">
            {workspace.actions.length ? (
              workspace.actions.map((action) => (
                <article
                  className="rounded-xl border border-slate-200 p-4"
                  key={action.action}
                >
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <h4 className="font-bold text-slate-800">
                        {action.label}
                      </h4>
                      <p className="mt-1 text-xs text-slate-500">
                        Target: {pretty(action.targetStatus)}
                      </p>
                    </div>
                    <button
                      className="rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                      disabled={!action.canExecute}
                      onClick={() => {
                        setSelected(action);
                        setDetails({});
                      }}
                      type="button"
                    >
                      Continue
                    </button>
                  </div>
                  <ul className="mt-3 space-y-1.5">
                    {action.requirements.map((requirement) => (
                      <li
                        className={`flex items-start gap-2 text-xs ${
                          requirement.met
                            ? "text-emerald-700"
                            : "text-amber-800"
                        }`}
                        key={requirement.key}
                      >
                        {requirement.met ? (
                          <Check size={14} />
                        ) : (
                          <AlertTriangle size={14} />
                        )}
                        <span>{requirement.label}</span>
                      </li>
                    ))}
                  </ul>
                </article>
              ))
            ) : (
              <p className="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">
                No lifecycle action is available to your role at this stage.
              </p>
            )}
          </div>
        </section>

        <aside className="space-y-5">
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">Related records</h3>
            <div className="mt-3 space-y-2">
              {workspace.relatedLinks.map((item) => (
                <Link
                  className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50"
                  key={item.path}
                  to={item.path}
                >
                  {item.label} <ExternalLink size={14} />
                </Link>
              ))}
            </div>
          </section>
          {workspace.closureDeferred ? (
            <section className="rounded-xl border border-amber-200 bg-amber-50 p-5">
              <div className="flex items-center gap-2 text-amber-800">
                <LockKeyhole size={18} />
                <h3 className="font-bold">Formal closure deferred</h3>
              </div>
              <p className="mt-2 text-xs leading-5 text-amber-800">
                Pre-closure review is available. Closure approval remains locked
                until the formal closure workflow and records are implemented.
              </p>
            </section>
          ) : (
            <section className="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
              <div className="flex items-center gap-2 text-emerald-800">
                <LockKeyhole size={18} />
                <h3 className="font-bold">Formal closure available</h3>
              </div>
              <p className="mt-2 text-xs leading-5 text-emerald-800">
                Completion Assessment, Closure review, records indexing,
                retention, and exceptional reopening are controlled in separate
                workspace tabs.
              </p>
              <Link
                className="mt-3 inline-flex rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800"
                to={`/audit-engagement-management/${engagementId}?tab=closure`}
              >
                Open Closure
              </Link>
            </section>
          )}
        </aside>
      </div>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 className="font-bold text-slate-900">
          Immutable transition history
        </h3>
        <ol className="mt-4 space-y-3">
          {workspace.timeline.length ? (
            workspace.timeline.map((event) => (
              <li
                className="border-l-2 border-sky-200 pl-3 text-sm"
                key={event.id}
              >
                <strong className="text-slate-800">
                  {pretty(event.action)}
                </strong>
                <span className="ml-2 text-xs text-slate-500">
                  {event.actor?.name} ·{" "}
                  {new Date(event.createdAt).toLocaleString()}
                </span>
                <p className="text-xs text-slate-600">
                  {pretty(event.fromStatus)} → {pretty(event.toStatus)}
                  {event.comment ? ` · ${event.comment}` : ""}
                </p>
              </li>
            ))
          ) : (
            <li className="text-sm text-slate-500">
              No aggregate transition has been recorded.
            </li>
          )}
        </ol>
      </section>

      <Modal
        description={`Move the engagement to ${pretty(selected?.targetStatus)}. The server will re-check every gate.`}
        onClose={() => !saving && setSelected(null)}
        open={Boolean(selected)}
        title={selected?.label ?? "Lifecycle action"}
        footer={
          <>
            <button
              className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setSelected(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={submit}
              type="button"
            >
              {saving ? "Processing…" : "Confirm action"}
            </button>
          </>
        }
      >
        <div className="space-y-4">
          {(selected?.requiresComment ||
            ["SUSPEND", "CANCEL"].includes(selected?.action)) && (
            <label className="block text-sm font-semibold text-slate-700">
              Comment / reason
              <textarea
                className="mt-1.5 min-h-24 w-full rounded-lg border border-slate-300 p-3"
                onChange={(event) =>
                  setDetails((value) => ({
                    ...value,
                    comment: event.target.value,
                  }))
                }
                required
                value={details.comment ?? ""}
              />
            </label>
          )}
          {(requiredDetails[selected?.action] ?? []).map((field) => (
            <label
              className="block text-sm font-semibold text-slate-700"
              key={field}
            >
              {fieldLabels[field]}
              {field.toLowerCase().includes("date") ? (
                <input
                  className="mt-1.5 w-full rounded-lg border border-slate-300 p-2.5"
                  onChange={(event) =>
                    setDetails((value) => ({
                      ...value,
                      [field]: event.target.value,
                    }))
                  }
                  type="date"
                  value={details[field] ?? ""}
                />
              ) : (
                <textarea
                  className="mt-1.5 min-h-20 w-full rounded-lg border border-slate-300 p-3"
                  onChange={(event) =>
                    setDetails((value) => ({
                      ...value,
                      [field]: event.target.value,
                    }))
                  }
                  value={details[field] ?? ""}
                />
              )}
            </label>
          ))}
        </div>
      </Modal>
    </div>
  );
}
