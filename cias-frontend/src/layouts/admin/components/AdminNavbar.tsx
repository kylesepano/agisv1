import { AlertTriangle, Bell, CheckCheck, Clock3, HelpCircle, LayoutGrid, Loader2, Search, ShieldCheck } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { Button } from "../../../components/ui/button";
import { Modal } from "../../../components/ui/modal";
import { useToast } from "../../../components/ui/toast";
import { clearAuthSession } from "../../../lib/auth";
import { useAuth } from "../../../hooks/useAuth";
import { notificationService, type BackendNotification } from "../../../services/notificationService";
import { cn } from "../../../utils/helpers";
import { navigateTo, useCurrentPath } from "../../../routes";
import { UserDropdown } from "./UserDropdown";

type AdminNavbarProps = {
  onMenuToggle: () => void;
};

const adminPageTitles: Record<string, string> = {
  "/": "Audit Engagement Management",
  "/admin/dashboard": "Dashboard",
  "/admin/users": "Users",
  "/admin/offices": "Offices",
  "/admin/audit-areas": "Audit Areas",
  "/admin/audit-focuses": "Audit Focuses",
  "/admin/categories": "Categories",
  "/admin/risk-management": "Risk Levels",
  "/admin/risk-assessment": "Risk Assessment",
  "/admin/risk-assessment/assessment-matrix": "Assessment Matrix",
  "/admin/risk-assessment/risk-register": "Risk Register",
  "/admin/annual-audit-plan": "Annual Audit Plan",
  "/admin/audit-calendar": "Audit Calendar",
  "/admin/audit-engagements": "Audit Engagement Management",
  "/admin/audit-engagement": "Audit Engagement Management",
  "/admin/audit-engagement/create": "Audit Engagement Management",
  "/admin/team-assignment": "Team Assignment",
  "/admin/audit-universe": "Audit Universe",
  "/admin/audit-universe/create": "Create Auditable Unit",
  "/admin/reports": "Reports",
  "/admin/tasks": "Activity Logs",
  "/admin/settings": "Settings",
  "/admin/compliances": "Compliances",
  "/admin/documents": "Documents",
  "/admin/policies": "Policies",
  "/admin/training": "Training",
  "/admin/notifications": "Notifications Center",
  "/notifications": "Notifications Center",
};

function notificationToneClasses(tone: "info" | "warning" | "success") {
  if (tone === "warning") {
    return "bg-amber-100 text-amber-700";
  }

  if (tone === "success") {
    return "bg-emerald-100 text-emerald-700";
  }

  return "bg-blue-100 text-blue-700";
}

function getNotificationTone(item: BackendNotification): "info" | "warning" | "success" {
  if (item.category === "OVERDUE" || item.priority === "CRITICAL" || item.priority === "URGENT") {
    return "warning";
  }
  if (item.category === "WORKFLOW" || item.isRead) {
    return "success";
  }
  return "info";
}

