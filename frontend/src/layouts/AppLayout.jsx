import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Bell,
  ChevronDown,
  ChevronUp,
  CircleHelp,
  LogOut,
  Menu,
  PanelLeftClose,
  PanelLeftOpen,
  Search,
  UserRound,
  X,
} from "lucide-react";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router";
import { useAuth } from "../auth/auth-context";
import Brand from "../components/Brand";
import ConfirmDialog from "../components/ui/ConfirmDialog";
import {
  hasPermission,
  navigationSections,
  notificationPathForUser,
  pageForPath,
  visibleFor,
} from "../config/navigation";
import { notificationApi } from "../services/api";
import { useToast } from "../ui/toast-context";
import { formatConfiguredDate } from "../utils/date-format";

function groupedNavigationItems(items) {
  const groups = [];
  const byGroup = new Map();

  items.forEach((item) => {
    const key = item.group ?? "__ungrouped";
    if (!byGroup.has(key)) {
      const group = { key, label: item.group ?? null, items: [] };
      byGroup.set(key, group);
      groups.push(group);
    }
    byGroup.get(key).items.push(item);
  });

  return groups;
}

function navigationItemActive(item, location) {
  const target = item.href ?? item.path;
  const [targetPath, targetQuery = ""] = target.split("?");
  const isPathMatch = item.end
    ? location.pathname === targetPath
    : location.pathname === targetPath ||
      location.pathname.startsWith(`${targetPath}/`);

  if (!isPathMatch) return false;
  if (!targetQuery) return true;

  const expected = new URLSearchParams(targetQuery);
  const current = new URLSearchParams(location.search);
  return [...expected.entries()].every(
    ([key, value]) => current.get(key) === value,
  );
}

