# Audit Engagement Management (AEMS)

## Governance, implementation, and acceptance contract

**Compiled module document:** AEMS-G0 through AEMS-G10E  
**Status:** accepted as the current AEMS as-built contract  
**Effective review:** 14 August 2026  
**Owner:** CIAS Management / AGIS Product Governance

This is the single canonical reference for the AEMS governance and conformance
phases. It contains the decisions, implementation controls, workspaces,
integration boundaries, API families, permissions, data rules, phase gates,
and verification evidence previously spread across the AEMS-G documents. The
separate phase files have been retired from the active documentation set so a
reader does not need to reconcile duplicate G0-G10E documents.

The application retains the compatibility identifiers `AEMS`, `AEMS-*`,
`aems.*`, and legacy `aem.*`, while the descriptive display name is **Audit
Engagement Management**. Source code and tests remain authoritative when a
draft MDS/UID/DGM reference conflicts with runtime behavior.

## 1. Module responsibility and boundaries

AEMS manages an audit engagement from authorized source or special
authorization through planning, execution, findings, reporting, transfer, and
administrative closure.

| Boundary | AEMS responsibility | Other owner |
| --- | --- | --- |
| Identity and scope | Consume users, offices, roles, permissions, areas, focuses, documents, workflows, notifications, logs, configuration, and numbering | Core |
| Engagement planning source | Import one approved IAP engagement-plan source without mutating it | IAP |
| Resource authority | Consume competencies, availability, workload, assignments, and actuals from ARMIS; retain IAP only as planning lineage/reconciliation evidence | ARMIS sole operational provider |
| Findings and recommendations | Own Issues, Findings, Recommendations, management dialogue, rejoinders, report provenance, and final snapshots | AEMS |
| Post-issuance monitoring | Transfer finalized recommendation snapshots once | CMS owns Action Plans, monitoring, validation, dispositions, reopening, and CMS closure |
| Intelligence | AEMS does not own an AIS business workflow or source mutation | AIS owns a separate read-only analytical/integration contract |

## 2. Current AEMS module surfaces

| Surface | Route | SCR/permission contract | Function |
| --- | --- | --- | --- |
| AEMS Dashboard | `/audit-engagement-management/dashboard` | `aems.engagement.view` | Scope-aware progress cards, phase counts, overdue work, evidence gaps, findings, responses, conferences, reports, transfer exceptions, closure readiness, queue indicators, and protected exports |
| Audit Engagement Workspace | `/audit-engagement-management` | SCR-210 / `aems.engagement.view` | IAP import or special engagement creation, editable foundation metadata, office scope, search/filter, archive/restore, and engagement details |
| Audit Team | `/audit-engagement-management/team` | SCR-213 / `aems.team.view` | Team assignment, competency/availability/capacity, independence and conflict safeguards, ARMIS status, planned/actual person-days, amendments, and approval |
| Engagement Orders | `/audit-engagement-management/aeo` | SCR-214 / `aems.aeo.view` | AEO draft, independent review, signatures, approval, issue, distribution, acknowledgement, amendment, cancellation, void, and supersession |
| Planning Workspace | `/audit-engagement-management/planning-package` | SCR-221 / `aems.planning-package.view` | Preliminary survey, process flow, AEP + KPIs, risk matrices/items, audit programs/procedures, traceability, readiness, review, approval, return, revision, and immutable baseline |
| Engagement Plan | `/audit-engagement-management/aep` | SCR-222 / `aems.aep.view` | Objectives, scope, criteria, period, resources, procedures, communication/effectivity, controlled review and issue |
| Audit Program | `/audit-engagement-management/audit-program` | SCR-223 / `aems.program.view` | Program/procedure register, area/focus/risk/method/criteria/planned-day fields, execution state, reviewer notes, and links |
| Execution Workspace | `/audit-engagement-management/execution` | SCR-226 / `aems.fieldwork.view` | Fieldwork records, procedure execution, timeline, tasks, due dates, reviewer notes, WP/evidence links, blockers, and issue creation |
| Entry Conferences | `/audit-engagement-management/entry-conferences` | SCR-225 child / `aems.entry-conference.view` | Entry schedule, agenda, participants, attendance, venue/online details, attachments, minutes, and acknowledgement |
| Conference Management | `/audit-engagement-management/conferences` | SCR-225 / `aems.conference.view` | Entry/exit timelines, findings discussed, agreements/disagreements, revised dates, attendance, and dialogue history |
| Working Papers & Evidence | `/audit-engagement-management/working-papers` | SCR-228 / `aems.working-paper.view` | WP index, objective/procedure/population/sample/results/conclusion, preparer/reviewer dates, cross-references, immutable versions, approval locking, and evidence links |
| Evidence Management | `/audit-engagement-management/evidence` | SCR-229 / `aems.evidence-request.view` | Evidence Request lifecycle, receipt/submission, custody/checksum/confidentiality, assessment/outcome, restrictions/gaps, versions, and traceability |
| Audit Issues | `/audit-engagement-management/issues` | SCR-230 / `aems.issue.view` | Issue review, validation, merge, referral, resolution, observation, conversion, withdrawal, dismissal, and terminal disposition |
| Findings & Recommendations | `/audit-engagement-management/findings` | SCR-240 / `aems.finding.view` | Criteria, condition, cause, conclusion, effect, risk, evidence, responsible office, management response, rejoinder, recommendation, controlled correction, finalization, and immutable snapshots |
| Auditee Responses | `/audit-engagement-management/auditee-responses` | SCR-241 / `aems.management-response.view` | Formally communicated findings only; agree/partial/disagree, comments, corrective actions, responsible personnel, target dates, attachments, clarification, extension, late, and supplemental responses |
| Exit Conferences | `/audit-engagement-management/exit-conferences` | SCR-225 child / `aems.conference.view` | Exit schedule, participants, attendance, findings, agreements/disagreements, revised targets, minutes, attachments, and acknowledgement |
| Audit Reporting Workspace | `/audit-engagement-management/reports` | SCR-250 / `aems.report.view` or `aems.report.view_issued` | Interim/draft/final assembly, section order, executive summary, review, authority decisions, signatures, transmittal, distribution, acknowledgement, amendment, withdrawal, supersession, and protected PDF |
| Operational Work Queues | `/audit-engagement-management/work-queues` | `aems.task.view` | Tasks, review notes, due process, escalation candidates, assignment, due/overdue transitions, notifications, and audited actions |
| Audit Calendar | `/audit-engagement-management/calendar` | `aems.calendar.view` | Engagement milestones, owners, dates, overdue state, and completion monitoring |
| Registers & Exports | `/audit-engagement-management/registers` | `aems.engagement.view` and export permissions | Engagement, progress, queue, records, reports, document index, and authenticated protected CSV/PDF exports |
| Records & Administrative Closure | `/audit-engagement-management/records-closure` | closure/records/retention permissions | Completion assessment, closure blockers, retention monitoring, legal holds, archive/disposition, destruction eligibility, record search, formal closure, and controlled reopening |

Process Flow and Risk Matrix are artifacts inside the SCR-221 Planning Package;
they are intentionally not duplicate sidebar pages. The SCR registry contains
32 canonical identifiers, including reserved `SCR-243`.

## 3. Governance decisions (G0)

These decisions are the professional-control contract that later phases must
follow. They are not optional UI conventions.

| Decision | Current rule |
| --- | --- |
| Authority/signatories | Preparer drafts/revises; independent reviewer assesses; approver decides; issuer/signatory releases. AEO and report authority matrices are append-only and version-bound. When the active CIAS Head is the sole available CIAS Management authority, she may review, approve, and issue an AEO she prepared; the same controlled exception may be used for her own AEMS review/acceptance actions across planning, execution, findings, reporting, transfer, and closure. Every use is limited to the sole-head deployment condition and recorded immutably. |
| Direct AFR | Direct Finding creation requires an authorized reason, source/authority context, engagement scope, and audit event. Normal conversion from an Issue remains supported. |
| Evidence Request | `DRAFT → SUBMITTED → SENT → ACKNOWLEDGED → PARTIALLY_RECEIVED → RECEIVED → FOR_REVIEW → ASSESSED → CLOSED`; controlled `OVERDUE`, extension, escalation, cancellation, and closed-without-submission states are separate decisions. |
| Audit Evidence | Technical states such as `REGISTERED`, `FOR_ASSESSMENT`, `ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`, `DUPLICATE`, `SUPERSEDED`, and `VOIDED` are distinct from assessment and document lock state. `LOCKED` never means professional acceptance by itself. |
| Assessment scale | Positive/adequate dimensions, explicit confidentiality, no unresolved gaps, and approved exceptions for restrictions/limitations are required before evidence may support a validated/finalized finding. |
| Response extension | An extension needs a reason, future date, independent review, immutable event, and effective due date. Late, supplemental, and replacement responses are distinct versioned records. |
| Retention | Approved retention metadata is immutable; legal hold overrides archive/disposition; destruction eligibility is a reviewed state, not physical deletion. |
| Planning units | Risk matrices may be multiple where authorized; programs/procedures carry area, focus, process, method, criteria, sampling, planned days, and planned-WP requirements. |
| Conference waiver | Waiver authority, reason, actor, and audit reference are required; absence of a conference cannot be silently inferred. |
| Effort/provider | ARMIS is the only operational provider; current ARMIS status and planned/actual effort checks apply. Historical provider reconciliation is not required for approval. |
| Signatures/transmittal | Signatures are authenticated in-app attestations with actor, timestamp, method/reference, version, and immutable transmittal/acknowledgement events. |
| Report distribution | IAU Head recommendation and LCE approval are recorded; required signatories, recipient decisions, delivery, acknowledgement, and confidentiality are preserved. |
| Completed vs Closed | `COMPLETED` means substantive audit work finished. `CLOSED` means formal administrative closure after authoritative records, retention, transfer, and blocker reconciliation. |
| Acceptance/change control | Governance changes require a new versioned decision, compatibility plan, updated rule/SCR/role tests, and documentation truth pass. |

## 4. Unified engagement lifecycle

```text
Approved IAP source or special/emergency authorization
  → one-office scope and AEO/team authority
  → Planning Package + AEP approval
  → Audit Program and fieldwork execution
  → Working Papers + Evidence Requests/assessment
  → Issues → Findings → management dialogue
  → Entry/Exit Conferences
  → Interim/Draft/Final reporting and distribution
  → finalized recommendation snapshot → one-time CMS transfer
  → completion assessment → COMPLETED
  → retention/records/blocker reconciliation → CLOSED
```

The aggregate lifecycle is atomic and scope-aware. Child records retain their
own versions and events. Approved/issued artifacts cannot be overwritten;
corrections create revisions or superseding records.

## 5. Phase implementation record (G0–G10E)

| Phase | Module capability consolidated | Current implementation and gate |
| --- | --- | --- |
| G0 | Governance and conformance contract | 14 professional decisions, status compatibility map, rule-to-code-to-test matrix, authority and change-control ownership published |
| G1A/G1B | Immediate professional-control hardening | Evidence eligibility enforced; immutable request/assessment versions; required Finding Conclusion; direct-Finding authority; real planning progress/KPI gates; UI removes ineligible actions |
| G2A/G2B | Foundation, scope, lifecycle, navigation | Reviewed one-office backfill/invariant, structured Area/Focus scope, limitations/source variance, special authorization, Core numbering, IAP risk discriminator, distinct COMPLETED, SCR-212 workspace |
| G3A/G3B | Planning conformance | Structured Process Flow, multiple matrices, Rule-35 risk fields, program/procedure criteria/process/method/person-days, KPI/planned-WP/sampling traceability, strict fieldwork readiness |
| G4A/G4B | AEO and team authority | Signatory matrix, AEO signatures, distribution/transmittal/acknowledgement, cancel/void/amend/supersede, stronger separation, team amendments/consequence assessment, access history |
| G5A/G5B | Complete Evidence lifecycle | Full request states, acknowledgement, extension, overdue/escalation, cancellation/no-submission closure, explicit outcomes, acquisition/source links, report links, consolidated Evidence workspace |
| G6A/G6B | Issues, AFR, dialogue, queues | Status compatibility, withdrawal and terminal dispositions, AFR delivery/acknowledgement, response extensions and late/supplemental versions, operational Tasks/Review Notes/due process/escalation actions |
| G7A/G7B | Reporting and distribution | Interim source links and final treatment, Issue/WP/Evidence traceability, IAU Head/LCE decisions, signatories/transmittals, reproducible protected exports, administrative report closure |
| G8A/G8B | Records, calendar, closure hardening | Retention/archive/disposition/legal-hold controls, destruction review, Audit Calendar/milestones, complete closure blockers, records search and monitoring, distinct COMPLETED/CLOSED |
| G9 | Verification and documentation truth | Rule/SCR/role matrices, duplicate-route check, mutation/download/responsive coverage, migration rehearsal, documentation corrections |
| G10A/G10B | Dashboard and frontend conformance | Scope-aware backend dashboard, responsive hoverable cards, phase/status filters, work queues, notification/empty/error/unauthorized states, extended taxonomy/criteria traceability |
| G10C | Operational queues and output surfaces | Dedicated Tasks, Review Notes, Due Process, Escalation Candidates, Calendar, Registers/Exports routes with actions and responsive layouts |
| G10D | Records and administrative closure surface | Dedicated records/closure workspace with retention, legal hold, archive/disposition, blockers, and reopening history |
| G10E | Governance and final acceptance | Final status map, semantic Rule 1–35 tests, 32 SCR checks, 6-role navigation matrix, acceptance record and documentation truth pass |

## 6. Professional controls that apply everywhere

- Every API is authenticated and permission-protected. Engagement, office,
  assignment, confidentiality, and role scope are checked by Laravel services
  and policies; React visibility is not the security boundary.
- Separation of duties prevents preparers from independently reviewing,
  approving, validating, issuing, or finalizing their own professional work.
- Current optimistic lock versions are required for mutable transitions.
- Approved/issued/finalized versions are immutable. Revisions preserve the
  superseded version and record correction/amendment reasons.
- Core `document_versions` is authoritative for checksum, file size, MIME type,
  custody, confidentiality, version lineage, and protected authenticated
  downloads. Public document/export URLs are not exposed.
- Material mutations create Activity Log entries, Audit Trail events,
  workflow events, and notifications where the module contract requires them.
- Automation may identify readiness, create reminders/candidates, or prepare a
  draft; it may not make final professional decisions, close a record, reopen a
  record, or issue an escalation notice automatically.

### AEO authority guidance

The AEO workspace exposes responsible-account guidance beside workflow actions
and the immutable signatory matrix. The normal route is:

1. The preparer submits the AEO version.
2. An assigned AEMS Reviewer records the independent review. If the preparer
   is the active CIAS Head and no alternate CIAS Management authority is
   available, that same CIAS Head may record the review under the controlled
   single-authority exception.
3. An active user with the `cias_management` role approves the reviewed
   version. The sole active CIAS Head may approve her own AEO when no alternate
   authority is designated.
4. An active `cias_management` authority issues the approved version. The sole
   active CIAS Head may also issue the AEO she approved under the same narrow
   exception.
5. After the AEO is issued, the engagement Lifecycle workspace exposes the
   aggregate `ISSUE_AUTHORIZATION` action. The sole active CIAS Head may
   execute that action only when the issued AEO was prepared, approved, and
   issued by that same account; it changes the aggregate state to
   `AUTHORIZED` and is a separate, auditable lifecycle event.
6. For later AEMS records, the same sole active CIAS Head exception may be used
   when she is the record preparer: she may review and approve her own AEP,
   Planning Package, Audit Program, Working Paper, Fieldwork, evidence,
   finding, report, completion, transfer, or closure submission where that
   workflow exposes the corresponding action. The normal role, readiness,
   immutable-version, evidence, and status gates still apply; the exception
   only removes the preparer-versus-reviewer identity conflict when no
   alternate active CIAS Management authority exists.

