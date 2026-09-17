import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  Archive,
  Bell,
  BellRing,
  CheckCheck,
  Mail,
  MailOpen,
  Megaphone,
  RotateCcw,
  Search,
  Send,
  Settings2,
  ShieldAlert,
} from "lucide-react";
import { useNavigate } from "react-router";
import { useAuth } from "../../auth/auth-context";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import {
  hasPermission,
  notificationPathForUser,
} from "../../config/navigation";
import { notificationApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const primaryButton =
  "inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800 disabled:opacity-50";
const secondaryButton =
  "inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50";

const defaultPreferences = {
  inAppEnabled: true,
  workflowEnabled: true,
  assignmentsEnabled: true,
  dueDatesEnabled: true,
  systemEnabled: true,
  emailEnabled: false,
  digestFrequency: "IMMEDIATE",
  quietHoursStart: "",
  quietHoursEnd: "",
};

const emptyDelivery = {
  targetType: "USER",
  userIds: [],
  roleId: "",
  officeId: "",
  category: "SYSTEM",
  priority: "NORMAL",
  moduleCode: "CORE",
  title: "",
  message: "",
  actionUrl: "",
  actionLabel: "",
  expiresAt: "",
};

function dateTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function relativeTime(value) {
  if (!value) return "";
  const difference = new Date(value).getTime() - Date.now();
  const minutes = Math.round(difference / 60000);
  const formatter = new Intl.RelativeTimeFormat("en", { numeric: "auto" });
  if (Math.abs(minutes) < 60) return formatter.format(minutes, "minute");
  const hours = Math.round(minutes / 60);
  if (Math.abs(hours) < 24) return formatter.format(hours, "hour");
  return formatter.format(Math.round(hours / 24), "day");
}

function categoryTone(category) {
  return {
    WORKFLOW: "info",
    ASSIGNMENT: "success",
    DUE_DATE: "warning",
    OVERDUE: "danger",
    SYSTEM: "inactive",
  }[category];
}

function priorityClass(priority) {
  return {
    LOW: "border-l-slate-300",
    NORMAL: "border-l-sky-400",
    HIGH: "border-l-amber-400",
    URGENT: "border-l-red-500",
  }[priority];
}

function Field({ label, required = false, children }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-bold text-slate-700">
        {label} {required && <span className="text-red-500">*</span>}
      </span>
      {children}
    </label>
  );
}

function signalChange() {
  window.dispatchEvent(new CustomEvent("agis:notifications-changed"));
}

/**
 * Presents actionable notifications, preferences, delivery tools, reminders,
 * and safe navigation to the record referenced by each notification.
 */
