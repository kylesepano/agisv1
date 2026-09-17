# BAICS Governance and Conformance Contract

## Baseline Assessment of the Internal Control System

**Module owner:** Internal Audit Planning (IAP)  
**Contract version:** BAICS-0.2  
**Implementation checkpoint:** BAICS-6
**Status:** BAICS-1A/1B through BAICS-6 are implemented; focused BAICS/IAP
verification passed. The time-bounded full Feature-suite result is recorded
below; future governance enhancements remain separate work.
**Effective scope:** BAICS-1A/1B foundation, BAICS-2A/2B assessment
instruments, BAICS-3A/3B Control Universe/BAR, BAICS-4 IAP integration,
BAICS-5 authorization/notification hardening, and BAICS-6 final verification;
current IAP, AEMS, CMS,
ARMIS, AIS and Core workflows remain unchanged

## 1. Purpose

This contract establishes the meaning, ownership, workflow, evidence rules,
approval gates and integration boundaries for the implemented Baseline
Assessment of the Internal Control System (BAICS) capability in AGIS.

BAICS is not a rename or replacement of the existing Audit Universe. The Audit
Universe answers **what can be audited**. BAICS assesses **what controls exist
around those processes, whether they are designed and operating, and where
gaps, deficiencies or breakdowns exist**.

The contract is based on the BAICS requirements identified from the Revised
PGIAM and the related internal-control concepts in NGICS and ICSPPS 2017. The
source references remain standards authority; AGIS source code and tests are
the as-built implementation authority.

## 2. Current as-built position

The following capabilities already exist and may be reused:

- Core offices, users, roles, permissions, Audit Areas, Audit Focuses,
  documents, versions, workflows, notifications, Activity Log, Audit Trail,
  numbering and protected downloads;
- IAP Audit Universe subjects, responsible offices, stakeholder offices,
  materiality/exposure, classification, scope and historical audit context;
- IAP risk-assessment periods, scoring, validation, approval and locking;
- IAP prioritization, strategic planning, annual planning, scheduling,
  capacity and reports;
- AEMS Process Flow, Working Paper, Evidence and immutable-document patterns;
- Core evidence/checksum/confidentiality and protected-version services; and
- existing workflow, scope, optimistic-locking and audit-event patterns.

These capabilities are **inputs or reusable infrastructure**, not proof that
later BAICS outputs are implemented. BAICS-1A/1B adds the controlled cycle,
scope-lineage, assignment, lifecycle, version-history, permission and IAP
workspace foundation. BAICS-2A/2B adds the five-component assessment
instruments, corroboration, exact evidence links and component readiness.
BAICS-3A/3B adds the traceable Control Universe, interim analysis sources,
Baseline Assessment Report assembly, immutable report versions and protected
authenticated exports. BAICS-4 adds approved BAR/legacy-exception decisions,
IAP consumer readiness checks and staged approval gates without source writes.
BAICS-5 adds granular integration permissions, participant eligibility checks,
transition-level authority, and scoped Core notification delivery with
deduplication and decision metadata.

## 3. Ownership and placement

BAICS belongs inside IAP because it is a strategic and annual planning input.
It must not become a new top-level AGIS module and must not take ownership of
records belonging to Core, IAP risk assessment, AEMS, CMS, ARMIS or AIS.

The intended planning chain is:

```text
Core registries
  -> IAP Audit Universe
  -> BAICS assessment cycle
  -> Control Universe and Baseline Assessment Report
  -> IAP Risk Assessment
  -> Prioritization
  -> Strategic / Annual Audit Plan
  -> AEMS engagement execution
```

The current IAP sidebar destination is:

```text
Internal Audit Planning
  - IAP Dashboard
  - Strategic Audit Plan
  - Audit Universe
  - Baseline Assessment (BAICS)
  - Risk Assessment
  - Audit Prioritization
  - Annual Audit Plan
  - Audit Scheduling
  - Resource Capacity
  - IAP Reports
```

BAICS-1A/1B canonical route is `/internal-audit-planning/baics` under the IAP
sidebar. BAICS-3B adds `/internal-audit-planning/baics/control-universe` (the
Control Universe and BAR workspace), `/internal-audit-planning/baics/reports`
as a direct report workspace route, and `/internal-audit-planning/baics/integration`
as the approved IAP-consumer integration workspace. The API family remains `/api/iap/baics`;
no separate top-level module or duplicate SCR ownership was created.

