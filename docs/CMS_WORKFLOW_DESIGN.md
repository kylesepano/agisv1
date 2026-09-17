# CMS Workflow Design

## 1. Purpose and implemented boundary

Compliance Management receives finalized recommendations from issued AEMS
reports and preserves management accountability through a controlled
implementation workflow.

Implemented as-built scope:

- CMS-1 immutable AEMS recommendation intake, operational case, and initial
  append-only event;
- CMS-2 scoped dashboard, server-driven registry, safe recommendation detail,
  Compliance Monitor assignment history, and React workspace;
- CMS-3A management-owned Corrective Action Plan family, immutable versions,
  measurable milestones, independent compliance review, return, acceptance,
  and controlled revision;
- CMS-3B responsive React Action Plan workspace for management preparation,
  milestone editing, submission, reviewer decisions, accepted-baseline
  visibility, immutable history, and controlled revision;
- CMS-4A management-reported Progress Update families, immutable versions,
  accepted-baseline milestone reports, exact Core Document Version evidence,
  completeness review, recording, and controlled correction.
- CMS-4B responsive React Progress Update workspace; and
- CMS-5A independent Validation Review families, historical Primary Validator
  assignments, professional Validation Versions and Items, evidence
  assessments, validator-obtained Core Document Versions, supervisory review,
  four controlled conclusions, and authoritative implementation-state
  transitions.

Target-date extensions, the CMS-7A/B escalation workflow, formal closure,
accepted-risk and no-longer-applicable dispositions, and controlled reopening
are operational through CMS-10B. CMS-11A adds backend scheduled reminders,
automation, and closure-readiness/escalation candidates. The CMS-11B
administrative workspace, CMS-12A report/export backend, and CMS-12B React
reports workspace are operational. The CMS-5B React validation workspace is operational against
the CMS-5A contracts and the scoped `validation-options` endpoint described in
section 13.6.

### Current CMS status

CMS is operational through CMS-12B. The current surface includes protected
dashboard, registry, recommendation detail, Action Plan, Progress Update,
Validation, target-date extension, escalation, closure, disposition, reopening,
automation administration, candidate review, report, CSV, and PDF workspaces.
All final professional decisions remain explicit human actions. CMS automation
creates reminders and reviewable candidates only; it cannot close, reopen,
dispose, or issue an escalation notice. CMS report generation is backend-owned,
scope/confidentiality-aware, reproducible from immutable snapshots, and served
through authenticated downloads. AIS-5D consumes CMS through a read-only,
scope-aware adapter and never mutates CMS; ARMIS provider authority remains a
separate gated boundary.

## 2. Record lineage

```text
Issued AEMS Report Version
  → immutable CmsRecommendation intake
  → one CmsRecommendationCase
  → zero or one CmsCorrectiveActionPlan family
  → one or more immutable CmsActionPlanVersion records
  → one or more version-owned CmsActionPlanMilestone records
  → zero or more CmsProgressUpdate reporting-period families
  → one or more immutable CmsProgressUpdateVersion records
  → accepted-baseline CmsMilestoneProgress records
  → exact CmsProgressEvidenceLink → Core DocumentVersion records
  → one CmsValidationReview per exact recorded Progress Update Version
  → immutable CmsValidationVersion revisions and CmsValidationItem procedures
  → CmsValidationEvidenceAssessment and exact validator DocumentVersion links
```

The `{recommendation}` CMS route identifier is always
`cms_recommendation_cases.id`. CMS never rewrites the issued recommendation,
finding, report, original responsible-office snapshot, or original target date.

## 3. Recommendation case status

```text
TRANSFERRED
  → FOR_ACTION_PLAN       first Action Plan draft is created
  → MONITORING            a reviewed Action Plan version is accepted
```

Draft updates, submission, review start, return, and an initial revision keep
the case in `FOR_ACTION_PLAN`. A revision of an accepted plan leaves the case in
`MONITORING`; the previously accepted version remains authoritative until the
revision is accepted. Clients cannot set case status.

## 4. Action Plan aggregate

### 4.1 Stable family

`cms_corrective_action_plans` has one row per recommendation case. It records
the owner office, creator, current-version pointer, accepted-version pointer,
and optimistic lock. Its display code is derived as
`CAP-CMS-REC-{zero-padded case ID}`; no unsupported numbering configuration is
stored.

### 4.2 Immutable versions

`cms_action_plan_versions` stores narratives, plan dates, owner/focal user,
workflow actors and timestamps, immutable submission snapshot, prior-version
lineage, and optimistic lock.

Statuses:

```text
DRAFT → SUBMITTED → UNDER_REVIEW → ACCEPTED
                                └→ RETURNED
RETURNED → new DRAFT revision
ACCEPTED → new DRAFT revision
```

Only `DRAFT` content is editable. Submitted, returned, and accepted records are
never reopened. A newer accepted version changes the family accepted pointer;
the old accepted record remains `ACCEPTED` and is derived as superseded.

