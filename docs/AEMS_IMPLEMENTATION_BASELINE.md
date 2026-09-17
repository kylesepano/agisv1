# AEMS Implementation Baseline and Design Contract

## 1. Purpose and status

This document is the AEMS-0 baseline for completing the Audit Engagement
Management module. It records the current as-built state, the screen and
navigation contract, the cross-module boundaries, and the migration and
verification rules. The professional decisions that were previously implicit
or unresolved are now consolidated in the [AEMS-G0 Governance and Conformance
Contract](AEMS_GOVERNANCE_AND_ACCEPTANCE.md).

The source code and automated tests remain authoritative for current behavior.
The MDS-200, UID-200, and DGM-200 reference artifacts define the target design,
but they are still draft/review documents and are not evidence that every
planned screen or entity already exists.

This phase is documentation and contract work only. It does not change
operational workflow behavior, database structure, permissions, or routes.

The AEMS-1A foundation contract, AEMS-1B React shell, AEMS-2A/2B planning
package, AEMS-3A/3B team and safeguard workspaces, AEMS-4A/4B fieldwork
records and execution workspace, AEMS-5A/5B evidence request/assessment
workspace, AEMS-6A/6B issue/finding controls, AEMS-7A/7B conference/dialogue
work queues, AEMS-8A/8B reporting, and AEMS-9A/9B completion/transfer workspace
are implemented as additive follow-on phases.
Their current contracts and verification notes are recorded at the end of this
document and in the workflow/API references.

## 2. Reference artifacts

| Artifact | Current reference status | Use in this contract |
| --- | --- | --- |
| MDS-200 AEM Module Design Specification v0.8 | Draft for discussion | Business scope, entities, controls, lifecycle, integrations |
| UID-200 Audit Engagement Management UI Design Specification v0.5 | Draft for review | Screen IDs, engagement navigation, UI responsibilities, role-aware actions |
| DGM-200-01 AEM Master SCR and Event Workflow Diagram v0.2 | Draft for review | Cross-screen sequence, events, exceptions, and lifecycle relationships |

The reference artifacts use AEM (Audit Engagement Management). The application
uses AEMS (Audit Engagement Monitoring System) in routes, permissions, tables,
services, and navigation. The compatibility contract is to retain the `AEMS`
and `aems.*` identifiers while using “Audit Engagement Management” as the
descriptive display name where a clearer label is useful.

## 3. Current as-built baseline

The following capabilities are implemented in the current source and protected
by existing tests:

- IAP-sourced and special engagement creation, authorization, lineage, and
  duplicate-source prevention;
- engagement registry, filtering, scope access, archive, and restoration;
- audit team assignment and assignment history;
- AEO and AEP review, approval, issuance, versioning, and locking;
- Audit Program and procedure management;
- Entry and Exit Conference workflows;
- Working Paper versions, required audit content, approval locking, and
  corrections through revisions;
- Core Document-backed evidence with checksum, size, MIME type, confidentiality,
  versioning, and links to Working Papers, Issues, and Findings;
- Issue validation, dismissal, and conversion to Finding;
- Finding, Recommendation, Auditee Response, Auditor Rejoinder, and dialogue
  workflows;
- Draft and Final Report generation, approval, issuance, PDF protection, and
  idempotent CMS transfer;
- completion assessment, closure checklist, closure, retention/index metadata,
  lessons learned, controlled engagement reopening, CMS transfer manifests,
  transfer exception reconciliation, and ARMIS effort reconciliation;
- dashboard cards and a derived operational progress tracker;
- role, assignment, office, confidentiality, separation-of-duties, optimistic
  locking, Activity Log, Audit Trail, and workflow notifications.

G10C adds dedicated operational queue, calendar, and register/export surfaces
over these contracts. See [AEMS Governance and Acceptance](AEMS_GOVERNANCE_AND_ACCEPTANCE.md).
G10D adds the dedicated Records and Administrative Closure workspace over the
existing retention, records, closure-checklist, archive, legal-hold, and
disposition services. See [AEMS Governance and Acceptance](AEMS_GOVERNANCE_AND_ACCEPTANCE.md).

The current implementation is the accepted operational AEMS contract for the
approved MDS/UID scope. G10C and G10D close the dedicated queue/output-surface
and records/administrative-closure gaps, and G10E closes the governance and
verification gate. AIS integration and explicitly reserved/reference-only
screens remain outside this scope.

## 4. Target entities and remaining design scope

The source contains the approved operational AEMS entities and workflows. The
G0 contract remains the authoritative decision and compatibility record; G10E
adds the semantic Rule 1–35 acceptance suite, SCR registry checks, role/menu
matrix, and final regression evidence. Reserved SCR-243, AIS, and any future
reference-only entity remain explicit documented boundaries rather than hidden
gaps.

The completed phase checkpoints below remain historical evidence of what each
increment added. G10E is the current acceptance checkpoint and supersedes
earlier historical statements about work that had not yet started.

## 4.1 AEMS-G2 foundation implementation

G2A/G2B adds the reviewed one-office backfill and database uniqueness
invariant, structured Area/Focus coverage metadata, engagement boundaries and
limitations, source-variance decisions, the IAP risk-source discriminator,
special/emergency authorization classification, Runtime Configuration-backed
engagement numbering, and the distinct aggregate `COMPLETED` state. SCR-212 is
implemented as a contextual Define Engagement Scope workspace at
`?tab=scope`; it is not a duplicate sidebar page. The visible module name is
Audit Engagement Management while AEMS compatibility identifiers remain.

The G2 migration records legacy office rows before reconciling duplicate pivot
links. A record with no historical office remains explicitly marked for
authorized review rather than receiving an invented office. The focused G2
tests and existing AEMS registry/lifecycle tests are the conformance gate.

## 5. Fixed design decisions

### 5.1 Engagement office rule

The target rule is exactly one Engagement Office. Existing records that contain
multiple office links must be handled by a reviewed backfill and compatibility
strategy before a database constraint is tightened. No existing record may be
silently reassigned or deleted.

### 5.2 Phase and administrative status

Future AEMS records must expose both:

- lifecycle phase: Foundation, Planning, Execution, Issues/AFR, Conferences,
  Reporting, Completion/Transfer, or Closure;
- administrative status: Draft, Returned, Approved, Issued, Suspended,
  Cancelled, Closed, and other controlled record-specific states.

The dashboard may derive progress stages, but it must not become the source of
truth for a record's state.

### 5.3 Finding structure

The target Finding structure is:

- Criteria;
- Condition;
- Conclusion;
- Cause;
- Effect/Significance/Risk;
- Risk rating;
- Evidence and Working Paper references;
- Responsible Office;
- Management Response;
- Auditor Rejoinder;
- Recommendation.

