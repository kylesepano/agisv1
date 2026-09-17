# ARMIS Workflow and Implementation Checkpoint

## Current implementation checkpoint

ARMIS-1A/1B, ARMIS-2A/2B, ARMIS-3A/3B, ARMIS-4A/4B, ARMIS-5A/5B, ARMIS-6A,
ARMIS-6B, ARMIS-6C, ARMIS-6D, ARMIS-7A, and ARMIS-7B are verified. ARMIS-7C
is now the final post-deployment smoke-verification checkpoint. ARMIS-3A
provides the backend planning ledger for availability, annual capacity,
planned workload, and utilization; ARMIS-3B provides its protected React
workspace. ARMIS-4A provides separate assignment and actual-person-day
ledgers with conflict and capacity rules. The mode-aware
`ConfigurableResourcePlanningGateway` now protects the replaceable boundary.
ARMIS is the sole operational resource owner and the only active provider.
Historical IAP/shadow values remain only in immutable lineage records. They
are never selected for AEMS operational reads, never act as an IAP fallback,
and cannot be used to switch provider ownership. The former provider-
reconciliation workspace is retired from navigation; its compatibility route
redirects to read-only Provider Monitoring. Current assignment readiness uses
approved ARMIS records directly.

## ARMIS-0 scope

ARMIS-0 established the as-built boundary. ARMIS-1A adds the backend foundation
for the resource registry and its governed planning ledger. It does not change
IAP or AEMS workflow behavior, switch the provider, or start AIS integration.

## Verified as-built matrix

| Area | Current state | Evidence |
| --- | --- | --- |
| Navigation | ARMIS exposes Resource Registry, Competencies & Certifications, Planning & Utilization, Assignments & Actuals, Provider Monitoring, and Reports & Administration pages | `src/config/navigation.js` |
| Workspace/API | Protected resource, competency, planning, assignment/actuals, provider monitoring, and reports/administration React workspaces consume dedicated ARMIS APIs | `src/App.jsx`, `backend/routes/api.php` |
| Permissions | Granular `armis.*` permissions govern registry, competency, planning, actuals, reports, and exports; compatibility permissions remain | `RolePermissionSeeder` |
| Resource data | ARMIS owns current capacity, unavailability, competencies, requirements, workload, and actual person-days; IAP tables are historical lineage only | ARMIS planning models/services, `ArmisResourceBackfillService` |
| AEMS consumption | AEMS reads a replaceable `ResourcePlanningGateway` | `ResourcePlanningGateway`, `ConfigurableResourcePlanningGateway` |
| Provider mode | `ARMIS_AUTHORITATIVE` only; historical fallback/shadow values are not active settings | `ConfigurableResourcePlanningGateway`, runtime configuration, and cutover migration |
| ARMIS adapter | Approved/current ARMIS capacity, availability, competency, requirement, assignment, and actual ledgers are exposed in the AEMS gateway shape | `ArmisResourcePlanningGateway` |
| Reports/API | ARMIS-5A provides immutable scope-pinned report runs, protected CSV/PDF exports, private downloads, administration status, and hardening flags | `ArmisReportService`, `ArmisReportController`, `armis_report_runs`, `armis_report_exports` |
| Historical compatibility | Legacy comparison records/routes remain available for migration lineage only; they are not an operational gate and cannot switch provider ownership | `ArmisProviderReconciliationService`, compatibility redirect, ARMIS provider tests |
| Provider monitoring | Immutable health and cutover checks, failure notifications, protected history, and read-only monitoring workspace | `ArmisProviderMonitoringController`, `armis_provider_monitoring_checks`, `tests/e2e/armis-provider-monitoring.spec.js` |
| Security and deployment | Full protected-route regression, migration/provider preflight, private-disk and security-header hardening, and Render smoke verification | ARMIS-7A/7B/7C tests, `ArmisDeploymentCheckCommand`, `scripts/verify-armis-render.ps1` |
| Tests | ARMIS foundation, competency, planning, assignment, report, resource, provider adapter, reconciliation, and desktop/mobile workspace tests protect the module | `backend/tests/Feature/Armis*Test.php`, `tests/e2e/armis-*.spec.js` |

### Fully implemented before ARMIS-1A