PostgreSQL uses a partial unique index and all supported test/runtime databases
use a nullable `active_slot` unique key to enforce one
`DRAFT`/`SUBMITTED`/`UNDER_REVIEW` version per family.

### 4.3 Milestones

Milestones belong to one version and record sequence, measurable output,
indicator/verification information, responsible office/user, dates, optional
weight, and display order. They can be replaced or removed only while their
version is `DRAFT`.

Submission requires at least one milestone. Sequence numbers are unique.
Milestone responsibility remains with the Action Plan owner office in CMS-3A.
Dates must fit the plan period. If weights are unused, no weighted progress is
implied; if any weight is used, every milestone is weighted and the total must
equal 100%.

## 5. Ownership, access, and separation

`CmsRecommendationScopeService` remains the single visibility and
confidentiality boundary.

Responsible-office actions require the exact granular permission, a usable
account, case visibility, and an office matching the case lead responsible
office:

- create;
- update a draft;
- submit;
- revise a returned or accepted current version.

Reviewer actions require the assigned active Compliance Monitor or CIAS
Management, the exact review/return/accept permission, case visibility,
independence from the owner office, and separation from preparer, focal user,
and submitter. Platform and AGIS administrators receive no professional review
or acceptance authority.

Permissions:

```text
cms.action-plan.view
cms.action-plan.create
cms.action-plan.update
cms.action-plan.submit
cms.action-plan.review
cms.action-plan.accept
cms.action-plan.return
cms.action-plan.revise
```

Legacy `cms.*` permissions remain unchanged and do not bypass office,
assignment, state, confidentiality, or separation rules.

## 6. Completeness and target dates

Submission and acceptance validate narratives, focal user, plan dates, at
least one complete milestone, office/user eligibility, unique sequences, date
consistency, weighting, and current `lockVersion`.

An Action Plan target cannot exceed the case effective target without the
future extension workflow. When the case has no effective target, management
may propose an Action Plan date, but CMS-3A does not establish or overwrite the
case target. Resources expose that missing-policy condition.

## 7. Transactions, locking, and immutable baseline

Creation, update, submission, review start, return, acceptance, and revision
use database transactions and row locks. Unique constraints protect one family,
version numbers, active versions, and milestone sequences. Optimistic version
locks reject stale browser state.

Acceptance atomically:

1. verifies independent authority, completeness, and submission-snapshot
   integrity;
2. marks the version `ACCEPTED`;
3. sets current and accepted family pointers;
4. moves an initial case to `MONITORING`;
5. appends the event and both log layers;
6. schedules notifications after commit.

Only the family `accepted_version_id` is the official monitoring baseline.

## 8. Events, logs, and notifications

Append-only recommendation event codes:

```text
ACTION_PLAN_CREATED
ACTION_PLAN_UPDATED
ACTION_PLAN_SUBMITTED
ACTION_PLAN_REVIEW_STARTED
ACTION_PLAN_RETURNED
ACTION_PLAN_REVISION_CREATED
ACTION_PLAN_ACCEPTED
```

Matching `cms.action-plan.*` Activity Log and Audit Trail actions retain IDs,
statuses, pointers, ownership, milestone count, actor, and controlled reasons
without copying full plan narratives into operational logs.

After-commit workflow notifications cover submission, review start, return, and
acceptance. Recipients are limited to authorized reviewers, submitter, focal
user, current monitor, and responsible-office representatives as appropriate.
No reminder or overdue notification is implemented.

## 9. API

```text
GET  /api/cms/recommendations/{recommendation}/action-plan
POST /api/cms/recommendations/{recommendation}/action-plans
GET  /api/cms/action-plans/{actionPlan}
PUT  /api/cms/action-plans/{actionPlan}/versions/{version}
POST /api/cms/action-plans/{actionPlan}/versions/{version}/transitions/submit
POST /api/cms/action-plans/{actionPlan}/versions/{version}/transitions/start-review
POST /api/cms/action-plans/{actionPlan}/versions/{version}/transitions/return
POST /api/cms/action-plans/{actionPlan}/versions/{version}/transitions/accept
POST /api/cms/action-plans/{actionPlan}/versions/{version}/revisions
```

Resources expose safe case context, family pointers, version lineage,
milestones, completeness, derived current/accepted/superseded state, and
actor-specific permitted actions. They do not expose storage paths,
unrestricted AEMS records, or raw confidential metadata.

The CMS recommendation detail response adds only a backward-compatible
`actionPlanSummary`.

## 10. CMS-3B React workspace

The protected route
`/compliance-management/recommendations/{caseId}/action-plan` requires
`cms.action-plan.view`. It is reached contextually from the recommendation
detail page because the backend has no Action Plan collection endpoint; no
unsupported sidebar registry is implied.

The workspace:

- preserves the recommendation, owner-office, target-date, confidentiality,
  family, current-version, and accepted-baseline context;
- supports draft narratives, focal/responsible users, plan dates, and
  add/edit/remove/reorder milestone operations;
- shows whether weighting is unused, incomplete, or totals 100%;
- submits only complete drafts and exposes start-review, return, accept, and
  revision controls from backend `availableActions` plus the matching granular
  frontend permission;
