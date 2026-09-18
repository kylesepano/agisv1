import { useCallback, useEffect, useMemo, useState } from "react";
import { ArrowLeft, Building2, RefreshCw } from "lucide-react";
import { Link, useNavigate, useSearchParams } from "react-router";
import AemsScopeWorkspace from "../../components/aems/AemsScopeWorkspace";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import { aemsEngagementApi } from "../../services/api";

/** Standalone SCR-212 workspace for the scope portion of an engagement draft. */
export default function AemsEngagementScopePage() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const engagementId = params.get("engagementId");
  const [engagement, setEngagement] = useState(null);
  const [engagements, setEngagements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      if (!engagementId) {
        const data = await aemsEngagementApi.list({
          perPage: 100,
          sortBy: "updated_at",
          sortDirection: "desc",
        });
        setEngagements(data.engagements ?? []);
        setEngagement(null);
      } else {
        setEngagement(await aemsEngagementApi.show(engagementId));
      }
    } catch (reason) {
      setError(reason?.message ?? "The engagement could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, [engagementId]);

  const engagementOptions = useMemo(
    () =>
      engagements
        .filter((item) => !item.isArchived)
        .map((item) => ({
          value: item.id,
          label: `${item.engagementCode} — ${item.title}`,
          keywords: `${item.status} ${item.sourceType ?? ""}`,
          description: `${item.statusLabel ?? item.status} · ${item.offices?.[0]?.name ?? "Office not selected"}`,
        })),
    [engagements],
  );

  useEffect(() => {
    // This effect intentionally refreshes server state when the selected
    // engagement changes; the loader owns the loading/error state.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  return (
    <div className="mx-auto w-full max-w-[1600px] p-4 sm:p-6 lg:p-8">
      <RegistryHeader
        icon={Building2}
        title="Engagement Scope"
        description="Select one office, scoped audit areas, and applicable audit focuses before team assignment and authorization."
        actions={
          <div className="flex flex-wrap gap-2">
            <Link className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50" to="/audit-engagement-management">
              <ArrowLeft size={16} /> Audit Engagement Workspace
            </Link>
            <button className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50" onClick={load} type="button">
              <RefreshCw size={16} /> Refresh
            </button>
          </div>
        }
      />
      {error && <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{error}</div>}
      {loading ? (
        <div className="grid min-h-56 place-items-center rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-500">Loading engagement scope…</div>
      ) : !engagementId ? (
        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-base font-bold text-slate-900">
            Select an engagement
          </h2>
          <p className="mt-1 text-sm leading-6 text-slate-600">
            Choose a Draft or active engagement to define its SCR-212 scope.
            Scope completion is required before the Audit Team can be assigned.
          </p>
          <div className="mt-4 max-w-2xl">
            <SearchableSelect
              onChange={(value) =>
                navigate(
                  `/audit-engagement-management/scope?engagementId=${value}`,
                )
              }
              options={engagementOptions}
              placeholder="Select an engagement"
              searchPlaceholder="Search code, title, status, or office..."
              value=""
            />
          </div>
          {!engagementOptions.length && (
            <p className="mt-4 rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm font-semibold text-slate-500">
              No accessible engagements are available for scope maintenance.
            </p>
          )}
        </section>
      ) : engagement ? (
        <AemsScopeWorkspace
          engagementId={engagement.id}
          initialEngagement={engagement}
        />
      ) : null}
    </div>
  );
}
