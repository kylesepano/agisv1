import { CheckCircle2, ChevronRight, History, Layers3 } from "lucide-react";
import StatusBadge from "../ui/StatusBadge";
import { CmsActionPlanStatusBadge } from "./CmsBadges";

function displayDate(value) {
  if (!value) return "Not recorded";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not recorded";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
  }).format(date);
}

export default function CmsActionPlanVersionHistory({
  versions,
  selectedVersionId,
  onSelect,
}) {
  return (
    <section
      aria-labelledby="action-plan-version-history"
      className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
    >
      <div className="flex items-start gap-3">
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-700">
          <History size={18} />
        </span>
        <div>
          <h3
            className="font-bold text-slate-800"
            id="action-plan-version-history"
          >
            Version history
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            Select any retained version to inspect it without changing history.
          </p>
        </div>
      </div>

      {versions.length === 0 ? (
        <p className="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-8 text-center text-sm text-slate-500">
          No previous versions are available.
        </p>
      ) : (
        <div className="mt-4 grid gap-2">
          {versions.map((version) => {
            const selected = Number(selectedVersionId) === Number(version.id);
            return (
              <button
                aria-current={selected ? "true" : undefined}
                className={`w-full rounded-xl border p-3 text-left transition focus-visible:outline-2 focus-visible:outline-sky-600 ${
                  selected
                    ? "border-sky-400 bg-sky-50"
                    : "border-slate-200 bg-white hover:border-sky-200 hover:bg-slate-50"
                }`}
                key={version.id}
                onClick={() => onSelect(version.id)}
                type="button"
              >
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="font-bold text-slate-800">
                      Version {version.versionNumber}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">
                      Created {displayDate(version.createdAt)}
                    </p>
                  </div>
                  <ChevronRight
                    className={selected ? "text-sky-700" : "text-slate-400"}
                    size={17}
                  />
                </div>
                <div className="mt-2 flex flex-wrap gap-1.5">
                  <CmsActionPlanStatusBadge status={version.status} />
                  {version.isCurrent && (
                    <StatusBadge tone="info">
                      <Layers3 aria-hidden="true" className="mr-1" size={12} />
                      Current version
                    </StatusBadge>
                  )}
                  {version.isAcceptedCurrent && (
                    <StatusBadge tone="success">
                      <CheckCircle2
                        aria-hidden="true"
                        className="mr-1"
                        size={12}
                      />
                      Accepted baseline
                    </StatusBadge>
                  )}
                  {version.isSuperseded && (
                    <StatusBadge tone="warning">
                      Superseded baseline
                    </StatusBadge>
                  )}
                </div>
                {version.previousVersionId && (
                  <p className="mt-2 text-xs text-slate-500">
                    Revised from version record #{version.previousVersionId}
                  </p>
                )}
              </button>
            );
          })}
        </div>
      )}
    </section>
  );
}