- renders every non-draft and every historical version read-only, including
  current, accepted-baseline, and superseded indicators;
- keeps the previously accepted baseline visible while a later draft is under
  preparation or review;
- sends the latest `lockVersion` with every mutation, blocks duplicate
  submissions, warns before discarding unsaved work, preserves local draft
  values after a stale-lock response, and offers an explicit authoritative
  reload; and
- presents validation details without clearing entered values, treats Laravel
  authorization as final, and uses a generic unavailable state for scoped
  `404` responses.

The responsive Playwright coverage exercises responsible-office preparation,
milestone weighting and ordering, reviewer return and acceptance, immutable
history, revision, accepted-baseline continuity, authorization failure,
optimistic-lock recovery, duplicate-click prevention, and scope-safe
unavailability on desktop and mobile.

## 11. CMS-4A management-reported progress

### 11.1 Boundary and baseline

CMS-4A records what management reports and the evidence management supplies.
`RECORDED` means the submission passed completeness review and was recorded for
follow-up; it is not an independent validation or an implementation
conclusion. A reported 100% is exposed as
`reportedCompleteAwaitingValidation`, never as `implemented`.

Progress creation requires a `MONITORING` or `PARTIALLY_IMPLEMENTED` case and
the current accepted Action Plan Version. Every family stores that exact
accepted version. Historical
updates remain pinned when a later plan revision is accepted; a new update must
use the new accepted baseline. Submission rejects a draft if its baseline is no
longer current.

### 11.2 Family, versions, and reporting periods

`cms_progress_updates` is the stable family for one case/reporting period.
Reporting periods require start and end dates, cannot predate baseline
acceptance, cannot overlap another family for the case, and are fixed after
creation. No monthly or quarterly frequency is assumed.

`cms_progress_update_versions` follows:

```text
DRAFT → SUBMITTED → UNDER_REVIEW → RECORDED
                              └→ RETURNED → new DRAFT revision
RECORDED → new DRAFT correction revision
```

Only `DRAFT` is editable. Current and recorded pointers are distinct; a
previously recorded version remains authoritative until its correction is
recorded. Older recorded versions derive supersession without changing their
stored `RECORDED` status. The recommendation case remains `MONITORING`
throughout.

### 11.3 Milestone progress and calculations

Each version reports against milestone IDs and immutable milestone snapshots
from its exact accepted Action Plan Version. Submission requires exactly one
entry for every accepted milestone. Allowed management-reported states are
`NOT_STARTED`, `IN_PROGRESS`, `REPORTED_COMPLETED`, `DELAYED`, and `ON_HOLD`.

For weighted baselines, the server calculates:

```text
sum(reported milestone percentage × accepted milestone weight) / 100
```

The result uses half-up rounding to two decimal places and cannot be overridden
by the client. Unweighted baselines require an overall management-reported
percentage and are not automatically averaged.

### 11.4 Evidence

Evidence uploads create a private Core `Document` and immutable
`DocumentVersion`; CMS stores the exact document-version ID, checksum, and
effective confidentiality snapshot. The stricter of recommendation and
requested document confidentiality applies. CMS never copies files to a
separate evidence repository or follows a document's later current-version
pointer.

Progress above 0% requires milestone evidence or a no-evidence explanation.
`REPORTED_COMPLETED` requires evidence. The whole update requires evidence or a
general explanation. These checks assess completeness only, not sufficiency,
reliability, or effectiveness. A draft link may be marked removed without
deleting the Core document; submitted and historical links are immutable.

### 11.5 Authority, permissions, and controls

Responsible-office actions require matching office, case visibility, usable
account, and the matching `cms.progress.*` or `cms.evidence.*` permission.
Review, return, and recording require the active Compliance Monitor or CIAS
Management, independent from the responsible office, preparer, submitter, and
accepted-plan focal user. Technical administrator roles receive no
professional recording authority.

Permissions are:

```text
cms.progress.view
cms.progress.create
cms.progress.update
cms.progress.submit
cms.progress.review
cms.progress.return
cms.progress.record
cms.progress.revise
cms.evidence.view
cms.evidence.upload
cms.evidence.download
cms.evidence.remove_draft
```

Legacy `cms.*` compatibility permissions remain unchanged and do not bypass
the new granular permissions or scope.

Transactions, row locks, `lockVersion`, unique period/version/active-slot
constraints, immutable submission snapshots, append-only events, Activity Log,
Audit Trail, and after-commit notifications protect every transition.
Notifications explicitly describe recorded progress as management-reported
and not independently validated.

### 11.6 API

```text
GET    /api/cms/recommendations/{recommendation}/progress-updates
POST   /api/cms/recommendations/{recommendation}/progress-updates
GET    /api/cms/progress-updates/{progressUpdate}
PUT    /api/cms/progress-updates/{progressUpdate}/versions/{version}
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/submit
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/start-review
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/return
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/record
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/revisions
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/evidence
GET    /api/cms/progress-evidence/{evidence}/download
DELETE /api/cms/progress-evidence/{evidence}
```