The historical IAP resource boundary is retained for lineage and
reconciliation. Current AEMS and IAP scheduling reads use ARMIS capacity,
competencies, availability, requirements, and workload ledgers.

### ARMIS-1A implemented

ARMIS now has the authoritative, scope-aware foundation for resource profiles,
competencies, availability periods, capacity submissions, requirements,
workload allocations, actual person-day versions, and immutable workflow event
timelines. Profiles link to Core users and offices. Competency evidence links
to an exact immutable Core `document_versions` record.

The profile registry API is protected by granular `armis.resource.*`
permissions and supports optimistic locking, draft-to-active lifecycle
transitions, soft archive/restore, office scope filtering, Activity Log,
Audit Trail, and ARMIS workflow events. ARMIS records are consumed directly by
AEMS and IAP scheduling. The legacy IAP Resource Capacity route redirects to
ARMIS Planning and its mutation endpoints are blocked with a replacement link.

### Historical ARMIS-0 baseline: partially implemented

Temporary IAP resource records support AEMS planning but are not a complete
resource registry. They have no ARMIS-owned lifecycle, independent approval,
versioned actuals, utilization reporting, or ARMIS scope model.

### Historical ARMIS-0 baseline: missing at that checkpoint

At the ARMIS-0 checkpoint AIS integration remained future scope. AIS-5D was
implemented later as a separate read-only analytical contract. ARMIS-3A/3B, ARMIS-4A/4B, ARMIS-5A/5B,
ARMIS-6A, and ARMIS-6B provide planning, assignment, actuals, conflict,
notifications, administration status, protected report snapshots, private
exports, adapter reads, reconciliation, authority decisions, and rollback.
The authority boundary is changed only by the explicit ARMIS-6B gate.

## Existing interim data and ownership

The following IAP tables must remain intact for historical plans and backward
compatibility:

- `iap_auditor_capacities`
- `iap_auditor_unavailability`
- `iap_auditor_skills`
- `iap_engagement_skill_requirements`
- planned IAP team allocations

AEMS engagement and assignment actual person-days remain in AEMS tables.
ARMIS-4A adds a separate versioned assignment and actual-person-day ledger; it
does not migrate, rename, remove, or overwrite those records.

## Current provider contract

`ResourcePlanningGateway` is the stable read boundary used by AEMS:

```text
capacityFor
engagementActualPersonDays
assignmentActualPersonDays
skills
requirements
unavailability
status
```

`ConfigurableResourcePlanningGateway` is the current binding. It delegates
active AEMS reads to `InterimIapResourcePlanningGateway` in both supported
ARMIS-6A modes and exposes `ArmisResourcePlanningGateway` as the shadow
adapter for reconciliation. ARMIS writes and approvals use separate
authorized services; the read gateway is not an untracked write path.

## ARMIS-1A design checkpoint

The next phase is the backend foundation only. It should establish normalized,
scope-aware, soft-deletable and auditable tables for:

1. resource profiles linked to Core users and offices;
2. competency catalogue links and verification evidence;
3. availability periods and capacity submissions;
4. workload allocations and resource requirements;
5. actual person-day submissions and immutable approved versions;
6. workflow events, activity/audit records, and notifications.

Profile lifecycle is `Draft -> Active -> Suspended -> Inactive -> Archived`.
Capacity and actuals schemas provide independent submit/review/approve states
and version lineage; their operational review services are reserved for the
later ARMIS phases. Approved versions are designed to become immutable and
corrections must create a new version.

### ARMIS-1A API routes

- `GET /api/armis/metadata`
- `GET /api/armis/resources`
- `POST /api/armis/resources`
- `GET /api/armis/resources/{profile}`
- `GET /api/armis/resources/{profile}/events`
- `PUT /api/armis/resources/{profile}`
- `POST /api/armis/resources/{profile}/transition`
- `POST /api/armis/resources/{profile}/restore`

## Permissions and separation of duties

ARMIS-1A introduces granular `armis.*` permissions for resource,
competency, availability, capacity, workload, actuals, reports, and exports.
The existing `arms.view` and `arms.manage` compatibility permissions must be
preserved. Resource maintainers must not approve their own submissions;
independent reviewers approve verified data. Auditees have no ARMIS write
access, and technical administrators do not receive professional approval by
default.

