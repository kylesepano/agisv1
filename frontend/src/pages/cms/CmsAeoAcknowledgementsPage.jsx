import { useCallback, useEffect, useMemo, useState } from "react";
import {
  CheckCircle2,
  ClipboardCheck,
  Download,
  ExternalLink,
  FileCheck2,
  RefreshCw,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { aemsAeoApi, ApiError } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const dateTime = (value) => (value ? new Date(value).toLocaleString() : "—");

export default function CmsAeoAcknowledgementsPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [searchParams] = useSearchParams();
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState(null);
  const [downloadId, setDownloadId] = useState(null);
  const [expandedId, setExpandedId] = useState(null);
  const [acknowledgementItem, setAcknowledgementItem] = useState(null);
  const [acknowledgementNote, setAcknowledgementNote] = useState(
    "The issued Audit Engagement Order was received and recorded.",
  );
  const [acknowledgementError, setAcknowledgementError] = useState("");
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const data = await aemsAeoApi.recipientAcknowledgements();
      setRecords(data?.distributions ?? []);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : "Unable to load issued AEO transmittals.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timeout = window.setTimeout(load, 0);
    return () => window.clearTimeout(timeout);
  }, [load]);

  const highlightedId = Number(searchParams.get("distributionId"));
  const pending = useMemo(
    () => records.filter((item) => item.status !== "ACKNOWLEDGED"),
    [records],
  );

  function openAcknowledgement(item) {
    setAcknowledgementItem(item);
    setAcknowledgementNote(
      "The issued Audit Engagement Order was received and recorded.",
    );
    setAcknowledgementError("");
  }

  async function acknowledge(item, note) {
    if (note === undefined) {
      openAcknowledgement(item);
      return;
    }
    setBusyId(item.id);
    try {
      await aemsAeoApi.acknowledgeRecipient(item.id, { note: note.trim() });
      toast.success("AEO transmittal acknowledged.");
      await load();
      return true;
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : "Unable to acknowledge this AEO transmittal.",
      );
      return false;
    } finally {
      setBusyId(null);
    }
  }

  async function submitAcknowledgement() {
    const note = acknowledgementNote.trim();
    if (note.length < 2) {
      setAcknowledgementError(
        "Enter a short acknowledgement note before submitting.",
      );
      return;
    }
    const saved = await acknowledge(acknowledgementItem, note);
    if (saved) setAcknowledgementItem(null);
  }

  async function downloadAeo(item) {
    setDownloadId(item.id);
    try {
      await aemsAeoApi.downloadRecipientPdf(
        item.id,
        `${item.orderCode || "issued-aeo"}-v${item.versionNumber}.pdf`,
      );
      toast.success("Approved AEO downloaded.");
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : "Unable to download the approved AEO.",
      );
    } finally {
      setDownloadId(null);
    }
  }

  return (
    <div className="min-w-0">
      <RegistryHeader
        icon={ClipboardCheck}
        title="AEO Acknowledgements"
        description="Receive and acknowledge issued Audit Engagement Orders transmitted to your account or office."
        actions={
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
            onClick={load}
            type="button"
          >
            <RefreshCw size={15} /> Refresh
          </button>
        }
      />

      <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <SummaryCard
          icon={FileCheck2}
          label="Issued transmittals"
          value={records.length}
          note="Available to your account or office"
          tone="sky"
        />
        <SummaryCard
          icon={CheckCircle2}
          label="Awaiting acknowledgement"
          value={pending.length}
          note="Requires your receipt confirmation"
          tone="amber"
        />
        <SummaryCard
          icon={ClipboardCheck}
          label="Acknowledged"
          value={records.length - pending.length}
          note={`Recipient: ${user?.name ?? "current account"}`}
          tone="emerald"
        />
      </div>

      <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-200 px-4 py-4 sm:px-5">
          <h2 className="font-bold text-slate-900">
            Issued Audit Engagement Orders
          </h2>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            This recipient portal is limited to AEO transmittals addressed to
            you or your office. Internal AEMS workspaces remain protected.
          </p>
        </div>
        {error && (
          <div className="m-4 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-sm text-red-800">
            {error}
          </div>
        )}
        {loading && (
          <div className="px-5 py-12 text-center text-sm text-slate-500">
            Loading issued transmittals…
          </div>
        )}
        {!loading && !error && records.length === 0 && (
          <div className="px-5 py-12 text-center text-sm text-slate-500">
            No issued AEO transmittals are currently addressed to your account
            or office.
          </div>
        )}
        {!loading && !error && records.length > 0 && (
          <div className="divide-y divide-slate-100">
            {records.map((item) => (
              <article
                className={`px-4 py-4 transition sm:px-5 ${highlightedId === Number(item.id) ? "bg-sky-50" : "hover:bg-slate-50/70"}`}
                key={item.id}
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="font-bold text-slate-900">
                        {item.orderCode || "Issued AEO"}
                      </h3>
                      <StatusBadge
                        tone={
                          item.status === "ACKNOWLEDGED" ? "active" : "warning"
                        }
                      >
                        {item.status}
                      </StatusBadge>
                    </div>
                    <p className="mt-1 text-sm text-slate-700">
                      {item.engagementTitle || "Audit engagement"}
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                      {item.engagementCode || "—"} · Version{" "}
                      {item.versionNumber} · Sent {dateTime(item.sentAt)}
                    </p>
                  </div>
                  {item.status !== "ACKNOWLEDGED" && (
                    <button
                      className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
                      disabled={busyId === item.id}
                      onClick={() => acknowledge(item)}
                      type="button"
                    >
                      <CheckCircle2 size={15} />{" "}
                      {busyId === item.id ? "Saving…" : "Acknowledge receipt"}
                    </button>
                  )}
                </div>
                <div className="mt-3 flex flex-wrap justify-end gap-2">
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-200 bg-white px-3 text-sm font-bold text-sky-700 hover:bg-sky-50"
                    onClick={() =>
                      setExpandedId((current) =>
                        current === item.id ? null : item.id,
                      )
                    }
                    type="button"
                  >
                    <FileCheck2 size={15} />{" "}
                    {expandedId === item.id
                      ? "Hide issued AEO"
                      : "View issued AEO"}
                  </button>
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    disabled={downloadId === item.id}
                    onClick={() => downloadAeo(item)}
                    type="button"
                  >
                    <Download size={15} />{" "}
                    {downloadId === item.id
                      ? "Downloading…"
                      : "Download approved AEO"}
                  </button>
                </div>
                <div className="mt-3 grid gap-2 text-xs text-slate-500 sm:grid-cols-3">
                  <span>
                    Recipient:{" "}
                    <strong className="text-slate-700">
                      {item.recipientName ||
                        item.office?.name ||
                        item.recipientUser?.name ||
                        "Your office"}
                    </strong>
                  </span>
                  <span>
                    Method:{" "}
                    <strong className="text-slate-700">
                      {item.transmittalMethod || "—"}
                    </strong>
                  </span>
                  <span>
                    Reference:{" "}
                    <strong className="text-slate-700">
                      {item.transmittalReference || "—"}
                    </strong>
                  </span>
                </div>
                {expandedId === item.id && item.aeo && (
                  <div className="mt-4 rounded-xl border border-sky-100 bg-sky-50/60 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <h4 className="font-bold text-slate-900">
                        Issued Audit Engagement Order · Version{" "}
                        {item.aeo.versionNumber}
                      </h4>
                      <span className="text-xs text-slate-500">
                        Approved {dateTime(item.aeo.approvedAt)} · Issued{" "}
                        {dateTime(item.aeo.issuedAt)}
                      </span>
                    </div>
                    <div className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                      <div>
                        <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                          Authority
                        </span>
                        <p className="mt-1 whitespace-pre-line text-slate-700">
                          {item.aeo.authority || "—"}
                        </p>
                      </div>
                      <div>
                        <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                          Auditee office(s)
                        </span>
                        <p className="mt-1 text-slate-700">
                          {item.aeo.auditeeOffices
                            ?.map((office) => office.name)
                            .join(", ") || "—"}
                        </p>
                      </div>
                      <div>
                        <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                          Effectivity and planned dates
                        </span>
                        <p className="mt-1 text-slate-700">
                          {item.aeo.effectivityDate || "Not specified"} ·{" "}
                          {item.aeo.plannedStartDate || "—"} to{" "}
                          {item.aeo.plannedEndDate || "—"}
                        </p>
                      </div>
                      <div>
                        <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                          Audit areas
                        </span>
                        <p className="mt-1 text-slate-700">
                          {item.aeo.auditAreas
                            ?.map((area) => area.name)
                            .join(", ") || "—"}
                        </p>
                      </div>
                    </div>
                    <div className="mt-3">
                      <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        Objectives
                      </span>
                      <p className="mt-1 whitespace-pre-line text-sm text-slate-700">
                        {item.aeo.objectives || "—"}
                      </p>
                    </div>
                    <div className="mt-3">
                      <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        Scope
                      </span>
                      <p className="mt-1 whitespace-pre-line text-sm text-slate-700">
                        {item.aeo.scope || "—"}
                      </p>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-3 text-xs text-slate-600">
                      <span>
                        Approved by:{" "}
                        <strong className="text-slate-800">
                          {item.aeo.approvedBy?.name || "—"}
                        </strong>
                      </span>
                      <span>
                        Issued by:{" "}
                        <strong className="text-slate-800">
                          {item.aeo.issuedBy?.name || "—"}
                        </strong>
                      </span>
                    </div>
                  </div>
                )}
                {item.status === "ACKNOWLEDGED" && (
                  <p className="mt-3 text-xs text-emerald-700">
                    Acknowledged {dateTime(item.acknowledgedAt)} by{" "}
                    {item.acknowledgedBy?.name || "recipient"}.{" "}
                    {item.acknowledgementNote || ""}
                  </p>
                )}
                {item.engagementId && (
                  <p className="mt-3 inline-flex items-center gap-1 text-xs text-slate-500">
                    <ExternalLink size={13} /> This acknowledgement is recorded
                    against the issued AEO version.
                  </p>
                )}
              </article>
            ))}
          </div>
        )}
      </section>
      <Modal
        open={Boolean(acknowledgementItem)}
        onClose={() => busyId === null && setAcknowledgementItem(null)}
        title="Acknowledge issued AEO"
        description={
          acknowledgementItem
            ? `Record receipt of ${acknowledgementItem.orderCode || "the issued Audit Engagement Order"}.`
            : "Record receipt of the issued Audit Engagement Order."
        }
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              disabled={busyId !== null}
              onClick={() => setAcknowledgementItem(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
              disabled={busyId !== null}
              onClick={submitAcknowledgement}
              type="button"
            >
              {busyId !== null ? "Saving…" : "Submit acknowledgement"}
            </button>
          </>
        }
      >
        <label
          className="block text-sm font-semibold text-slate-700"
          htmlFor="aeo-acknowledgement-note"
        >
          Acknowledgement note
          <textarea
            autoFocus
            className="mt-1.5 min-h-32 w-full resize-y rounded-lg border border-slate-300 p-3 font-normal leading-6 text-slate-700 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
            id="aeo-acknowledgement-note"
            maxLength={4000}
            onChange={(event) => {
              setAcknowledgementNote(event.target.value);
              if (event.target.value.trim().length >= 2)
                setAcknowledgementError("");
            }}
            placeholder="Confirm that the issued AEO was received and recorded."
            value={acknowledgementNote}
          />
          <span className="mt-1 block text-xs font-normal text-slate-500">
            This note is retained with your account, timestamp, and the issued
            AEO version.
          </span>
          {acknowledgementError && (
            <span className="mt-1 block text-xs font-semibold text-red-600">
              {acknowledgementError}
            </span>
          )}
        </label>
      </Modal>
    </div>
  );
}
