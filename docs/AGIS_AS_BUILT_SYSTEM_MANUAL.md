# AGIS As-Built System Manual

## 1. Purpose and authority

This is the consolidated, module-oriented guide to the AGIS application as it
is implemented in this repository. It explains what each page does, who may use
it, which records are created, how a record moves through its lifecycle, what
must be true before the next action is available, and what is written to the
Activity Log, Audit Trail, notification center, and document repository.

This manual is written for a new user, reviewer, administrator, tester, and
developer. It is an operational companion to the detailed design and API
documents, not a replacement for source code. When a statement conflicts with
the application, the following order is authoritative:

**Last synchronized with source and tests:** 24 August 2026.

1. Laravel services, Form Requests, policies, models, migrations, and routes.
2. React routes, navigation registry, and API client.
3. Automated Feature and Playwright tests.
4. This manual and the other documents in docs/.

The main references are API_AND_DATA_REFERENCE.md, SYSTEM_FLOW.md,
END_TO_END_TESTING_GUIDE.md, and the module workflow documents linked from
README.md.

## 2. Global operating rules

### 2.1 Identity, scope, and separation of duties

- Users sign in with employee ID and password. Sessions use Laravel Sanctum.
- Every request is authenticated, then checked against the effective union of
  the user's active roles, office scope, engagement scope, record ownership,
  status, and confidentiality.
- A user's office is the default scope anchor. A user can be granted all
  offices, own office, assigned offices, or no office access.
- Engagement-level scope can be all engagements, own-office engagements,
  assigned engagements, or none.
- Auditee Representatives see only findings formally communicated to their
  office and only the response/evidence actions permitted for that finding.
- Technical or registry administration does not automatically grant a
  professional approval. Submitters, reviewers, approvers, issuers, and
  closure authorities are separated where the workflow requires it.
- A user must never approve, validate, finalize, or close their own work when
  the service enforces an independent actor.

### 2.2 Record integrity

- Approved, issued, finalized, transferred, and locked records are immutable.
  A correction creates a revision or superseding version; it does not overwrite
  the approved source.
- Optimistic locking (lock_version) prevents two users from silently
  overwriting a change. A 409 response means reload and reconcile the record.
- Soft deletion is used where the model supports it. Delete removes the record
  from normal lists but preserves history; restore returns the same identifier.
- Cross-module references use immutable IDs, version IDs, source snapshots, and
  transfer manifests. A receiving module does not rewrite its source module.
- Exact Core document_versions are used for evidence, reports, signatures,
  transmittals, and other protected files. Checksum, size, MIME type,
  confidentiality, custody, and version are retained.

### 2.3 Logs, notifications, and files

Every meaningful mutation records actor, timestamp, action, old/new state (when
applicable), related module/record, and correlation/version information in the
Activity Log and Audit Trail. Workflow and due-process events may additionally
create a notification or reminder. Protected downloads require an authenticated
route, permission, scope check, and confidentiality check; public file URLs are
not exposed.

### 2.4 Common response handling

| Response | Meaning | What the user should do |
| --- | --- | --- |
| 200/201 | Read or mutation succeeded | Refresh the workspace if the action changed a related list |
| 401 | Session expired | Sign in again; do not retry a mutation blindly |
| 403 | Permission, office, engagement, or confidentiality failure | Ask the scope/role administrator; the record is not necessarily missing |
| 404 | Record/file is absent or intentionally hidden outside scope | Verify the identifier and engagement |
| 409 | Stale version, invalid lifecycle action, duplicate transfer, or separation-of-duties conflict | Reload, inspect the reason, and complete the prerequisite or use a revision |
| 422 | Required field or professional rule failed | Correct the fields listed in the response |
| 429 | Download/report rate limit | Wait for the retry window; do not bypass the protected endpoint |
| 500 | Unexpected server failure | Retry a read, capture the correlation ID, and report it; do not repeat a professional mutation without checking the log |

## 3. Core Records (platform foundation)

### 3.1 Core pages and functions

