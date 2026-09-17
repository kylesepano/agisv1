import { useEffect, useMemo, useState } from "react";
import { Archive, Database, Pencil, Plus, Search } from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import DataTable from "../../components/ui/DataTable";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { hasPermission } from "../../config/navigation";
import { ApiError, masterListApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

/**
 * Maintains administrator-configurable reference data used by forms and
 * filters while protecting workflow-owned state machines from ad hoc changes.
 */
export default function MasterListsPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [lists, setLists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const [selectedList, setSelectedList] = useState(null);
  useRecordView(selectedList, {
    module: "CORE",
    recordType: "MASTER_LIST",
    code: (record) => record.code,
    label: (record) => record.name,
  });
  const [search, setSearch] = useState("");
  const [form, setForm] = useState(null);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [saveConfirmOpen, setSaveConfirmOpen] = useState(false);
  const [itemArchiveIndex, setItemArchiveIndex] = useState(null);
  const canManage = hasPermission(user, "master_lists.manage");

  useEffect(() => {
    let active = true;
    masterListApi
      .list({ configurableOnly: true })
      .then((records) => active && setLists(records))
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  function edit(list) {
    setEditing(list);
    setErrors({});
    setForm({
      code: list.code,
      name: list.name,
      description: list.description ?? "",
      isActive: list.isActive,
      items: list.items.map((item) => ({ ...item })),
    });
    setEditorOpen(true);
  }

  function createList() {
    setEditing(null);
    setErrors({});
    setForm({
      code: "",
      name: "",
      description: "",
      isActive: true,
      items: [
        {
          code: "",
          label: "",
          description: "",
          isActive: true,
        },
      ],
    });
    setEditorOpen(true);
  }

  function updateItem(index, field, value) {
    setForm((current) => ({
      ...current,
      items: current.items.map((item, itemIndex) =>
        itemIndex === index ? { ...item, [field]: value } : item,
      ),
    }));
  }

  function save(event) {
    event.preventDefault();
    setSaveConfirmOpen(true);
  }

  async function persistList() {
    setSaving(true);
    try {
      if (editing) await masterListApi.update(editing.id, form);
      else await masterListApi.create(form);
      setLists(await masterListApi.list({ configurableOnly: true }));
      setSaveConfirmOpen(false);
      setEditorOpen(false);
      setEditing(null);
      toast.success(
        editing
          ? "Master list updated successfully."
          : "Master list created successfully.",
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

  const filteredLists = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return lists;
    return lists.filter((list) =>
      [list.code, list.name, list.description, ...list.items.map((item) => item.label)].some(
        (value) => value?.toLowerCase().includes(query),
      ),
    );
  }, [lists, search]);
  const columns = [
    {
      key: "name",
      label: "Master List",
      render: (list) => (
        <strong className="block min-w-56 text-slate-800">{list.name}</strong>
      ),
    },
    { key: "code", label: "List Code" },
    {
      key: "description",
      label: "Description",
      render: (list) => (
        <p className="min-w-80 text-xs leading-5 text-slate-500">
          {list.description}
        </p>
      ),
    },
    {
      key: "items",
      label: "Items",
      sortValue: (list) => list.items.length,
      render: (list) => (
        <strong className="text-slate-700">{list.items.length}</strong>
      ),
    },
    {
      key: "status",
      label: "Status",
      sortValue: (list) => (list.isActive ? 1 : 0),
      render: (list) => (
        <StatusBadge tone={list.isActive ? "active" : "inactive"}>
          {list.isActive ? "Active" : "Inactive"}
        </StatusBadge>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            label: "Actions",
            className: "text-right",
            render: (list) => (
              <button
                className="grid h-9 w-9 place-items-center rounded-lg text-blue-700 hover:bg-blue-100"
                onClick={() => edit(list)}
                type="button"
              >
                <Pencil size={17} />
              </button>
            ),
          },
        ]
      : []),
  ];

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          canManage && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800"
              onClick={createList}
              type="button"
            >
              <Plus size={18} /> Add master list
            </button>
          )
        }
        description="Maintain centrally controlled reference values reused by AGIS modules and workflows."
        icon={Database}
        readOnly={!canManage}
        title="Master Lists"
      />

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
          <strong>{filteredLists.length} master lists</strong>
          <label className="flex h-11 w-full max-w-sm items-center gap-2 rounded-lg border px-3 text-slate-500">
            <Search size={17} />
            <input
              className="min-w-0 flex-1 outline-none"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search lists and values..."
              value={search}
            />
          </label>
        </header>
        <DataTable
          columns={columns}
          loading={loading}
          onRowClick={setSelectedList}
          rows={filteredLists}
        />
      </section>

      <Modal
        onClose={() => setSelectedList(null)}
        open={Boolean(selectedList)}
        size="xl"
        title={selectedList?.name ?? "Master list details"}
      >
        {selectedList && (
          <div className="grid gap-4">
            <div className="rounded-lg bg-slate-50 p-4">
              <StatusBadge>{selectedList.code}</StatusBadge>
              <p className="mt-3 text-sm leading-6 text-slate-600">
                {selectedList.description}
              </p>
            </div>
            <DataTable
              columns={[
                {
                  key: "code",
                  label: "Code",
                  render: (item) => (
                    <span className="block max-w-48 break-all text-xs font-semibold">
                      {item.code}
                    </span>
                  ),
                },
                {
                  key: "label",
                  label: "Value",
                  render: (item) => (
                    <span className="block max-w-56 whitespace-normal break-words">
                      {item.label}
                    </span>
                  ),
                },
                {
                  key: "description",
                  label: "Description",
                  render: (item) => (
                    <span className="block max-w-md whitespace-normal break-words text-xs leading-5">
                      {item.description || "—"}
                    </span>
                  ),
                },
                {
                  key: "status",
                  label: "Status",
                  sortValue: (item) => (item.isActive ? 1 : 0),
                  render: (item) => (
                    <StatusBadge
                      tone={item.isActive ? "active" : "inactive"}
                    >
                      {item.isActive ? "Active" : "Inactive"}
                    </StatusBadge>
                  ),
                },
              ]}
              rows={selectedList.items}
            />
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              onClick={() => setEditorOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white"
              form="master-list-form"
              type="submit"
            >
              {saving ? "Saving..." : "Save master list"}
            </button>
          </>
        }
        onClose={() => !saving && setEditorOpen(false)}
        open={editorOpen}
        size="lg"
        title={editing ? `Edit ${editing.name}` : "Add master list"}
      >
        {form && (
          <form className="grid gap-4" id="master-list-form" onSubmit={save}>
            <FormField
              error={errors.code?.[0]}
              htmlFor="list-code"
              label="List code"
              required
            >
              <input
                className="h-11 w-full rounded-lg border px-3 uppercase disabled:bg-slate-100"
                disabled={Boolean(editing)}
                id="list-code"
                onChange={(event) =>
                  setForm({ ...form, code: event.target.value })
                }
                placeholder="e.g. DOCUMENT_TYPE"
                required
                value={form.code}
              />
            </FormField>
            <FormField htmlFor="list-name" label="List name">
              <input
                className="h-11 w-full rounded-lg border px-3"
                id="list-name"
                onChange={(event) =>
                  setForm({ ...form, name: event.target.value })
                }
                value={form.name}
                required
              />
            </FormField>
            <FormField htmlFor="list-description" label="Description">
              <textarea
                className="min-h-20 w-full rounded-lg border p-3"
                id="list-description"
                onChange={(event) =>
                  setForm({ ...form, description: event.target.value })
                }
                value={form.description}
              />
            </FormField>
            <div className="max-h-80 space-y-3 overflow-y-auto">
              {form.items.map((item, index) => (
                <div
                  className="grid gap-2 rounded-lg border bg-slate-50 p-3 sm:grid-cols-[.8fr_1.2fr_auto]"
                  key={item.id ?? `new-${index}`}
                >
                  <input
                    className="h-10 rounded-md border px-2 text-xs font-semibold"
                    onChange={(event) =>
                      updateItem(index, "code", event.target.value)
                    }
                    placeholder="CODE"
                    required
                    value={item.code}
                  />
                  <input
                    className="h-10 rounded-md border px-2 text-sm"
                    onChange={(event) =>
                      updateItem(index, "label", event.target.value)
                    }
                    placeholder="Display label"
                    required
                    value={item.label}
                  />
                  <button
                    className="grid h-10 w-10 place-items-center rounded-md text-red-600 hover:bg-red-100"
                    onClick={() => setItemArchiveIndex(index)}
                    title="Archive list item"
                    type="button"
                  >
                    <Archive size={17} />
                  </button>
                  <textarea
                    className="min-h-16 rounded-md border p-2 text-xs sm:col-span-3"
                    onChange={(event) =>
                      updateItem(index, "description", event.target.value)
                    }
                    placeholder="Description"
                    value={item.description ?? ""}
                  />
                </div>
              ))}
            </div>
            <button
              className="flex h-10 items-center justify-center gap-2 rounded-lg border border-dashed border-sky-400 text-sm font-bold text-sky-700 hover:bg-sky-50"
              onClick={() =>
                setForm((current) => ({
                  ...current,
                  items: [
                    ...current.items,
                    {
                      code: "",
                      label: "",
                      description: "",
                      isActive: true,
                    },
                  ],
                }))
              }
              type="button"
            >
              <Plus size={17} /> Add list item
            </button>
          </form>
        )}
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel={editing ? "Update master list" : "Add master list"}
        description={
          editing
            ? `Apply these changes to ${editing.name}? Removed items will be archived, not permanently deleted.`
            : `Add ${form?.name || "this master list"} and its values?`
        }
        onCancel={() => setSaveConfirmOpen(false)}
        onConfirm={persistList}
        open={saveConfirmOpen}
        title={editing ? "Confirm master-list update" : "Confirm new master list"}
      />

      <ConfirmDialog
        confirmLabel="Archive item"
        description={
          itemArchiveIndex === null
            ? ""
            : `${form?.items[itemArchiveIndex]?.label || "This item"} will be removed when you save the master list.`
        }
        onCancel={() => setItemArchiveIndex(null)}
        onConfirm={() => {
          setForm((current) => ({
            ...current,
            items: current.items.filter(
              (_, index) => index !== itemArchiveIndex,
            ),
          }));
          setItemArchiveIndex(null);
        }}
        open={itemArchiveIndex !== null}
        title="Archive this list item?"
        tone="danger"
      />
    </div>
  );
}
