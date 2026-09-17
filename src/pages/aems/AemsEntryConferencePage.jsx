import { useCallback, useEffect, useState } from "react";
import { ArrowLeft, CalendarCheck2, RefreshCw } from "lucide-react";
import { Link, useParams, useSearchParams } from "react-router";
import AemsEntryConferenceWorkspace from "../../components/aems/AemsEntryConferenceWorkspace";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import { aemsEntryConferenceApi } from "../../services/api";

/** Office-scoped Entry Conference surface used by invited auditee representatives. */
export default function AemsEntryConferencePage() {
  const { engagementId: routeEngagementId } = useParams();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(
    routeEngagementId ?? params.get("engagementId") ?? "",
  );
  const [loading, setLoading] = useState(!routeEngagementId);
  const [error, setError] = useState("");

  const loadEngagements = useCallback(async () => {
    if (routeEngagementId) return;
    setLoading(true);
    setError("");
    try {
      const records = await aemsEntryConferenceApi.engagements();
      setEngagements(records);
      setEngagementId((current) => current || String(records[0]?.id ?? ""));
    } catch (reason) {
      setError(reason.message);
    } finally {
      setLoading(false);
    }
  }, [routeEngagementId]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  function selectEngagement(value) {
    const next = String(value ?? "");
    setEngagementId(next);
    setParams(next ? { engagementId: next } : {});
  }

  return (
    <main className="min-w-0 p-4 sm:p-5">
      {routeEngagementId && (
        <Link
          className="mb-4 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"
          to="/dashboard"
        >
          <ArrowLeft size={17} />
          Dashboard
        </Link>
      )}
      <RegistryHeader
        description="Prepare, conduct, acknowledge, complete, or formally waive the official pre-fieldwork conference."
        icon={CalendarCheck2}
        title="Entry Conferences"
      />

      {!routeEngagementId && (
        <section className="mb-5 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
          <div className="min-w-0 flex-1">
            <SearchableSelect
              disabled={loading}
              onChange={selectEngagement}
              options={engagements.map((engagement) => ({
                value: engagement.id,
                label: `${engagement.engagementCode} — ${engagement.title}`,
                keywords: `${engagement.status} ${engagement.entryConferenceStatus ?? ""}`,
              }))}
              placeholder={
                loading ? "Loading engagements…" : "Select an engagement"
              }
              value={engagementId}
            />
          </div>
          <button
            className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-sky-300 px-4 text-sm font-bold text-sky-700 hover:bg-sky-50"
            onClick={loadEngagements}
            type="button"
          >
            <RefreshCw size={16} /> Refresh
          </button>
        </section>
      )}

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}
      {engagementId ? (
        <AemsEntryConferenceWorkspace engagementId={engagementId} />
      ) : (
        !loading && (
          <div className="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            No Entry Conference engagement is available to your current role and
            office scope.
          </div>
        )
      )}
    </main>
  );
}
