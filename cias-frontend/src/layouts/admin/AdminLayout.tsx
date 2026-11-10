import { useEffect, useState, type ReactNode } from "react";
import { PageTransition } from "../../components/shared/PageTransition";
import { useCurrentPath } from "../../routes";
import { cn } from "../../utils/helpers";

import { AdminNavbar } from "./components/AdminNavbar";
import { AdminSidebar } from "./components/AdminSidebar";

const ADMIN_SIDEBAR_COLLAPSED_KEY = "admin-sidebar-collapsed";

type AdminLayoutProps = {
  children: ReactNode;
};

export function AdminLayout({ children }: AdminLayoutProps) {
  const [isMobile, setIsMobile] = useState(window.innerWidth < 960);
  const [isSidebarOpen, setIsSidebarOpen] = useState(window.innerWidth >= 960);
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(() => {
    return window.localStorage.getItem(ADMIN_SIDEBAR_COLLAPSED_KEY) === "true";
  });
  const path = useCurrentPath();

  useEffect(() => {
    window.localStorage.setItem(
      ADMIN_SIDEBAR_COLLAPSED_KEY,
      String(isSidebarCollapsed),
    );
  }, [isSidebarCollapsed]);

  useEffect(() => {
    const handleResize = () => {
      const mobile = window.innerWidth < 960;

      setIsMobile(mobile);

      if (mobile) {
        setIsSidebarOpen(false);
      } else {
        setIsSidebarOpen(true);
      }
    };

    window.addEventListener("resize", handleResize);

    return () => {
      window.removeEventListener("resize", handleResize);
    };
  }, []);

  return (
    <div className="relative flex h-screen overflow-hidden bg-[#eaf1fb]">
      <div
        className={cn(
          "overflow-hidden transition-all duration-300 ease-in-out",
          isMobile
            ? "pointer-events-none absolute inset-y-0 left-0 z-30 w-[min(84vw,300px)]"
            : isSidebarCollapsed
              ? "w-[64px] translate-x-0 opacity-100"
              : "w-[280px] translate-x-0 opacity-100",
          isMobile &&
            (isSidebarOpen
              ? "pointer-events-auto translate-x-0 opacity-100"
              : "-translate-x-full opacity-0"),
        )}
      >
        <AdminSidebar
          isOpen={isSidebarOpen}
          isCollapsed={!isMobile && isSidebarCollapsed}
          onClose={() => setIsSidebarOpen(false)}
        />
      </div>

      <div className="relative flex min-w-0 flex-1 flex-col overflow-hidden bg-[#eef4fb]">
        <AdminNavbar
          onMenuToggle={() => {
            if (isMobile) {
              setIsSidebarOpen((prev) => !prev);
              return;
            }

            setIsSidebarCollapsed((prev) => !prev);
          }}
        />

        <main className="flex-1 overflow-y-auto px-3.5 pb-1.5 pt-2 max-[960px]:px-2.5 max-[960px]:pb-1 max-[960px]:pt-2">
          <PageTransition transitionKey={path}>{children}</PageTransition>
        </main>

        {isSidebarOpen && isMobile && (
          <button
            aria-label="Close sidebar overlay"
            className="absolute inset-0 z-10 bg-black/40"
            onClick={() => setIsSidebarOpen(false)}
          />
        )}
      </div>
    </div>
  );
}