| Page/registry | Main functions | Typical permissions |
| --- | --- | --- |
| Dashboard (/dashboard) | Scope-aware module cards, tasks, upcoming activities, recent engagements, and quick links | dashboard.view plus the module permissions represented by a card |
| Office Registry (/office-registry) | Create, edit, archive, restore, assign office head, inspect users/areas/history | offices.view, offices.create, offices.update, offices.archive, offices.restore |
| Audit Area Registry | Maintain reusable auditable processes/themes and hierarchy | audit_areas.* |
| Audit Focus Registry | Maintain one-area focus records and display order | audit_focus.* |
| User Registry | Create/update users, assign one office and roles, lock/unlock, disable, reset password, archive/restore | users.* |
| Access Role Registry | Create roles, assign permissions, set primary role and compatibility behavior | roles.*, permissions.* |
| Master Lists | Maintain controlled values used by modules | master_lists.* |
| Document Repository | Register documents, upload immutable versions, set confidentiality, link a document to a module record, download protected versions | documents.* and module document permissions |
| Workflow/Notification/Activity/Audit views | Inspect definitions/instances, notifications, Activity Log, and Audit Trail | administrator and audit-view permissions |
| System Configuration | Runtime branding, numbering, session/security, mail and feature flags | system_configuration.* |
| Administrative Reports | Scope-pinned report snapshots and authenticated CSV/PDF exports | Core report view/export permissions from RolePermissionSeeder |

### 3.2 Core user lifecycle

1. An administrator opens User Registry and selects New user.
2. Enter normalized first name, optional middle initial, last name, extension,
   unique employee ID, office, employment details, active state, and roles.
3. Save. The API validates employee-ID uniqueness, office assignment, role
   availability, and scope rules, then writes the user and an audit event.
4. To change access, edit office/roles and verify the effective permission
   preview before saving.
5. To stop access temporarily, use Disable or Lock; do not archive an account
   merely to handle a failed login. To remove a historical user from normal
   lists, archive; restore retains the same history and ID.
6. Password reset, manual unlock, and profile changes are separate audited
   actions. The user must sign in again after a reset or session revocation.

### 3.3 Core document procedure

1. Create document metadata with module, record type, owner, confidentiality,
   and retention classification.
2. Upload through the protected API. The server calculates checksum, size, MIME
   type, and version; the browser never supplies these as authoritative values.
3. Link the exact document_version_id to the business record. A later upload
   creates a new version and leaves the old version available for history.
4. After approval/issuance, download only through the authenticated endpoint.
   Confidential files also require office/engagement scope and clearance.
5. For a correction, create a revision and link it as superseding the prior
   version. Never replace the binary or checksum of an approved version.

### 3.4 Shared workflow, notification, and audit functions

- A workflow definition describes allowed states/actions; a workflow instance
  records current state, actors, timestamps, comments, and version.
- A controller calls the domain service; the service checks state, permission,
  scope, separation of duties, readiness, and lock version before mutation.
- A notification is an instruction or reminder, not proof that an action was
  approved. Open it to reach the owning record and complete the action there.
- Activity Log answers who did what and when; Audit Trail preserves before/after
  or immutable decision context. Inspect both for disputed transfers, returns,
  approvals, downloads, and status changes.
- Runtime configuration supplies safe branding, numbering, mail/session values,
  feature switches, and policy thresholds. It never replaces professional
  approval or a database constraint.

## 4. Internal Audit Planning (IAP)

IAP decides what should be audited and when. It owns plans and prioritization;
it does not perform AEMS fieldwork and does not receive AEMS results.

### 4.1 IAP pages

| Page | Function |
| --- | --- |
| IAP Dashboard | Planning workload, plan status, due items, and links |
| Strategic Audit Plan (/internal-audit-planning/strategic-plan) | Multi-year objectives, risk themes, priorities, review/return/approval |
| Audit Universe | Register auditable entities, areas, focuses, offices, and universe history |
| Risk Assessment (/internal-audit-planning/risk-assessment) | Current IAP risk-assessment register and scoring |
| Audit Prioritization | Finalized prioritization runs and ranked universe |
| Annual Audit Plan (/internal-audit-planning) | Build yearly plan from finalized priorities, assign offices/resources, review and approve |
| Audit Scheduling | Place approved engagements on the calendar and maintain scheduling revisions |
| Resource Capacity | Historical IAP planning view; current capacity, unavailability, skills, and requirements are maintained in ARMIS |
| IAP Reports | Scope-pinned planning reports and protected exports |

IAP deliberately retains two risk systems: iap_risk_assessments (plan-level
assessment) and iap_universe_risk_assessments (universe-level assessment).
They have different source lineage and must not be merged or deleted without a
governance decision.

### 4.2 Plan lifecycle and review

The common plan lifecycle is:

    Draft -> Pending Review -> Approved
                         \-> Returned for Revision -> Resubmitted -> Approved
    Approved -> Active -> Completed

1. Create the draft and populate objective, scope/year, source risk run,
   priority, responsible office, proposed timing, resources, and attachments.
2. Select Submit only when required planning records and a finalized
   prioritization run exist. Submission freezes the submitted snapshot.