## Integration sequence

1. Build ARMIS records and APIs without changing IAP/AEMS behavior.
2. Reference approved IAP demand with source and snapshot lineage.
3. Add an ARMIS adapter implementing the existing read gateway.
4. Expose interim, shadow, and gated authoritative provider modes.
5. Reconcile ARMIS and IAP results before any authority switch.
6. Transfer actual-person-day ownership only through an explicit, tested gate.

CMS remains independent. AIS integration remains out of scope; completing ARMIS
does not implicitly enable or authorize an AIS integration.

## ARMIS roadmap

- ARMIS-1A: backend foundation, schema, permissions, authorization, audit, and APIs.
- ARMIS-1B: resource registry and detail workspace.
- ARMIS-2A/2B: competency and certification backend/workspace.
- ARMIS-3A: availability, capacity, workload, utilization backend, revision
  lineage, independent review, locking, notifications, and audit records.
- ARMIS-3B: availability, capacity, workload, calendar, and utilization React
  workspace at `/audit-resource-management/planning`, with responsive
  utilization cards, availability calendar, and permission-aware workflow
  actions.
- ARMIS-4A: assignment and actual-person-day backend, conflicts, capacity rules,
  approvals, revisions, and audit/notification controls.
- ARMIS-4B: assignment and actuals React workspace.
- ARMIS-5A: immutable scope-aware reports, protected CSV/PDF exports, notification
  and administration status, checksum controls, and operational hardening.
- ARMIS-5B: ARMIS reports and administration React workspace.
- ARMIS-6A: IAP/AEMS adapter and controlled fallback/shadow provider boundary.
- ARMIS-6B: immutable shadow reconciliation, independent review, authority
  activation, atomic rollback, permissions, audit, notifications, and APIs.
- ARMIS-6C: protected reconciliation and provider-authority React workspace,
  responsive discrepancy review, separation-of-duties controls, activation and
  rollback dialogs, and browser regression coverage.
- ARMIS-6D: immutable provider health and cutover-verification checks, protected
  monitoring APIs, failure notifications, and a responsive monitoring
  workspace.
- ARMIS-7A: full ARMIS backend/browser regression coverage, route and
  permission security review, immutable-record and protected-download checks,
  and documentation of the verification gate. This phase does not change
  operational workflow behavior, add migrations, or begin deployment
  hardening.
- ARMIS-7B: read-only migration/deployment preflight, Render startup
  hardening, private-disk protection, security headers, and deployment
  regression coverage. This phase does not change ARMIS business workflows or
  provider authority.
- ARMIS-7C: operator-invoked Render smoke verification for health, compiled
  SPA fallback, anonymous ARMIS API rejection, and deployment security
  headers. This phase is read-only and does not bypass authentication.

## ARMIS-1A acceptance

ARMIS-1A is complete when its schema, models, scoped profile API, lifecycle,
optimistic locking, compatibility permissions, audit/event records, tests, and
documentation are verified. Later planning and actuals phases remain
separate; no provider authority switch or AIS integration is part of ARMIS-1A.

## ARMIS-1B frontend checkpoint

ARMIS-1B adds the protected React Resource Registry at
`/audit-resource-management/resources` and the resource detail route at
`/audit-resource-management/resources/{profileId}`. It consumes only the
ARMIS-1A contracts, supports scope-safe search/status filtering, draft profile
creation, optimistic-lock profile editing, lifecycle actions, archive/restore,
and an immutable workflow timeline. The workspace displays the interim provider
warning and does not present ARMIS as authoritative for AEMS.

## ARMIS-2A competency and certification backend checkpoint

ARMIS-2A operationalizes the existing `armis_competencies` foundation table as
a controlled certification ledger. Competencies are selected from the Core
`IAP_AUDITOR_SPECIALIZATION` catalogue (or a future `ARMIS_COMPETENCY`
catalogue) and certification evidence must reference an exact active Core
`document_versions` record. The selected document version remains immutable;
ARMIS stores only its foreign-key identity and evidence metadata.

The competency lifecycle is:

```text
Draft / Returned
    -> Pending Verification
    -> Verified
    -> Expired or Revoked (controlled reviewer actions)
```