Auditee office heads and the City Mayor are recipients/acknowledgers of an
issued AEO, not internal AEO approvers or issuers. They do not need internal
AEMS operational access: issued copies are acknowledged through the CMS
recipient portal. The matrix displays the
actual actor's name, employee ID, username, position, timestamp, and signature
status after each step. When a pending authority has no eligible active
account, the workspace tells the administrator to designate another CIAS
Management account rather than silently weakening separation of duties.

## 7. API/data and test references

The complete payload and endpoint inventory is in [API and Data Reference](API_AND_DATA_REFERENCE.md). The implementation authorities are:

```text
Frontend routes/navigation  src/App.jsx, src/config/navigation.js
Backend routes              backend/routes/api.php
Validation                  backend/app/Http/Requests
Business rules              backend/app/Services and Policies
Persistence                 backend/app/Models and migrations
Defaults                    backend/database/seeders
Backend verification        backend/tests/Feature/Api/Aems*Test.php
Browser verification        tests/e2e/aems-*.spec.js
```

The final acceptance evidence is:

- semantic Rule 1–35 runtime acceptance tests;
- 32 canonical SCR registry checks, including reserved SCR-243;
- six seeded role navigation/contextual-tab matrices;
- authenticated protected-download and mutation checks;
- migration rehearsal and full Feature regression suites; and
- desktop/mobile AEMS conformance suites.

The current command results are maintained in this document's verification
snapshot below. The source code and test output remain authoritative when a
historical acceptance note differs from the current run.

## 8. Compatibility and non-goals

- Do not rename `AEMS`, `AEMS-*`, `aems.*`, or legacy `aem.*` identifiers.
- Do not merge or remove `iap_risk_assessments` or
  `iap_universe_risk_assessments` without a new governance decision.
- The standalone AFR route is a compatibility/navigation entry; AEMS owns the
  operational Findings and Recommendations workspace.
- AIS is outside the current AEMS implementation scope.
- Historical phase decisions are retained here as append-only sections; this
  compiled module document and the source/tests define the current status.

## 9. Consolidated phase implementation record

This section is the complete G0-G10E implementation record. Each phase lists
the business purpose, backend and frontend contracts, controls, key APIs and
the verification gate. The phase codes are historical delivery identifiers;
they do not create separate modules or routes.

### G0 - Governance and conformance contract

G0 resolved the professional decisions that later implementation phases must
not invent independently:

- authority/signatory matrix: preparer, independent reviewer, approver and
  issuer are separate actors for AEO, planning, Working Papers, Findings,
  reports, distribution, completion and reopening;
- direct AFR policy: normal Issue-to-Finding conversion remains the default;
  direct Finding creation requires an authorized reason, authority reference,
  factual basis, scope and independent review;
- Evidence Request and Audit Evidence compatibility states are separate from
  technical document locking; receipt never equals professional acceptance;
- evidence assessment uses `NOT_ASSESSED`, `INADEQUATE`, `PARTIAL`,
  `ADEQUATE`, and justified `NOT_APPLICABLE`, with gaps, contradictions,
  limitations, restrictions and exceptions explicitly recorded;
- response extensions preserve the original due date, require independent
  review and an immutable decision; late, supplemental and replacement
  responses are distinct versions;
- retention defaults are ten years for operational audit records and permanent
  for issued reports, closure decisions, reopening decisions and decision
  trails; legal hold overrides disposition;
- Risk Matrices and Audit Programs are Area/Focus-scoped planning units;
  multiple matrices are permitted when authorized, and shared procedures must
  retain explicit Area/Focus ownership and criteria;
- signatures are authenticated AGIS attestations bound to a version and hash;
  transmittal, delivery and acknowledgement are append-only records;
- `COMPLETED` means substantive audit work is finished; `CLOSED` means formal
  administrative closure after records and retention reconciliation; and
- governance changes require a versioned decision, compatibility map, updated
  tests and a documentation truth pass.

The decision register is identified as G0-01 through G0-14 (the final entry is
G0-14, the rule-to-code-to-test and change-control decision). A dedicated
**Status compatibility map** preserves legacy database values while exposing
the canonical labels and action semantics used by the UI and APIs.

The status compatibility map preserves existing database codes while exposing
canonical labels and action semantics. It covers aggregate engagement,
AEO/AEP, Audit Program, Working Paper, Evidence Request, Issue/Finding,
management response, report, retention and closure statuses. Legacy `AEMS`,
`aems.*`, and `aem.*` identifiers remain compatible.

The runtime compatibility vocabulary includes `COMPLETED`, `CLOSED`,
`ACKNOWLEDGED`, `EXTENSION_REQUESTED`, `ADMINISTRATIVELY_CLOSED`, `WITHDRAWN`,
`ACCEPTED` and `CLOSED_WITHOUT_SUBMISSION` in addition to the request,
assessment, review, issuance, transfer and retention states described below.

### G1A/G1B - Immediate professional-control hardening

Backend enforcement is provided by the AEMS evidence, finding and planning
services. A Finding cannot be submitted, validated or finalized without a
required Conclusion. Direct Finding creation records a controlled authority
reason. Evidence eligibility requires the current Evidence version, the exact
Core `document_versions` row, an immutable assessment, positive mandatory
dimensions, no unresolved gap/limitation/contradiction, and an approved
exception for any restricted or limited use. Evidence Request and assessment
versions are append-only; corrections create superseding versions. Planning
progress and KPI gates are calculated from the approved baseline rather than a
hard-coded frontend flag.

The Findings and Evidence workspaces show eligibility and blocking reasons,
require Conclusion, expose the direct-Finding authority form, and suppress
Validate/Finalize actions for ineligible evidence. The gate is that negative,
incomplete or unresolved evidence cannot support a validated or finalized
Finding.

### G2A/G2B - Foundation, scope, lifecycle and navigation

The foundation contract enforces one canonical engagement office, reviewed
backfill/invariant behavior, structured Area/Focus coverage, boundaries,
exclusions, limitations and source variance. Special and emergency
authorizations preserve authority and reason. Core numbering is used for
engagement identifiers, and the IAP risk-source discriminator preserves the
coexistence of `iap_risk_assessments` and
`iap_universe_risk_assessments`. `COMPLETED` is distinct from `CLOSED`.

SCR-212 is represented in the engagement workspace and the sidebar/route
registry uses one canonical entry per SCR. Scope checks are enforced in
Laravel services and policies; UI visibility is not a security boundary. The
focused gate covers the office invariant, Area/Focus relationships, source
lineage, lifecycle projection and duplicate-route exclusion.

### G3A/G3B - Planning conformance

Planning Package persistence includes structured Process Flow details, Area and
Focus coverage, multiple authorized Risk Matrices, Rule-35 risk fields and
traceability. Audit Programs and procedures carry audit type, period, process,
method, criteria, planned person-days, sampling and planned Working Paper or
Evidence requirements. KPI records and non-applicability rationale are
version-bound and immutable after approval.

The Planning Package UI provides Process Flow editing/viewing, Risk Matrix and
Risk Item details, traceability, KPI and sampling/planned-WP panels, readiness,
review, return and version comparison. The aggregate `START_FIELDWORK`
transition evaluates the complete approved baseline and returns all blockers;
fieldwork cannot begin from an incomplete or unapproved package.

### G4A/G4B - AEO and team authority

The Engagement Order authority contract separates preparation, review, approval,
issuance and distribution. AEO signatures record the signatory, authority,
method, timestamp and immutable source version. Recipient distribution,
transmittal and acknowledgement records are retained against the issued AEO.
Cancel, void, amend and supersede operations create controlled decisions and
never overwrite an issued order.

Team amendments require an authorized actor, an amendment reason, effective
dates, consequence assessment and access-history record. Competency,
independence, objectivity, workload and conflict safeguards are re-evaluated
when a team amendment is proposed. The gate blocks issuance or approval when a
required signatory, safeguard or resource decision is missing.

The main permissions are `aems.aeo.sign`, `aems.aeo.review`,
`aems.aeo.approve`, `aems.aeo.issue`, `aems.aeo.distribute`,
`aems.aeo.acknowledge`, `aems.aeo.cancel`, `aems.aeo.void`,
`aems.aeo.amend`, `aems.aeo.supersede`, `aems.team.amend` and
`aems.team.history`. Every authority action emits an Activity Log and Audit
Trail event.

### G5A/G5B - Complete Evidence lifecycle

Evidence Requests use the controlled lifecycle `DRAFT`, `SUBMITTED`,
`FOR_REVIEW`, `SENT`, `ACKNOWLEDGED`, `PARTIALLY_RECEIVED`, `RECEIVED`,
`ASSESSED` and `CLOSED`. `OVERDUE`, `EXTENDED`, `ESCALATED`, `CANCELLED` and
`CLOSED_WITHOUT_SUBMISSION` are exception or terminal states, not substitutes
for receipt. Acknowledgement identifies the custodian and date; extensions
record authority, reason and revised due date; overdue and escalation actions
are reviewable and auditable.

Each evidence version has an explicit outcome: `REGISTERED`, `FOR_ASSESSMENT`,
`ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`, `DUPLICATE`,
`SUPERSEDED` or `VOIDED`. Assessment dimensions cover sufficiency,
appropriateness, relevance, reliability, competence, accuracy, completeness,
corroboration, contradiction, authenticity, integrity, confidentiality,
restrictions, limitations and gaps. Negative, incomplete, restricted or
unresolved evidence cannot support finding validation/finalization unless an
approved exception exists.

Acquisition method/form and direct objective, control, risk, Working Paper,
Fieldwork, Issue, Finding and Interim/Final Report links are persisted. Files
continue to use Core `document_versions` for checksum, size, MIME type,
custody, confidentiality and protected downloads. The consolidated Evidence
workspace distinguishes requested, received, assessed, restricted,
insufficient and accepted-for-reporting records and exposes version comparison
without allowing mutation of immutable evidence.

### G6A/G6B - Issues, AFR, dialogue and queues

Issue status compatibility preserves legacy values while presenting the
canonical Draft, For Review, Open, Under Evaluation, Disposed and Withdrawn
semantics. Terminal dispositions include Converted to Finding, Merged,
Resolved During Audit, Observation, Referred, Closed Without Finding and
Dismissed. Withdrawal records authority and reason. Direct Finding creation is
allowed only with an authorized reason and source/authority record; Conclusion
is mandatory and authors cannot validate their own findings.

AFR communication stores recipient office, issuance method, transmittal,
delivery and acknowledgement snapshots. Management responses support original,
late, supplemental and replacement submissions. Clarification and response
extensions preserve the prior version and record the approving authority,
reason and revised due date.

Tasks, Review Notes, due-process events and escalation candidates are
operational records with assignee, owner, due date, status, engagement/office
scope, separation checks, notification state and immutable transition history.
Queues expose assignment, completion, return, clarification, escalation and
overdue actions only when the current role and record status allow them.

### G7A/G7B - Reporting and distribution

Every report version stores a reproducible source manifest. The manifest
identifies the exact approved Finding, Issue, Working Paper and Core Evidence
versions used to build the report and is hashed with SHA-256. Interim report
sources and Interim-to-Final treatment (carried, revised, superseded or
excluded with reason) remain traceable.

IAU Head and LCE authority decisions, signatory matrix entries, transmittal
references, recipient delivery and acknowledgement are separate immutable
records. Issued reports are locked; an amendment, withdrawal or superseding
report creates a new version. Protected authenticated PDF/CSV exports are
generated by the backend, include the source/version metadata and retain a
checksum. Administrative closure of a report records its final distribution,
retention and unresolved exception state.

### G8A/G8B - Records, calendar and closure hardening

Retention records support `ACTIVE`, `ARCHIVED` and `DISPOSITION_RECORDED`
states, schedule and retention dates, legal holds and releases, destruction
eligibility review, approving authority and immutable archive/disposition
decisions. A record cannot be destroyed while a legal hold, unresolved
investigation, report dependency or other blocker is present. Records search
and retention monitoring are scope-aware and protected.

Audit Calendar milestones have owner, type, due date, completion date, status,
engagement linkage and due/overdue indicators. Closure readiness reconciles
planning, fieldwork, evidence, findings, responses, reports, CMS transfer,
person-days, retention and records blockers. Substantive completion is
`COMPLETED`; formal administrative closure is `CLOSED` and requires every
closure blocker to be resolved. Reopening preserves the original closure
decision and creates a new immutable reopening decision.

### G9 - Verification and documentation truth

The final conformance suite maps Rules 1-35 to runtime anchors, permissions,
scope checks, persistence and focused tests. The SCR registry contains 32
canonical identifiers (including reserved SCR-243), and every canonical
sidebar/engagement route is registered once. Role-by-role menu and tab checks,
authenticated protected-download checks, negative evidence checks,
desktop/mobile route checks, mutation workflows, migration rehearsal and
documentation truth checks are included in the verification plan.

### G10A/G10B - Dashboard, queues and responsive surfaces

The AEMS Dashboard is calculated from scope-aware backend queries. It presents
active engagements, phase counts, overdue procedures, Working Papers awaiting
review, Evidence Requests and gaps, findings and responses, conferences,
reports pending approval, CMS transfer exceptions, closure readiness, tasks,
review notes and escalation candidates. Cards are hoverable and responsive;
zero, loading, error and unauthorized states are explicit, and visible cards
fill the available row for each role's scope.

### G10C - Operational queues and output surfaces

Dedicated responsive workspaces exist for Tasks, Review Notes, due process,
escalation candidates, Audit Calendar, Registers & Exports and the related
notification/overdue indicators. Queue actions support assignment, due-date
changes, status transitions, return/clarification/escalation and controlled
completion. Every action is role-, engagement-, office- and
separation-of-duties-aware, logged and notification-capable. Register and
report exports use authenticated protected endpoints.

### G10D - Records and administrative closure conformance

The Records & Administrative Closure workspace exposes retention monitoring,
archive/disposition review, legal-hold controls, destruction eligibility,
closure-blocker reconciliation, completion assessment and closure history. The
backend rejects a `CLOSED` transition when an unresolved record, retention,
report, transfer or professional blocker remains. `COMPLETED` and `CLOSED`
remain distinct in API responses, navigation and audit events.

### G10E - Governance and final acceptance

G10E is the acceptance record for the complete AEMS governance contract. It
requires the resolved governance decisions above, the status compatibility map,
the 35-rule semantic matrix, the 32-SCR registry, role/navigation tests,
protected-download tests, negative professional-control tests, migration
rehearsal, full backend/frontend regression and a documentation truth pass.
The semantic acceptance target is explicitly **35/35 rules** and **32/32 SCR
entries**. Historical delivery identifiers G0-G10E are preserved for audit
traceability but do not create separate modules or routes.

## 10. Route, permission and data ownership index

| Concern | Canonical surface | Primary permission family | Owner/source |
| --- | --- | --- | --- |
| Engagement registry and scope | `/audit-engagement-management` | `aems.engagement.*` | AEMS, with approved IAP lineage |
| AEO and team authority | `/audit-engagement-management/aeo`, `/team` | `aems.aeo.*`, `aems.team.*` | AEMS; Core users/offices; ARMIS resources |
| Planning and program | `/planning-package`, `/aep`, `/audit-program` | `aems.planning-package.*`, `aems.aep.*`, `aems.program.*` | AEMS; IAP source references |
| Execution, WP and Evidence | `/execution`, `/working-papers`, `/evidence` | `aems.fieldwork.*`, `aems.working-paper.*`, `aems.evidence-request.*` | AEMS; Core Document Versions |
| Issues, findings and dialogue | `/issues`, `/findings`, `/auditee-responses` | `aems.issue.*`, `aems.finding.*`, `aems.management-response.*` | AEMS; auditee office scope |
| Conferences and reports | `/conferences`, `/exit-conferences`, `/reports` | `aems.conference.*`, `aems.report.*` | AEMS; Core documents and notifications |
| Queues, calendar and closure | `/work-queues`, `/calendar`, `/records-closure` | `aems.task.*`, `aems.calendar.*`, closure/records permissions | AEMS; Core retention/activity/audit |
| CMS transfer | Report/completion contextual workspaces | `aems.recommendation.transfer` | AEMS owns provenance; CMS owns monitoring/closure |
| Resource provider | Team and assignment panels | ARMIS provider permissions | ARMIS authoritative only; historical IAP/fallback values are lineage-only |