Recommendation detail and dashboard responses add backward-compatible
management-reported summaries.

## 12. CMS-4B React workspace

The frontend exposes Progress Updates from the recommendation-specific Detail
Workspace and does not create a global Progress Update registry. The protected
routes are:

```text
/compliance-management/recommendations/{recommendationId}/progress-updates
/compliance-management/recommendations/{recommendationId}/progress-updates/{progressUpdateId}
```

The list displays reporting period, current version/status, accepted baseline,
reported percentage, evidence count, recorded-version indicators, and the
server-authorized create action. The detail workspace provides Overview,
Milestone Progress, Evidence, and Versions & History regions. Historical
versions are read-only; only a current `DRAFT` version exposes the editor.

The editor preserves accepted-baseline milestone wording, owners, dates, and
weights. Weighted progress is presented as the server-calculated
management-reported result; unweighted progress remains management-entered and
is not averaged. A reported 100% is shown as “awaiting independent validation,”
never as “Implemented.”

Evidence uploads use the existing Core-backed multipart contract and protected
CMS download route. Confidentiality options come from the Core Document API.
Draft evidence links may be removed with a reason; submitted and historical
links have no removal control. Internal storage paths and generated filenames
are never shown.

Submit, start-review, return, record, and revision dialogs use the version's
server-provided `availableActions`, current `lockVersion`, and existing
permission helpers. Stale locks, changed baselines, 403/404/409/422 responses,
validation errors, uncertain network results, loading, empty, and retry states
are presented without silently resending a mutation.

CMS-4B remains a presentation layer for management-reported information. It
does not add independent validation, implementation conclusions, escalation,
closure, accepted-risk decisions, reopening, reports, exports, AIS, or ARMIS
integration. Target-date extensions are provided by CMS-6A/6B.

## 13. CMS-5A independent validation

### 13.1 Professional boundary and lineage

Management prepares the Action Plan, reports progress, and supplies evidence.
A Compliance reviewer records completeness only. A separately assigned
Primary Validator performs procedures and proposes a conclusion; an eligible
CIAS supervisory reviewer independently returns or finalizes it. Neither a
recorded update nor management-reported 100% establishes implementation.

One `cms_validation_reviews` family pins the exact recommendation case,
accepted Action Plan Version, Progress Update family, and current recorded
Progress Update Version. A recorded version may be used by only one review.
Only one review is active per case. Historical records are never remapped to a
new plan, progress report, evidence file, validator, or conclusion.

The review owns:

- immutable `cms_validation_versions`;
- milestone/recommendation `cms_validation_items`;
- management/validator `cms_validation_evidence_assessments`;
- exact Core-backed `cms_validation_evidence_links`; and
- ended, never overwritten, `cms_validation_assignments`.

Derived display codes use
`VAL-CMS-REC-{zero-padded case ID}-{sequence}-V{version}`.

### 13.2 Statuses and case transitions

Validation Version lifecycle:

```text
DRAFT → SUBMITTED → UNDER_REVIEW → FINALIZED
                              └→ RETURNED → new DRAFT revision
```

Only `DRAFT` is editable. Submission snapshots the source recommendation,
accepted milestones, recorded management progress, exact evidence checksums,
Validation Items, assessments, professional narratives, validator, and
proposed conclusion. Submitted, under-review, returned, and finalized versions
are immutable. Only a returned current version may create a revision.

Starting a review changes `MONITORING` or `PARTIALLY_IMPLEMENTED` to
`FOR_VALIDATION`. Finalization applies only:

| Final conclusion | Case state |
| --- | --- |
| `NOT_IMPLEMENTED` | `MONITORING` |
| `INADEQUATE_BASIS` | `MONITORING` |
| `PARTIALLY_IMPLEMENTED` | `PARTIALLY_IMPLEMENTED` |
| `IMPLEMENTED` | `IMPLEMENTED` |

`IMPLEMENTED` is independently validated implementation, not closure.
Progress reporting resumes after `NOT_IMPLEMENTED`, `INADEQUATE_BASIS`, or
`PARTIALLY_IMPLEMENTED`; it is blocked during `FOR_VALIDATION` and while
`IMPLEMENTED`.

### 13.3 Validation Items and evidence assessment

The server initializes one milestone Validation Item for every milestone in
the pinned accepted baseline and associates the exact milestone-progress row
when available. Submission requires complete criterion, procedure,
population/source, result, and item conclusion for every accepted milestone.
Cross-baseline and duplicate milestone items are rejected.

Item conclusions are `SATISFIED`, `PARTIALLY_SATISFIED`, `NOT_SATISFIED`,
`INADEQUATE_BASIS`, and `NOT_APPLICABLE`.

Every exact management evidence link is assessed without modifying it.
Validator-obtained evidence creates a private Core `Document` and immutable
`DocumentVersion`, pins its version/checksum/classification, and receives its
own assessment. Assessment controls are relevance, reliability, sufficiency,
relied-upon state, summary, and limitation. Relied-upon evidence cannot remain
`NOT_ASSESSED`; evidence not relied upon requires an explanation. Draft
validator links may be marked removed without deleting the Core document.

