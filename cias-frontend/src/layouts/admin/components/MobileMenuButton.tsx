import { Menu } from "lucide-react";

type MobileMenuButtonProps = {
  onClick: () => void;
};

export function MobileMenuButton({ onClick }: MobileMenuButtonProps) {
  return (
    <button
      aria-label="Toggle sidebar"
      className="grid h-10 w-10 place-items-center rounded-xl text-slate-700 transition hover:bg-slate-100"
      type="button"
      onClick={onClick}
    >
      <Menu size={22} strokeWidth={2} />
    </button>
  );
}