## 11. Verification snapshot

The source tree and test output are authoritative. The latest recorded checks
for this documentation pass are:

| Check | Result |
| --- | --- |
| `php artisan test` | Passed: 396 tests, 4,673 assertions |
| `npm.cmd run lint` | Passed |
| `npm.cmd run build` | Passed |
| `git diff --check` | Passed |
| Broad Playwright run | Incomplete: the fresh-port run timed out; the captured failure was a strict locator in `tests/e2e/aems-reporting.spec.js:31` because two `Protected PDF` buttons matched. The default-port run also hit the login throttle. |

The Playwright result is intentionally not represented as a full acceptance
pass until the report selector is scoped and the suite is rerun. This does not
change operational behavior, but it prevents the documentation from claiming
verification that has not completed.

## 12. Maintenance and change control

`AEMS_GOVERNANCE_AND_ACCEPTANCE.md` is the single active AEMS G-phase
document. Update this file when a governance decision, phase control, route,
permission, data rule or verification result changes. Keep
`AEMS_WORKFLOW_DESIGN.md` as the functional lifecycle narrative,
`AEMS_CROSS_MODULE_INTEGRATION.md` as the integration boundary reference and
`AEMS_IMPLEMENTATION_BASELINE.md` as the historical implementation baseline.
Do not create another standalone AEMS G phase file for a correction; add an
append-only dated entry here and update the relevant API/data or testing guide.

## 13. Merged historical phase source detail

The appendices below preserve the complete text of the retired AEMS-G phase
documents for audit traceability. They are intentionally merged here rather
than maintained as separate files. Their historical command-result statements
describe earlier checkpoints; the Verification snapshot in section 11 is the
current result and takes precedence when a historical result differs.

### Merged source detail: AEMS_G0_GOVERNANCE_CONFORMANCE_CONTRACT

# AEMS-G0 Governance and Conformance Contract

## 1. Purpose, authority, and scope

This document resolves the open professional and interoperability decisions
identified in **MDS-200 AEM v0.8**, **UID-200 v0.5**, and **DGM-200 v0.2**. It is
the AGIS implementation and acceptance contract for AEMS. Runtime behavior is
still determined by the source code and tests; compatibility exceptions are
listed explicitly in the status map and rule matrix rather than left implicit.

The reference documents are draft/review artefacts. This contract is the
project-level decision record that prevents a later implementation phase from
inventing a different authority, status, retention, evidence, or reporting
policy. A decision may be changed only through a new, versioned governance
decision and a documented compatibility plan.

The source code and automated tests remain the as-built authority. Therefore,
each decision below identifies both the target rule and the compatibility
behavior that remains in the current release. AEMS compatibility identifiers
(`AEMS`, `aems.*`, and `aem.*`) are retained.

**Effective date:** 13 August 2026
**Contract version:** G0.1
**Owner:** CIAS Management / AGIS Product Governance
**Implementation scope:** governance, conformance, and test contract only;
no schema, route, permission, or operational workflow change is included.

## 2. Resolved decision register

### G0-01 — Authority and signatory matrix

The following matrix is the minimum authority separation for professional
decisions. Normally “Preparer” may draft and revise only; the active CIAS Head
has a narrowly documented exception to record AEO review, approval, and
issuance of her own prepared version when she is the sole active CIAS
Management authority. “Reviewer” performs the independent assessment. “Approver” records
the final professional decision. “Issuer/signatory” authorizes release to an
external recipient.

| Record or action | Preparer | Independent reviewer | Approver | Issuer/signatory | Required separation |
| --- | --- | --- | --- | --- | --- |
| Engagement source and AEO | Team Leader or Engagement Supervisor | Assigned Reviewer (or sole active CIAS Head self-review exception) | Active CIAS Management authority; sole active CIAS Head may approve her own AEO | Active CIAS Management authority; sole active CIAS Head may issue her own approved AEO | Separate approver/issuer accounts are the normal rule; the documented sole-head exception extends to the corresponding aggregate and later AEMS review/approval actions only when no alternate active CIAS Management authority exists. |
| AEP, Planning Package, and Audit Program | Team Leader or assigned auditor | Reviewer | CIAS Management authorized audit authority | Not externally issued; approved baseline is the authority | Preparer cannot approve the same version. |
| Team safeguards and ARMIS authority | Engagement Supervisor | Independent safeguard reviewer | Designated CIAS/ARMIS resource authority | Not applicable | The person declaring a conflict cannot approve its mitigation. |
| Working Paper | Assigned Auditor | Reviewer | Reviewer with approval permission | Not externally issued | Preparer cannot review or approve the same version. |
| Issue and Finding validation | Assigned Auditor/Finding author | Independent Finding Reviewer | CIAS Management validation authority | Not applicable until report communication | Finding author cannot validate the finding. |
| AFR communication and finalization | Finding team | Independent AFR Reviewer | CIAS Management AFR authority | Designated CIAS Management signatory | AFR issuance is independent from preparation. |
| Management Response | Auditee Representative | Assigned Auditor/Reviewer | Auditee office authority for its response | Not applicable | The auditor cannot author the auditee response. |
| Auditor Rejoinder and dialogue finalization | Assigned Auditor/Reviewer | Engagement Supervisor or independent reviewer | CIAS Management dialogue authority | Not applicable | Auditee author cannot approve the rejoinder. |
| Interim/Final Report | Report preparer | Quality Reviewer | CIAS Management report authority | CIAS Management designated signatory | Preparer and reviewer cannot approve; issuer cannot be the preparer. |
| Report distribution | Distribution officer | Quality/records reviewer | CIAS Management distribution authority | Same designated signatory as the approved report, or a separately delegated signatory | Recipient, method, delivery, and acknowledgement are recorded per version. |
| Completion and formal closure | Completion assessor | Independent closure reviewer | CIAS Management closure authority | Not externally issued | Completion assessor cannot approve closure. |
| Controlled reopening | Reopening requester | Independent reviewer | CIAS Management reopening authority | Not applicable | Original closure decision is never replaced. |
| CMS transfer | AEMS transfer officer/service | Transfer reconciliation reviewer for exceptions | No new professional decision; transfer is permitted only from an issued report | Not applicable | Transfer is idempotent and cannot create or alter a recommendation decision. |

An authenticated user identity, authority basis, decision date/time, reason,
and exact version/document hash are recorded for every approval, issuance,
waiver, distribution, closure, and reopening decision. A permission grants the
ability to perform an action; it does not by itself grant professional
authority.

### G0-02 — Direct AFR policy

The normal path is **Issue → Finding → AFR**. A Finding may be created without
an Issue only when one of these controlled reasons is selected:

1. `URGENT_OR_MATERIAL_RISK` — delay in creating an Issue would expose a
   material or urgent risk;
2. `FORMAL_DIRECTIVE_OR_LEGAL_REQUIREMENT` — a law, regulator, CIAS directive,
   or formally approved request requires a direct AFR; or
3. `CROSS_CUTTING_OR_SYSTEMIC_MATTER` — the matter spans issues or offices and
   a single authoritative finding is more traceable than duplicate Issues.

Direct AFR creation requires an immutable authorization record containing the
reason, factual basis, directing authority, date, affected engagement/area,
and independent reviewer. The finding must still contain all required
criteria, condition, cause, conclusion, effect/significance, risk, evidence,
and recommendation fields. “No Issue was convenient” is not an authorized
reason. A direct finding cannot be self-authored and validated.

### G0-03 — Evidence Request statuses and lifecycle

The canonical future lifecycle is:

```text
DRAFT → FOR_REVIEW → SENT → ACKNOWLEDGED →
PARTIALLY_RECEIVED → RECEIVED → ASSESSED → CLOSED
```

The following exception states are controlled, not automatic professional
decisions:

- `OVERDUE` is a due-date condition recorded when a complete receipt is not
  recorded by the due date;
- `EXTENDED` is an approved target-date extension and preserves the original
  due date;
- `ESCALATED` is a reviewable escalation candidate or approved escalation
  record, never an automatic notice;
- `CANCELLED` requires an authorized reason before complete receipt; and
- `CLOSED_WITHOUT_SUBMISSION` requires a documented authority decision and
  records why no submission will be expected.

The current `SUBMITTED` value is the compatibility representation of
`FOR_REVIEW`. Existing `PARTIALLY_RECEIVED`, `RECEIVED`, `ASSESSED`, and
`CLOSED` values retain their meaning. A request is not `ASSESSED` merely because
files were received; every received exact document version must have an
eligible assessment or an approved exception.

### G0-04 — Audit Evidence statuses

Professional evidence status is separate from Core document storage and
checksum state. The canonical evidence status set is:

```text
REGISTERED → FOR_ASSESSMENT → ACCEPTED
                         ├→ LIMITED
                         ├→ ADDITIONAL_REQUIRED
                         ├→ REJECTED
                         ├→ DUPLICATE
                         └→ SUPERSEDED
VOIDED (controlled terminal state)
```

The current `DRAFT` maps to `REGISTERED`, and `VERIFIED` maps to
`FOR_ASSESSMENT` because technical file verification is not professional
acceptance. Current `LOCKED` means the record/file is immutable; it must not be
interpreted as `ACCEPTED` without a professional assessment. Current `VOIDED`
remains `VOIDED`. Core `document_versions` continues to own checksum, file
size, MIME type, version, custody, confidentiality, and protected download
controls.

### G0-05 — Evidence assessment scale and eligibility

Each applicable assessment dimension uses exactly one of:

| Value | Meaning | Final-finding eligibility |
| --- | --- | --- |
| `NOT_ASSESSED` | No professional conclusion has been recorded | Always blocks |
| `INADEQUATE` | The dimension does not meet the evidence requirement | Blocks |
| `PARTIAL` | The dimension is only partly met | Blocks unless a separate authorized exception explicitly accepts the limitation and compensating corroboration |
| `ADEQUATE` | The dimension is acceptable for the stated purpose | May pass that dimension |
| `NOT_APPLICABLE` | The dimension genuinely does not apply | Requires written assessor rationale and independent review; prohibited for a mandatory dimension |

The scale applies to sufficiency, appropriateness, relevance, reliability,
competence, accuracy, completeness, corroboration, contradiction,
authenticity, integrity, and confidentiality. For contradiction,
`ADEQUATE` means no unresolved contradiction remains. For confidentiality,
`ADEQUATE` means the classification and access controls are sufficient.

An evidence version is eligible for a finalized finding only when it links to
the exact Core `document_versions` row, is current, has an assessment in the
`ASSESSED` family state, has no unresolved gaps/limitations/contradictions,
has all mandatory dimensions `ADEQUATE`, and satisfies restriction/exception
rules. `NOT_ASSESSED`, `INADEQUATE`, or `PARTIAL` evidence cannot silently pass
the gate.

Legacy rating values are read compatibly as `YES → ADEQUATE`, `NO →
INADEQUATE`, `PARTIAL → PARTIAL`, and `NOT_ASSESSED → NOT_ASSESSED` until the
assessment schema is migrated.

### G0-06 — Management-response extension and no-response policy

The communication snapshot sets the original management-response due date;
that date is never overwritten. An auditee office may request:

- one ordinary extension of up to **15 calendar days**; and
- one exceptional extension of up to **15 additional calendar days** for a
  documented force-majeure, legal, or materially complex circumstance.

Requests should be submitted before the due date. A late request is allowed
only with a recorded reason and CIAS Management approval. The auditor may
recommend an extension but cannot approve the auditee's extension request.
Each approved/rejected extension is an immutable version with requester,
assessor, authority, reason, old date, new date, and decision timestamp.

Reminder and due-process timing is: reminder three business days before the
date, due notice on the date, first non-response notice after five business
days, and final non-response decision after ten business days. A no-response
decision does not fabricate agreement, close the finding, or bypass the
existing dialogue/finalization workflow.

### G0-07 — Retention and records periods

The default AGIS retention schedule is measured from the formal engagement
`CLOSED` decision (or from the final disposition for cancelled engagements):

| Record class | Minimum retention |
| --- | --- |
| Engagement identity, AEO/AEP, Planning Package, Audit Program, Working Papers, Evidence metadata/files, Issues, Findings, Responses, Conferences, Reports, CMS transfer manifests, completion and reopening records | 10 years |
| Issued Final Reports, formal Closure Decisions, Reopening Decisions, legal-hold records, and immutable audit/Activity Trail needed to prove those decisions | Permanent |
| Superseded/returned drafts and report versions | 10 years, unless placed on legal hold |
| Reminder, notification, and transient queue records not part of the decision trail | 3 years |

Legal hold, litigation, statutory schedules, or a formally approved records
schedule always overrides the default and may extend retention. Retention does
not authorize deletion: archive, destruction eligibility, approval, execution,
and the immutable disposition event are separate records-management actions.

### G0-08 — Risk Matrix and Audit Program per Area

The default planning unit is the **Audit Area**:

- each approved Planning Package version has one or more Risk Matrices as
  needed, with at least one matrix for every in-scope Audit Area;
- a matrix belongs to one Audit Area and may cover multiple Audit Focuses;
- a Risk Matrix Item has one primary Audit Area and at least one Audit Focus;
  cross-area risks are represented by linked items or an explicit cross-area
  relationship, never by an unowned item;
- each Audit Area has one approved Audit Program baseline per Planning Package
  version; revisions create a new immutable program version;
- each procedure has one primary Audit Area and Audit Focus, with explicit
  links for any additional focus; and
- a shared procedure is allowed only when every covered Area/Focus is linked,
  the expected evidence and criteria are explicit, and area-specific
  ownership is preserved.

Process Flow documents follow the same Area/Focus scope and are revisioned.
Imported IAP risks remain source lineage; AEMS Risk Matrices and Programs are
engagement-specific planning artifacts and cannot mutate IAP records.

### G0-09 — Conference waiver authority

An Entry Conference waiver must be approved by the CIAS Management authorized
audit authority before fieldwork begins. An Exit Conference waiver must be
approved by the CIAS Management closure/report authority before final report
approval. A Team Leader, Reviewer, or Auditee Representative cannot approve
their own waiver. Each waiver records reason, authority, date, affected scope
or findings, compensating communication, and expiry/validity. Emergency or
special engagements use the same rule unless a separate written special
authority explicitly names the waiver authority.

### G0-10 — Effort boundary and ARMIS authority

AEMS records engagement planned and actual person-days; ARMIS is authoritative
in the sole `ARMIS_AUTHORITATIVE` mode. `IAP_INTERIM_FALLBACK` and
`ARMIS_SHADOW` are historical compatibility values only. Planned effort covers approved engagement work and
procedures. Actual effort covers recorded engagement work, fieldwork, review,
and report/closure effort; leave, training, and unrelated administration are
excluded unless explicitly configured as a separate category. Completion
requires reconciliation of AEMS and the active provider. Missing or stale
provider data blocks approval; it never causes an automatic provider switch.

### G0-11 — Signature, transmittal, and acknowledgement method

The authoritative signature is an authenticated AGIS electronic approval. The
system stores signer, role, authority basis, UTC timestamp, exact record
version, and SHA-256 of the signed Core Document Version. A wet signature or
external signature file may be attached as supporting evidence but does not
replace the AGIS decision record.

The default transmittal is a protected, authenticated AGIS portal download.
Email may notify a recipient but may not be the authoritative delivery of a
confidential report or evidence file. Every transmittal receives a unique
identifier and immutable recipient snapshot, method, delivery timestamp,
acknowledgement/rejection decision, actor, and exact version/hash. Public file
URLs are prohibited.

### G0-12 — Report distribution authority

The final report quality reviewer records a recommendation. The CIAS
Management authorized distribution authority records the final decision. The
signatory matrix must identify the IAU Head recommendation (where applicable),
the LCE/Presiding Officer or delegated CIAS authority decision, and the
authorized signatory. A report cannot be marked issued until the authority,
recipient list, confidentiality classification, transmittal method, and exact
PDF checksum are recorded.

### G0-13 — Completed versus Closed