function NavigationSection({ section, user, collapsed, onNavigate }) {
  const [expanded, setExpanded] = useState(true);
  const location = useLocation();
  const [expandedItems, setExpandedItems] = useState(() => ({
    iap: location.pathname.startsWith("/internal-audit-planning"),
    aem: location.pathname.startsWith("/audit-engagement-management"),
    cms: location.pathname.startsWith("/compliance-management"),
    arms: location.pathname.startsWith("/audit-resource-management"),
  }));
  const items = visibleFor(user, section.items);

  if (items.length === 0) return null;

  return (
    <section className="w-full min-w-0 max-w-full border-b border-white/15 py-2.5 last:border-b-0">
      {section.title && (
        <button
          className="flex w-full items-center justify-between rounded px-2 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-blue-50 transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-cyan-300"
          type="button"
          onClick={() => setExpanded((current) => !current)}
          aria-expanded={expanded}
          title={collapsed ? section.title : undefined}
        >
          {!collapsed && <span>{section.title}</span>}
          {!collapsed &&
            (expanded ? <ChevronUp size={15} /> : <ChevronDown size={15} />)}
          {collapsed && <span className="mx-auto h-px w-9 bg-white/35" />}
        </button>
      )}

      {(!section.title || expanded) && (
        <nav
          className="grid w-full min-w-0 max-w-full gap-1"
          aria-label={section.title ?? "Primary"}
        >
          {items.map((item) => {
            const ItemIcon = item.icon;
            const childItems = visibleFor(user, item.children ?? []);
            const childGroups = groupedNavigationItems(childItems);
            const childExpanded =
              expandedItems[item.key] ??
              location.pathname.startsWith(item.path);
            const moduleActive = childItems.some(
              (child) =>
                location.pathname === child.path ||
                location.pathname.startsWith(`${child.path}/`),
            );

            return (
              <div className="grid gap-1" key={item.path}>
                <div className="flex w-full min-w-0 max-w-full items-center gap-1">
                  {childItems.length > 0 ? (
                    <button
                      aria-expanded={childExpanded}
                      aria-label={`${childExpanded ? "Collapse" : "Expand"} ${item.label}`}
                      className={`group flex min-h-10 min-w-0 flex-1 items-center rounded-lg px-2.5 text-left text-[13px] font-medium transition duration-200 focus-visible:outline-2 focus-visible:outline-cyan-300 ${
                        moduleActive
                          ? "bg-[#4a87cb] text-white shadow-sm"
                          : "text-blue-50 hover:translate-x-0.5 hover:bg-white/12 hover:text-white"
                      } ${collapsed ? "justify-center" : "gap-3"}`}
                      onClick={() =>
                        setExpandedItems((current) => ({
                          ...current,
                          [item.key]: !childExpanded,
                        }))
                      }
                      title={collapsed ? item.label : undefined}
                      type="button"
                    >
                      <ItemIcon className="shrink-0" size={19} />
                      {!collapsed && (
                        <span className="min-w-0 flex-1 truncate">
                          {item.label}
                        </span>
                      )}
                    </button>
                  ) : (
                    <NavLink
                      className={({ isActive }) =>
                        `group flex min-h-10 min-w-0 flex-1 items-center rounded-lg px-2.5 text-[13px] font-medium transition duration-200 focus-visible:outline-2 focus-visible:outline-cyan-300 ${
                          isActive || moduleActive
                            ? "bg-[#4a87cb] text-white shadow-sm"
                            : "text-blue-50 hover:translate-x-0.5 hover:bg-white/12 hover:text-white"
                        } ${collapsed ? "justify-center" : "gap-3"}`
                      }
                      to={item.path}
                      onClick={onNavigate}
                      title={collapsed ? item.label : undefined}
                    >
                      <ItemIcon className="shrink-0" size={19} />
                      {!collapsed && (
                        <span className="min-w-0 flex-1 truncate">
                          {item.label}
                        </span>
                      )}
                    </NavLink>
                  )}
                  {!collapsed && childItems.length > 0 && (
                    <button
                      aria-expanded={childExpanded}
                      aria-label={`${childExpanded ? "Hide" : "Show"} ${item.label} pages`}
                      className="grid h-9 w-8 shrink-0 place-items-center rounded-lg text-blue-100 transition hover:bg-white/12 hover:text-white"
                      onClick={() =>
                        setExpandedItems((current) => ({
                          ...current,
                          [item.key]: !childExpanded,
                        }))
                      }
                      type="button"
                    >
                      {childExpanded ? (
                        <ChevronUp size={15} />
                      ) : (
                        <ChevronDown size={15} />
                      )}
                    </button>
                  )}
                </div>

                {!collapsed && childItems.length > 0 && childExpanded && (
                  <nav
                    aria-label={`${item.label} pages`}
                    className="ml-3 grid min-w-0 max-w-full gap-1 border-l border-blue-200/30 pl-3"
                  >
                    {childGroups.map((group) => (
                      <div className="grid gap-1" key={group.key}>
                        {group.label && (
                          <p className="px-2.5 pb-0.5 pt-2 text-[10px] font-bold uppercase tracking-[0.08em] text-blue-200/75 first:pt-0">
                            {group.label}
                          </p>
                        )}
                        {group.items.map((child) => {
                          const ChildIcon = child.icon;
                          const childActive = navigationItemActive(
                            child,
                            location,
                          );
                          return (
                            <NavLink
                              className={({ isActive }) =>
                                `flex min-h-9 min-w-0 items-center gap-2 rounded-lg px-2.5 text-[12px] font-medium transition ${
                                  isActive || childActive
                                    ? "bg-white/18 text-white shadow-sm"
                                    : "text-blue-100 hover:bg-white/10 hover:text-white"
                                }`
                              }
                              end={Boolean(child.end)}
                              key={child.screenId ?? child.href ?? child.path}
                              onClick={onNavigate}
                              to={child.href ?? child.path}
                            >
                              <ChildIcon className="shrink-0" size={15} />
                              <span className="truncate">{child.label}</span>
                            </NavLink>
                          );
                        })}
                      </div>
                    ))}
                  </nav>
                )}
              </div>
            );
          })}
        </nav>
      )}
    </section>
  );
}

