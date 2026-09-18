import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  Calculator,
  Download,
  FileCheck2,
  FileText,
  FolderOpen,
  LockKeyhole,
  MessageSquareText,
  Paperclip,
  Plus,
  RefreshCw,
  RotateCcw,
  Search,
  ShieldCheck,
} from "lucide-react";
import { Link } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { ApiError, iapSupportingRecordsApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import ConfirmDialog from "../ui/ConfirmDialog";
import Modal from "../ui/Modal";
import SearchableSelect from "../ui/SearchableSelect";
import StatusBadge from "../ui/StatusBadge";
import { hasPermission } from "../../config/navigation";

const typeTones = {
  RISK_SUPPORT: "warning",
  PLANNING_WORKPAPER: "info",
  MANAGEMENT_DIRECTIVE: "active",
  BUDGET_RESOURCE_SUPPORT: "success",
  APPROVAL_SUPPORT: "success",
  OTHER: "inactive",
};

const commentTones = {
  REVIEW: "info",
  RETURN_INSTRUCTION: "danger",
  MANAGEMENT: "warning",
  APPROVAL_NOTE: "success",
  REVISION_EXPLANATION: "active",
  GENERAL: "inactive",
};

function formatDateTime(value) {
  if (!value) return "-";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(value));
}

