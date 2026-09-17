import { useCallback, useEffect, useState } from "react";
import {
  Archive,
  Database,
  LockKeyhole,
  RefreshCw,
  Search,
  ShieldCheck,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsClosureApi } from "../../services/api";

export default function AemsRecordsWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [query, setQuery] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const load = useCallback(
    async (value = query) => {
      setError("");
      try {
        setData(await aemsClosureApi.records(engagementId, value));
      } catch (reason) {
        setError(reason.message);
      }
    },
    [engagementId, query],
  );
  useEffect(() => {
    const timer = window.setTimeout(() => void load(""), 0);
    return () => window.clearTimeout(timer);
  }, [load]);
  async function act(operation) {
    setBusy(true);
    setError("");
    try {
      await operation();
      await load();
    } catch (reason) {
      setError(reason.message);
    } finally {
      setBusy(false);
    }
  }
  if (!data)
    return (
      <div className="grid min-h-64 place-items-center">
        <RefreshCw className="animate-spin text-sky-700" size={26} />
      </div>
    );
  const retention = data.retention;
  const canReview = hasPermission(user, "aems.retention.destruction_review");
  const canArchive = hasPermission(user, "aems.retention.archive");
  const canRelease = hasPermission(user, "aems.retention.legal_hold_release");
  const canDisposition = hasPermission(
    user,
    "aems.retention.disposition_execute",
  );
  return (
    <div className="space-y-5" data-testid="aems-records-workspace">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex items-start gap-3">
          <span className="grid h-10 w-10 place-items-center rounded-xl bg-sky-50 text-sky-700">
            <Database size={20} />
          </span>
          <div>
            <h3 className="font-bold text-slate-900">
              Records and Retention Monitoring
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              Search the final document index and control archive, legal hold,
              and disposition decisions. Core DocumentVersions are never
              physically deleted here.
            </p>
          </div>
        </div>
        {error && (
          <p className="mt-3 rounded-lg bg-rose-50 p-3 text-sm font-semibold text-rose-700">
            {error}
          </p>
        )}
        <div className="mt-4 flex gap-2">
          <div className="relative flex-1">
            <Search
              className="absolute left-3 top-2.5 text-slate-400"
              size={17}
            />
            <input
              className="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm"
              placeholder="Search reference, title, record type"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && void load()}
            />
          </div>
          <button
            className="rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
            onClick={() => void load()}
            type="button"
          >
            Search
          </button>
        </div>
      </section>
      {data.blockers.length > 0 && (
        <section className="rounded-xl border border-rose-200 bg-rose-50 p-4">
          <h3 className="font-bold text-rose-800">Closure blockers</h3>
          <ul className="mt-2 list-disc pl-5 text-sm text-rose-700">
            {data.blockers.map((item) => (
              <li key={item.code}>{item.description}</li>
            ))}
          </ul>
        </section>
      )}
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="font-bold text-slate-800">Retention control</h3>
            <p className="text-sm text-slate-500">
              Archive: {retention?.archiveStatus || "ACTIVE"} · Destruction
              review:{" "}
              {retention?.destructionEligibilityStatus || "NOT_REVIEWED"}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {retention && canRelease && retention.legalHoldFlag && (
              <button
                className="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white"
                disabled={busy}
                onClick={() => {
                  const reason = window.prompt(
                    "Reason for releasing the legal hold",
                  );
                  if (reason)
                    void act(() =>
                      aemsClosureApi.releaseLegalHold(
                        engagementId,
                        retention.id,
                        { reason },
                      ),
                    );
                }}
                type="button"
              >
                <ShieldCheck size={14} className="mr-1 inline" /> Release hold
              </button>
            )}
            {retention && canReview && (
              <button
                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700"
                disabled={busy}
                onClick={() => {
                  const reason = window.prompt(
                    "Reason for the destruction-eligibility review",
                  );
                  if (reason)
                    void act(() =>
                      aemsClosureApi.reviewDestruction(
                        engagementId,
                        retention.id,
                        { reason },
                      ),
                    );
                }}
                type="button"
              >
                Review destruction
              </button>
            )}
            {retention &&
              canArchive &&
              retention.archiveStatus !== "ARCHIVED" && (
                <button
                  className="rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white"
                  disabled={busy}
                  onClick={() => {
                    const reason = window.prompt("Reason for archival");
                    if (reason)
                      void act(() =>
                        aemsClosureApi.archiveRetention(
                          engagementId,
                          retention.id,
                          { reason },
                        ),
                      );
                  }}
                  type="button"
                >
                  <Archive size={14} className="mr-1 inline" /> Archive
                </button>
              )}
            {retention &&
              canDisposition &&
              retention.destructionEligibilityStatus === "ELIGIBLE" && (
                <button
                  className="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white"
                  disabled={busy}
                  onClick={() => {
                    const reason = window.prompt(
                      "Reason for recording disposition",
                    );
                    const reference = window.prompt("Disposition reference");
                    if (reason && reference)
                      void act(() =>
                        aemsClosureApi.recordDisposition(
                          engagementId,
                          retention.id,
                          { reason, reference },
                        ),
                      );
                  }}
                  type="button"
                >
                  Record disposition
                </button>
              )}
          </div>
        </div>
        {retention?.legalHoldFlag && (
          <p className="mt-3 flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
            <LockKeyhole size={16} /> Active legal hold blocks archive and
            disposition.
          </p>
        )}
      </section>
      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h3 className="font-bold text-slate-800">
            Indexed records ({data.items.length})
          </h3>
          <button
            className="rounded-lg border border-slate-300 p-2"
            onClick={() => void load()}
            type="button"
          >
            <RefreshCw size={15} />
          </button>
        </div>
        <div className="divide-y divide-slate-100">
          {data.items.length === 0 && (
            <p className="p-5 text-sm text-slate-500">
              No indexed records match this search.
            </p>
          )}
          {data.items.map((item) => (
            <div
              className="flex items-center justify-between gap-3 p-4"
              key={item.id}
            >
              <div>
                <p className="text-xs font-bold uppercase text-slate-400">
                  {item.recordType} · {item.referenceCode}
                </p>
                <p className="font-semibold text-slate-800">{item.title}</p>
                <p className="text-xs text-slate-500">
                  DocumentVersion #{item.documentVersionId} ·{" "}
                  {item.confidentialityCode}
                </p>
              </div>
              <span
                className={`rounded-full px-2 py-1 text-[11px] font-bold ${item.includedFlag ? "bg-emerald-50 text-emerald-700" : "bg-slate-100 text-slate-500"}`}
              >
                {item.includedFlag ? "Included" : "Excluded"}
              </span>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}