Verified records are not edited in place. Corrections use a new revision in
the same competency family, link `supersedes_id` to the prior version, mark
the prior row non-current, and return the new version to Draft. The database
enforces one current revision for a resource and Core competency catalogue
item, while the service enforces the same rule inside a row-locking
transaction.

ARMIS-2A API routes:

- `GET /api/armis/competencies/metadata`
- `GET /api/armis/competencies`
- `POST /api/armis/competencies`
- `GET /api/armis/competencies/{competency}`
- `GET /api/armis/competencies/{competency}/events`
- `PUT /api/armis/competencies/{competency}` (Draft or Returned only)
- `POST /api/armis/competencies/{competency}/submit`
- `POST /api/armis/competencies/{competency}/review`
- `POST /api/armis/competencies/{competency}/revisions`

`armis.competency.manage` covers preparation, draft maintenance, submission,
and correction requests. `armis.competency.verify` is separate and is required
for Verify, Return, and Revoke decisions. A submitter or resource owner cannot
perform the independent review. Each mutation records an Activity Log, Audit
Trail entry, and immutable ARMIS workflow event; submission and review changes
also generate deduplicated in-app notifications through Core.

ARMIS-2A did not add a React workspace; competency and certification screens
were the ARMIS-2B scope. Availability, capacity, workload, actuals, provider
switching, and AIS integration were later phases. ARMIS-3 through ARMIS-7C are
now implemented; AIS integration remains out of scope.

## ARMIS-2B competency workspace checkpoint

ARMIS-2B adds the protected React workspace at
`/audit-resource-management/competencies` and the detail route at
`/audit-resource-management/competencies/{competencyId}`. The registry is
office-scoped through the ARMIS-2A API and provides search, status filtering,
current-claim metrics, resource and Core catalogue selection, credential
metadata, exact Core Document Version evidence references, and immutable
review history.

The detail workspace exposes only backend-authorized actions: Draft/Returned
editing, submission for verification, independent Verify/Return/Revoke
decisions, and correction revision creation from a Verified record. The UI
does not fabricate evidence downloads or bypass Core document authorization;
it displays the exact Core Document Version identity and checksum metadata
returned by the backend. Desktop and mobile browser coverage protects registry
rendering, detail history, draft creation, submission, and independent
verification.

## ARMIS-3B planning workspace checkpoint

ARMIS-3B adds the protected React workspace at
`/audit-resource-management/planning`. One responsive workspace presents four
operational views:

- Overview/Utilization for fiscal-year capacity, approved workload, remaining
  days, utilization percentage, and over-capacity indicators;
- Availability Calendar for current scope-aware periods and their workflow
  statuses;
- Capacity for annual capacity submissions and immutable version actions;
- Workload for planned allocations, source lineage, and capacity-aware review.

The workspace consumes only the ARMIS-3A APIs. It offers create/edit for Draft
and Returned records, submission, independent Approve/Return review, locking,
and correction revisions only when the signed-in user has the corresponding
granular permission. Approved and Locked records are presented as immutable.
Fiscal-year and status filters, search, responsive tables, and hoverable
summary cards are included for desktop and mobile use. The historical ARMIS-3B
checkpoint displayed the then-current `IAP_INTERIM_FALLBACK`; the current
provider is ARMIS after the resource cutover. The workspace does not create
public document URLs.

Focused browser coverage protects both desktop and mobile rendering and the
capacity draft/submission flow in `tests/e2e/armis-planning.spec.js`.

## ARMIS-4A assignment and actuals backend checkpoint

ARMIS-4A operationalizes the previously reserved actuals foundation and adds
the separate `armis_engagement_assignments` and
`armis_assignment_competencies` ledgers. Assignment revisions are linked to an
AEMS engagement and an ARMIS resource profile. Required competencies are
stored as an immutable snapshot on each assignment revision and can also be
copied from an AEMS-scoped ARMIS resource requirement.

Assignments use `DRAFT -> SUBMITTED -> RETURNED/APPROVED -> LOCKED`. Draft and
Returned assignments accept optimistic-lock updates. Approved and Locked
assignments cannot be edited in place; corrections create a new Draft revision
with `supersedes_id` and the previous competency snapshot preserved.