MDS-200 and UID-200 require Conclusion. DGM-200 omits it in one workflow box;
the detailed MDS/UID requirement controls until the reference set is formally
revised.

### 5.4 Planning ownership

IAP remains the source of approved engagement authorization and source lineage.
AEMS owns engagement-specific planning artifacts and approved snapshots.
AEMS must not mutate an approved IAP plan.

The two existing IAP risk systems remain distinct and are not replaced by the
AEMS engagement Risk Matrix:

- `iap_risk_assessments`;
- `iap_universe_risk_assessments`.

Cross-module references and traceability are required; migration or removal of
either IAP risk system is out of scope for AEMS-0.

### 5.5 Evidence ownership

Evidence Requests and Audit Evidence are separate concepts and lifecycles.
File content and immutable file versions continue to use Core
`document_versions`. AEMS adds request, assessment, custody, restriction, and
traceability records around those Core documents.

### 5.6 Reporting stages

The target report stages are:

```text
Interim Report → Draft Report → Final Report → Issued/Distributed
```

Issued versions are immutable. Corrections, amendments, withdrawal, or
supersession create controlled successor versions.

### 5.7 CMS and ARMIS boundaries

- AEMS owns findings, recommendations, management comments, auditor rejoinders,
  reports, and the transfer snapshot.
- CMS owns Action Plans, monitoring, validation, dispositions, and closure of
  transferred recommendations.
- ARMIS is the sole provider for competency, availability, workload, planned
  person-days, and actual person-days.
- IAP resource values remain historical planning lineage and reconciliation
  evidence only; fallback and shadow modes are not active.
- Missing or stale ARMIS data blocks team/AEO/fieldwork approval.
- AIS integration is not part of this AEMS completion roadmap.

### 5.8 No duplicate AFR module

The existing Findings & Recommendations page remains the canonical AFR
workspace. AEMS phases extend its data and workflow contracts rather than
creating a second competing AFR module.

### 5.9 Team safeguards and specialist roles

AEMS team assignments support `SPECIALIST` and `AUTHORIZED_PARTICIPANT` in
addition to the four core lifecycle roles. Each active assignment must carry
current Objectivity, Conflict-of-Interest, and Independence declarations before
an explicit safeguard assessment can be approved. Declarations and safeguard
assessments are versioned and append-only; accepted records are corrected by
revision. ARMIS provides the authoritative competency, certification,
availability, capacity, planned-days, and actual-days data. IAP resource values
remain historical lineage only and are never represented as the active provider.

## 6. Navigation and screen contract

### 6.1 Global sidebar groups

The AEMS sidebar will use these groups and canonical destinations:

```text
AEMS Dashboard
Engagement Registry
Engagement Foundation: Audit Team, Engagement Orders, Engagement Scope
Planning: Planning Package, AEP, Process Flow, Risk Matrix, Audit Program
Execution: Fieldwork Records, Working Papers, Evidence Requests, Audit Evidence
Issues and AFR: Audit Issues, Findings and Recommendations,
  Auditee Responses, Auditor Rejoinders
Conferences: Entry Conferences, Exit Conferences
Reporting: Interim Reports, Draft Reports, Final Reports, Report Distribution
Completion and Transfer: Completion Assessment, CMS Transfer, Closure,
  Reopen Requests, Activity and Audit Trail
```

The React AEMS navigation registry now carries the DGM SCR identifier beside
each visible destination. The reporting group exposes one sidebar destination:
the SCR-250 Audit Reporting Workspace. Its SCR-251 Interim Audit Report,
SCR-252 Draft Audit Report, SCR-253 Final Audit Report, and SCR-254 Report
Distribution views remain contextual stages inside that workspace. They reuse
the protected `/reports` route and select a stage with a query parameter; they
do not create duplicate React route registrations or duplicate sidebar items.

The visible labels remain task-oriented where the DGM screen is a contextual
detail (for example, Findings & Recommendations and Auditee Responses), while
the registry retains the authoritative DGM label and legacy screen identifier
for traceability. SCR-211–214, SCR-220, SCR-224, SCR-227, SCR-231–232,
SCR-242, SCR-244, and SCR-260–263 remain engagement-contextual actions and are
opened from the registry or engagement workspace tabs. They are not added as
standalone sidebar links that would lose engagement scope or point to a
non-canonical route.

Registry parent-tab values follow the DGM lifecycle contract: Planning,
Execution, Audit Issues, AFRs, Conferences, Audit Reports, and Completion &
Transfer. The sidebar grouping (Foundation, Planning, Execution, Issues & AFR,
Conferences, and Reporting) is a presentation grouping and does not replace
the SCR parent-tab relationship.

Menu visibility is controlled by backend permissions, engagement scope,
assignment, confidentiality, phase, and available action rules. React menu
visibility is a usability aid only; Laravel remains authoritative.

### 6.2 Engagement-centered tabs

SCR-220 is the stable engagement center. Its lifecycle tabs are:

```text
Overview
Planning
Execution
Audit Issues
AFRs
Conferences
Audit Reports
Completion & Transfer
Activity
```

Procedure details, Create Issue, Evidence assessment, Rejoinder, Distribution
Decision, and Close/Reopen are contextual screens opened from these tabs rather
than unrelated global menu entries.

The `Completion & Transfer` tab is the AEMS-9B workspace. It is separate from
the formal `Closure` tab so transfer and effort reconciliation can be reviewed
before a closure record is submitted.

### 6.3 Route compatibility

Existing AEMS routes remain valid during migration. New screens must use one
canonical route registry and an engagement-centered route pattern, for example:

```text
/audit-engagement-management/:engagementId
/audit-engagement-management/:engagementId/planning
/audit-engagement-management/:engagementId/execution
/audit-engagement-management/:engagementId/issues
/audit-engagement-management/:engagementId/afrs
/audit-engagement-management/:engagementId/conferences
/audit-engagement-management/:engagementId/reports
/audit-engagement-management/:engagementId/completion-transfer
/audit-engagement-management/:engagementId/activity
```

Existing portfolio routes such as the Engagement Registry, AEMS Dashboard,
Working Papers, Findings, Conferences, and Reports remain supported as
authorized deep links. No duplicate explicit/fallback route registration is
permitted.

### 6.4 SCR registry

Each controlled screen must have:

- SCR identifier;
- canonical route;
- navigation group;
- parent engagement tab;
- required view and mutation permissions;
- scope requirements;
- allowed lifecycle phases;
- available actions;
- loading, empty, error, and unauthorized states;
- focused frontend and backend tests.

