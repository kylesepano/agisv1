import { useCallback, useEffect, useState } from "react";
import {
  CheckCircle2,
  Database,
  LockKeyhole,
  RefreshCw,
  Save,
  ShieldAlert,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsClosureApi } from "../../services/api";

function formFrom(record, options) {
  return {
    retentionClassificationCode:
      record?.retentionClassificationCode ?? "AUDIT_ENGAGEMENT_RECORD",
    retentionTriggerCode: record?.retentionTriggerCode ?? "ENGAGEMENT_CLOSED",
    retentionStartDate:
      record?.retentionStartDate ?? new Date().toISOString().slice(0, 10),
    retentionPeriodValue: record?.retentionPeriodValue ?? 10,
    retentionPeriodUnit: record?.retentionPeriodUnit ?? "YEARS",
    permanentFlag: record?.permanentFlag ?? false,
    custodianUserId:
      record?.custodianUserId ?? options?.custodians?.[0]?.id ?? "",
    custodianOfficeId:
      record?.custodianOfficeId ?? options?.offices?.[0]?.id ?? "",
    storageLocationDescription: record?.storageLocationDescription ?? "",
    legalHoldFlag: record?.legalHoldFlag ?? false,
    legalHoldReference: record?.legalHoldReference ?? "",
  };
}

