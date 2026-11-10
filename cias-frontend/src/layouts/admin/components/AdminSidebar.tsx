import {
  CalendarDays,
  CheckSquare,
  ChevronDown,
  FileCheck2,
  FileSearch2,
  FolderCog,
  Home,
  LayoutDashboard,
  LineChart,
  Loader2,
  LogOut,
  NotebookText,
  Settings,
  ShieldCheck,
  ShieldAlert,
  Users,
  X,
  type LucideIcon,
} from "lucide-react";
import { useState } from "react";
import logo from "../../../assets/images/cias-logo.png";
import { useToast } from "../../../components/ui/toast";
import { useAuth } from "../../../hooks/useAuth";
import { clearAuthSession } from "../../../lib/auth";
import { navigateTo, useCurrentPath } from "../../../routes";
import { cn } from "../../../utils/helpers";

import { Button } from "../../../components/ui/button";
import { Modal } from "../../../components/ui/modal";

type AdminSidebarProps = {
  isOpen: boolean;
  isCollapsed: boolean;
  onClose: () => void;
};

type SidebarNavItem = {
  label: string;
  path?: string;
  icon?: LucideIcon;
  disabled?: boolean;
  children?: SidebarNavItem[];
};

export function AdminSidebar({
  isOpen,
  isCollapsed,
  onClose,
}: AdminSidebarProps) {
  const { showToast } = useToast();
  const { runtimeConfig, logout } = useAuth();
  const currentPath = useCurrentPath();
  const shortName = typeof runtimeConfig?.systemShortName === "string" ? runtimeConfig.systemShortName : "AGIS";
  const systemName = typeof runtimeConfig?.systemName === "string" ? runtimeConfig.systemName : "";
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({
    "Master Registries": false,
    "Settings & Administration": false,
  });
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  const handleNavigate = () => {
    if (typeof window !== "undefined" && window.innerWidth < 960) {
      onClose();
    }
  };

  const handleDraftClick = (item: SidebarNavItem) => {
    showToast({
      title: `${item.label} is still in draft`,
      description:
        "This section is not functional yet. Please check back soon as we roll out additional AGIS modules.",
      tone: "warning",
    });
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
        description:
          "Your local session has been cleared. Redirecting you to the login page.",
        tone: "success",
      });
      navigateTo("/login");
      handleNavigate();
    }
  };

  const isItemActive = (path?: string) => {
    if (!path) return false;
    if (path === "/admin/dashboard" || path === "/") {
      return currentPath === "/" || currentPath === "/admin/dashboard";
    }
    if (path === "/admin/audit-engagements") {
      return currentPath.startsWith("/admin/audit-engagement");
    }
    return currentPath.startsWith(path);
  };

  const topSimpleLinks: SidebarNavItem[] = [
    {
      label: "Home",
      path: "/admin/dashboard",
      icon: Home,
    },
    {
      label: "Dashboard",
      path: "/admin/dashboard",
      icon: LayoutDashboard,
    },
  ];

  const mainModules: SidebarNavItem[] = [
    {
      label: "Internal Audit Planning",
      path: "/admin/annual-audit-plan",
      icon: CalendarDays,
    },
    {
      label: "Audit Engagement Management",
      path: "/admin/audit-engagements",
      icon: ShieldCheck,
    },
    {
      label: "Compliance Management",
      path: "/admin/compliances",
      icon: CheckSquare,
    },
    {
      label: "Audit Resource Management",
      path: "/admin/team-assignment",
      icon: Users,
    },
    {
      label: "Audit Intelligence System",
      path: "/admin/reports",
      icon: LineChart,
    },
  ];

  const expandableSections: {
    label: string;
    children: SidebarNavItem[];
  }[] = [
    {
      label: "Master Registries",
      children: [
        {
          label: "Office Registry",
          path: "/admin/offices",
          icon: NotebookText,
        },
        {
          label: "Audit Area Registry",
          path: "/admin/audit-areas",
          icon: FileSearch2,
        },
        {
          label: "Audit Focus Registry",
          path: "/admin/audit-focuses",
          icon: FileCheck2,
        },
        {
          label: "User Registry",
          path: "/admin/users",
          icon: Users,
        },
        {
          label: "Auditable Units",
          path: "/admin/audit-universe",
          icon: FileSearch2,
        },
      ],
    },
    {
      label: "Settings & Administration",
      children: [
        {
          label: "System Settings",
          path: "/admin/settings",
          icon: Settings,
        },
        {
          label: "Activity Logs",
          path: "/admin/tasks",
          icon: NotebookText,
        },
        {
          label: "Document Management",
          path: "/admin/documents",
          icon: FolderCog,
        },
        {
          label: "Policies",
          path: "/admin/policies",
          icon: FileCheck2,
        },
        {
          label: "Training",
          path: "/admin/training",
          icon: FileSearch2,
        },
      ],
    },
  ];

  return (
    <>
      <aside
        className={cn(
          "flex h-screen w-full shrink-0 flex-col overflow-hidden border-r border-[#1a3452] bg-[#234062] text-white transition-[padding] max-[960px]:fixed max-[960px]:inset-y-0 max-[960px]:left-0 max-[960px]:z-30 max-[960px]:w-[min(84vw,300px)] max-[960px]:-translate-x-full max-[960px]:transition-transform max-[640px]:w-full",
          isOpen && "max-[960px]:translate-x-0",
        )}
      >
        {/* Mobile top close bar */}
        <div className="hidden h-14 items-center justify-between border-b border-white/10 px-3 max-[960px]:flex">
          <div className="flex items-center gap-2 text-slate-200">
            <ShieldAlert size={16} />
            <span className="text-xs font-semibold uppercase tracking-wider">
              Menu
            </span>
          </div>
          <button
            aria-label="Close sidebar"
            className="grid h-8 w-8 place-items-center rounded-lg text-slate-200 transition hover:bg-white/10"
            type="button"
            onClick={onClose}
          >
            <X size={18} />
          </button>
        </div>

        {/* Header Branding Card (Imitates attached reference image) */}
        <a
          className={cn(
            "flex w-full items-center border-b border-white/10 bg-[#284870] px-4 py-3 text-white no-underline transition-colors hover:bg-[#2b4d77]",
            isCollapsed ? "justify-center px-2 py-3" : "gap-3",
          )}
          href="/admin/dashboard"
          onClick={(event) => {
            event.preventDefault();
            navigateTo("/admin/dashboard");
            handleNavigate();
          }}
          title={isCollapsed ? shortName : undefined}
        >
          <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-xs">
            <img
              alt="City Internal Audit Seal"
              className="h-[270%] w-[270%] max-w-none object-contain"
              src={logo}
            />
          </div>

          {!isCollapsed && (
            <div className="min-w-0 flex-1">
              <p className="text-[1.3rem] font-bold leading-none tracking-tight text-white">
                {shortName}
              </p>
              <p className="mt-1 text-[11px] font-normal leading-[1.25] text-slate-200/90">
                {systemName ? (
                  systemName
                ) : (
                  <>
                    Audit Governance
                    <br />
                    Information System
                  </>
                )}
              </p>
            </div>
          )}
        </a>

        {/* Scrollable Navigation Area */}
        <div className="sidebar-scroll flex-1 overflow-y-auto py-2">
          {/* Section 1: Home & Dashboard (Simple list without icons, matching image) */}
          <div className="space-y-0.5">
            {topSimpleLinks.map((item) => {
              const active =
                item.label === "Dashboard"
                  ? currentPath === "/admin/dashboard"
                  : currentPath === "/";
              const Icon = item.icon || LayoutDashboard;

              return (
                <a
                  key={item.label}
                  href={item.path}
                  onClick={(e) => {
                    e.preventDefault();
                    if (item.path) navigateTo(item.path);
                    handleNavigate();
                  }}
                  className={cn(
                    "group relative flex w-full items-center px-4 py-2 text-[13.5px] transition-all duration-150",
                    isCollapsed ? "justify-center px-2 py-2.5" : "text-left",
                    active
                      ? "bg-[#2762a9] text-[#f0cf65] font-medium"
                      : "text-slate-200 hover:bg-white/5 hover:text-white font-normal",
                  )}
                  title={isCollapsed ? item.label : undefined}
                >
                  {isCollapsed ? (
                    <Icon size={18} className={active ? "text-[#f0cf65]" : "text-slate-300"} />
                  ) : (
                    <span>{item.label}</span>
                  )}
                  {active && (
                    <span className="absolute inset-y-0 right-0 w-1.5 bg-[#0de3b4]" />
                  )}
                </a>
              );
            })}
          </div>

          {/* Divider line */}
          <div className="my-2.5 mx-3 border-t border-white/10" />

          {/* Section 2: Main Module Links with Icons */}
          <nav className="space-y-0.5">
            {mainModules.map((item) => {
              const active = isItemActive(item.path);
              const Icon = item.icon || ShieldCheck;

              return (
                <a
                  key={item.label}
                  href={item.path}
                  onClick={(e) => {
                    e.preventDefault();
                    if (item.path) navigateTo(item.path);
                    handleNavigate();
                  }}
                  className={cn(
                    "group relative flex w-full items-center gap-3 px-4 py-2.5 text-[13.5px] transition-all duration-150",
                    isCollapsed ? "justify-center px-2" : "text-left",
                    active
                      ? "bg-[#2762a9] text-[#f0cf65] font-medium"
                      : "text-slate-200/90 hover:bg-white/5 hover:text-white font-normal",
                  )}
                  title={isCollapsed ? item.label : undefined}
                >
                  <Icon
                    size={18}
                    strokeWidth={1.9}
                    className={cn(
                      "shrink-0 transition-colors duration-150",
                      active ? "text-[#f0cf65]" : "text-slate-300/80 group-hover:text-white",
                    )}
                  />
                  {!isCollapsed && (
                    <span className="truncate leading-tight">{item.label}</span>
                  )}
                  {active && (
                    <span className="absolute inset-y-0 right-0 w-1.5 bg-[#0de3b4]" />
                  )}
                </a>
              );
            })}
          </nav>

          {/* Divider line */}
          <div className="my-2.5 mx-3 border-t border-white/10" />

          {/* Section 3: Expandable Registries & Administration */}
          <div className="space-y-0.5">
            {expandableSections.map((section) => {
              const isOpen = openGroups[section.label] ?? false;
              const hasActiveChild = section.children.some((child) =>
                isItemActive(child.path),
              );

              return (
                <div key={section.label}>
                  <button
                    type="button"
                    onClick={() => {
                      setOpenGroups((prev) => ({
                        ...prev,
                        [section.label]: !isOpen,
                      }));
                    }}
                    className={cn(
                      "group flex w-full items-center justify-between px-4 py-2 text-[13.5px] transition-all duration-150",
                      isCollapsed ? "justify-center px-2" : "text-left",
                      hasActiveChild
                        ? "text-[#f0cf65] font-medium"
                        : "text-slate-200/90 hover:bg-white/5 hover:text-white font-normal",
                    )}
                    title={isCollapsed ? section.label : undefined}
                  >
                    {!isCollapsed && (
                      <span className="truncate leading-tight">{section.label}</span>
                    )}
                    {!isCollapsed && (
                      <ChevronDown
                        size={15}
                        className={cn(
                          "shrink-0 text-slate-400 transition-transform duration-200",
                          isOpen && "rotate-180",
                        )}
                      />
                    )}
                  </button>

                  {/* Sub-items if expanded */}
                  {!isCollapsed && isOpen && (
                    <div className="space-y-0.5 bg-black/10 py-1 pl-4">
                      {section.children.map((subItem) => {
                        const subActive = isItemActive(subItem.path);
                        const SubIcon = subItem.icon || FileSearch2;

                        return (
                          <a
                            key={subItem.label}
                            href={subItem.path}
                            onClick={(e) => {
                              e.preventDefault();
                              if (subItem.path) {
                                navigateTo(subItem.path);
                              } else {
                                handleDraftClick(subItem);
                              }
                              handleNavigate();
                            }}
                            className={cn(
                              "group relative flex w-full items-center gap-2.5 px-3 py-1.5 text-[12.5px] transition-all duration-150",
                              subActive
                                ? "bg-[#2762a9] text-[#f0cf65] font-medium"
                                : "text-slate-300 hover:bg-white/5 hover:text-white font-normal",
                            )}
                          >
                            <SubIcon
                              size={15}
                              className={cn(
                                "shrink-0",
                                subActive ? "text-[#f0cf65]" : "text-slate-400 group-hover:text-white",
                              )}
                            />
                            <span className="truncate">{subItem.label}</span>
                            {subActive && (
                              <span className="absolute inset-y-0 right-0 w-1 bg-[#0de3b4]" />
                            )}
                          </a>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        {/* Section 4: Sign out at bottom */}
        <div className="mt-auto border-t border-white/10">
          <button
            type="button"
            onClick={() => setShowLogoutModal(true)}
            className={cn(
              "flex w-full items-center px-4 py-3 text-[13.5px] text-slate-300/90 transition hover:bg-white/5 hover:text-white",
              isCollapsed ? "justify-center px-2" : "gap-2.5 text-left",
            )}
            title="Sign out"
          >
            {isCollapsed ? (
              <LogOut size={18} />
            ) : (
              <span>Sign out</span>
            )}
          </button>
        </div>
      </aside>

      {/* Logout Confirmation Modal */}
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
              className="bg-red-600 hover:bg-red-700 text-white font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-80 disabled:cursor-not-allowed"
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
            Are you sure you want to sign out?
          </p>
          <p className="mt-1 text-sm text-slate-500">
            This will end your current session and redirect you to the login page.
          </p>
        </div>
      </Modal>
    </>
  );
}
