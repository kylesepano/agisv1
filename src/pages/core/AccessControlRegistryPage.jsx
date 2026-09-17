import { useEffect, useMemo, useState } from "react";
import {
  Archive,
  CircleCheckBig,
  CirclePause,
  Copy,
  KeyRound,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  ShieldCheck,
  X,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import DataTable from "../../components/ui/DataTable";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { ApiError, permissionApi, roleApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const emptyRoleForm = {
  code: "",
  name: "",
  description: "",
  isActive: true,
  officeAccessScope: "OWN_OFFICE",
  engagementAccessScope: "ASSIGNED",
  permissionIds: [],
};

/**
 * Manages roles or permissions depending on the requested registry mode.
 * Role mutations remain permission-gated and archiving is blocked while users
 * are still assigned to the role.
 */
export default function AccessControlRegistryPage({ mode }) {
  const { user } = useAuth();
  const toast = useToast();
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [permissionFilter, setPermissionFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [editing, setEditing] = useState(null);
  const [cloneSource, setCloneSource] = useState(null);
  const [form, setForm] = useState(emptyRoleForm);
  const [editorOpen, setEditorOpen] = useState(false);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [selectedRole, setSelectedRole] = useState(null);
  useRecordView(selectedRole, {
    module: "CORE",
    recordType: "ACCESS_ROLE",
    code: (record) => record.code,
    label: (record) => record.name,
  });
  const isRoles = mode === "roles";
  const canCreate = isRoles && hasPermission(user, "roles.create");
  const canClone = isRoles && hasPermission(user, "roles.clone");
  const canUpdate = isRoles && hasPermission(user, "roles.update");
  const canDelete = isRoles && hasPermission(user, "roles.delete");
  const canRestore = isRoles && hasPermission(user, "roles.restore");
  const isPlatformAdministrator = hasPermission(
    user,
    "access.manage_system_roles",
  );
  const isProtectedRole = (role) =>
    (role.permissions ?? []).includes("access.manage_system_roles");
  const canManage =
    canCreate || canClone || canUpdate || canDelete || canRestore;

  useEffect(() => {
    let active = true;
    Promise.all([
      isRoles ? roleApi.list({ includeArchived: true }) : Promise.resolve([]),
      permissionApi.list(),
    ])
      .then(([roleRecords, permissionRecords]) => {
        if (!active) return;
        setRoles(roleRecords);
        setPermissions(permissionRecords);
      })
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [isRoles, toast]);

  const rows = useMemo(() => {
    const source = isRoles ? roles : permissions;
    const query = search.trim().toLowerCase();
    return source.filter((item) => {
      if (!isRoles) {
        return (
          !query ||
          [item.code, item.name, item.description, item.module].some((value) =>
            value?.toLowerCase().includes(query),
          )
        );
      }

      const matchesSearch =
        !query ||
        [
          item.code,
          item.name,
          item.description,
          item.officeAccessScope,
          item.engagementAccessScope,
          ...item.permissions,
          ...item.users.flatMap((assignedUser) => [
            assignedUser.name,
            assignedUser.employeeId,
            assignedUser.email,
          ]),
        ].some((value) => value?.toLowerCase().includes(query));
      const matchesPermission =
        !permissionFilter ||
        item.permissionIds.some(
          (permissionId) => String(permissionId) === String(permissionFilter),
        );
      const matchesStatus =
        !statusFilter ||
        (statusFilter === "archived"
          ? item.isArchived
          : statusFilter === "active"
            ? item.isActive && !item.isArchived
            : !item.isActive && !item.isArchived);

      return matchesSearch && matchesPermission && matchesStatus;
    });
  }, [isRoles, permissionFilter, permissions, roles, search, statusFilter]);

  const roleStats = useMemo(() => {
    const active = roles.filter(
      (role) => role.isActive && !role.isArchived,
    ).length;
    const archived = roles.filter((role) => role.isArchived).length;
    const inactive = roles.length - active - archived;

    return { total: roles.length, active, inactive, archived };
  }, [roles]);

  const permissionFilterOptions = useMemo(
    () =>
      permissions.map((permission) => ({
        value: permission.id,
        label: permission.name,
        description: permission.module.replaceAll("_", " "),
        keywords: `${permission.code} ${permission.action} ${permission.description ?? ""}`,
      })),
    [permissions],
  );

  const hasActiveFilters = Boolean(search || permissionFilter || statusFilter);

  function showRoleEditor(role = null) {
    setEditing(role);
    setCloneSource(null);
    setErrors({});
    setForm(
      role
        ? {
            code: role.code,
            name: role.name,
            description: role.description ?? "",
            isActive: role.isActive,
            officeAccessScope: role.officeAccessScope,
            engagementAccessScope: role.engagementAccessScope,
            permissionIds: role.permissionIds,
          }
        : emptyRoleForm,
    );
    setEditorOpen(true);
  }

  function showCloneEditor(role) {
    setEditing(null);
    setCloneSource(role);
    setErrors({});
    setForm({
      code: `${role.code}_copy`,
      name: `${role.name} Copy`,
      description: role.description ?? "",
      isActive: true,
      officeAccessScope: role.officeAccessScope,
      engagementAccessScope: role.engagementAccessScope,
      permissionIds: [...role.permissionIds],
    });
    setEditorOpen(true);
  }

  function saveRole(event) {
    event.preventDefault();
    setConfirmOpen(true);
  }

  async function persistRole() {
    setSaving(true);
    setErrors({});
    try {
      if (editing) await roleApi.update(editing.id, form);
      else if (cloneSource) await roleApi.clone(cloneSource.id, form);
      else await roleApi.create(form);
      setRoles(await roleApi.list({ includeArchived: true }));
      setConfirmOpen(false);
      setEditorOpen(false);
      setEditing(null);
      setCloneSource(null);
      toast.success(
        editing
          ? "Access role updated successfully."
          : cloneSource
            ? "Access role cloned successfully."
            : "Access role created successfully.",
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422)
        setErrors(error.errors);
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function archiveRole() {
    if (!archiveTarget) return;
    setSaving(true);
    try {
      await roleApi.remove(archiveTarget.id);
      setRoles((current) =>
        current.map((role) =>
          role.id === archiveTarget.id
            ? { ...role, isActive: false, isArchived: true }
            : role,
        ),
      );
      setArchiveTarget(null);
      toast.success("Access role archived successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function restoreRole() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      const restored = await roleApi.restore(restoreTarget.id);
      setRoles((current) =>
        current.map((role) => (role.id === restored.id ? restored : role)),
      );
      setRestoreTarget(null);
      toast.success("Access role restored successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  const columns = isRoles
    ? [
        {
          key: "name",
          label: "Access Role",
          render: (role) => (
            <div className="min-w-64">
              <strong className="text-slate-800">{role.name}</strong>
              <p className="mt-1 text-xs text-slate-500">{role.description}</p>
            </div>
          ),
        },
        { key: "code", label: "Role Code" },
        { key: "usersCount", label: "Users" },
        {
          key: "permissions",
          label: "Permissions",
          render: (role) => `${role.permissions.length} assigned`,
        },
        {
          key: "accessScopes",
          label: "Access Scopes",
          render: (role) => (
            <div className="grid min-w-40 gap-1 text-xs text-slate-600">
              <span>
                Offices:{" "}
                <strong className="text-slate-700">
                  {role.officeAccessScope === "ALL"
                    ? "All offices"
                    : "Own office"}
                </strong>
              </span>
              <span>
                Engagements:{" "}
                <strong className="text-slate-700">
                  {role.engagementAccessScope === "ALL"
                    ? "All engagements"
                    : "Assigned only"}
                </strong>
              </span>
            </div>
          ),
        },
        {
          key: "status",
          label: "Status",
          render: (role) => (
            <StatusBadge
              tone={
                role.isArchived
                  ? "inactive"
                  : role.isActive
                    ? "active"
                    : "warning"
              }
            >
              {role.isArchived
                ? "Archived"
                : role.isActive
                  ? "Active"
                  : "Inactive"}
            </StatusBadge>
          ),
        },
        ...(canUpdate || canDelete || canRestore
          ? [
              {
                key: "actions",
                label: "Actions",
                className: "text-right",
                headerClassName: "text-right",
                render: (role) => (
                  <div className="flex justify-end gap-1">
                    {canClone &&
                      !role.isArchived &&
                      (!isProtectedRole(role) || isPlatformAdministrator) && (
                        <button
                          className="grid h-9 w-9 place-items-center rounded-lg text-violet-700 transition hover:bg-violet-100"
                          onClick={(event) => {
                            event.stopPropagation();
                            showCloneEditor(role);
                          }}
                          title="Clone access role"
                          type="button"
                        >
                          <Copy size={17} />
                        </button>
                      )}
                    {canUpdate &&
                      !role.isArchived &&
                      (!isProtectedRole(role) || isPlatformAdministrator) && (
                        <button
                          className="grid h-9 w-9 place-items-center rounded-lg text-blue-700 transition hover:bg-blue-100"
                          onClick={(event) => {
                            event.stopPropagation();
                            showRoleEditor(role);
                          }}
                          title="Edit access role"
                          type="button"
                        >
                          <Pencil size={17} />
                        </button>
                      )}
                    {canDelete &&
                      !role.isArchived &&
                      !isProtectedRole(role) && (
                        <button
                          className="grid h-9 w-9 place-items-center rounded-lg text-red-600 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-35"
                          disabled={role.usersCount > 0}
                          onClick={(event) => {
                            event.stopPropagation();
                            setArchiveTarget(role);
                          }}
                          title={
                            role.usersCount > 0
                              ? `Cannot archive: ${role.usersCount} users are still assigned`
                              : "Archive access role"
                          }
                          type="button"
                        >
                          <Archive size={17} />
                        </button>
                      )}
                    {canRestore && role.isArchived && (
                      <button
                        className="grid h-9 w-9 place-items-center rounded-lg text-emerald-700 transition hover:bg-emerald-100"
                        onClick={(event) => {
                          event.stopPropagation();
                          setRestoreTarget(role);
                        }}
                        title="Restore access role"
                        type="button"
                      >
                        <RotateCcw size={17} />
                      </button>
                    )}
                  </div>
                ),
              },
            ]
          : []),
      ]
    : [
        {
          key: "name",
          label: "Permission",
          render: (permission) => (
            <div className="min-w-60">
              <strong className="text-slate-800">{permission.name}</strong>
              <p className="mt-1 text-xs text-slate-500">
                {permission.description}
              </p>
            </div>
          ),
        },
        { key: "code", label: "Permission Code" },
        {
          key: "module",
          label: "Module",
          render: (permission) => (
            <StatusBadge>{permission.module.replaceAll("_", " ")}</StatusBadge>
          ),
        },
        { key: "action", label: "Action" },
      ];

  const groupedPermissions = permissions.reduce((groups, permission) => {
    const modulePermissions = groups[permission.module] ?? [];
    return {
      ...groups,
      [permission.module]: [...modulePermissions, permission],
    };
  }, {});

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          canCreate && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800"
              onClick={() => showRoleEditor()}
              type="button"
            >
              <Plus size={18} /> Add access role
            </button>
          )
        }
        description={
          isRoles
            ? "Manage standard and custom AGIS roles and assign their permitted capabilities."
            : "Review the stable module.action permission catalogue used by server authorization."
        }
        icon={isRoles ? KeyRound : ShieldCheck}
        readOnly={!canManage}
        title={isRoles ? "Access Role Registry" : "Permission Registry"}
      />

      {isRoles && (
        <section className="mb-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <SummaryCard
            icon={KeyRound}
            label="Total access roles"
            tone="sky"
            value={roleStats.total}
          />
          <SummaryCard
            icon={CircleCheckBig}
            label="Active access roles"
            tone="emerald"
            value={roleStats.active}
          />
          <SummaryCard
            icon={CirclePause}
            label="Inactive access roles"
            tone="amber"
            value={roleStats.inactive}
          />
          <SummaryCard
            icon={Archive}
            label="Archived access roles"
            tone="red"
            value={roleStats.archived}
          />
        </section>
      )}

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="border-b border-slate-200 bg-white p-4">
          {isRoles ? (
            <div className="grid w-full gap-2 md:grid-cols-[minmax(16rem,1fr)_17rem_11rem_auto]">
              <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 transition focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                <Search className="shrink-0" size={17} />
                <input
                  className="min-w-0 flex-1 bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Search role, code, permission, user..."
                  type="search"
                  value={search}
                />
              </label>

              <SearchableSelect
                onChange={setPermissionFilter}
                options={[
                  { value: "", label: "All permissions" },
                  ...permissionFilterOptions,
                ]}
                placeholder="Filter by permission"
                searchPlaceholder="Search permissions..."
                value={permissionFilter}
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
                  setPermissionFilter("");
                  setStatusFilter("");
                }}
                type="button"
              >
                <X size={16} />
                Clear filters
              </button>
            </div>
          ) : (
            <label className="flex h-11 w-full max-w-md items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 transition focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
              <Search className="shrink-0" size={17} />
              <input
                className="min-w-0 flex-1 bg-transparent text-sm text-slate-800 outline-none"
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search permissions..."
                type="search"
                value={search}
              />
            </label>
          )}
        </header>

        <div className="[&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-sky-50/60 [&_thead]:bg-slate-50/90">
          <DataTable
            columns={columns}
            emptyMessage={
              isRoles
                ? "No access roles match your filters."
                : "No permissions match your search."
            }
            key={
              isRoles ? `${search}|${permissionFilter}|${statusFilter}` : search
            }
            loading={loading}
            onRowClick={isRoles ? setSelectedRole : undefined}
            pageSizeOptions={[8, 10, 25, 50]}
            rows={rows}
          />
        </div>
      </section>

      <Modal
        onClose={() => setSelectedRole(null)}
        open={Boolean(selectedRole)}
        size="xl"
        title={selectedRole?.name ?? "Access role details"}
      >
        {selectedRole && (
          <div className="grid gap-5">
            <div className="rounded-xl bg-slate-50 p-4">
              <div className="flex flex-wrap items-center gap-2">
                <StatusBadge>{selectedRole.code}</StatusBadge>
                <StatusBadge
                  tone={
                    selectedRole.isArchived
                      ? "inactive"
                      : selectedRole.isActive
                        ? "active"
                        : "warning"
                  }
                >
                  {selectedRole.isArchived
                    ? "Archived"
                    : selectedRole.isActive
                      ? "Active"
                      : "Inactive"}
                </StatusBadge>
                {selectedRole.isSystem && (
                  <StatusBadge tone="warning">System role</StatusBadge>
                )}
              </div>
              <p className="mt-3 text-sm leading-6 text-slate-600">
                {selectedRole.description || "No description provided."}
              </p>
              <p className="mt-2 text-xs font-semibold text-slate-500">
                {selectedRole.usersCount} assigned{" "}
                {selectedRole.usersCount === 1 ? "user" : "users"} ·{" "}
                {selectedRole.permissionIds.length} permissions
              </p>
            </div>
            <div className="mt-3 grid gap-2 sm:grid-cols-2">
              <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <span className="block text-[11px] font-bold uppercase tracking-wide text-slate-400">
                  Office scope
                </span>
                <strong className="text-sm text-slate-700">
                  {selectedRole.officeAccessScope === "ALL"
                    ? "All offices"
                    : "User's assigned office"}
                </strong>
              </div>
              <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <span className="block text-[11px] font-bold uppercase tracking-wide text-slate-400">
                  Engagement scope
                </span>
                <strong className="text-sm text-slate-700">
                  {selectedRole.engagementAccessScope === "ALL"
                    ? "All engagements"
                    : "Assigned engagements only"}
                </strong>
              </div>
            </div>
            {selectedRole.users.length > 0 && (
              <section>
                <h3 className="text-sm font-bold text-slate-800">
                  Assigned users ({selectedRole.users.length})
                </h3>
                <div className="mt-2 grid max-h-56 gap-2 overflow-y-auto sm:grid-cols-2">
                  {selectedRole.users.map((assignedUser) => (
                    <div
                      className="rounded-lg border border-slate-200 px-3 py-2.5"
                      key={assignedUser.id}
                    >
                      <strong className="block text-sm text-slate-700">
                        {assignedUser.name}
                      </strong>
                      <span className="text-xs text-slate-500">
                        {assignedUser.employeeId}
                        {assignedUser.isArchived ? " · Archived account" : ""}
                      </span>
                    </div>
                  ))}
                </div>
              </section>
            )}
            <div className="grid gap-3 md:grid-cols-2">
              {Object.entries(
                permissions
                  .filter((permission) =>
                    selectedRole.permissionIds.includes(permission.id),
                  )
                  .reduce(
                    (groups, permission) => ({
                      ...groups,
                      [permission.module]: [
                        ...(groups[permission.module] ?? []),
                        permission,
                      ],
                    }),
                    {},
                  ),
              ).map(([module, modulePermissions]) => (
                <section
                  className="rounded-xl border border-slate-200 p-4"
                  key={module}
                >
                  <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500">
                    {module.replaceAll("_", " ")}
                  </h3>
                  <div className="mt-2 flex flex-wrap gap-2">
                    {modulePermissions.map((permission) => (
                      <span
                        className="rounded-md bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-800"
                        key={permission.id}
                        title={permission.description}
                      >
                        {permission.action.replaceAll("_", " ")}
                      </span>
                    ))}
                  </div>
                </section>
              ))}
            </div>
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              disabled={saving}
              onClick={() => setEditorOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white"
              disabled={saving}
              form="role-form"
              type="submit"
            >
              {saving
                ? "Saving..."
                : editing
                  ? "Save role"
                  : cloneSource
                    ? "Clone role"
                    : "Create role"}
            </button>
          </>
        }
        onClose={() => !saving && setEditorOpen(false)}
        open={editorOpen}
        size="lg"
        title={
          editing
            ? `Edit ${editing.name}`
            : cloneSource
              ? `Clone ${cloneSource.name}`
              : "Add access role"
        }
      >
        <form className="grid gap-4" id="role-form" onSubmit={saveRole}>
          <FormField
            error={errors.code?.[0]}
            htmlFor="role-code"
            label="Role code"
            required
          >
            <input
              className="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100 disabled:text-slate-500"
              disabled={Boolean(editing?.isSystem)}
              id="role-code"
              onChange={(event) =>
                setForm({ ...form, code: event.target.value })
              }
              placeholder="e.g. regional_reviewer"
              value={form.code}
            />
          </FormField>
          <FormField
            error={errors.name?.[0]}
            htmlFor="role-name"
            label="Role name"
            required
          >
            <input
              className="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
              id="role-name"
              onChange={(event) =>
                setForm({ ...form, name: event.target.value })
              }
              value={form.name}
            />
          </FormField>
          <FormField
            error={errors.description?.[0]}
            htmlFor="role-description"
            label="Description"
          >
            <textarea
              className="min-h-24 w-full rounded-lg border border-slate-300 p-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
              id="role-description"
              onChange={(event) =>
                setForm({ ...form, description: event.target.value })
              }
              value={form.description}
            />
          </FormField>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              error={errors.officeAccessScope?.[0]}
              htmlFor="role-office-scope"
              label="Office access scope"
              required
            >
              <select
                className="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                id="role-office-scope"
                onChange={(event) =>
                  setForm({
                    ...form,
                    officeAccessScope: event.target.value,
                  })
                }
                value={form.officeAccessScope}
              >
                <option value="ALL">All offices</option>
                <option value="OWN_OFFICE">User&apos;s assigned office</option>
              </select>
            </FormField>
            <FormField
              error={errors.engagementAccessScope?.[0]}
              htmlFor="role-engagement-scope"
              label="Engagement access scope"
              required
            >
              <select
                className="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                id="role-engagement-scope"
                onChange={(event) =>
                  setForm({
                    ...form,
                    engagementAccessScope: event.target.value,
                  })
                }
                value={form.engagementAccessScope}
              >
                <option value="ALL">All engagements</option>
                <option value="ASSIGNED">Assigned engagements only</option>
              </select>
            </FormField>
          </div>
          <p className="-mt-2 text-xs leading-5 text-slate-500">
            Module access comes from the permissions below. These scopes limit
            which office and engagement records the role may access.
          </p>
          {errors.permissionIds?.[0] && (
            <p className="-mb-2 text-xs font-semibold text-red-600">
              {errors.permissionIds[0]}
            </p>
          )}
          <div className="max-h-80 overflow-y-auto rounded-lg border p-3">
            {Object.entries(groupedPermissions).map(
              ([module, modulePermissions]) => (
                <fieldset className="mb-4" key={module}>
                  <legend className="mb-2 text-xs font-bold uppercase text-slate-500">
                    {module.replaceAll("_", " ")}
                  </legend>
                  <div className="grid gap-2 sm:grid-cols-2">
                    {modulePermissions.map((permission) => (
                      <label
                        className="flex gap-2 rounded-md bg-slate-50 px-2 py-2 text-xs"
                        key={permission.id}
                      >
                        <input
                          checked={form.permissionIds.includes(permission.id)}
                          onChange={() =>
                            setForm((current) => ({
                              ...current,
                              permissionIds: current.permissionIds.includes(
                                permission.id,
                              )
                                ? current.permissionIds.filter(
                                    (id) => id !== permission.id,
                                  )
                                : [...current.permissionIds, permission.id],
                            }))
                          }
                          type="checkbox"
                        />
                        {permission.name}
                      </label>
                    ))}
                  </div>
                </fieldset>
              ),
            )}
          </div>
          <label className="flex items-center gap-2 text-sm font-semibold">
            <input
              checked={form.isActive}
              onChange={(event) =>
                setForm({ ...form, isActive: event.target.checked })
              }
              type="checkbox"
            />
            Active role
          </label>
        </form>
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel={
          editing
            ? "Update access role"
            : cloneSource
              ? "Clone access role"
              : "Create access role"
        }
        description={
          editing
            ? `Permission changes affect every user assigned to ${editing.name}.`
            : cloneSource
              ? `Create ${form.name || "this access role"} from ${cloneSource.name} with its selected permissions and access scopes?`
              : `Create ${form.name || "this access role"} with ${form.permissionIds.length} assigned permissions?`
        }
        onCancel={() => setConfirmOpen(false)}
        onConfirm={persistRole}
        open={confirmOpen}
        title={
          editing
            ? "Confirm access-role update"
            : cloneSource
              ? "Confirm role clone"
              : "Confirm access role"
        }
        tone={editing ? "warning" : undefined}
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive access role"
        description={`${archiveTarget?.name ?? "This access role"} will be archived and remain recoverable. Only roles without assigned users can be archived.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archiveRole}
        open={Boolean(archiveTarget)}
        title="Archive access role?"
        tone="danger"
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore access role"
        description={`${restoreTarget?.name ?? "This access role"} will return to the active registry.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restoreRole}
        open={Boolean(restoreTarget)}
        title="Restore access role?"
      />
    </div>
  );
}