export default function AemsRetentionWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [workspace, setWorkspace] = useState(null);
  const [form, setForm] = useState({});
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
      setForm(formFrom(data.retention, data.retentionOptions));
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
        data-testid="retention-loading"
      >
        <RefreshCw className="animate-spin text-sky-700" size={28} />
      </div>
    );
  }

  if (!workspace) {
    return (
      <div className="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm font-semibold text-rose-700">
        {error || "Retention metadata is unavailable."}
      </div>
    );
  }

  const record = workspace.retention;
  const approved = Boolean(record?.approvedAt);
  const canManage = hasPermission(user, "aems.retention.manage") && !approved;
  const canApprove =
    hasPermission(user, "aems.retention.approve") && record && !approved;
  const options = workspace.retentionOptions;

  return (
    <div className="space-y-5" data-testid="retention-workspace">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex items-start gap-3">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-700">
            <Database size={20} />
          </span>
          <div>
            <h3 className="font-bold text-slate-900">
              Retention and Records Custody
            </h3>
            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
              This interim AEMS provider records the approved classification,
              trigger, period, custodian, storage location, and legal hold.
              Future Core Records Management can replace the provider while
              preserving this historical snapshot.
            </p>
          </div>
        </div>
        {approved && (
          <div className="mt-4 flex gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            <LockKeyhole className="shrink-0" size={18} />
            Approved retention metadata is immutable. Approval recorded{" "}
            {new Date(record.approvedAt).toLocaleString()}.
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

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <label>
            <span className="text-xs font-bold uppercase text-slate-500">
              Retention classification
            </span>
            <input
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  retentionClassificationCode: event.target.value,
                }))
              }
              value={form.retentionClassificationCode ?? ""}
            />
          </label>
          <label>
            <span className="text-xs font-bold uppercase text-slate-500">
              Retention trigger
            </span>
            <input
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  retentionTriggerCode: event.target.value,
                }))
              }
              value={form.retentionTriggerCode ?? ""}
            />
          </label>
          <label>
            <span className="text-xs font-bold uppercase text-slate-500">
              Retention start
            </span>
            <input
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  retentionStartDate: event.target.value,
                }))
              }
              type="date"
              value={form.retentionStartDate ?? ""}
            />
          </label>
          <label>
            <span className="text-xs font-bold uppercase text-slate-500">
              Custodian
            </span>
            <select
              className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  custodianUserId: Number(event.target.value),
                }))
              }
              value={form.custodianUserId ?? ""}
            >
              <option value="">Select custodian</option>
              {options.custodians.map((person) => (
                <option key={person.id} value={person.id}>
                  {person.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            <span className="text-xs font-bold uppercase text-slate-500">
              Custodian office
            </span>
            <select
              className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  custodianOfficeId: Number(event.target.value),
                }))
              }
              value={form.custodianOfficeId ?? ""}
            >
              <option value="">Select office</option>
              {options.offices.map((office) => (
                <option key={office.id} value={office.id}>
                  {office.code} · {office.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            <span className="text-xs font-bold uppercase text-slate-500">
              Storage location
            </span>
            <input
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  storageLocationDescription: event.target.value,
                }))
              }
              value={form.storageLocationDescription ?? ""}
            />
          </label>
        </div>

        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <label className="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
            <input
              checked={Boolean(form.permanentFlag)}
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  permanentFlag: event.target.checked,
                }))
              }
              type="checkbox"
            />
            <span className="text-sm font-semibold text-slate-700">
              Permanent retention
            </span>
          </label>
          <label className="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
            <input
              checked={Boolean(form.legalHoldFlag)}
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  legalHoldFlag: event.target.checked,
                }))
              }
              type="checkbox"
            />
            <span className="text-sm font-semibold text-slate-700">
              Legal hold active
            </span>
          </label>
        </div>

        {!form.permanentFlag && (
          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <label>
              <span className="text-xs font-bold uppercase text-slate-500">
                Retention period
              </span>
              <input
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                disabled={!canManage}
                min="1"
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    retentionPeriodValue: Number(event.target.value),
                  }))
                }
                type="number"
                value={form.retentionPeriodValue ?? ""}
              />
            </label>
            <label>
              <span className="text-xs font-bold uppercase text-slate-500">
                Period unit
              </span>
              <select
                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
                disabled={!canManage}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    retentionPeriodUnit: event.target.value,
                  }))
                }
                value={form.retentionPeriodUnit ?? "YEARS"}
              >
                <option value="DAYS">Days</option>
                <option value="MONTHS">Months</option>
                <option value="YEARS">Years</option>
              </select>
            </label>
          </div>
        )}

        {form.legalHoldFlag && (
          <label className="mt-4 block">
            <span className="text-xs font-bold uppercase text-slate-500">
              Legal-hold reference
            </span>
            <input
              className="mt-1 w-full rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm"
              disabled={!canManage}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  legalHoldReference: event.target.value,
                }))
              }
              value={form.legalHoldReference ?? ""}
            />
          </label>
        )}

        <div className="mt-4 flex flex-wrap gap-2">
          {canManage && (
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
              disabled={
                busy ||
                !form.custodianUserId ||
                !form.custodianOfficeId ||
                (form.legalHoldFlag && !form.legalHoldReference)
              }
              onClick={() =>
                void act(
                  () => aemsClosureApi.saveRetention(engagementId, form),
                  "Retention and custody metadata saved.",
                )
              }
              type="button"
            >
              <Save size={15} /> Save retention
            </button>
          )}
          {canApprove && (
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white"
              disabled={busy}
              onClick={() =>
                void act(
                  () =>
                    aemsClosureApi.approveRetention(engagementId, record.id, {
                      lockVersion: record.lockVersion,
                    }),
                  "Retention metadata approved and locked.",
                )
              }
              type="button"
            >
              <CheckCircle2 size={15} /> Approve retention
            </button>
          )}
        </div>
      </section>

      <section className="grid gap-3 md:grid-cols-2">
        <div className="flex gap-3 rounded-xl border border-slate-200 bg-white p-4">
          <ShieldAlert className="shrink-0 text-amber-600" size={20} />
          <p className="text-sm leading-6 text-slate-600">
            Legal hold prevents disposition. AEMS does not implement physical
            destruction or expose private files.
          </p>
        </div>
        <div className="flex gap-3 rounded-xl border border-slate-200 bg-white p-4">
          <Database className="shrink-0 text-sky-700" size={20} />
          <p className="text-sm leading-6 text-slate-600">
            {record?.scheduledDispositionDate
              ? `Calculated disposition date: ${record.scheduledDispositionDate}.`
              : form.permanentFlag
                ? "Permanent records have no destruction date."
                : "Save the retention rule to calculate a disposition date."}
          </p>
        </div>
      </section>
    </div>
  );
}