## 4. Terminology and boundaries

| Term | BAICS meaning | Boundary |
| --- | --- | --- |
| Audit Universe | Inventory of auditable processes, programs, systems, services and projects | Existing IAP source; BAICS reads it and stores source lineage |
| BAICS assessment | Controlled assessment of internal-control components and methods for one approved scope/cycle | Current IAP record; BAICS owns the assessment and preserves read-only source lineage |
| Control Universe | Inventory of controls on key OPS, STO and GAS processes, including gaps, deficiencies and breakdowns | Generated from BAICS evidence; not the Audit Universe |
| Control gap | Missing, incomplete or inadequately designed control | BAICS assessment result |
| Deficiency | Control weakness requiring documented evaluation and response | BAICS assessment result; may influence IAP risk |
| Breakdown | Control exists or is designed but is not operating as required | BAICS assessment result |
| Baseline Assessment Report (BAR) | Approved report consolidating interim analysis, Control Universe and supported oversight findings | BAICS-3A/3B IAP report; not an AEMS audit report |
| AEMS Finding | Finding arising from an authorized audit engagement | AEMS-owned; BAICS results do not automatically create one |

BAICS does not replace or mutate either `iap_risk_assessments` or
`iap_universe_risk_assessments`. The two existing risk systems remain separate
compatibility systems.

## 5. Assessment scope and cycle

Each BAICS cycle must record:

- cycle identifier and assessment year/period;
- organizational scope and applicable offices;
- selected Audit Universe subjects and immutable source snapshots;
- Audit Areas and Audit Focuses;
- objectives, boundaries, exclusions and known limitations;
- assessment methodology and planned methods;
- responsible office and stakeholder offices;
- assessment team and authority assignments;
- source-document and criteria register;
- planned dates, review dates and reporting date; and
- legacy/exception status where a prior baseline is being relied upon.

A cycle may cover multiple subjects, but every subject, process, control and
component assessment must retain explicit ownership and scope. A control may
not exist in the Control Universe without a linked process, office, Audit Area
or Focus, and BAICS source assessment.

## 6. Five internal-control components

Every approved BAICS scope must assess all five components:

1. **Control Environment** — accountability, ethics, authority, competency,
   supervision, organizational structure and segregation of duties.
2. **Risk Assessment** — objectives, risk identification, risk ownership,
   risk analysis and risk-response practices.
3. **Control Activities** — preventive/detective controls, approvals,
   reconciliations, access controls, segregation of duties and operating
   procedures.
4. **Information and Communication** — information sources, reports, records,
   communication channels, escalation and timeliness/completeness.
5. **Monitoring and Evaluation** — supervisory review, performance
   indicators, exception monitoring, corrective action and oversight.

Each component requires an assessment conclusion, supporting evidence,
limitations, reviewer and version. A component cannot be marked complete only
because a questionnaire was sent; the required assessment methods and
corroboration rules must be satisfied.

## 7. Approved assessment methods

BAICS must support the following methods as distinct records, not merely as
free-text labels:

- Internal Control Questionnaire (ICQ);
- interview, inquiry and focus-group discussion;
- documentary and criteria review;
- process narrative or flowchart;
- walkthrough and observation;
- test of controls;
- oversight/development-partner report review; and
- interim analysis.

Each method record must capture actor, office/process, date, questions or
procedure, result, evidence, limitations, reviewer and immutable version.
Core `document_versions` remains authoritative for file checksum, size, MIME
type, confidentiality, custody and protected downloads.

For each component, the default acceptance rule is at least three independent
corroborating assessment methods. A lower number requires an approved,
versioned exception that records the reason, authority, affected component,
compensating evidence and expiry. The exception cannot silently convert an
unassessed component into an adequate one.

## 8. Control Universe contract

Each Control Universe record must retain:

