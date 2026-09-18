import {
  AlertTriangle,
  CheckCircle2,
  ClipboardCheck,
  GitCompareArrows,
  History,
  RefreshCw,
  RotateCcw,
  ShieldCheck,
  XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useAuth } from "../../auth/auth-context";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { hasPermission } from "../../config/navigation";
import { armisProviderApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const currentYear = new Date().getFullYear();

function collection(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.data)) return value.data;
  return [];
}

function readable(value) {
  return String(value || "Unknown")
    .replaceAll("_", " ")
    .toLowerCase()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

function displayDate(value) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function modeTone(mode) {
  return mode === "ARMIS_AUTHORITATIVE" ? "success" : "warning";
}

function statusTone(status) {
  if (status === "MATCH" || status === "ACCEPTED") return "success";
  if (status === "DISCREPANCY" || status === "REJECTED") return "danger";
  return "info";
}

function inputClass(extra = "") {
  return `mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 ${extra}`;
}

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <XCircle className="mx-auto text-red-600" size={34} />
      <h2 className="mt-3 font-bold text-red-900">
        ARMIS provider workspace unavailable
      </h2>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-red-700">
        {message}
      </p>
      <button
        className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800"
        onClick={onRetry}
        type="button"
      >
        <RefreshCw size={16} /> Retry
      </button>
    </section>
  );
}

function ProviderStatus({ status }) {
  const provider = status?.provider || {};
  const mode = "ARMIS_AUTHORITATIVE";
  const latest = status?.latestReconciliation;
  const review = status?.latestReview;

  return (
    <section
      className={`rounded-2xl border p-5 shadow-sm ${
        mode === "ARMIS_AUTHORITATIVE"
          ? "border-emerald-200 bg-emerald-50"
          : "border-amber-200 bg-amber-50"
      }`}
    >
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-start gap-3">
          {mode === "ARMIS_AUTHORITATIVE" ? (
            <ShieldCheck
              className="mt-0.5 shrink-0 text-emerald-700"
              size={23}
            />
          ) : (
            <AlertTriangle
              className="mt-0.5 shrink-0 text-amber-700"
              size={23}
            />
          )}
          <div>
            <h2 className="font-bold text-slate-900">
              Provider authority status
            </h2>
            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-700">
              ARMIS is the sole active resource provider for AEMS.
              Reconciliation snapshots compare historical IAP planning lineage
              with ARMIS without changing operational ownership.
            </p>
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <StatusBadge tone={modeTone(mode)}>{readable(mode)}</StatusBadge>
              <span className="text-xs text-slate-600">
                Active: {readable(provider.activeProvider || provider.provider)}
              </span>
            </div>
          </div>
        </div>
      </div>
      <div className="mt-5 grid gap-3 border-t border-black/5 pt-4 sm:grid-cols-3">
        <div>
          <p className="text-xs font-bold uppercase tracking-wide text-slate-600">
            Latest run
          </p>
          <p className="mt-1 text-sm font-bold text-slate-900">
            {latest?.displayCode || "No reconciliation"}
          </p>
          <p className="text-xs text-slate-600">
            {latest
              ? displayDate(latest.generatedAt)
              : "Generate a snapshot to begin."}
          </p>
        </div>
        <div>
          <p className="text-xs font-bold uppercase tracking-wide text-slate-600">
            Discrepancies
          </p>
          <p className="mt-1 text-sm font-bold text-slate-900">
            {latest?.summary?.discrepancies ?? 0}
          </p>
          <p className="text-xs text-slate-600">
            {review
              ? `${readable(review.decision)} review`
              : "Independent review pending"}
          </p>
        </div>
        <div>
          <p className="text-xs font-bold uppercase tracking-wide text-slate-600">
            Authority gate
          </p>
          <p className="mt-1 text-sm font-bold text-slate-900">ARMIS active</p>
          <p className="text-xs text-slate-600">ARMIS is permanently active</p>
        </div>
      </div>
    </section>
  );
}

