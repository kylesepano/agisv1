import { useMemo, useState } from "react";
import { Download, Paperclip, Trash2, Upload } from "lucide-react";
import ConfirmDialog from "../ui/ConfirmDialog";
import FormField from "../ui/FormField";
import { hasPermission } from "../../config/navigation";
import { ApiError, cmsApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const textareaClass =
  "mt-1 min-h-20 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";

function firstError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

function formatBytes(value) {
  if (!value) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const index = Math.min(
    Math.floor(Math.log(value) / Math.log(1024)),
    units.length - 1,
  );
  const amount = value / 1024 ** index;
  return `${amount.toFixed(index === 0 || amount >= 10 ? 0 : 1)} ${units[index]}`;
}

function displayDate(value) {
  if (!value) return "Not available";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? "Not available"
    : new Intl.DateTimeFormat("en-PH", {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(date);
}

export default function CmsProgressEvidencePanel({
  updateId,
  version,
  milestoneProgress = [],
  confidentialityLevels = [],
  onRefresh,
  user,
}) {
  const toast = useToast();
  const [form, setForm] = useState({
    file: null,
    evidenceCategory: "MANAGEMENT_SUPPORT",
    title: "",
    description: "",
    sourceOrCustodian: "",
    confidentialityLevelId: "",
    milestoneProgressId: "",
  });
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const [removalTarget, setRemovalTarget] = useState(null);
  const [removalReason, setRemovalReason] = useState("");
  const [removalError, setRemovalError] = useState("");

  const canUpload =
    hasPermission(user, "cms.evidence.upload") &&
    version?.availableActions?.includes("upload-evidence");
  const canRemove =
    hasPermission(user, "cms.evidence.remove_draft") &&
    version?.status === "DRAFT";
  const evidence = useMemo(() => version?.evidence ?? [], [version]);

  async function upload(event) {
    event.preventDefault();
    if (!form.file || busy) return;
    setBusy(true);
    setErrors({});
    const payload = new FormData();
    payload.set("lockVersion", String(version.lockVersion));
    payload.set("evidenceCategory", form.evidenceCategory);
    payload.set("title", form.title.trim());
    payload.set("description", form.description.trim());
    payload.set("sourceOrCustodian", form.sourceOrCustodian.trim());
    payload.set("confidentialityLevelId", String(form.confidentialityLevelId));
    payload.set("file", form.file);
    if (form.milestoneProgressId)
      payload.set("milestoneProgressId", form.milestoneProgressId);
    try {
      await cmsApi.uploadProgressEvidence(updateId, version.id, payload);
      toast.success("Supporting evidence linked to the Progress Update draft.");
      setForm({
        ...form,
        file: null,
        title: "",
        description: "",
        sourceOrCustodian: "",
        milestoneProgressId: "",
      });
      await onRefresh();
    } catch (error) {
      setErrors(error.errors ?? {});
      toast.error(
        error.message || "The supporting evidence could not be uploaded.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function download(item) {
    try {
      await cmsApi.downloadProgressEvidence(
        item.id,
        item.fileName || item.title || "supporting-evidence",
      );
    } catch (error) {
      toast.error(
        error.message || "The supporting evidence could not be downloaded.",
      );
    }
  }

  async function remove() {
    if (!removalTarget || !removalReason.trim() || busy) {
      setRemovalError("A removal reason is required.");
      return;
    }
    setBusy(true);
    setRemovalError("");
    try {
      await cmsApi.removeProgressEvidence(removalTarget.id, {
        lockVersion: version.lockVersion,
        removalReason: removalReason.trim(),
      });
      toast.success(
        "Draft evidence link removed. The Core document was retained.",
      );
      setRemovalTarget(null);
      setRemovalReason("");
      await onRefresh();
    } catch (error) {
      if (error instanceof ApiError && [403, 409, 422].includes(error.status)) {
        setRemovalError(error.message);
      } else {
        toast.error(error.message || "The evidence link could not be removed.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-lg font-bold text-slate-800">
            Supporting evidence
          </h3>
          <p className="mt-1 text-sm leading-6 text-slate-500">
            Evidence is linked to an exact immutable Core Document Version.
            Sufficiency and effectiveness are not independently assessed here.
          </p>
        </div>
        <span className="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
          {evidence.length} linked document{evidence.length === 1 ? "" : "s"}
        </span>
      </div>

      {canUpload && (
        <form
          className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4"
          onSubmit={upload}
        >
          <div className="flex items-center gap-2 text-sm font-bold text-sky-900">
            <Upload size={16} /> Link evidence to this draft
          </div>
          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <FormField
              error={firstError(errors, "file")}
              htmlFor="progress-evidence-file"
              label="File"
              required
            >
              <input
                className={inputClass}
                id="progress-evidence-file"
                onChange={(event) =>
                  setForm({ ...form, file: event.target.files?.[0] ?? null })
                }
                type="file"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
              />
              {form.file && (
                <p className="mt-1 text-xs text-slate-600">
                  {form.file.name} · {formatBytes(form.file.size)}
                </p>
              )}
            </FormField>
            <FormField
              error={firstError(errors, "evidenceCategory")}
              htmlFor="progress-evidence-category"
              label="Evidence category"
              required
            >
              <input
                className={inputClass}
                id="progress-evidence-category"
                maxLength={80}
                onChange={(event) =>
                  setForm({ ...form, evidenceCategory: event.target.value })
                }
                value={form.evidenceCategory}
              />
            </FormField>
            <FormField
              error={firstError(errors, "title")}
              htmlFor="progress-evidence-title"
              label="Title"
              required
            >
              <input
                className={inputClass}
                id="progress-evidence-title"
                maxLength={255}
                onChange={(event) =>
                  setForm({ ...form, title: event.target.value })
                }
                value={form.title}
              />
            </FormField>
            <FormField
              error={firstError(errors, "confidentialityLevelId")}
              htmlFor="progress-evidence-confidentiality"
              label="Confidentiality"
              required
              hint="The stricter effective classification is applied by Core."
            >
              <select
                className={inputClass}
                id="progress-evidence-confidentiality"
                onChange={(event) =>
                  setForm({
                    ...form,
                    confidentialityLevelId: event.target.value,
                  })
                }
                value={form.confidentialityLevelId}
              >
                <option value="">Select confidentiality</option>
                {confidentialityLevels.map((level) => (
                  <option key={level.id} value={level.id}>
                    {level.label || level.name || level.code}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField
              error={firstError(errors, "milestoneProgressId")}
              htmlFor="progress-evidence-milestone"
              label="Link to milestone (optional)"
            >
              <select
                className={inputClass}
                id="progress-evidence-milestone"
                onChange={(event) =>
                  setForm({ ...form, milestoneProgressId: event.target.value })
                }
                value={form.milestoneProgressId}
              >
                <option value="">General Progress Update evidence</option>
                {milestoneProgress.map((item) => (
                  <option key={item.id} value={item.id}>
                    Milestone {item.milestoneSequence}:{" "}
                    {item.milestoneSnapshot?.title || "Milestone"}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField
              error={firstError(errors, "sourceOrCustodian")}
              htmlFor="progress-evidence-source"
              label="Source or custodian"
            >
              <input
                className={inputClass}
                id="progress-evidence-source"
                maxLength={255}
                onChange={(event) =>
                  setForm({ ...form, sourceOrCustodian: event.target.value })
                }
                value={form.sourceOrCustodian}
              />
            </FormField>
            <div className="md:col-span-2">
              <FormField
                error={firstError(errors, "description")}
                htmlFor="progress-evidence-description"
                label="Description"
              >
                <textarea
                  className={textareaClass}
                  id="progress-evidence-description"
                  maxLength={3000}
                  onChange={(event) =>
                    setForm({ ...form, description: event.target.value })
                  }
                  value={form.description}
                />
              </FormField>
            </div>
          </div>
          <button
            className="mt-4 inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
            disabled={busy || !form.file}
            type="submit"
          >
            <Paperclip size={16} /> {busy ? "Uploading..." : "Link evidence"}
          </button>
        </form>
      )}

      <div className="mt-5 grid gap-3">
        {evidence.length === 0 ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
            No supporting evidence is linked to this version.
          </div>
        ) : (
          evidence.map((item) => (
            <article
              className="rounded-xl border border-slate-200 p-4"
              key={item.id}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <h4 className="break-words font-bold text-slate-800">
                    {item.title || item.fileName || "Supporting evidence"}
                  </h4>
                  <p className="mt-1 text-xs text-slate-500">
                    {item.evidenceCategory || "Uncategorized"} ·{" "}
                    {item.fileName || "File name unavailable"} ·{" "}
                    {formatBytes(item.fileSize)}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  {hasPermission(user, "cms.evidence.download") && (
                    <button
                      className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50"
                      onClick={() => download(item)}
                      type="button"
                    >
                      <Download size={14} /> Download
                    </button>
                  )}
                  {canRemove && !item.isRemoved && (
                    <button
                      className="inline-flex h-9 items-center gap-2 rounded-lg border border-red-300 bg-white px-3 text-xs font-bold text-red-700 hover:bg-red-50"
                      onClick={() => {
                        setRemovalTarget(item);
                        setRemovalError("");
                      }}
                      type="button"
                    >
                      <Trash2 size={14} /> Remove draft link
                    </button>
                  )}
                </div>
              </div>
              <dl className="mt-3 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                  <dt className="font-bold text-slate-500">Document Version</dt>
                  <dd className="mt-1 break-all">
                    {item.documentVersionId ?? "Not available"}
                  </dd>
                </div>
                <div>
                  <dt className="font-bold text-slate-500">Checksum</dt>
                  <dd className="mt-1 break-all font-mono">
                    {item.checksumSha256 || "Not available"}
                  </dd>
                </div>
                <div>
                  <dt className="font-bold text-slate-500">Confidentiality</dt>
                  <dd className="mt-1">
                    {item.confidentiality?.label ||
                      item.confidentiality?.code ||
                      "Restricted by Core"}
                  </dd>
                </div>
                <div>
                  <dt className="font-bold text-slate-500">Linked</dt>
                  <dd className="mt-1">{displayDate(item.linkedAt)}</dd>
                </div>
              </dl>
              {item.description && (
                <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-600">
                  {item.description}
                </p>
              )}
            </article>
          ))
        )}
      </div>

      <ConfirmDialog
        busy={busy}
        confirmLabel="Remove evidence link"
        description="This removes only the draft CMS evidence link. The Core Document and immutable Document Version will be retained."
        onCancel={() => !busy && setRemovalTarget(null)}
        onConfirm={remove}
        open={Boolean(removalTarget)}
        title="Remove draft evidence link"
        tone="danger"
      >
        <div className="mt-4">
          <FormField
            error={removalError}
            htmlFor="progress-evidence-removal-reason"
            label="Removal reason"
            required
          >
            <textarea
              className={textareaClass}
              id="progress-evidence-removal-reason"
              maxLength={2000}
              onChange={(event) => setRemovalReason(event.target.value)}
              value={removalReason}
            />
          </FormField>
        </div>
      </ConfirmDialog>
    </section>
  );
}