`COMPLETED` means the audit work is substantively finished: approved objectives
and procedures are executed or formally waived, Working Papers and evidence
are finalized, Issues/Findings/AFRs and responses are dispositioned, reports
and required CMS transfer reconciliation are complete, person-days and
retention/index records are reconciled, and lessons learned are recorded.
The engagement may still be awaiting the formal closure decision.

`CLOSED` means CIAS Management has approved the formal Closure Decision after
the completion assessment. The aggregate and ordinary child records are
locked, retention/records controls are active, and any reopening requires a
new immutable Reopening Decision that preserves the original closure.

The current release stores `COMPLETED` as a distinct aggregate status and only
the formal Closure workflow may move an engagement to `CLOSED`. No client may
treat `CLOSED` as mere management-reported completion.

### G0-14 — Acceptance authority and change control

The G10E acceptance gate is owned by CIAS Management and the AGIS product
owner. A release is accepted only when the semantic Rule 1–35 suite, SCR and
role/navigation suite, migration rehearsal, full Feature suite, lint/build,
protected-download checks, and desktop/mobile Playwright projects have exact
recorded results. A compatibility alias, legacy status, or deferred AIS
integration is not an unresolved governance decision. Any change to this
contract requires a versioned decision, an updated compatibility map, and
regression evidence before deployment.

## 3. Status compatibility map

G0 defines semantic compatibility; it does not rename existing database
values. Later migrations may add canonical values only with a backfill,
read/write compatibility, and regression tests.

| Contract concept | Canonical G0 meaning | Current as-built value(s) | Compatibility rule |
| --- | --- | --- | --- |
| Engagement review | `FOR_REVIEW` | `RETURNED_FOR_REVISION` and stage-specific review | Keep aggregate status; expose review stage/action separately. |
| Engagement completed | `COMPLETED` | `COMPLETED` | Completion assessment and aggregate transition establish substantive completion; formal Closure is still required for `CLOSED`. |
| AEO/AEP review | `FOR_REVIEW`, `RETURNED`, `APPROVED`, `ISSUED`, `SUPERSEDED` | `PENDING_REVIEW`, `RETURNED_FOR_REVISION`, `APPROVED`, `ISSUED`, `SUPERSEDED` | Map labels only; preserve stored codes. |
| Audit Program | `DRAFT`, `FOR_REVIEW`, `APPROVED`, `ACTIVE`, `COMPLETED`, `SUPERSEDED` | Same except `PENDING_REVIEW` represents `FOR_REVIEW` | Preserve current code. |
| Working Paper | `DRAFT`, `SUBMITTED`, `RETURNED`, `RESUBMITTED`, `APPROVED`, `SUPERSEDED`, `VOIDED` | Same, with `RETURNED_FOR_REVISION` | UI label may say Returned; code remains compatible. |
| Evidence Request | `DRAFT`, `FOR_REVIEW`, `SENT`, `ACKNOWLEDGED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `OVERDUE`, `EXTENDED`, `ESCALATED`, `ASSESSED`, `CLOSED`, `CANCELLED`, `CLOSED_WITHOUT_SUBMISSION` | `DRAFT`, `SUBMITTED`, `SENT`, `ACKNOWLEDGED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `FOR_REVIEW`, `ASSESSED`, `OVERDUE`, `EXTENSION_REQUESTED`, `EXTENDED`, `ESCALATED`, `CANCELLED`, `CLOSED_WITHOUT_SUBMISSION`, `CLOSED` | `SUBMITTED` remains the read-compatible alias for `FOR_REVIEW`; `EXTENSION_REQUESTED` is the explicit pending-extension state. |
| Audit Evidence | `REGISTERED`, `FOR_ASSESSMENT`, `ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`, `SUPERSEDED`, `DUPLICATE`, `VOIDED` | Technical `DRAFT`, `VERIFIED`, `LOCKED`, `VOIDED` plus professional `outcome` values | `LOCKED` is technical immutability; only `outcome=ACCEPTED` satisfies the final-finding gate. |
| Issue | `DRAFT`, `FOR_REVIEW`, `OPEN`, `UNDER_EVALUATION`, `DISPOSED`, `WITHDRAWN` | `DRAFT`, `SUBMITTED`, `VALIDATED`, `DISMISSED`, `CONVERTED_TO_FINDING` plus dispositions | Preserve disposition history; add proj…9533 tokens truncated…e`, and the controlled `aems.retention.archive`,
`aems.retention.legal_hold_release`, `aems.retention.destruction_review`, and
`aems.retention.disposition_execute` actions. Scope and closed-engagement
guards are applied by `AemsAccessService`; sensitive actions are CIAS-only or
reviewer-separated. The React engagement workspace exposes **Records &
Disposition** and **Audit Calendar** tabs with empty, error, search, and
permission-aware states. Records marked `RESTRICTED` or `SECRET` are omitted
unless the user has the Core `documents.view_restricted` permission.

## Verification

The focused closure regression now covers records search, milestone creation,
scope enforcement, and an auditable ineligible destruction review. The
existing four closure/completion tests continue to pass.

## 14. Deferred standards-governance roadmap

This section records standards-governance work identified by the comparison of
AGIS with the Revised PGIAM, NGICS, ICSPPS 2017 and IASPPS 2017. These items
are **not implemented by the current AEMS G0-G10E acceptance baseline**. They
are retained for a later governance decision and must not be inferred from a
placeholder route, a master-list value, a lessons-learned note or an
engagement-level safeguard.

| Priority | Deferred capability | Current as-built position | Proposed owner |
| --- | --- | --- | --- |
| 1 | [BAICS / Baseline Assessment of Internal Control System](BAICS_GOVERNANCE_CONTRACT.md) | BAICS-0 through BAICS-4 are implemented inside IAP: scoped cycles, five-component assessment, Control Universe, BAR, immutable versions, approved IAP-consumer decisions, legacy exceptions and staged risk-consumption gates. | IAP with Core standards governance |
| 2 | Internal Audit Charter and organizational independence governance | No Charter version/approval lifecycle, Head of Internal Audit authority record, reporting-relationship record, access-rights confirmation or periodic organizational-independence declaration. Engagement-level independence safeguards remain implemented. | Core / CIAS governance |
| 3 | IASPPS QAIP | No dedicated ongoing-monitoring, periodic internal-assessment, external-assessment, remediation or QAIP-reporting workflow. A QAIP reference in lessons learned is informational only. | Core / CIAS governance |
| 4 | Professional development and CPD/CPE compliance | ARMIS manages competencies, certifications, availability and training conflicts, but does not provide a complete annual development plan, CPE-hour ledger, training-needs assessment, completion record or competency-gap remediation lifecycle. | ARMIS |
| 5 | Standards traceability and control-component mapping | No complete requirement-to-module-to-control-to-test-to-evidence matrix for PGIAM, IASPPS, NGICS and ICSPPS, and no universal structured mapping of Control Environment, Risk Assessment, Control Activities, Information & Communication and Monitoring to procedures/findings. | Governance documentation first; optional Core/IAP support |

### Deferred implementation rules

- Do not describe AGIS as fully conforming to IASPPS until the IAS has a
  current QAIP, including the required internal and external assessments, and
  the responsible authority has approved the conformance statement.
- Do not treat AGIS as the agency's complete NGICS/ICSPPS internal-control
  system. AGIS supports CIAS's independent evaluation, documentation,
  reporting and follow-up of management-owned controls.
- BAICS may be closed by an approved equivalence decision if the existing IAP
  records demonstrably satisfy the required baseline assessment; otherwise a
  dedicated BAICS layer should be planned. No duplicate risk or control
  ownership tables should be created without that decision.
- A future standards phase must define scope, authority, data ownership,
  immutable versions, separation of duties, audit events, reports and tests
  before implementation begins.

Until a future phase is approved, these items remain documented gaps and do not
alter the current AEMS workflows, permissions, routes or module boundaries.

### Merged source detail: AEMS_G9_VERIFICATION_AND_TRUTH

# AEMS-G9 Verification and Documentation Truth Pass

Status: implemented as a verification and documentation phase on 13 August
2026. This phase adds regression contracts and corrects the as-built record; it
does not change AEMS workflow behavior.

## Verification index

`backend/tests/Feature/Api/AemsG9ConformanceTest.php` is the historical G9
registry index. The final semantic acceptance index is
`backend/tests/Feature/Api/AemsG10EAcceptanceTest.php`. Together they create:

- 35 G9 source-contract rows and 35 G10E runtime semantic Rule tests (`Rule 01`
  through `Rule 35`), each requiring an executable authoritative Laravel
  service/model anchor or compatibility status constant;
- 32 independent SCR tests for the UID/DGM registry (`SCR-210` through
  `SCR-263`, including reserved `SCR-243`);
- authenticated AEMS download/export route coverage;
- migration-chain manifest coverage for the G3 through G8 migrations.

The 32 SCR count excludes the AEMS dashboard identifier and the two sidebar
entries that are legacy/contextual records without an SCR id. Process Flow and
Risk Matrix remain artifacts inside `SCR-221`/the Planning Package; they are
not additional sidebar screens.

## Frontend verification

`tests/e2e/aems-g9-conformance.spec.js` adds:

- a static duplicate-route check proving every operational AEMS sidebar route
  has exactly one explicit `<Route>` and is present in `implementedCorePaths`,
  while the generic `ModulePage` fallback remains excluded;
- a 6-role menu and contextual-tab matrix for `platform_admin`, `agis_admin`,
  `cias_management`, `agis_user`, `auditee_representative`, and `read_only`;
- browser-level mutation payload checks for optimistic-lock transitions and a
  negative Evidence assessment;
- protected-download and negative-evidence suite presence checks;
- desktop/mobile project and responsive-shell coverage checks.

The fixture at
`tests/e2e/fixtures/aems-role-navigation-matrix.js` is permission-driven and
mirrors the stable permission codes seeded by `RolePermissionSeeder`. It does
not add role-name branches to the application.

## Migration rehearsal

Run `scripts/verify-aems-g9.ps1` from the repository root. By default it uses
an ephemeral SQLite schema, performs a fresh seeded migration rehearsal, the G9
PHP contracts, lint/build, and the G9 desktop/mobile Playwright projects. Use
`-SkipFrontend` or `-SkipPlaywright` when isolating a layer. Pass
`-UseConfiguredDatabase` only when an explicitly disposable PostgreSQL database
has been provisioned.

The rehearsal is intentionally explicit and local; it does not stage, commit,
reset, clean, or push files.

## As-built truth rules

1. AEMS is the compatibility identifier used by the current application;
   reference documents may call the module AEM (Audit Engagement Management).
2. `Audit Reporting Workspace` is the one reporting sidebar destination.
   Interim, Draft, Final, and Distribution are contextual stages under that
   workspace, not four duplicate sidebar modules.
3. Child-record workflows are implemented. Aggregate lifecycle transitions,
   formal closure, records controls, and calendar controls are represented by
   the current G8 services and routes; the strict planning gate remains the
   authoritative fieldwork gate.
4. Core Document Versions remain the source of file checksum, MIME type, size,
   version, custody, confidentiality, and protected download data.
5. IAP remains a read-only source for approved engagement lineage. ARMIS is the
   sole operational resource provider and must satisfy current provider-health
   and reconciliation checks; historical fallback/shadow values remain visible
   only as lineage evidence. AIS is outside AEMS workflow ownership but may
   consume AEMS through its read-only integration contract.
6. Historical checkpoint statements in
   `AEMS_IMPLEMENTATION_BASELINE.md` describe the state at their checkpoint.
   Later G4-G8 sections supersede earlier “not started” statements; they are
   not current missing-feature claims.

## Current verification result

The focused G9 backend contract passes **69 tests and 123 assertions**. The
G10E acceptance contract adds the runtime semantic Rule matrix and final
status/route assertions. Exact full-suite, migration, lint/build, and
desktop/mobile Playwright results are recorded in
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md` for each release; a stale fixture must be
updated to satisfy the strict planning readiness gate rather than weakening
that gate.

### Merged source detail: AEMS_G10A_BACKEND_CONFORMANCE

# AEMS-G10A — Backend conformance closure (bounded pass)

Status: implemented as an additive backend pass; frontend queue work remains a
separate follow-up.

## Controls delivered

### Fieldwork record taxonomy

`AemsFieldworkRecord::TYPES` is now the single source used by the request and
workspace contract. In addition to the existing Interview, Observation,
Walkthrough, Inspection, Testing, Sampling, and Analysis types, the API accepts
Inquiry, Meeting, Field Note, and Other. Existing values and persisted records
remain compatible.

### Finding-to-procedure criteria traceability

The `audit_finding_procedure` pivot records the approved audit procedure(s)
supporting a finding, the procedure's criteria reference, a traceability note,
the linking actor, and timestamps. Finding revisions copy these links into the
new immutable revision. Communication and finalization snapshots include the
procedure IDs alongside the existing working-paper, evidence, and fieldwork
version IDs.

The API accepts optional `procedureIds`. For compatibility, links are also
derived from cited Working Paper Versions and Fieldwork Record Versions. Every
derived or supplied procedure must belong to the same engagement. Finding
submission, validation, and finalization require at least one procedure link;
the existing approved-paper, finalized-fieldwork, and evidence eligibility
gates continue to apply.

The finding detail contract now exposes `procedures` with procedure code,
objective, audit criteria, criteria reference, traceability note, and linking
actor.

## Focused verification

The Issue/Finding feature test suite covers:

- successful automatic procedure traceability from an approved working paper;
- persistence of the normalized finding/procedure link;
- rejection of an unscoped procedure ID;
- the expanded fieldwork taxonomy.

The full `AemsIssueFindingRecommendationTest` suite remains green after this
pass. This phase does not change Issue, Evidence, Report, Closure, CMS, AIS, or
ARMIS workflow transitions.

## Remaining conformance work

This is not a declaration that every MDS/UID requirement is complete. The
remaining reference gaps are tracked in the as-built baseline and require
separate phases, including dedicated operational queues, records/archive
operations, and any governance decisions still marked open in the reference
documents.

### Merged source detail: AEMS_G10B_FRONTEND_CONFORMANCE

# AEMS-G10B — Frontend conformance

Status: implemented.

The Execution Workspace consumes the backend `recordTypes` contract, so Inquiry,
Meeting, Field Note, and Other are available in the create/edit record form
alongside the existing fieldwork types. The selected procedure panel displays
its audit criteria when the planning contract supplies it.

The Findings workspace now consumes `procedures` from the findings workspace
contract. Draft and edit forms can select one or more approved-program
procedures, while procedure links inferred by the backend remain visible after
save. Finding detail displays each procedure's objective, criteria reference,
and traceability note and explains when the professional gate is not met.

The SCR-212 Scope workspace detects stored Focus links that do not belong to
their selected Area, presents an explicit integrity warning, and disables save
until the invalid relationship is corrected. Backend validation remains
authoritative.

Focused Playwright coverage covers the extended taxonomy and procedure/criteria
detail on desktop and mobile projects. Existing Execution and Issues/Findings
regressions continue to run unchanged.

### Merged source detail: AEMS_G10C_OPERATIONAL_QUEUES_OUTPUTS

# AEMS-G10C Operational Queues and Output Surfaces

## Scope

G10C exposes the existing AEMS-7A/7B and G8 calendar contracts as dedicated,
responsive workspaces. It does not duplicate workflow state or bypass the
backend authorization rules.

## Frontend workspaces

| Workspace | Route | Backend contract |
| --- | --- | --- |
| Operational Work Queues | `/audit-engagement-management/work-queues` | `/api/aems/engagements/{engagement}/work-queue` |
| Audit Calendar and Milestones | `/audit-engagement-management/calendar` | `/api/aems/engagements/{engagement}/calendar` |
| Registers and Protected Exports | `/audit-engagement-management/registers` | dashboard, document-index, records, and report endpoints |

Operational Work Queues has dedicated tabs for Tasks, Review Notes,
Due Process, and Escalation Candidates. Each tab uses the selected engagement
scope and displays status, due/overdue state, linked records, actors, and
version/lock information returned by the API.

## Controls

- Task assignment is limited to active engagement participants or authorized
  global users. Office and engagement scope are checked by the service.