The DGM sequence is a workflow relationship, not permission to bypass the
engagement context or backend transition guards.

## 7. Workflow contract

All future AEMS workflows must use action-based transitions. Clients submit an
action and lock version, never an arbitrary target status.

Every transition must validate:

1. authenticated user and active account;
2. permission and role;
3. engagement, office, assignment, and confidentiality scope;
4. current record status and lifecycle phase;
5. prerequisite records and required document versions;
6. separation of duties;
7. optimistic lock version;
8. transaction and idempotency rules;
9. Activity Log and Audit Trail event creation;
10. notification dispatch after successful commit.

Approved and issued records are immutable. Corrections create new versions or
controlled successor records.

## 8. Cross-module contract

| Module | AEMS contract |
| --- | --- |
| Core | Reuse users, offices, roles, permissions, master lists, documents, workflows, notifications, Activity Log, Audit Trail, runtime configuration, and protected downloads |
| IAP | Supplies approved engagement authority, plan lineage, source scope, and existing risk references; AEMS reads approved source snapshots |
| ARMIS | Supplies competency, availability, workload, planned/actual person-days, assignment data, reconciliation, and provider status |
| CMS | Receives only formally issued/finalized recommendation snapshots; AEMS does not edit CMS Action Plans or monitoring state |
| AIS | AIS-5D verified integrated hardened read-only analytical dashboard, source-health diagnostics, immutable integration snapshots, responsive source-health workspace, review indicators, immutable reports, protected exports, and audit/rate-limit controls are implemented outside the AEMS roadmap; AEMS remains a source module and no AIS operational write or professional decision is enabled |

No module may create a second copy of Core documents, permissions, audit logs,
or notification infrastructure unless an explicit architecture decision is
approved.

## 9. Migration and compatibility strategy

Future implementation must follow these rules:

- migrations are additive and reversible where practical;
- existing columns and compatibility aliases are preserved;
- existing routes and permissions remain supported during transition;
- historical records are backfilled with explicit migration metadata;
- database constraints are added only after data-quality review;
- immutable records are never rewritten to fit the new model;
- old records receive a documented legacy/unknown value where a new field cannot
  be safely inferred;
- foreign keys, unique indexes, soft deletion, and lock/version columns are
  added before exposing new mutation actions;
- production migration rehearsal occurs before deployment;
- no migration removes either IAP risk system.

## 10. Verification contract

Each implementation phase requires all of the following before the next phase:

- focused backend Feature tests;
- permission and scope tests;
- concurrency and idempotency tests where applicable;
- React lint and production build;
- focused Playwright or responsive UI tests;
- full backend regression suite;
- route and permission inventory review;
- `git diff --check`;
- updated workflow, API/data, System Flow, operations, and end-to-end docs;
- an explicit checkpoint recording files changed, commands run, and exact
  results.

### AEMS-12 final verification status

The final verification gate covers scope and separation controls, IAP lineage,
planning traceability, fieldwork completion, evidence assessment/checksums,
immutable versions, issue/finding corrections, report issuance, CMS transfer
idempotency, closure/reopening, soft deletion, and activity/audit logging.
The canonical desktop/mobile AEMS browser suites cover sidebar navigation,
engagement lifecycle, planning, execution, evidence, issues/findings,
reporting, and completion/closure. `src/services/api.js` redacts SQLSTATE and
database-driver diagnostics before they reach user-facing error surfaces.

The final AEMS acceptance gate must demonstrate the full chain:

```text
IAP authorization
→ AEMS planning package
→ team safeguards and ARMIS resources
→ fieldwork and procedures
→ Working Papers and Evidence Requests
→ assessed evidence
→ Issues and Findings
→ Management Comments and Rejoinders
→ Conferences
→ Interim/Draft/Final Reports
→ Distribution
→ CMS transfer
→ Completion and Closure
→ Controlled Reopening when authorized
```

## 11. AEMS-0 exit criteria

AEMS-0 is complete when:

- the current as-built state is documented without overclaiming missing entities;
- target entities and explicit gaps are recorded;
- office, phase/status, Finding Conclusion, evidence, reporting, CMS, ARMIS,
  and AIS decisions are fixed;
- global sidebar and engagement tab navigation are defined;
- the SCR route/permission contract is defined;
- the [AEMS Governance and Acceptance](AEMS_GOVERNANCE_AND_ACCEPTANCE.md)
  resolves the authority, status, evidence, retention, signature/transmittal,
  planning-unit, completion, and rule-to-code-to-test decisions;
- migration and compatibility rules are defined;
- cross-module boundaries are documented;
- later implementation phases have measurable acceptance gates;
- the documentation map links this baseline as the AEMS implementation
  contract.

No database, route, permission, or operational workflow changes are included in
AEMS-0.

## 12. AEMS-0 verification baseline

The documentation-only AEMS-0 changes were originally checked with:

| Check | Result |
| --- | --- |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `git diff --check` | Passed |

| `php artisan test --filter=AemsFoundationTest --testdox` | Passed: 3 tests, 66 assertions |
| `php artisan test --filter=Aems` | Historical AEMS-0 run exposed 36 passed, 3 order-dependent failures, 709 assertions; each affected test class passed independently |
| `php artisan test --testsuite=Feature` | Did not complete within the 180-second verification window |

The three historical combined-run failures predated the AEMS-0 documentation
changes and were recorded as test-isolation findings. A later combined run
completed successfully during AEMS-1B verification, so they are no longer an
active AEMS-filter regression:

1. `AemsCompletionClosureTest::formal assessment closure and atomic closed transition`
   reports a false closure-readiness result.
2. `AemsIssueFindingRecommendationTest::finding dialogue finalizes and locks recommendations`
   receives HTTP 404 when downloading the response/rejoinder dialogue
   attachment.
3. `AemsReportTest::report versions review issuance confidential download and cms transfer`
   receives HTTP 404 when downloading an issued report version.

The affected classes pass independently (`AemsCompletionClosureTest`,
`AemsIssueFindingRecommendationTest`, and `AemsReportTest`). No operational
code was changed to work around the historical result during AEMS-0.

## 12.1 AEMS-1B verification refresh

| Check | Result |
| --- | --- |
| `php artisan test --filter=Aems --testdox` | Passed: 41 tests, 745 assertions |
| Focused AEMS foundation/registry/lifecycle/dashboard tests | Passed: 16 tests, 249 assertions |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `npx.cmd playwright test tests/e2e/aems-shell.spec.js --project=desktop-chrome --project=mobile-chrome` | Passed: 2 tests |
| `npx.cmd playwright test tests/e2e/aems-responsive.spec.js --project=desktop-chrome --project=mobile-chrome` | Passed: 6 tests |
| `git diff --check` | Passed |