function formatSize(value) {
  const bytes = Number(value || 0);
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function linkedRecordLabel(record) {
  if (record.engagement) {
    return `${record.engagement.engagementCode} - ${record.engagement.title}`;
  }
  if (record.riskAssessment) {
    return `${record.riskAssessment.office?.code ?? "Office"} / ${
      record.riskAssessment.auditArea?.code ?? "Audit area"
    }`;
  }
  return "Annual plan";
}

function emptyUpload() {
  return {
    file: null,
    displayName: "",
    description: "",
    attachmentTypeId: "",
    visibility: "INTERNAL",
    linkedRecordType: "PLAN",
    linkedRecordId: "",
  };
}

export default function IapSupportingRecordsPanel({ planId }) {
  const { user, runtimeConfig } = useAuth();
  const toast = useToast();
  const [records, setRecords] = useState({
    attachments: [],
    comments: [],
    attachmentTypes: [],
    riskAssessments: [],
    engagements: [],
    capabilities: {},
  });
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [activeTab, setActiveTab] = useState("ATTACHMENTS");
  const [query, setQuery] = useState("");
  const [typeFilter, setTypeFilter] = useState("");
  const [showArchived, setShowArchived] = useState(false);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [upload, setUpload] = useState(emptyUpload);
  const [uploadErrors, setUploadErrors] = useState({});
  const [commentOpen, setCommentOpen] = useState(false);
  const [commentBody, setCommentBody] = useState("");
  const [commentEngagementId, setCommentEngagementId] = useState("");
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const isManagement = hasPermission(user, "iap.manage_universe");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setRecords(
        await iapSupportingRecordsApi.show(planId, {
          includeArchived: showArchived,
        }),
      );
    } catch (loadError) {
      setError(
        loadError instanceof Error
          ? loadError.message
          : "Unable to load supporting records.",
      );
    } finally {
      setLoading(false);
    }
  }, [planId, showArchived]);

  useEffect(() => {
    let active = true;
    iapSupportingRecordsApi
      .show(planId, { includeArchived: showArchived })
      .then((data) => {
        if (active) {
          setRecords(data);
          setError("");
        }
      })
      .catch((loadError) => {
        if (active) {
          setError(
            loadError instanceof Error
              ? loadError.message
              : "Unable to load supporting records.",
          );
        }
      })
      .finally(() => active && setLoading(false));

    return () => {
      active = false;
    };
  }, [planId, showArchived]);

  const filteredAttachments = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    return records.attachments.filter((attachment) => {
      const matchesType =
        !typeFilter ||
        String(attachment.attachmentTypeId) === String(typeFilter);
      const haystack = [
        attachment.displayName,
        attachment.fileName,
        attachment.description,
        attachment.attachmentType?.label,
        linkedRecordLabel(attachment),
        attachment.uploader?.name,
      ]
        .join(" ")
        .toLowerCase();
      return matchesType && (!normalized || haystack.includes(normalized));
    });
  }, [query, records.attachments, typeFilter]);

  const filteredComments = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return records.comments;
    return records.comments.filter((comment) =>
      [
        comment.body,
        comment.commentType?.label,
        comment.author?.name,
        comment.engagement?.engagementCode,
        comment.engagement?.title,
      ]
        .join(" ")
        .toLowerCase()
        .includes(normalized),
    );
  }, [query, records.comments]);

  const counts = useMemo(
    () => ({
      total: records.attachments.filter((item) => !item.isArchived).length,
      risk: records.attachments.filter(
        (item) =>
          !item.isArchived && item.attachmentType?.code === "RISK_SUPPORT",
      ).length,
      capacity: records.attachments.filter(
        (item) =>
          !item.isArchived &&
          item.attachmentType?.code === "BUDGET_RESOURCE_SUPPORT",
      ).length,
      comments: records.comments.length,
    }),
    [records.attachments, records.comments],
  );

  function openUpload() {
    setUpload(emptyUpload());
    setUploadErrors({});
    setUploadOpen(true);
  }

  async function submitUpload(event) {
    event.preventDefault();
    setBusy(true);
    setUploadErrors({});
    try {
      await iapSupportingRecordsApi.upload(planId, {
        file: upload.file,
        displayName: upload.displayName.trim(),
        description: upload.description.trim(),
        attachmentTypeId: upload.attachmentTypeId,
        visibility: upload.visibility,
        planEngagementId:
          upload.linkedRecordType === "ENGAGEMENT" ? upload.linkedRecordId : "",
        riskAssessmentId:
          upload.linkedRecordType === "RISK_ASSESSMENT"
            ? upload.linkedRecordId
            : "",
      });
      setUploadOpen(false);
      toast.success("Supporting document uploaded successfully.");
      await load();
    } catch (saveError) {
      if (saveError instanceof ApiError) setUploadErrors(saveError.errors);
      toast.error(saveError.message);
    } finally {
      setBusy(false);
    }
  }

  async function submitComment(event) {
    event.preventDefault();
    setBusy(true);
    try {
      await iapSupportingRecordsApi.addComment(planId, {
        body: commentBody.trim(),
        planEngagementId: commentEngagementId || null,
      });
      setCommentOpen(false);
      setCommentBody("");
      setCommentEngagementId("");
      setActiveTab("COMMENTS");
      toast.success("Reviewer comment recorded successfully.");
      await load();
    } catch (saveError) {
      toast.error(saveError.message);
    } finally {
      setBusy(false);
    }
  }

  async function archiveAttachment() {
    setBusy(true);
    try {
      await iapSupportingRecordsApi.archive(planId, archiveTarget.id);
      setArchiveTarget(null);
      toast.success("Supporting document archived successfully.");
      await load();
    } catch (archiveError) {
      toast.error(archiveError.message);
    } finally {
      setBusy(false);
    }
  }

  async function restoreAttachment() {
    setBusy(true);
    try {
      await iapSupportingRecordsApi.restore(planId, restoreTarget.id);
      setRestoreTarget(null);
      toast.success("Supporting document restored successfully.");
      await load();
    } catch (restoreError) {
      toast.error(restoreError.message);
    } finally {
      setBusy(false);
    }
  }

  async function download(attachment) {
    try {
      await iapSupportingRecordsApi.download(planId, attachment);
    } catch (downloadError) {
      toast.error(downloadError.message);
    }
  }

  const linkedOptions =
    upload.linkedRecordType === "ENGAGEMENT"
      ? records.engagements.map((engagement) => ({
          value: engagement.id,
          label: `${engagement.engagementCode} - ${engagement.title}`,
        }))
      : records.riskAssessments.map((assessment) => ({
          value: assessment.id,
          label: `${assessment.label} - ${assessment.assessmentDate ?? "No date"}`,
          keywords: `${assessment.office?.name ?? ""} ${
            assessment.auditArea?.name ?? ""
          }`,
        }));

  return (
    <section className="mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
          <h2 className="flex items-center gap-2 text-base font-bold text-slate-900">
            <FolderOpen className="text-sky-700" size={20} />
            Attachments and management comments
          </h2>
          <p className="mt-1 max-w-3xl text-sm leading-5 text-slate-500">
            Keep risk evidence, planning working papers, directives, capacity
            calculations, approval documents, and immutable review history with
            this plan revision.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {records.capabilities.canComment && (
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-sky-200 bg-white px-4 text-sm font-bold text-sky-700 transition hover:-translate-y-0.5 hover:bg-sky-50 hover:shadow-sm"
              onClick={() => setCommentOpen(true)}
              type="button"
            >
              <MessageSquareText size={17} />
              Add reviewer comment
            </button>
          )}
          {records.capabilities.canUpload && (
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-sky-800 hover:shadow-md"
              onClick={openUpload}
              type="button"
            >
              <Plus size={17} />
              Upload supporting file
            </button>
          )}
        </div>
      </header>

      {records.capabilities.isFrozen && (
        <div className="flex items-start gap-3 border-b border-emerald-200 bg-emerald-50 px-5 py-3 text-sm text-emerald-900">
          <LockKeyhole className="mt-0.5 shrink-0" size={18} />
          <span>
            These records are frozen with the approved plan version. They may be
            downloaded and reviewed, but changes require a formal revision.
          </span>
        </div>
      )}

      <div className="grid gap-3 border-b border-slate-100 bg-slate-50/70 p-4 sm:grid-cols-2 xl:grid-cols-4">
        {[
          [Paperclip, counts.total, "Active attachments", "text-sky-700"],
          [ShieldCheck, counts.risk, "Risk evidence", "text-amber-700"],
          [Calculator, counts.capacity, "Capacity support", "text-emerald-700"],
          [
            MessageSquareText,
            counts.comments,
            "Review and workflow notes",
            "text-violet-700",
          ],
        ].map(([Icon, value, label, color]) => (
          <div
            className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3"
            key={label}
          >
            <span
              className={`grid h-10 w-10 place-items-center rounded-lg bg-slate-50 ${color}`}
            >
              <Icon size={19} />
            </span>
            <div>
              <strong className="block text-xl text-slate-900">{value}</strong>
              <span className="text-xs font-semibold text-slate-500">
                {label}
              </span>
            </div>
          </div>
        ))}
      </div>

      <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 pt-3">
        {[
          ["ATTACHMENTS", Paperclip, "Attachments", records.attachments.length],
          [
            "COMMENTS",
            MessageSquareText,
            "Comments and instructions",
            records.comments.length,
          ],
        ].map(([tab, Icon, label, count]) => (
          <button
            className={`inline-flex min-h-10 items-center gap-2 border-b-2 px-3 text-sm font-bold transition ${
              activeTab === tab
                ? "border-sky-700 text-sky-800"
                : "border-transparent text-slate-500 hover:text-slate-800"
            }`}
            key={tab}
            onClick={() => {
              setActiveTab(tab);
              setQuery("");
            }}
            type="button"
          >
            <Icon size={16} />
            {label}
            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs">
              {count}
            </span>
          </button>
        ))}
      </div>

      <div className="flex flex-col gap-2 border-b border-slate-200 p-4 lg:flex-row">
        <label className="flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3">
          <Search className="text-slate-400" size={17} />
          <input
            className="min-w-0 flex-1 text-sm outline-none"
            onChange={(event) => setQuery(event.target.value)}
            placeholder={
              activeTab === "ATTACHMENTS"
                ? "Search files, categories, linked records, or uploaders..."
                : "Search comments, instructions, authors, or engagements..."
            }
            value={query}
          />
        </label>
        {activeTab === "ATTACHMENTS" && (
          <div className="w-full lg:w-72">
            <SearchableSelect
              onChange={setTypeFilter}
              options={[
                { value: "", label: "All attachment types" },
                ...records.attachmentTypes.map((type) => ({
                  value: type.id,
                  label: type.label,
                  keywords: type.code,
                })),
              ]}
              placeholder="All attachment types"
              value={typeFilter}
            />
          </div>
        )}
        {records.capabilities.canViewArchived &&
          activeTab === "ATTACHMENTS" && (
            <label className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-600">
              <input
                checked={showArchived}
                onChange={(event) => setShowArchived(event.target.checked)}
                type="checkbox"
              />
              Show archived
            </label>
          )}
        <button
          aria-label="Refresh supporting records"
          className="grid min-h-11 min-w-11 place-items-center rounded-lg border border-slate-300 text-slate-600 transition hover:bg-slate-50 hover:text-sky-700"
          onClick={load}
          type="button"
        >
          <RefreshCw className={loading ? "animate-spin" : ""} size={17} />
        </button>
      </div>

      {error ? (
        <div className="p-6 text-sm font-semibold text-red-700">{error}</div>
      ) : loading ? (
        <div className="grid min-h-40 place-items-center text-sm font-semibold text-slate-500">
          Loading supporting records...
        </div>
      ) : activeTab === "ATTACHMENTS" ? (
        filteredAttachments.length ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-5 py-3">Supporting document</th>
                  <th className="px-5 py-3">Category</th>
                  <th className="px-5 py-3">Linked record</th>
                  <th className="px-5 py-3">Uploaded</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filteredAttachments.map((attachment) => (
                  <tr
                    className={
                      attachment.isArchived ? "bg-slate-50 opacity-75" : ""
                    }
                    key={attachment.id}
                  >
                    <td className="px-5 py-4">
                      <div className="flex items-start gap-3">
                        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-700">
                          <FileText size={19} />
                        </span>
                        <div className="min-w-0">
                          <strong className="block text-slate-900">
                            {attachment.displayName}
                          </strong>
                          <span className="mt-0.5 block text-xs text-slate-500">
                            {attachment.fileName} ·{" "}
                            {formatSize(attachment.fileSize)}
                          </span>
                          {attachment.description && (
                            <span className="mt-1 block max-w-lg text-xs leading-5 text-slate-500">
                              {attachment.description}
                            </span>
                          )}
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-4">
                      <StatusBadge
                        tone={
                          typeTones[attachment.attachmentType?.code] ??
                          "inactive"
                        }
                      >
                        {attachment.attachmentType?.label ?? "Supporting file"}
                      </StatusBadge>
                      {attachment.visibility === "MANAGEMENT" && (
                        <span className="mt-1.5 block text-xs font-semibold text-violet-700">
                          Management only
                        </span>
                      )}
                    </td>
                    <td className="px-5 py-4 text-sm text-slate-700">
                      {linkedRecordLabel(attachment)}
                    </td>
                    <td className="px-5 py-4">
                      <strong className="block text-xs text-slate-700">
                        {attachment.uploader?.name ?? "System"}
                      </strong>
                      <span className="mt-1 block text-xs text-slate-500">
                        {formatDateTime(attachment.createdAt)}
                      </span>
                      {attachment.isArchived && (
                        <StatusBadge tone="inactive">Archived</StatusBadge>
                      )}
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-2">
                        <button
                          aria-label={`Download ${attachment.displayName}`}
                          className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-sky-700 transition hover:bg-sky-50"
                          onClick={() => download(attachment)}
                          title="Download"
                          type="button"
                        >
                          <Download size={16} />
                        </button>
                        {!attachment.isArchived &&
                          records.capabilities.canArchive && (
                            <button
                              aria-label={`Archive ${attachment.displayName}`}
                              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-red-600 transition hover:bg-red-50"
                              onClick={() => setArchiveTarget(attachment)}
                              title="Archive"
                              type="button"
                            >
                              <Archive size={16} />
                            </button>
                          )}
                        {attachment.isArchived &&
                          records.capabilities.canRestore && (
                            <button
                              aria-label={`Restore ${attachment.displayName}`}
                              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-emerald-700 transition hover:bg-emerald-50"
                              onClick={() => setRestoreTarget(attachment)}
                              title="Restore"
                              type="button"
                            >
                              <RotateCcw size={16} />
                            </button>
                          )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="grid min-h-48 place-items-center p-6 text-center">
            <div>
              <Paperclip className="mx-auto text-slate-300" size={34} />
              <strong className="mt-3 block text-sm text-slate-700">
                No supporting documents found
              </strong>
              <p className="mt-1 text-xs text-slate-500">
                Upload evidence or adjust the current filters.
              </p>
            </div>
          </div>
        )
      ) : filteredComments.length ? (
        <div className="grid gap-3 p-4">
          {filteredComments.map((comment) => (
            <article
              className="rounded-xl border border-slate-200 bg-slate-50/70 p-4"
              key={comment.id}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                  <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-black text-sky-800">
                    {comment.author?.initials ?? "AG"}
                  </span>
                  <div>
                    <strong className="block text-sm text-slate-900">
                      {comment.author?.name ?? "AGIS System"}
                    </strong>
                    <span className="text-xs text-slate-500">
                      {formatDateTime(comment.createdAt)}
                    </span>
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <StatusBadge
                    tone={commentTones[comment.commentType?.code] ?? "inactive"}
                  >
                    {comment.commentType?.label ?? "Planning comment"}
                  </StatusBadge>
                  {comment.isImmutable && (
                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500">
                      <LockKeyhole size={13} />
                      Permanent record
                    </span>
                  )}
                </div>
              </div>
              {comment.engagement && (
                <div className="mt-3 rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800">
                  {comment.engagement.engagementCode} -{" "}
                  {comment.engagement.title}
                </div>
              )}
              <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                {comment.body}
              </p>
            </article>
          ))}
        </div>
      ) : (
        <div className="grid min-h-48 place-items-center p-6 text-center">
          <div>
            <MessageSquareText className="mx-auto text-slate-300" size={34} />
            <strong className="mt-3 block text-sm text-slate-700">
              No comments or workflow instructions found
            </strong>
            <p className="mt-1 text-xs text-slate-500">
              Reviewer comments and workflow notes will appear here.
            </p>
          </div>
        </div>
      )}

      <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-500">
        <span>
          Risk evidence for Audit Universe assessments remains linked to its
          validated assessment record.
        </span>
        <Link
          className="font-bold text-sky-700 hover:text-sky-900"
          to="/internal-audit-planning/risk-assessment"
        >
          Open Risk Assessment evidence
        </Link>
      </footer>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={busy}
              onClick={() => setUploadOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="min-h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={
                busy ||
                !upload.file ||
                !upload.displayName.trim() ||
                !upload.attachmentTypeId ||
                (upload.linkedRecordType !== "PLAN" && !upload.linkedRecordId)
              }
              form="iap-supporting-file-form"
              type="submit"
            >
              {busy ? "Uploading..." : "Upload file"}
            </button>
          </>
        }
        onClose={() => !busy && setUploadOpen(false)}
        open={uploadOpen}
        size="lg"
        title="Upload supporting document"
      >
        <form
          className="grid gap-4"
          id="iap-supporting-file-form"
          onSubmit={submitUpload}
        >
          <label>
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">
              File <span className="text-red-500">*</span>
            </span>
            <input
              accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png"
              className="block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-1.5 file:font-bold file:text-sky-700"
              onChange={(event) => {
                const file = event.target.files?.[0] ?? null;
                setUpload((current) => ({
                  ...current,
                  file,
                  displayName: current.displayName || file?.name || "",
                }));
              }}
              type="file"
            />
            <p className="mt-1 text-xs text-slate-500">
              PDF, Office document, text, CSV, or image. Maximum{" "}
              {runtimeConfig.documentUploadMaxMb} MB.
            </p>
            {uploadErrors.file?.[0] && (
              <p className="mt-1 text-xs font-semibold text-red-600">
                {uploadErrors.file[0]}
              </p>
            )}
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <label>
              <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                Display name <span className="text-red-500">*</span>
              </span>
              <input
                className="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                maxLength={255}
                onChange={(event) =>
                  setUpload((current) => ({
                    ...current,
                    displayName: event.target.value,
                  }))
                }
                value={upload.displayName}
              />
            </label>
            <div>
              <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                Supporting record type <span className="text-red-500">*</span>
              </span>
              <SearchableSelect
                onChange={(value) =>
                  setUpload((current) => ({
                    ...current,
                    attachmentTypeId: value,
                  }))
                }
                options={records.attachmentTypes.map((type) => ({
                  value: type.id,
                  label: type.label,
                  keywords: `${type.code} ${type.description ?? ""}`,
                }))}
                placeholder="Search attachment types..."
                value={upload.attachmentTypeId}
              />
            </div>
          </div>

          <label>
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">
              Description
            </span>
            <textarea
              className="min-h-24 w-full rounded-lg border border-slate-300 p-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
              maxLength={3000}
              onChange={(event) =>
                setUpload((current) => ({
                  ...current,
                  description: event.target.value,
                }))
              }
              placeholder="Explain how this file supports the plan."
              value={upload.description}
            />
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <label>
              <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                Link file to
              </span>
              <select
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500"
                onChange={(event) =>
                  setUpload((current) => ({
                    ...current,
                    linkedRecordType: event.target.value,
                    linkedRecordId: "",
                  }))
                }
                value={upload.linkedRecordType}
              >
                <option value="PLAN">Annual plan</option>
                <option value="RISK_ASSESSMENT">Risk assessment</option>
                <option value="ENGAGEMENT">Proposed engagement</option>
              </select>
            </label>
            {upload.linkedRecordType !== "PLAN" && (
              <div>
                <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                  Linked record <span className="text-red-500">*</span>
                </span>
                <SearchableSelect
                  onChange={(value) =>
                    setUpload((current) => ({
                      ...current,
                      linkedRecordId: value,
                    }))
                  }
                  options={linkedOptions}
                  placeholder="Search records..."
                  value={upload.linkedRecordId}
                />
              </div>
            )}
          </div>

          {isManagement && (
            <label>
              <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                Visibility
              </span>
              <select
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500"
                onChange={(event) =>
                  setUpload((current) => ({
                    ...current,
                    visibility: event.target.value,
                  }))
                }
                value={upload.visibility}
              >
                <option value="INTERNAL">All authorized plan users</option>
                <option value="MANAGEMENT">CIAS management only</option>
              </select>
            </label>
          )}
        </form>
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={busy}
              onClick={() => setCommentOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="min-h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={busy || !commentBody.trim()}
              form="iap-reviewer-comment-form"
              type="submit"
            >
              {busy ? "Recording..." : "Record comment"}
            </button>
          </>
        }
        onClose={() => !busy && setCommentOpen(false)}
        open={commentOpen}
        size="md"
        title="Add reviewer comment"
      >
        <form
          className="grid gap-4"
          id="iap-reviewer-comment-form"
          onSubmit={submitComment}
        >
          <div className="rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm leading-6 text-sky-900">
            <FileCheck2 className="mr-2 inline" size={17} />
            Reviewer comments are permanent planning records. Return
            instructions and approval notes are recorded through their workflow
            actions.
          </div>
          <div>
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">
              Related engagement
            </span>
            <SearchableSelect
              onChange={setCommentEngagementId}
              options={[
                { value: "", label: "Entire annual plan" },
                ...records.engagements.map((engagement) => ({
                  value: engagement.id,
                  label: `${engagement.engagementCode} - ${engagement.title}`,
                })),
              ]}
              placeholder="Entire annual plan"
              value={commentEngagementId}
            />
          </div>
          <label>
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">
              Reviewer comment <span className="text-red-500">*</span>
            </span>
            <textarea
              autoFocus
              className="min-h-36 w-full rounded-lg border border-slate-300 p-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
              maxLength={10000}
              onChange={(event) => setCommentBody(event.target.value)}
              placeholder="Record the observation, clarification, or required review consideration..."
              value={commentBody}
            />
          </label>
        </form>
      </Modal>

      <ConfirmDialog
        busy={busy}
        confirmLabel="Archive document"
        description={`${archiveTarget?.displayName ?? "This file"} will be removed from active supporting records but retained for recovery.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archiveAttachment}
        open={Boolean(archiveTarget)}
        title="Archive supporting document?"
        tone="danger"
      />
      <ConfirmDialog
        busy={busy}
        confirmLabel="Restore document"
        description={`${restoreTarget?.displayName ?? "This file"} will return to the plan's active supporting records.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restoreAttachment}
        open={Boolean(restoreTarget)}
        title="Restore supporting document?"
      />
    </section>
  );
}
