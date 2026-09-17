import { useCallback, useEffect, useMemo, useState } from "react";
import { Link2, RefreshCw, ShieldCheck, TriangleAlert } from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { baicsApi, userApi } from "../../services/api";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { useToast } from "../../ui/toast-context";

const TYPES = [
  ["UNIVERSE_RISK_ASSESSMENT", "Risk assessment"],
  ["RISK_PERIOD", "Risk assessment period"],
  ["PRIORITIZATION_RUN", "Prioritization run"],
  ["STRATEGIC_PLAN", "Strategic audit plan"],
  ["ANNUAL_PLAN", "Annual audit plan"],
  ["ANNUAL_PLAN_ENGAGEMENT", "Annual-plan engagement"],
];

const EMPTY = {
  consumerType: "UNIVERSE_RISK_ASSESSMENT",
  consumerId: "",
  decisionType: "BAICS_BACKED",
  reportId: "",
  reportVersionId: "",
  decisionReason: "",
  legacyReason: "",
  compensatingSource: "",
  reviewerId: "",
  authorityUserId: "",
  expiresAt: "",
};

function tone(status) {
  if (["APPROVED"].includes(status)) return "success";
  if (["PENDING_REVIEW", "RETURNED"].includes(status)) return "warning";
  if (["RETIRED"].includes(status)) return "danger";
  return "slate";
}
function label(value) {
  return String(value ?? "").replaceAll("_", " ");
}