3. A reviewer chooses Return with a recorded reason or Approve. A submitter
   cannot approve their own plan.
4. A returned plan is edited as a revision, resubmitted, and reviewed again;
   the returned version remains in history.
5. An approved plan can be activated for the operating year and later marked
   completed. Completion does not permit mutation of the approved plan.

### 4.3 IAP-to-AEMS handoff

1. Ensure the Annual Audit Plan is approved/active and the intended engagement
   option has a stable source ID.
2. In the Audit Engagement Workspace choose Import approved IAP plan and select only
   an eligible source in the user's scope.
3. Review the preview: source plan/version, office, audit area/focus,
   risk-source discriminator, schedule, and snapshot hash.
4. Confirm once. AEMS stores immutable source lineage and a source snapshot; it
   does not update the approved IAP row.
5. If the same source/version was already imported, the API returns a duplicate
   conflict. Open the existing engagement instead of importing again.
6. A later IAP revision does not silently change the AEMS snapshot. Resync or
   reimport requires an explicit governed action.

### 4.4 IAP reports, capacity, and scheduling decisions

- IAP Reports use finalized prioritization and approved/active/completed plan
  data. Select period and scope, export through the protected endpoint, and
  retain the returned checksum.
- Resource Capacity records interim auditor skills, availability, leave/training,
  capacity, and engagement skill requirements. A warning is visible to the
  planner; it is not an ARMIS authority decision.
- Audit Scheduling preserves proposed/approved dates and every revision reason.
  Moving a date updates milestone/task notifications and does not rewrite the
  historical approved plan snapshot.

## 5. Audit Engagement Management (AEMS)

AEMS performs and reports the audit. Its navigation follows the approved SCR
contract; Process Flow and Risk Matrix are planning artifacts inside the
Planning Package, not separate sidebar modules.

### 5.1 AEMS navigation and ownership

| Workspace/route | SCR or contract | Purpose |
| --- | --- | --- |
| AEMS Dashboard | Portfolio | Engagement progress, work queues, overdue items, conferences, reports, closure readiness |
| Audit Engagement Workspace (/audit-engagement-management) | SCR-210/211 | Create/import engagements, edit foundation metadata, search/filter, archive/restore, and open engagement context |
| Engagement Scope (/audit-engagement-management/scope) | SCR-212 | Select exactly one office, office-linked Audit Areas, and Area-linked Audit Focuses; record boundaries, limitations, and source variance |
| Audit Team (/team) | SCR-213 | Assign people, competencies, independence, workload, and amendments |
| Engagement Orders (/aeo) | SCR-214 | Prepare, review, approve, issue, supersede, cancel/void AEO versions |
| Planning Workspace (/planning-package) | SCR-221 | Preliminary survey, Process Flow, Risk Matrix, AEP + KPIs, readiness, review/versioning |
| Engagement Plan (/aep) | SCR-222 | Objective, scope, criteria, approach, communication and approval |
| Audit Program (/audit-program) | SCR-223/224 | Procedures, risks, criteria, sampling, planned WP, person-days, execution links |
| Audit Procedure Details (/audit-procedure-details) | SCR-224 | Procedure process/method/risk lineage, responsible person, planned days, WP/evidence links, results, conclusion, review, and issue creation |
| Execution Workspace (/execution) | SCR-226/227 | Fieldwork records, procedure execution, tasks, reviewer notes, blockers |
| Conference Management (/conferences) | SCR-225 | Manage Entry and Exit Conference schedules, participants, attendance, agenda, minutes, agreements, waivers, and acknowledgements |
| Working Papers & Evidence (/working-papers) | SCR-228 | WP lifecycle, immutable approved versions, evidence/WP traceability |
| Evidence Management (/evidence) | SCR-229 | Requests, receipt, assessment, gaps, restriction, custody, protected files |
| Audit Issues (/issues) | SCR-230/231/232 | Issue register, dispositions, merge/referral/resolution/withdrawal |
| Findings & Recommendations (/findings) | SCR-240 | Criteria/condition/cause/conclusion/effect, risk, evidence, response, rejoinder, finalization |
| Auditee Responses (/auditee-responses) | SCR-241/242/244 | Communicated finding responses, clarifications, extensions, rejoinders |
| Exit Conferences (/exit-conferences) | SCR-225 | Findings discussed, agreements/disagreements, revised dates, acknowledgement |
| Audit Reporting Workspace (/reports) | SCR-250–254 | Interim, draft, final, distribution, immutable issued reports |
| Operational Work Queues (/work-queues) | G10C | Tasks, Review Notes, due process, escalation candidates, assignments |
| Audit Calendar (/calendar) | G10C/G8 | Milestones, conferences, due/overdue states, filters |
| Registers & Exports (/registers) | G10C | Protected scope-aware AEMS registers and exports |
| Records & Administrative Closure (/records-closure) | SCR-260–263 | Retention, archive/disposition, legal hold, completion, CMS transfer, closure/reopen |