- BAICS cycle and assessment version;
- source Audit Universe subject and process step;
- responsible office/unit and control owner;
- objective and related risk;
- control description and expected result;
- preventive, detective or hybrid type;
- manual, automated or hybrid execution;
- frequency and evidence produced;
- approval and segregation-of-duties requirements;
- design assessment and operating assessment;
- control status and deficiency classification;
- limitation, gap, breakdown or contradiction details;
- recommendation or improvement action; and
- linked evidence, method records, reviewer and version.

Initial statuses are:

```text
Existing
Partially Designed
Not Designed
Operating Effectively
Partially Effective
Not Operating
Control Gap
Deficiency
Breakdown
```

The Control Universe is a BAICS output. It is not a second Audit Universe and
must not duplicate ownership of IAP risk records or AEMS Findings.

## 9. BAICS workflow and authority

The proposed cycle lifecycle is:

```text
Draft
  -> Planning
  -> In Progress
  -> Pending Review
  -> Returned
  -> Resubmitted
  -> Approved
  -> Published
  -> Archived
```

The proposed Baseline Assessment Report lifecycle is:

```text
Draft
  -> Pending Review
  -> Returned
  -> Approved
  -> Issued
  -> Superseded
```

The following separation rules are mandatory:

- the preparer cannot approve the same cycle or BAR;
- a component assessor cannot be the sole independent reviewer;
- a responsible-office respondent cannot approve the final assessment;
- the person who accepts an exception cannot be the only person who benefits
  from that exception;
- final approval authority must be recorded; and
- every return, resubmission, approval, publication, issuance and supersession
  creates an Activity Log and Audit Trail event.

Approved cycles, Control Universe baselines and issued BAR versions are
immutable. Corrections create a new revision and preserve the prior decision.

## 10. IAP integration gates

After BAICS is operational, risk-assessment approval should require one of:

1. an approved BAICS baseline covering the applicable scope and period; or
2. an approved legacy/exception decision that records why BAICS is not yet
   applicable, the compensating source, authority and expiry.

Prior approved IAP periods must not be invalidated silently. A migration or
grandfathering decision must preserve their original source lineage and explain
whether they are BAICS-backed, legacy-exempt or pending reassessment.

Risk Assessment may consume only an approved BAICS snapshot. Prioritization,
Strategic Audit Plans and Annual Audit Plans preserve the BAICS baseline ID
and version used for their decisions.

AEMS may consume approved IAP lineage and BAICS references read-only. AEMS
must not modify BAICS, the Audit Universe or IAP risk records. CMS receives no
direct BAICS ownership. AIS may later report BAICS metrics through its read-only
source boundary after the records and confidentiality contract is approved.

ARMIS provides assessment-team competency, availability and capacity data
through the configured provider boundary. Missing or stale resource data must
block assignment approval; BAICS has no fallback provider and must not create a
second resource ledger.

## 11. Permissions and notifications

The legacy `iap.baics.*` family remains supported for compatibility. BAICS
integration endpoints additionally use this narrower action-specific family:

```text
iap.baics.view
iap.baics.create
iap.baics.update
iap.baics.assign
iap.baics.submit
iap.baics.review
iap.baics.return
iap.baics.approve
iap.baics.publish
iap.baics.archive
iap.baics.export
iap.baics.manage-controls
```

```text
iap.baics.integration.view
iap.baics.integration.create
iap.baics.integration.update
iap.baics.integration.submit
iap.baics.integration.review
iap.baics.integration.return
iap.baics.integration.approve
iap.baics.integration.retire
```

Read endpoints accept either integration view or the legacy BAICS view alias.
Draft/update and transition endpoints require the matching integration
permission or its documented legacy alias. Assigned reviewers and authorities
must be active users with review/approval authority; preparer, reviewer and
authority are separate users. Only the reviewer or authority may return a
pending decision, and only the authority may retire an approved decision.

Integration draft, update, submit, review, return, approve and retire events
are delivered through Core after commit. Recipients are limited to the
assigned preparer, reviewer and authority as appropriate; the actor is
excluded, inactive users and users without `notifications.view` are filtered,
and recipient/event/version deduplication prevents duplicate alerts. Payloads
include the integration code, assessment, consumer, decision type, old/new
status, version and protected workspace link. Notification preferences still
control in-app and optional email delivery. Notification delivery does not
replace the authoritative workflow decision.