The existing `armis_actual_person_days` table now has assignment lineage,
version/current-revision guards, variance reasons, and creator/updater audit
metadata. Actuals use the same independent review and locking workflow.
Recording requires an approved or locked assignment. Actual periods must be
inside the assignment dates. A variance reason is required when cumulative
actual person-days exceed the approved assignment plan.

Submission and approval enforce these hard rules:

1. the resource profile and Core user are active;
2. the resource office is covered by the engagement offices;
3. a resource cannot have overlapping current ARMIS assignments;
4. approved or locked availability conflicts block assignment approval;
5. an approved ARMIS capacity version is required and cannot be exceeded;
6. engagement planned person-days cannot be exceeded by approved assignments;
7. every required competency must have a current verified ARMIS claim at the
   required proficiency; and
8. submitters and resource owners cannot independently review or approve their
   own records.

The APIs are protected by `armis.assignment.*` and existing
`armis.actuals.*` permissions. Mutations create ARMIS workflow events, Core
Activity Log entries, Audit Trail entries, and after-commit review/outcome
notifications. The ARMIS-4A ledger is consumed through the AEMS
`ResourcePlanningGateway`. Historical IAP/AEMS records are preserved; the
resource backfill never overwrites them.

## ARMIS-4B assignment and actuals React checkpoint

The protected `/audit-resource-management/assignments` workspace consumes the
ARMIS-4A assignment and actuals APIs. It is a dedicated page beside Planning &
Utilization so availability, annual capacity, workload, assignments, and
actual person-days do not become one overloaded registry.

The workspace provides responsive Overview, Assignments, and Actual
Person-Days sections. It supports permission-aware Draft creation, editing of
Draft/Returned records, submission, independent Approve/Return review, locking,
correction revisions, current conflict inspection, search, status filtering,
and empty/loading/retry states. Assignment forms use the authorized AEMS
engagement list when available and retain a safe ID fallback for ARMIS-only
roles. Required competency snapshots are selectable from Core catalogue and
requirement data; the backend remains authoritative for eligibility.

Actuals can only be recorded against an approved or locked assignment. The UI
shows planned versus actual days and explains the required variance reason when
actuals exceed the assignment plan. Historical approved and locked versions
remain read-only; corrections invoke the backend revision endpoints.

ARMIS-4B does not modify AEMS team rows, switch the AEMS provider, expose
public URLs, or make professional conflict/capacity decisions in the browser.
Focused browser coverage belongs in `tests/e2e/armis-assignments.spec.js` and
the existing ARMIS-4A backend regression remains the authoritative workflow
guard.

## ARMIS-5A reports and operational hardening checkpoint

ARMIS-5A adds a backend-only report and administration boundary. The protected
report catalog contains Resource Utilization, Assignment Register, Capacity and
Workload, and Competency Coverage reports. Each generation stores the visible
resource/assignment identifiers, filters, source-query version, result snapshot,
row count, and SHA-256 checksum in an immutable `armis_report_runs` record.

The protected routes are:

- `GET /api/armis/reports`
- `GET /api/armis/reports/runs`
- `GET /api/armis/reports/runs/{run}`
- `POST /api/armis/reports/{report}/generate`
- `POST /api/armis/reports/runs/{run}/exports`
- `GET /api/armis/report-exports/{export}/download`
- `GET /api/armis/administration`

Report viewing requires `armis.report.view`; CSV/PDF generation and download
require `armis.report.export`. CSV values beginning with spreadsheet formula
characters are prefixed defensively. PDF and CSV files are stored on the
private local disk, expose no public storage URL, preserve file size and
checksum metadata, and are re-used idempotently when the original artifact is
available. Report runs and exports cannot be updated or deleted.

The administration contract reports the current office/engagement scope,
available ARMIS permissions, workflow status families, ARMIS notification
counts, provider mode, and hardening guarantees. Existing ARMIS planning,
assignment, actuals, competency, and review notifications remain in-app Core
notifications; no automatic professional decision or provider authority switch
is introduced.

## ARMIS-5B reports and administration React checkpoint

ARMIS-5B adds the protected React workspace at
`/audit-resource-management/reports`. The Reports tab consumes the ARMIS-5A
catalog, generates backend snapshots with supported search/status/office/fiscal
year filters, displays immutable run history and checksums, and creates or
downloads protected CSV/PDF artifacts only when `armis.report.export` is
granted. Downloads use authenticated endpoints; the browser never receives a
public storage URL.