export default function IapBaicsIntegrationPage() {
  const { user } = useAuth();
  const toast = useToast();
  const can = (...permissions) =>
    permissions.some((permission) => hasPermission(user, permission));
  const canManage = can(
    "iap.baics.integration.create",
    "iap.baics.integration.update",
    "iap.baics.manage-controls",
    "iap.baics.update",
  );
  const canApprove = can("iap.baics.integration.approve", "iap.baics.approve");
  const [assessments, setAssessments] = useState([]);
  const [selected, setSelected] = useState(null);
  const [integrations, setIntegrations] = useState([]);
  const [candidates, setCandidates] = useState(null);
  const [reports, setReports] = useState([]);
  const [users, setUsers] = useState([]);
  const [editor, setEditor] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [list, candidateData, userList] = await Promise.all([
        baicsApi.list({ perPage: 100 }),
        baicsApi.integrationCandidates(),
        userApi.list(),
      ]);
      const cycles = list.assessments ?? [];
      setAssessments(cycles);
      setCandidates(candidateData);
      setUsers(userList);
      setSelected((current) => current ?? cycles[0] ?? null);
    } catch (e) {
      setError(
        e instanceof Error
          ? e.message
          : "Unable to load BAICS integration data.",
      );
    } finally {
      setLoading(false);
    }
  }, []);
  useEffect(() => {
    const timer = window.setTimeout(() => {
      void load();
    }, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const selectCycle = useCallback(
    async (cycle) => {
      setSelected(cycle);
      try {
        const [linked, reportResult] = await Promise.all([
          baicsApi.integrations(cycle.id),
          baicsApi.reports(cycle.id),
        ]);
        setIntegrations(linked);
        setReports(reportResult.reports ?? []);
      } catch (e) {
        toast.error(
          e instanceof Error
            ? e.message
            : "Unable to load integration decisions.",
        );
      }
    },
    [toast],
  );
  useEffect(() => {
    if (!selected) return undefined;
    const timer = window.setTimeout(() => {
      void selectCycle(selected);
    }, 0);
    return () => window.clearTimeout(timer);
  }, [selected?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const options = useMemo(
    () =>
      candidates?.[
        `${editor?.consumerType === "RISK_PERIOD" ? "riskPeriods" : editor?.consumerType === "UNIVERSE_RISK_ASSESSMENT" ? "riskAssessments" : editor?.consumerType === "PRIORITIZATION_RUN" ? "prioritizations" : editor?.consumerType === "STRATEGIC_PLAN" ? "strategicPlans" : editor?.consumerType === "ANNUAL_PLAN" ? "annualPlans" : "annualPlanEngagements"}`
      ] ?? [],
    [candidates, editor?.consumerType],
  );
  async function save(event) {
    event.preventDefault();
    if (!selected || !editor) return;
    try {
      await baicsApi.createIntegration(selected.id, {
        ...editor,
        consumerId: Number(editor.consumerId),
        reviewerId: Number(editor.reviewerId),
        authorityUserId: Number(editor.authorityUserId),
        reportId: editor.reportId ? Number(editor.reportId) : undefined,
        reportVersionId: editor.reportVersionId
          ? Number(editor.reportVersionId)
          : undefined,
      });
      toast.success("Integration decision drafted.");
      setEditor(null);
      await selectCycle(selected);
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : "Unable to save integration decision.",
      );
    }
  }
  async function transition(record, action) {
    try {
      await baicsApi.transitionIntegration(record.id, action, {
        comment:
          action === "RETURN" || action === "RETIRE"
            ? (window.prompt("Reason") ?? "")
            : undefined,
      });
      toast.success(`Integration ${action.toLowerCase()}d.`);
      await selectCycle(selected);
    } catch (e) {
      toast.error(
        e instanceof Error
          ? e.message
          : "Unable to update integration decision.",
      );
    }
  }

  return (
    <div className="space-y-5 p-4 sm:p-6">
      <RegistryHeader
        icon={Link2}
        eyebrow="Internal Audit Planning"
        title="BAICS IAP Integration"
        description="Link approved BAICS baselines or time-limited legacy decisions to IAP risk, prioritization, strategic, and annual-plan records without mutating their source data."
        actions={
          <button
            type="button"
            onClick={load}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold"
          >
            <RefreshCw size={15} /> Refresh
          </button>
        }
      />
      <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 className="text-base font-bold text-slate-900">
              BAICS baseline consumer
            </h2>
            <p className="text-xs text-slate-500">
              Choose the assessment cycle whose approved BAR or legacy exception
              will be recorded.
            </p>
          </div>
          <select
            value={selected?.id ?? ""}
            onChange={(e) => {
              const cycle = assessments.find(
                (item) => String(item.id) === e.target.value,
              );
              if (cycle) setSelected(cycle);
            }}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm md:max-w-xl"
          >
            <option value="">Select BAICS cycle</option>
            {assessments.map((item) => (
              <option key={item.id} value={item.id}>
                {item.assessmentCode} · {item.name} · {item.status}
              </option>
            ))}
          </select>
        </div>
        {error && (
          <div className="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {error}
          </div>
        )}
        {loading && (
          <p className="mt-3 text-sm text-slate-500">
            Loading integration candidates…
          </p>
        )}
      </section>
      {selected && (
        <>
          <section className="grid gap-3 md:grid-cols-3">
            <div
              className={`rounded-xl border p-4 ${candidates?.enforcementEnabled ? "border-amber-200 bg-amber-50" : "border-slate-200 bg-white"}`}
            >
              <div className="flex items-center gap-2">
                <ShieldCheck size={18} className="text-sky-700" />
                <strong className="text-sm">Approval gate</strong>
              </div>
              <p className="mt-2 text-xs text-slate-600">
                {candidates?.enforcementEnabled
                  ? "Enabled: IAP approvals require an approved BAICS decision."
                  : "Staged: enable baics_integration_required after legacy reconciliation."}
              </p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4">
              <strong className="text-sm">Linked decisions</strong>
              <p className="mt-2 text-2xl font-bold text-slate-900">
                {integrations.length}
              </p>
              <span className="text-xs text-slate-500">
                For this BAICS cycle
              </span>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4">
              <strong className="text-sm">ARMIS boundary</strong>
              <p className="mt-2 text-xs text-slate-600">
                {candidates?.provider?.provider ??
                  "Provider status unavailable"}
              </p>
              <span className="text-xs text-slate-500">
                Read-only provider snapshot; no resource writes
              </span>
            </div>
          </section>
          <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-slate-200 p-4">
              <div>
                <h2 className="text-base font-bold text-slate-900">
                  IAP lineage decisions
                </h2>
                <p className="text-xs text-slate-500">
                  Reviewable decisions preserve the exact BAICS report version
                  used by each consumer.
                </p>
              </div>
              {canManage && (
                <button
                  type="button"
                  onClick={() =>
                    setEditor({ ...EMPTY, reviewerId: "", authorityUserId: "" })
                  }
                  className="rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white"
                >
                  New decision
                </button>
              )}
            </div>
            <div className="divide-y divide-slate-100">
              {integrations.length === 0 ? (
                <div className="p-8 text-center text-sm text-slate-500">
                  No integration decisions recorded for this cycle.
                </div>
              ) : (
                integrations.map((record) => (
                  <article key={record.id} className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <strong className="text-sm text-slate-900">
                            {record.integrationCode}
                          </strong>
                          <StatusBadge tone={tone(record.status)}>
                            {label(record.status)}
                          </StatusBadge>
                          <span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600">
                            {label(record.consumerType)}
                          </span>
                          <span className="rounded-full bg-violet-100 px-2 py-1 text-[10px] font-semibold text-violet-700">
                            {label(record.decisionType)}
                          </span>
                        </div>
                        <p className="mt-2 text-xs text-slate-600">
                          {record.consumerSnapshot?.code ??
                            record.consumerSnapshot?.subjectCode ??
                            record.consumerSnapshot?.title ??
                            `Consumer #${record.consumerId}`}{" "}
                          ·{" "}
                          {record.sourceSnapshot?.reportCode
                            ? `${record.sourceSnapshot.reportCode} v${record.sourceSnapshot.reportVersion}`
                            : "Legacy exception"}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                          Reviewer: {record.reviewer?.name ?? "—"} · Authority:{" "}
                          {record.authority?.name ?? "—"} · Expires:{" "}
                          {record.expiresAt ?? "No expiry"}
                        </p>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        {record.status === "DRAFT" && (
                          <button
                            type="button"
                            onClick={() => transition(record, "SUBMIT")}
                            className="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white"
                          >
                            Submit
                          </button>
                        )}
                        {record.status === "PENDING_REVIEW" && canApprove && (
                          <>
                            <button
                              type="button"
                              onClick={() => transition(record, "RETURN")}
                              className="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-800"
                            >
                              Return
                            </button>
                            <button
                              type="button"
                              onClick={() => transition(record, "REVIEW")}
                              className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold"
                            >
                              Review
                            </button>
                            <button
                              type="button"
                              onClick={() => transition(record, "APPROVE")}
                              className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white"
                            >
                              Approve
                            </button>
                          </>
                        )}
                        {record.status === "APPROVED" && canManage && (
                          <button
                            type="button"
                            onClick={() => transition(record, "RETIRE")}
                            className="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700"
                          >
                            Retire
                          </button>
                        )}
                      </div>
                    </div>
                  </article>
                ))
              )}
            </div>
          </section>
        </>
      )}
      {editor && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 p-4">
          <form
            onSubmit={save}
            className="mx-auto max-w-3xl rounded-2xl bg-white p-5 shadow-xl"
          >
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-slate-900">
                New IAP integration decision
              </h2>
              <button
                type="button"
                onClick={() => setEditor(null)}
                className="text-sm text-slate-500"
              >
                Close
              </button>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <label className="text-xs font-semibold text-slate-600">
                Consumer type
                <select
                  value={editor.consumerType}
                  onChange={(e) =>
                    setEditor({
                      ...editor,
                      consumerType: e.target.value,
                      consumerId: "",
                    })
                  }
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  {TYPES.map(([value, name]) => (
                    <option key={value} value={value}>
                      {name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="text-xs font-semibold text-slate-600">
                Consumer
                <select
                  value={editor.consumerId}
                  onChange={(e) =>
                    setEditor({ ...editor, consumerId: e.target.value })
                  }
                  required
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select consumer</option>
                  {options.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.code ??
                        item.subjectCode ??
                        item.title ??
                        `#${item.id}`}{" "}
                      · {item.name ?? item.status}
                    </option>
                  ))}
                </select>
              </label>
              <label className="text-xs font-semibold text-slate-600">
                Decision type
                <select
                  value={editor.decisionType}
                  onChange={(e) =>
                    setEditor({ ...editor, decisionType: e.target.value })
                  }
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="BAICS_BACKED">BAICS-backed baseline</option>
                  <option value="LEGACY_EXCEPTION">
                    Legacy / exception decision
                  </option>
                </select>
              </label>
              {editor.decisionType === "BAICS_BACKED" ? (
                <>
                  <label className="text-xs font-semibold text-slate-600">
                    Approved BAR
                    <select
                      value={editor.reportId}
                      onChange={(e) => {
                        const report = reports.find(
                          (item) => String(item.id) === e.target.value,
                        );
                        setEditor({
                          ...editor,
                          reportId: e.target.value,
                          reportVersionId: report?.latestVersion?.id ?? "",
                        });
                      }}
                      required
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                      <option value="">Select approved BAR</option>
                      {reports
                        .filter((item) =>
                          ["APPROVED", "ISSUED"].includes(item.status),
                        )
                        .map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.reportCode} · {item.status}
                          </option>
                        ))}
                    </select>
                  </label>
                  <label className="text-xs font-semibold text-slate-600">
                    BAR version ID
                    <input
                      value={editor.reportVersionId}
                      onChange={(e) =>
                        setEditor({
                          ...editor,
                          reportVersionId: e.target.value,
                        })
                      }
                      required
                      type="number"
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>
                </>
              ) : (
                <>
                  <label className="text-xs font-semibold text-slate-600">
                    Legacy expiry
                    <input
                      value={editor.expiresAt}
                      onChange={(e) =>
                        setEditor({ ...editor, expiresAt: e.target.value })
                      }
                      required
                      type="date"
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>
                  <label className="text-xs font-semibold text-slate-600">
                    Legacy reason
                    <textarea
                      value={editor.legacyReason}
                      onChange={(e) =>
                        setEditor({ ...editor, legacyReason: e.target.value })
                      }
                      required
                      rows={2}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>
                  <label className="text-xs font-semibold text-slate-600 sm:col-span-2">
                    Compensating source
                    <textarea
                      value={editor.compensatingSource}
                      onChange={(e) =>
                        setEditor({
                          ...editor,
                          compensatingSource: e.target.value,
                        })
                      }
                      required
                      rows={2}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>
                </>
              )}
              <label className="text-xs font-semibold text-slate-600">
                Independent reviewer
                <select
                  value={editor.reviewerId}
                  onChange={(e) =>
                    setEditor({ ...editor, reviewerId: e.target.value })
                  }
                  required
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select reviewer</option>
                  {users.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="text-xs font-semibold text-slate-600">
                Approving authority
                <select
                  value={editor.authorityUserId}
                  onChange={(e) =>
                    setEditor({ ...editor, authorityUserId: e.target.value })
                  }
                  required
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select authority</option>
                  {users.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="mt-4 flex items-start gap-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
              <TriangleAlert
                size={16}
                className="mt-0.5 shrink-0 text-amber-600"
              />
              Source records remain read-only. Approval only creates an
              immutable lineage decision and does not change the risk,
              prioritization, or plan owner record.
            </div>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setEditor(null)}
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              >
                Cancel
              </button>
              <button className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">
                Save decision
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
