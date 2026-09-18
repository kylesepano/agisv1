import { useEffect, useMemo, useState } from "react";
import {
  Archive,
  BookOpen,
  CircleCheckBig,
  CirclePause,
  Download,
  FileClock,
  Files,
  GitBranch,
  Link2,
  LockKeyhole,
  Pencil,
  RotateCcw,
  Search,
  Upload,
  X,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import DataTable from "../../components/ui/DataTable";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { ApiError, documentApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const emptyForm = {
  documentTypeId: "",
  confidentialityLevelId: "",
  title: "",
  referenceNumber: "",
  issuingAuthority: "",
  publicationDate: "",
  version: "",
  description: "",
  isActive: true,
  file: null,
  linkKeys: [],
};

const emptyVersionForm = {
  versionLabel: "",
  changeSummary: "",
  file: null,
};

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

function formatBytes(bytes) {
  if (!bytes) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const unit = Math.min(
    Math.floor(Math.log(bytes) / Math.log(1024)),
    units.length - 1,
  );
  const value = bytes / 1024 ** unit;
  return `${value.toFixed(unit === 0 || value >= 10 ? 0 : 1)} ${units[unit]}`;
}

function formatDate(value) {
  if (!value) return "Not specified";
  return new Intl.DateTimeFormat("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

/**
 * Provides the governed document repository, including confidentiality,
 * immutable versions, module links, permission-aware actions, and archiving.
 */
export default function DocumentManagementPage() {
  const { user, runtimeConfig } = useAuth();
  const toast = useToast();
  const [documents, setDocuments] = useState([]);
  const [documentTypes, setDocumentTypes] = useState([]);
  const [confidentialityLevels, setConfidentialityLevels] = useState([]);
  const [linkOptions, setLinkOptions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [typeFilter, setTypeFilter] = useState("");
  const [confidentialityFilter, setConfidentialityFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [selectedDocument, setSelectedDocument] = useState(null);
  useRecordView(selectedDocument, {
    module: "CORE",
    recordType: "DOCUMENT",
    code: (record) => record.documentCode ?? record.referenceNumber,
    label: (record) => record.title,
  });
  const [editing, setEditing] = useState(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [saveConfirmOpen, setSaveConfirmOpen] = useState(false);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [versionTarget, setVersionTarget] = useState(null);
  const [versionForm, setVersionForm] = useState(emptyVersionForm);
  const [versionErrors, setVersionErrors] = useState({});
  const [versionConfirmOpen, setVersionConfirmOpen] = useState(false);

  const canUpload = hasPermission(user, "documents.upload");
  const canUpdate = hasPermission(user, "documents.update");
  const canDelete = hasPermission(user, "documents.delete");
  const canRestore = hasPermission(user, "documents.restore");
  const canDownload = hasPermission(user, "documents.download");

  useEffect(() => {
    let active = true;

    documentApi
      .list({ includeArchived: true })
      .then((result) => {
        if (!active) return;
        setDocuments(result.documents);
        setDocumentTypes(result.documentTypes);
        setConfidentialityLevels(result.confidentialityLevels);
        setLinkOptions(result.linkOptions);
      })
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));

    return () => {
      active = false;
    };
  }, [toast]);

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();

    return documents.filter(
      (document) =>
        (!query ||
          [
            document.title,
            document.referenceNumber,
            document.issuingAuthority,
            document.documentType,
            document.confidentialityLevel,
            document.documentCode,
            document.description,
            document.fileName,
            document.version,
            document.uploadedBy,
            ...(document.links ?? []).flatMap((link) => [
              link.module,
              link.moduleLabel,
              link.label,
            ]),
          ].some((value) => value?.toLowerCase().includes(query))) &&
        (!typeFilter ||
          String(document.documentTypeId) === String(typeFilter)) &&
        (!confidentialityFilter ||
          String(document.confidentialityLevelId) ===
            String(confidentialityFilter)) &&
        (!statusFilter ||
          (statusFilter === "archived"
            ? document.isArchived
            : statusFilter === "active"
              ? document.isActive && !document.isArchived
              : !document.isActive && !document.isArchived)),
    );
  }, [confidentialityFilter, documents, search, statusFilter, typeFilter]);

  const stats = useMemo(() => {
    const active = documents.filter(
      (document) => document.isActive && !document.isArchived,
    ).length;
    const archived = documents.filter((document) => document.isArchived).length;
    const inactive = documents.length - active - archived;
    return {
      total: documents.length,
      active,
      inactive,
      archived,
    };
  }, [documents]);

  const typeOptions = useMemo(
    () =>
      documentTypes.map((type) => ({
        value: type.id,
        label: type.label,
        description: type.description,
        keywords: type.code,
      })),
    [documentTypes],
  );
  const moduleLinkOptions = useMemo(
    () =>
      linkOptions.map((option) => ({
        value: option.key,
        label: option.label,
        description: `${option.moduleLabel} · ${option.recordType.replaceAll("_", " ").toLowerCase()}`,
        keywords: `${option.module} ${option.moduleLabel} ${option.recordCode ?? ""} ${option.recordType}`,
      })),
    [linkOptions],
  );
  const confidentialityOptions = useMemo(
    () =>
      confidentialityLevels.map((level) => ({
        value: level.id,
        label: level.label,
        description: level.description,
        keywords: level.code,
      })),
    [confidentialityLevels],
  );
  const hasActiveFilters = Boolean(
    search || typeFilter || confidentialityFilter || statusFilter,
  );

  function showEditor(document = null) {
    setEditing(document);
    setErrors({});
    setForm(
      document
        ? {
            documentTypeId: document.documentTypeId,
            confidentialityLevelId: document.confidentialityLevelId,
            title: document.title,
            referenceNumber: document.referenceNumber ?? "",
            issuingAuthority: document.issuingAuthority ?? "",
            publicationDate: document.publicationDate ?? "",
            version: document.version ?? "",
            description: document.description ?? "",
            isActive: document.isActive,
            file: null,
            linkKeys: document.linkKeys ?? [],
          }
        : {
            ...emptyForm,
            documentTypeId: documentTypes[0]?.id ?? "",
            confidentialityLevelId:
              confidentialityLevels.find((level) => level.code === "INTERNAL")
                ?.id ??
              confidentialityLevels[0]?.id ??
              "",
          },
    );
    setEditorOpen(true);
  }

  function submitDocument(event) {
    event.preventDefault();
    setSaveConfirmOpen(true);
  }

  function buildFormData() {
    const payload = new FormData();
    payload.set("documentTypeId", form.documentTypeId);
    payload.set("confidentialityLevelId", form.confidentialityLevelId);
    payload.set("title", form.title);
    payload.set("referenceNumber", form.referenceNumber);
    payload.set("issuingAuthority", form.issuingAuthority);
    payload.set("publicationDate", form.publicationDate);
    payload.set("description", form.description);
    payload.set("isActive", form.isActive ? "1" : "0");
    payload.set(
      "links",
      JSON.stringify(
        form.linkKeys
          .map((key) => linkOptions.find((option) => option.key === key))
          .filter(Boolean)
          .map((option) => ({
            module: option.module,
            recordType: option.recordType,
            recordId: option.recordId,
          })),
      ),
    );
    if (!editing) payload.set("version", form.version);
    if (form.file) payload.set("file", form.file);
    return payload;
  }

  async function persistDocument() {
    setSaving(true);
    setErrors({});
    try {
      if (editing) await documentApi.update(editing.id, buildFormData());
      else await documentApi.create(buildFormData());

      const result = await documentApi.list({ includeArchived: true });
      setDocuments(result.documents);
      setDocumentTypes(result.documentTypes);
      setConfidentialityLevels(result.confidentialityLevels);
      setLinkOptions(result.linkOptions);
      setSaveConfirmOpen(false);
      setEditorOpen(false);
      toast.success(
        editing
          ? "Document metadata and module links updated successfully."
          : "Document uploaded successfully.",
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(error.errors);
      }
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function archiveDocument() {
    if (!archiveTarget) return;
    setSaving(true);
    try {
      await documentApi.remove(archiveTarget.id);
      setDocuments((current) =>
        current.map((document) =>
          document.id === archiveTarget.id
            ? { ...document, isActive: false, isArchived: true }
            : document,
        ),
      );
      setArchiveTarget(null);
      toast.success("Document archived successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function restoreDocument() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      const restored = await documentApi.restore(restoreTarget.id);
      setDocuments((current) =>
        current.map((document) =>
          document.id === restored.id ? restored : document,
        ),
      );
      setRestoreTarget(null);
      toast.success("Document restored successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function downloadDocument(document) {
    try {
      await documentApi.download(document);
    } catch (error) {
      toast.error(error.message);
    }
  }

  function showVersionEditor(document) {
    setSelectedDocument(null);
    setVersionTarget(document);
    setVersionForm({
      ...emptyVersionForm,
      versionLabel: `Revision ${document.versionCount + 1}`,
    });
    setVersionErrors({});
  }

  async function createVersion() {
    if (!versionTarget) return;
    setSaving(true);
    setVersionErrors({});
    const payload = new FormData();
    payload.set("versionLabel", versionForm.versionLabel);
    payload.set("changeSummary", versionForm.changeSummary);
    if (versionForm.file) payload.set("file", versionForm.file);

    try {
      const updated = await documentApi.createVersion(
        versionTarget.id,
        payload,
      );
      setDocuments((current) =>
        current.map((document) =>
          document.id === updated.id ? updated : document,
        ),
      );
      if (selectedDocument?.id === updated.id) setSelectedDocument(updated);
      setVersionConfirmOpen(false);
      setVersionTarget(null);
      toast.success("A new immutable document version was created.");
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setVersionErrors(error.errors);
      }
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function downloadVersion(document, version) {
    try {
      await documentApi.downloadVersion(document, version);
    } catch (error) {
      toast.error(error.message);
    }
  }

  const columns = [
    {
      key: "title",
      label: "Document",
      render: (document) => (
        <div className="min-w-72">
          <strong className="block text-slate-800">{document.title}</strong>
          <span className="mt-1 block text-xs text-slate-500">
            {document.referenceNumber || document.fileName}
          </span>
        </div>
      ),
    },
    {
      key: "documentType",
      label: "Document Type",
      render: (document) => <StatusBadge>{document.documentType}</StatusBadge>,
    },
    {
      key: "confidentialityLevel",
      label: "Confidentiality",
      render: (document) => (
        <StatusBadge
          tone={
            document.confidentialityCode === "RESTRICTED"
              ? "inactive"
              : document.confidentialityCode === "CONFIDENTIAL"
                ? "warning"
                : document.confidentialityCode === "PUBLIC"
                  ? "active"
                  : undefined
          }
        >
          {document.confidentialityLevel}
        </StatusBadge>
      ),
    },
    {
      key: "versionCount",
      label: "Versions",
      render: (document) => (
        <div className="whitespace-nowrap">
          <strong className="block text-slate-700">
            v{document.currentVersionNumber}
          </strong>
          <span className="text-xs text-slate-500">
            {document.versionCount} immutable{" "}
            {document.versionCount === 1 ? "version" : "versions"}
          </span>
        </div>
      ),
    },
    {
      key: "links",
      label: "Module Links",
      render: (document) =>
        document.links?.length ? (
          <div className="flex max-w-52 flex-wrap gap-1">
            {[...new Set(document.links.map((link) => link.module))].map(
              (module) => (
                <StatusBadge key={module}>{module}</StatusBadge>
              ),
            )}
          </div>
        ) : (
          <span className="text-xs text-slate-400">Repository only</span>
        ),
    },
    {
      key: "issuingAuthority",
      label: "Issuing Authority",
      render: (document) => document.issuingAuthority || "—",
    },
    {
      key: "publicationDate",
      label: "Publication Date",
      render: (document) => formatDate(document.publicationDate),
    },
    {
      key: "fileSize",
      label: "File",
      render: (document) => (
        <div className="whitespace-nowrap">
          <strong className="block uppercase text-slate-700">
            {document.fileExtension || "FILE"}
          </strong>
          <span className="text-xs text-slate-500">
            {formatBytes(document.fileSize)}
          </span>
        </div>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (document) => (
        <StatusBadge
          tone={
            document.isArchived
              ? "inactive"
              : document.isActive
                ? "active"
                : "warning"
          }
        >
          {document.isArchived
            ? "Archived"
            : document.isActive
              ? "Active"
              : "Inactive"}
        </StatusBadge>
      ),
    },
    {
      key: "actions",
      label: "Actions",
      className: "text-right",
      headerClassName: "text-right",
      sortable: false,
      render: (document) => (
        <div className="flex justify-end gap-1">
          {canDownload && !document.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-sky-700 transition hover:bg-sky-100"
              onClick={(event) => {
                event.stopPropagation();
                downloadDocument(document);
              }}
              title="Download document"
              type="button"
            >
              <Download size={17} />
            </button>
          )}
          {canUpdate && !document.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-violet-700 transition hover:bg-violet-100"
              onClick={(event) => {
                event.stopPropagation();
                showVersionEditor(document);
              }}
              title="Create new immutable version"
              type="button"
            >
              <FileClock size={17} />
            </button>
          )}
          {canUpdate && !document.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-blue-700 transition hover:bg-blue-100"
              onClick={(event) => {
                event.stopPropagation();
                showEditor(document);
              }}
              title="Edit document"
              type="button"
            >
              <Pencil size={17} />
            </button>
          )}
          {canDelete && !document.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-red-600 transition hover:bg-red-100"
              onClick={(event) => {
                event.stopPropagation();
                setArchiveTarget(document);
              }}
              title="Archive document"
              type="button"
            >
              <Archive size={17} />
            </button>
          )}
          {canRestore && document.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-emerald-700 transition hover:bg-emerald-100"
              onClick={(event) => {
                event.stopPropagation();
                setRestoreTarget(document);
              }}
              title="Restore document"
              type="button"
            >
              <RotateCcw size={17} />
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="min-h-full bg-sky-50/40 p-4 sm:p-5">
      <RegistryHeader
        actions={
          canUpload && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800"
              onClick={() => showEditor()}
              type="button"
            >
              <Upload size={18} /> Add document
            </button>
          )
        }
        description="Maintain laws, manuals, issuances, policies, books, templates, and other authorized audit references."
        icon={BookOpen}
        readOnly={!canUpload && !canUpdate && !canDelete}
        title="Document Management"
      />

      <section className="mb-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={Files}
          label="Total documents"
          tone="sky"
          value={stats.total}
        />
        <SummaryCard
          icon={CircleCheckBig}
          label="Active documents"
          tone="emerald"
          value={stats.active}
        />
        <SummaryCard
          icon={CirclePause}
          label="Inactive documents"
          tone="amber"
          value={stats.inactive}
        />
        <SummaryCard
          icon={Archive}
          label="Archived documents"
          tone="red"
          value={stats.archived}
        />
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="border-b border-slate-200 bg-white p-4">
          <div className="grid w-full gap-2 lg:grid-cols-[minmax(16rem,1fr)_15rem_14rem_11rem_auto]">
            <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 transition focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
              <Search className="shrink-0" size={17} />
              <input
                className="min-w-0 flex-1 bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search title, reference, authority, file..."
                type="search"
                value={search}
              />
            </label>
            <SearchableSelect
              onChange={setTypeFilter}
              options={[
                { value: "", label: "All document types" },
                ...typeOptions,
              ]}
              placeholder="Filter by document type"
              searchPlaceholder="Search document types..."
              value={typeFilter}
            />
            <SearchableSelect
              onChange={setConfidentialityFilter}
              options={[
                { value: "", label: "All confidentiality levels" },
                ...confidentialityOptions,
              ]}
              placeholder="Filter by confidentiality"
              searchPlaceholder="Search levels..."
              value={confidentialityFilter}
            />
            <SearchableSelect
              onChange={setStatusFilter}
              options={[
                { value: "", label: "All statuses" },
                { value: "active", label: "Active" },
                { value: "inactive", label: "Inactive" },
                { value: "archived", label: "Archived" },
              ]}
              placeholder="Filter by status"
              searchPlaceholder="Search statuses..."
              value={statusFilter}
            />
            <button
              className="flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
              disabled={!hasActiveFilters}
              onClick={() => {
                setSearch("");
                setTypeFilter("");
                setConfidentialityFilter("");
                setStatusFilter("");
              }}
              type="button"
            >
              <X size={16} />
              Clear filters
            </button>
          </div>
        </header>

        <div className="[&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-sky-50/60 [&_thead]:bg-slate-50/90">
          <DataTable
            columns={columns}
            emptyMessage="No documents match your filters. Upload a reference such as the PGIAM, a law, circular, policy, or audit guide."
            key={`${search}|${typeFilter}|${confidentialityFilter}|${statusFilter}`}
            loading={loading}
            onRowClick={setSelectedDocument}
            pageSizeOptions={[8, 10, 25, 50]}
            rows={filtered}
          />
        </div>
      </section>

      <Modal
        onClose={() => setSelectedDocument(null)}
        open={Boolean(selectedDocument)}
        size="xl"
        title={selectedDocument?.title ?? "Document details"}
      >
        {selectedDocument && (
          <div className="grid gap-5">
            <div className="rounded-xl bg-slate-50 p-4">
              <div className="flex flex-wrap gap-2">
                <StatusBadge>{selectedDocument.documentType}</StatusBadge>
                <StatusBadge
                  tone={
                    selectedDocument.confidentialityCode === "RESTRICTED"
                      ? "inactive"
                      : selectedDocument.confidentialityCode === "CONFIDENTIAL"
                        ? "warning"
                        : selectedDocument.confidentialityCode === "PUBLIC"
                          ? "active"
                          : undefined
                  }
                >
                  <LockKeyhole className="mr-1 inline" size={12} />
                  {selectedDocument.confidentialityLevel}
                </StatusBadge>
                <StatusBadge
                  tone={
                    selectedDocument.isArchived
                      ? "inactive"
                      : selectedDocument.isActive
                        ? "active"
                        : "warning"
                  }
                >
                  {selectedDocument.isArchived
                    ? "Archived"
                    : selectedDocument.isActive
                      ? "Active"
                      : "Inactive"}
                </StatusBadge>
                <StatusBadge tone="warning">
                  v{selectedDocument.currentVersionNumber} ·{" "}
                  {selectedDocument.versionCount} stored
                </StatusBadge>
              </div>
              <p className="mt-3 text-sm leading-6 text-slate-600">
                {selectedDocument.description || "No description provided."}
              </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              {[
                ["Reference number", selectedDocument.referenceNumber || "—"],
                ["Issuing authority", selectedDocument.issuingAuthority || "—"],
                [
                  "Publication date",
                  formatDate(selectedDocument.publicationDate),
                ],
                ["Version", selectedDocument.version || "—"],
                ["File name", selectedDocument.fileName],
                ["File size", formatBytes(selectedDocument.fileSize)],
                ["Uploaded by", selectedDocument.uploadedBy],
                ["Last updated by", selectedDocument.updatedBy],
              ].map(([label, value]) => (
                <div
                  className="rounded-lg border border-slate-200 p-3"
                  key={label}
                >
                  <span className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                    {label}
                  </span>
                  <strong className="mt-1 block break-words text-sm text-slate-700">
                    {value}
                  </strong>
                </div>
              ))}
            </div>
            <section>
              <div className="flex items-center gap-2">
                <Link2 className="text-sky-700" size={17} />
                <h3 className="text-sm font-bold text-slate-800">
                  Module links ({selectedDocument.links?.length ?? 0})
                </h3>
              </div>
              {selectedDocument.links?.length ? (
                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                  {selectedDocument.links.map((link) => (
                    <div
                      className="rounded-lg border border-slate-200 px-3 py-2.5"
                      key={link.id}
                    >
                      <StatusBadge>{link.module}</StatusBadge>
                      <strong className="mt-2 block text-sm text-slate-700">
                        {link.label}
                      </strong>
                      <span className="text-xs text-slate-500">
                        {link.recordType.replaceAll("_", " ").toLowerCase()}
                      </span>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-500">
                  This document currently belongs only to the shared repository.
                </p>
              )}
            </section>
            <section>
              <div className="flex items-center gap-2">
                <GitBranch className="text-violet-700" size={17} />
                <h3 className="text-sm font-bold text-slate-800">
                  Immutable version history
                </h3>
              </div>
              <div className="mt-2 grid gap-2">
                {selectedDocument.versions?.map((version) => (
                  <article
                    className={`rounded-xl border p-3 ${
                      version.isCurrent
                        ? "border-violet-300 bg-violet-50/60"
                        : "border-slate-200"
                    }`}
                    key={version.id}
                  >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <strong className="text-sm text-slate-800">
                            Version {version.versionNumber}
                            {version.versionLabel
                              ? ` — ${version.versionLabel}`
                              : ""}
                          </strong>
                          {version.isCurrent && (
                            <StatusBadge tone="active">Current</StatusBadge>
                          )}
                          <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500">
                            <LockKeyhole size={12} /> Immutable
                          </span>
                        </div>
                        <p className="mt-1 text-xs leading-5 text-slate-600">
                          {version.changeSummary}
                        </p>
                        <p className="mt-1 break-all text-[11px] text-slate-400">
                          {version.fileName} · {formatBytes(version.fileSize)} ·{" "}
                          {version.uploadedBy}
                        </p>
                      </div>
                      {canDownload && !selectedDocument.isArchived && (
                        <button
                          className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-sky-700 hover:bg-sky-100"
                          onClick={() =>
                            downloadVersion(selectedDocument, version)
                          }
                          title={`Download version ${version.versionNumber}`}
                          type="button"
                        >
                          <Download size={16} />
                        </button>
                      )}
                    </div>
                  </article>
                ))}
              </div>
            </section>
            {canUpdate && !selectedDocument.isArchived && (
              <button
                className="flex h-11 items-center justify-center gap-2 rounded-lg bg-violet-700 px-4 text-sm font-bold text-white hover:bg-violet-800"
                onClick={() => showVersionEditor(selectedDocument)}
                type="button"
              >
                <FileClock size={17} /> Create new immutable version
              </button>
            )}
            {canDownload && !selectedDocument.isArchived && (
              <button
                className="flex h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
                onClick={() => downloadDocument(selectedDocument)}
                type="button"
              >
                <Download size={17} /> Download document
              </button>
            )}
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold"
              disabled={saving}
              onClick={() => setEditorOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              form="document-form"
              type="submit"
            >
              {saving
                ? "Saving..."
                : editing
                  ? "Save document"
                  : "Upload document"}
            </button>
          </>
        }
        onClose={() => !saving && setEditorOpen(false)}
        open={editorOpen}
        size="lg"
        title={editing ? "Edit document" : "Add document"}
      >
        <form
          className="grid gap-4"
          id="document-form"
          onSubmit={submitDocument}
        >
          <FormField
            error={errors.documentTypeId?.[0]}
            label="Document type"
            required
          >
            <SearchableSelect
              onChange={(documentTypeId) =>
                setForm({ ...form, documentTypeId })
              }
              options={typeOptions}
              placeholder="Select a document type"
              searchPlaceholder="Search document types..."
              value={form.documentTypeId}
            />
          </FormField>
          <FormField
            error={errors.confidentialityLevelId?.[0]}
            label="Confidentiality level"
            required
          >
            <SearchableSelect
              onChange={(confidentialityLevelId) =>
                setForm({ ...form, confidentialityLevelId })
              }
              options={confidentialityOptions}
              placeholder="Select a confidentiality level"
              searchPlaceholder="Search confidentiality levels..."
              value={form.confidentialityLevelId}
            />
          </FormField>
          <FormField
            error={errors.title?.[0]}
            htmlFor="document-title"
            label="Document title"
            required
          >
            <input
              className={inputClass}
              id="document-title"
              onChange={(event) =>
                setForm({ ...form, title: event.target.value })
              }
              placeholder="e.g. Philippine Government Internal Audit Manual"
              value={form.title}
            />
          </FormField>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              error={errors.referenceNumber?.[0]}
              htmlFor="document-reference"
              label="Reference number"
            >
              <input
                className={inputClass}
                id="document-reference"
                onChange={(event) =>
                  setForm({ ...form, referenceNumber: event.target.value })
                }
                placeholder="e.g. COA Circular No. 2023-001"
                value={form.referenceNumber}
              />
            </FormField>
            <FormField
              error={errors.issuingAuthority?.[0]}
              htmlFor="document-authority"
              label="Issuing authority"
            >
              <input
                className={inputClass}
                id="document-authority"
                onChange={(event) =>
                  setForm({ ...form, issuingAuthority: event.target.value })
                }
                placeholder="e.g. Commission on Audit"
                value={form.issuingAuthority}
              />
            </FormField>
            <FormField
              error={errors.publicationDate?.[0]}
              htmlFor="document-date"
              label="Publication date"
            >
              <input
                className={inputClass}
                id="document-date"
                onChange={(event) =>
                  setForm({ ...form, publicationDate: event.target.value })
                }
                type="date"
                value={form.publicationDate}
              />
            </FormField>
            {!editing && (
              <FormField
                error={errors.version?.[0]}
                htmlFor="document-version"
                label="Initial version or edition"
              >
                <input
                  className={inputClass}
                  id="document-version"
                  onChange={(event) =>
                    setForm({ ...form, version: event.target.value })
                  }
                  placeholder="e.g. Volume I, 2024 Edition"
                  value={form.version}
                />
              </FormField>
            )}
          </div>
          <FormField
            error={errors.description?.[0]}
            htmlFor="document-description"
            label="Description"
          >
            <textarea
              className={`${inputClass} min-h-24 py-3`}
              id="document-description"
              onChange={(event) =>
                setForm({ ...form, description: event.target.value })
              }
              placeholder="Describe the document and how it may be used."
              value={form.description}
            />
          </FormField>
          <FormField
            error={errors.links?.[0]}
            label="Linked AGIS modules and records"
            hint="A document may be linked to multiple Core or IAP records, or to an entire AGIS module."
          >
            <SearchableSelect
              multiple
              multipleDisplay="summary"
              onChange={(linkKeys) => setForm({ ...form, linkKeys })}
              options={moduleLinkOptions}
              placeholder="Select module links"
              searchPlaceholder="Search modules, plans, engagements, offices..."
              value={form.linkKeys}
            />
          </FormField>
          {!editing && (
            <FormField
              error={errors.file?.[0]}
              htmlFor="document-file"
              label="Document file"
              required
              hint={`PDF, Word, Excel, PowerPoint, text, or CSV; maximum ${runtimeConfig.documentUploadMaxMb} MB.`}
            >
              <input
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
                className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-1.5 file:font-semibold file:text-sky-700"
                id="document-file"
                onChange={(event) =>
                  setForm({ ...form, file: event.target.files?.[0] ?? null })
                }
                required
                type="file"
              />
            </FormField>
          )}
          {editing && (
            <div className="flex gap-3 rounded-xl border border-violet-200 bg-violet-50 p-4">
              <LockKeyhole
                className="mt-0.5 shrink-0 text-violet-700"
                size={18}
              />
              <p className="text-sm leading-6 text-violet-900">
                File versions are immutable. Use{" "}
                <strong>Create new version</strong> from the table or document
                details to publish a replacement file.
              </p>
            </div>
          )}
          <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <input
              checked={form.isActive}
              onChange={(event) =>
                setForm({ ...form, isActive: event.target.checked })
              }
              type="checkbox"
            />
            Active document
          </label>
        </form>
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold"
              disabled={saving}
              onClick={() => setVersionTarget(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-violet-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              form="document-version-form"
              type="submit"
            >
              Continue
            </button>
          </>
        }
        onClose={() => !saving && setVersionTarget(null)}
        open={Boolean(versionTarget)}
        size="lg"
        title={`New version${versionTarget ? ` — ${versionTarget.title}` : ""}`}
      >
        <form
          className="grid gap-4"
          id="document-version-form"
          onSubmit={(event) => {
            event.preventDefault();
            setVersionConfirmOpen(true);
          }}
        >
          <div className="flex gap-3 rounded-xl border border-violet-200 bg-violet-50 p-4">
            <LockKeyhole
              className="mt-0.5 shrink-0 text-violet-700"
              size={19}
            />
            <p className="text-sm leading-6 text-violet-900">
              The existing {versionTarget?.versionCount ?? 0}{" "}
              {(versionTarget?.versionCount ?? 0) === 1
                ? "version remains"
                : "versions remain"}{" "}
              unchanged. This upload becomes the current version and cannot be
              edited or deleted.
            </p>
          </div>
          <FormField
            error={versionErrors.versionLabel?.[0]}
            htmlFor="new-version-label"
            label="Version label"
            required
          >
            <input
              className={inputClass}
              id="new-version-label"
              onChange={(event) =>
                setVersionForm({
                  ...versionForm,
                  versionLabel: event.target.value,
                })
              }
              placeholder="e.g. 2026 Revised Edition"
              required
              value={versionForm.versionLabel}
            />
          </FormField>
          <FormField
            error={versionErrors.changeSummary?.[0]}
            htmlFor="new-version-summary"
            label="Change explanation"
            required
            hint="Explain why this version is being created and what changed."
          >
            <textarea
              className={`${inputClass} min-h-28 py-3`}
              id="new-version-summary"
              onChange={(event) =>
                setVersionForm({
                  ...versionForm,
                  changeSummary: event.target.value,
                })
              }
              required
              value={versionForm.changeSummary}
            />
          </FormField>
          <FormField
            error={versionErrors.file?.[0]}
            htmlFor="new-version-file"
            label="New version file"
            required
            hint="The server rejects files already present in this document's version history."
          >
            <input
              accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
              className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-violet-50 file:px-3 file:py-1.5 file:font-semibold file:text-violet-700"
              id="new-version-file"
              onChange={(event) =>
                setVersionForm({
                  ...versionForm,
                  file: event.target.files?.[0] ?? null,
                })
              }
              required
              type="file"
            />
          </FormField>
        </form>
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Create immutable version"
        description={`Version ${versionForm.versionLabel || "without a label"} will become current. Existing versions remain unchanged and recoverable.`}
        onCancel={() => setVersionConfirmOpen(false)}
        onConfirm={createVersion}
        open={versionConfirmOpen}
        title="Publish new document version?"
        tone="warning"
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel={editing ? "Update document" : "Upload document"}
        description={
          editing
            ? `Save changes to ${editing.title}?`
            : `Add ${form.title || "this document"} to the reference library?`
        }
        onCancel={() => setSaveConfirmOpen(false)}
        onConfirm={persistDocument}
        open={saveConfirmOpen}
        title={editing ? "Confirm document update" : "Confirm document upload"}
      />
      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive document"
        description={`${archiveTarget?.title ?? "This document"} will be archived but its file will remain securely stored and recoverable.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archiveDocument}
        open={Boolean(archiveTarget)}
        title="Archive document?"
        tone="danger"
      />
      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore document"
        description={`${restoreTarget?.title ?? "This document"} will return to the active reference library.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restoreDocument}
        open={Boolean(restoreTarget)}
        title="Restore document?"
      />
    </div>
  );
}