### 13.4 Conclusions and supervisory control

Professional conclusions are only `NOT_IMPLEMENTED`,
`PARTIALLY_IMPLEMENTED`, `IMPLEMENTED`, and `INADEQUATE_BASIS`. The service
rejects obvious contradictions: implemented work cannot contain a partial,
unsatisfied, inadequate-basis, or materially insufficient relied-upon result;
partial implementation requires both established progress and remaining work;
not implemented cannot contradict all-satisfied milestones; inadequate basis
requires a documented limitation and inadequate item or evidence basis.

Finalization requires an `UNDER_REVIEW` immutable submission, confirmation,
comment, current locks, complete evidence assessment, valid source lineage,
and an independent supervisor. Changing the validator's proposal requires an
explicit override reason. Version finalization, review pointer, case state,
event, Activity Log, Audit Trail, and notifications commit atomically.

### 13.5 Independence, scope, permissions, and API

The Primary Validator must be active, unlocked, professionally permitted,
outside the responsible office, and not the plan preparer/focal
user/submitter, progress preparer/submitter/recorder, or current Compliance
Monitor. The supervisor must be CIAS Management with the exact professional
permission and cannot be the validator, responsible-office user, or source
participant. Technical administration and the legacy `cms.validate` code do
not bypass these rules.

Permissions:

```text
cms.validation.view
cms.validation.create
cms.validation.assign
cms.validation.update
cms.validation.submit
cms.validation.review
cms.validation.return
cms.validation.finalize
cms.validation.revise
cms.validation-evidence.view
cms.validation-evidence.upload
cms.validation-evidence.download
cms.validation-evidence.remove_draft
```

Routes:

```text
GET|POST /api/cms/recommendations/{recommendation}/validations
GET      /api/cms/validations/{validation}
GET|POST /api/cms/validations/{validation}/assignments
POST     /api/cms/validations/{validation}/assignments/{assignment}/end
PUT      /api/cms/validations/{validation}/versions/{version}
POST     /api/cms/validations/{validation}/versions/{version}/transitions/submit
POST     /api/cms/validations/{validation}/versions/{version}/transitions/start-review
POST     /api/cms/validations/{validation}/versions/{version}/transitions/return
POST     /api/cms/validations/{validation}/versions/{version}/transitions/finalize
POST     /api/cms/validations/{validation}/versions/{version}/revisions
POST     /api/cms/validations/{validation}/versions/{version}/evidence
GET      /api/cms/validation-evidence/{evidence}/download
DELETE   /api/cms/validation-evidence/{evidence}
```

All mutations use camelCase payloads and the latest `lockVersion`. Clients
cannot select case/version status, sequence/version number, baseline pointers,
actors, timestamps, or final pointers. Recommendation detail adds a
backward-compatible `validationSummary`; the dashboard adds scoped active,
awaiting-review, returned, and conclusion metrics. The CMS-5B React workspace is
operational against these contracts; its safe eligible-validator catalog gap is
documented below.

Append-only events, matching `cms.validation.*` Activity/Audit actions, and
after-commit notifications cover creation, assignment/replacement, updates,
evidence links/removal, submission, supervisory review, return, revision, and
finalization.

At the CMS-5A/B checkpoint, automated reminders, the escalation React workspace
(CMS-7B), closure request/approval, Accepted-Risk, No-Longer-Applicable,
reopening, recurrence analysis, reports, and exports were later phases. Those
CMS capabilities are now implemented in CMS-7B through CMS-12B. AIS integration
is read-only through AIS-5D, and ARMIS provider authority remains a separate
boundary.

### 13.6 CMS-5B React validation workspace

The recommendation-scoped React workspace is available at:

```text
/compliance-management/recommendations/{recommendationId}/validations
/compliance-management/recommendations/{recommendationId}/validations/{validationId}
```

Recommendation Detail is the primary entry point; there is no global Validation
Registry. The workspace consumes the CMS-5A routes through the existing `cmsApi`
client and presents Overview, Procedures & Conclusions, Evidence Assessment,
Validator Evidence, Assignments, and Versions & History tabs. Draft narratives,
validation items, and evidence assessments are editable only when the server
returns the corresponding `availableActions`; all submitted, returned, and
finalized versions are read-only.

The UI uses only recorded Progress Update versions and Primary Validators
returned by the authorized `validation-options` endpoint. It never loads an
unrestricted User Registry. Laravel applies active/unlocked/non-archived,
professional-permission, office, source-participant, Compliance Monitor, and
confidentiality filters through the existing aggregate eligibility logic.

Submission, supervisory review, return, revision, and finalization use current
lock versions and reload after conflicts. Protected validator-evidence downloads
use authenticated Core document routes. A finalized `IMPLEMENTED` conclusion is
displayed as independently validated while closure remains pending; the React
workspace does not create closure, extensions, reminders, escalation, reopening,
reports, exports, or CMS/AIS/ARMIS integrations. Those capabilities were added
in later, separate increments; the following sections describe each later
checkpoint in sequence.