## 12. Baseline Assessment Report contract

The BAR must be assembled from approved, versioned sources and contain:

- executive summary;
- objectives, scope and methodology;
- assessment-period and source-lineage statement;
- detailed findings for each internal-control component;
- overall findings and control-gap summary;
- Control Universe summary;
- recommendations and responsible offices;
- oversight/development-partner findings, where accepted;
- limitations and exceptions; and
- attachments and source manifest.

Issued BAR versions must be reproducible, confidentiality-aware and protected
by authenticated downloads. PDF/CSV checksums and source-version metadata must
be preserved. A BAR cannot be issued from unapproved components, unresolved
mandatory evidence gaps or unapproved exceptions.

## 13. Rule-to-code-to-test acceptance matrix

| Rule | Contract requirement | Future implementation evidence | Gate |
| --- | --- | --- | --- |
| BAICS-01 | BAICS is an IAP capability, not a top-level module | IAP route/permission registry and documentation | No duplicate module ownership |
| BAICS-02 | Audit Universe remains the read-only source inventory | Source snapshot/link and no-write test | Audit Universe is never mutated |
| BAICS-03 | Every cycle has explicit scope, office, subject, Area and Focus | Migration, request, policy and API test | No unscoped assessment |
| BAICS-04 | All five components are assessed | Component model, readiness service and negative test | Incomplete component blocks approval |
| BAICS-05 | Approved methods are distinct records | ICQ, interview, review, flow, walkthrough and control-test models | Free-text-only evidence is insufficient |
| BAICS-06 | Three corroborating methods are required by default | Readiness calculation and exception service | Uncorroborated component blocks approval |
| BAICS-07 | Exact Core Document Versions support evidence | Document-version links and protected-download test | Checksum/confidentiality preserved |
| BAICS-08 | Controls form a traceable Control Universe | Control model and generated snapshot | Every control has process and owner lineage |
| BAICS-09 | Gaps, deficiencies and breakdowns are classified | Status/disposition model and review test | Classification requires evidence/reason |
| BAICS-10 | Approved cycles and reports are immutable | Version tables, optimistic locking and mutation-negative tests | Corrections create revisions |
| BAICS-11 | Separation of duties is enforced | Policy/service tests and authority matrix | Preparer cannot approve |
| BAICS-12 | Risk Assessment consumes approved BAICS only | IAP transition gate and lineage test | No unsupported risk baseline |
| BAICS-13 | IAP/Audit Universe source records are not mutated | Cross-module no-write test | Source ownership preserved |
| BAICS-14 | BAR is reproducible and protected | Source manifest, checksum and authenticated download test | Issued report can be reproduced |
| BAICS-15 | Legacy periods are explicitly grandfathered or reassessed | Migration rehearsal and exception records | No silent invalidation |

## 14. Delivery phases and gates

The implementation must proceed in this order:

1. **BAICS-1A/1B — Foundation backend and UI:** cycles, scope, source
   lineage, permissions, workflow, assignments and version history.
2. **BAICS-2A/2B — Assessment instruments:** five components, ICQ, interviews,
   document review, process flow, walkthroughs, control testing and evidence.
3. **BAICS-3A/3B — Control Universe and BAR:** controls, gaps, deficiencies,
   interim analysis, report assembly, approval and protected exports.
4. **BAICS-4 — IAP integration (implemented):** risk-assessment, prioritization, strategic
   and annual-plan lineage, legacy handling and ARMIS resource boundary.
5. **BAICS-5 — Permissions and notification hardening (implemented):** granular
   integration permissions, participant eligibility, transition-level authority,
   scoped Core notifications and deduplication.
6. **BAICS-6 — Final verification and documentation (implemented):** role,
   scope and separation tests; schema/migration rehearsal; shared-owner and
   immutable-version conformance checks; canonical navigation coverage; and
   documentation/as-built traceability. The responsive UI contract is covered
   by the committed Playwright specification and remains a manual execution
   step while Playwright execution is paused.

No later phase begins until the previous phase passes its focused backend,
frontend, regression, documentation and migration gate.

### Current checkpoint clarification

