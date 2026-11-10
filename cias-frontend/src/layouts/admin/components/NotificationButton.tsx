import { Bell } from "lucide-react";

export function NotificationButton() {
  return (
    <button
      aria-label="Notifications"
      className="relative grid h-10 w-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100"
      type="button"
    >
      <Bell size={22} strokeWidth={2} />

      <span className="absolute right-0 top-0 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
        5
      </span>
    </button>
  );
}