The full `php artisan test --testsuite=Feature` suite was also attempted with a
300-second window but did not produce a summary before timing out. AEMS-1B
therefore gates only on the complete AEMS-filtered suite plus focused UI and
backend checks above. AEMS-2A and AEMS-2B are now implemented and have their
own focused gates; AEMS-3A is now implemented and has the checkpoint below.

## 13. AEMS-1B implementation checkpoint

AEMS-1B implements the React shell and registry experience against the AEMS-1A
contract:

- grouped AEMS sidebar navigation for the implemented Portfolio, Foundation,
  Planning, Execution, Issues & AFR, Conferences, and Reporting screens;
- exported `aemsScreenRegistry` route/SCR inventory with screen identifiers,
  groups, parent tabs, and view permissions;
- an engagement-centered workspace tab bar that deep-links to existing
  authorized portfolio pages without removing compatibility routes;
- registry filters and status presentation for lifecycle phase,
  administrative status, canonical office, and legacy multi-office scope;
- detail-page presentation of the phase, administrative status, office rule,
  and engagement-centered tabs.

Reference-only screens that do not yet have backend contracts are not exposed as
dead links. They remain future AEMS phases. This phase adds no new database
tables, permissions, operational transitions, or document behavior.

## 14. AEMS-2A Planning Package backend checkpoint

AEMS-2A adds the additive Planning Package contract: immutable versioned
survey/process-flow/objective/risk records; risk links to objectives, program
procedures, and working-paper references; exact Core Document Version
references; preserved IAP lineage; readiness checks; independent review;
separate approval; return/resubmit and formal revision; package permissions;
events, Activity Log, Audit Trail, notifications; and the aggregate
`START_FIELDWORK` approval gate.

Focused verification:

| Check | Result |
| --- | --- |
| `php artisan test --filter=AemsPlanningPackageTest --testdox` | Passed: 2 tests, 20 assertions |
| `php artisan test --filter=AemsEngagementLifecycleTest --testdox` | Passed: 5 tests, 77 assertions |
| Migration dry-run and application | Passed: `2026_08_12_000000_create_aems_planning_package_tables` |
| PHP syntax checks | Passed |

## 15. AEMS-2B Planning Package UI checkpoint

AEMS-2B adds the engagement-scoped React Planning Package workspace at
`/audit-engagement-management/planning-package`. The workspace keeps the shared
engagement navigation visible and provides the following sections without
leaving the engagement context: overview and objectives, preliminary survey,
process-flow editor/viewer, risk-matrix register and item detail, traceability,
backend readiness checks and review queue, and immutable version history with
side-by-side section comparison. Users can create drafts, save immutable
versions, submit, independently review, return for revision, resubmit, approve,
start a formal revision, and inspect approved snapshots. Approved versions are
presented as read-only and cannot be changed from the UI.

The UI consumes the AEMS-2A API; authorization, readiness, scope, separation of
duties, optimistic locking, document-version references, and fieldwork gating
remain server-enforced. No new AEMS-2B tables or workflow states are introduced.

## 16. AEMS-3A Team, safeguards, and ARMIS backend checkpoint

AEMS-3A adds:

- `SPECIALIST` and `AUTHORIZED_PARTICIPANT` assignment roles while retaining
  the four lifecycle roles required by AEO and fieldwork gates;
- immutable, versioned Objectivity, Conflict-of-Interest, and Independence
  declarations for every active assignment;
- exact Core `document_versions` evidence links and correction-by-revision;
- independent declaration review and a separate immutable safeguard assessment
  decision;
- ARMIS provider status and planned/actual person-day reconciliation in the
  readiness contract; historical provider reconciliation is not a readiness
  prerequisite;
- competency/certification, capacity, workload overlap, leave/training, and
  independence blockers;
- ARMIS-only operational behavior. Historical shadow/fallback snapshots never
  approve a team; missing or stale ARMIS data blocks team/AEO/fieldwork approval;
- AEMS events, Activity Log, Audit Trail, notifications, scoped permissions,
  API routes, and focused regression tests.

The backend routes are:

```text
GET  /api/aems/engagements/{engagement}/team/safeguards
POST /api/aems/engagements/{engagement}/team/safeguards/assess
POST /api/aems/engagements/{engagement}/team/safeguards/approve
POST /api/aems/engagements/{engagement}/team/{member}/safeguards/declarations
POST /api/aems/engagements/{engagement}/team/{member}/safeguards/declarations/{declaration}/review
```

Focused verification:

| Check | Result |
| --- | --- |
| `php artisan test --filter=AemsTeamSafeguardTest --testdox` | Passed: 4 tests, 67 assertions |
| `php artisan test --filter=AemsTeamAeoTest --testdox` | Passed: 3 tests, 48 assertions |
| `php artisan test --filter='ArmisProviderAdapterTest\|AemsTeamSafeguardTest' --testdox` | Passed: 8 tests, 94 assertions |
| `php artisan test --filter=Aems --testdox` | Passed: 47 tests, 836 assertions |
| `php artisan test --testsuite=Feature --stop-on-failure` | Passed: 228 tests, 3,678 assertions |
| `php artisan migrate --force` | Passed: `2026_08_20_000000_create_aems_team_safeguard_tables` |

The final Specialist/Authorized Participant visibility expansion is covered by
the post-change focused access/safeguard run (9 tests, 113 assertions). A
subsequent full-suite wrapper attempt exceeded its 360-second wait without
emitting a test failure; the last completed full Feature run immediately before
that visibility-only change passed 228 tests and 3,678 assertions.

## 17. AEMS-3B Team and resource workspace checkpoint

AEMS-3B consumes the AEMS-3A contracts in the existing engagement Team page.
The workspace provides:

- an ARMIS-authoritative provider status and planned-versus-actual effort
  summary; historical fallback/shadow modes are not operational;
- planned-versus-actual person-day cards and a competency, certification,
  availability, workload, and overlap matrix;
- explicit readiness blockers with the responsible resolver, plus warnings and
  per-check status so a user can see why approval is unavailable;
- Objectivity, Conflict-of-Interest, and Independence declaration forms,
  version-aware review/return actions, and exact Core Document Version IDs;
- immutable pending assessment and final approval actions with visible
  assessor/approver separation and assessment version history;
- assignment history retained alongside the safeguards panel, without changing
  the existing assignment workflow or creating duplicate tables/routes.

All writes remain server-enforced by the AEMS-3A permissions and engagement
scope rules. The page is a presentation and action client for the existing
backend; it does not approve assignments automatically and it does not add AIS
or ARMIS integration behavior.