The engagement workspace tabs are Overview, Planning, Execution, Audit Issues,
AFRs, Conferences, Audit Reporting Workspace, Completion & Transfer, and
Activity. The sidebar opens a workspace; the tab/action opens the engagement
context. Entry and Exit Conference compatibility routes remain available below
Conference Management, but Conference Management is the primary combined
workspace.

### 5.2 Engagement authorization procedure

1. Create an engagement or import an approved IAP source. New special/unplanned
   records are saved as `DRAFT`; external authority details do not bypass the
   internal authorization workflow. Open **Engagement Scope**, select exactly
   one office first, then choose only Audit Areas linked to that office and
   Audit Focuses linked to those areas. Save the scope with boundaries,
   limitations, source variance, and applicable objectives.
2. From `DRAFT`, use the Lifecycle workspace to select **Prepare Authorization**.
   Audit Team assignment is intentionally locked during `DRAFT` and unlocks in
   **Authorization Preparation**. Assign the team only after checking current
   ARMIS competency, availability, leave/training conflicts, workload,
   objectivity, and independence declarations.
3. Prepare the AEO and its authority/signatory/distribution metadata. Submit;
   the assigned reviewer records review or returns it with reasons. The active
   CIAS Head may record review of an AEO she prepared as a controlled exception.
   If no alternate CIAS Management authority is available, she may also approve
   and issue that same AEO.
4. Otherwise, an active CIAS Management account approves the reviewed AEO, and a
   different active CIAS Management account issues it. The AEO workspace shows
   the candidate/current account beside each action and records the actor's
   name, username, position, timestamp, and signature status.
5. Issue the approved AEO through the protected issuance action. Issuance locks
   the version and records signatory, transmittal/delivery, recipients, and
   acknowledgements. A correction is an amended/superseding version.
6. Return to the engagement **Lifecycle** tab after the AEO status is
   **ISSUED**. Select **Issue Authorization** under Allowed actions and gates.
   The CIAS Head's controlled aggregate authorization moves the engagement from
   **Authorization Preparation** to **Authorized** and is recorded separately
   from the child AEO issuance. In a sole-head deployment, the same active
   CIAS Head may perform this aggregate authorization; the exception is limited
   to this gate and remains auditable.
7. Select **Start Planning** from the Authorized lifecycle state. This moves
   the engagement to **Engagement Planning** and unlocks the AEP and Planning
   Package workspaces. Prepare the AEP only after an issued AEO exists. Submit,
   return/resubmit, or approve under the applicable review controls. The
   approved AEP is the fieldwork base.
8. In a single-authority deployment, the active CIAS Head may use the same
   controlled exception for her own AEP, Planning Package, Audit Program,
   Working Paper, Fieldwork, evidence, finding, report, completion, transfer,
   and closure review/approval actions. The exception is available only when
   no alternate active CIAS Management authority exists; all readiness,
   evidence, status, version-lock, and audit requirements remain enforced.

### 5.3 Planning Package and fieldwork gate

The Planning Package contains the Preliminary Survey, structured Process Flow,
authorized Risk Matrices, risk items, risk-to-objective/procedure/working-paper
traceability, sampling/planned WP requirements, KPIs, and review history.

    Draft -> Pending Review -> Approved
                         \-> Returned for Revision -> Resubmitted -> Approved
    Approved -> New Revision (Draft)

The readiness checklist must show AEO/AEP, scope, process flow, risk matrix/items,
program/procedure traceability, team safeguards, sampling, planned WP, and
KPI/communication gates. Fieldwork is blocked until the package is approved and
its baseline version is recorded.

### 5.4 Audit Program and execution

1. Create a program from the approved AEP/planning package. Add area, focus,
   period, type, criteria, process, method, risk statement, planned days,
   sample design, and planned working paper.
2. Add procedures and link each to risk, criteria, process, area/focus, planned
   WP, and sampling where required.
3. Submit for independent review. Return/resubmit or approve. An approved
   program can start (ACTIVE) and later be completed.
4. For every completed procedure create at least one Fieldwork Record. Select
   type, date/location, participants, procedure, area/focus, WP, and evidence.
