import { Activity } from "lucide-react";
import LogRegistryPage from "../../components/LogRegistryPage";

/**
 * Presents operational activity records through the shared log registry with
 * activity-specific filters and export behavior.
 */
export default function ActivityLogPage() {
  return <LogRegistryPage icon={Activity} mode="activity" />;
}
