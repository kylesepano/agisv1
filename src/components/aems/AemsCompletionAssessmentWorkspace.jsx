import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  CheckCircle2,
  ClipboardCheck,
  History,
  RefreshCw,
  Save,
  Send,
  Undo2,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsCompletionAssessmentApi } from "../../services/api";

const emptyForm = {
  overallResultCode: "SATISFACTORY",
  objectivesAchievementSummary: "",
  scopeCompletionSummary: "",
  methodologyAssessment: "",
  standardsComplianceAssessment: "",
  evidenceSufficiencyAssessment: "",
  supervisionAssessment: "",
  reportTimelinessAssessment: "",
  managementResponseAssessment: "",
  recommendationTransferAssessment: "",
  resourceUtilizationAssessment: "",
  limitationsSummary: "",
  lessonsSummary: "",
  recommendationForClosure: "",
};

const fields = [
  ["objectivesAchievementSummary", "Objectives achievement"],
  ["scopeCompletionSummary", "Scope completion"],
  ["methodologyAssessment", "Methodology assessment"],
  ["standardsComplianceAssessment", "Standards compliance"],
  ["evidenceSufficiencyAssessment", "Evidence sufficiency"],
  ["supervisionAssessment", "Supervision"],
  ["reportTimelinessAssessment", "Report timeliness"],
  ["managementResponseAssessment", "Management-response dialogue"],
  ["recommendationTransferAssessment", "Recommendation transfer"],
  ["resourceUtilizationAssessment", "Resource utilization"],
  ["limitationsSummary", "Limitations and nonconformance"],
  ["lessonsSummary", "Lessons summary"],
  ["recommendationForClosure", "Recommendation for closure"],
];

function assessmentForm(assessment) {
  if (!assessment) return { ...emptyForm, items: [] };
  return {
    ...emptyForm,
    ...Object.fromEntries(
      Object.keys(emptyForm).map((key) => [key, assessment[key] ?? ""]),
    ),
    items: (assessment.items ?? []).map((item) => ({ ...item })),
  };
}

function tone(status) {
  if (status === "APPROVED" || status === "PASS") {
    return "border-emerald-200 bg-emerald-50 text-emerald-700";
  }
  if (["FAIL", "RETURNED_FOR_REVISION", "UNSATISFACTORY"].includes(status)) {
    return "border-rose-200 bg-rose-50 text-rose-700";
  }
  if (
    ["PARTIAL", "PENDING", "PENDING_REVIEW", "RESUBMITTED"].includes(status)
  ) {
    return "border-amber-200 bg-amber-50 text-amber-700";
  }
  return "border-slate-200 bg-slate-50 text-slate-600";
}

export default function AemsCompletionAssessmentWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [workspace, setWorkspace] = useState(null);
  const [form, setForm] = useState(() => assessmentForm(null));
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [comment, setComment] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const data = await aemsCompletionAssessmentApi.show(engagementId);
      setWorkspace(data);
      setForm(assessmentForm(data.currentAssessment));
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

  const assessment = workspace?.currentAssessment;
  const editable =
    !assessment ||
    ["DRAFT", "RETURNED_FOR_REVISION"].includes(assessment.statusCode);
  const canCreate = hasPermission(user, "aems.completion-assessment.create");
  const canUpdate = hasPermission(user, "aems.completion-assessment.update");
  const canSubmit = hasPermission(user, "aems.completion-assessment.submit");
  const canReview = hasPermission(user, "aems.completion-assessment.review");
  const canApprove = hasPermission(user, "aems.completion-assessment.approve");
  const unresolved = useMemo(
    () =>
      (assessment?.items ?? []).filter(
        (item) =>
          item.blockingFlag &&
          !["PASS", "NOT_APPLICABLE"].includes(item.resultCode) &&
          !item.blockerAccepted,
      ),
    [assessment],
  );

  async function act(operation, success) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await operation();
      setNotice(success);
      await load();
    } catch (reason) {
      setError(reason.message);
    } finally {
      setBusy(false);
    }
  }

  async function save() {
    const payload = {
      ...form,
      ...(assessment ? { lockVersion: assessment.lockVersion } : {}),
      items: form.items?.length
        ? form.items.map((item) => ({
            criterionCode: item.criterionCode,
            plannedValue: item.plannedValue,
            actualValue: item.actualValue,
            resultCode: item.resultCode,
            varianceValue: item.varianceValue,
            explanation: item.explanation,
            blockingFlag: item.blockingFlag,
            relatedRecordType: item.relatedRecordType,
            relatedRecordId: item.relatedRecordId,
            responsibleUserId: item.responsibleUser?.id,
          }))
        : undefined,
    };
    await act(
      () =>
        assessment
          ? aemsCompletionAssessmentApi.update(
              engagementId,
              assessment.id,
              payload,
            )
          : aemsCompletionAssessmentApi.create(engagementId, payload),
      assessment
        ? "Completion Assessment saved."
        : "Completion Assessment created with authoritative baseline criteria.",
    );
  }

  async function transition(action) {
    await act(
      () =>
        aemsCompletionAssessmentApi.transition(
          engagementId,
          assessment.id,
          action,
          {
            lockVersion: assessment.lockVersion,
            comment: comment || undefined,
          },
        ),
      `Completion Assessment ${action.toLowerCase()} completed.`,
    );
    setComment("");
  }

  function updateItem(index, key, value) {
    setForm((current) => ({
      ...current,
      items: current.items.map((item, itemIndex) =>
        itemIndex === index ? { ...item, [key]: value } : item,
      ),
    }));
  }

  if (loading) {
    return (
      <div
        className="grid min-h-72 place-items-center"
        data-testid="completion-assessment-loading"
      >
        <RefreshCw className="animate-spin text-sky-700" size={28} />
      </div>
    );
  }

  return (
    <div className="space-y-5" data-testid="completion-assessment-workspace">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <ClipboardCheck className="text-sky-700" size={21} />
              <h3 className="text-base font-bold text-slate-900">
                Completion Assessment
              </h3>
            </div>
            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
              Evaluate delivery against the approved objectives, plan,
              standards, evidence, communication, resources, and closure
              readiness. Approval does not close the engagement.
            </p>
          </div>
          {assessment && (
            <span
              className={`rounded-full border px-3 py-1 text-xs font-bold ${tone(
                assessment.statusCode,
              )}`}
            >
              {assessment.statusCode.replaceAll("_", " ")}
            </span>
          )}
        </div>

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

        {!assessment && !canCreate ? (
          <div className="mt-5 rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
            No Completion Assessment exists, and your role cannot create one.
          </div>
        ) : (
          <div className="mt-5 space-y-4">
            <label className="block">
              <span className="text-xs font-bold uppercase tracking-wide text-slate-500">
                Overall result
              </span>
              <select
                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
                disabled={!editable}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    overallResultCode: event.target.value,
                  }))
                }
                value={form.overallResultCode}
              >
                <option value="SATISFACTORY">Satisfactory</option>
                <option value="PARTIALLY_SATISFACTORY">
                  Partially satisfactory
                </option>
                <option value="UNSATISFACTORY">Unsatisfactory</option>
              </select>
            </label>
            <div className="grid gap-4 xl:grid-cols-2">
              {fields.map(([key, label]) => (
                <label className="block" key={key}>
                  <span className="text-xs font-bold uppercase tracking-wide text-slate-500">
                    {label}
                  </span>
                  <textarea
                    className="mt-1 min-h-24 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm leading-6"
                    disabled={!editable}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        [key]: event.target.value,
                      }))
                    }
                    required={
                      !["limitationsSummary", "lessonsSummary"].includes(key)
                    }
                    value={form[key]}
                  />
                </label>
              ))}
            </div>
            {editable && (canCreate || canUpdate) && (
              <button
                className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
                disabled={busy}
                onClick={save}
                type="button"
              >
                <Save size={16} />{" "}
                {assessment ? "Save assessment" : "Create assessment"}
              </button>
            )}
          </div>
        )}
      </section>

      {assessment && (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <header className="border-b border-slate-200 px-4 py-3 sm:px-5">
            <h3 className="font-bold text-slate-900">
              Required assessment areas
            </h3>
            <p className="mt-1 text-xs text-slate-500">
              All 25 criteria require a result before submission. Blocking
              failures require resolution or elevated formal acceptance.
            </p>
          </header>
          <div className="overflow-x-auto">
            <table className="min-w-[880px] w-full text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                  <th className="px-4 py-3">Criterion</th>
                  <th className="px-4 py-3">Planned / actual</th>
                  <th className="px-4 py-3">Result</th>
                  <th className="px-4 py-3">Explanation</th>
                  <th className="px-4 py-3">Gate</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {form.items.map((item, index) => (
                  <tr key={item.criterionCode}>
                    <td className="px-4 py-3 font-semibold text-slate-800">
                      {item.criterionCode.replaceAll("_", " ")}
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-500">
                      <div>Planned: {item.plannedValue || "—"}</div>
                      <div>Actual: {item.actualValue || "—"}</div>
                    </td>
                    <td className="px-4 py-3">
                      <select
                        className="rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold"
                        disabled={!editable}
                        onChange={(event) =>
                          updateItem(index, "resultCode", event.target.value)
                        }
                        value={item.resultCode}
                      >
                        <option value="PENDING">Pending</option>
                        <option value="PASS">Pass</option>
                        <option value="PARTIAL">Partial</option>
                        <option value="FAIL">Fail</option>
                        <option value="NOT_APPLICABLE">Not applicable</option>
                      </select>
                    </td>
                    <td className="px-4 py-3">
                      <textarea
                        className="min-h-16 w-full rounded-lg border border-slate-300 px-2 py-2 text-xs"
                        disabled={!editable}
                        onChange={(event) =>
                          updateItem(index, "explanation", event.target.value)
                        }
                        value={item.explanation}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex rounded-full border px-2 py-1 text-[11px] font-bold ${tone(
                          item.resultCode,
                        )}`}
                      >
                        {item.blockerAccepted
                          ? "ACCEPTED"
                          : item.blockingFlag
                            ? "BLOCKING"
                            : "ADVISORY"}
                      </span>
                      {canApprove &&
                        item.blockingFlag &&
                        !item.blockerAccepted &&
                        !["PASS", "NOT_APPLICABLE"].includes(
                          item.resultCode,
                        ) && (
                          <button
                            className="mt-2 block text-xs font-bold text-amber-700 underline"
                            disabled={busy}
                            onClick={() => {
                              const reason = window.prompt(
                                "State the elevated authority and acceptance reason:",
                              );
                              if (!reason) return;
                              void act(
                                () =>
                                  aemsCompletionAssessmentApi.acceptBlocker(
                                    engagementId,
                                    assessment.id,
                                    item.id,
                                    {
                                      lockVersion: assessment.lockVersion,
                                      reason,
                                    },
                                  ),
                                "Assessment blocker formally accepted.",
                              );
                            }}
                            type="button"
                          >
                            Accept with authority
                          </button>
                        )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {assessment && (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div className="flex items-center gap-2">
            <Send className="text-violet-700" size={19} />
            <h3 className="font-bold text-slate-900">Controlled review</h3>
          </div>
          {unresolved.length > 0 && (
            <div className="mt-3 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
              <AlertTriangle className="mt-0.5 shrink-0" size={17} />
              {unresolved.length} unresolved blocking assessment item(s) prevent
              approval.
            </div>
          )}
          <textarea
            className="mt-4 min-h-20 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            onChange={(event) => setComment(event.target.value)}
            placeholder="Review or return comment"
            value={comment}
          />
          <div className="mt-3 flex flex-wrap gap-2">
            {canSubmit && assessment.statusCode === "DRAFT" && (
              <button
                className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-violet-700 px-4 text-sm font-bold text-white"
                disabled={busy}
                onClick={() => void transition("SUBMIT")}
                type="button"
              >
                <Send size={15} /> Submit
              </button>
            )}
            {canSubmit && assessment.statusCode === "RETURNED_FOR_REVISION" && (
              <button
                className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-violet-700 px-4 text-sm font-bold text-white"
                disabled={busy}
                onClick={() => void transition("RESUBMIT")}
                type="button"
              >
                <Send size={15} /> Resubmit
              </button>
            )}
            {canReview &&
              ["PENDING_REVIEW", "RESUBMITTED"].includes(
                assessment.statusCode,
              ) && (
                <button
                  className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-amber-300 px-4 text-sm font-bold text-amber-700"
                  disabled={busy || !comment.trim()}
                  onClick={() => void transition("RETURN")}
                  type="button"
                >
                  <Undo2 size={15} /> Return
                </button>
              )}
            {canApprove &&
              ["PENDING_REVIEW", "RESUBMITTED"].includes(
                assessment.statusCode,
              ) && (
                <button
                  className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white"
                  disabled={busy || unresolved.length > 0}
                  onClick={() => void transition("APPROVE")}
                  type="button"
                >
                  <CheckCircle2 size={15} /> Approve
                </button>
              )}
            {canCreate && assessment.statusCode === "APPROVED" && (
              <button
                className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
                disabled={busy}
                onClick={() => {
                  const reason = window.prompt(
                    "State the controlled correction reason:",
                  );
                  if (!reason) return;
                  void act(
                    () =>
                      aemsCompletionAssessmentApi.revise(
                        engagementId,
                        assessment.id,
                        { reason },
                      ),
                    "Controlled assessment revision created.",
                  );
                }}
                type="button"
              >
                <History size={15} /> Create correction revision
              </button>
            )}
          </div>
        </section>
      )}
    </div>
  );
}