5. Record results, conclusion, reviewer notes, related tasks, and execution
   status. Finalize only after required traceability and evidence controls pass.

### 5.5 Working Papers and evidence

Working Paper lifecycle:

    Draft -> Submitted -> Returned for Revision -> Resubmitted -> Approved

Use unique WP index, objective, procedure performed, population/sample, results,
conclusion, preparer/reviewer/date, cross-references, and revision history.
Approved WPs are locked; corrections create a revision.

Evidence Request lifecycle is distinct from evidence assessment:

    Draft -> Submitted -> Sent -> Partially Received -> Received -> Assessed -> Closed

The request tracks source/custodian, due date, correspondence, extensions,
acknowledgement, overdue/escalation, cancellation/no-submission outcome, and
attachments. Evidence uses Core document versions for checksum, size, MIME,
custody, confidentiality, protected download, and immutable history.

Assessment addresses sufficiency, appropriateness, relevance, reliability,
competence, accuracy, completeness, corroboration, contradiction, authenticity,
integrity, confidentiality, access restrictions, limitations, and gaps. An
assessment marked negative, partial, not assessed, restricted without approved
exception, or with unresolved gaps cannot support a validated/finalized finding.

### 5.6 Issues, Findings, AFRs, and dialogue

An issue may be submitted, validated, evaluated, merged, resolved during audit,
observation, referred, closed without finding, dismissed, withdrawn, or
converted to a finding. A direct finding requires an authorized reason and
authority; it is not a shortcut around issue governance.

Finding lifecycle:

    Draft -> Pending Review -> Validated -> Communicated
           -> Awaiting Management Response -> Under Dialogue -> Finalized

The finding contains Criteria, Condition, Cause, Conclusion, Effect/significance,
risk rating, evidence, responsible office, management response, auditor
rejoinder, recommendation, and direct fieldwork/WP traceability. The author
cannot validate it. Corrections, amendment, withdrawal, and supersession create
immutable snapshots and a reason. Finalized recommendations are immutable and
transfer only once.

After formal communication, an auditee may agree, partially agree, disagree,
comment, propose corrective action, name responsible personnel, propose target
dates, upload support, and submit clarification/late/supplemental responses.
Auditors may accept, partially accept, reject, request clarification, extend a
due date under policy, add a rejoinder, and finalize dialogue. Every exchange
records actor, date/time, content, attachment versions, engagement, finding,
and response version. Auditee users cannot see uncommunicated findings.

### 5.7 Conferences

For an entry conference, schedule venue/online details, agenda, participants,
attendance, opening scope/criteria, and minutes. Link the conference to the
engagement and create tasks/notifications for missing attendance or minutes.

For an exit conference, select in-scope findings, record participants and
attendance, discuss each finding, capture agreement/disagreement, revised target
dates, minutes, attachments, and auditee acknowledgement. A disagreement is a
recorded outcome, not an automatic dismissal.

### 5.8 Reporting and distribution

Audit Reporting Workspace stages:

    Interim Report (if used) -> Draft Report -> Pending Review -> Returned -> Approved
    Final Report: Draft -> Pending Review -> Returned -> Approved -> Issued

1. Assemble interim/draft reports from selected traceable sources. Arrange
   sections, add executive summary, reviewer comments, and quality checklist.
2. A Final Report selects finalized findings only. Preserve direct Issue, WP,
   Evidence, Fieldwork, and Interim source links and exact approved versions.
3. A reviewer returns with comments or approves. Record approving authority,
   IAU Head/LCE/signatory decision, signatory matrix, transmittal, recipients,
   delivery, and acknowledgement.
4. Issue the report. The PDF/document version, checksum, confidentiality,
   file version, issuance date, and distribution decisions become immutable.
   Corrections create amendment, withdrawal, or superseding report versions.
5. Use protected PDF/register export routes. Repeated generation with the same
   scope, source versions, and parameters is reproducible and auditable.

### 5.9 Completion, CMS transfer, closure, and reopening

Completion is an operational state; closure is an administrative/legal state.

1. Complete the assessment/checklist: AEO/AEP, approved program, completed
   procedures, approved WPs, assessed evidence, finalized findings, issued
   report, distribution/acknowledgements, actual days and ARMIS reconciliation,
   open tasks/review notes, retention/index, and lessons learned.
2. Resolve every blocker. Missing evidence, unapproved WP, unfinalized finding,
   unresolved report version, required distribution, open task, stale ARMIS
   reconciliation, or unapproved retention record keeps the engagement unready.