Frontend verification: `npm.cmd run lint`, `npm.cmd run build`, and
`git diff --check` passed after the AEMS-3B workspace changes. The focused
backend safeguards tests remain the gate for the underlying workflow.

## 18. AEMS-4A Fieldwork Records backend checkpoint

AEMS-4A adds the execution-record contract beneath the existing Audit Program:

- versioned Fieldwork Records for interviews, observations, walkthroughs,
  inspections, testing, sampling, and analysis;
- date/location, participants, Audit Area/Focus, procedure, related task and
  record, Working Paper version, and Evidence traceability;
- exact Core Document Version lineage through Audit Evidence;
- draft, submit, return/resubmit, independent review, finalization, and formal
  correction-by-revision behavior with immutable content versions;
- procedure fieldwork status, results, conclusion, review state, related
  records/tasks, completion actor/time, and finalized-record counters;
- atomic optimistic-lock updates, engagement scope checks, role separation,
  events, Activity Log, Audit Trail, and notifications;
- the hard gate that rejects an Audit Program procedure transition to
  `COMPLETED` unless a finalized Fieldwork Record is linked to that procedure.

The backend API is available at `/api/aems/engagements/{engagement}/fieldwork`
and its update/transition child routes. AEMS-4B consumes this contract through
the engagement-scoped Execution Workspace; it does not duplicate workflow or
authorization logic in React. The focused backend gate remains
`AemsFieldworkRecordTest` (versioning, independent review, finalization,
procedure completion, and the blocked-completion path).

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `php artisan test --filter=AemsFieldworkRecordTest --testdox` | Passed: 2 tests, 20 assertions |
| AEMS regression (`--filter=Aems`) | Passed: 49 tests, 856 assertions |
| `php artisan test --testsuite=Feature --stop-on-failure` | Passed: 230 tests, 3,698 assertions |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `php artisan migrate --force` | Passed: no pending migrations after AEMS-4A migration application |
| `git diff --check` | Passed |

## 19. AEMS-4B Execution Workspace UI checkpoint

AEMS-4B adds `/audit-engagement-management/execution` and the `Execution
Workspace` navigation entry. The page keeps engagement, procedure, and record
context in query parameters and consumes the AEMS-4A workspace response. It
provides the procedure execution panel, Fieldwork Record list/detail, immutable
version and event timeline, reviewer notes, task and due-date capture,
Working Paper/Evidence traceability, overdue/blocker visibility, and direct
links to the Audit Program, Working Papers/Evidence, and Issues pages. A
Create-Issue-from-Fieldwork action copies exact linked version identifiers but
does not bypass the independent Issue workflow.

The page uses the existing `aems.fieldwork.view/create/review/finalize`,
`aems.working-paper.view`, and `aems.issue.view/create` permissions and the
existing protected API. Server readiness, engagement scope, separation of
duties, optimistic locking, immutable versions, and procedure-completion gates
remain authoritative.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `npx.cmd playwright test tests/e2e/aems-execution.spec.js --project=desktop-chrome` | Passed: 1 test |
| `git diff --check` | Passed |

## 20. AEMS-5A Evidence Requests and assessment backend checkpoint

AEMS-5A introduces Evidence Requests as a separate engagement-scoped record;
they are not a status alias for `audit_evidence`. A request stores its own
immutable request versions and follows:

```text
DRAFT -> SUBMITTED -> SENT -> PARTIALLY_RECEIVED -> RECEIVED -> ASSESSED -> CLOSED
```

Received evidence is linked to the exact current `audit_evidence` row and its
exact Core `document_versions` row. The request cannot be assessed until every
received link has a current eligible assessment. Assessment versions are
immutable snapshots and preserve the full professional assessment contract:
sufficiency, appropriateness, relevance, reliability, competence, accuracy,
completeness, corroboration, contradiction, authenticity, integrity,
confidentiality, restrictions, limitations, and evidence gaps.

Restricted evidence (or evidence with access restrictions) is not eligible for
finalized findings until a separate authorized exception decision is recorded.
The assessor cannot approve that exception. Evidence assessment and exception
approval use independent permissions and optimistic locking. New evidence
uploaded through `AemsEvidenceService` sets `assessment_required`; direct
legacy evidence rows retain `false` as a compatibility marker so existing
historical fixtures are not silently invalidated. New evidence therefore must
pass assessment before Finding validation, while legacy rows remain governed by
their pre-existing verification/locking controls.

The backend endpoints are documented in the API reference. All mutations are
engagement-scoped, assignment/scope checked, evented, written to Activity Log
and Audit Trail, and notified through Core. AEMS-5A adds no React page; the
future UI must consume these contracts rather than reproduce workflow rules.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `php artisan test --filter=AemsEvidenceRequestTest --testdox` | Passed: 2 tests, 21 assertions |
| `php artisan test --filter='Aems' --testdox --no-coverage` | Passed: 51 tests, 877 assertions |
| `php artisan test --testsuite=Feature --no-coverage` | Passed: 232 tests, 3,719 assertions |
| `php artisan migrate --force` | Passed: AEMS-5A migrations applied |
| `git diff --check` | Passed |

## 21. AEMS-5B Evidence Management workspace checkpoint

AEMS-5B adds the dedicated `/audit-engagement-management/evidence` page under
the AEMS Execution group. It is separate from the existing Working Papers
page, but links back to it and to Fieldwork, Issues, Findings, and Reports.
The page consumes the AEMS-5A Evidence Request and assessment APIs for request
register/detail, correspondence and immutable request versions, partial and
complete receipt, assessment capture, evidence gaps, restricted evidence,
exception status, custody/checksum metadata, and Evidence family comparison.

Summary cards distinguish requested, received/partial, assessed, restricted,
and accepted-for-reporting evidence. Request and evidence details keep exact
Core `DocumentVersion` identifiers visible. The page never calculates final
eligibility or bypasses the server-side request transition, assessment,
exception, confidentiality, scope, or optimistic-lock decisions.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `npx.cmd playwright test tests/e2e/aems-evidence-management.spec.js --project=desktop-chrome` | Passed: 1 test |
| `git diff --check` | Passed |

## 22. AEMS-6A Issues, Findings, and Recommendations backend checkpoint

AEMS-6A expands validated Issue dispositions without breaking the legacy
`DISMISSED`/`CONVERTED_TO_FINDING` status contract. Structured disposition
metadata now records conversion, merge, resolution during audit, observation,
referral, closure without finding, and dismissal outcomes. Dispositions are
terminal, engagement-scoped, independently authorized, and evented.
At the AEMS-6A checkpoint the runtime permission catalogue contained 121
`aems.*` operations (382 permissions across Core, IAP, AEMS, CMS, and ARMIS),
including the new
disposition and revision permissions.

