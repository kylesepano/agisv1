import { useCallback, useEffect, useState } from "react";
import { CalendarCheck2, RefreshCw } from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import AemsEntryConferenceWorkspace from "../../components/aems/AemsEntryConferenceWorkspace";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import { aemsEntryConferenceApi, ApiError } from "../../services/api";

/**
 * CMS-facing recipient portal for reading and acknowledging Entry Conference
 * notes. The shared workspace keeps the backend scope and acknowledgement
 * rules authoritative while its AEMS management controls remain permissioned.
 */
export default function CmsEntryConferenceAcknowledgementsPage() {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(
    searchParams.get("engagementId") ?? "",
  );
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const loadEngagements = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const records = await aemsEntryConferenceApi.engagements();
      setEngagements(records);
      const requestedId = searchParams.get("engagementId");
      const requestedExists = records.some(
        (record) => String(record.id) === String(requestedId),
      );
      setEngagementId(
        requestedExists ? String(requestedId) : String(records[0]?.id ?? ""),
      );
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : "Unable to load Entry Conference records.",
      );
    } finally {
      setLoading(false);
    }
  }, [searchParams]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  function selectEngagement(value) {
    const next = String(value ?? "");
    setEngagementId(next);
    setSearchParams(next ? { engagementId: next } : {});
  }

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        description="Review the Entry Conference notes issued to your office and record your acknowledgement from the CMS recipient portal."
        icon={CalendarCheck2}
        title="Entry Conference Acknowledgements"
        actions={
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
            onClick={loadEngagements}
            type="button"
          >
            <RefreshCw size={15} /> Refresh
          </button>
        }
      />

      <section className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-900">
        <strong>{user?.name ?? "Recipient"}</strong>, this workspace is limited
        to Entry Conferences for engagements assigned to your office. You can
        read the official notes and acknowledge them; preparation, editing,
        scheduling, and workflow management remain with the audit team.
      </section>

      <section className="mt-5 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div className="min-w-0 flex-1">
          <label className="mb-1.5 block text-sm font-semibold text-slate-700">
            Engagement / Entry Conference
          </label>
          <SearchableSelect
            disabled={loading}
            onChange={selectEngagement}
            options={engagements.map((engagement) => ({
              value: engagement.id,
              label: `${engagement.engagementCode} — ${engagement.title}`,
              keywords: `${engagement.status} ${engagement.entryConferenceStatus ?? ""}`,
            }))}
            placeholder={
              loading ? "Loading Entry Conferences…" : "Select an engagement"
            }
            value={engagementId}
          />
        </div>
      </section>

      {error && (
        <div className="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      {engagementId ? (
        <div className="mt-5">
          <AemsEntryConferenceWorkspace engagementId={engagementId} />
        </div>
      ) : (
        !loading && (
          <div className="mt-5 rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            No Entry Conference notes are currently available for your office.
          </div>
        )
      )}
    </main>
  );
}