The Administration tab consumes `GET /api/armis/administration` and presents
the interim provider warning, workflow status families, permission contract,
notification counts, authorized scope, and hardening flags. It is a read-only
operational view; it cannot switch providers, approve assignments, change
workflow decisions, or alter report snapshots. The page is responsive and
keeps report tables horizontally scrollable on narrow screens.

Focused browser coverage is in `tests/e2e/armis-reports.spec.js`. At the ARMIS-5B
checkpoint, the workspace did not switch the AEMS `ResourcePlanningGateway`;
provider authority, shadow reconciliation, rollback, and ARMIS-owned actuals
were later ARMIS-6 scope and are now implemented. AIS integration remains out
of scope.

## Historical ARMIS-6A provider adapter checkpoint

ARMIS-6A adds the backend provider adapter and controlled mode boundary. The
`ArmisResourcePlanningGateway` reads only approved/current ARMIS capacity,
availability, competency, requirement, assignment, and actual-person-day
records and maps them to the stable AEMS `ResourcePlanningGateway` contract.

`ConfigurableResourcePlanningGateway` is now the container binding used by
AEMS. The supported runtime modes at that historical checkpoint were:

- `IAP_INTERIM_FALLBACK` — the default; AEMS reads IAP and ARMIS remains a
  separately available adapter;
- `ARMIS_SHADOW` — ARMIS is prepared for comparison, but AEMS still reads IAP.

The runtime configuration key is `armis_provider_mode` and is restricted to
those two values. `ARMIS_AUTHORITATIVE` was intentionally unavailable in the
original ARMIS-6A checkpoint. The integration status reported the active
provider, shadow adapter, supported modes, authority-gate blocker, and
actual-person-day ownership.
Configuration changes use the existing protected Core System Configuration
endpoint, so Activity Log and Audit Trail entries are preserved.

ARMIS-6A does not reconcile records, transfer ownership, switch AEMS authority,
or add AIS integration. Those actions require the later reconciliation and
authority-gate phases. Focused backend coverage is in
`backend/tests/Feature/ArmisProviderAdapterTest.php`.

### Current resource ownership after the cutover

The historical ARMIS-6A text above describes the original fallback boundary.
The current provider is `ARMIS_AUTHORITATIVE`: AEMS and IAP scheduling read
ARMIS. Historical `ARMIS_SHADOW` and `IAP_INTERIM_FALLBACK` values are retained
only in old snapshots and compatibility classes; they are not active resource
paths, runtime options, or UI actions. The cutover migration normalizes existing
runtime configuration to ARMIS and preserves historical IAP records unchanged.

## Historical ARMIS-6B reconciliation and authority-gate checkpoint

This section describes the superseded migration checkpoint. It is retained as
an audit-history reference only. It is not part of the current assignment
workflow: ARMIS is already authoritative, AEMS does not require a recent
reconciliation, and no rollback to IAP is available.

ARMIS-6B adds the protected provider integration API. A reconciliation run
compares the IAP interim provider with the approved/current ARMIS adapter for
capacity, skills, unavailability, requirements, engagement actuals, and
assignment actuals. The run stores the exact filters, authorized office and
engagement scope, normalized result rows, discrepancy keys, and SHA-256
checksum in an immutable `armis_provider_reconciliation_runs` record.

The workflow is:

```text
ARMIS_SHADOW
  → Generate immutable reconciliation snapshot
  → Independent review (every discrepancy explicitly accepted or rejected)
  → Authority approval
  → ARMIS_AUTHORITATIVE
  → Explicit rollback decision
  → IAP_INTERIM_FALLBACK
```

Reviews and provider authority decisions are separate immutable records. The
run generator cannot review its own snapshot, the reviewer cannot activate
authority, and provider authority actions require global office scope and the
dedicated `armis.provider.*` permissions. Direct updates to
`armis_provider_mode` through the generic Core configuration endpoint cannot
set `ARMIS_AUTHORITATIVE`.

The protected routes are:

