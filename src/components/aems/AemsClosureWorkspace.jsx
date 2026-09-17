import { useCallback, useEffect, useState } from "react";
import {
  AlertOctagon,
  Archive,
  CheckCircle2,
  ClipboardCheck,
  ExternalLink,
  LockKeyhole,
  RefreshCw,
  RotateCcw,
  Send,
  ShieldAlert,
  Undo2,
} from "lucide-react";
import { Link } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsClosureApi, aemsReopenApi } from "../../services/api";

function resultTone(result) {
  if (result === "PASS") return "bg-emerald-100 text-emerald-700";
  if (result === "WARNING") return "bg-amber-100 text-amber-700";
  if (result === "NOT_APPLICABLE") return "bg-slate-100 text-slate-600";
  return "bg-rose-100 text-rose-700";
}

export default function AemsClosureWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [workspace, setWorkspace] = useState(null);
  const [reopenRequests, setReopenRequests] = useState([]);
  const [summary, setSummary] = useState("");
  const [comment, setComment] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const data = await aemsClosureApi.show(engagementId);
      setWorkspace(data);
      setSummary(data.closure?.closureSummary ?? "");
      if (data.engagement?.isClosed) {
        setReopenRequests(await aemsReopenApi.list(engagementId));
      } else {
        setReopenRequests([]);
      }
    } catch (reason) {
      setError(reason.message);
    } finally {
      setLoading(false);
    }
  }, [engagementId]);

  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  async function act(operation, message) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await operation();
      setNotice(message);
      await load();
    } catch (reason) {
      setError(reason.message);
    } finally {
      setBusy(false);
    }
  }

  async function transition(action) {
    const closure = workspace.closure;
    await act(
      () =>
        aemsClosureApi.transition(engagementId, closure.id, action, {
          lockVersion: closure.lockVersion,
          engagementLockVersion: workspace.engagement.lockVersion,
          comment: comment || undefined,
        }),
      `${action.replaceAll("_", " ")} completed.`,
    );
    setComment("");
  }

  if (loading) {
    return (
      <div
        className="grid min-h-72 place-items-center"
        data-testid="closure-loading"
      >
        <RefreshCw className="animate-spin text-sky-700" size={28} />
      </div>
    );
  }

  if (!workspace) {
    return (
      <div className="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm font-semibold text-rose-700">
        {error || "Closure workspace is unavailable."}
      </div>
    );
  }

  const { engagement, closure, readiness, evaluatedChecklist, cms } = workspace;
  const actions = closure ? (workspace.permittedActions ?? []) : [];

  return (
    <div className="space-y-5" data-testid="closure-workspace">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              {engagement.isClosed ? (
                <LockKeyhole className="text-emerald-700" size={21} />
              ) : (
                <ClipboardCheck className="text-sky-700" size={21} />
              )}
              <h3 className="text-base font-bold text-slate-900">
                Formal Engagement Closure
              </h3>
            </div>
            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
              Tracker readiness is advisory. Final closure requires the current
              approved Completion Assessment, an approved Closure record, and an
              atomic re-evaluation of every authoritative gate.
            </p>
          </div>
          <div className="text-right">
            <strong className="block text-3xl text-sky-800">
              {readiness.percentage}%
            </strong>
            <span className="text-xs font-bold uppercase text-slate-400">
              Formal readiness
            </span>
          </div>
        </div>
        <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
          <div
            className={`h-full rounded-full ${
              readiness.ready ? "bg-emerald-500" : "bg-amber-500"
            }`}
            style={{ width: `${readiness.percentage}%` }}
          />
        </div>
        {engagement.isClosed && (
          <div className="mt-4 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            <LockKeyhole className="shrink-0" size={19} />
            <div>
              <strong className="block">Immutable closed engagement</strong>
              Official child records and the final document index are locked.
              Authorized downloads remain available; archive remains a separate
              records action.
            </div>
          </div>
        )}
        {error && (
          <div className="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {error}
          </div>
        )}
        {notice && (
          <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {notice}
          </div>
        )}
      </section>

      <div className="grid gap-4 lg:grid-cols-4">
        {[
          [
            "Completion assessment",
            closure?.completionAssessmentStatus ?? "MISSING",
          ],
          ["CMS disposition", `${cms.transferred + cms.excluded}/${cms.total}`],
          [
            "Final document index",
            closure?.documentIndexLockedAt
              ? "LOCKED"
              : closure?.readiness?.ready
                ? "READY"
                : "OPEN",
          ],
          ["Closure status", closure?.statusCode ?? "NOT CREATED"],
        ].map(([label, value]) => (
          <div
            className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            key={label}
          >
            <span className="text-xs font-bold uppercase text-slate-400">
              {label}
            </span>
            <strong className="mt-2 block text-sm text-slate-800">
              {value}
            </strong>
          </div>
        ))}
      </div>

      {!closure && (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <h3 className="font-bold text-slate-900">Create Closure record</h3>
          {engagement.status !== "CLOSURE_REVIEW" ? (
            <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
              The engagement must first pass the lifecycle{" "}
              <strong>SUBMIT FOR CLOSURE</strong> transition into
              CLOSURE_REVIEW.
            </div>
          ) : (
            <>
              <textarea
                className="mt-3 min-h-28 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                onChange={(event) => setSummary(event.target.value)}
                placeholder="Summarize completed work, issued results, residual matters, and the basis for closure."
                value={summary}
              />
              {hasPermission(user, "aems.closure.create") && (
                <button
                  className="mt-3 inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
                  disabled={busy || !summary.trim()}
                  onClick={() =>
                    void act(
                      () =>
                        aemsClosureApi.create(engagementId, {
                          closureSummary: summary,
                        }),
                      "Formal Closure record created.",
                    )
                  }
                  type="button"
                >
                  <ClipboardCheck size={16} /> Create Closure
                </button>
              )}
            </>
          )}
        </section>
      )}

      {closure && (
        <>
          <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-5">
              <div>
                <h3 className="font-bold text-slate-900">
                  Authoritative closure checklist
                </h3>
                <p className="mt-1 text-xs text-slate-500">
                  Results are regenerated from source records. They cannot be
                  changed with manual checkboxes.
                </p>
              </div>
              {hasPermission(user, "aems.closure.update") &&
                !["APPROVED", "CLOSED"].includes(closure.statusCode) && (
                  <button
                    className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700"
                    disabled={busy}
                    onClick={() =>
                      void act(
                        () =>
                          aemsClosureApi.refreshChecklist(
                            engagementId,
                            closure.id,
                          ),
                        "Checklist refreshed from authoritative records.",
                      )
                    }
                    type="button"
                  >
                    <RefreshCw size={14} /> Refresh checklist
                  </button>
                )}
            </header>
            <div className="divide-y divide-slate-100">
              {evaluatedChecklist.map((item) => (
                <div
                  className="grid gap-3 px-4 py-3 sm:grid-cols-[9rem_minmax(0,1fr)_auto] sm:px-5"
                  key={item.checklistCode}
                >
                  <div>
                    <span
                      className={`inline-flex rounded-full px-2 py-1 text-[11px] font-bold ${resultTone(
                        item.resultCode,
                      )}`}
                    >
                      {item.resultCode.replaceAll("_", " ")}
                    </span>
                    <span className="mt-1 block text-[10px] font-bold uppercase text-slate-400">
                      {item.checklistCategoryCode}
                    </span>
                  </div>
                  <div>
                    <strong className="text-sm text-slate-800">
                      {item.description}
                    </strong>
                    <p className="mt-1 text-xs leading-5 text-slate-500">
                      {item.explanation}
                    </p>
                  </div>
                  {item.sourcePath && (
                    <Link
                      className="inline-flex items-center gap-1 self-center text-xs font-bold text-sky-700 hover:underline"
                      to={item.sourcePath}
                    >
                      Source <ExternalLink size={12} />
                    </Link>
                  )}
                </div>
              ))}
            </div>
          </section>

          {readiness.blockers.length > 0 && (
            <section className="rounded-xl border border-rose-200 bg-rose-50 p-4">
              <div className="flex items-center gap-2 text-rose-800">
                <AlertOctagon size={18} />
                <h3 className="font-bold">
                  {readiness.blockers.length} blocking requirement(s)
                </h3>
              </div>
              <ul className="mt-3 space-y-2 text-sm text-rose-700">
                {readiness.blockers.map((item) => (
                  <li key={item.checklistCode}>• {item.description}</li>
                ))}
              </ul>
            </section>
          )}

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h3 className="font-bold text-slate-900">
              Controlled Closure actions
            </h3>
            <textarea
              className="mt-3 min-h-20 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              onChange={(event) => setComment(event.target.value)}
              placeholder="Required return, resubmission, or closure comment"
              value={comment}
            />
            <div className="mt-3 flex flex-wrap gap-2">
              {actions.includes("SUBMIT_CLOSURE") && (
                <button
                  className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-violet-700 px-4 text-sm font-bold text-white"
                  disabled={busy}
                  onClick={() => void transition("SUBMIT_CLOSURE")}
                  type="button"
                >
                  <Send size={15} /> Submit Closure
                </button>
              )}
              {actions.includes("RESUBMIT_CLOSURE") && (
                <button
                  className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-violet-700 px-4 text-sm font-bold text-white"
                  disabled={busy || !comment.trim()}
                  onClick={() => void transition("RESUBMIT_CLOSURE")}
                  type="button"
                >
                  <Send size={15} /> Resubmit Closure
                </button>
              )}
              {actions.includes("RETURN_CLOSURE") && (
                <button
                  className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-amber-300 px-4 text-sm font-bold text-amber-700"
                  disabled={busy || !comment.trim()}
                  onClick={() => void transition("RETURN_CLOSURE")}
                  type="button"
                >
                  <Undo2 size={15} /> Return
                </button>
              )}
              {actions.includes("APPROVE_CLOSURE") && (
                <button
                  className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white"
                  disabled={busy}
                  onClick={() => void transition("APPROVE_CLOSURE")}
                  type="button"
                >
                  <CheckCircle2 size={15} /> Approve Closure
                </button>
              )}
              {actions.includes("CLOSE_ENGAGEMENT") && (
                <button
                  className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-bold text-white"
                  disabled={busy}
                  onClick={() => {
                    if (
                      window.confirm(
                        "Re-evaluate every authoritative gate and permanently close this engagement?",
                      )
                    ) {
                      void transition("CLOSE_ENGAGEMENT");
                    }
                  }}
                  type="button"
                >
                  <LockKeyhole size={15} /> Close Engagement
                </button>
              )}
            </div>
          </section>

          {closure.timeline.length > 0 && (
            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
              <h3 className="font-bold text-slate-900">
                Closure status timeline
              </h3>
              <ol className="mt-4 space-y-3">
                {closure.timeline
                  .slice()
                  .reverse()
                  .map((event) => (
                    <li
                      className="border-l-2 border-sky-200 pl-4 text-sm"
                      key={event.id}
                    >
                      <strong className="text-slate-800">
                        {event.actionCode.replaceAll("_", " ")}
                      </strong>
                      <span className="ml-2 text-xs text-slate-400">
                        {new Date(event.occurredAt).toLocaleString()}
                      </span>
                      {event.comment && (
                        <p className="mt-1 text-slate-500">{event.comment}</p>
                      )}
                    </li>
                  ))}
              </ol>
            </section>
          )}
        </>
      )}

      {engagement.isClosed && (
        <section className="rounded-xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
          <div className="flex items-center gap-2 text-amber-900">
            <ShieldAlert size={20} />
            <h3 className="font-bold">Exceptional reopening</h3>
          </div>
          <p className="mt-2 text-sm leading-6 text-amber-800">
            Reopening requires dedicated authority, a mandatory reason, and an
            exact written-authority DocumentVersion. The original closed
            snapshot and Closure record remain immutable history.
          </p>
          {hasPermission(user, "aems.engagement.reopen_request") &&
            !reopenRequests.some((item) =>
              ["DRAFT", "PENDING_APPROVAL", "APPROVED"].includes(
                item.statusCode,
              ),
            ) && (
              <button
                className="mt-3 inline-flex min-h-10 items-center gap-2 rounded-lg bg-amber-700 px-4 text-sm font-bold text-white"
                disabled={busy}
                onClick={() => {
                  const reasonCode =
                    window.prompt(
                      "Reason code: AUTHORIZED_CORRECTION, SIGNIFICANT_ERROR, COURT_DIRECTION, OVERSIGHT_DIRECTION, or OTHER_APPROVED_AUTHORITY",
                    ) || "";
                  const reasonText =
                    window.prompt("Detailed reopening reason:") || "";
                  const authorityDocumentVersionId = Number(
                    window.prompt(
                      "Exact written-authority DocumentVersion ID:",
                    ),
                  );
                  if (!reasonCode || !reasonText || !authorityDocumentVersionId)
                    return;
                  void act(
                    () =>
                      aemsReopenApi.create(engagementId, {
                        reasonCode,
                        reasonText,
                        authorityDocumentVersionId,
                      }),
                    "Exceptional reopening request created.",
                  );
                }}
                type="button"
              >
                <RotateCcw size={15} /> Request reopening
              </button>
            )}
          <div className="mt-4 space-y-3">
            {reopenRequests.map((item) => (
              <div
                className="rounded-lg border border-amber-200 bg-white p-3"
                key={item.id}
              >
                <div className="flex flex-wrap justify-between gap-2">
                  <strong className="text-sm text-slate-800">
                    {item.requestCode} · {item.reasonCode}
                  </strong>
                  <span className="text-xs font-bold text-amber-700">
                    {item.statusCode.replaceAll("_", " ")}
                  </span>
                </div>
                <p className="mt-2 text-sm text-slate-600">{item.reasonText}</p>
                <div className="mt-3 flex flex-wrap gap-2">
                  {item.statusCode === "DRAFT" &&
                    hasPermission(user, "aems.engagement.reopen_request") && (
                      <button
                        className="rounded-lg bg-amber-700 px-3 py-2 text-xs font-bold text-white"
                        onClick={() =>
                          void act(
                            () =>
                              aemsReopenApi.transition(
                                engagementId,
                                item.id,
                                "SUBMIT_REOPEN_REQUEST",
                                { lockVersion: item.lockVersion },
                              ),
                            "Reopening request submitted.",
                          )
                        }
                        type="button"
                      >
                        Submit request
                      </button>
                    )}
                  {item.statusCode === "PENDING_APPROVAL" &&
                    hasPermission(user, "aems.engagement.reopen_approve") && (
                      <>
                        <button
                          className="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white"
                          onClick={() =>
                            void act(
                              () =>
                                aemsReopenApi.transition(
                                  engagementId,
                                  item.id,
                                  "APPROVE_REOPEN_REQUEST",
                                  { lockVersion: item.lockVersion },
                                ),
                              "Reopening request approved.",
                            )
                          }
                          type="button"
                        >
                          Approve
                        </button>
                        <button
                          className="rounded-lg border border-rose-300 px-3 py-2 text-xs font-bold text-rose-700"
                          onClick={() => {
                            const reviewComment =
                              window.prompt("Rejection reason:");
                            if (!reviewComment) return;
                            void act(
                              () =>
                                aemsReopenApi.transition(
                                  engagementId,
                                  item.id,
                                  "REJECT_REOPEN_REQUEST",
                                  {
                                    lockVersion: item.lockVersion,
                                    comment: reviewComment,
                                  },
                                ),
                              "Reopening request rejected.",
                            );
                          }}
                          type="button"
                        >
                          Reject
                        </button>
                      </>
                    )}
                  {item.statusCode === "APPROVED" &&
                    hasPermission(user, "aems.engagement.reopen_approve") && (
                      <button
                        className="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white"
                        onClick={() => {
                          const implementationComment = window.prompt(
                            "Implementation authority/comment:",
                          );
                          if (!implementationComment) return;
                          void act(
                            () =>
                              aemsReopenApi.transition(
                                engagementId,
                                item.id,
                                "IMPLEMENT_REOPEN_REQUEST",
                                {
                                  lockVersion: item.lockVersion,
                                  comment: implementationComment,
                                },
                              ),
                            "Controlled reopening revision implemented.",
                          );
                        }}
                        type="button"
                      >
                        <Archive size={13} /> Implement controlled revision
                      </button>
                    )}
                </div>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