The permissions and notification hardening described above is implemented as
BAICS-5. BAICS-6 closes the final verification/documentation pass for the
backend contract, schema rehearsal, shared ownership, and canonical routes.
The responsive UI checks are committed in the BAICS Playwright specification
but were not executed because Playwright execution was explicitly paused.

## 15. Explicit non-goals for BAICS-0 (historical phase contract)

BAICS-0 does not add:

- database migrations;
- API routes or controllers;
- React pages or sidebar entries;
- BAICS permissions to the runtime catalogue;
- automatic risk scores or prioritization changes;
- AEMS Findings or CMS Recommendations;
- a duplicate control, risk, office or resource ownership table; or
- a formal IASPPS conformance claim.

Those changes require the later BAICS phases and a separate implementation
checkpoint. BAICS-1A/1B does not claim any of them.

## 16. BAICS-1A/1B as-built foundation

The foundation is implemented in IAP and is intentionally limited to cycle
governance and scope preparation:

- `iap_baics_assessments` stores a cycle family, assessment year, responsible
  office, objectives, boundaries, exclusions, limitations, dates, status,
  preparer/reviewer/approver/publisher, optimistic lock, and soft deletion.
- `iap_baics_scope_items` stores one or more Audit Universe subjects with an
  immutable source snapshot plus explicit office, Audit Area, and Audit Focus.
  BAICS reads the source and never updates the Audit Universe record.
- `iap_baics_assignments` records assessor, reviewer, approver, coordinator and
  respondent assignments with reason, actor, status, and assignment history.
- `iap_baics_versions` stores immutable snapshots and SHA-256 hashes for every
  foundation write or lifecycle decision. Approved/published records are
  corrected by creating a new revision family member.

The implemented lifecycle is:

```text
DRAFT → PLANNING → IN_PROGRESS → PENDING_REVIEW → RETURNED
                                      ↘ RESUBMITTED → APPROVED → PUBLISHED → ARCHIVED
```

Readiness currently checks responsible office, non-empty source scope,
explicit Area/Focus dimensions, objectives, methodology, and at least one
active assignment. Approval is blocked when readiness fails or when the
preparer attempts to approve the same cycle. Core `IapSupport` audit records,
Activity Log records, notification delivery, and Core runtime numbering are
used; no BAICS-owned duplicate identity, office, risk, document, or resource
tables were introduced.

The foundation permissions are:

```text
iap.baics.view, create, update, assign, submit, review, return, approve,
iap.baics.publish, archive, export, manage-controls
```

These permissions are seeded for the appropriate IAP roles. The current UI
supports cycle search, create/edit, source-scope selection, readiness display,
assignments, lifecycle actions, revisions, and immutable version history.

## 17. BAICS-1A/1B and BAICS-2A/2B verification

Focused backend coverage is `IapBaicsFoundationTest` (source-lineage capture,
assignment, workflow, separation of duties, optimistic locking, and version
snapshots) and `IapBaicsControlAssessmentTest` (independent methods, exact
Core evidence, component approval and incomplete-cycle readiness). Frontend
route and build checks use the canonical IAP navigation registry and
`IapBaicsPage`.

## 18. BAICS-2A/2B as-built assessment instruments

Every newly created or revised cycle initializes exactly these five component
records in `iap_baics_components`: Control Environment, Risk Assessment,
Control Activities, Information and Communication, and Monitoring and
Evaluation. Each component records an assessor, independent reviewer,
conclusion, supporting summary, limitations, review/approval state, lock
version and immutable component-version snapshots. Approved components cannot
be edited; corrections require a new BAICS cycle revision.

`iap_baics_methods` stores distinct, reviewable method records. Supported
types are ICQ, interview/inquiry/focus group, documentary/criteria review,
process narrative/flowchart, walkthrough/observation, test of controls,
oversight/development-partner report review and interim analysis. Methods
record performer, date, procedure, result, limitations, reviewer, status and
immutable method-version snapshots. A method cannot be approved until it has
an exact Core `document_versions` link.

`iap_baics_evidence_links` references Core Document Versions directly, while
`iap_baics_exception_versions` preserves immutable exception decisions; BAICS
does not copy files or create a parallel document repository. The API exposes
the source filename, MIME type, size and SHA-256 checksum while protected
downloads remain authenticated Core endpoints.