export default function AppLayout() {
  const { user, logout, runtimeConfig } = useAuth();
  const toast = useToast();
  const location = useLocation();
  const navigate = useNavigate();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [logoutOpen, setLogoutOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const [notificationOpen, setNotificationOpen] = useState(false);
  const [notificationLoading, setNotificationLoading] = useState(false);
  const [recentNotifications, setRecentNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const canViewNotifications = hasPermission(user, "notifications.view");
  const currentPage = pageForPath(location.pathname);
  const dateLabel = useMemo(
    () =>
      formatConfiguredDate(new Date(), {
        format: runtimeConfig.dateFormat,
        timezone: runtimeConfig.timezone,
        weekday: true,
      }),
    [runtimeConfig.dateFormat, runtimeConfig.timezone],
  );

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await logout();
    } catch (error) {
      toast.error(
        error instanceof Error
          ? `${error.message} Your session is still active; please try again.`
          : "Unable to sign out. Your session is still active; please try again.",
      );
    } finally {
      setLoggingOut(false);
      setLogoutOpen(false);
    }
  }

  const loadNotifications = useCallback(async () => {
    if (!canViewNotifications) return;
    setNotificationLoading(true);
    try {
      const data = await notificationApi.recent();
      setRecentNotifications(data.notifications ?? []);
      setUnreadCount(data.unreadCount ?? 0);
    } catch {
      // The rest of AGIS remains usable if the notification refresh fails.
    } finally {
      setNotificationLoading(false);
    }
  }, [canViewNotifications]);

  useEffect(() => {
    if (!canViewNotifications) return undefined;
    const initial = window.setTimeout(loadNotifications, 0);
    const interval = window.setInterval(
      loadNotifications,
      runtimeConfig.notificationRefreshSeconds * 1000,
    );
    window.addEventListener("agis:notifications-changed", loadNotifications);
    return () => {
      window.clearTimeout(initial);
      window.clearInterval(interval);
      window.removeEventListener(
        "agis:notifications-changed",
        loadNotifications,
      );
    };
  }, [
    canViewNotifications,
    loadNotifications,
    runtimeConfig.notificationRefreshSeconds,
  ]);

  async function openNotification(notification) {
    if (!notification.isRead) {
      try {
        await notificationApi.markRead(notification.id);
        setUnreadCount((current) => Math.max(0, current - 1));
        setRecentNotifications((current) =>
          current.map((item) =>
            item.id === notification.id ? { ...item, isRead: true } : item,
          ),
        );
      } catch {
        // Navigation is still useful even if read state could not be saved.
      }
    }
    setNotificationOpen(false);
    navigate(notificationPathForUser(user, notification));
  }

  const sidebarWidth = collapsed ? "lg:w-20" : "lg:w-72";
  const navigationCollapsed = collapsed && !mobileOpen;

  return (
    <div className="flex min-h-screen bg-[#f3f8fc] font-['Segoe_UI',Arial,sans-serif] text-slate-800">
      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-72 max-w-[calc(100vw-1rem)] flex-col overflow-hidden bg-[#28598f] text-white shadow-xl transition-all duration-300 lg:sticky lg:top-0 lg:h-screen lg:max-w-none lg:translate-x-0 ${sidebarWidth} ${
          mobileOpen ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div
          className={`flex min-h-20 items-center border-b border-white/20 px-3 ${collapsed ? "lg:justify-center" : ""}`}
        >
          <Brand collapsed={navigationCollapsed} />
          <button
            className="ml-auto grid h-9 w-9 place-items-center rounded-md text-blue-50 transition hover:bg-white/15 lg:hidden"
            type="button"
            onClick={() => setMobileOpen(false)}
            aria-label="Close navigation"
          >
            <X size={20} />
          </button>
        </div>

        <div className="min-h-0 min-w-0 flex-1 overscroll-contain overflow-x-hidden overflow-y-auto px-2 py-2 pr-2 [scrollbar-color:rgba(255,255,255,.35)_transparent] [scrollbar-gutter:stable]">
          {navigationSections.map((section) => (
            <NavigationSection
              key={section.key}
              section={section}
              user={user}
              collapsed={navigationCollapsed}
              onNavigate={() => setMobileOpen(false)}
            />
          ))}
        </div>

        <div className="shrink-0 border-t border-white/20 p-3">
          <div
            className={`flex items-center ${collapsed ? "lg:justify-center" : "gap-3"}`}
          >
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white text-sm font-bold text-[#28598f]">
              {user.initials}
            </span>
            {!collapsed && (
              <span className="min-w-0">
                <strong className="block truncate text-sm">{user.name}</strong>
                <small className="block truncate text-[11px] text-blue-100">
                  {user.role}
                </small>
              </span>
            )}
          </div>
          <button
            className={`mt-3 flex h-10 w-full items-center rounded-md text-sm transition hover:bg-white/12 focus-visible:outline-2 focus-visible:outline-cyan-300 ${
              collapsed ? "justify-center" : "gap-3 px-2"
            }`}
            type="button"
            onClick={() => setLogoutOpen(true)}
            title={collapsed ? "Logout" : undefined}
          >
            <LogOut size={19} />
            {!collapsed && "Logout"}
          </button>
        </div>
      </aside>

      {mobileOpen && (
        <button
          className="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[1px] lg:hidden"
          type="button"
          onClick={() => setMobileOpen(false)}
          aria-label="Close navigation"
        />
      )}

      <div className="flex min-w-0 flex-1 flex-col overflow-x-hidden">
        <header className="sticky top-0 z-30 flex min-h-16 min-w-0 items-center border-b border-slate-200/90 bg-white px-3 shadow-sm sm:px-5">
          <button
            className="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-100 lg:hidden"
            type="button"
            onClick={() => setMobileOpen(true)}
            aria-label="Open navigation"
          >
            <Menu size={21} />
          </button>

          <button
            className="mr-3 hidden h-11 w-11 place-items-center rounded-lg text-[#068bc7] transition duration-200 hover:scale-105 hover:bg-sky-50 lg:grid"
            type="button"
            onClick={() => setCollapsed((current) => !current)}
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
          >
            {collapsed ? (
              <PanelLeftOpen size={26} />
            ) : (
              <PanelLeftClose size={26} />
            )}
          </button>

          <h1 className="ml-3 truncate text-xl font-bold text-slate-800 sm:text-2xl lg:ml-0">
            {currentPage?.label ??
              (location.pathname === "/unauthorized"
                ? "Access denied"
                : "AGIS")}
          </h1>

          <div className="ml-auto flex items-center gap-2">
            <label className="hidden h-10 w-64 items-center gap-2 rounded-lg border border-slate-200 px-3 text-slate-500 transition focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 xl:flex">
              <Search size={16} />
              <input
                className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400"
                placeholder="Search anything..."
                aria-label="Search"
              />
              <kbd className="text-[10px]">Ctrl + K</kbd>
            </label>
            {canViewNotifications && (
              <div className="relative">
                <button
                  aria-expanded={notificationOpen}
                  aria-label="Notifications"
                  className="relative grid h-10 w-10 place-items-center rounded-lg text-slate-700 transition hover:bg-slate-100"
                  onClick={() => {
                    setNotificationOpen((current) => !current);
                    loadNotifications();
                  }}
                  type="button"
                >
                  <Bell size={20} />
                  {unreadCount > 0 && (
                    <span className="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">
                      {unreadCount > 99 ? "99+" : unreadCount}
                    </span>
                  )}
                </button>
                {notificationOpen && (
                  <div className="absolute right-0 top-12 w-[min(24rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                    <div className="flex items-center border-b border-slate-200 px-4 py-3">
                      <div>
                        <strong className="block text-sm text-slate-800">
                          Notifications
                        </strong>
                        <small className="text-xs text-slate-500">
                          {unreadCount} unread
                        </small>
                      </div>
                      {unreadCount > 0 && (
                        <button
                          className="ml-auto text-xs font-bold text-sky-700 hover:text-sky-900"
                          onClick={async () => {
                            await notificationApi.markAllRead();
                            await loadNotifications();
                          }}
                          type="button"
                        >
                          Mark all read
                        </button>
                      )}
                    </div>
                    <div className="max-h-[26rem] overflow-y-auto">
                      {notificationLoading &&
                        recentNotifications.length === 0 && (
                          <div className="space-y-2 p-3">
                            {[1, 2, 3].map((item) => (
                              <div
                                className="h-16 animate-pulse rounded-lg bg-slate-100"
                                key={item}
                              />
                            ))}
                          </div>
                        )}
                      {!notificationLoading &&
                        recentNotifications.length === 0 && (
                          <div className="p-8 text-center">
                            <Bell
                              className="mx-auto text-slate-300"
                              size={34}
                            />
                            <p className="mt-2 text-sm font-bold text-slate-700">
                              Your inbox is clear
                            </p>
                          </div>
                        )}
                      {recentNotifications.map((notification) => (
                        <button
                          className={`flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left transition hover:bg-sky-50 ${
                            notification.isRead ? "bg-white" : "bg-sky-50/50"
                          }`}
                          key={notification.id}
                          onClick={() => openNotification(notification)}
                          type="button"
                        >
                          <span
                            className={`mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${
                              notification.isRead
                                ? "bg-slate-300"
                                : "bg-sky-600"
                            }`}
                          />
                          <span className="min-w-0">
                            <strong className="block truncate text-xs text-slate-800">
                              {notification.title}
                            </strong>
                            <span className="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">
                              {notification.message}
                            </span>
                            <small className="mt-1 block text-[10px] font-bold text-sky-700">
                              {notification.moduleCode} ·{" "}
                              {notification.category.replaceAll("_", " ")}
                            </small>
                          </span>
                        </button>
                      ))}
                    </div>
                    <NavLink
                      className="flex h-11 items-center justify-center bg-slate-50 text-sm font-bold text-sky-700 hover:bg-sky-50"
                      onClick={() => setNotificationOpen(false)}
                      to="/notifications"
                    >
                      View all notifications
                    </NavLink>
                  </div>
                )}
              </div>
            )}
            <button
              className="hidden h-10 w-10 place-items-center rounded-lg text-slate-700 transition hover:bg-slate-100 sm:grid"
              type="button"
              onClick={() => toast.info("Help center is coming soon.")}
              aria-label="Help"
            >
              <CircleHelp size={20} />
            </button>

            <div className="relative">
              <button
                className="flex items-center gap-2 rounded-lg px-1.5 py-1 transition hover:bg-slate-100"
                type="button"
                onClick={() => setProfileOpen((current) => !current)}
                aria-expanded={profileOpen}
              >
                <span className="grid h-9 w-9 place-items-center rounded-full bg-sky-600 text-xs font-bold text-white">
                  {user.initials}
                </span>
                <span className="hidden max-w-36 text-left lg:block">
                  <strong className="block truncate text-xs text-slate-800">
                    {user.name}
                  </strong>
                  <small className="block truncate text-[10px] text-slate-500">
                    {user.role}
                  </small>
                </span>
                {profileOpen ? (
                  <ChevronUp size={14} />
                ) : (
                  <ChevronDown size={14} />
                )}
              </button>

              {profileOpen && (
                <div className="absolute right-0 top-12 w-64 rounded-lg border border-slate-200 bg-white p-3 shadow-xl">
                  <strong className="block text-sm">{user.name}</strong>
                  <span className="mt-1 block text-xs text-slate-500">
                    {user.office}
                  </span>
                  <NavLink
                    className="mt-3 flex w-full items-center gap-2 rounded-md bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-100"
                    onClick={() => setProfileOpen(false)}
                    to="/profile"
                  >
                    <UserRound size={17} />
                    Edit profile
                  </NavLink>
                  <button
                    className="mt-2 flex w-full items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700 transition hover:bg-red-50 hover:text-red-700"
                    type="button"
                    onClick={() => {
                      setProfileOpen(false);
                      setLogoutOpen(true);
                    }}
                  >
                    <LogOut size={17} />
                    Sign out
                  </button>
                </div>
              )}
            </div>
          </div>
        </header>

        <div className="agis-route-content flex-1">
          <Outlet context={{ dateLabel }} />
        </div>

        <footer className="flex min-h-8 flex-wrap items-center gap-x-5 gap-y-1 border-t border-slate-200 bg-white px-4 py-2 text-[10px] text-slate-500">
          <span>
            {runtimeConfig.systemShortName} v{runtimeConfig.systemVersion}
          </span>
          <span>City Internal Audit Service (CIAS)</span>
          <span className="sm:ml-auto">
            © {new Date().getFullYear()} {runtimeConfig.organizationName}. All
            rights reserved.
          </span>
        </footer>
      </div>

      <ConfirmDialog
        busy={loggingOut}
        confirmLabel="Sign out"
        description="You will need to enter your credentials again to continue using AGIS."
        onCancel={() => setLogoutOpen(false)}
        onConfirm={handleLogout}
        open={logoutOpen}
        title={`Sign out of ${runtimeConfig.systemShortName}?`}
        tone="logout"
      />
    </div>
  );
}