- `GET /api/armis/provider/status`
- `GET /api/armis/provider/reconciliations`
- `POST /api/armis/provider/reconciliations`
- `GET /api/armis/provider/reconciliations/{run}`
- `POST /api/armis/provider/reconciliations/{run}/review`
- `POST /api/armis/provider/reconciliations/{run}/activate`
- `POST /api/armis/provider/rollback`

Generation and decisions write ARMIS workflow events, Core Activity Log and
Audit Trail records, and in-app notifications. The provider gateway fails
closed to IAP if an authoritative configuration exists without a matching
latest activation decision. ARMIS-6B does not add AIS integration, automate a
professional decision, mutate IAP/AEMS ledgers, or provide a browser-side
authority shortcut. Focused coverage is in
`backend/tests/Feature/ArmisProviderReconciliationTest.php`.

## Historical ARMIS-6C reconciliation and authority React checkpoint

ARMIS-6C adds the protected workspace at
`/audit-resource-management/provider-reconciliation`. It consumes only the
ARMIS-6B provider routes and does not add a second provider-switch path. The
workspace provides:

- current provider mode, active provider, latest reconciliation, and authority
  eligibility status;
- immutable reconciliation history scoped by the backend;
- fiscal-year snapshot generation for users with `armis.provider.reconcile`;
- discrepancy comparison of IAP and ARMIS values, including subject context;
- an independent review dialog that requires an overall decision, a comment,
  and an explicit ACCEPT or REJECT value for every discrepancy;
- an authority activation dialog available only after the backend confirms an
  accepted shadow review and the user has `armis.provider.switch`;
- a rollback dialog available only in authoritative mode to users with
  `armis.provider.rollback`;
- visible separation-of-duties messaging and immutable decision history; and
- responsive layouts that retain horizontal comparison tables without
  exposing public or unprotected endpoints.

The UI disables controls for users without the matching permission, prevents
the displayed generator or reviewer from using the same run for a later
decision where that identity is available, and leaves final enforcement to the
ARMIS-6B service. Activation, rollback, review, and generation remain
authenticated CSRF-protected mutations that write the existing ARMIS workflow,
Core Activity Log, Audit Trail, and notification records. ARMIS-6C does not add
AIS integration, change provider behavior, mutate IAP/AEMS ledgers, or permit
direct runtime-configuration authority changes. Browser coverage is in
`tests/e2e/armis-provider-reconciliation.spec.js`.

## ARMIS-6D provider monitoring and cutover-verification checkpoint

ARMIS-6D adds a read-only operational monitoring boundary at
`/audit-resource-management/provider-monitoring`. It verifies the effective
ARMIS provider mode against runtime configuration, confirms the AEMS active
read path, checks provider consistency, and confirms ARMIS adapter
availability. A check is `HEALTHY` when all checks pass and `FAILED` when a
provider consistency or adapter check fails; reconciliation freshness is not
checked because no reconciliation is required for the sole-provider design.

Every requested check is an immutable, scope-pinned
`armis_provider_monitoring_checks` snapshot with its source query version,
provider/configuration snapshot, diagnostic observations, SHA-256 checksum,
actor, and timestamp. A failed or degraded check sends a protected ARMIS
notification to monitoring users. Checks create ARMIS workflow events and Core
Activity Log/Audit Trail entries, but they never mutate IAP or ARMIS ledgers,
runtime configuration, provider authority, or professional decisions.

The protected routes are:

- `GET /api/armis/provider/monitoring/status`
- `GET /api/armis/provider/monitoring/checks`
- `POST /api/armis/provider/monitoring/checks`
- `GET /api/armis/provider/monitoring/checks/{check}`

Viewing uses `armis.provider.view`; running a check requires the new
`armis.provider.monitor` permission and global office scope. Authority
activation and rollback remain exclusively in ARMIS-6B and the separate
reconciliation workspace. ARMIS-6D does not schedule automated decisions,
switch providers, add AIS integration, or expose public monitoring URLs.
Focused backend coverage is in
`backend/tests/Feature/ArmisProviderMonitoringTest.php`, with desktop/mobile
browser coverage in `tests/e2e/armis-provider-monitoring.spec.js`.

## ARMIS-7A full testing and security review checkpoint

