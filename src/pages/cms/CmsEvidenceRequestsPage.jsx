import { useCallback, useEffect, useMemo, useState } from "react";
import { CheckCircle2, FileCheck2, RefreshCw, Send, Upload } from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { ApiError, cmsEvidenceRequestApi } from "../../services/api";

const inputClass =
  "mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textAreaClass =
  "mt-1 min-h-24 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const responseStatuses = [
  "SENT",
  "ACKNOWLEDGED",
  "PARTIALLY_RECEIVED",
  "OVERDUE",
  "EXTENDED",
  "ESCALATED",
  "EXTENSION_REQUESTED",
];

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function date(value, includeTime = false) {
  if (!value) return "Not available";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    ...(includeTime ? { timeStyle: "short" } : {}),
  }).format(parsed);
}

function firstError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

function Field({ label: fieldLabel, error, children }) {
  return (
    <label className="block text-sm font-semibold text-slate-700">
      {fieldLabel}
      {children}
      {error && <span className="mt-1 block text-xs font-semibold text-red-600">{error}</span>}
    </label>
  );
}

export default function CmsEvidenceRequestsPage() {
  const { user } = useAuth();
  const [requests, setRequests] = useState([]);
  const [selectedId, setSelectedId] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [errors, setErrors] = useState({});
  const [form, setForm] = useState({
    title: "",
    sourceDescription: "",
    dateObtained: new Date().toISOString().slice(0, 10),
    responseNote: "",
    file: null,
  });

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const records = await cmsEvidenceRequestApi.list();
      setRequests(records);
      setSelectedId((current) =>
        records.some((record) => String(record.id) === String(current))
          ? current
          : String(records[0]?.id ?? ""),
      );
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : "Unable to load Evidence Requests.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const selected = useMemo(
    () => requests.find((record) => String(record.id) === String(selectedId)),
    [requests, selectedId],
  );
  const canRespond = selected && responseStatuses.includes(selected.status);

  function updateForm(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  async function acknowledge() {
    if (!selected || saving) return;
    setSaving(true);
    setError("");
    try {
      await cmsEvidenceRequestApi.acknowledge(selected.id, {
        lockVersion: selected.lockVersion,
        comment: "The Evidence Request was received by the auditee office.",
      });
      setSuccess("Evidence Request acknowledged.");
      await load();
    } catch (cause) {
      setErrors(cause.errors || {});
      setError(cause.message || "The Evidence Request could not be acknowledged.");
    } finally {
      setSaving(false);
    }
  }

  async function submit(event) {
    event.preventDefault();
    if (!selected || saving || !form.file) return;
    setSaving(true);
    setError("");
    setSuccess("");
    setErrors({});
    try {
      await cmsEvidenceRequestApi.submitResponse(selected.id, {
        ...form,
        lockVersion: selected.lockVersion,
      });
      setSuccess("Evidence submitted to the auditor for receipt and assessment.");
      setForm({
        title: "",
        sourceDescription: "",
        dateObtained: new Date().toISOString().slice(0, 10),
        responseNote: "",
        file: null,
      });
      event.target.reset();
      await load();
    } catch (cause) {
      setErrors(cause.errors || {});
      setError(cause.message || "The evidence response could not be submitted.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        description="Review Evidence Requests sent to your office and submit the requested documents securely to the audit team."
        icon={FileCheck2}
        title="Evidence Requests"
        actions={
          <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50" onClick={load} type="button">
            <RefreshCw size={15} /> Refresh
          </button>
        }
      />

      <section className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-900">
        <strong>{user?.name ?? "Auditee representative"}</strong>, this page shows requests addressed to your office or specifically to you. Uploaded files become protected AEMS evidence records; the auditor must still verify, receive, and professionally assess them.
      </section>

      {error && <div className="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{error}</div>}
      {success && <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">{success}</div>}

      {loading ? (
        <div className="mt-5 grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-white text-sm text-slate-500">Loading Evidence Requests…</div>
      ) : (
        <div className="mt-5 grid gap-4 xl:grid-cols-[minmax(18rem,0.75fr)_minmax(0,1.5fr)]">
          <section className="space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            {requests.map((record) => (
              <button className={`w-full rounded-xl border p-3 text-left transition ${String(record.id) === String(selectedId) ? "border-sky-300 bg-sky-50 ring-1 ring-sky-200" : "border-slate-200 hover:border-sky-200 hover:bg-slate-50"}`} key={record.id} onClick={() => setSelectedId(String(record.id))} type="button">
                <div className="flex items-start justify-between gap-2">
                  <strong className="text-sm text-slate-800">{record.requestCode}</strong>
                  <StatusBadge tone={record.status === "ASSESSED" || record.status === "CLOSED" ? "success" : "warning"}>{label(record.status)}</StatusBadge>
                </div>
                <span className="mt-2 block text-sm text-slate-700">{record.title}</span>
                <span className="mt-1 block text-xs text-slate-500">Due {date(record.extensionDueDate || record.dueDate)}</span>
              </button>
            ))}
            {!requests.length && <p className="px-3 py-12 text-center text-sm text-slate-500">No Evidence Requests are available for your office.</p>}
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            {!selected ? (
              <div className="grid min-h-64 place-items-center text-center text-sm text-slate-500">Select an Evidence Request to inspect it.</div>
            ) : (
              <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4">
                  <div>
                    <div className="flex flex-wrap items-center gap-2"><h2 className="text-lg font-bold text-slate-900">{selected.requestCode}</h2><StatusBadge tone="warning">{label(selected.status)}</StatusBadge></div>
                    <p className="mt-1 text-sm text-slate-600">{selected.engagement?.engagementCode} — {selected.engagement?.title}</p>
                  </div>
                  {selected.status === "SENT" && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-3 text-xs font-bold text-sky-800 hover:bg-sky-100" disabled={saving} onClick={acknowledge} type="button"><CheckCircle2 size={15} /> Acknowledge request</button>}
                </div>
                <div className="grid gap-3 sm:grid-cols-2"><Info label="Purpose" value={selected.purpose} /><Info label="Due date" value={date(selected.extensionDueDate || selected.dueDate)} /><Info label="Requested from office" value={selected.requestedFromOffice?.name} /><Info label="Requested from user" value={selected.requestedFromUser?.name || "Any authorized office representative"} /></div>
                <section className="rounded-xl border border-slate-200 bg-slate-50 p-4"><h3 className="text-sm font-bold text-slate-800">Requested items</h3><ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700">{(selected.latestVersion?.requestedItems || []).map((item) => <li key={item}>{item}</li>)}</ul></section>
                {(selected.responses || []).length > 0 && <section className="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><h3 className="text-sm font-bold text-emerald-900">Submitted responses</h3><div className="mt-3 space-y-2">{selected.responses.map((response) => <div className="rounded-lg bg-white p-3 text-sm" key={response.id}><div className="flex flex-wrap justify-between gap-2"><strong>{response.fileName || response.evidenceCode}</strong><span className="text-xs text-slate-500">{date(response.submittedAt, true)}</span></div><p className="mt-1 text-xs text-slate-600">Evidence status: {label(response.evidenceStatus)} · Awaiting auditor receipt and assessment.</p>{response.responseNote && <p className="mt-1 text-xs text-slate-700">{response.responseNote}</p>}</div>)}</div></section>}
                {canRespond ? <form className="space-y-4 rounded-xl border border-sky-200 bg-sky-50 p-4" onSubmit={submit}><div><h3 className="flex items-center gap-2 text-sm font-bold text-sky-900"><Upload size={16} /> Submit requested evidence</h3><p className="mt-1 text-xs leading-5 text-sky-800">Each submission is checksum-recorded as a draft evidence version. The auditor will verify and record receipt in AEMS.</p></div><div className="grid gap-4 sm:grid-cols-2"><Field error={firstError(errors, "title")} label="Evidence title"><input className={inputClass} required value={form.title} onChange={(event) => updateForm("title", event.target.value)} /></Field><Field error={firstError(errors, "dateObtained")} label="Date obtained"><input className={inputClass} required type="date" value={form.dateObtained} onChange={(event) => updateForm("dateObtained", event.target.value)} /></Field><Field error={firstError(errors, "sourceDescription")} label="Description / coverage"><textarea className={textAreaClass} required value={form.sourceDescription} onChange={(event) => updateForm("sourceDescription", event.target.value)} /></Field><Field error={firstError(errors, "responseNote")} label="Response note"><textarea className={textAreaClass} value={form.responseNote} onChange={(event) => updateForm("responseNote", event.target.value)} /></Field></div><Field error={firstError(errors, "file")} label="Evidence file"><input className="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" required onChange={(event) => updateForm("file", event.target.files?.[0] ?? null)} type="file" /></Field><button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60" disabled={saving || !form.file} type="submit"><Send size={15} /> {saving ? "Submitting…" : "Submit evidence"}</button></form> : <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">This request is no longer open for an auditee response. Review the submission and auditor assessment history above.</div>}
              </div>
            )}
          </section>
        </div>
      )}
    </main>
  );
}

function Info({ label: infoLabel, value }) {
  return <div className="rounded-lg bg-slate-50 p-3"><span className="block text-[11px] font-bold uppercase tracking-wide text-slate-400">{infoLabel}</span><span className="mt-1 block text-sm text-slate-700">{value || "Not available"}</span></div>;
}