## CMS-6A target-date extensions

CMS-6A adds a backend-only, approval-controlled target-date extension workflow.
An extension is a stable request family with immutable versions and follows
`DRAFT → SUBMITTED → UNDER_REVIEW → FOR_APPROVAL → APPROVED|REJECTED`, with
`RETURNED` revisions between review stages. Requests are available only for
eligible `MONITORING` or `PARTIALLY_IMPLEMENTED` cases with an accepted action
plan, recorded progress, a current effective target date, and no active
validation. The responsible office prepares and submits; independent CMS
reviewers assess; an authorized management decision-maker approves or rejects.

The original AEMS target date remains immutable. Only an approved decision may
change `cms_recommendation_cases.effective_target_implementation_date`; every
change appends `cms_recommendation_target_date_history` and records a decision,
event, activity entry, audit entry, and notification. Case status is unchanged.
Evidence is linked to an exact Core document version, with checksum and
confidentiality snapshots. Approved and rejected versions, decisions, and date
history are append-only; stale lock versions and changed source snapshots are
rejected.

CMS-6A exposes the extension API and dashboard/detail aggregates. CMS-6B adds
the recommendation-scoped React extension list and detail workspace. Automated
reminders, escalation, closure, accepted-risk, no-longer-applicable, reopening,
recurrence, reports, exports, AIS, and ARMIS integrations were outside this
increment and are documented in the later CMS sections.

## CMS-6B React workspace

The frontend routes are recommendation-specific:

```text
/compliance-management/recommendations/:recommendationId/extensions
/compliance-management/recommendations/:recommendationId/extensions/:extensionId
```

Recommendation Detail links to the workspace and displays the CMS-6A extension
summary without replacing the current effective target date. The workspace
contains a request list, creation context, draft form, Supporting Evidence,
Assessment & Recommendation, Decision, and Versions & History tabs. It uses
the existing `cmsApi` wrapper, authenticated Core downloads, responsive cards,
and backend `availableActions` for control visibility.

Original, current effective, requested, and approved dates are always labelled
separately. Pending, returned, or rejected requests never change the effective
date display; only an approved backend decision does. Draft evidence can be
linked or removed with a reason, while submitted and historical evidence is
read-only. Stale locks and source changes preserve local context and offer a
reload rather than overwriting newer state.

The workspace does not add escalation UI, closure, accepted-risk, reopening,
recurrence, reporting, exports, AIS, or ARMIS functionality.

## CMS-7A escalation management backend

CMS-7A records a formal escalation separately from recommendation
implementation status. New escalations are allowed only for visible
`MONITORING` or `PARTIALLY_IMPLEMENTED` cases with no unresolved escalation.
Supported primary triggers are `OVERDUE_TARGET`, `MISSING_PROGRESS_UPDATE`,
`REPEATED_PROGRESS_RETURN`, `INADEQUATE_MANAGEMENT_RESPONSE`,
`VALIDATION_NOT_IMPLEMENTED`, `VALIDATION_INADEQUATE_BASIS`,
`REPEATED_EXTENSION_REQUEST`, `FAILURE_TO_COMPLETE_REQUIRED_ACTION`,
`MANAGEMENT_NON_RESPONSE`, and `OTHER` (which requires an explanation).

Notice versions follow `DRAFT → SUBMITTED → UNDER_REVIEW → RETURNED|ISSUED`;
returned notices require a new immutable revision. Issuance snapshots the
source case, target dates, overdue context, extension context, action plan,
progress, validation, monitor, trigger, and recipients. Issuance never changes
the recommendation case status. Responsible-office acknowledgement is
append-only and separate from agreement.

Responsible-office responses follow `DRAFT → SUBMITTED → UNDER_REVIEW →
RETURNED|ACCEPTED_FOR_FOLLOW_UP`; accepted responses establish a follow-up
baseline without validating implementation. CIAS Management may resolve the
escalation process only; the immutable resolution explicitly records that
recommendation closure was not performed.

Notice and response evidence pins exact Core Document Versions with checksum
and confidentiality snapshots. Notice issuance, response acceptance, and
resolution use optimistic locking, row transactions, append-only CMS events,
Activity Log entries, and after-commit notifications.

## CMS-7B escalation React workspace

The recommendation-scoped routes `/compliance-management/recommendations/:id/escalations`
and `/compliance-management/recommendations/:id/escalations/:escalationId` provide
the protected list and detail workspace. The workspace consumes the CMS-7A
options, list, detail, version, evidence, acknowledgement, response, and
resolution endpoints. It keeps source snapshots read-only, separates escalation
status from recommendation status, supports draft-only editing and evidence
links, immutable revisions, protected downloads, stale-lock reload guidance,
and read-only views for returned/issued/accepted versions. Recommendation Detail
and the CMS dashboard link to the workspace and display live CMS-7A metrics.

