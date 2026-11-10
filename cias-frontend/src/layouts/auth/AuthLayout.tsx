import type { ReactNode } from "react";

type AuthLayoutProps = {
  children: ReactNode;
};

export function AuthLayout({ children }: AuthLayoutProps) {
  return <div className="min-h-screen bg-[#0d5fa8]">{children}</div>;
}