By default, component readiness requires at least three approved methods with
distinct method type/performer corroboration, evidence for every method,
component evidence, an independent reviewer, a conclusion and no open
exception. An approved, time-limited exception may satisfy the corroboration
count only when it includes a reason, designated authority, compensating
evidence and future expiry. The designated authority must approve it; the
creator and component assessor cannot approve it. Draft, returned or pending
exceptions never satisfy readiness.

The cycle may be submitted for review while components are still being
completed so reviewers can return a single controlled package. Cycle approval
is stricter: all five components must be ready and independently approved.
This prevents an incomplete internal-control baseline from becoming an
approved planning source.

The BAICS page now provides a cycle-detail assessment workspace for component
conclusions, method entry/review, exact evidence linking, exception drafts,
readiness indicators, component approval actions, assignments and immutable
history. BAICS-2 does not create a Control Universe, BAR, automatic risk
score, or cross-module write.

## 19. BAICS-3A/3B as-built Control Universe and BAR

`iap_baics_controls` is the BAICS-owned Control Universe output. It is linked
to an immutable BAICS scope item, optional internal-control component, process
step, owner office/user, objective, related risk, control description,
expected result, control type, execution mode, frequency, evidence produced,
approval and segregation-of-duties requirements, design and operating
assessments, control status, deficiency classification, limitation/gap/
breakdown/contradiction basis, and improvement action. It is not a second
Audit Universe and does not copy or own IAP risk records.

Control statuses are `Existing`, `Partially Designed`, `Not Designed`,
`Operating Effectively`, `Partially Effective`, `Not Operating`, `Control Gap`,
`Deficiency`, and `Breakdown`. A gap/deficiency/breakdown classification cannot
be submitted without a documented basis. Control workflow is Draft → Pending
Review → Returned or Approved. Approval requires an independent reviewer,
approved assessment methods, exact Core Document Version evidence, complete
scope/owner/component fields, and the classification basis where applicable.
Approved controls are immutable; later corrections require a BAICS revision.
Control method and evidence relationships are persisted in dedicated trace
tables, while the evidence itself remains in Core `document_versions`.

Interim analyses are separate versioned records with narrative, period,
findings, recommendations, limitations, source manifest, reviewer and
approval. Their lifecycle is Draft → Pending Review → Returned or Approved.
Selected interim analyses must be approved before a BAR can be submitted.

`iap_baics_reports` and `iap_baics_report_versions` implement the Baseline
Assessment Report. A BAR captures executive summary, objectives/scope/
methodology, overall findings, control-gap summary, recommendations,
limitations/exceptions, selected Control Universe records, selected interim
analyses and a source manifest. Its lifecycle is Draft → Pending Review →
Returned → Approved → Issued → Superseded. The preparer cannot approve, the
independent reviewer must approve, and the approver cannot issue the same
report. Submission/approval is blocked unless the BAICS cycle has all five
approved components, every selected control is approved and evidence-traceable,
and selected interim analyses are approved.

Each report version stores an immutable snapshot, source-manifest SHA-256,
content SHA-256, deterministic PDF/CSV checksums and a file-version key. PDF
and CSV exports are authenticated `iap.baics.export` endpoints; no public
document URL or duplicate document repository was introduced. CSV cells are
protected against spreadsheet formula injection, and every export is written
to the Core Audit Log and Activity Log.

BAICS-3A/3B API routes cover Control Universe CRUD and transitions, interim
analysis CRUD and transitions, BAR CRUD and transitions, and protected PDF/CSV
export. The React workspace is `IapBaicsControlUniversePage` and is linked
from the IAP sidebar without changing the existing BAICS foundation route.

Focused verification is `IapBaicsControlUniverseTest` (3 tests, 19
assertions): incomplete controls cannot be approved, BAR submission cannot
bypass missing approved sources, and an approved BAR can only be downloaded
through the protected, checksum-bearing CSV export contract. The existing
BAICS-1/2 tests remain unchanged and continue to protect source
lineage, independent methods, exact evidence, component readiness, separation
of duties, optimistic locking, and immutable history.

## 20. BAICS-4 as-built IAP integration