The CMS-7A Resources currently do not populate per-record `availableActions`;
the frontend uses returned actions when present and falls back to existing
permission/status visibility until that contract is enriched. Laravel remains
authoritative. At the CMS-7B checkpoint, automatic escalation, scheduled
reminders, recommendation closure, dispositions, reopening, reports, and
exports were still later increments. CMS-8 through CMS-12B now provide those
capabilities; AIS integration is read-only through AIS-5D and ARMIS provider
authority remains separately gated.

## CMS-8A formal recommendation closure

> Historical checkpoint note: this section records the CMS-8A gate as it stood
> before CMS-9A/B dispositions and CMS-10A/B reopening. Statements below that
> describe those later workflows as unavailable are historical, not current.

CMS closure is a separate professional decision from management-reported completion and independent validation. A finalized validation conclusion of `IMPLEMENTED` is required before a Closure Request can be submitted; only an approved Closure Decision changes the case to `CLOSED`.

Closure Requests are immutable versioned families (`DRAFT → SUBMITTED → UNDER_REVIEW → RETURNED/FOR_DECISION → APPROVED/REJECTED`). Submission moves `IMPLEMENTED` to `FOR_CLOSURE`; rejection returns it to `IMPLEMENTED`; approval atomically records the decision and moves it to terminal `CLOSED`. Closed cases remain read-only. Reopening and alternative dispositions were added in CMS-9A/10A and are described below.

CMS-8A adds an authoritative readiness checklist, source-lineage and final snapshots, independent review assessment, CIAS Management decision authority, restrictive evidence links to Core document versions, separation-of-duties checks, and closure permissions (`cms.closure.*`, `cms.closure-evidence.*`).

CMS-8B provides recommendation-scoped Closure Request list and detail routes at `/compliance-management/recommendations/{id}/closure-requests` and `/closure-requests/{requestId}`. The client renders the backend readiness checklist, immutable source lineage, evidence links, assessment, decision, version history, draft editing, and available backend actions. `IMPLEMENTED`, `FOR_CLOSURE`, and `CLOSED` remain distinct in the UI. Reopening and alternative dispositions are separate workspaces implemented by CMS-9B and CMS-10B.

> Current-state correction: reopening and alternative dispositions are available
> through the separate CMS-9B and CMS-10B protected workspaces. They do not
> alter the CMS-8 closure controls or make closed records editable in place.

## CMS-9A accepted-risk and no-longer-applicable dispositions

CMS-9A adds two controlled professional dispositions without treating either as
implementation or ordinary closure. A shared immutable request family supports
`ACCEPTED_RISK` and `NO_LONGER_APPLICABLE` versions:

```text
DRAFT → SUBMITTED → UNDER_REVIEW → FOR_DECISION → APPROVED|REJECTED
                         └→ RETURNED → new immutable revision (DRAFT)
```

Submission moves a `MONITORING` or `PARTIALLY_IMPLEMENTED` case to
`FOR_DISPOSITION`. Approval atomically moves it to the selected terminal
disposition; rejection restores the version's pinned prior case status. No
disposition changes a recommendation to `CLOSED`, and no automation or client
can make the final decision.

The responsible office or authorized compliance monitor prepares the request.
An independent reviewer records readiness, basis, evidence, and risk
assessments; a different CIAS Management decision-maker records approval or
rejection. Active validation, extension, escalation, closure, and disposition
requests block eligibility. Optimistic case/version locks and transactional
transitions prevent concurrent updates.

Disposition evidence links pin an exact Core `DocumentVersion`, checksum, and
confidentiality snapshot. Submitted, reviewed, decided, and linked-evidence
records are immutable; corrections use a new request version. Every transition
records an append-only CMS event, Activity Log entry, Audit Trail entry, and
after-commit notification.

CMS-9A is backend-only. Recommendation Detail and the dashboard expose live
disposition summaries, readiness, and available actions; the React workspace is
reserved for CMS-9B. Reopening, reminders/automation, reports/exports, AIS, and
ARMIS remain outside this increment.

## CMS-9B disposition React workspace

CMS-9B adds the protected recommendation-scoped routes:

```text
/compliance-management/recommendations/:recommendationId/dispositions
/compliance-management/recommendations/:recommendationId/dispositions/:dispositionId
```

The workspace consumes the CMS-9A options, readiness, request, version,
assessment, decision, and exact Core-version evidence contracts. It keeps
Accepted Risk and No Longer Applicable visually and semantically distinct from
`IMPLEMENTED` and `CLOSED`, renders backend `availableActions`, and uses
authenticated protected downloads. Draft narratives may be edited; submitted,
reviewed, decision-stage, approved, and rejected versions are rendered
read-only. Returned versions use the revision endpoint.

The workspace includes Overview, Readiness & Eligibility, Disposition Details,
Supporting Evidence, Review Assessment, Final Decision, and Versions & History
tabs. It does not expose reopening, automation, reports, exports, AIS, or ARMIS
controls. Historical version detail is rendered when supplied by the backend
resource; CMS-9A currently returns the authoritative current version only.