Findings now support conclusion and significance/effect classifications,
direct links to exact finalized Fieldwork Record versions, revision history,
correction/amendment/supersession/withdrawal reasons, and immutable revision
snapshots. The protected revision endpoint is:

```text
POST /api/aems/engagements/{engagement}/findings/{finding}/revisions
```

It creates a new family revision atomically and never overwrites the prior
Finding or finalized recommendation snapshot. Corrections, amendments, and
supersessions begin as drafts; withdrawals are terminal. Existing
recommendation edit/finalization/CMS-transfer protections remain unchanged;
revision snapshots capture recommendation content without duplicating CMS
transfer lineage.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `php artisan migrate --force` | Passed: AEMS-6A migration applied |
| `php artisan db:seed --class=RolePermissionSeeder --force` | Passed |
| `php artisan test --filter=AemsIssueFindingRecommendationTest` | Passed: 5 tests, 91 assertions |
| `php artisan test --filter='Aems' --no-coverage --stop-on-failure` | Passed: 54 tests, 904 assertions |
| `php artisan test --testsuite=Feature --no-coverage --stop-on-failure` | Passed: 235 tests, 3,746 assertions |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `git diff --check` | Passed (line-ending warnings only) |

## AEMS-G4 AEO and team authority checkpoint

G4 adds an immutable AEO signatory matrix, explicit AEO cancellation,
voiding, supersession, and amendment operations, controlled issued-order
transmittal and acknowledgement, and append-only team amendment and access
history. AEO signatures preserve the authority role, actor, method, reference,
timestamp, and immutable content version. Team changes preserve authority,
reason, consequence assessment, and before/after snapshots.

