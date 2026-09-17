import { useCallback, useEffect, useState } from "react";
import { CalendarClock, RefreshCw } from "lucide-react";
import { useSearchParams } from "react-router";
import { aemsClosureApi, aemsEngagementApi } from "../../services/api";
import RegistryHeader from "../../components/ui/RegistryHeader";
import AemsCalendarWorkspace from "../../components/aems/AemsCalendarWorkspace";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";

export default function AemsCalendarPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    searchParams.get("engagementId") || "",
  );
  const [summary, setSummary] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  const loadEngagements = useCallback(async () => {
    try {
      const result = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(result.engagements || []);
      setSelectedId(
        (current) => current || String(result.engagements?.[0]?.id || ""),
      );
    } catch (reason) {
      setError(reason.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => void loadEngagements(), 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);
  useEffect(() => {
    if (selectedId)
      setSearchParams({ engagementId: selectedId }, { replace: true });
  }, [selectedId, setSearchParams]);
  useEffect(() => {
    if (!selectedId) return undefined;
    let active = true;
    aemsClosureApi
      .calendar(selectedId)
      .then((data) => {
        if (active) setSummary(data?.summary || null);
      })
      .catch((reason) => {
        if (active) setError(reason.message);
      });
    return () => {
      active = false;
    };
  }, [selectedId]);

  const selectedEngagement = engagements.find(
    (item) => String(item.id) === String(selectedId),
  );
  const completionGate = getAemsWorkspaceGate("completion", selectedEngagement);

  return (
    <div className="min-w-0" data-testid="aems-calendar-page">
      <RegistryHeader
        icon={CalendarClock}
        title="Audit Calendar and Milestones"
        description="Review milestone dates, overdue blockers, responsible owners, and completion status across an authorized engagement."
        actions={
          <button
            className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
            disabled={loading}
            onClick={() => {
              setLoading(true);
              void loadEngagements();
            }}
            type="button"
          >
            <RefreshCw className={loading ? "animate-spin" : ""} size={16} />{" "}
            Refresh
          </button>
        }
      />
      {error && (
        <div
          className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800"
          role="alert"
        >
          {error}
        </div>
      )}
      <section className="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
          Engagement scope
          <select
            className="mt-2 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800"
            value={selectedId}
            onChange={(event) => setSelectedId(event.target.value)}
          >
            <option value="">Select an engagement</option>
            {engagements.map((item) => (
              <option key={item.id} value={item.id}>
                {item.engagementCode} — {item.title}
              </option>
            ))}
          </select>
        </label>
      </section>
      {selectedId && !completionGate.unlocked ? (
        <AemsWorkspaceLockNotice engagementId={selectedId} gate={completionGate} />
      ) : selectedId ? (
        <>
          <div className="mb-5 grid gap-3 sm:grid-cols-3">
            {[
              ["total", "Registered milestones"],
              ["overdue", "Overdue"],
              ["completed", "Completed"],
            ].map(([key, label]) => (
              <div
                className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                key={key}
              >
                <strong className="block text-2xl text-slate-900">
                  {summary?.[key] ?? "—"}
                </strong>
                <span className="text-xs font-bold uppercase tracking-wide text-slate-500">
                  {label}
                </span>
              </div>
            ))}
          </div>
          <AemsCalendarWorkspace engagementId={selectedId} />
        </>
      ) : (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center text-sm text-slate-500">
          No authorized engagements are available in your scope.
        </div>
      )}
    </div>
  );
}
