import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  Archive,
  Bell,
  CheckCheck,
  Clock3,
  ExternalLink,
  Filter,
  Loader2,
  MailOpen,
  RotateCcw,
  Search,
} from "lucide-react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { Button } from "../../../components/ui/button";
import { useToast } from "../../../components/ui/toast";
import {
  notificationService,
  type BackendNotification,
  type NotificationCategory,
  type NotificationPriority,
} from "../../../services/notificationService";
import { navigateTo } from "../../../routes";
import { cn } from "../../../utils/helpers";

type TabType = "ALL" | "UNREAD" | "ACTION_REQUIRED" | "OVERDUE" | "ARCHIVED";

export function AdminNotificationsPage() {
  const { showToast } = useToast();
  const [activeTab, setActiveTab] = useState<TabType>("ALL");
  const [search, setSearch] = useState("");
  const [priorityFilter, setPriorityFilter] = useState<string>("ALL");
  const [isLoading, setIsLoading] = useState(false);
  const [notifications, setNotifications] = useState<BackendNotification[]>([]);
  const [summary, setSummary] = useState({
    total: 0,
    unread: 0,
    actionRequired: 0,
    overdue: 0,
    archived: 0,
  });

  const loadNotifications = useCallback(async () => {
    setIsLoading(true);
    try {
      const isArchived = activeTab === "ARCHIVED";
      const readStatus = activeTab === "UNREAD" ? "UNREAD" : undefined;
      const category: NotificationCategory | undefined =
        activeTab === "OVERDUE"
          ? "OVERDUE"
          : activeTab === "ACTION_REQUIRED"
          ? "WORKFLOW"
          : undefined;

      const priority: NotificationPriority | undefined =
        priorityFilter !== "ALL" ? (priorityFilter as NotificationPriority) : undefined;

      const response = await notificationService.list({
        search: search.trim() || undefined,
        includeArchived: isArchived,
        readStatus,
        category,
        priority,
        perPage: 50,
      });

      setNotifications(response.notifications ?? []);
      if (response.summary) {
        setSummary(response.summary);
      }
    } catch {
      showToast({
        title: "Connection error",
        description: "Could not load notifications from backend.",
        tone: "warning",
      });
    } finally {
      setIsLoading(false);
    }
  }, [activeTab, priorityFilter, search, showToast]);

  useEffect(() => {
    loadNotifications();
  }, [loadNotifications]);

  const handleMarkAllRead = async () => {
    try {
      await notificationService.markAllRead();
      showToast({
        title: "Success",
        description: "All notifications marked as read.",
        tone: "success",
      });
      window.dispatchEvent(new CustomEvent("agis:notifications-changed"));
      loadNotifications();
    } catch {
      showToast({
        title: "Error",
        description: "Failed to mark all notifications as read.",
        tone: "warning",
      });
    }
  };

  const handleToggleRead = async (notification: BackendNotification) => {
    try {
      if (notification.isRead) {
        await notificationService.markUnread(notification.id);
      } else {
        await notificationService.markRead(notification.id);
      }
      window.dispatchEvent(new CustomEvent("agis:notifications-changed"));
      loadNotifications();
    } catch {
      showToast({
        title: "Update failed",
        description: "Could not change read state.",
        tone: "warning",
      });
    }
  };

  const handleToggleArchive = async (notification: BackendNotification) => {
    try {
      if (notification.isArchived) {
        await notificationService.restore(notification.id);
        showToast({
          title: "Restored",
          description: "Notification moved back to active inbox.",
          tone: "success",
        });
      } else {
        await notificationService.archive(notification.id);
        showToast({
          title: "Archived",
          description: "Notification archived.",
          tone: "info",
        });
      }
      window.dispatchEvent(new CustomEvent("agis:notifications-changed"));
      loadNotifications();
    } catch {
      showToast({
        title: "Action failed",
        description: "Could not update notification archive state.",
        tone: "warning",
      });
    }
  };

  const handleNavigate = (url?: string | null) => {
    if (!url) return;
    if (url.startsWith("/admin/")) {
      navigateTo(url);
    } else if (url.startsWith("/audit-engagement") || url.startsWith("/audit-engagements")) {
      navigateTo(`/admin${url}`);
    } else if (url.startsWith("/internal-audit-planning")) {
      navigateTo("/admin/annual-audit-plan");
    } else if (url.startsWith("/workflow-management")) {
      navigateTo("/admin/annual-audit-plan");
    } else {
      navigateTo(url);
    }
  };

  return (
    <AdminLayout>
      <div className="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div className="flex items-center gap-2">
              <div className="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-blue-600">
                <Bell size={20} />
              </div>
              <h1 className="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                Notifications Center
              </h1>
            </div>
            <p className="mt-1 text-sm text-slate-500">
              Manage system notices, workflow actions, review alerts, and audit reminders.
            </p>
          </div>

          <div className="flex items-center gap-2.5">
            <Button
              className="inline-flex items-center gap-1.5 border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50"
              onClick={handleMarkAllRead}
              type="button"
            >
              <CheckCheck size={14} className="text-blue-600" />
              Mark all read
            </Button>
          </div>
        </div>

        {/* Stats Row */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
          <div
            className={cn(
              "cursor-pointer rounded-xl border bg-white p-3.5 shadow-2xs transition hover:border-blue-300",
              activeTab === "ALL" ? "border-blue-500 ring-2 ring-blue-100" : "border-slate-200"
            )}
            onClick={() => setActiveTab("ALL")}
          >
            <p className="text-xs font-medium text-slate-500">Total Inbox</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">{summary.total}</p>
          </div>
          <div
            className={cn(
              "cursor-pointer rounded-xl border bg-white p-3.5 shadow-2xs transition hover:border-blue-300",
              activeTab === "UNREAD" ? "border-blue-500 ring-2 ring-blue-100" : "border-slate-200"
            )}
            onClick={() => setActiveTab("UNREAD")}
          >
            <p className="text-xs font-medium text-blue-600">Unread</p>
            <p className="mt-1 text-2xl font-bold text-blue-700">{summary.unread}</p>
          </div>
          <div
            className={cn(
              "cursor-pointer rounded-xl border bg-white p-3.5 shadow-2xs transition hover:border-amber-300",
              activeTab === "ACTION_REQUIRED" ? "border-amber-500 ring-2 ring-amber-100" : "border-slate-200"
            )}
            onClick={() => setActiveTab("ACTION_REQUIRED")}
          >
            <p className="text-xs font-medium text-amber-600">Action Required</p>
            <p className="mt-1 text-2xl font-bold text-amber-700">{summary.actionRequired}</p>
          </div>
          <div
            className={cn(
              "cursor-pointer rounded-xl border bg-white p-3.5 shadow-2xs transition hover:border-rose-300",
              activeTab === "OVERDUE" ? "border-rose-500 ring-2 ring-rose-100" : "border-slate-200"
            )}
            onClick={() => setActiveTab("OVERDUE")}
          >
            <p className="text-xs font-medium text-rose-600">Overdue</p>
            <p className="mt-1 text-2xl font-bold text-rose-700">{summary.overdue}</p>
          </div>
          <div
            className={cn(
              "cursor-pointer rounded-xl border bg-white p-3.5 shadow-2xs transition hover:border-slate-400",
              activeTab === "ARCHIVED" ? "border-slate-600 ring-2 ring-slate-100" : "border-slate-200"
            )}
            onClick={() => setActiveTab("ARCHIVED")}
          >
            <p className="text-xs font-medium text-slate-500">Archived</p>
            <p className="mt-1 text-2xl font-bold text-slate-700">{summary.archived}</p>
          </div>
        </div>

        {/* Filter controls */}
        <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search notifications by title, keyword, or module..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 text-xs text-slate-800 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100"
            />
          </div>

          <div className="flex items-center gap-2">
            <div className="flex items-center gap-1.5 text-xs text-slate-500">
              <Filter size={14} />
              <span>Priority:</span>
            </div>
            <select
              value={priorityFilter}
              onChange={(e) => setPriorityFilter(e.target.value)}
              className="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
              <option value="ALL">All Priorities</option>
              <option value="CRITICAL">Critical</option>
              <option value="URGENT">Urgent</option>
              <option value="HIGH">High</option>
              <option value="NORMAL">Normal</option>
              <option value="LOW">Low</option>
            </select>
          </div>
        </div>

        {/* Notifications List */}
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
          {isLoading ? (
            <div className="flex flex-col items-center justify-center py-16 text-slate-400">
              <Loader2 className="h-8 w-8 animate-spin text-blue-600 mb-3" />
              <p className="text-sm font-medium text-slate-600">Loading notifications...</p>
            </div>
          ) : notifications.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
              <Bell size={36} className="text-slate-300 mb-3" />
              <p className="text-sm font-semibold text-slate-700">No notifications found</p>
              <p className="mt-1 text-xs text-slate-400">
                {search ? "No records match your search criteria." : "You have caught up with all updates."}
              </p>
            </div>
          ) : (
            <div className="divide-y divide-slate-100">
              {notifications.map((item) => {
                const isOverdue = item.category === "OVERDUE" || item.priority === "CRITICAL";
                return (
                  <div
                    key={item.id}
                    className={cn(
                      "flex flex-col gap-3 p-4 transition sm:flex-row sm:items-start sm:justify-between sm:p-5 hover:bg-slate-50/70",
                      !item.isRead ? "bg-blue-50/30" : "bg-white"
                    )}
                  >
                    <div className="flex items-start gap-3.5">
                      <div
                        className={cn(
                          "mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl",
                          isOverdue
                            ? "bg-rose-100 text-rose-600"
                            : item.category === "WORKFLOW"
                            ? "bg-emerald-100 text-emerald-600"
                            : "bg-blue-100 text-blue-600"
                        )}
                      >
                        {isOverdue ? <AlertTriangle size={17} /> : <Bell size={17} />}
                      </div>

                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <p className="text-sm font-semibold text-slate-900">{item.title}</p>
                          {!item.isRead ? (
                            <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                              NEW
                            </span>
                          ) : null}
                          {item.priority === "CRITICAL" || item.priority === "URGENT" ? (
                            <span className="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">
                              {item.priority}
                            </span>
                          ) : null}
                          {item.moduleCode ? (
                            <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                              {item.moduleCode}
                            </span>
                          ) : null}
                        </div>

                        <p className="mt-1 text-xs leading-relaxed text-slate-600">{item.message}</p>

                        <div className="mt-2.5 flex flex-wrap items-center gap-3 text-[11px] text-slate-400">
                          <span className="inline-flex items-center gap-1 font-medium">
                            <Clock3 size={12} />
                            {new Date(item.createdAt).toLocaleString("en-US", {
                              month: "short",
                              day: "numeric",
                              year: "numeric",
                              hour: "numeric",
                              minute: "2-digit",
                            })}
                          </span>
                          {item.actor ? (
                            <span>
                              By: <strong className="text-slate-600">{item.actor.name}</strong>
                            </span>
                          ) : null}
                        </div>
                      </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-1.5 self-end sm:self-center">
                      {item.actionUrl ? (
                        <Button
                          className="inline-flex items-center gap-1 border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50"
                          onClick={() => handleNavigate(item.actionUrl)}
                          type="button"
                        >
                          <span>{item.actionLabel || "View"}</span>
                          <ExternalLink size={12} />
                        </Button>
                      ) : null}

                      <button
                        title={item.isRead ? "Mark as unread" : "Mark as read"}
                        className="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                        onClick={() => handleToggleRead(item)}
                        type="button"
                      >
                        {item.isRead ? <MailOpen size={15} /> : <CheckCheck size={15} className="text-blue-600" />}
                      </button>

                      <button
                        title={item.isArchived ? "Restore to inbox" : "Archive notification"}
                        className="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                        onClick={() => handleToggleArchive(item)}
                        type="button"
                      >
                        {item.isArchived ? <RotateCcw size={15} /> : <Archive size={15} />}
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </AdminLayout>
  );
}