3. Prepare the CMS transfer manifest. Select finalized recommendations only and
   inspect immutable snapshot, issued source report/version/checksum, office,
   and transfer key.
4. Confirm once. The API creates an immutable CMS intake envelope and
   reconciliation record. A repeated request returns the existing result or a
   duplicate conflict; it never creates a second case.
5. Resolve transfer exceptions, record included/excluded recommendations and
   provenance, then request closure review. CMS owns Action Plans, monitoring,
   validation, dispositions, reopening, and closure; AEMS retains provenance.
6. Complete retention/records, archive/disposition, legal-hold, lessons learned,
   and closure approval. Closure preserves a decision.
7. Reopening requires an authorized reason, independent review, and a new
   immutable reopening decision. The original closure/disposition remains.

### 5.10 AEMS work queues and operational outputs

Tasks, Review Notes, Due Process items, and Escalation Candidates are
operational records, not dashboard-only counters. From Operational Work Queues
a permitted user can create, assign, update, complete, cancel, or reopen a
task; draft, revise, or finalize a review note; record a reminder or a
no-response event; and review, resolve, or dismiss an escalation candidate.
Each action is engagement/office scoped, checks separation of duties, preserves
attachments and due dates, and updates notifications. Audit Calendar is the
date-oriented view of the same milestones; it does not approve the underlying
record.

## 6. Compliance Management System (CMS)

CMS receives finalized AEMS recommendations and manages corrective action. It
does not edit AEMS findings or decide whether an audit finding was valid.

### 6.1 CMS pages

| Page | Function |
| --- | --- |
| CMS Dashboard | Scope-aware case counts, overdue work, response/validation/closure queues |
| AEO Acknowledgements (`/compliance-management/aeo-acknowledgements`) | Recipient-scoped issued AEO copies, protected approved-PDF download, and auditee office/user acknowledgement |
| Recommendation Registry/Detail | Search immutable intake, assignment, provenance, status, and actions |
| Action Plans | Draft/submit/review/accept plans, actions, personnel, target dates |
| Progress Updates | Periodic evidence, claimed completion, comments, attachments, review |
| Validation | Independent validation assessment, evidence, return, final validation |
| Target-Date Extensions | Request, justify, review, approve/reject, preserve history |
| Escalations | Review candidates, prepare/issue notices by an authorized actor |
| Closure | Readiness, closure review/approval, formal closure, history |
| Accepted Risk / No Longer Applicable | Independent review and separate final decision |
| Controlled Reopening | Preserve original closure/disposition; new immutable reopening decision |
| Automation & Candidates | Configure reminders/criteria; inspect candidates/drafts |
| Reports & Exports | Scope/confidentiality-aware reproducible reports and protected CSV/PDF |

### 6.2 CMS intake and case lifecycle

1. AEMS sends a finalized-recommendation manifest. Verify source report,
   recommendation snapshot, checksum, responsible office, and idempotency key.
2. CMS creates one immutable intake case. Source links and snapshot remain.
3. Assign the case to an authorized office/monitor within office scope.
4. The case normally progresses:

    Transferred/Registered -> Action Plan Required -> Action Plan Submitted
    -> Under Monitoring -> Validation -> Closure Review -> Closed

The current case status and availableActions response are authoritative.

### 6.3 Management action and validation

1. The responsible office creates an Action Plan with corrective action,
   personnel, resources, target date, and supporting documents.
2. Submit. The monitor accepts, returns, or requests clarification. The submitter
   cannot perform the independent approval.
3. Add Progress Updates with period, result, evidence, blockers, and attachments.
4. A validator independently checks implementation evidence against criteria and
   may accept, partially accept, reject, or request clarification as allowed.
5. Request an extension with reason before/with the documented deadline.
   Independent authority approves/rejects and history preserves old/new dates.

### 6.4 Dispositions and reopening

- Accepted Risk means residual risk is consciously accepted. It is not
  implementation and not ordinary closure. Require rationale, evidence,
  independent review, and a separate final decision.
- No Longer Applicable means the condition or mandate changed. It is not
  implementation and not ordinary closure. Require factual basis, evidence,
  independent review, and separate final decision.
- Closure means the approved CMS closure workflow found its requirements met.
- Reopening preserves the original decision and creates a new immutable decision.

### 6.5 Automation and exports

Automation may identify overdue work, send reminders, create escalation or
closure-readiness candidates, and prepare drafts. It must not close a case,
accept risk, set a final disposition, issue an escalation notice, or approve
evidence. Candidates always require a human decision.

CSV output is formula-injection-safe. PDF/CSV downloads are authenticated,
scope-aware, confidentiality-aware, rate-limited, and checksum-preserved.