- Task transitions require the current optimistic lock version. Terminal tasks
  cannot be edited; reopening is an explicit audited action.
- Review notes remain draft-editable only by their author. Finalization requires
  an independent actor and creates an immutable audit/event record.
- Due-process entries remain append-only. Follow-up reminders and clarification
  requests create new exchanges linked to the original finding.
- Escalation candidates are reviewable prompts. Acknowledgement, resolution,
  and dismissal require role permission, engagement scope, a comment, and the
  current lock version. No notice is issued automatically.
- Calendar milestones use the Core activity/audit infrastructure and current
  lock version for mutations. Overdue indicators are derived from authoritative
  dates.
- CSV/PDF/document-index downloads are authenticated protected endpoints. No
  public export URL is introduced.

## Permissions

The existing seeded permissions remain authoritative:

`aems.task.view`, `aems.task.create`, `aems.task.update`,
`aems.task.complete`, `aems.task.cancel`, `aems.task.reopen`,
`aems.task.escalate`, `aems.review-note.view`, `aems.review-note.create`,
`aems.review-note.update`, `aems.review-note.finalize`,
`aems.due-process.view`, `aems.due-process.create`,
`aems.escalation-candidate.view`, `aems.escalation-candidate.review`,
`aems.escalation-candidate.resolve`, `aems.escalation-candidate.dismiss`,
`aems.calendar.view`, `aems.calendar.manage`, and the existing report/export
permissions.

The React routes use the least broad page gate (`aems.task.view`,
`aems.calendar.view`, or `aems.engagement.view`) and hide mutations when the
individual action permission is absent. Laravel remains the final authority.

## Output surfaces

The Registers and Protected Exports page links the engagement registry,
operational queues, calendar, report workspace, progress CSV, work-queue CSV,
and document-index CSV. Report PDFs/CSVs continue to be downloaded through the
existing protected AEMS report endpoints.

## Verification contract

The G10C gate is met when the dedicated workspaces are reachable for each
authorized role, empty/loading/error states are usable on desktop and mobile,
mutations refresh from the authoritative API, and existing queue/calendar
feature tests plus frontend lint/build and route checks remain green.

### Merged source detail: AEMS_G10D_RECORDS_ADMINISTRATIVE_CLOSURE

# AEMS-G10D Records and Administrative Closure Conformance

G10D adds the dedicated `Records and Administrative Closure` route:

`/audit-engagement-management/records-closure`

The workspace is engagement-scoped and provides three controlled views:

- **Closure Readiness** — authoritative checklist, completion/transfer
  reconciliation, closure blockers, formal Closure actions, and reopening
  history where applicable;
- **Retention Monitoring** — approved retention classification, trigger,
  period, custodian, storage location, legal-hold state, and immutable approval;
- **Records & Disposition** — searchable final document index, archive status,
  legal-hold release, destruction-eligibility review, disposition recording, and
  closure blockers.

## Professional controls

- `COMPLETED` means substantive audit work has finished. It is not formal
  administrative closure.
- `CLOSED` is created only by the formal Closure workflow after the backend
  re-evaluates authoritative blockers. Closed child records and the final
  document index are immutable.
- Approved retention metadata is immutable. Archive is allowed only after
  formal closure and approved retention metadata.
- Active legal holds block archive and disposition. Legal-hold release records
  an actor, reason, reference, and audit event.
- Destruction eligibility is a review result, not physical deletion. A
  disposition record requires an eligible review, no legal hold, and the
  authorized disposition permission.
- Closure blockers are calculated from authoritative records; the UI cannot
  override them with manual checkboxes.

## Permission contract

The route is visible when the user has one of `aems.closure.view`,
`aems.retention.view`, or `aems.records.view`. Individual tabs and mutations
remain permission-aware. Laravel services enforce engagement scope, office
scope, separation of duties, optimistic locking, audit logs, and activity
events regardless of frontend visibility.

The existing protected APIs remain the source of truth:

- `/api/aems/engagements/{engagement}/closure`;
- `/api/aems/engagements/{engagement}/records`;
- `/api/aems/engagements/{engagement}/retention/{retention}/archive`;
- `/api/aems/engagements/{engagement}/retention/{retention}/legal-hold-release`;
- `/api/aems/engagements/{engagement}/retention/{retention}/destruction-review`;
- `/api/aems/engagements/{engagement}/retention/{retention}/disposition`.

No migration or physical-record deletion is introduced in G10D.

### Merged source detail: AEMS_G10E_FINAL_ACCEPTANCE

# AEMS-G10E Governance and Final Acceptance

Status: **accepted as the current AEMS as-built contract** on 13 August 2026.

G10E closes the governance and verification gate. It does not rename legacy
`AEMS`/`aems.*`/`aem.*` identifiers, remove either IAP risk system, or begin AIS
integration.

## Governance decisions

The authoritative decision register is
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md`, sections G0-01 through
G0-14. It resolves:

- authority and signatory separation;
- direct AFR authorization;
- Evidence Request and Evidence status semantics;
- the evidence assessment scale and final-finding eligibility;
- management-response extensions and no-response due process;
- retention periods and legal-hold precedence;
- Risk Matrix and Audit Program per-Area policy;
- conference waiver authority;
- ARMIS effort and provider authority boundaries;
- authenticated signatures, transmittals, and acknowledgements;
- report distribution authority;
- `COMPLETED` versus `CLOSED`; and
- acceptance ownership and change control.

The status map is semantic and additive. Stored compatibility values remain
readable; `SUBMITTED` is the Evidence Request alias for `FOR_REVIEW`,
`RETURNED_FOR_REVISION` is the report alias for `RETURNED`, and technical
Evidence `LOCKED` never means professional acceptance. The current runtime
codes are asserted by `AemsG10EAcceptanceTest`.

## Verification contract

Acceptance coverage is **35/35 semantic rules**, **32/32 canonical SCR
identifiers**, and **6/6 seeded role navigation matrices**.

### Backend

`backend/tests/Feature/Api/AemsG10EAcceptanceTest.php` executes 35 semantic
Rule rows. Each row resolves a runtime service/model anchor or a runtime status
constant, rather than only checking that a documentation marker exists. The
test also verifies the status map, authenticated AEMS API routes, and canonical
SCR identifiers.

### Frontend

The G9 and G10E Playwright contracts verify:

- 32 unique SCR identifiers, including reserved `SCR-243`;
- every explicit operational AEMS route exactly once and its exclusion from
  generic fallback generation;
- six seeded role navigation/contextual-tab matrices;
- protected downloads and mutation payloads;
- Records and Administrative Closure on desktop and mobile; and
- desktop/mobile responsive project configuration and empty/error states.

The canonical reporting destination remains one **Audit Reporting Workspace**;
Interim, Draft, Final, and Distribution are contextual stages under it.

## Acceptance commands and result

The final gate runs:

```text
npm.cmd run lint
npm.cmd run build
php artisan test --testsuite=Feature
php artisan migrate:fresh --seed
php artisan test
git diff --check
npx.cmd playwright test --project=desktop-chrome --project=mobile-chrome
```

Focused G10E acceptance is also repeatable with:

```text
powershell.exe -ExecutionPolicy Bypass -File .\scripts\verify-aems-g9.ps1
```

The exact command output is recorded in the release handoff. A failed legacy
fixture is corrected at the test-fixture layer when the stricter planning
conformance gate is the intended behavior; professional workflow behavior is
not weakened to satisfy stale counts or fixtures.

The verification completed during this acceptance pass produced these
results:

- `npm.cmd run lint`: passed.
- `npm.cmd run build`: passed (Vite transformed 2,531 modules).
- `php artisan test --testsuite=Feature`: **370 tests, 4,377 assertions
  passed** (the run was subsequently stopped before a duplicate full backend
  pass was started).
- `AemsG10EAcceptanceTest`: **37 tests, 273 assertions passed**.
- Fresh SQLite migration/seed rehearsal with the G9 and G10E suites:
  **106 tests, 396 assertions passed**.
- Focused G10E desktop/mobile Playwright contract:
  **6 tests passed**.
- `git diff --check`: passed (Git emitted only normal LF/CRLF conversion
  warnings).

The broad 158-test desktop/mobile Playwright run was stopped at the user's
request after reaching its 20-minute command limit. It reported unrelated
legacy browser-fixture/authentication and selector failures before stopping;
therefore this record makes no claim that the complete browser matrix is
green. The focused G9/G10E acceptance contracts remain the verified browser
gate for this change, and the remaining browser workflows are available for
manual testing.

## Explicit boundaries

- IAP supplies approved lineage and remains read-only from AEMS.
- ARMIS is the sole operational resource provider in `ARMIS_AUTHORITATIVE`
  mode. Historical fallback/shadow and reconciliation records are retained for
  lineage only; no provider switch or fallback read is available.
- CMS receives only finalized/issued recommendation snapshots.
- Core Document Versions remain authoritative for checksums, MIME type, size,
  custody, confidentiality, immutable versions, and protected downloads.
- AIS remains outside AEMS completion scope.

This document is the final AEMS verification index. Future changes require a
new versioned governance decision, updated status compatibility documentation,
semantic tests, and a new acceptance record.
+
### Complete merged source: AEMS_G0_GOVERNANCE_CONFORMANCE_CONTRACT

# AEMS-G0 Governance and Conformance Contract

## 1. Purpose, authority, and scope

This document resolves the open professional and interoperability decisions
identified in **MDS-200 AEM v0.8**, **UID-200 v0.5**, and **DGM-200 v0.2**. It is
the AGIS implementation and acceptance contract for AEMS. Runtime behavior is
still determined by the source code and tests; compatibility exceptions are
listed explicitly in the status map and rule matrix rather than left implicit.

The reference documents are draft/review artefacts. This contract is the
project-level decision record that prevents a later implementation phase from
inventing a different authority, status, retention, evidence, or reporting
policy. A decision may be changed only through a new, versioned governance
decision and a documented compatibility plan.

The source code and automated tests remain the as-built authority. Therefore,
each decision below identifies both the target rule and the compatibility
behavior that remains in the current release. AEMS compatibility identifiers
(`AEMS`, `aems.*`, and `aem.*`) are retained.

**Effective date:** 13 August 2026
**Contract version:** G0.1
**Owner:** CIAS Management / AGIS Product Governance
**Implementation scope:** governance, conformance, and test contract only;
no schema, route, permission, or operational workflow change is included.

## 2. Resolved decision register

### G0-01 — Authority and signatory matrix

The following matrix is the minimum authority separation for professional
decisions. Normally “Preparer” may draft and revise only; the active CIAS Head
has a narrowly documented exception to record AEO review, approval, and
issuance of her own prepared version when she is the sole active CIAS
Management authority. “Reviewer” performs the independent assessment. “Approver” records
the final professional decision. “Issuer/signatory” authorizes release to an
external recipient.

| Record or action | Preparer | Independent reviewer | Approver | Issuer/signatory | Required separation |
| --- | --- | --- | --- | --- | --- |
| Engagement source and AEO | Team Leader or Engagement Supervisor | Assigned Reviewer (or sole active CIAS Head self-review exception) | Active CIAS Management authority; sole active CIAS Head may approve her own AEO | Active CIAS Management authority; sole active CIAS Head may issue her own approved AEO | Separate approver/issuer accounts are the normal rule; the documented sole-head exception extends to the corresponding aggregate and later AEMS review/approval actions only when no alternate active CIAS Management authority exists. |
| AEP, Planning Package, and Audit Program | Team Leader or assigned auditor | Reviewer | CIAS Management authorized audit authority | Not externally issued; approved baseline is the authority | Preparer cannot approve the same version. |
| Team safeguards and ARMIS authority | Engagement Supervisor | Independent safeguard reviewer | Designated CIAS/ARMIS resource authority | Not applicable | The person declaring a conflict cannot approve its mitigation. |
| Working Paper | Assigned Auditor | Reviewer | Reviewer with approval permission | Not externally issued | Preparer cannot review or approve the same version. |
| Issue and Finding validation | Assigned Auditor/Finding author | Independent Finding Reviewer | CIAS Management validation authority | Not applicable until report communication | Finding author cannot validate the finding. |
| AFR communication and finalization | Finding team | Independent AFR Reviewer | CIAS Management AFR authority | Designated CIAS Management signatory | AFR issuance is independent from preparation. |
| Management Response | Auditee Representative | Assigned Auditor/Reviewer | Auditee office authority for its response | Not applicable | The auditor cannot author the auditee response. |
| Auditor Rejoinder and dialogue finalization | Assigned Auditor/Reviewer | Engagement Supervisor or independent reviewer | CIAS Management dialogue authority | Not applicable | Auditee author cannot approve the rejoinder. |
| Interim/Final Report | Report preparer | Quality Reviewer | CIAS Management report authority | CIAS Management designated signatory | Preparer and reviewer cannot approve; issuer cannot be the preparer. |
| Report distribution | Distribution officer | Quality/records reviewer | CIAS Management distribution authority | Same designated signatory as the approved report, or a separately delegated signatory | Recipient, method, delivery, and acknowledgement are recorded per version. |
| Completion and formal closure | Completion assessor | Independent closure reviewer | CIAS Management closure authority | Not externally issued | Completion assessor cannot approve closure. |
| Controlled reopening | Reopening requester | Independent reviewer | CIAS Management reopening authority | Not applicable | Original closure decision is never replaced. |
| CMS transfer | AEMS transfer officer/service | Transfer reconciliation reviewer for exceptions | No new professional decision; transfer is permitted only from an issued report | Not applicable | Transfer is idempotent and cannot create or alter a recommendation decision. |

An authenticated user identity, authority basis, decision date/time, reason,
and exact version/document hash are recorded for every approval, issuance,
waiver, distribution, closure, and reopening decision. A permission grants the
ability to perform an action; it does not by itself grant professional
authority.

### G0-02 — Direct AFR policy

The normal path is **Issue → Finding → AFR**. A Finding may be created without
an Issue only when one of these controlled reasons is selected:

1. `URGENT_OR_MATERIAL_RISK` — delay in creating an Issue would expose a
   material or urgent risk;
2. `FORMAL_DIRECTIVE_OR_LEGAL_REQUIREMENT` — a law, regulator, CIAS directive,
   or formally approved request requires a direct AFR; or
3. `CROSS_CUTTING_OR_SYSTEMIC_MATTER` — the matter spans issues or offices and
   a single authoritative finding is more traceable than duplicate Issues.

Direct AFR creation requires an immutable authorization record containing the
reason, factual basis, directing authority, date, affected engagement/area,
and independent reviewer. The finding must still contain all required
criteria, condition, cause, conclusion, effect/significance, risk, evidence,
and recommendation fields. “No Issue was convenient” is not an authorized
reason. A direct finding cannot be self-authored and validated.

### G0-03 — Evidence Request statuses and lifecycle

The canonical future lifecycle is:

```text
DRAFT → FOR_REVIEW → SENT → ACKNOWLEDGED →
PARTIALLY_RECEIVED → RECEIVED → ASSESSED → CLOSED
```

The following exception states are controlled, not automatic professional
decisions:

- `OVERDUE` is a due-date condition recorded when a complete receipt is not
  recorded by the due date;
- `EXTENDED` is an approved target-date extension and preserves the original
  due date;
- `ESCALATED` is a reviewable escalation candidate or approved escalation
  record, never an automatic notice;
- `CANCELLED` requires an authorized reason before complete receipt; and
- `CLOSED_WITHOUT_SUBMISSION` requires a documented authority decision and
  records why no submission will be expected.

The current `SUBMITTED` value is the compatibility representation of
`FOR_REVIEW`. Existing `PARTIALLY_RECEIVED`, `RECEIVED`, `ASSESSED`, and
`CLOSED` values retain their meaning. A request is not `ASSESSED` merely because
files were received; every received exact document version must have an
eligible assessment or an approved exception.

### G0-04 — Audit Evidence statuses

Professional evidence status is separate from Core document storage and
checksum state. The canonical evidence status set is:

```text
REGISTERED → FOR_ASSESSMENT → ACCEPTED
                         ├→ LIMITED
                         ├→ ADDITIONAL_REQUIRED
                         ├→ REJECTED
                         ├→ DUPLICATE
                         └→ SUPERSEDED
