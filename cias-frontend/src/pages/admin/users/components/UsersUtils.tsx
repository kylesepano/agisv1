import { Badge } from "../../../../components/ui/badge";
import { cn } from "../../../../utils/helpers";
import type { UserRecord, UserRole, UserStatus } from "../users-types";

const roleClasses: Record<UserRole, string> = {
  Administrator: "bg-blue-100 text-blue-700",
  "Compliance Officer": "bg-sky-100 text-sky-700",
  "Senior Auditor": "bg-violet-100 text-violet-700",
  "Audit Team Leader": "bg-amber-100 text-amber-700",
  Auditor: "bg-emerald-100 text-emerald-700",
  "Audit Staff": "bg-rose-100 text-rose-700",
  Viewer: "bg-slate-100 text-slate-700",
  "Data Encoder": "bg-orange-100 text-orange-700",
};

const statusTone: Record<UserStatus, string> = {
  Active: "bg-emerald-100 text-emerald-700",
  Inactive: "bg-rose-100 text-rose-700",
};

const avatarTone = [
  "bg-blue-100 text-blue-700",
  "bg-emerald-100 text-emerald-700",
  "bg-violet-100 text-violet-700",
  "bg-amber-100 text-amber-700",
  "bg-cyan-100 text-cyan-700",
  "bg-rose-100 text-rose-700",
];

export function getUserInitials(name: string) {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");
}

export function getUserAvatarClass(record: UserRecord) {
  const seed = record.name.length + record.username.length;
  return avatarTone[seed % avatarTone.length];
}

export function UserRoleBadge({ role }: { role: UserRole }) {
  return (
    <Badge className={cn("font-semibold", roleClasses[role])} tone="neutral">
      {role}
    </Badge>
  );
}

export function UserStatusBadge({ status }: { status: UserStatus }) {
  return (
    <Badge className={cn("font-semibold", statusTone[status])} tone="neutral">
      {status}
    </Badge>
  );
}