## 7. Audit Resource Management (ARMIS)

ARMIS is the sole operational resource provider. The only active provider mode
is `ARMIS_AUTHORITATIVE`; IAP resource values and historical fallback/shadow
records are retained only for planning lineage or historical reconciliation and
cannot be selected as a live provider.

### 7.1 ARMIS pages

| Page | Function |
| --- | --- |
| Resource Registry | User-linked profile, office, lifecycle, archive/restore, history |
| Competencies & Certifications | Competency, specialization, certification evidence, independent verification |
| Planning & Utilization | Availability, leave/training, capacity, workload, utilization, revisions |
| Assignments & Actuals | Engagement assignments, competency, planned/actual days, conflicts, approvals |
| Provider Monitoring | Verify the current ARMIS resource ledger, read path, and health notifications |
| Reports & Administration | Immutable resource reports, protected exports, status/preflight |

### 7.2 Resource and assignment lifecycle

1. Create a resource profile linked to a Core user and office. Add competencies
   and certifications with exact Core document evidence.
2. Enter availability, leave/training, capacity, and workload. Submit for
   independent review; returned data is revised, not overwritten.
3. Create an engagement assignment with requirements, planned days, actual-day
   ledger, objectivity, and independence declarations.
4. Run competency, capacity, overlap, office, and independence checks. A
   mandatory failure blocks approval.
5. Submit assignment/actuals. Independent reviewer approves or returns; approved
   records can be locked. Corrections create revisions.
6. Use Provider Monitoring for current ARMIS health and read-path checks. A
   historical provider comparison is not required to approve an assignment.
7. Resolve missing or stale ARMIS resource records before approval. ARMIS is
   permanently authoritative for operational resource data; there is no IAP
   fallback or provider rollback action.

## 8. Audit Intelligence System (AIS)

AIS is a read-only analytical and integration-health surface. It consumes
scope-aware adapters from Core, IAP, AEMS, CMS, and ARMIS; it does not own or
mutate source records.

### 8.1 AIS pages

- AIS Dashboard: cross-module counts, freshness, confidentiality, and review
  indicators.
- Integration Health: source contract status, stale/missing data,
  reconciliation diagnostics, fail-closed exceptions, and authoritative links.
- AIS Reports & Exports: immutable analytical snapshots and protected CSV/PDF.

### 8.2 AIS operating rules

1. Authenticate and scope each source query.
2. Validate availability, lineage, confidentiality, freshness, and
   reconciliation.
3. Fail closed for unavailable, stale, out-of-scope, or invalid sources.
4. Store actor, scope, source versions/checksums, timestamp, diagnostics, and
   output hash in an immutable snapshot.
5. Correct a source issue in its owner module; AIS has no write-back action.

## 9. Cross-module ownership and transfer contracts

| Boundary | Source owner | Receiving behavior | Prohibited behavior |
| --- | --- | --- | --- |
| Core -> all | Identity, registries, roles, documents, workflows, logs, notifications, numbering | Modules reference shared IDs/services | Duplicate user/office/permission/document ownership tables |
| IAP -> AEMS | Approved Annual Audit Plan and risk/area/focus lineage | AEMS stores immutable import snapshot and source IDs | Mutating approved IAP rows from AEMS |
| ARMIS -> AEMS | Competency, availability, workload, planned/actual days when authoritative | AEMS reads ResourcePlanningGateway, shows provider status, blocks stale data | Unreviewed copy or automatic approval |
| IAP -> ARMIS | Historical resource lineage only | ARMIS owns current resource data; AEMS reads ARMIS directly | IAP fallback reads, provider switching, or reconciliation write-back |
| AEMS -> CMS | Finalized recommendation in exact issued report | CMS creates one immutable case/manifest | Draft/unfinalized or duplicate transfer |
| Core/IAP/AEMS/CMS/ARMIS -> AIS | Read contracts | AIS presents scoped snapshots/diagnostics | AIS mutation or professional decision |

AEMS and ARMIS assignment ledgers are reconciled, not automatically merged.
Imported IAP snapshots are not silently resynced. CMS monitoring/closure results
do not rewrite AEMS. Any bidirectional contract needs a new governance decision,
versioned events, migration/backfill, and separation-of-duties tests.

## 10. Administration and support

### 10.1 Seed local/demo data

Run migrations and seeders from backend with the configured database. The
role/permission seeder creates compatibility and current module permissions.
Never enable demo credentials in production.

### 10.2 Safe migration and deployment

