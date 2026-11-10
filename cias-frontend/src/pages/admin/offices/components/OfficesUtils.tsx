import { Badge } from "../../../../components/ui/badge";
import type { OfficeRecord } from "../offices-types";

export function OfficeStatusBadge({
  status,
}: {
  status: OfficeRecord["status"];
}) {
  return (
    <Badge tone={status === "Active" ? "success" : "neutral"}>{status}</Badge>
  );
}