The additive migration is
`2026_08_31_000000_add_aems_g4_authority_controls.php`. The detailed contract,
status rules, permissions, and API endpoints are in
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md`.

Verification for this checkpoint:

| Check | Result |
| --- | --- |
| `php artisan migrate:fresh --seed --force --quiet` | Passed |
| `php artisan test --filter='AemsG4AuthorityTest\|AemsTeamAeoTest\|AemsAepProgramTest\|AemsFoundationG2Test\|AemsPlanningPackageTest'` | Passed: 13 tests, 200 assertions |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `git diff --check` | Passed (normal LF-to-CRLF warnings only) |

The broader AEMS suite currently stops at the pre-existing G3 lifecycle fixture
that starts fieldwork without a conformant Planning Package. That is unrelated
to G4 authority controls and remains a G3 test-fixture follow-up.

## AEMS-G1A/G1B Professional-Control Hardening checkpoint

The evidence and finding gates now enforce the G0 professional decisions at
runtime. A current verified/locked Evidence record must have a current
immutable assessment tied to the exact Core Document Version, positive
professional outcomes, classified confidentiality, and no unresolved gaps or
limitations. Restricted or exception-controlled evidence requires a separate
approved exception revision. Ineligible Evidence is excluded from the Finding
support selector and hides Validate/Finalize actions; the backend remains the
authoritative gate.

Finding Conclusion is required by the request contract and all submission,
validation, and finalization transitions. Direct Findings require an allowed
authority reason and reference, with actor/timestamp provenance. Evidence
Request and Assessment versions cannot be updated or deleted; corrections and
exception approvals create superseding immutable versions. KPI and progress
controls are read from the approved Planning Package baseline, with explicit
legacy not-configured compatibility rather than unconditional lifecycle
passes.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `php artisan test --testsuite=Feature --filter=Aems --compact` | Passed: 65 tests, 1,008 assertions |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `git diff --check` | Passed |

See [AEMS Governance and Acceptance](AEMS_GOVERNANCE_AND_ACCEPTANCE.md)
for the detailed API and evidence eligibility contract.

## AEMS-10A/10B Dashboard and work-queue checkpoint

The AEMS Dashboard now consumes only backend-derived, engagement-scope-aware
values. In addition to the existing progress cards and tracker, it exposes
phase distribution, evidence-request and evidence-gap queues, findings and
report review queues, conferences, CMS transfer exceptions, Review Notes,
tasks, escalation candidates, closure-ready counts, and the current user's
notification monitoring summary. The dashboard also provides a protected
operational queue CSV export and formula-injection-safe progress/queue values.

The React workspace uses responsive auto-fit metric cards, hover states,
phase/status/office/search filters, queue panels, notification monitoring,
empty/loading/error states, and protected deep links. Cards expand to fill the
available row for users with different visible engagement counts.

Reminder windows are configurable through Core System Configuration using
`aems_reminders_enabled`, `aems_reminder_due_hours`,
`aems_response_reminder_days`, and `aems_conference_reminder_days`. Reminder
rules only produce notifications and reviewable queue signals; they never
perform approval, closure, CMS transfer, or other professional decisions.

## AEMS-11 Cross-module integration and security checkpoint

AEMS-11 hardens the existing Core, IAP, ARMIS, and CMS boundaries without
creating duplicate user, permission, document, workflow, notification, or
logging infrastructure. The IAP gateway is read-only: approved source rows are
not updated during import, archive, restore, or relink. AEMS owns the import
foreign key and immutable source snapshot, while the legacy IAP
`aem_engagement_id` value remains a computed compatibility projection.

ARMIS competency, availability, workload, planned/actual effort, and provider
health data continue through `ResourcePlanningGateway`. ARMIS is already the
sole operational provider; historical reconciliation records are not a
readiness gate. CMS intake remains create-once and
idempotent, and its immutable source envelope now includes AEMS source lineage
and a source-snapshot hash. The protected integration-status endpoint is:

```text
GET /api/aems/integrations/status
```

It uses the existing AEMS view permission and scope policy, hides global
counts from scoped users, reports referential-health checks, and explicitly
reports AIS as out of scope. See [AEMS Cross-Module Integration](AEMS_CROSS_MODULE_INTEGRATION.md)
for the detailed ownership contract.

## 24. AEMS-9A/9B Completion, CMS transfer, and closure checkpoint

AEMS-9A adds `aems_completion_transfer_manifests`,
`aems_completion_transfer_exceptions`, and `aems_effort_reconciliations`.
The manifest pins one issued Final Report version, its exact Core
`document_version_id` and checksum, and every finalized or formally excluded
recommendation. Reconciliation is transactional and delegates to the existing
idempotent CMS gateway; retrying a transfer reuses the existing CMS transfer
key. Approved manifests and effort snapshots are immutable and cannot be
updated or deleted.

Effort reconciliation records planned person-days, AEMS actuals, ARMIS actuals,
variance, provider mode, and source status. ARMIS is the only provider that can
satisfy closure readiness; stale or missing ARMIS data remains a blocker. The
independent reviewer/final approver cannot be the snapshot generator.

The server-derived Closure Checklist now adds the blocking checks
`CMS_TRANSFER_MANIFEST`, `CMS_TRANSFER_EXCEPTIONS`, and
`EFFORT_RECONCILIATION`. These checks cannot be overridden by client values and
are re-evaluated atomically by the formal Closure workflow. Controlled
reopening remains separate and preserves the original immutable closure
decision.

AEMS-9B adds the engagement-centered `Completion & Transfer` tab. It displays
the manifest lineage and counts, exception messages, provider mode,
planned-versus-actual effort variance, independent approval controls, and a
plain-language closure-gate explanation. Formal Closure remains the final
approval/close surface; no AIS or ARMIS integration was started.

Protected routes:

```text
GET  /api/aems/engagements/{engagement}/completion-transfer
POST /api/aems/engagements/{engagement}/completion-transfer/reconcile
POST /api/aems/engagements/{engagement}/completion-transfer/{MANIFEST|EFFORT}/{id}/approve
```

Focused regression coverage is in `AemsCompletionClosureTest`; frontend
coverage is protected by the lint and production-build checks.

## AEMS-8A/8B Reporting and distribution checkpoint

AEMS reporting now supports Interim, Draft, and Final Report assembly. Interim
and Draft workspaces preserve ordered sections, executive summary, quality
checklists, reviewer comments, confidentiality, exact Finding references, and
immutable Core Document Version/PDF checksum metadata. Final generation accepts
only an approved Interim or Draft Report and only current finalized Findings.

Issued versions are locked. Distribution decisions are append-only and record
delivery or recipient acknowledgement against the exact version. Controlled
withdrawal retains the issued version; amendment and supersession reopen the
existing family in a controlled draft state and create an immutable successor
PDF linked through `contentSnapshot.supersedesVersionId` with a reason. No
issued report content is overwritten.

The Reporting Workspace provides interim/draft/final assembly, section ordering,
quality review, recipient decisions, version comparison, protected PDF
download, and controlled amendment/withdrawal/supersession actions. Focused
browser coverage is in `tests/e2e/aems-reporting.spec.js`.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `php artisan migrate --force` | Passed: AEMS-8 report distribution migration applied |
| `php artisan test --testsuite=Feature --filter=AemsReportTest --stop-on-failure` | Passed: 3 tests, 73 assertions |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `git diff --check` | Passed (normal LF-to-CRLF warnings only) |

## AEMS-7B Conference and dialogue frontend checkpoint

The new `/audit-engagement-management/conferences` page provides the
engagement-scoped Conference & Dialogue workspace. It combines Entry and Exit
Conference timelines, participant/attendance and acknowledgement summaries,
agreements and disagreements, response/rejoinder and clarification history,
overdue response and review queues, escalation candidates, and the AEMS
notification-center panel. Detailed create/edit/acknowledge/transition
actions remain on the existing Entry Conference, Exit Conference, and
Auditee Responses pages; the aggregate page links to them and does not alter
workflow behavior.

The page consumes the existing protected Entry Conference, Exit Conference,
Findings, AEMS-7A Work Queue, and Core notification endpoints. Laravel remains
authoritative for role, engagement, and office scope. Auditee users can only
see findings formally communicated to their office because the workspace
renders the already scoped `findings-workspace` response and never broadens it
client-side. Focused browser coverage is in
`tests/e2e/aems-conference-dialogue.spec.js`.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `npx.cmd playwright test tests/e2e/aems-conference-dialogue.spec.js --project=desktop-chrome` | Passed: 2 tests |
| `npx.cmd playwright test tests/e2e/aems-issues-findings.spec.js --project=desktop-chrome` | Passed: 2 tests |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `php artisan test --testsuite=Feature --filter=Aems --stop-on-failure` | Passed: 56 tests, 929 assertions |
| `git diff --check` | Passed (normal LF-to-CRLF warnings only) |

No AEMS backend workflow behavior was changed in AEMS-7B. AIS and ARMIS
integration remain outside this phase.

## 24. AEMS-7A Conferences, dialogue, and work queues backend checkpoint

AEMS-7A hardens the backend exchange contract without beginning the React
work-queue page. Existing Entry/Exit Conference, Management Response,
Auditor Rejoinder, acknowledgement, attachment, notification, Activity Log,
Audit Trail, and Engagement Event behavior is preserved. New engagement
tasks, task event versions, review-note revisions, no-response due process,
due-process attachments, and reviewable escalation candidates are now scoped
and optimistic-lock protected.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `php artisan migrate --force` | Passed: AEMS-7A migration applied |
| `php artisan db:seed --class=RolePermissionSeeder --force` | Passed |
| `php artisan test --filter=AemsWorkQueueTest` | Passed: 2 tests, 25 assertions |
| `php artisan test --filter=AemsNotificationTest` | Passed: 2 tests, 8 assertions |
| `php artisan test --filter='DatabaseFoundationTest\|CoreModuleTest\|AemsNotificationTest'` | Passed: 15 tests, 340 assertions |
| PHP syntax checks for AEMS-7A models/service/controller/migration | Passed |

The complete Feature suite subsequently passed **237 tests with 3,771
assertions**. Frontend lint and production build also passed; `git diff
--check` reported no whitespace errors (only the repository's normal
LF-to-CRLF warnings). No AEMS-7B UI, AIS integration, or ARMIS integration
was started.

The runtime permission catalogue includes the four new AEMS permission
families. The AEMS-7B workspace is documented in the frontend checkpoint
above; AIS and ARMIS integrations remain outside these phases.

## AEMS-G3 planning conformance checkpoint

The Planning Package and Audit Program now expose structured Process Flow,
multi-matrix risk planning, Rule-35 Risk Matrix Item, program/procedure, KPI,
sampling, and planned Working Paper requirements. The strict backend
`fieldworkReady` contract is documented in
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md`; aggregate `START_FIELDWORK` is blocked
until it passes. The existing compatibility `ready` field is retained for
legacy clients while new clients should use `fieldworkReady`.

## 23. AEMS-6B Issues and AFR frontend checkpoint

The dedicated Issues page now provides status- and permission-aware issue
capture, independent validation, and all AEMS-6A dispositions (dismiss,
convert, merge, resolve during audit, observation, referral, and close without
finding). Detail cards show the disposition decision, actor, timestamp,
reason, referral/merge target, resolution details, exact Working Paper and
Evidence support, and immutable workflow history. Finding authors receive an
explicit separation-of-duties notice and cannot see review actions for their
own records.