VOIDED (controlled terminal state)
```

The current `DRAFT` maps to `REGISTERED`, and `VERIFIED` maps to
`FOR_ASSESSMENT` because technical file verification is not professional
acceptance. Current `LOCKED` means the record/file is immutable; it must not be
interpreted as `ACCEPTED` without a professional assessment. Current `VOIDED`
remains `VOIDED`. Core `document_versions` continues to own checksum, file
size, MIME type, version, custody, confidentiality, and protected download
controls.

### G0-05 — Evidence assessment scale and eligibility

Each applicable assessment dimension uses exactly one of:

| Value | Meaning | Final-finding eligibility |
| --- | --- | --- |
| `NOT_ASSESSED` | No professional conclusion has been recorded | Always blocks |
| `INADEQUATE` | The dimension does not meet the evidence requirement | Blocks |
| `PARTIAL` | The dimension is only partly met | Blocks unless a separate authorized exception explicitly accepts the limitation and compensating corroboration |
| `ADEQUATE` | The dimension is acceptable for the stated purpose | May pass that dimension |
| `NOT_APPLICABLE` | The dimension genuinely does not apply | Requires written assessor rationale and independent review; prohibited for a mandatory dimension |

The scale applies to sufficiency, appropriateness, relevance, reliability,
competence, accuracy, completeness, corroboration, contradiction,
authenticity, integrity, and confidentiality. For contradiction,
`ADEQUATE` means no unresolved contradiction remains. For confidentiality,
`ADEQUATE` means the classification and access controls are sufficient.

An evidence version is eligible for a finalized finding only when it links to
the exact Core `document_versions` row, is current, has an assessment in the
`ASSESSED` family state, has no unresolved gaps/limitations/contradictions,
has all mandatory dimensions `ADEQUATE`, and satisfies restriction/exception
rules. `NOT_ASSESSED`, `INADEQUATE`, or `PARTIAL` evidence cannot silently pass
the gate.

Legacy rating values are read compatibly as `YES → ADEQUATE`, `NO →
INADEQUATE`, `PARTIAL → PARTIAL`, and `NOT_ASSESSED → NOT_ASSESSED` until the
assessment schema is migrated.

### G0-06 — Management-response extension and no-response policy

The communication snapshot sets the original management-response due date;
that date is never overwritten. An auditee office may request:

- one ordinary extension of up to **15 calendar days**; and
- one exceptional extension of up to **15 additional calendar days** for a
  documented force-majeure, legal, or materially complex circumstance.

Requests should be submitted before the due date. A late request is allowed
only with a recorded reason and CIAS Management approval. The auditor may
recommend an extension but cannot approve the auditee's extension request.
Each approved/rejected extension is an immutable version with requester,
assessor, authority, reason, old date, new date, and decision timestamp.

Reminder and due-process timing is: reminder three business days before the
date, due notice on the date, first non-response notice after five business
days, and final non-response decision after ten business days. A no-response
decision does not fabricate agreement, close the finding, or bypass the
existing dialogue/finalization workflow.

### G0-07 — Retention and records periods

The default AGIS retention schedule is measured from the formal engagement
`CLOSED` decision (or from the final disposition for cancelled engagements):

| Record class | Minimum retention |
| --- | --- |
| Engagement identity, AEO/AEP, Planning Package, Audit Program, Working Papers, Evidence metadata/files, Issues, Findings, Responses, Conferences, Reports, CMS transfer manifests, completion and reopening records | 10 years |
| Issued Final Reports, formal Closure Decisions, Reopening Decisions, legal-hold records, and immutable audit/Activity Trail needed to prove those decisions | Permanent |
| Superseded/returned drafts and report versions | 10 years, unless placed on legal hold |
| Reminder, notification, and transient queue records not part of the decision trail | 3 years |

Legal hold, litigation, statutory schedules, or a formally approved records
schedule always overrides the default and may extend retention. Retention does
not authorize deletion: archive, destruction eligibility, approval, execution,
and the immutable disposition event are separate records-management actions.

### G0-08 — Risk Matrix and Audit Program per Area

The default planning unit is the **Audit Area**:

- each approved Planning Package version has one or more Risk Matrices as
  needed, with at least one matrix for every in-scope Audit Area;
- a matrix belongs to one Audit Area and may cover multiple Audit Focuses;
- a Risk Matrix Item has one primary Audit Area and at least one Audit Focus;
  cross-area risks are represented by linked items or an explicit cross-area
  relationship, never by an unowned item;
- each Audit Area has one approved Audit Program baseline per Planning Package
  version; revisions create a new immutable program version;
- each procedure has one primary Audit Area and Audit Focus, with explicit
  links for any additional focus; and
- a shared procedure is allowed only when every covered Area/Focus is linked,
  the expected evidence and criteria are explicit, and area-specific
  ownership is preserved.

Process Flow documents follow the same Area/Focus scope and are revisioned.
Imported IAP risks remain source lineage; AEMS Risk Matrices and Programs are
engagement-specific planning artifacts and cannot mutate IAP records.

### G0-09 — Conference waiver authority

An Entry Conference waiver must be approved by the CIAS Management authorized
audit authority before fieldwork begins. An Exit Conference waiver must be
approved by the CIAS Management closure/report authority before final report
approval. A Team Leader, Reviewer, or Auditee Representative cannot approve
their own waiver. Each waiver records reason, authority, date, affected scope
or findings, compensating communication, and expiry/validity. Emergency or
special engagements use the same rule unless a separate written special
authority explicitly names the waiver authority.

### G0-10 — Effort boundary and ARMIS authority

AEMS records engagement planned and actual person-days; ARMIS is authoritative
in the sole `ARMIS_AUTHORITATIVE` mode. `IAP_INTERIM_FALLBACK` and
`ARMIS_SHADOW` are historical compatibility values only. Planned effort covers approved engagement work and
procedures. Actual effort covers recorded engagement work, fieldwork, review,
and report/closure effort; leave, training, and unrelated administration are
excluded unless explicitly configured as a separate category. Completion
requires reconciliation of AEMS and the active provider. Missing or stale
provider data blocks approval; it never causes an automatic provider switch.

### G0-11 — Signature, transmittal, and acknowledgement method

The authoritative signature is an authenticated AGIS electronic approval. The
system stores signer, role, authority basis, UTC timestamp, exact record
version, and SHA-256 of the signed Core Document Version. A wet signature or
external signature file may be attached as supporting evidence but does not
replace the AGIS decision record.

The default transmittal is a protected, authenticated AGIS portal download.
Email may notify a recipient but may not be the authoritative delivery of a
confidential report or evidence file. Every transmittal receives a unique
identifier and immutable recipient snapshot, method, delivery timestamp,
acknowledgement/rejection decision, actor, and exact version/hash. Public file
URLs are prohibited.

### G0-12 — Report distribution authority

The final report quality reviewer records a recommendation. The CIAS
Management authorized distribution authority records the final decision. The
signatory matrix must identify the IAU Head recommendation (where applicable),
the LCE/Presiding Officer or delegated CIAS authority decision, and the
authorized signatory. A report cannot be marked issued until the authority,
recipient list, confidentiality classification, transmittal method, and exact
PDF checksum are recorded.

### G0-13 — Completed versus Closed

`COMPLETED` means the audit work is substantively finished: approved objectives
and procedures are executed or formally waived, Working Papers and evidence
are finalized, Issues/Findings/AFRs and responses are dispositioned, reports
and required CMS transfer reconciliation are complete, person-days and
retention/index records are reconciled, and lessons learned are recorded.
The engagement may still be awaiting the formal closure decision.

`CLOSED` means CIAS Management has approved the formal Closure Decision after
the completion assessment. The aggregate and ordinary child records are
locked, retention/records controls are active, and any reopening requires a
new immutable Reopening Decision that preserves the original closure.

The current release stores `COMPLETED` as a distinct aggregate status and only
the formal Closure workflow may move an engagement to `CLOSED`. No client may
treat `CLOSED` as mere management-reported completion.

### G0-14 — Acceptance authority and change control

The G10E acceptance gate is owned by CIAS Management and the AGIS product
owner. A release is accepted only when the semantic Rule 1–35 suite, SCR and
role/navigation suite, migration rehearsal, full Feature suite, lint/build,
protected-download checks, and desktop/mobile Playwright projects have exact
recorded results. A compatibility alias, legacy status, or deferred AIS
integration is not an unresolved governance decision. Any change to this
contract requires a versioned decision, an updated compatibility map, and
regression evidence before deployment.

## 3. Status compatibility map

G0 defines semantic compatibility; it does not rename existing database
values. Later migrations may add canonical values only with a backfill,
read/write compatibility, and regression tests.

| Contract concept | Canonical G0 meaning | Current as-built value(s) | Compatibility rule |
| --- | --- | --- | --- |
| Engagement review | `FOR_REVIEW` | `RETURNED_FOR_REVISION` and stage-specific review | Keep aggregate status; expose review stage/action separately. |
| Engagement completed | `COMPLETED` | `COMPLETED` | Completion assessment and aggregate transition establish substantive completion; formal Closure is still required for `CLOSED`. |
| AEO/AEP review | `FOR_REVIEW`, `RETURNED`, `APPROVED`, `ISSUED`, `SUPERSEDED` | `PENDING_REVIEW`, `RETURNED_FOR_REVISION`, `APPROVED`, `ISSUED`, `SUPERSEDED` | Map labels only; preserve stored codes. |
| Audit Program | `DRAFT`, `FOR_REVIEW`, `APPROVED`, `ACTIVE`, `COMPLETED`, `SUPERSEDED` | Same except `PENDING_REVIEW` represents `FOR_REVIEW` | Preserve current code. |
| Working Paper | `DRAFT`, `SUBMITTED`, `RETURNED`, `RESUBMITTED`, `APPROVED`, `SUPERSEDED`, `VOIDED` | Same, with `RETURNED_FOR_REVISION` | UI label may say Returned; code remains compatible. |
| Evidence Request | `DRAFT`, `FOR_REVIEW`, `SENT`, `ACKNOWLEDGED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `OVERDUE`, `EXTENDED`, `ESCALATED`, `ASSESSED`, `CLOSED`, `CANCELLED`, `CLOSED_WITHOUT_SUBMISSION` | `DRAFT`, `SUBMITTED`, `SENT`, `ACKNOWLEDGED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `FOR_REVIEW`, `ASSESSED`, `OVERDUE`, `EXTENSION_REQUESTED`, `EXTENDED`, `ESCALATED`, `CANCELLED`, `CLOSED_WITHOUT_SUBMISSION`, `CLOSED` | `SUBMITTED` remains the read-compatible alias for `FOR_REVIEW`; `EXTENSION_REQUESTED` is the explicit pending-extension state. |
| Audit Evidence | `REGISTERED`, `FOR_ASSESSMENT`, `ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`, `SUPERSEDED`, `DUPLICATE`, `VOIDED` | Technical `DRAFT`, `VERIFIED`, `LOCKED`, `VOIDED` plus professional `outcome` values | `LOCKED` is technical immutability; only `outcome=ACCEPTED` satisfies the final-finding gate. |
| Issue | `DRAFT`, `FOR_REVIEW`, `OPEN`, `UNDER_EVALUATION`, `DISPOSED`, `WITHDRAWN` | `DRAFT`, `SUBMITTED`, `VALIDATED`, `DISMISSED`, `CONVERTED_TO_FINDING` plus dispositions | Preserve disposition history; add projections only through a controlled migration. |
| Finding/AFR | `DRAFT`, `PENDING_REVIEW`, `VALIDATED`, `COMMUNICATED`, `AWAITING_MANAGEMENT_RESPONSE`, `UNDER_DIALOGUE`, `FINALIZED`, `WITHDRAWN`, `SUPERSEDED` | Same | `FINALIZED` remains the only CMS/reporting source state. |
| Management response | `DRAFT`, `SUBMITTED`, `UNDER_AUDITOR_REVIEW`, `CLARIFICATION_REQUESTED`, `RESUBMITTED`, `DIALOGUE_FINALIZED` | Same | Extensions are separate immutable decisions, not status overwrites. |
| Report | `DRAFT`, `PENDING_REVIEW`, `RETURNED`, `RESUBMITTED`, `APPROVED`, `ISSUED`, `SUPERSEDED`, `WITHDRAWN`, `ADMINISTRATIVELY_CLOSED` | `DRAFT`, `PENDING_REVIEW`, `RETURNED_FOR_REVISION`, `RESUBMITTED`, `APPROVED`, `ISSUED`, `SUPERSEDED`, `WITHDRAWN`, `ADMINISTRATIVELY_CLOSED` | `RETURNED_FOR_REVISION` is the current `RETURNED` code; administrative closure is a report-family state and never mutates an issued version. |
| Conference | `DRAFT/SCHEDULED`, `HELD`, `ACKNOWLEDGED`, `COMPLETED`, `WAIVED`, `CANCELLED` | Entry and Exit conference-specific status sets | Conference-specific codes remain; waiver authority follows G0-09. |

## 4. Rule-to-code-to-test conformance matrix

The matrix is the acceptance index for later phases. “Partial” means the
current source protects part of the rule but does not yet implement the G0
decision. “Gap” means the required field, state, authority, or test must be
added. Test paths are existing tests unless marked **required**.

| MDS rule | Current code anchor | Existing protection / required test | G0 status |
| --- | --- | --- | --- |
| 1. Exactly one Office | `AemsEngagementRegistryService`, `AuditEngagement`, office pivot migration | `AemsEngagementRegistryTest.php`, `AemsFoundationContractTest.php`, `AemsFoundationG2Test.php`, G10E semantic row | Implemented |
| 2. Area belongs to Office | `AemsAccessService`, engagement area relations | `AemsAccessControlTest.php`, G10E semantic row | Implemented |
| 3. Focus belongs to Area | engagement focus relations and access scopes | `AemsFoundationG2Test::test_scope_rejects_focus_outside_the_selected_area`, G10E semantic row | Implemented |
| 4. Approved IAP or authorized unplanned source | `AemsEngagementRegistryService`, source validation | `AemsCrossModuleIntegrationTest.php`, `AemsEngagementRegistryTest.php` | Implemented |
| 5. Source linked and not overwritten | IAP gateway and `source_snapshot` | `AemsCrossModuleIntegrationTest.php` | Implemented |
| 6. Objectives/coverage/boundaries/limitations/variance | `AuditEngagement`, SCR-212 structured scope service | `AemsFoundationG2Test`, G10E semantic row | Implemented |
| 7. Required team roles before AEO | team service and AEO guards | `AemsTeamAeoTest.php`, `AemsEngagementLifecycleTest.php` | Implemented |
| 8. Competency/capacity/conflict/safeguards | team safeguard and ARMIS provider services | `AemsTeamSafeguardTest.php`, `AemsIntegrationBoundaryTest.php`, G10E semantic row | Implemented |
| 9. Team validation is not fieldwork authority | aggregate transition service | `AemsEngagementLifecycleTest.php` | Implemented |
| 10. Team changes require authority/reason/date/consequence/history | team update/history models | `AemsTeamSafeguardTest.php`, `AemsG4AuthorityTest.php`, G10E semantic row | Implemented |
| 11. Fieldwork authority/planning/Entry or waiver/emergency | `AemsEngagementTransitionService` | `AemsFieldworkRecordTest.php`, `AemsEngagementLifecycleTest.php`, G10E semantic row | Implemented |
| 12. Exit before final unless waived | report/closure transition guards | `AemsReportTest.php`, `AemsCompletionClosureTest.php`, G10E semantic row | Implemented |
| 13. Material work/conclusions in WPs | `WorkingPaper`, fieldwork/WP links | `AemsWorkingPaperEvidenceTest.php` | Implemented |
| 14. Completed procedure has conclusion/WP/disposition | `AemsFieldworkService`, `AuditProgramProcedure` | `AemsFieldworkRecordTest.php`, `AemsAepProgramTest.php` | Implemented |
| 15. Requests and evidence are distinct | `AemsEvidenceRequest`, `AuditEvidence`, link model | `AemsEvidenceRequestTest.php`, `AemsWorkingPaperEvidenceTest.php` | Implemented |
| 16. Receipt is not acceptance | `AemsEvidenceRequestService` | `AemsEvidenceRequestTest.php`, `AemsG5EvidenceLifecycleTest.php`, G10E semantic row | Implemented |
| 17. Evidence assessed and fully traceable | evidence assessment/link services | `AemsEvidenceRequestTest.php`, `AemsG5EvidenceLifecycleTest.php`, G10E semantic row | Implemented |
| 18. No uncontrolled evidence alteration | Core `document_versions`, evidence/WP version services | `AemsWorkingPaperEvidenceTest.php`, `AemsEvidenceRequestTest.php`, G10E semantic row | Implemented |
| 19. Issue history/disposition/conversion preserved | `AemsFindingService`, Issue disposition models | `AemsIssueFindingRecommendationTest.php` | Mostly implemented |
| 20. Issue→AFR normal; direct AFR authorized | `AemsFindingService::createFinding` | `AemsIssueFindingRecommendationTest.php`, G10E semantic row | Implemented |
| 21. Complete Finding structure | finding request/service | `AemsIssueFindingRecommendationTest.php`, G10E semantic row | Implemented |
| 22. Full finding/recommendation traceability | finding, WP, evidence, fieldwork links | `AemsIssueFindingRecommendationTest.php`, G10A conformance tests, G10E semantic row | Implemented |
| 23. Management comments do not overwrite findings | response/rejoinder services | `AemsIssueFindingRecommendationTest.php` | Implemented |
| 24. Corrective action belongs in response | management response model/service | `AemsIssueFindingRecommendationTest.php`; **required:** response-action boundary test | Mostly implemented |
| 25. Interim source and final treatment linked | report assembly/service | `AemsReportTest.php`, G10E semantic row | Implemented |
| 26. Final report uses finalized findings only | report selection guard | `AemsReportTest.php` | Implemented |
| 27. Distribution authority/signatory/transmittal/ack | report distribution service | `AemsReportTest.php`, `AemsG4AuthorityTest.php`, G10E semantic row | Implemented |
| 28. Only issued recommendations transfer to CMS | CMS intake and completion transfer | `CmsIntakeTest.php`, `AemsCompletionClosureTest.php` | Implemented |
| 29. Controlled revision/version/audit trail | version families and AEMS support events | AEMS API tests and `AemsFoundationContractTest.php` | Mostly implemented |
| 30. Completion assessment coverage | `AemsCompletionAssessmentService` | `AemsCompletionClosureTest.php` | Implemented |
| 31. Completed/closed protected | closure service and locked records | `AemsCompletionClosureTest.php`, `AemsFoundationG2Test.php`, G10D Playwright, G10E semantic row | Implemented |
| 32. Significant changes require approval/audit | transition/event/audit services | `AemsFoundationTest.php`, `AemsNotificationTest.php`, `AemsG4AuthorityTest.php`, G10E semantic row | Implemented |
| 33. Engagement/procedure/finding criteria traceable | AEP/program/finding payloads and `audit_finding_procedure` | `AemsAepProgramTest.php`, `AemsIssueFindingRecommendationTest::test_finding_exposes_explicit_procedure_criteria_traceability` | Implemented |
| 34. Planning package complete before fieldwork | planning service and transition gates | `AemsPlanningPackageTest.php`, `AemsEngagementLifecycleTest.php`, G10E semantic row | Implemented |
| 35. Rule-35 Risk Item/Program fields and links | `AemsRiskMatrixItem`, `AuditProgram`, `AuditProgramProcedure` | `AemsPlanningPackageTest.php`, `AemsFoundationG2Test.php`, G10E semantic row | Implemented |

The matrix distinguishes runtime enforcement from compatibility labels. The
G10E semantic acceptance suite executes every row; a compatibility alias is
reported as an alias and is never treated as a second professional decision
path.

## 5. Compatibility and implementation rules

1. No G0 decision permits a browser-only enforcement. Backend authorization,
   transactions, optimistic locking, Activity Log, Audit Trail, and immutable
   versions remain mandatory.
2. Existing `aem.*` compatibility permissions, legacy columns, and coexisting
   IAP risk tables are preserved. A compatibility alias is not a second
   professional decision path.
3. A status migration must be additive, backfillable, dual-readable during
   rollout, and covered by old-record and new-record tests. No status is renamed
   in place.
4. Every exact evidence/report file reference uses a Core
   `document_versions` identifier and checksum; public download URLs are never
   introduced.
5. Automation may calculate due/overdue conditions, reminders, or reviewable
   candidates. It may not approve evidence, validate findings, issue reports,
   close engagements, or make a direct AFR decision.
6. AEMS owns engagement execution and transfer provenance. IAP owns approved
   plan/risk source records, ARMIS owns authoritative resource data only after
   its authority gate, CMS owns post-transfer monitoring/validation/closure,
   and AIS remains a separate, read-only consumer outside AEMS professional
   decision workflows.

## 6. G0 gate and final acceptance order

G0 is complete when:

- all fourteen MDS open decisions are recorded above;
- the status compatibility map is accepted by backend and frontend owners;
- every MDS rule has a code anchor, runtime protection, and a semantic
  acceptance test;
- retention, signature/transmittal, direct AFR, waiver, and distribution
  authority are no longer implicit; and
- this contract is linked from the AEMS README and implementation baseline.

The historical implementation order after this documentation gate was:

1. **G1 professional-control corrections:** evidence eligibility and immutable
   assessment versions, mandatory Finding Conclusion, direct-AFR authority,
   and the hard-coded planning/KPI gate.
2. **G2 foundation and lifecycle conformance:** database one-office invariant,
   structured Area/Focus scope, team amendment authority, special/emergency
   waiver handling, and explicit `COMPLETED` versus `CLOSED`.
3. **G3 planning conformance:** Area/Focus Process Flow fields, multiple Risk
   Matrices, Rule-35 Risk Item fields, Program/Procedure criteria and process
   links, and planning UI/readiness tests.
4. **G4 authority and evidence lifecycle:** AEO/report signatory and
   transmittal records, complete Evidence Request/Evidence status states,
   extensions/overdue/escalation handling, and direct report traceability.
5. **G5 issues, dialogue, reporting, and records:** Issue status projections,
   response extensions/late submissions, operational task/review queues,
   interim/final lineage, distribution authority, archive/disposition, and
   calendar/milestone outputs.
6. **G6 verification and release hardening:** complete rule/SCR/role matrix,
   backend Feature tests, focused Playwright journeys, fresh migration/seed,
   lint/build, protected-download checks, and documentation reconciliation.

Those dependencies have now been exercised through G10E. AIS remains outside
AEMS operational ownership but its AIS-5D read-only integration contract is
implemented separately; compatibility identifiers and both IAP risk systems
remain intentionally preserved.

## 7. Verification record

The G0 contract was initially documentation-only. G10E is the final acceptance
checkpoint and records the current semantic Rule/SCR/role matrix and complete
regression results in `docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md`.
+
### Complete merged source: AEMS_G1_PROFESSIONAL_CONTROLS

# AEMS-G1 Professional-Control Hardening

Status: implemented and verified 13 August 2026.

This checkpoint hardens the professional gates identified by the AEMS-G0
governance contract. It does not change the AEMS navigation model or start a
new evidence/reporting lifecycle.

## Evidence eligibility

Evidence can support Finding validation or finalization only when all of the
following are true:

- the Evidence row is the current revision and is `VERIFIED` or `LOCKED`;
- the assessment is the current immutable `ASSESSED` revision;
- the assessment cites the exact current Core `document_versions` row;
- sufficiency, appropriateness, relevance, accuracy, completeness,
  corroboration, authenticity, and integrity are positive (`YES`, `HIGH`, or
  `ADEQUATE`);
- reliability and competence are positive (`HIGH` or `ADEQUATE`);
- contradiction is explicitly negative (`NO` or `ADEQUATE`);
- confidentiality is classified;
- no evidence gaps remain; and
- limitations, restrictions, or exception-required use have an approved
  exception decision.

The API returns `eligibleForFinalizedFinding` and `eligibilityReasons` for
Evidence and Assessment records. A failed reason is shown to reviewers and
prevents the Validate and Finalize actions. This is a backend rule; the UI is
not the security boundary.

## Immutable assessment and request versions

`AemsEvidenceRequestVersion` and `AemsEvidenceAssessment` rows are immutable.
Corrections create a new version with `supersedes_*`, a revision number, and a
change reason. Approving a restricted-evidence exception also creates a new
immutable assessment revision; it never overwrites the assessed snapshot.

## Findings

- `conclusion` is required by the finding request and by every Submit,
  Validate, and Finalize transition.
- A finding created without a source Issue must include an authorized reason
  (`URGENT_OR_MATERIAL_RISK`, `FORMAL_DIRECTIVE_OR_LEGAL_REQUIREMENT`, or
  `CROSS_CUTTING_OR_SYSTEMIC_MATTER`) and an authority reference.
- The authority actor and timestamp are preserved on the finding and returned
  by the Recommendation/AFR detail contract.
- Findings converted from an Issue must reference a validated Issue in the
  same engagement.

## Engagement progress controls

Entry-conference KPI and reporting progress gates are evaluated from the
approved immutable Planning Package baseline. A configured KPI control must
contain at least one indicator with a name, target, and measurement method.
Progress assessment controls require a recorded status and evidence
reference. Legacy packages without these optional controls remain compatible
and are explicitly reported as not configured/not applicable; they are no
longer represented by unconditional lifecycle gates.

## API and source contract

The existing AEMS finding and evidence endpoints retain their routes. New or
extended response fields are:

- `evidence[].eligibleForFinalizedFinding`;
- `evidence[].eligibilityReasons`;
- `evidence[].assessment.eligibilityReasons`;
- `directCreationReason`, `directCreationAuthority`, `directCreatedBy`, and
  `directCreatedAt` on Findings.

The migration
`2026_08_28_000000_harden_aems_professional_controls` adds the direct-finding
authority provenance columns. Legacy `aems.*` compatibility permissions and
existing route names are unchanged.

## Verification

The professional-control tests cover negative/incomplete evidence,
restricted-evidence exceptions, exact document-version pinning, immutable
request and assessment versions, required conclusions, direct-finding
authority, revision safety, and Planning Package KPI blockers.

### Complete merged source: AEMS_G2_FOUNDATION_SCOPE_LIFECYCLE

# AEMS-G2 Foundation, Scope, Lifecycle, and Navigation Contract

## Status

G2A/G2B is implemented as an additive foundation hardening phase. Existing
`AEMS`, `AEMS-*`, `aems.*`, and legacy `aem.*` identifiers remain compatible.
The descriptive module name in the React navigation is **Audit Engagement
Management**.

## Office invariant and reviewed backfill

Every active engagement has one Engagement Office. The canonical office is
stored in `audit_engagements.engagement_office_id` and the compatibility pivot
`audit_engagement_offices` contains one row marked primary. The G2 migration:

1. records every legacy office set in `aems_engagement_scope_backfill_reviews`;
2. retains the deterministic primary office when legacy duplicates exist;
3. removes duplicate compatibility pivot rows only after recording their IDs in
   the review record; and
4. creates a unique database index so a second pivot cannot be inserted.

Records with no historical office are marked `REQUIRES_REVIEW`; the API and
SCR-212 workspace refuse activation or scope completion until an authorized
reviewer assigns exactly one office. Existing source data is never silently
reassigned.

## Structured scope

The SCR-212 Define Engagement Scope workspace records:

- one Engagement Office;
- engagement-level boundaries and known limitations;
- an explicit source-variance decision (`ALIGNED`, `VARIANCE_APPROVED`, or
  `NOT_APPLICABLE`) with explanation/authority; and
- one or more Audit Areas, each with an objective, boundary, limitations, and
  source-variance note, plus its in-scope Audit Focus IDs.

Area and Focus metadata is stored on the existing compatibility pivots in
`coverage_metadata`. Approved IAP source records remain read-only; the
engagement-specific scope is the AEMS planning input.

## Lifecycle

`COMPLETED` is distinct from `CLOSED`:

- `COMPLETED` means substantive audit work and completion gates are complete;
  the engagement remains active while the formal Closure record and records
  controls are processed.
- `CLOSED` means the approved formal Closure decision has locked the engagement
  and its final records.

The aggregate transition service supports `CLOSURE_REVIEW` → `COMPLETED`
(`COMPLETE_AUDIT_WORK`) and `COMPLETED` → `CLOSURE_REVIEW`
(`OPEN_CLOSURE_REVIEW`) before the existing formal close operation. No child
workflow is bypassed.

## Special and emergency authorization

Special/unplanned engagements preserve the free-form authority type code for
compatibility and now also record `special_authority_class` as `SPECIAL` or
`EMERGENCY`. The approving authority remains separate from the registry
creator. Emergency classification does not bypass ordinary scope, team,
independence, or closure gates.

## IAP risk-source discriminator

Imported engagements expose `iap_risk_source_type`:

- `UNIVERSE_RISK_ASSESSMENT` for `iap_universe_risk_assessments` lineage;
- `LEGACY_RISK_ASSESSMENT` for `iap_risk_assessments` lineage (preserved in
  `iap_legacy_risk_assessment_id`); or
- `null` where the approved source has no risk assessment.

Both IAP risk systems remain present and are not migrated into one another.

## Core numbering

New engagement codes use Runtime Configuration key
`aems_engagement_number_format` (default `AEMS-{YEAR}-{SEQ:3}`), preserving
manual code overrides and uniqueness checks.

## SCR-212 and navigation

SCR-212 is a contextual workspace at
`/audit-engagement-management/{engagementId}?tab=scope`; it is intentionally
not a duplicate sidebar destination. The engagement workspace retains the
canonical SCR-220 tabs: Overview, Planning, Execution, Audit Issues, AFRs,
Conferences, Audit Reporting Workspace, Completion & Transfer, and Activity.
The React module label is reconciled to Audit Engagement Management while
route, permission, table, service, and activity compatibility identifiers
remain AEMS.

## Verification

Focused backend coverage is in
`backend/tests/Feature/Api/AemsFoundationG2Test.php` and protects schema
columns, SCR-212 API behavior, structured coverage, IAP risk discrimination,
the one-office database index, and the `COMPLETED` projection. Existing
registry, lifecycle, and foundation tests remain regression coverage.

### Complete merged source: AEMS_G3_PLANNING_CONFORMANCE

# AEMS-G3 Planning Conformance

Implemented on 13 August 2026 as an additive conformance layer over the
existing immutable Planning Package and Audit Program workflows.

## Backend contract

- Process-flow records support Area/Focus scope, boundary statement, ordered
  steps, inputs, outputs, records/systems, controls, decision points, risk
  points, limitations, and Core Document Version references.
- A planning version may contain multiple risk matrices. The legacy
  `riskMatrix` relation and payload remain as the first-matrix compatibility
  alias; the canonical payload is `riskMatrices`.
- Risk Matrix Items carry Rule-35 planning fields: Area/Focus, process, risk
  area, planned audit approach, criteria, process-flow traceability, response
  rationale, and source reference.
- Audit Programs carry Area, type, period, criteria, risk-statement set,
  sampling approach, and planned Working Paper requirements.
- Procedures carry Area/Focus, process, method, criteria, planned person-days,
  sampling requirements, planned Working Paper requirements, and risk links.
- `aems_planning_kpis` stores immutable-version KPI records.
- `aems_planned_working_paper_requirements` stores required planned work
  products and their evidence expectations.

## Readiness and fieldwork gate

The Planning Package workspace returns both the historical compatibility
`ready` value and the strict `fieldworkReady` value. `fieldworkReady` requires
valid IAP lineage, a complete survey and objectives, approved AEP and current
Audit Program, structured process-flow details, risk-matrix coverage for every
authorized Area, complete Rule-35 risk item fields and traceability, program
period/criteria/sampling, complete procedure process/method/criteria/person-
days/sampling/planned-WP values, KPI records or a documented not-applicable
decision, and at least one complete required planned Working Paper.

`START_FIELDWORK` requires an approved package whose current version is the
approved version and whose strict `fieldworkReady` result is true. The
aggregate transition service reports failed conformance checks and does not
mutate child workflow records.

## API and compatibility

Existing Planning Package and Audit Program endpoints accept the new camelCase
fields and return them in snapshots. No new sidebar route was added: Process
Flow, Risk Matrix, KPI, and planned-WP controls remain artifacts in the
canonical Planning Package and Program workspaces. Existing legacy fields,
endpoints, statuses, singular `riskMatrix` payload, and old planning tests
remain supported.

Additive migration:
`2026_08_30_000000_add_aems_g3_planning_conformance.php`.

### Complete merged source: AEMS_G4_AEO_TEAM_AUTHORITY

# AEMS-G4 AEO and Team Authority Contract

## Scope

G4 adds authority and auditable distribution controls without renaming the
existing `AEMS`, `AEMS-*`, `aems.*`, or legacy `aem.*` identifiers.

## AEO signatory matrix

Every AEO content version receives three required matrix entries:

1. `INDEPENDENT_REVIEWER`;
2. `APPROVING_AUTHORITY`; and
3. `ISSUING_AUTHORITY`.

The matrix is stored in `aems_aeo_signatories`. A transition records an
in-app attestation by the actor and preserves the signing method, reference,
actor, timestamp, and AEO version. Existing audited review events are
materialized as `LEGACY_EVENT_ATTESTATION` entries when an older AEO is first
approved after G4.

The preparer normally cannot independently approve or issue. When the active
CIAS Head is the sole available CIAS Management authority, she may record the
AEO review, approval, and issuance exception described above. Approval still
requires the review step, and signed matrix entries are immutable.

## AEO status controls

Existing draft/review/approval/issuance transitions remain supported. G4 adds:

- `CANCEL` → `CANCELLED` for an authorized cancellation;
- `VOID` → `VOIDED` for an authorized invalidation; and
- `SUPERSEDE` → `SUPERSEDED` when an issued order is retired before a new
  active AEO is created.

All three require a reason, CIAS Management authority, a lock version, an
engagement event, an audit log entry, and the actor identity. They deactivate
the order but do not delete versions. `AMEND` creates a new draft version and
preserves the previous issued version.

## Distribution and acknowledgement

Only an `ISSUED` AEO can be distributed. `aems_aeo_distributions` records the
version, recipient user or office, transmittal method, transmittal reference,
sent timestamp, and acknowledgement. A recipient or authorized internal user
may acknowledge once; the acknowledgement note and actor are retained.

API endpoints:

- `GET /api/aems/engagements/{engagement}/aeo/{order}/distribution`
- `POST /api/aems/engagements/{engagement}/aeo/{order}/distribution`
- `POST /api/aems/engagements/{engagement}/aeo/{order}/distribution/{distribution}/acknowledge`
- `GET /api/aems/aeo-acknowledgements`
- `POST /api/aems/aeo-acknowledgements/{distribution}/acknowledge`

## Team amendments and access history

Team assignment, update, reassignment, and ending create immutable records in
`aems_team_amendments`. Each record includes authority code, reason,
consequence assessment, before/after snapshots, actor, and timestamp.

`aems_team_access_history` separately records access grants, updates, and
revocations with role, dates, actor, reason, and assignment snapshot. Existing
`engagement_team_history` remains the compatibility workflow history.

## Permissions

G4 permissions are:

- `aems.aeo.sign`, `aems.aeo.amend`, `aems.aeo.distribute`,
  `aems.aeo.acknowledge`, `aems.aeo.cancel`, `aems.aeo.void`,
  `aems.aeo.supersede`;
- `aems.team.amend`; and
- `aems.team.history`.

Distribution, cancellation, voiding, supersession, and team amendment are
CIAS-authorized operations. Assignment-scoped users can inspect history. The
API remains authoritative for engagement scope, lock versions, and separation
of duties.

## Verification

The migration is additive and passed a fresh seeded migration. Existing AEO
and team tests remain useful regression coverage; tests that intentionally
reuse one actor for review and approval must use separate authorities under
the G4 separation-of-duties rule.

### Complete merged source: AEMS_G5_EVIDENCE_LIFECYCLE

# AEMS-G5 — Complete Evidence Lifecycle

Implementation status: backend and consolidated Evidence Management workspace are implemented.

## Evidence Request lifecycle

`DRAFT → SUBMITTED → SENT → ACKNOWLEDGED → PARTIALLY_RECEIVED → RECEIVED → FOR_REVIEW → ASSESSED → CLOSED`

Control states are available when applicable: `OVERDUE`, `EXTENSION_REQUESTED`, `EXTENDED`, `ESCALATED`, `CANCELLED`, and `CLOSED_WITHOUT_SUBMISSION`.

- Acknowledgement is restricted to the requested custodian user/office (or an authorized internal participant).
- Extensions are requested with a reason and future due date, then independently approved.
- Overdue and escalation actions are recorded with actor, timestamp, reason, status transition, and immutable request-event history.
- Cancellation and no-submission closure require an explicit reason. No-submission closure is not ordinary assessed closure.
- Request and receipt records use optimistic locking. Request versions and request lifecycle events are append-only.

## Evidence outcomes and professional eligibility

Every current Evidence record has an explicit outcome: `REGISTERED`, `FOR_ASSESSMENT`, `ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`, `DUPLICATE`, `SUPERSEDED`, or `VOIDED`.

An Evidence record may support a validated or finalized Finding only when:

1. the current Evidence version is verified or locked;
2. the exact current Core `document_versions` row is cited;
3. a current immutable professional assessment exists;
4. all assessment dimensions are positive/adequate, with no unresolved gap, limitation, restriction, or contradiction; and
5. the explicit outcome is `ACCEPTED`, or `LIMITED` with an independently approved exception.

Negative or incomplete assessments are retained for audit history but cannot be accepted for reporting. Evidence acceptance through the API also rechecks the assessment independently.

## Traceability

Evidence captures acquisition method/form, planning objective, risk-matrix item, and control reference. Evidence can be linked to an exact `audit_report_versions` row through the protected report-link endpoint. Issued/locked report versions cannot have links changed.

## API contract

- `GET /api/aems/engagements/{engagement}/evidence-requests` — consolidated requests, evidence, assessments, statuses, and lifecycle events.
- `POST /api/aems/engagements/{engagement}/evidence-requests/{evidenceRequest}/transition` — all request transitions; requires `lockVersion`, and extension requests additionally require `extensionDueDate`.
- `POST /api/aems/engagements/{engagement}/evidence/{evidence}/transition` — checksum verification, explicit outcome, and void actions.
- `POST /api/aems/engagements/{engagement}/evidence/{evidence}/report-links` — link accepted evidence to an unlocked report version.

All actions use scoped AEMS permissions, separation-of-duties checks where professional decisions are made, Core activity/audit events, and notifications.

The seeded operational permission contracts include `aems.evidence.outcome`,
`aems.evidence.link_report`, `aems.evidence-request.acknowledge`,
`aems.evidence-request.extend`, `aems.evidence-request.extension_approve`,
`aems.evidence-request.overdue`, `aems.evidence-request.escalate`, and
`aems.evidence-request.cancel`. Auditee representatives retain view and
acknowledgement compatibility access without receiving internal assessment,
outcome, or closure permissions.

### Complete merged source: AEMS_G6_ISSUES_AFR_DIALOGUE

# AEMS-G6 — Issues, AFR, Dialogue, and Queues

Implemented as an additive contract over the existing AEMS-6 through AEMS-7
workflows. Legacy issue statuses remain compatible: `DISMISSED` is retained for
existing disposition rows while `statusCompatibility.canonical` maps
`SUBMITTED` to `FOR_REVIEW`, `VALIDATED` to `UNDER_EVALUATION`, and terminal
rows to `DISPOSED`/`WITHDRAWN`; `disposition`/`terminalDisposition` identifies
the precise professional terminal decision. New controlled withdrawal uses `WITHDRAWN`
and requires an independent actor, a reason, engagement scope, and the current
lock version.

## AFR communication

Formal Finding communication creates an immutable `aems_finding_transmittals`
snapshot with the exact Finding revision, evidence/working-paper references,
confidentiality, method, response due date, sender, and recipient register.
Recipient delivery and acknowledgement are controlled transitions. Each event
is append-only and records actor, timestamp, content, and status change. The
API exposes protected transmittal creation and recipient delivery/acknowledgement
routes; no public download URL is introduced.

## Response extensions and late/supplemental dialogue

Management responses retain immutable version history and now carry a response
kind (`ORIGINAL`, `LATE`, `SUPPLEMENTAL`, `REPLACEMENT`), extension request and
approval metadata, effective due date, late reason, and supplemental reason.
Auditee representatives may request an extension; an independent reviewer may
approve or reject it. Submission after the effective due date requires a late
reason. Supplemental responses are separate current records and do not replace
the original response. Extension, late, and supplemental events are recorded in
the immutable due-process stream. Response kind cannot be changed while editing
a draft; a supplemental or late exchange must be created through its own
controlled record path.

## Queues and professional controls

The existing operational Task, Review Note, due-process, and escalation
services remain the single work-queue implementation. G6 actions use the same
engagement scope, office restrictions, separation-of-duties checks,
optimistic-lock checks, notifications, Activity Log, Audit Trail, and immutable
version conventions as the preceding phases.
Task assignment additionally requires an active engagement team assignment (or
an explicitly global account), and an assigned office must be in the engagement
scope and match the assigned user's office. Auditee acknowledgement is limited
to the responsible office and the named recipient user/office on the exact AFR
transmittal.

Focused verification is covered by `AemsG6IssuesDialogueContractTest`, the
existing issue/finding/recommendation feature suite, frontend lint/build, and
the AEMS issue/dialogue browser suite.

### Complete merged source: AEMS_G7_REPORTING_DISTRIBUTION

# AEMS-G7 — Reporting and distribution

G7 extends the AEMS reporting workspace without changing the immutable report
version contract. Each generated version carries a source manifest and a
SHA-256 manifest hash. The manifest pins the engagement, finding revisions,
Issues, approved Working Paper versions, and the exact Core Document Version
and checksum for every linked Evidence item.

## Source traceability

Draft, Interim, and Final report requests may include `issueIds`,
`workingPaperVersionIds`, and `evidenceIds`. Links are stored in immutable
version-bound tables. Working Papers must be approved/current and Final Reports
may only link Evidence with an `ACCEPTED` outcome. Final creation records the
approved Interim/Draft source version and an `interimTreatment` value
(`RETAINED_WITH_REVIEW`, `REVISED`, `OMITTED`, or `RESOLVED`). Existing report
versions remain immutable; amendments and supersessions still create new
versions.

## Authority, signatures, and transmittal

The authority matrix is append-only and version-bound. It records IAU Head
recommendation and LCE approval decisions (and permits a Presiding Officer
approval record), including actor, date, comment, and reference. The existing
approval transition records controlled compatibility decisions for legacy
report flows. Issuance requires both matrix decisions and creates immutable LCE
and Presiding Officer signatory records. Issuance also creates an immutable
controlled-system transmittal; additional transmittals can be recorded with a
reference, method, status, note, and sending actor.

For legacy report flows that do not submit separate authority rows, the
controlled approval action records compatibility IAU Head/LCE decision rows;
new clients can submit explicit version-bound decisions before approval.

## Protected reproducible exports

`GET .../versions/{version}/exports/PDF` and `.../CSV` are authenticated,
permissioned, scope-aware endpoints. They require an issued locked version.
PDF exports use the locked Core Document Version; CSV exports are generated
from the stored source manifest. Every export records its format, manifest
hash, file checksum, size, scope hash, generated actor/time, and protected
storage path. No public URL is exposed.

## Administrative closure

An issued report may be administratively closed with a reason and optional
reference by a separately authorized supervisor. Closure changes only the
report-family status to `ADMINISTRATIVELY_CLOSED`; the issued version, source
manifest, signatures, and transmittal remain locked and auditable.

## API additions

- `POST /aems/engagements/{engagement}/reports/{report}/versions/{version}/authority-decisions`
- `POST /aems/engagements/{engagement}/reports/{report}/versions/{version}/signatories`
- `POST /aems/engagements/{engagement}/reports/{report}/versions/{version}/transmittals`
- `POST /aems/engagements/{engagement}/reports/{report}/administrative-close`
- `GET /aems/engagements/{engagement}/reports/{report}/versions/{version}/exports/{PDF|CSV}`

Permissions are `aems.report.authority`, `aems.report.signatory`,
`aems.report.transmit`, `aems.report.export`, and `aems.report.close_admin`.
All actions use the existing AEMS scope, activity-event, audit-trail, and
optimistic-locking infrastructure.

### Complete merged source: AEMS_G8_RECORDS_CALENDAR_CLOSURE

# AEMS-G8 — Records, calendar, and closure hardening

G8 completes the operational records controls around AEMS closure while
preserving Core `documents` and immutable `document_versions`. AEMS records a
controlled archive or disposition decision; it does not physically delete a
Core document from these endpoints.

## Retention and disposition states

`engagement_retention_records` now has append-only operational state in addition
to its approved retention snapshot:

- `archive_status`: `ACTIVE`, `ARCHIVED`, or `DISPOSITION_RECORDED`;
- `legal_hold_flag`, release time, actor, and reason;
- legal-hold release reference where an external authority supplies one;
- `destruction_eligibility_status`: `NOT_REVIEWED`, `ELIGIBLE`, or
  `NOT_ELIGIBLE`;
- review and disposition timestamps, actors, reasons, and references.

Every archive, legal-hold release, destruction review, and disposition record
is preserved in `aems_record_disposition_actions`. These rows are immutable and
include actor, timestamp, reason, before/after state, reference, and a
retention snapshot. A legal hold blocks archive and disposition. Destruction
review reports the reasons for ineligibility; a disposition record can only be
entered after an approved, eligible review with no active hold. The disposition
endpoint records the authorized external/provider reference and does not remove
files.

## Closure blocker register

The authoritative closure checklist now blocks unresolved legal holds and
overdue required Audit Calendar milestones. Existing document-index,
retention-approval, transfer, reporting, evidence, dialogue, and completion
guards remain atomic in `AemsEngagementTransitionService`. `COMPLETED` remains
the substantive completion state and `CLOSED` remains the subsequent formal
administrative closure state; neither state is silently collapsed into the
other.

## Audit Calendar

`aems_engagement_milestones` stores milestone code, category, dates, owner,
required flag, related record, status, lock version, and actor history. A
completed milestone is immutable. The calendar API calculates open, overdue,
and completed totals, and milestone transitions use optimistic locking.

## Protected API contract

- `GET /aems/engagements/{engagement}/records?q=...`
- `GET /aems/engagements/{engagement}/calendar`
- `POST /aems/engagements/{engagement}/calendar/milestones`
- `PUT /aems/engagements/{engagement}/calendar/milestones/{milestone}`
- `POST /aems/engagements/{engagement}/calendar/milestones/{milestone}/transition`
- `POST /aems/engagements/{engagement}/retention/{retention}/archive`
- `POST /aems/engagements/{engagement}/retention/{retention}/legal-hold-release`
- `POST /aems/engagements/{engagement}/retention/{retention}/destruction-review`
- `POST /aems/engagements/{engagement}/retention/{retention}/disposition`

The new permission family is `aems.records.view/search`,
`aems.calendar.view/manage`, and the controlled `aems.retention.archive`,
`aems.retention.legal_hold_release`, `aems.retention.destruction_review`, and
`aems.retention.disposition_execute` actions. Scope and closed-engagement
guards are applied by `AemsAccessService`; sensitive actions are CIAS-only or
reviewer-separated. The React engagement workspace exposes **Records &
Disposition** and **Audit Calendar** tabs with empty, error, search, and
permission-aware states. Records marked `RESTRICTED` or `SECRET` are omitted
unless the user has the Core `documents.view_restricted` permission.

## Verification

The focused closure regression now covers records search, milestone creation,
scope enforcement, and an auditable ineligible destruction review. The
existing four closure/completion tests continue to pass.