ARMIS-7A is a verification-only gate for the completed ARMIS operational
surface. Every `/api/armis/*` route is required to carry both Sanctum
authentication and a granular `permission:armis.*` middleware. Focused security
coverage also verifies that anonymous callers are rejected and that a role
limited to `armis.provider.view` cannot create resource or monitoring records
or request provider rollback. Provider monitoring and provider-authority
permissions remain separate, preserving the separation of duties established
in ARMIS-6B/6D.

The ARMIS regression gate covers the resource registry, competencies,
planning/utilization, assignments and actuals, reports and protected exports,
provider reconciliation, and provider monitoring workspaces. Backend tests
continue to protect scope filtering, optimistic locking, immutable snapshots
and versions, Core document/checksum lineage, audit/activity records,
notifications, protected downloads, and authority/rollback controls. Desktop
and mobile browser suites exercise the corresponding protected workspaces.

ARMIS-7A does not modify operational workflow behavior, add or alter database
migrations, switch the active provider, or start AIS/ARMIS deployment
integration. Deployment hardening and any migration execution remain a later,
explicitly approved phase.

The ARMIS-7A verification result was 79 protected ARMIS API routes, 35 backend
ARMIS tests with 554 assertions, 217 Feature tests with 3,559 assertions, 15/15
desktop browser tests, 15/15 mobile browser tests, a passing frontend lint,
a passing production build, and a clean `git diff --check` (with only the
repository's existing line-ending warnings).

## ARMIS-7B migration and deployment-hardening checkpoint

ARMIS-7B hardens deployment without introducing another business migration or
changing any ARMIS workflow. The existing ARMIS-1A through ARMIS-6D migrations
remain additive and are applied by the normal `php artisan migrate --force`
startup step. The new read-only `armis:deployment-check --strict` command
verifies that all eight ARMIS migrations are recorded, PostgreSQL is active,
the provider mode is valid, authoritative mode has an immutable activation
decision when applicable, the application key and HTTPS URL are configured,
debug output is disabled, runtime directories are writable, and the default
document disk is private.

Render startup runs the strict preflight only when
`ARMIS_DEPLOYMENT_CHECK=true`, after migration, approved seeders, and Laravel
configuration/view caching. Local Docker checks remain opt-in and can use the
non-strict command with SQLite and an HTTP URL. The private local disk no
longer enables framework-generated serving routes; evidence and report files
continue to use authenticated, scope-aware Laravel download controllers.
Apache adds baseline `nosniff`, same-origin framing, referrer-policy, and
permissions-policy headers. No public document URL or `storage:link` is
created by the Render image.

ARMIS-7B does not switch provider authority, rewrite ARMIS/IAP records, add
AIS integration, or add a background worker/scheduler. Deployment operators
must still use durable private object storage before retaining evidence or
reports operationally on Render Free.

ARMIS-7B verification passed with 38 ARMIS tests and 567 assertions, 220 full
Feature tests and 3,572 assertions, frontend lint, production build, PHP
syntax checks, and `git diff --check`. The ARMIS report/export browser contract
passed on desktop and mobile after a one-minute test-server login-rate-limit
cooldown; the isolated mobile protected-workspace check passed 1/1. Docker
image execution could not be run because the local Docker daemon was
unavailable.

## ARMIS-7C post-deployment smoke-verification checkpoint

ARMIS-7C adds the operator-invoked
`scripts/verify-armis-render.ps1 -BaseUrl https://<service>.onrender.com`
smoke gate. It verifies the unauthenticated `/health` response, the compiled
AGIS root shell, a nested ARMIS React route through the Apache SPA fallback,
anonymous rejection of `/api/armis/provider/status`, and the four baseline
browser security headers. The script requires an HTTPS base URL, reports each
check independently, and exits non-zero when any check fails.

The smoke gate performs no login, write, migration, provider, authority, or
data operation. It is intended to run after each Render deploy and after
changing environment variables. A successful smoke run complements the
server-side `armis:deployment-check --strict` preflight; it does not replace
the full backend and browser regression suites.

The current repository regression baseline is 38 focused ARMIS tests with 570
assertions and 220 full Feature tests with 3,575 assertions. Frontend lint and
production build pass, and `git diff --check` reports no whitespace errors
(only the repository's normal line-ending conversion warnings).