function SummaryCard({ label, value, note, tone = "sky", icon: Icon }) {
  const tones = {
    sky: "border-sky-200 bg-sky-50 text-sky-900",
    emerald: "border-emerald-200 bg-emerald-50 text-emerald-900",
    amber: "border-amber-200 bg-amber-50 text-amber-900",
    red: "border-red-200 bg-red-50 text-red-900",
  };
  return (
    <section
      className={`rounded-xl border p-4 shadow-sm ${tones[tone] || tones.sky}`}
    >
      <div className="flex items-center justify-between gap-3">
        <p className="text-xs font-bold uppercase tracking-wide">{label}</p>
        <Icon size={18} />
      </div>
      <p className="mt-2 text-3xl font-bold">{value}</p>
      <p className="mt-1 text-xs opacity-80">{note}</p>
    </section>
  );
}

function RunList({ runs, selectedId, onSelect }) {
  return (
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
        <History className="text-sky-700" size={17} />
        <div>
          <h2 className="text-sm font-bold text-slate-900">
            Reconciliation history
          </h2>
          <p className="text-xs text-slate-500">
            Immutable snapshots in your authorized scope.
          </p>
        </div>
      </header>
      {runs.length === 0 ? (
        <p className="px-4 py-10 text-center text-sm text-slate-500">
          No reconciliation snapshots yet.
        </p>
      ) : (
        <div className="divide-y divide-slate-100">
          {runs.map((run) => (
            <button
              className={`block w-full px-4 py-3 text-left transition hover:bg-sky-50 ${selectedId === run.id ? "bg-sky-50" : "bg-white"}`}
              key={run.id}
              onClick={() => onSelect(run)}
              type="button"
            >
              <div className="flex items-start justify-between gap-3">
                <strong className="text-sm text-sky-800">
                  {run.displayCode}
                </strong>
                <StatusBadge tone={statusTone(run.reviews?.[0]?.decision)}>
                  {readable(run.reviews?.[0]?.decision || run.status)}
                </StatusBadge>
              </div>
              <p className="mt-1 text-xs text-slate-600">
                FY {run.fiscalYear} · {run.summary?.discrepancies ?? 0}{" "}
                discrepancies
              </p>
              <p className="mt-1 text-xs text-slate-500">
                Generated {displayDate(run.generatedAt)}
              </p>
            </button>
          ))}
        </div>
      )}
    </section>
  );
}

