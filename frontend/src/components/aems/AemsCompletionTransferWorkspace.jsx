import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  CheckCircle2,
  ClipboardCheck,
  FileCheck2,
  LockKeyhole,
  RefreshCw,
  ShieldCheck,
  UserCheck,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsCompletionTransferApi } from "../../services/api";

function statusTone(status) {
  if (["APPROVED", "RECONCILED"].includes(status)) {
    return "border-emerald-200 bg-emerald-50 text-emerald-700";
  }
  if (["EXCEPTION", "FAILED"].includes(status)) {
    return "border-rose-200 bg-rose-50 text-rose-700";
  }
  return "border-amber-200 bg-amber-50 text-amber-700";
}

function Status({ value }) {
  return (
    <span
      className={`inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${statusTone(value)}`}
    >
      {(value || "MISSING").replaceAll("_", " ")}
    </span>
  );
}

function Metric({ label, value, detail }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <span className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
        {label}
      </span>
      <strong className="mt-2 block text-xl text-slate-900">{value}</strong>
      {detail && <p className="mt-1 text-xs text-slate-500">{detail}</p>}
    </div>
  );
}

export default function AemsCompletionTransferWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [workspace, setWorkspace] = useState(null);
  const [comment, setComment] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setWorkspace(await aemsCompletionTransferApi.show(engagementId));
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

  if (loading) {
    return (
      <div
        className="grid min-h-72 place-items-center"
        data-testid="completion-transfer-loading"
      >
        <RefreshCw className="animate-spin text-sky-700" size={28} />
      </div>
    );
  }

  if (!workspace) {
    return (
      <div
        className="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm font-semibold text-rose-700"
        data-testid="completion-transfer-error"
      >
        {error || "Completion and transfer workspace is unavailable."}
      </div>
    );
  }

  const manifest = workspace.manifest;
  const effort = workspace.effortReconciliation;
  const provider = workspace.provider ?? {};
  const canReconcile = hasPermission(
    user,
    "aems.completion-transfer.reconcile",
  );
  const canApprove = hasPermission(user, "aems.completion-transfer.approve");

  return (
    <div className="space-y-5" data-testid="completion-transfer-workspace">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <ClipboardCheck className="text-sky-700" size={21} />
              <h3 className="text-base font-bold text-slate-900">
                Completion &amp; CMS Transfer
              </h3>
            </div>
            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
              Reconcile issued recommendations, resource effort, records, and
              transfer exceptions before formal engagement closure. CMS owns
              monitoring after the immutable AEMS transfer boundary.
            </p>
          </div>
          {canReconcile && (
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
              disabled={busy}
              onClick={() =>
                void act(
                  () => aemsCompletionTransferApi.reconcile(engagementId),
                  "CMS transfer and ARMIS effort reconciliation refreshed.",
                )
              }
              type="button"
            >
              <RefreshCw size={16} /> Reconcile now
            </button>
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
      </section>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Metric
          label="Transfer status"
          value={<Status value={manifest?.status} />}
          detail={
            manifest
              ? `${manifest.transferredCount} transferred · ${manifest.excludedCount} excluded`
              : "No issued manifest yet"
          }
        />
        <Metric
          label="Open exceptions"
          value={manifest?.exceptionCount ?? 0}
          detail={
            manifest?.expectedCount
              ? `${manifest.expectedCount} recommendation(s) evaluated`
              : "No recommendation transfer required"
          }
        />
        <Metric
          label="Effort status"
          value={<Status value={effort?.status} />}
          detail={
            effort
              ? `Version ${effort.versionNumber} · ${effort.providerMode}`
              : "Reconciliation not generated"
          }
        />
        <Metric
          label="Provider"
          value={provider.mode ?? "UNKNOWN"}
          detail={
            provider.stale
              ? "Provider data is stale"
              : "Provider status available"
          }
        />
      </div>

      <section className="grid gap-4 lg:grid-cols-2">
        <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div className="flex items-center gap-2">
            <FileCheck2 className="text-violet-700" size={19} />
            <h3 className="font-bold text-slate-900">CMS transfer manifest</h3>
          </div>
          <p className="mt-2 text-sm leading-6 text-slate-500">
            The manifest pins the issued report version and each finalized or
            formally excluded recommendation. Reconciliation is idempotent and
            preserves the Core Document Version checksum.
          </p>
          {manifest ? (
            <div className="mt-4 space-y-3 text-sm">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-slate-500">{manifest.manifestCode}</span>
                <Status value={manifest.status} />
              </div>
              <div className="grid grid-cols-3 gap-2 text-center">
                <Metric label="Expected" value={manifest.expectedCount} />
                <Metric label="Transferred" value={manifest.transferredCount} />
                <Metric label="Excluded" value={manifest.excludedCount} />
              </div>
              {manifest.exceptions?.length > 0 && (
                <div className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-rose-800">
                  <div className="flex items-center gap-2 font-bold">
                    <AlertTriangle size={16} /> Transfer blockers
                  </div>
                  <ul className="mt-2 space-y-1 text-xs">
                    {manifest.exceptions.map((item) => (
                      <li key={item.id}>{item.message}</li>
                    ))}
                  </ul>
                </div>
              )}
              {canApprove &&
                manifest.status === "RECONCILED" &&
                manifest.exceptionCount === 0 && (
                  <button
                    className="inline-flex min-h-9 items-center gap-2 rounded-lg bg-emerald-700 px-3 text-xs font-bold text-white disabled:opacity-50"
                    disabled={busy || comment.trim().length < 10}
                    onClick={() =>
                      void act(
                        () =>
                          aemsCompletionTransferApi.approve(
                            engagementId,
                            "MANIFEST",
                            manifest.id,
                            { lockVersion: manifest.lockVersion, comment },
                          ),
                        "CMS transfer manifest approved.",
                      )
                    }
                    type="button"
                  >
                    <CheckCircle2 size={15} /> Approve manifest
                  </button>
                )}
            </div>
          ) : (
            <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
              An issued Final Report is required before a CMS manifest can be
              generated.
            </div>
          )}
        </article>

        <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div className="flex items-center gap-2">
            <ShieldCheck className="text-emerald-700" size={19} />
            <h3 className="font-bold text-slate-900">
              ARMIS effort reconciliation
            </h3>
          </div>
          <p className="mt-2 text-sm leading-6 text-slate-500">
            Planned and AEMS actual person-days are reconciled against the
            configured ARMIS provider. Missing or stale authoritative data
            remains a closure blocker.
          </p>
          {effort ? (
            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
              <Metric
                label="Planned"
                value={effort.plannedPersonDays}
                detail="person-days"
              />
              <Metric
                label="AEMS actual"
                value={effort.aemsActualPersonDays}
                detail="person-days"
              />
              <Metric
                label="Provider actual"
                value={effort.providerActualPersonDays ?? "—"}
                detail={`Variance ${effort.variancePersonDays}`}
              />
            </div>
          ) : (
            <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
              Run reconciliation to create an immutable effort snapshot.
            </div>
          )}
          {effort && canApprove && effort.status === "RECONCILED" && (
            <button
              className="mt-4 inline-flex min-h-9 items-center gap-2 rounded-lg bg-emerald-700 px-3 text-xs font-bold text-white disabled:opacity-50"
              disabled={busy || comment.trim().length < 10}
              onClick={() =>
                void act(
                  () =>
                    aemsCompletionTransferApi.approve(
                      engagementId,
                      "EFFORT",
                      effort.id,
                      { lockVersion: effort.lockVersion, comment },
                    ),
                  "Effort reconciliation approved.",
                )
              }
              type="button"
            >
              <UserCheck size={15} /> Approve effort snapshot
            </button>
          )}
        </article>
      </section>

      {canApprove &&
        ((manifest?.status === "RECONCILED" && manifest.exceptionCount === 0) ||
          effort?.status === "RECONCILED") && (
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <label
              className="block text-sm font-bold text-slate-800"
              htmlFor="completion-transfer-comment"
            >
              Approval comment
            </label>
            <textarea
              id="completion-transfer-comment"
              className="mt-2 min-h-20 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              onChange={(event) => setComment(event.target.value)}
              placeholder="Record the independent review basis (minimum 10 characters)."
              value={comment}
            />
            <p className="mt-2 text-xs text-slate-500">
              The generator cannot approve the same snapshot. Approved snapshots
              are immutable.
            </p>
          </section>
        )}

      <section className="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 sm:p-5">
        <div className="flex items-start gap-3">
          <LockKeyhole className="mt-0.5 shrink-0" size={18} />
          <div>
            <strong className="block">Closure gate</strong>
            <span>
              {workspace.effortReconciliation?.status === "EXCEPTION" ||
              manifest?.exceptionCount > 0
                ? "Resolve the listed reconciliation exceptions before formal closure."
                : "The formal Closure workspace re-evaluates transfer, effort, retention, document index, report, finding, and dialogue blockers atomically."}
            </span>
          </div>
        </div>
      </section>
    </div>
  );
}
