import { History } from "lucide-react";
import LogRegistryPage from "../../components/LogRegistryPage";

/**
 * Displays immutable before-and-after change history using the shared log
 * registry configured for audit-trail fields and exports.
 */
export default function AuditTrailPage() {
  return <LogRegistryPage icon={History} mode="audit" />;
}
