import { Search } from "lucide-react";

export function SearchButton() {
  return (
    <button
      aria-label="Search"
      className="grid h-10 w-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100"
      type="button"
    >
      <Search size={22} strokeWidth={2} />
    </button>
  );
}