function formatNotificationTime(dateStr?: string | null): string {
  if (!dateStr) return "Recently";
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return "Recently";
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSec = Math.floor(diffMs / 1000);
  const diffMin = Math.floor(diffSec / 60);
  const diffHrs = Math.floor(diffMin / 60);
  const diffDays = Math.floor(diffHrs / 24);

  if (diffMin < 1) return "Just now";
  if (diffMin < 60) return `${diffMin} minute${diffMin === 1 ? "" : "s"} ago`;
  if (diffHrs < 24) {
    const isToday = now.toDateString() === date.toDateString();
    if (isToday) {
      return `Today, ${date.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" })}`;
    }
    return `${diffHrs} hour${diffHrs === 1 ? "" : "s"} ago`;
  }
  if (diffDays === 1) return "Yesterday";
  if (diffDays < 7) return `${diffDays} days ago`;
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

function resolveNotificationActionUrl(url?: string | null): string | null {
  if (!url) return null;
  if (url.startsWith("/admin/")) return url;
  if (url.startsWith("/audit-engagement") || url.startsWith("/audit-engagements")) {
    return `/admin${url}`;
  }
  if (url.startsWith("/internal-audit-planning")) {
    return "/admin/annual-audit-plan";
  }
  if (url.startsWith("/workflow-management")) {
    return "/admin/annual-audit-plan";
  }
  return url;
}

function getAdminPageTitle(path: string) {
  if (path.includes("/audit-engagement") || path.includes("/audit-engagements")) {
    return "Audit Engagement Management";
  }

  if (adminPageTitles[path]) {
    return adminPageTitles[path];
  }

  const lastSegment = path.split("/").filter(Boolean).at(-1);

  if (!lastSegment) {
    return "Audit Engagement Management";
  }

  return lastSegment
    .split("-")
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(" ");
}

export function AdminNavbar({ onMenuToggle }: AdminNavbarProps) {
  const currentPath = useCurrentPath();
  const { showToast } = useToast();
  const { runtimeConfig, logout } = useAuth();
  const [draftFeatureName, setDraftFeatureName] = useState<string | null>(null);
  const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [notifications, setNotifications] = useState<BackendNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState<number>(0);
  const [isLoadingNotifications, setIsLoadingNotifications] = useState<boolean>(false);
  const notificationsRef = useRef<HTMLDivElement | null>(null);

  const loadNotifications = useCallback(async () => {
    try {
      setIsLoadingNotifications(true);
      const data = await notificationService.recent();
      setNotifications(data.notifications ?? []);
      setUnreadCount(data.unreadCount ?? 0);
    } catch {
      // Keep UI functional if backend request fails or times out
    } finally {
      setIsLoadingNotifications(false);
    }
  }, []);

  useEffect(() => {
    loadNotifications();

    const refreshSeconds =
      typeof runtimeConfig?.notificationRefreshSeconds === "number" &&
      runtimeConfig.notificationRefreshSeconds > 0
        ? runtimeConfig.notificationRefreshSeconds
        : 60;

    const interval = window.setInterval(loadNotifications, refreshSeconds * 1000);
    const handleNotificationsChanged = () => {
      loadNotifications();
    };

    window.addEventListener("agis:notifications-changed", handleNotificationsChanged);
    return () => {
      window.clearInterval(interval);
      window.removeEventListener("agis:notifications-changed", handleNotificationsChanged);
    };
  }, [loadNotifications, runtimeConfig?.notificationRefreshSeconds]);

  const handleMarkAllRead = async () => {
    if (unreadCount === 0) return;
    try {
      setNotifications((prev) => prev.map((n) => ({ ...n, isRead: true })));
      setUnreadCount(0);
      await notificationService.markAllRead();
      showToast({
        title: "All notifications marked as read",
        description: "Your notification feed has been updated.",
        tone: "success",
      });
      window.dispatchEvent(new CustomEvent("agis:notifications-changed"));
    } catch {
      showToast({
        title: "Failed to update",
        description: "Could not mark all notifications as read.",
        tone: "warning",
      });
      loadNotifications();
    }
  };

  const handleNotificationClick = async (item: BackendNotification) => {
    if (!item.isRead) {
      setNotifications((prev) =>
        prev.map((n) => (n.id === item.id ? { ...n, isRead: true } : n))
      );
      setUnreadCount((prev) => Math.max(0, prev - 1));
      try {
        await notificationService.markRead(item.id);
        window.dispatchEvent(new CustomEvent("agis:notifications-changed"));
      } catch {
        // Navigation remains useful even if status update fails
      }
    }
    setIsNotificationsOpen(false);

    const targetUrl = resolveNotificationActionUrl(item.actionUrl);
    if (targetUrl) {
      navigateTo(targetUrl);
    }
  };

  useEffect(() => {
    const handleGlobalKeyDown = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        setDraftFeatureName("Search (Ctrl + K)");
      }
    };

    window.addEventListener("keydown", handleGlobalKeyDown);
    return () => window.removeEventListener("keydown", handleGlobalKeyDown);
  }, []);

  useEffect(() => {
    if (!isNotificationsOpen) {
      return;
    }

    const handlePointerDown = (event: MouseEvent) => {
      if (!notificationsRef.current?.contains(event.target as Node)) {
        setIsNotificationsOpen(false);
      }
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setIsNotificationsOpen(false);
      }
    };

    document.addEventListener("mousedown", handlePointerDown);
    window.addEventListener("keydown", handleKeyDown);

    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [isNotificationsOpen]);

  const handleLogout = () => {
    setShowLogoutModal(true);
  };

  const confirmLogout = async () => {
    setIsLoggingOut(true);
    try {
      await logout();
    } catch {
      clearAuthSession();
    } finally {
      setIsLoggingOut(false);
      setShowLogoutModal(false);
      showToast({
        title: "Signed out",
        description: "Your session has ended. Redirecting to the login page.",
        tone: "success",
      });
      navigateTo("/login");
    }
  };

  return (
    <>
      <header className="sticky top-0 z-30 flex h-14 md:h-16 items-center justify-between gap-3 border-b border-[#215899] bg-[#2762a9] px-4 shadow-sm text-white max-[720px]:px-3">
        <div className="flex min-w-0 items-center gap-2.5">
          <button
            aria-label="Toggle navigation"
            className="grid h-8 w-8 place-items-center rounded-lg text-white/90 transition-colors hover:bg-white/10 active:bg-white/20 min-[960px]:hidden"
            type="button"
            onClick={onMenuToggle}
          >
            <LayoutGrid size={18} strokeWidth={2} />
          </button>

          <div className="flex min-w-0 items-center gap-2.5">
            <ShieldCheck size={22} strokeWidth={2} className="text-white shrink-0" />
            <h1 className="truncate text-[15px] sm:text-[16px] font-medium tracking-normal text-white">
              {getAdminPageTitle(currentPath)}
            </h1>
          </div>
        </div>

        <div className="flex shrink-0 items-center gap-2 sm:gap-2.5">
          <button
            className="hidden h-9 w-60 items-center justify-between rounded-md bg-white px-3 text-xs text-slate-400 shadow-xs transition hover:bg-slate-50 sm:flex md:w-68 lg:w-76"
            type="button"
            onClick={() => setDraftFeatureName("Search (Ctrl + K)")}
          >
            <span className="flex items-center gap-2 text-slate-400">
              <Search size={15} strokeWidth={2} className="shrink-0" />
              <span className="text-xs text-slate-400 font-normal">Search anything...</span>
            </span>
            <span className="text-[11px] text-slate-400 font-normal">
              Ctrl + K
            </span>
          </button>

          <div className="hidden h-5 w-px bg-white/30 sm:block" />

          <div className="relative" ref={notificationsRef}>
            <button
              aria-expanded={isNotificationsOpen}
              aria-label="Notifications"
              className={cn(
                "relative grid h-8 w-8 place-items-center rounded-lg text-white transition hover:bg-white/10 sm:h-9 sm:w-9",
                isNotificationsOpen && "bg-white/15",
              )}
              type="button"
              onClick={() => setIsNotificationsOpen((prev) => !prev)}
            >
              <Bell size={19} strokeWidth={2} className="text-white" />
              {unreadCount > 0 ? (
                <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#dc2626] px-1 text-[10px] font-bold text-white shadow-xs">
                  {unreadCount > 99 ? "99+" : unreadCount}
                </span>
              ) : null}
            </button>

            {isNotificationsOpen ? (
              <div className="absolute right-0 top-[calc(100%+0.55rem)] z-50 w-[min(92vw,360px)] overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-900 shadow-[0_20px_60px_rgba(15,23,42,0.16)] animate-[page-enter_180ms_cubic-bezier(0.22,1,0.36,1)]">
                <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">Notifications</p>
                    <p className="mt-0.5 text-[11px] text-slate-500">
                      {unreadCount === 0 ? "All caught up" : `${unreadCount} unread update${unreadCount === 1 ? "" : "s"}`}
                    </p>
                  </div>
                  {unreadCount > 0 ? (
                    <button
                      className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-semibold text-blue-600 transition hover:bg-blue-50"
                      type="button"
                      onClick={handleMarkAllRead}
                    >
                      <CheckCheck size={13} />
                      Mark all read
                    </button>
                  ) : null}
                </div>

                <div className="max-h-[360px] overflow-y-auto p-2">
                  {isLoadingNotifications && notifications.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 text-slate-400">
                      <Loader2 className="h-6 w-6 animate-spin text-blue-600 mb-2" />
                      <p className="text-xs">Loading notifications...</p>
                    </div>
                  ) : notifications.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 text-center text-slate-400">
                      <Bell size={28} className="text-slate-300 mb-2" />
                      <p className="text-xs font-medium text-slate-600">No notifications</p>
                      <p className="text-[11px] text-slate-400 mt-0.5">You have no new updates</p>
                    </div>
                  ) : (
                    <div className="space-y-1.5">
                      {notifications.map((item) => {
                        const tone = getNotificationTone(item);
                        return (
                          <button
                            key={item.id}
                            className={cn(
                              "flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-slate-50",
                              !item.isRead ? "bg-blue-50/40" : "bg-white"
                            )}
                            type="button"
                            onClick={() => handleNotificationClick(item)}
                          >
                            <div
                              className={cn(
                                "mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full",
                                notificationToneClasses(tone),
                              )}
                            >
                              <Bell size={14} />
                            </div>

                            <div className="min-w-0 flex-1">
                              <div className="flex items-start justify-between gap-3">
                                <p className="text-[12px] font-semibold text-slate-800">
                                  {item.title}
                                </p>
                                {!item.isRead ? (
                                  <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-500" />
                                ) : null}
                              </div>
                              <p className="mt-0.5 text-[11px] leading-5 text-slate-500">
                                {item.message}
                              </p>
                              <div className="mt-1 flex items-center justify-between text-[10px] font-medium text-slate-400">
                                <span className="inline-flex items-center gap-1">
                                  <Clock3 size={11} />
                                  {formatNotificationTime(item.createdAt)}
                                </span>
                                {item.moduleCode ? (
                                  <span className="rounded bg-slate-100 px-1 py-0.5 text-[9px] font-semibold text-slate-600">
                                    {item.moduleCode}
                                  </span>
                                ) : null}
                              </div>
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>

                <div className="border-t border-slate-100 bg-slate-50/80 px-4 py-2.5">
                  <button
                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-100"
                    type="button"
                    onClick={() => {
                      setIsNotificationsOpen(false);
                      navigateTo("/admin/notifications");
                    }}
                  >
                    View all notifications
                  </button>
                </div>
              </div>
            ) : null}
          </div>

          <button
            aria-label="Help"
            className="grid h-8 w-8 place-items-center rounded-lg text-white transition hover:bg-white/10 sm:h-9 sm:w-9"
            type="button"
            onClick={() => setDraftFeatureName("Help & Support")}
          >
            <HelpCircle size={19} strokeWidth={2} className="text-white" />
          </button>

          <div className="h-5 w-px bg-white/30" />

          <UserDropdown
            onProfileSelect={() => setDraftFeatureName("Profile")}
            onSettingsSelect={() => navigateTo("/admin/settings")}
            onLogoutSelect={handleLogout}
          />
        </div>
      </header>

      <Modal
        isOpen={Boolean(draftFeatureName)}
        onClose={() => setDraftFeatureName(null)}
        title="Module Under Development"
        size="sm"
        footer={
          <div className="flex justify-end">
            <Button
              className="bg-[#234a87] hover:bg-[#1c3c6f] text-white font-medium px-4 py-2 rounded-lg"
              onClick={() => setDraftFeatureName(null)}
            >
              Got it
            </Button>
          </div>
        }
      >
        <div className="flex items-start gap-4 py-2">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-600">
            <AlertTriangle size={22} />
          </div>
          <div>
            <h3 className="text-base font-semibold text-slate-900">
              {draftFeatureName} is not functional yet
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              This feature is currently under active development. Please check back soon as we roll out additional AGIS system modules.
            </p>
          </div>
        </div>
      </Modal>

      <Modal
        isOpen={showLogoutModal}
        onClose={() => {
          if (!isLoggingOut) {
            setShowLogoutModal(false);
          }
        }}
        title="Confirm Logout"
        size="sm"
        closeOnOverlayClick={!isLoggingOut}
        closeOnEscape={!isLoggingOut}
        showCloseButton={!isLoggingOut}
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button
              variant="ghost"
              disabled={isLoggingOut}
              onClick={() => setShowLogoutModal(false)}
            >
              Cancel
            </Button>
            <Button
              className="bg-red-600 px-4 py-2 font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-80"
              disabled={isLoggingOut}
              onClick={confirmLogout}
            >
              {isLoggingOut ? (
                <span className="flex items-center justify-center gap-2">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>Logging out...</span>
                </span>
              ) : (
                "Log Out"
              )}
            </Button>
          </div>
        }
      >
        <div className="py-2">
          <p className="text-base font-semibold text-slate-900">
            Are you sure you want to log out?
          </p>
          <p className="mt-1 text-sm text-slate-500">
            This will end your current session and redirect you to the login page.
          </p>
        </div>
      </Modal>
    </>
  );
}