1. Back up PostgreSQL and private storage.
2. Review migration SQL for unique indexes, foreign keys, soft deletes, and
   immutable/version constraints.
3. Run php artisan migrate --force.
4. Run a fresh database rehearsal, seed reference data, and verify role/permission
   counts.
5. Build the SPA with npm.cmd run build and serve compiled assets with Laravel
   fallback.
6. Verify private storage, HTTPS, secure cookies, mail, queue/cron, rate limits,
   and protected downloads.
7. Verify health/runtime configuration, sign in with a non-admin role, and inspect
   Activity Log/Audit Trail before release.

### 10.3 Common blocked actions

- Approve unavailable: wrong status, self-review, missing prerequisite, or scope.
- Start fieldwork unavailable: AEO/AEP/program/planning/team gate incomplete.
- Use evidence unavailable: negative/partial/not-assessed, restricted without
  exception, or unresolved gap/limitation.
- Validate/finalize finding unavailable: required fields, evidence, independent
  reviewer, communication, or dialogue incomplete.
- Issue report unavailable: non-final finding, authority/signatory/transmittal,
  checksum, or confidentiality missing.
- Transfer unavailable: non-final recommendation, non-issued source, incomplete
  manifest, or prior transfer.
- Close unavailable: open checklist/task/records/retention/ARMIS/report/finding/
  transfer blocker.

## 11. As-built boundaries and future decisions

- Legacy AEMS compatibility metadata may label AIS out-of-scope even though
  AIS-5D is implemented read-only; operational writes remain disabled.
- AEMS and ARMIS assignment ledgers are reconciled, not automatically merged.
- IAP snapshots require an explicit resync decision for later plan revisions.
- CMS does not push monitoring/closure outcomes back into AEMS.
- Historical checkpoint text in phase documents is not a current inventory.
- New authority/signatory, retention, evidence, risk, distribution, or
  bidirectional-integration policy requires governance approval before code.

## 11.1 Permission family reference

The seeded permission catalogue is the exact enforcement contract; the table
below groups the actions so a reviewer can understand the least-privilege
model. The complete current list remains in RolePermissionSeeder.php and is
returned in the authenticated user contract.

| Module | Permission families and examples |
| --- | --- |
| Core | dashboard, offices, audit_areas, audit_focus, users, roles, permissions, master_lists, documents, workflows, notifications, activity/audit, system configuration, reports |
| IAP | iap.view/create/update, assess_risk, manage_universe, manage_engagements, assign_team, submit, review, approve, activate, complete, create_revision, archive, restore, export |
| AEMS foundation | aems.engagement, foundation, team, aeo, aep, program, fieldwork, planning-package |
| AEMS evidence | aems.working-paper, evidence, evidence-request (upload/verify/assess/outcome/link_report, receive/extend/escalate/cancel/close) |
| AEMS issues/AFR | aems.issue (validate/convert/merge/resolve/observe/refer/close_without_finding/withdraw), afr (transmit/delivery/acknowledge), finding (review/validate/communicate/finalize/revise) |
| AEMS dialogue/operations | management-response, rejoinder, review-note, task, due-process, escalation-candidate, conference, entry-conference |
| AEMS reporting/closure | report (create/review/distribute/authority/signatory/transmit/amend/withdraw/supersede/export/close_admin), completion-assessment, completion-transfer, closure, document-index, retention, records, calendar, engagement.reopen_request |
| CMS | dashboard, recommendation, action-plan, progress, evidence, validation, validation-evidence, extension, extension-evidence, escalation, escalation-evidence, closure, closure-evidence, disposition, disposition-evidence, reopening, reopening-evidence, automation, report |
| ARMIS | resource, competency, availability, capacity, workload, assignment, actuals, provider, report, plus preserved arms.view/arms.manage compatibility permissions |
| AIS | ais.view and ais.export compatibility/current read-only reporting permissions; source ownership is never granted through AIS |

The action suffix is meaningful: view/read is not create/update; submit is not
review; review is not approve; export is not a data mutation; and an auditee's
response permission does not grant finding validation/finalization.

## 12. Verification map

- Routes/page inventory: src/App.jsx and src/config/navigation.js.
- API/permission contract: backend/routes/api.php, RolePermissionSeeder.php,
  and API_AND_DATA_REFERENCE.md.
- Rules: backend/app/Services, Form Requests, and workflow documents.
- Data ownership/versioning: backend/app/Models and migrations.
- Regression evidence: backend/tests/Feature and tests/e2e.
- Operations: OPERATIONS_GUIDE.md and RENDER_DEPLOYMENT.md.
