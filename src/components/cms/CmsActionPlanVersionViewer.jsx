import {
  CalendarDays,
  CheckCircle2,
  FileLock2,
  RotateCcw,
  UserRound,
} from "lucide-react";
import StatusBadge from "../ui/StatusBadge";
import { CmsActionPlanStatusBadge } from "./CmsBadges";

function displayDate(value, includeTime = false) {
  if (!value) return "Not recorded";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not recorded";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    ...(includeTime ? { timeStyle: "short" } : {}),
  }).format(date);
}

function Narrative({ label, value }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
      <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">
        {label}
      </dt>
      <dd className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-800">
        {value || "Not provided"}
      </dd>
    </div>
  );
}

function WorkflowDatum({ label, actor, date }) {
  return (
    <div>
      <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">
        {label}
      </dt>
      <dd className="mt-1 text-sm font-semibold text-slate-800">
        {actor?.name || "Not recorded"}
      </dd>
      <dd className="mt-0.5 text-xs text-slate-500">
        {displayDate(date, true)}
      </dd>
    </div>
  );
}

export default function CmsActionPlanVersionViewer({ version }) {
  if (!version) return null;

  return (
    <div className="grid gap-4">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
              {version.displayCode || `Version ${version.versionNumber}`}
            </p>
            <h3 className="mt-1 text-lg font-bold text-slate-800">
              Action Plan version {version.versionNumber}
            </h3>
          </div>
          <div className="flex flex-wrap gap-2">
            <CmsActionPlanStatusBadge status={version.status} />
            {version.status !== "DRAFT" && (
              <StatusBadge tone="info">
                <FileLock2 aria-hidden="true" className="mr-1" size={13} />
                Immutable version
              </StatusBadge>
            )}
            {version.isAcceptedCurrent && (
              <StatusBadge tone="success">Accepted baseline</StatusBadge>
            )}
            {version.isSuperseded && (
              <StatusBadge tone="warning">
                Superseded accepted version
              </StatusBadge>
            )}
          </div>
        </div>

        {version.returnReason && (
          <div className="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <div className="flex items-start gap-2">
              <RotateCcw className="mt-0.5 shrink-0 text-amber-700" size={18} />
              <div>
                <p className="text-sm font-bold text-amber-900">
                  Return instructions
                </p>
                <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-amber-800">
                  {version.returnReason}
                </p>
              </div>
            </div>
          </div>
        )}

        {version.acceptanceComment && (
          <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <div className="flex items-start gap-2">
              <CheckCircle2
                className="mt-0.5 shrink-0 text-emerald-700"
                size={18}
              />
              <div>
                <p className="text-sm font-bold text-emerald-900">
                  Acceptance comment
                </p>
                <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-emerald-800">
                  {version.acceptanceComment}
                </p>
              </div>
            </div>
          </div>
        )}

        {version.revisionReason && (
          <div className="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4">
            <p className="text-sm font-bold text-sky-900">Revision reason</p>
            <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-sky-800">
              {version.revisionReason}
            </p>
          </div>
        )}

        <dl className="mt-5 grid gap-3 sm:grid-cols-2">
          <Narrative label="Plan summary" value={version.planSummary} />
          <Narrative
            label="Implementation strategy"
            value={version.implementationStrategy}
          />
          <Narrative label="Expected outcome" value={version.expectedOutcome} />
          <Narrative
            label="Root-cause response"
            value={version.rootCauseResponse}
          />
          <Narrative
            label="Resources required"
            value={version.resourcesRequired}
          />
          <Narrative label="Dependencies" value={version.dependencies} />
          <Narrative
            label="Risks and constraints"
            value={version.risksAndConstraints}
          />
        </dl>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex items-center gap-2">
          <CalendarDays className="text-sky-700" size={18} />
          <h3 className="font-bold text-slate-800">Ownership and schedule</h3>
        </div>
        <dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <dt className="text-xs font-bold uppercase text-slate-500">
              Owner office
            </dt>
            <dd className="mt-1 text-sm font-semibold text-slate-800">
              {version.ownerOffice?.name || "Not recorded"}
            </dd>
          </div>
          <div>
            <dt className="text-xs font-bold uppercase text-slate-500">
              Focal user
            </dt>
            <dd className="mt-1 text-sm font-semibold text-slate-800">
              {version.focalUser?.name || "Not recorded"}
            </dd>
          </div>
          <div>
            <dt className="text-xs font-bold uppercase text-slate-500">
              Planned start
            </dt>
            <dd className="mt-1 text-sm font-semibold text-slate-800">
              {displayDate(version.plannedStartDate)}
            </dd>
          </div>
          <div>
            <dt className="text-xs font-bold uppercase text-slate-500">
              Planned target
            </dt>
            <dd className="mt-1 text-sm font-semibold text-slate-800">
              {displayDate(version.plannedTargetDate)}
            </dd>
          </div>
        </dl>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex items-center gap-2">
          <UserRound className="text-sky-700" size={18} />
          <h3 className="font-bold text-slate-800">
            Controlled workflow record
          </h3>
        </div>
        <dl className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <WorkflowDatum
            actor={version.preparedBy}
            date={version.createdAt}
            label="Prepared by"
          />
          <WorkflowDatum
            actor={version.submittedBy}
            date={version.submittedAt}
            label="Submitted by"
          />
          <WorkflowDatum
            actor={version.reviewStartedBy}
            date={version.reviewStartedAt}
            label="Review started by"
          />
          {version.returnedAt ? (
            <WorkflowDatum
              actor={version.returnedBy}
              date={version.returnedAt}
              label="Returned by"
            />
          ) : (
            <WorkflowDatum
              actor={version.acceptedBy}
              date={version.acceptedAt}
              label="Accepted by"
            />
          )}
        </dl>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h3 className="font-bold text-slate-800">
          Milestones ({version.milestones?.length ?? 0})
        </h3>
        {(version.milestones?.length ?? 0) === 0 ? (
          <p className="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
            No milestones were recorded for this version.
          </p>
        ) : (
          <div className="mt-4 grid gap-3 lg:grid-cols-2">
            {version.milestones.map((milestone) => (
              <article
                className="rounded-xl border border-slate-200 bg-slate-50 p-4"
                key={milestone.id}
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
                      Milestone {milestone.sequenceNumber}
                    </p>
                    <h4 className="mt-1 font-bold text-slate-800">
                      {milestone.title}
                    </h4>
                  </div>
                  {milestone.weightPercentage !== null &&
                    milestone.weightPercentage !== undefined && (
                      <StatusBadge tone="info">
                        {Number(milestone.weightPercentage).toFixed(2)}%
                      </StatusBadge>
                    )}
                </div>
                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">
                  {milestone.description || "No description provided."}
                </p>
                <dl className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                  <Narrative
                    label="Expected output"
                    value={milestone.expectedOutput}
                  />
                  <Narrative
                    label="Success indicator"
                    value={milestone.successIndicator}
                  />
                  <Narrative
                    label="Verification method"
                    value={milestone.verificationMethod}
                  />
                  <Narrative
                    label="Responsible person"
                    value={
                      milestone.responsibleUser?.name ||
                      milestone.responsibleOffice?.name
                    }
                  />
                </dl>
                <p className="mt-3 text-xs font-semibold text-slate-600">
                  {displayDate(milestone.plannedStartDate)} –{" "}
                  {displayDate(milestone.plannedTargetDate)}
                </p>
              </article>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
