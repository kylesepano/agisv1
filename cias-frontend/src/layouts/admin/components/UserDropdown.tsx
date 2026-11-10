import {
  ChevronDown,
  ChevronRight,
  CircleHelp,
  LogOut,
  Settings,
  User,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { getStoredUser } from "../../../lib/auth";
import { cn } from "../../../utils/helpers";

type UserDropdownProps = {
  onProfileSelect?: () => void;
  onSettingsSelect?: () => void;
  onLogoutSelect?: () => void;
};

export function UserDropdown({
  onProfileSelect,
  onSettingsSelect,
  onLogoutSelect,
}: UserDropdownProps) {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement | null>(null);
  const currentUser = getStoredUser();
  const displayName = currentUser?.name || currentUser?.username || "Admin User";
  const displayRole = currentUser?.role || "Administrator";

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const handlePointerDown = (event: MouseEvent) => {
      if (!dropdownRef.current?.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setIsOpen(false);
      }
    };

    document.addEventListener("mousedown", handlePointerDown);
    window.addEventListener("keydown", handleKeyDown);

    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [isOpen]);

  const menuItems = [
    {
      label: "Profile",
      description: "View your personal information",
      icon: User,
      onSelect: onProfileSelect,
      tone: "default" as const,
    },
    {
      label: "Settings & privacy",
      description: "Manage your account settings",
      icon: Settings,
      onSelect: onSettingsSelect,
      tone: "default" as const,
    },
    {
      label: "Help & support",
      description: "Get help and see FAQs",
      icon: CircleHelp,
      onSelect: undefined,
      tone: "default" as const,
    },
    {
      label: "Logout",
      description: "Sign out of your account",
      icon: LogOut,
      onSelect: onLogoutSelect,
      tone: "danger" as const,
    },
  ];

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        aria-expanded={isOpen}
        className={cn(
          "flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1 transition hover:bg-white/10 active:bg-white/15",
          isOpen && "bg-white/15",
        )}
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
      >
        <div className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#1b508e] text-white border border-white/40 shadow-xs">
          <User size={18} className="fill-white text-white" />
        </div>

        <div className="hidden text-left sm:block">
          <div className="flex items-center gap-1.5">
            <span className="text-[13px] font-medium leading-tight text-white">
              {displayName}
            </span>
            <ChevronDown
              size={14}
              className={cn(
                "text-white/85 transition-transform duration-200",
                isOpen && "rotate-180",
              )}
            />
          </div>
          <p className="text-[11px] leading-tight text-blue-100/80 font-normal">
            {displayRole}
          </p>
        </div>
      </button>

      {isOpen ? (
        <div className="absolute right-0 top-[calc(100%+0.55rem)] z-50 w-[248px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_20px_60px_rgba(15,23,42,0.16)] animate-[page-enter_180ms_cubic-bezier(0.22,1,0.36,1)]">
          <div className="px-3.5 py-3.5">
            <div className="flex items-start gap-3">
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#1b508e] text-white border border-white/40 shadow-xs">
                <User size={20} className="fill-white text-white" />
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-[0.95rem] font-semibold text-slate-900">
                  {displayName}
                </p>
                <p className="truncate text-xs text-slate-500">
                  {displayRole}
                </p>
                <button
                  className="mt-0.5 inline-flex items-center gap-1.5 text-[13px] font-medium text-blue-600 transition hover:text-blue-700"
                  type="button"
                  onClick={() => {
                    setIsOpen(false);
                    onProfileSelect?.();
                  }}
                >
                  <span className="h-2 w-2 rounded-full bg-emerald-500" />
                  View your profile
                </button>
              </div>
            </div>
          </div>

          <div className="border-t border-slate-100 px-2 py-1.5">
            {menuItems.map((item, index) => {
              const Icon = item.icon;
              const showDividerBefore = index === 3;

              return (
                <div key={item.label}>
                  {showDividerBefore ? (
                    <div className="mx-2 my-1 border-t border-slate-100" />
                  ) : null}
                  <button
                    className={cn(
                      "flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left transition-all duration-200 hover:bg-slate-50",
                      item.tone === "danger"
                        ? "text-slate-700 hover:bg-rose-50"
                        : "text-slate-700",
                    )}
                    type="button"
                    onClick={() => {
                      setIsOpen(false);
                      item.onSelect?.();
                    }}
                  >
                    <div
                      className={cn(
                        "flex h-8 w-8 shrink-0 items-center justify-center rounded-full",
                        item.tone === "danger"
                          ? "bg-rose-50 text-rose-600"
                          : "bg-slate-100 text-slate-600",
                      )}
                    >
                      <Icon size={16} />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p
                        className={cn(
                          "text-[12.5px] font-semibold",
                          item.tone === "danger" && "text-slate-800",
                        )}
                      >
                        {item.label}
                      </p>
                      <p
                        className={cn(
                          "mt-0.5 text-[11px] leading-4",
                          item.tone === "danger"
                            ? "text-slate-500"
                            : "text-slate-500",
                        )}
                      >
                        {item.description}
                      </p>
                    </div>
                    <ChevronRight size={16} className="shrink-0 text-slate-300" />
                  </button>
                </div>
              );
            })}
          </div>
        </div>
      ) : null}
    </div>
  );
}