function RunDetail({ run, onReview, canReview, currentUserId }) {
  if (!run) {
    return (
      <section className="grid min-h-72 place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
        <div>
          <GitCompareArrows className="mx-auto text-slate-400" size={34} />
          <h2 className="mt-3 font-bold text-slate-800">
            Select a reconciliation snapshot
          </h2>
          <p className="mt-2 max-w-lg text-sm leading-6 text-slate-500">
            Compare historical IAP planning lineage with ARMIS values and record
            every discrepancy decision.
          </p>
        </div>
      </section>
    );
  }

  const items = collection(run.resultSnapshot);
  const discrepancies = items.filter((item) => item.status === "DISCREPANCY");
  const review = collection(run.reviews)[0];
  const authority = null;
  const generatorId = Number(run.generatedBy?.id || 0);
  const reviewerId = Number(review?.reviewedBy?.id || 0);
  const sameActor =
    currentUserId &&
    (generatorId === currentUserId || reviewerId === currentUserId);
  const reviewDisabled = Boolean(review) || !canReview || Boolean(sameActor);

  return (
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-slate-900">
              {run.displayCode}
            </h2>
            <StatusBadge tone={modeTone(run.providerMode)}>
              {readable(run.providerMode)}
            </StatusBadge>
            <StatusBadge tone={statusTone(review?.decision || run.status)}>
              {readable(review?.decision || run.status)}
            </StatusBadge>
          </div>
          <p className="mt-1 text-xs text-slate-500">
            Generated {displayDate(run.generatedAt)} · FY {run.fiscalYear} ·
            Source {run.sourceQueryVersion}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {canReview && (
            <button
              className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-xs font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50"
              disabled={reviewDisabled}
              onClick={onReview}
              type="button"
            >
              <ClipboardCheck size={15} />
              {review
                ? "Review recorded"
                : sameActor
                  ? "Separation required"
                  : "Record review"}
            </button>
          )}
        </div>
      </header>
      <div className="grid gap-3 border-b border-slate-100 bg-slate-50 p-4 sm:grid-cols-4">
        <SummaryCard
          icon={GitCompareArrows}
          label="Compared"
          value={run.summary?.total ?? items.length}
          note="IAP and ARMIS records"
        />
        <SummaryCard
          icon={CheckCircle2}
          label="Matches"
          value={
            run.summary?.matches ??
            items.filter((item) => item.status === "MATCH").length
          }
          note="No provider difference"
          tone="emerald"
        />
        <SummaryCard
          icon={AlertTriangle}
          label="Discrepancies"
          value={run.summary?.discrepancies ?? discrepancies.length}
          note="Each requires a decision"
          tone="amber"
        />
        <SummaryCard
          icon={ShieldCheck}
          label="Checksum"
          value={
            run.resultChecksumSha256
              ? `${run.resultChecksumSha256.slice(0, 10)}…`
              : "-"
          }
          note="Immutable snapshot"
          tone="sky"
        />
      </div>
      <div className="border-b border-slate-100 px-5 py-3 text-xs leading-5 text-slate-600">
        <strong>Scope:</strong>{" "}
        {run.scopeSnapshot?.globalOfficeScope
          ? "Portfolio-wide"
          : "Office-scoped"}{" "}
        · {collection(run.scopeSnapshot?.officeIds).length} office(s) ·
        Generated by {run.generatedBy?.name || "Unknown actor"}
      </div>
      {discrepancies.length === 0 ? (
        <div className="px-5 py-12 text-center">
          <CheckCircle2 className="mx-auto text-emerald-600" size={34} />
          <h3 className="mt-3 font-bold text-slate-800">
            No discrepancies require review
          </h3>
          <p className="mt-2 text-sm text-slate-500">
            The immutable snapshot found matching values across the compared
            ledgers.
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[760px] text-left">
            <thead className="bg-slate-50">
              <tr>
                <th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                  Area
                </th>
                <th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                  Subject
                </th>
                <th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                  IAP value
                </th>
                <th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                  ARMIS value
                </th>
                <th className="border-b border-slate-200 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                  Review
                </th>
              </tr>
            </thead>
            <tbody>
              {discrepancies.map((item) => (
                <tr
                  className="border-b border-slate-100 align-top"
                  key={item.key}
                >
                  <td className="px-4 py-3 text-xs font-bold text-slate-700">
                    {readable(item.category)}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-600">
                    {Object.entries(item.subject || {}).map(([key, value]) => (
                      <div key={key}>
                        <span className="font-semibold">{readable(key)}:</span>{" "}
                        {String(value)}
                      </div>
                    ))}
                  </td>
                  <td className="max-w-xs whitespace-pre-wrap px-4 py-3 text-xs text-slate-600">
                    {JSON.stringify(item.iap, null, 2)}
                  </td>
                  <td className="max-w-xs whitespace-pre-wrap px-4 py-3 text-xs text-slate-600">
                    {JSON.stringify(item.armis, null, 2)}
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge tone="danger">Discrepancy</StatusBadge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {review && (
        <div className="border-t border-slate-200 bg-sky-50/50 px-5 py-4">
          <div className="flex flex-wrap items-center gap-2">
            <strong className="text-sm text-slate-900">
              Independent review:{" "}
            </strong>
            <StatusBadge tone={statusTone(review.decision)}>
              {readable(review.decision)}
            </StatusBadge>
            <span className="text-xs text-slate-500">
              {displayDate(review.reviewedAt)}
            </span>
          </div>
          <p className="mt-2 text-sm leading-6 text-slate-700">
            {review.comment}
          </p>
        </div>
      )}
      {authority && (
        <div className="border-t border-slate-200 bg-emerald-50/60 px-5 py-4">
          <div className="flex flex-wrap items-center gap-2">
            <strong className="text-sm text-slate-900">
              Authority decision:{" "}
            </strong>
            <StatusBadge tone="success">
              {readable(authority.decisionCode)}
            </StatusBadge>
            <span className="text-xs text-slate-500">
              {readable(authority.fromMode)} → {readable(authority.toMode)} ·{" "}
              {displayDate(authority.decidedAt)}
            </span>
          </div>
          <p className="mt-2 text-sm leading-6 text-slate-700">
            {authority.reason}
          </p>
        </div>
      )}
    </section>
  );
}

export default function ArmisProviderReconciliationPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [status, setStatus] = useState(null);
  const [runs, setRuns] = useState([]);
  const [selectedRun, setSelectedRun] = useState(null);
  const [fiscalYear, setFiscalYear] = useState(String(currentYear));
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [reviewOpen, setReviewOpen] = useState(false);
  const [reviewDecision, setReviewDecision] = useState("ACCEPTED");
  const [reviewComment, setReviewComment] = useState("");
  const [discrepancyDecisions, setDiscrepancyDecisions] = useState({});
  const [decisionTarget, setDecisionTarget] = useState("");
  const [decisionReason, setDecisionReason] = useState("");

  const canReconcile = hasPermission(user, "armis.provider.reconcile");
  const canReview = hasPermission(user, "armis.provider.review");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [nextStatus, nextRuns] = await Promise.all([
        armisProviderApi.getStatus(),
        armisProviderApi.getRuns(),
      ]);
      const normalizedRuns = collection(nextRuns);
      setStatus(nextStatus || {});
      setRuns(normalizedRuns);
      setSelectedRun(
        (current) =>
          normalizedRuns.find((item) => item.id === current?.id) ||
          normalizedRuns[0] ||
          null,
      );
    } catch (requestError) {
      setError(
        requestError.message ||
          "Unable to load ARMIS provider reconciliation data.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const discrepancies = useMemo(
    () =>
      collection(selectedRun?.resultSnapshot).filter(
        (item) => item.status === "DISCREPANCY",
      ),
    [selectedRun],
  );

  function openReview() {
    setReviewDecision("ACCEPTED");
    setReviewComment("");
    setDiscrepancyDecisions(
      Object.fromEntries(discrepancies.map((item) => [item.key, "ACCEPT"])),
    );
    setReviewOpen(true);
  }

  async function generate() {
    setGenerating(true);
    setError("");
    try {
      const run = await armisProviderApi.generate(fiscalYear);
      if (!run)
        throw new Error(
          "The reconciliation snapshot was not returned by the server.",
        );
      setRuns((current) => [
        run,
        ...current.filter((item) => item.id !== run.id),
      ]);
      setSelectedRun(run);
      await load();
      toast.success("ARMIS reconciliation snapshot generated.");
    } catch (requestError) {
      setError(
        requestError.message || "Unable to generate the ARMIS reconciliation.",
      );
      toast.error(
        requestError.message || "Unable to generate the ARMIS reconciliation.",
      );
    } finally {
      setGenerating(false);
    }
  }

  async function submitReview() {
    if (!selectedRun || reviewComment.trim().length < 10) return;
    setSaving(true);
    try {
      const review = await armisProviderApi.review(selectedRun.id, {
        decision: reviewDecision,
        comment: reviewComment.trim(),
        discrepancyDecisions,
      });
      setSelectedRun((current) => ({
        ...current,
        reviews: [
          review,
          ...collection(current.reviews).filter(
            (item) => item.id !== review.id,
          ),
        ],
      }));
      setRuns((current) =>
        current.map((item) =>
          item.id === selectedRun.id ? { ...item, reviews: [review] } : item,
        ),
      );
      setReviewOpen(false);
      toast.success("Independent ARMIS reconciliation review recorded.");
      await load();
    } catch (requestError) {
      toast.error(
        requestError.message || "Unable to record the reconciliation review.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function submitAuthorityDecision() {
    setDecisionTarget("");
    setDecisionReason("");
  }

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <label className="flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600">
              Fiscal year
              <input
                aria-label="Reconciliation fiscal year"
                className="w-16 border-0 bg-transparent p-0 text-sm font-bold text-slate-900 outline-none focus:ring-0"
                max="2200"
                min="2000"
                onChange={(event) => setFiscalYear(event.target.value)}
                type="number"
                value={fiscalYear}
              />
            </label>
            <button
              aria-label="Refresh ARMIS provider reconciliation"
              className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50 disabled:opacity-50"
              disabled={loading}
              onClick={load}
              type="button"
            >
              <RefreshCw className={loading ? "animate-spin" : ""} size={16} />{" "}
              Refresh
            </button>
            {canReconcile && (
              <button
                className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50"
                disabled={generating || !fiscalYear}
                onClick={generate}
                type="button"
              >
                <GitCompareArrows size={16} />
                {generating ? "Generating…" : "New reconciliation"}
              </button>
            )}
          </div>
        }
        description="Compare IAP and ARMIS resource ledgers and govern provider authority through immutable, permission-controlled decisions."
        icon={GitCompareArrows}
        title="ARMIS Provider Reconciliation"
      />
      {error && (
        <div className="mb-5">
          <ErrorState message={error} onRetry={load} />
        </div>
      )}
      {loading ? (
        <div
          aria-label="Loading ARMIS provider reconciliation"
          className="grid gap-3 md:grid-cols-3"
        >
          <div className="h-32 animate-pulse rounded-xl bg-slate-200" />
          <div className="h-32 animate-pulse rounded-xl bg-slate-200" />
          <div className="h-32 animate-pulse rounded-xl bg-slate-200" />
        </div>
      ) : (
        <>
          <ProviderStatus status={status} />
          <div className="mt-5 grid gap-3 sm:grid-cols-3">
            <SummaryCard
              icon={History}
              label="Snapshots"
              value={runs.length}
              note="Immutable reconciliation runs"
            />
            <SummaryCard
              icon={AlertTriangle}
              label="Latest differences"
              value={status?.latestReconciliation?.summary?.discrepancies ?? 0}
              note="Require explicit review"
              tone="amber"
            />
            <SummaryCard
              icon={ShieldCheck}
              label="Operational provider"
              value="ARMIS active"
              note="No provider switch"
              tone="emerald"
            />
          </div>
          <section className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">
            <strong>Control boundary:</strong> reconciliation never mutates IAP
            or ARMIS ledgers. IAP is retained only as historical planning
            lineage; ARMIS remains the sole operational resource provider and no
            provider switch is available.
          </section>
          <div className="mt-5 grid min-w-0 gap-5 xl:grid-cols-[19rem_minmax(0,1fr)]">
            <RunList
              onSelect={setSelectedRun}
              runs={runs}
              selectedId={selectedRun?.id}
            />
            <RunDetail
              canReview={canReview}
              currentUserId={Number(user?.id || 0)}
              onReview={openReview}
              run={selectedRun}
            />
          </div>
        </>
      )}
      <Modal
        description="Every discrepancy must have an explicit ACCEPT or REJECT decision. This review is immutable and must be performed by a different actor from the snapshot generator."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setReviewOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50"
              disabled={saving || reviewComment.trim().length < 10}
              onClick={submitReview}
              type="button"
            >
              <ClipboardCheck size={15} />
              {saving ? "Saving…" : "Record immutable review"}
            </button>
          </>
        }
        onClose={() => !saving && setReviewOpen(false)}
        open={reviewOpen}
        size="xl"
        title={`Review ${selectedRun?.displayCode || "reconciliation"}`}
      >
        <div className="grid gap-4">
          <label className="text-sm font-semibold text-slate-700">
            Overall decision
            <select
              aria-label="Reconciliation decision"
              className={inputClass()}
              onChange={(event) => setReviewDecision(event.target.value)}
              value={reviewDecision}
            >
              <option value="ACCEPTED">Accept reconciliation</option>
              <option value="REJECTED">Reject reconciliation</option>
            </select>
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Independent review comment (minimum 10 characters)
            <textarea
              aria-label="Review comment"
              className={inputClass("min-h-28 py-3")}
              onChange={(event) => setReviewComment(event.target.value)}
              placeholder="Explain the basis for your independent assessment..."
              value={reviewComment}
            />
          </label>
          {discrepancies.length > 0 && (
            <div>
              <h3 className="text-sm font-bold text-slate-800">
                Discrepancy decisions
              </h3>
              <div className="mt-2 grid gap-2">
                {discrepancies.map((item) => (
                  <label
                    className="flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs sm:flex-row sm:items-center sm:justify-between"
                    key={item.key}
                  >
                    <span className="font-semibold text-slate-700">
                      {readable(item.category)} · {item.key}
                    </span>
                    <select
                      aria-label={`Decision for ${item.key}`}
                      className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700"
                      onChange={(event) =>
                        setDiscrepancyDecisions((current) => ({
                          ...current,
                          [item.key]: event.target.value,
                        }))
                      }
                      value={discrepancyDecisions[item.key] || "ACCEPT"}
                    >
                      <option value="ACCEPT">Accept difference</option>
                      <option value="REJECT">Reject difference</option>
                    </select>
                  </label>
                ))}
              </div>
            </div>
          )}
        </div>
      </Modal>
      {decisionTarget && (
        <Modal
          description={
            decisionTarget === "activate"
              ? "This will switch the configured AEMS resource provider to ARMIS after the backend gate validates the accepted shadow review."
              : "This will return provider authority to the IAP interim provider. A new shadow reconciliation will be required before ARMIS can be activated again."
          }
          footer={
            <>
              <button
                className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
                disabled={saving}
                onClick={() => setDecisionTarget("")}
                type="button"
              >
                Cancel
              </button>
              <button
                className={`inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50 ${decisionTarget === "activate" ? "bg-emerald-700 hover:bg-emerald-800" : "bg-amber-700 hover:bg-amber-800"}`}
                disabled={saving || decisionReason.trim().length < 10}
                onClick={submitAuthorityDecision}
                type="button"
              >
                {decisionTarget === "activate" ? (
                  <ShieldCheck size={15} />
                ) : (
                  <RotateCcw size={15} />
                )}
                {saving
                  ? "Saving…"
                  : decisionTarget === "activate"
                    ? "Activate ARMIS authority"
                    : "Rollback to IAP"}
              </button>
            </>
          }
          onClose={() => !saving && setDecisionTarget("")}
          open={Boolean(decisionTarget)}
          size="md"
          title={
            decisionTarget === "activate"
              ? "Activate ARMIS authority"
              : "Rollback provider authority"
          }
        >
          <label className="text-sm font-semibold text-slate-700">
            Decision reason (minimum 10 characters)
            <textarea
              aria-label="Authority decision reason"
              className={inputClass("min-h-32 py-3")}
              onChange={(event) => setDecisionReason(event.target.value)}
              placeholder="Record the professional and operational basis for this decision..."
              value={decisionReason}
            />
          </label>
        </Modal>
      )}
    </main>
  );
}