Findings & Recommendations remains its own page. It now renders every finding
element (criteria, condition, cause, conclusion, effect, risk, significance,
and effect classification), direct Fieldwork Record, Working Paper, and
Evidence traceability, recommendation editing, management comment and
auditor rejoinder workspaces, and immutable revision history. Finalized,
withdrawn, and superseded findings are visibly locked. A controlled revision
modal calls the AEMS-6A revision endpoint and requires a correction,
amendment, supersession, or withdrawal reason; no finalized row is edited.

Focused browser coverage is in
`tests/e2e/aems-issues-findings.spec.js`. The page API client now includes
`aemsFindingApi.reviseFinding()`.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `npx.cmd playwright test tests/e2e/aems-issues-findings.spec.js --project=desktop-chrome` | Passed: 2 tests |
| `git diff --check` | Passed (line-ending warnings only) |

## AEMS-G5 Evidence lifecycle checkpoint

The evidence subsystem now has a complete, auditable request lifecycle. Requests
support acknowledgement, partial/complete receipt, review, assessment,
extension request and approval, overdue and escalation handling, cancellation,
and closed-without-submission outcomes. Each transition is recorded in the
append-only `aems_evidence_request_events` table and continues to emit Core
activity, audit, and notification events.

Evidence records now carry explicit professional outcomes (`REGISTERED`,
`FOR_ASSESSMENT`, `ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`,
`SUPERSEDED`, `DUPLICATE`, and `VOIDED`), acquisition method/form, planning
objective, risk-matrix-item, and control references, plus direct links to report
versions. Assessment eligibility requires an exact current Core Document
Version, a current verified/locked evidence revision, positive assessment
dimensions, no unresolved gaps or contradictions, and an explicit acceptable
outcome; restricted or limited evidence requires the existing approved
exception path.

The consolidated Evidence Management workspace exposes request stages, control
actions, lifecycle history, explicit outcome and traceability indicators, and
protected report-link actions. Existing AEMS evidence and working-paper
compatibility routes remain unchanged.

Verification recorded for this checkpoint:

| Check | Result |
| --- | --- |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `php artisan migrate:fresh --seed --force --quiet` | Passed |
| Focused G4/G5/request/WP Feature tests | Passed: 10 tests, 121 assertions |
| `npx.cmd playwright test tests/e2e/aems-evidence-management.spec.js --project=desktop-chrome --project=mobile-chrome` | Passed: 2 tests |
| `git diff --check` | Passed (line-ending warnings only) |

The broader `php artisan test --testsuite=Feature --compact` run was previously
attempted, but the runner exceeded five minutes while the existing working-paper
request path was still active. The focused G4/G5, request, and working-paper
suites pass independently, and the desktop/mobile Evidence workspace suites
pass against the updated lifecycle and traceability contract. This is recorded
as a suite/runtime limitation rather than a G5 assertion failure.
## AEMS-G6 checkpoint

G6 adds controlled issue withdrawal and compatibility metadata, immutable AFR
transmittal/recipient/event records, delivery and acknowledgement actions,
response extensions, late and supplemental response metadata, and UI/API
actions for the existing operational queues. Existing legacy DISMISSED issue
rows and response workflows remain supported. Verification is recorded in
`AemsG6IssuesDialogueContractTest` and the issue/finding regression suite.

## AEMS-G7 checkpoint

G7 adds immutable report source manifests and hashes, direct Issue/approved
Working Paper/current Evidence links, Interim-to-Final treatment metadata,
IAU Head/LCE authority decisions, signatory and transmittal records,
confidentiality-aware reproducible PDF/CSV exports, and supervisor-controlled
administrative closure. The issued version remains locked; closure and report
successors operate at the report-family level. See
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md` for the contract and protected routes.

## AEMS-G8 checkpoint

G8 adds controlled archive and disposition state, legal-hold release, immutable
destruction-eligibility reviews, protected records search, and an operational
Audit Calendar with optimistic-locked milestones. Legal holds and overdue
required milestones now enter the atomic Closure blocker register. `COMPLETED`
and `CLOSED` remain distinct lifecycle states; recording disposition never
physically deletes Core DocumentVersions. See
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md` for the API, permissions, data model,
and verification contract.

## AEMS-G9 verification and documentation truth pass

The current verification contract is in `docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md`.
The G9 backend index contains 35 independent Rule tests and 32 independent
SCR registry tests. Frontend verification covers the explicit-versus-generic
route boundary, the six seeded role navigation matrix, mutation payloads,
negative Evidence, protected downloads, and desktop/mobile responsive
projects. `scripts/verify-aems-g9.ps1` is the repeatable migration rehearsal
and verification entry point.

Earlier checkpoint sections in this document are historical snapshots. Where
an earlier section says a later phase had not started, the later G4-G8 and G9
sections are the current as-built state. No AEMS operational workflow is
changed by G9, and AIS remains outside the AEMS scope.

## AEMS-G10A bounded backend conformance pass

The fieldwork taxonomy is now sourced from `AemsFieldworkRecord::TYPES` and
includes Inquiry, Meeting, Field Note, and Other in addition to the existing
record types. Findings now preserve an explicit normalized procedure-to-
criteria traceability link. Links may be supplied by API clients or derived
from cited Working Paper and Fieldwork Record Versions; cross-engagement
procedure IDs are rejected. Finding revisions and communication/finalization
snapshots preserve the procedure IDs. See
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md`.

This bounded pass does not claim complete MDS/UID conformance. Dedicated queue
operations, records/archive operations, and unresolved governance decisions
remain separately tracked.

## AEMS-G10B frontend conformance

The Execution Workspace exposes the expanded Fieldwork Record taxonomy and
shows procedure criteria alongside the selected execution procedure. Findings
can select approved-program procedures, and Finding detail displays the
procedure-to-criteria traceability chain returned by the backend. The SCR-212
Scope workspace now warns about invalid Area/Focus links and prevents saving
until the relationship is corrected. See
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md` and
`tests/e2e/aems-g10b-conformance.spec.js`.

## AEMS-G10E governance and final acceptance

G10E is the final governance and verification gate. The G0 decision register
now includes the acceptance/change-control decision (G0-14), the status map
reflects the current runtime values, and `AemsG10EAcceptanceTest` executes all
35 Rule rows against runtime classes, methods, and status constants. The
canonical 32-SCR and six-role navigation contracts remain covered by the G9
and G10E Playwright suites. See
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md` for the final command contract and
release boundaries.