## CMS-10A controlled recommendation reopening backend

Reopening is an exceptional workflow for effective `CLOSED`, `ACCEPTED_RISK`,
and `NO_LONGER_APPLICABLE` recommendations. The original Closure or
Disposition Decision remains immutable and is pinned by ID and source snapshot.
The request family is `DRAFT → SUBMITTED → UNDER_REVIEW → RETURNED/FOR_DECISION
→ APPROVED/REJECTED`; returned requests are corrected through new immutable
revisions. Approval may move the case only to `FOR_ACTION_PLAN` or `MONITORING`.
Monitoring requires an accepted Action Plan, current Compliance Monitor, valid
target date, and suitability assessment. Approval starts a new active cycle;
rejection leaves the terminal case unchanged.

Independent review and CIAS Management decision authority are separated from
the initiator. Optimistic locks, atomic transitions, immutable assessments and
decisions, exact Core Document Version evidence, scope/confidentiality checks,
CMS events, Activity Log, Audit Trail, and after-commit notifications apply to
all transitions. No automation, direct closure, AIS, or ARMIS integration is
included. CMS-10B consumes this backend contract through protected React routes
`/compliance-management/recommendations/:recommendationId/reopening-requests`
and its request-detail child route. The workspace treats backend readiness and
`availableActions` as authoritative, keeps exact Core-version evidence links
protected, and shows the current active cycle alongside the preserved
historical terminal decision. It never mutates case status directly or treats
a request/review as a reopening.

## CMS-11A scheduled automation and reviewable candidates

CMS-11A adds the backend automation boundary. Versioned rules are stored in
`cms_automation_rules` and `cms_automation_rule_versions`; each idempotent run
and resulting action is recorded in `cms_automation_runs` and
`cms_automation_actions`.

The first rule family supports target-date reminders, closure-readiness
detection, and overdue escalation candidates. Closure candidates are created
only when the existing CMS closure gates pass: `IMPLEMENTED` case status,
finalized `IMPLEMENTED` validation, no active validation, extension,
escalation, closure, disposition, or reopening request. Escalation candidates
are reviewable drafts only. Automation never closes a case, approves a
disposition, reopens a recommendation, or issues an escalation notice.

Candidates are scope- and confidentiality-filtered, deduplicated by a stable
detection key, and reviewable through protected API endpoints. Acknowledge and
dismiss actions record the reviewer, timestamp, note, Activity Log, Audit
Trail, CMS event, and notification lineage. The existing CMS-8 closure review
and approval workflow remains the only path to formal closure.

The CMS dashboard and Recommendation Detail contract expose automation counts,
candidate summaries, and per-case readiness. CMS-11A is the backend contract and
CMS-11B is the administrative React workspace. CMS-12A/12B provide the report
and export backend/workspace. AIS integration is read-only through AIS-5D; ARMIS
remains a separate provider boundary rather than a CMS dependency.

## CMS-11B automation administration workspace

CMS-11B provides the protected React workspace at
`/compliance-management/automation`. The workspace presents active rule
counts, open closure-readiness and escalation candidates, recent reminders,
and the latest automation run. Authorized managers can create or revise a
rule; each save creates a new immutable backend version. Authorized operators
can run automation manually and inspect replay-safe run history.

The Candidate Review tab separates closure-readiness candidates from overdue
escalation candidates. Reviewers can acknowledge or dismiss a candidate with a
note, while the UI explicitly preserves the recommendation case status and
routes all final professional decisions to the existing CMS workflows. The
route, navigation item, actions, and candidate records are permission- and
scope-aware on both client and server. Desktop and mobile browser coverage is
included; CMS-12 reports/exports and AIS/ARMIS remain outside this phase.

## CMS-12A reports and protected exports

CMS-12A adds backend-generated CMS report snapshots and private CSV/PDF
artifacts. The report catalog currently includes `portfolio-status`,
`implementation-progress`, `target-date-monitoring`, and `closure-readiness`.
Each run applies the existing `CmsRecommendationScopeService`, including
assignment/office visibility and recommendation confidentiality, then stores
the authorized case IDs, validated filters, source-query version, ordered
columns/rows, and SHA-256 result checksum in immutable `cms_report_runs`.

CSV and PDF files are generated only from that immutable snapshot. CSV cells
beginning with `=`, `+`, `-`, or `@` (including leading whitespace) receive a
leading apostrophe to prevent spreadsheet formula execution. PDF and CSV
artifacts are stored on the private local disk with a checksum, MIME type,
size, filename, and immutable export version in `cms_report_exports`.

The protected API exposes the catalog, run generation, run history, export
generation, and authenticated downloads. Download authorization rechecks the
current visible case set, so a report cannot be downloaded after its scope or
confidentiality becomes unauthorized. Report generation and export/download
actions create Activity Log and Audit Trail entries. CMS-12A/12B do not transfer,
close, reopen, or otherwise change recommendation cases. AIS-2 is a separate
read-only analytical surface; it does not mutate CMS records, and ARMIS provider
integration remains outside the CMS boundary.