export default function NotificationCenterPage() {
  const { user, runtimeConfig } = useAuth();
  const navigate = useNavigate();
  const toast = useToast();
  const [records, setRecords] = useState([]);
  const [summary, setSummary] = useState({});
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    perPage: runtimeConfig.paginationSize,
    total: 0,
  });
  const [options, setOptions] = useState({
    categories: [],
    priorities: [],
    modules: [],
    users: [],
    roles: [],
    offices: [],
  });
  const [preferences, setPreferences] = useState(defaultPreferences);
  const [filters, setFilters] = useState({
    search: "",
    category: "",
    priority: "",
    module: "",
    readStatus: "",
    includeArchived: false,
    page: 1,
    perPage: runtimeConfig.paginationSize,
  });
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [selected, setSelected] = useState(null);
  useRecordView(selected, {
    module: "CORE",
    recordType: "NOTIFICATION",
    code: (record) => record.subjectCode,
    label: (record) => record.title,
  });
  const [preferencesOpen, setPreferencesOpen] = useState(false);
  const [preferenceForm, setPreferenceForm] = useState(defaultPreferences);
  const [deliveryOpen, setDeliveryOpen] = useState(false);
  const [delivery, setDelivery] = useState(emptyDelivery);
  const [confirmArchive, setConfirmArchive] = useState(null);
  const canManage = hasPermission(user, "notifications.manage");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await notificationApi.list(filters);
      setRecords(data.notifications ?? []);
      setSummary(data.summary ?? {});
      setPagination(data.pagination ?? {});
      setOptions(data.options ?? {});
      setPreferences(data.preferences ?? defaultPreferences);
    } catch (error) {
      toast.error(error.message);
    } finally {
      setLoading(false);
    }
  }, [filters, toast]);

  useEffect(() => {
    const timeoutId = window.setTimeout(load, 0);
    return () => window.clearTimeout(timeoutId);
  }, [load]);

  function changeFilter(key, value) {
    setFilters((current) => ({ ...current, [key]: value, page: 1 }));
  }

  async function openNotification(notification) {
    let opened = notification;
    if (!notification.isRead) {
      try {
        opened = await notificationApi.markRead(notification.id);
        setRecords((current) =>
          current.map((item) => (item.id === opened.id ? opened : item)),
        );
        setSummary((current) => ({
          ...current,
          unread: Math.max(0, (current.unread ?? 0) - 1),
        }));
        signalChange();
      } catch (error) {
        toast.error(error.message);
      }
    }
    const destination = notificationPathForUser(user, notification);
    if (notification.actionUrl || destination !== "/notifications") {
      navigate(destination);
    } else {
      setSelected(opened);
    }
  }

  async function toggleRead(notification) {
    setBusy(true);
    try {
      const updated = notification.isRead
        ? await notificationApi.markUnread(notification.id)
        : await notificationApi.markRead(notification.id);
      setRecords((current) =>
        current.map((item) => (item.id === updated.id ? updated : item)),
      );
      if (selected?.id === updated.id) setSelected(updated);
      await load();
      signalChange();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function markAllRead() {
    setBusy(true);
    try {
      await notificationApi.markAllRead();
      toast.success("All notifications marked as read.");
      await load();
      signalChange();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function archiveSelected() {
    if (!confirmArchive) return;
    setBusy(true);
    try {
      await notificationApi.archive(confirmArchive.id);
      toast.success("Notification archived. It can be restored.");
      setConfirmArchive(null);
      setSelected(null);
      await load();
      signalChange();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function restore(notification) {
    setBusy(true);
    try {
      await notificationApi.restore(notification.id);
      toast.success("Notification restored.");
      setSelected(null);
      await load();
      signalChange();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function savePreferences() {
    setBusy(true);
    try {
      const updated = await notificationApi.updatePreferences({
        ...preferenceForm,
        quietHoursStart: preferenceForm.quietHoursStart || null,
        quietHoursEnd: preferenceForm.quietHoursEnd || null,
      });
      setPreferences(updated);
      setPreferencesOpen(false);
      toast.success("Notification preferences updated.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function deliver() {
    setBusy(true);
    try {
      const payload = {
        ...delivery,
        userIds: delivery.userIds.map(Number),
        roleId: delivery.roleId ? Number(delivery.roleId) : null,
        officeId: delivery.officeId ? Number(delivery.officeId) : null,
        actionUrl: delivery.actionUrl || null,
        actionLabel: delivery.actionLabel || null,
        expiresAt: delivery.expiresAt || null,
      };
      const result = await notificationApi.deliver(payload);
      toast.success(`Notification delivered to ${result.delivered} users.`);
      setDeliveryOpen(false);
      setDelivery(emptyDelivery);
      await load();
      signalChange();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  const clearDisabled = useMemo(
    () =>
      !filters.search &&
      !filters.category &&
      !filters.priority &&
      !filters.module &&
      !filters.readStatus &&
      !filters.includeArchived,
    [filters],
  );

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          <>
            <button
              className={secondaryButton}
              onClick={() => {
                setPreferenceForm({
                  ...defaultPreferences,
                  ...preferences,
                  quietHoursStart: preferences.quietHoursStart ?? "",
                  quietHoursEnd: preferences.quietHoursEnd ?? "",
                });
                setPreferencesOpen(true);
              }}
              type="button"
            >
              <Settings2 size={17} /> Preferences
            </button>
            {summary.unread > 0 && (
              <button
                className={secondaryButton}
                onClick={markAllRead}
                type="button"
              >
                <CheckCheck size={17} /> Mark all read
              </button>
            )}
            {canManage && (
              <button
                className={primaryButton}
                onClick={() => setDeliveryOpen(true)}
                type="button"
              >
                <Send size={17} /> Send notification
              </button>
            )}
          </>
        }
        description="Review workflow actions, assignments, deadlines, overdue alerts, and system advisories addressed to your account."
        icon={BellRing}
        title="Notification Center"
      />

      <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <SummaryCard
          icon={Bell}
          label="Inbox"
          tone="sky"
          value={summary.total ?? 0}
        />
        <SummaryCard
          icon={Mail}
          label="Unread"
          tone="emerald"
          value={summary.unread ?? 0}
        />
        <SummaryCard
          icon={ShieldAlert}
          label="Action required"
          tone="amber"
          value={summary.actionRequired ?? 0}
        />
        <SummaryCard
          icon={AlertTriangle}
          label="Overdue alerts"
          tone="red"
          value={summary.overdue ?? 0}
        />
        <SummaryCard
          icon={Archive}
          label="Archived"
          tone="slate"
          value={summary.archived ?? 0}
        />
      </div>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-3 border-b border-slate-200 p-4 xl:grid-cols-[minmax(20rem,1fr)_11rem_10rem_10rem_10rem_auto]">
          <label className="relative">
            <Search
              className="absolute left-3 top-3.5 text-slate-400"
              size={17}
            />
            <input
              className={`${inputClass} pl-10`}
              onChange={(event) => changeFilter("search", event.target.value)}
              placeholder="Search title, message, or record code..."
              value={filters.search}
            />
          </label>
          <select
            className={inputClass}
            onChange={(event) => changeFilter("category", event.target.value)}
            value={filters.category}
          >
            <option value="">All categories</option>
            {options.categories.map((item) => (
              <option key={item}>{item.replaceAll("_", " ")}</option>
            ))}
          </select>
          <select
            className={inputClass}
            onChange={(event) => changeFilter("module", event.target.value)}
            value={filters.module}
          >
            <option value="">All modules</option>
            {options.modules.map((item) => (
              <option key={item}>{item}</option>
            ))}
          </select>
          <select
            className={inputClass}
            onChange={(event) => changeFilter("priority", event.target.value)}
            value={filters.priority}
          >
            <option value="">All priorities</option>
            {options.priorities.map((item) => (
              <option key={item}>{item}</option>
            ))}
          </select>
          <select
            className={inputClass}
            onChange={(event) => changeFilter("readStatus", event.target.value)}
            value={filters.readStatus}
          >
            <option value="">Read and unread</option>
            <option value="UNREAD">Unread only</option>
            <option value="READ">Read only</option>
          </select>
          <button
            className={secondaryButton}
            disabled={clearDisabled}
            onClick={() =>
              setFilters({
                search: "",
                category: "",
                priority: "",
                module: "",
                readStatus: "",
                includeArchived: false,
                page: 1,
                perPage: runtimeConfig.paginationSize,
              })
            }
            type="button"
          >
            Clear
          </button>
        </div>
        <label className="flex items-center gap-2 border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600">
          <input
            checked={filters.includeArchived}
            onChange={(event) =>
              changeFilter("includeArchived", event.target.checked)
            }
            type="checkbox"
          />
          Include archived notifications
        </label>

        {loading ? (
          <div className="space-y-3 p-5">
            {[1, 2, 3, 4].map((item) => (
              <div
                className="h-20 animate-pulse rounded-lg bg-slate-100"
                key={item}
              />
            ))}
          </div>
        ) : records.length === 0 ? (
          <div className="grid min-h-72 place-items-center p-6 text-center">
            <div>
              <Bell className="mx-auto text-slate-300" size={46} />
              <p className="mt-3 font-bold text-slate-700">
                Your inbox is clear
              </p>
              <p className="mt-1 text-sm text-slate-500">
                New assignments, approvals, and reminders will appear here.
              </p>
            </div>
          </div>
        ) : (
          <div className="divide-y divide-slate-200">
            {records.map((notification) => (
              <div
                className={`group flex border-l-4 transition hover:bg-sky-50/60 ${priorityClass(notification.priority)} ${
                  notification.isRead ? "bg-white" : "bg-sky-50/40"
                }`}
                key={notification.id}
              >
                <button
                  className="grid min-w-0 flex-1 gap-3 px-4 py-4 text-left md:grid-cols-[minmax(0,2fr)_9rem_8rem_8rem] md:items-center"
                  onClick={() => openNotification(notification)}
                  type="button"
                >
                  <span className="min-w-0">
                    <span className="flex items-center gap-2">
                      {!notification.isRead && (
                        <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-sky-600" />
                      )}
                      <strong className="truncate text-sm text-slate-900">
                        {notification.title}
                      </strong>
                    </span>
                    <span className="mt-1 block truncate text-xs text-slate-500">
                      {notification.message}
                    </span>
                  </span>
                  <span>
                    <StatusBadge tone={categoryTone(notification.category)}>
                      {notification.category.replaceAll("_", " ")}
                    </StatusBadge>
                  </span>
                  <strong
                    className={`text-xs ${
                      notification.priority === "URGENT"
                        ? "text-red-600"
                        : notification.priority === "HIGH"
                          ? "text-amber-700"
                          : "text-slate-500"
                    }`}
                  >
                    {notification.priority}
                  </strong>
                  <span className="text-xs text-slate-500">
                    {relativeTime(notification.createdAt)}
                  </span>
                </button>
                <button
                  aria-label={
                    notification.isRead ? "Mark as unread" : "Mark as read"
                  }
                  className="hidden w-12 place-items-center text-slate-400 transition hover:bg-white hover:text-sky-700 sm:grid"
                  disabled={busy}
                  onClick={() => toggleRead(notification)}
                  type="button"
                >
                  {notification.isRead ? (
                    <Mail size={17} />
                  ) : (
                    <MailOpen size={17} />
                  )}
                </button>
              </div>
            ))}
          </div>
        )}

        <footer className="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center">
          <span className="text-xs text-slate-500">
            Showing {records.length} of {pagination.total ?? 0} notifications
          </span>
          <div className="flex items-center gap-2 sm:ml-auto">
            <select
              className="h-9 rounded-lg border border-slate-300 px-2 text-xs"
              onChange={(event) =>
                setFilters((current) => ({
                  ...current,
                  perPage: Number(event.target.value),
                  page: 1,
                }))
              }
              value={filters.perPage}
            >
              <option value="10">10 per page</option>
              <option value="25">25 per page</option>
              <option value="50">50 per page</option>
            </select>
            <button
              className={secondaryButton}
              disabled={pagination.currentPage <= 1}
              onClick={() =>
                setFilters((current) => ({
                  ...current,
                  page: current.page - 1,
                }))
              }
              type="button"
            >
              Previous
            </button>
            <span className="text-xs font-bold text-slate-600">
              {pagination.currentPage ?? 1} / {pagination.lastPage ?? 1}
            </span>
            <button
              className={secondaryButton}
              disabled={pagination.currentPage >= pagination.lastPage}
              onClick={() =>
                setFilters((current) => ({
                  ...current,
                  page: current.page + 1,
                }))
              }
              type="button"
            >
              Next
            </button>
          </div>
        </footer>
      </section>

      <Modal
        footer={
          selected && (
            <>
              <button
                className={secondaryButton}
                onClick={() => toggleRead(selected)}
                type="button"
              >
                {selected.isRead ? <Mail size={16} /> : <MailOpen size={16} />}
                Mark {selected.isRead ? "unread" : "read"}
              </button>
              {selected.isArchived ? (
                <button
                  className={primaryButton}
                  onClick={() => restore(selected)}
                  type="button"
                >
                  <RotateCcw size={16} /> Restore
                </button>
              ) : (
                <button
                  className={secondaryButton}
                  onClick={() => setConfirmArchive(selected)}
                  type="button"
                >
                  <Archive size={16} /> Archive
                </button>
              )}
              {selected.actionUrl && (
                <button
                  className={primaryButton}
                  onClick={() => {
                    setSelected(null);
                    navigate(selected.actionUrl);
                  }}
                  type="button"
                >
                  {selected.actionLabel ?? "Open record"}
                </button>
              )}
            </>
          )
        }
        onClose={() => setSelected(null)}
        open={Boolean(selected)}
        title={selected?.title ?? "Notification"}
        description={
          selected
            ? `${selected.moduleCode} · ${selected.category.replaceAll("_", " ")} · ${dateTime(selected.createdAt)}`
            : ""
        }
      >
        {selected && (
          <div>
            <div className="flex flex-wrap gap-2">
              <StatusBadge tone={categoryTone(selected.category)}>
                {selected.category.replaceAll("_", " ")}
              </StatusBadge>
              <StatusBadge
                tone={
                  selected.priority === "URGENT"
                    ? "danger"
                    : selected.priority === "HIGH"
                      ? "warning"
                      : "inactive"
                }
              >
                {selected.priority} PRIORITY
              </StatusBadge>
            </div>
            <p className="mt-4 whitespace-pre-wrap text-sm leading-7 text-slate-700">
              {selected.message}
            </p>
            {(selected.subjectCode || selected.actor) && (
              <div className="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                <div>
                  <small className="font-bold uppercase text-slate-400">
                    Related record
                  </small>
                  <strong className="mt-1 block text-sm text-slate-700">
                    {selected.subjectCode ?? "—"}
                  </strong>
                </div>
                <div>
                  <small className="font-bold uppercase text-slate-400">
                    Sent by
                  </small>
                  <strong className="mt-1 block text-sm text-slate-700">
                    {selected.actor?.name ?? "AGIS System"}
                  </strong>
                </div>
              </div>
            )}
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className={secondaryButton}
              onClick={() => setPreferencesOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className={primaryButton}
              disabled={busy}
              onClick={savePreferences}
              type="button"
            >
              Save preferences
            </button>
          </>
        }
        onClose={() => !busy && setPreferencesOpen(false)}
        open={preferencesOpen}
        title="Notification preferences"
        description="Choose which in-app notification categories AGIS may deliver to you."
      >
        <div className="space-y-3">
          {[
            [
              "inAppEnabled",
              "In-app notifications",
              "Master switch for Notification Center delivery.",
            ],
            [
              "workflowEnabled",
              "Workflow actions",
              "Submissions, returns, approvals, and workflow steps.",
            ],
            [
              "assignmentsEnabled",
              "Assignments",
              "Audit team, evidence, and task assignments.",
            ],
            [
              "dueDatesEnabled",
              "Deadlines and overdue alerts",
              "Upcoming and overdue workflow or audit dates.",
            ],
            [
              "systemEnabled",
              "System advisories",
              "Policy, configuration, and service announcements.",
            ],
            [
              "emailEnabled",
              "Email delivery",
              "Prepared for email delivery once mail configuration is enabled.",
            ],
          ].map(([key, label, description]) => (
            <label
              className="flex items-start gap-3 rounded-xl border border-slate-200 p-3"
              key={key}
            >
              <input
                checked={preferenceForm[key]}
                className="mt-1"
                onChange={(event) =>
                  setPreferenceForm((current) => ({
                    ...current,
                    [key]: event.target.checked,
                  }))
                }
                type="checkbox"
              />
              <span>
                <strong className="block text-sm text-slate-800">
                  {label}
                </strong>
                <small className="text-xs leading-5 text-slate-500">
                  {description}
                </small>
              </span>
            </label>
          ))}
        </div>
        <div className="mt-4 grid gap-4 sm:grid-cols-3">
          <Field label="Digest frequency">
            <select
              className={inputClass}
              onChange={(event) =>
                setPreferenceForm((current) => ({
                  ...current,
                  digestFrequency: event.target.value,
                }))
              }
              value={preferenceForm.digestFrequency}
            >
              <option value="IMMEDIATE">Immediate</option>
              <option value="DAILY">Daily digest</option>
              <option value="WEEKLY">Weekly digest</option>
            </select>
          </Field>
          <Field label="Quiet hours start">
            <input
              className={inputClass}
              onChange={(event) =>
                setPreferenceForm((current) => ({
                  ...current,
                  quietHoursStart: event.target.value,
                }))
              }
              type="time"
              value={preferenceForm.quietHoursStart}
            />
          </Field>
          <Field label="Quiet hours end">
            <input
              className={inputClass}
              onChange={(event) =>
                setPreferenceForm((current) => ({
                  ...current,
                  quietHoursEnd: event.target.value,
                }))
              }
              type="time"
              value={preferenceForm.quietHoursEnd}
            />
          </Field>
        </div>
      </Modal>

      <Modal
        footer={
          <>
            <button
              className={secondaryButton}
              onClick={() => setDeliveryOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className={primaryButton}
              disabled={
                busy ||
                !delivery.title.trim() ||
                !delivery.message.trim() ||
                (delivery.targetType === "USER" &&
                  delivery.userIds.length === 0) ||
                (delivery.targetType === "ROLE" && !delivery.roleId) ||
                (delivery.targetType === "OFFICE" && !delivery.officeId)
              }
              onClick={deliver}
              type="button"
            >
              <Megaphone size={16} /> Deliver notification
            </button>
          </>
        }
        onClose={() => !busy && setDeliveryOpen(false)}
        open={deliveryOpen}
        size="lg"
        title="Send notification"
        description="Deliver a system message to selected users, a role, an office, or every active AGIS account."
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Recipients" required>
            <select
              className={inputClass}
              onChange={(event) =>
                setDelivery((current) => ({
                  ...current,
                  targetType: event.target.value,
                }))
              }
              value={delivery.targetType}
            >
              <option value="USER">Selected users</option>
              <option value="ROLE">Access role</option>
              <option value="OFFICE">Office</option>
              <option value="ALL">All active users</option>
            </select>
          </Field>
          {delivery.targetType === "USER" && (
            <Field label="Users" required>
              <SearchableSelect
                multiple
                multipleDisplay="summary"
                onChange={(value) =>
                  setDelivery((current) => ({ ...current, userIds: value }))
                }
                options={options.users.map((item) => ({
                  value: item.id,
                  label: item.name,
                  keywords: item.employee_id,
                }))}
                value={delivery.userIds}
              />
            </Field>
          )}
          {delivery.targetType === "ROLE" && (
            <Field label="Access role" required>
              <SearchableSelect
                onChange={(value) =>
                  setDelivery((current) => ({ ...current, roleId: value }))
                }
                options={options.roles.map((item) => ({
                  value: item.id,
                  label: item.name,
                  keywords: item.code,
                }))}
                value={delivery.roleId}
              />
            </Field>
          )}
          {delivery.targetType === "OFFICE" && (
            <Field label="Office" required>
              <SearchableSelect
                onChange={(value) =>
                  setDelivery((current) => ({ ...current, officeId: value }))
                }
                options={options.offices.map((item) => ({
                  value: item.id,
                  label: `${item.code} — ${item.name}`,
                }))}
                value={delivery.officeId}
              />
            </Field>
          )}
          <Field label="Category" required>
            <select
              className={inputClass}
              onChange={(event) =>
                setDelivery((current) => ({
                  ...current,
                  category: event.target.value,
                }))
              }
              value={delivery.category}
            >
              {options.categories.map((item) => (
                <option key={item}>{item}</option>
              ))}
            </select>
          </Field>
          <Field label="Priority" required>
            <select
              className={inputClass}
              onChange={(event) =>
                setDelivery((current) => ({
                  ...current,
                  priority: event.target.value,
                }))
              }
              value={delivery.priority}
            >
              {options.priorities.map((item) => (
                <option key={item}>{item}</option>
              ))}
            </select>
          </Field>
          <Field label="Module" required>
            <select
              className={inputClass}
              onChange={(event) =>
                setDelivery((current) => ({
                  ...current,
                  moduleCode: event.target.value,
                }))
              }
              value={delivery.moduleCode}
            >
              {options.modules.map((item) => (
                <option key={item}>{item}</option>
              ))}
            </select>
          </Field>
          <Field label="Expires at">
            <input
              className={inputClass}
              onChange={(event) =>
                setDelivery((current) => ({
                  ...current,
                  expiresAt: event.target.value,
                }))
              }
              type="datetime-local"
              value={delivery.expiresAt}
            />
          </Field>
        </div>
        <div className="mt-4 grid gap-4">
          <Field label="Title" required>
            <input
              className={inputClass}
              onChange={(event) =>
                setDelivery((current) => ({
                  ...current,
                  title: event.target.value,
                }))
              }
              value={delivery.title}
            />
          </Field>
          <Field label="Message" required>
            <textarea
              className={`${inputClass} min-h-28 py-3`}
              onChange={(event) =>
                setDelivery((current) => ({
                  ...current,
                  message: event.target.value,
                }))
              }
              value={delivery.message}
            />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Action URL">
              <input
                className={inputClass}
                onChange={(event) =>
                  setDelivery((current) => ({
                    ...current,
                    actionUrl: event.target.value,
                  }))
                }
                placeholder="/document-management"
                value={delivery.actionUrl}
              />
            </Field>
            <Field label="Action label">
              <input
                className={inputClass}
                onChange={(event) =>
                  setDelivery((current) => ({
                    ...current,
                    actionLabel: event.target.value,
                  }))
                }
                placeholder="Open document"
                value={delivery.actionLabel}
              />
            </Field>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        busy={busy}
        confirmLabel="Archive notification"
        description="The notification will leave your active inbox but remain recoverable when archived records are shown."
        onCancel={() => setConfirmArchive(null)}
        onConfirm={archiveSelected}
        open={Boolean(confirmArchive)}
        title={`Archive “${confirmArchive?.title ?? "notification"}”?`}
        tone="danger"
      />
    </div>
  );
}
