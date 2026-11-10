import { Badge } from "../../../../components/ui/badge";
import { cn } from "../../../../utils/helpers";
import type { CategoryRecord } from "../categories-types";

const scopeTone: Record<CategoryRecord["scope"], string> = {
  Department: "bg-blue-100 text-blue-700",
  Process: "bg-emerald-100 text-emerald-700",
  System: "bg-violet-100 text-violet-700",
  Fund: "bg-amber-100 text-amber-700",
  Program: "bg-indigo-100 text-indigo-700",
  Project: "bg-rose-100 text-rose-700",
  Asset: "bg-slate-100 text-slate-700",
};

export function CategoryScopeBadge({
  scope,
}: {
  scope: CategoryRecord["scope"];
}) {
  return (
    <Badge className={cn(scopeTone[scope])} tone="neutral">
      {scope}
    </Badge>
  );
}

export function CategoryStatusBadge({
  status,
}: {
  status: CategoryRecord["status"];
}) {
  return (
    <Badge tone={status === "Active" ? "success" : "neutral"}>{status}</Badge>
  );
}
