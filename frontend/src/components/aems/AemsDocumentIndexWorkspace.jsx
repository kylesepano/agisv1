import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  Download,
  FileArchive,
  FilePlus2,
  LockKeyhole,
  RefreshCw,
  XCircle,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsDocumentIndexApi } from "../../services/api";

const initialForm = {
  documentVersionId: "",
  recordCategoryCode: "OTHER",
  referenceCode: "",
  title: "",
  confidentialityCode: "INTERNAL",
};

export default function AemsDocumentIndexWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [workspace, setWorkspace] = useState(null);
  const [form, setForm] = useState(initialForm);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setWorkspace(await aemsDocumentIndexApi.show(engagementId));
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
        data-testid="document-index-loading"
      >
        <RefreshCw className="animate-spin text-sky-700" size={28} />
      </div>
    );
  }

  if (!workspace) {
    return (
      <div className="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm font-semibold text-rose-700">
        {error || "The final document index is unavailable."}
      </div>
    );
  }

  const locked = Boolean(workspace.lockedAt);
  const canManage =
    hasPermission(user, "aems.document-index.manage") && !locked;
  const canExclude =
    hasPermission(user, "aems.document-index.finalize") && !locked;

  return (
    <div className="space-y-5" data-testid="document-index-workspace">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <FileArchive className="text-sky-700" size={21} />
              <h3 className="font-bold text-slate-900">Final Document Index</h3>
            </div>
            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
              Discover eligible engagement records, preserve exact Core
              DocumentVersion IDs, identify broken files, and authorize
              exclusions without copying private files.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {canManage && (
              <button
                className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-sky-300 px-3 text-sm font-bold text-sky-700"
                disabled={busy}
                onClick={() =>
                  void act(
                    () => aemsDocumentIndexApi.refresh(engagementId),
                    "Eligible records discovered and indexed.",
                  )
                }
                type="button"
              >
                <RefreshCw size={15} /> Discover records
              </button>
            )}
            <a
              className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-slate-800 px-3 text-sm font-bold text-white"
              href={aemsDocumentIndexApi.exportUrl(engagementId)}
            >
              <Download size={15} /> Export CSV
            </a>
          </div>
        </div>
        {locked && (
          <div className="mt-4 flex gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            <LockKeyhole className="shrink-0" size={18} />
            Final index locked at{" "}
            {new Date(workspace.lockedAt).toLocaleString()}.
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

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {[
          ["Indexed", workspace.summary.total],
          ["Included", workspace.summary.included],
          ["Excluded", workspace.summary.excluded],
          ["Eligible missing", workspace.summary.eligibleMissing],
          ["Broken files", workspace.summary.brokenReferences],
        ].map(([label, value]) => (
          <div
            className="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm"
            key={label}
          >
            <strong className="block text-2xl text-sky-800">{value}</strong>
            <span className="text-xs font-bold uppercase text-slate-400">
              {label}
            </span>
          </div>
        ))}
      </div>

      {(workspace.summary.eligibleMissing > 0 ||
        workspace.summary.brokenReferences > 0) && (
        <section className="rounded-xl border border-amber-200 bg-amber-50 p-4">
          <div className="flex items-center gap-2 text-amber-800">
            <AlertTriangle size={18} />
            <strong>Index requires attention</strong>
          </div>
          <p className="mt-2 text-sm text-amber-700">
            Discover missing eligible records and restore broken immutable file
            references before Closure approval.
          </p>
        </section>
      )}

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="min-w-[980px] w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th className="px-4 py-3">#</th>
                <th className="px-4 py-3">Category / reference</th>
                <th className="px-4 py-3">Title</th>
                <th className="px-4 py-3">Exact version</th>
                <th className="px-4 py-3">File</th>
                <th className="px-4 py-3">Disposition</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {workspace.items.map((item) => (
                <tr key={item.id}>
                  <td className="px-4 py-3 font-bold text-slate-500">
                    {item.sequenceNo}
                  </td>
                  <td className="px-4 py-3">
                    <strong className="block text-slate-800">
                      {item.referenceCode}
                    </strong>
                    <span className="text-xs text-slate-400">
                      {item.recordCategoryCode}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-700">{item.title}</td>
                  <td className="px-4 py-3 text-xs text-slate-500">
                    <div>DocumentVersion #{item.documentVersionId}</div>
                    <code className="mt-1 block max-w-56 truncate">
                      {item.checksumSha256 || "No checksum"}
                    </code>
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`rounded-full px-2 py-1 text-xs font-bold ${
                        item.fileAvailable
                          ? "bg-emerald-100 text-emerald-700"
                          : "bg-rose-100 text-rose-700"
                      }`}
                    >
                      {item.fileAvailable ? "AVAILABLE" : "MISSING"}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {item.includedFlag ? (
                      <div>
                        <span className="text-xs font-bold text-emerald-700">
                          INCLUDED
                        </span>
                        {canExclude && (
                          <button
                            className="ml-3 text-xs font-bold text-rose-700 underline"
                            onClick={() => {
                              const reason = window.prompt(
                                "Required exclusion reason and authority basis:",
                              );
                              if (!reason) return;
                              void act(
                                () =>
                                  aemsDocumentIndexApi.exclude(
                                    engagementId,
                                    item.id,
                                    { reason },
                                  ),
                                "Record excluded with authority.",
                              );
                            }}
                            type="button"
                          >
                            Exclude
                          </button>
                        )}
                      </div>
                    ) : (
                      <div className="text-xs text-rose-700">
                        <span className="font-bold">EXCLUDED</span>
                        <p className="mt-1 max-w-56">{item.exclusionReason}</p>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
              {workspace.items.length === 0 && (
                <tr>
                  <td
                    className="px-4 py-10 text-center text-slate-500"
                    colSpan={6}
                  >
                    No records have been indexed yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      {workspace.eligibleMissing.length > 0 && (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <h3 className="font-bold text-slate-900">
            Eligible records not yet indexed
          </h3>
          <div className="mt-3 grid gap-2 md:grid-cols-2">
            {workspace.eligibleMissing.map((item) => (
              <div
                className="rounded-lg border border-slate-200 p-3 text-sm"
                key={`${item.recordType}-${item.recordId}-${item.documentVersionId}`}
              >
                <strong className="text-slate-800">{item.referenceCode}</strong>
                <p className="mt-1 text-xs text-slate-500">{item.title}</p>
              </div>
            ))}
          </div>
        </section>
      )}

      {canManage && (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div className="flex items-center gap-2">
            <FilePlus2 className="text-sky-700" size={18} />
            <h3 className="font-bold text-slate-900">
              Add authorized supporting record
            </h3>
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            {[
              ["documentVersionId", "DocumentVersion ID", "number"],
              ["recordCategoryCode", "Category", "text"],
              ["referenceCode", "Reference code", "text"],
              ["title", "Title", "text"],
              ["confidentialityCode", "Confidentiality", "text"],
            ].map(([key, label, type]) => (
              <label className="block" key={key}>
                <span className="text-xs font-bold uppercase text-slate-500">
                  {label}
                </span>
                <input
                  className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      [key]: event.target.value,
                    }))
                  }
                  type={type}
                  value={form[key]}
                />
              </label>
            ))}
          </div>
          <button
            className="mt-3 inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
            disabled={
              busy ||
              !form.documentVersionId ||
              !form.referenceCode ||
              !form.title
            }
            onClick={() =>
              void act(
                () =>
                  aemsDocumentIndexApi.add(engagementId, {
                    ...form,
                    documentVersionId: Number(form.documentVersionId),
                  }),
                "Authorized supporting record indexed.",
              ).then(() => setForm(initialForm))
            }
            type="button"
          >
            <FilePlus2 size={15} /> Add supporting record
          </button>
        </section>
      )}

      {locked && (
        <div className="flex gap-2 text-xs text-slate-500">
          <XCircle size={14} />
          Physical destruction, public exposure, and archive are not performed
          by Engagement Closure.
        </div>
      )}
    </div>
  );
}
