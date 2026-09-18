import { Clock3, UserRound } from "lucide-react";
import { labelFor } from "./cms-format";

const EVENT_LABELS = {
  INTAKE_CREATED: "Recommendation transferred to CMS",
  COMPLIANCE_MONITOR_ASSIGNED: "Compliance Monitor assigned",
  COMPLIANCE_MONITOR_REPLACED: "Compliance Monitor replaced",
  COMPLIANCE_MONITOR_ASSIGNMENT_ENDED: "Compliance Monitor assignment ended",
};

function formatDateTime(value) {
  if (!value) return "Date unavailable";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Date unavailable";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

export default function CmsRecommendationTimeline({ events = [] }) {
  if (events.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
        No recommendation history is available.
      </div>
    );
  }

  return (
    <ol className="relative ml-2 border-l border-slate-200">
      {events.map((event) => (
        <li className="relative pb-7 pl-7 last:pb-0" key={event.id}>
          <span className="absolute -left-2 top-0 grid h-4 w-4 place-items-center rounded-full bg-sky-600 ring-4 ring-white" />
          <p className="font-semibold text-slate-800">
            {EVENT_LABELS[event.eventCode] || labelFor(event.eventCode)}
          </p>
          <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <span className="inline-flex items-center gap-1">
              <Clock3 size={13} aria-hidden="true" />
              {formatDateTime(event.createdAt)}
            </span>
            <span className="inline-flex items-center gap-1">
              <UserRound size={13} aria-hidden="true" />
              {event.actor?.name || "System"}
            </span>
          </div>
          {(event.previousStatus || event.newStatus) && (
            <p className="mt-2 text-sm text-slate-600">
              {labelFor(event.previousStatus, "Created")} →{" "}
              {labelFor(event.newStatus, "No status change")}
            </p>
          )}
          {event.metadata && Object.keys(event.metadata).length > 0 && (
            <details className="mt-2 text-xs text-slate-500">
              <summary className="cursor-pointer font-semibold text-sky-700">
                Technical details
              </summary>
              <dl className="mt-2 grid gap-1 rounded-lg bg-slate-50 p-3">
                {Object.entries(event.metadata)
                  .filter(([, value]) =>
                    ["string", "number", "boolean"].includes(typeof value),
                  )
                  .map(([key, value]) => (
                    <div className="flex gap-2" key={key}>
                      <dt className="font-semibold">{labelFor(key)}:</dt>
                      <dd className="break-all">{String(value)}</dd>
                    </div>
                  ))}
              </dl>
            </details>
          )}
        </li>
      ))}
    </ol>
  );
}