BAICS-4 is implemented as a separate, auditable IAP integration ledger. It
supports the following consumer types: risk periods, universe risk
assessments, prioritization runs, strategic plans, annual plans, and
annual-plan engagements. A decision is either linked to an approved/issued,
immutable BAR version (`BAICS_BACKED`) or is an explicitly approved,
time-limited `LEGACY_EXCEPTION` with a compensating source, reason, authority,
and future expiry.

The workflow is Draft → Pending Review → Returned or Approved. The preparer,
independent reviewer, and approving authority must be different users. Review
records a reviewer timestamp; approval is a separate authority action. Active
duplicates for the same consumer are rejected, optimistic locking protects
concurrent edits, and every saved version/transition writes an immutable
snapshot plus Core Activity Log/Audit Trail entries. The provider snapshot is
read-only and reports ARMIS availability without creating an ownership table.

`/internal-audit-planning/baics/integration` is the controlled workspace.
`baics_integration_required` is a Core runtime setting, defaulting to `false`
for staged rollout. When enabled, IAP risk-period validation, prioritization
finalization, strategic-plan approval, and annual-plan approval fail closed
unless their required BAICS decision is approved and unexpired. Existing IAP,
BAICS, ARMIS, AEMS, CMS, and AIS source records are never mutated. The focused
verification is `IapBaicsIntegrationTest` (4 tests, 29 assertions), alongside
the existing BAICS-1 through BAICS-3 suites.

## 21. BAICS-6 final verification and migration rehearsal

BAICS-6 is the conformance gate for the complete BAICS delivery. The focused
`IapBaicsG6AcceptanceTest` verifies that:

- BAICS-01 through BAICS-15 are present in this contract;
- legacy `iap.baics.*` compatibility permissions and granular
  `iap.baics.integration.*` permissions are present, with role-level
  create/review/approve separation;
- all BAICS foundation, assessment, Control Universe, BAR, and integration
  tables and their checksum/lock/version columns survive a fresh schema;
- BAICS references Core offices, users, areas, focuses, documents and IAP risk
  tables rather than creating duplicate ownership tables; and
- the three canonical BAICS navigation paths are each registered once.

The migration rehearsal is intentionally isolated from the developer's
PostgreSQL database. Run it from `backend` with a temporary in-memory SQLite
connection:

```powershell
$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; $env:APP_ENV='testing';
php artisan migrate:fresh --seed --force --env=testing
```

The command completed successfully during the BAICS-6 gate. It creates and
seeds a disposable schema, then exits without changing local PostgreSQL data.

The committed `tests/e2e/iap-baics-foundation.spec.js` covers the foundation,
Control Universe/BAR, and IAP Integration workspaces for desktop and mobile
layout, canonical navigation, and SQL-error visibility. Playwright execution
was not run in this checkpoint because it was explicitly paused; the suite is
ready for the later manual execution gate.

The BAICS-6 role/scope matrix is: AGIS Administrator can view but cannot make
integration decisions; CIAS Management can create, review and approve within
its authorized IAP scope; AGIS User can review assigned decisions; and all
other access remains subject to the existing Core office/role scope policies.

### BAICS-6 verification record

| Check | Result |
| --- | --- |
| `IapBaicsG6AcceptanceTest` | 3 passed, 66 assertions |
| BAICS aggregate (`--filter=IapBaics`) | 16 passed, 175 assertions |
| IAP aggregate (`--filter=Iap`) | 56 passed, 920 assertions |
| Core notification regression | 3 passed, 35 assertions |
| Core/database baseline corrections | 13 passed, 332 assertions |
| SQLite migration rehearsal | Passed; disposable in-memory schema and seed |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `git diff --check` | Passed |
| Playwright | Not run by explicit instruction; committed specs remain ready |

The unfiltered Feature suite reached 408 passing tests before exposing three
stale seeded-count expectations. Those expectations were corrected to the
current 473-permission and 43-configuration catalog and the affected tests
passed in isolation. A subsequent unfiltered run exceeded the ten-minute
runner timeout without buffered failure output; it is therefore recorded as a
timeout, not as a pass or a failure. Focused BAICS/IAP and affected regression
suites are the authoritative completed gate for this checkpoint.
