import { Plus } from "lucide-react";
import { Button } from "../../../../components/ui/button";

export function UsersHeader() {
  return (
    <header className="flex items-start justify-between gap-4 max-[700px]:flex-col">
      <div>
        <h1 className="text-[2.2rem] font-bold tracking-[-0.04em] text-slate-950">
          Users
        </h1>
        <p className="mt-1 text-sm text-slate-600">
          Manage system users and their access.
        </p>
      </div>

      <Button className="h-11 rounded-xl bg-[#2563eb] px-4 hover:bg-[#1d4ed8] focus-visible:ring-blue-300">
        <Plus size={16} />
        Add New User
      </Button>
    </header>
  );
}
