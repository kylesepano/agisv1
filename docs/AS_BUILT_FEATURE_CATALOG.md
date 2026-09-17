# AGIS As-Built Feature Catalog

**Purpose:** a comparison-ready inventory of the features, user workspaces,
workflow actions, access controls, integrations, and verification evidence that
exist in the repository today.

**Authority:** this catalog describes the source code as built. When this file
differs from a design document, the implementation sources listed in section 3
win. A feature marked **Not implemented** is deliberately not implied by a
placeholder route or a navigation card.

**Last reviewed:** 24 August 2026.

For a user-oriented explanation of every page and action, read the [AGIS
As-Built System Manual](AGIS_AS_BUILT_SYSTEM_MANUAL.md). For executable
step-by-step procedures (including return, decline, transfer, disposition,
reopening, and closure), read [AGIS Operational Playbooks](AGIS_OPERATIONAL_PLAYBOOKS.md).

## 1. How to compare a requirement with the implementation

For each requested feature, check the following columns in this document:

1. **User surface** — the canonical sidebar route or contextual workspace.
2. **Backend contract** — the API family/controller and persisted record.
3. **Workflow and controls** — state transitions, permissions, scope,
   separation-of-duties, locking, evidence, notifications, and audit events.
4. **Verification** — the Feature or Playwright test that protects the
   behavior.
5. **Boundary** — whether another module owns the decision or supplies data.

“Implemented” means a usable UI/API path exists and is protected by the
backend. “Partial” means a documented target exists but one or more required
actions, fields, or controls are absent. “Reference only” means a route or
identifier is retained for compatibility but no operational feature is
claimed.

## 2. Module summary

| Module | As-built status | Primary source of truth | Current boundary |
| --- | --- | --- | --- |
| Core | Implemented | `src/config/navigation.js`, Core controllers/services/models | Owns identity, office/area/focus registries, roles/scopes, documents, workflows, notifications, logs, configuration, and numbering |
| IAP | Implemented | `IAP_WORKFLOW_DESIGN.md`, IAP controllers/services/models | Owns strategic and annual planning; AEMS consumes approved engagement-plan lineage read-only |
| AEMS | Implemented through G10E acceptance | `AEMS_WORKFLOW_DESIGN.md`, `AEMS_GOVERNANCE_AND_ACCEPTANCE.md`, `AEMS_CROSS_MODULE_INTEGRATION.md` | Owns engagement execution, findings, recommendations, reports, transfer provenance, completion, and closure |
| CMS | Implemented through CMS-12B | `CMS_WORKFLOW_DESIGN.md`, CMS controllers/services/models | Owns post-issuance Action Plans, monitoring, validation, dispositions, reopening, closure, reports, and exports |
| ARMIS | Implemented through ARMIS-7C | `ARMIS_WORKFLOW_DESIGN.md`, ARMIS controllers/services/models | Owns resource and allocation ledgers; AEMS consumes the configured provider boundary |
| AFR standalone module | Reference only | `src/config/navigation.js` and route registry | Findings and Recommendations are owned by AEMS; no separate AFR business workflow is claimed |
| AIS | AIS-5D verified read-only analytical reporting and source-health workspace | `AIS_GOVERNANCE_CONTRACT.md`, `AisContractService`, `AisIntegrationContractService`, `AisIntegrationHealthService`, `AisIntegrationSnapshot`, `AisAggregationService`, `AisReportService`, `AisAuditService`, `AisReportRun`, `AisReportExport`, `AisGovernancePage`, `AisIntegrationHealthPage`, `AisReportsPage` | Scope-aware metrics, Core/IAP/AEMS/CMS/ARMIS source adapters, per-source freshness/reconciliation diagnostics, fail-closed aggregation/reporting, immutable integration-health snapshots, responsive source-health cards, scope/confidentiality indicators, authoritative-module links, analytical dashboard, review-only alerts, immutable reports, protected CSV/PDF exports, checksums, private responses, named rate limits, read audit events, and AIS-5D role/scope/confidentiality/lineage/no-write verification; operational writes, professional decisions, and duplicate ownership tables remain disabled |

## 3. Implementation authorities

| Concern | Authoritative source |
| --- | --- |
| Frontend routes and sidebar permissions | `src/App.jsx`, `src/config/navigation.js` |
| API routes and middleware permissions | `backend/routes/api.php` |
| Request validation | `backend/app/Http/Requests` |
| Business transitions and separation of duties | `backend/app/Services`, policies, and workflow classes |
| Persistence and relationships | `backend/app/Models`, migrations, factories, seeders |
| Core documents and protected downloads | Core document models/services and `document_versions` |
| Activity and audit records | Core activity/audit services and event listeners |
| Automated verification | `backend/tests/Feature` and `tests/e2e` |

Module source layout:

```text
src/pages/core/    Core registries, dashboard, administration, profile
src/pages/iap/     Internal Audit Planning pages
src/pages/aems/    Audit Engagement Management pages
src/pages/cms/     Compliance Management pages
src/pages/armis/   Audit Resource Management pages
src/pages/shared/  Login, generic module, and unauthorized pages

backend/app/Models/Core/    Core identity, scope, documents, workflow, and log models
backend/app/Models/Iap/     Internal Audit Planning and BAICS models
backend/app/Models/Aems/    Audit Engagement Management models
backend/app/Models/Cms/     Compliance Management models
backend/app/Models/Armis/   Audit Resource Management models
backend/app/Models/Ais/     Audit Intelligence System models

backend/app/Http/Controllers/Api/Core/
backend/app/Http/Controllers/Api/Iap/
backend/app/Http/Controllers/Api/Aems/
backend/app/Http/Controllers/Api/Cms/
backend/app/Http/Controllers/Api/Armis/
backend/app/Http/Controllers/Api/Shared/

backend/app/Http/Requests/Core/       Core request validation
backend/app/Http/Requests/Iap/        IAP request validation
backend/app/Http/Requests/Aems/       AEMS request validation
backend/app/Http/Requests/Cms/        CMS request validation
backend/app/Http/Requests/Auth/       Shared authentication requests

backend/app/Services/Core/    Core services
backend/app/Services/Iap/     IAP and BAICS services
backend/app/Services/Aems/    AEMS services
backend/app/Services/Cms/     CMS services
backend/app/Services/Armis/   ARMIS services
backend/app/Services/Ais/     AIS services

Model and service classes retain their existing `App\Models` and
`App\Services` namespaces for compatibility. Composer uses explicit module
directory mappings, so moving a class into a module folder does not change
imports, route payloads, or persisted relationships.
```

Backend controller and request namespaces match these folders; route URLs and
API payloads are unchanged by this organizational layout.

Detailed endpoint payloads are maintained in [API and Data Reference](API_AND_DATA_REFERENCE.md). The workflow documents explain rationale and sequence; this catalog is the cross-module comparison index.

## 4. AGIS Core

### 4.1 User-facing workspaces

| Workspace | Route | Functions | Status |
| --- | --- | --- | --- |
| Dashboard | `/dashboard` | Live scope-aware module cards, status totals, overdue recommendations, recent engagements, tasks, quick actions, and safe loading/error fallback | Implemented live |
| Office Registry | `/office-registry` | Create, edit, activate/archive/restore, office scope and related records | Implemented |
| Audit Area Registry | `/audit-area-registry` | Maintain reusable audit areas and scope relationships | Implemented |
| Audit Focus Registry | `/audit-focus-registry` | Maintain audit focuses and area relationships | Implemented |
| User Registry | `/user-registry` | User accounts, offices, roles, active state, reset/deactivation controls | Implemented |
| Access Role Registry | `/access-role-registry` | Roles, permission assignment, scope configuration, separation checks | Implemented |
| Permission Registry | `/permission-registry` | Permission catalogue and compatibility aliases | Implemented |
| Master Lists | `/master-lists` | Configurable status/types/list values used by modules | Implemented |
| Document Management | `/document-management` | Core document metadata, versions, confidentiality, checksum, protected access | Implemented |
| Notifications | `/notifications` | Read/unread state, notification history, action links, mark-read actions | Implemented |
| Workflow Management | `/workflow-management` | Workflow definitions, transitions, reviewer/approval configuration | Implemented |
| Activity Log | `/activity-log` | User/system activity search and detail | Implemented |
| Audit Trail | `/audit-trail` | Security and record-change history, immutable event context | Implemented |
| System Configuration | `/system-configuration` | Runtime settings, numbering formats, provider configuration, feature settings | Implemented |
| Administrative Reports | `/administrative-reports` | Immutable scope-pinned office, user-access, workflow, and activity snapshots with protected CSV/PDF exports and checksums | Implemented live |

### 4.2 Core platform functions

- Sanctum authentication, login/logout, demo-account discovery, session and
  runtime-configuration endpoints.
- Office, user, role, permission, area, focus, and master-list scope checks.
- Soft deletion/restoration where the record contract permits it.
- Optimistic locking for mutable records and immutable version snapshots for
  controlled submissions/approvals.
- Core `document_versions` for file checksum, size, MIME type, custody,
  confidentiality, version lineage, and authenticated protected downloads.
- Notifications, Activity Log, Audit Trail, workflow events, runtime
  configuration, and configurable numbering shared by IAP, AEMS, CMS, and
  ARMIS.

Protected API behavior is covered by `CoreModuleTest`, `AuthenticationTest`,
`AccessRoleScopeTest`, `DocumentManagementTest`, `ActivityAuditTrailTest`,
`NotificationCenterTest`, `WorkflowManagementTest`, and related registry tests.

## 5. Internal Audit Planning (IAP)

### 5.1 Workspaces and functions

| Workspace | Canonical route | Implemented functions |
| --- | --- | --- |
| IAP Dashboard | `/internal-audit-planning/dashboard` | Live planning, risk, prioritization, annual-plan, schedule, approval, and capacity aggregates |
| Strategic Audit Plan | `/internal-audit-planning/strategic-plan` | Multi-year objectives/themes, area links, draft/review/return/resubmit/approve/activate/complete, revision and immutable approved versions |
| Audit Universe | `/internal-audit-planning/audit-universe` | Auditable subjects, office/area ownership, classification, rationale, risk-period links, history and scope |
| Baseline Assessment (BAICS) | `/internal-audit-planning/baics`, `/internal-audit-planning/baics/control-universe`, and `/internal-audit-planning/baics/integration` | BAICS-1A/1B through BAICS-6: five component assessments, distinct methods, independent review, exact Core Document Version evidence, corroboration exceptions, traceable Control Universe, interim analysis, BAR assembly, approval, immutable histories, protected PDF/CSV exports, approved BAR/legacy IAP-consumer decisions, granular integration permissions, participant eligibility and scoped deduplicated workflow notifications, role/scope/SoD conformance, migration rehearsal, shared-owner checks, and canonical navigation verification |
| Risk Assessment | `/internal-audit-planning/risk-assessment` | Risk periods, assessment records, scoring, validation, approval/lock, history; legacy and universe risk systems coexist |
| Audit Prioritization | `/internal-audit-planning/prioritization` | Prioritization runs, scoring/weights, selected subjects, final decisions, locking and history |
| Annual Audit Plan | `/internal-audit-planning` | Annual plan engagements, IAP lineage, team/capacity inputs, draft/review/return/resubmit/approve/activate/complete, revisions and supporting records |
| Audit Scheduling | `/internal-audit-planning/scheduling` | Schedule windows, assignment conflicts, due dates, status, calendar views and changes |
| Resource Capacity | `/audit-resource-management/planning` | ARMIS-owned capacity, availability, competencies, workload and conflict inputs consumed by IAP |
| IAP Reports | `/internal-audit-planning/reports` | Scope-aware planning reports and exports |

### 5.2 IAP controls and boundary

- IAP action permissions include view, create, update, risk assessment,
  engagement management, assign team, submit, review, approve, activate,
  complete, revise, archive, restore, and export.
- Controlled records use draft → review → return/resubmit → approve and,
  where applicable, activate/complete. Approved/active/completed versions are
  immutable; revisions preserve prior lineage.
- The two risk systems are intentionally retained:
  `iap_risk_assessments` and `iap_universe_risk_assessments`. They must not be
  silently merged or removed.
- IAP is the source of approved engagement-plan lineage. AEMS may import an
  approved source once, record source identifiers, and add engagement-specific
  attributes; it must not mutate the IAP source.
- BAICS-1A/1B through BAICS-6 are implemented as an IAP capability between
  Audit Universe and Risk Assessment. The five components, distinct method
  records, exact Core evidence links, corroboration exceptions and readiness
  gates are operational. Control Universe, BAR and the approved risk-consumption
  ledger, integration permission aliases and participant-scoped notifications
  are operational. BAICS-6 adds the final role/scope/SoD, migration, ownership
  and navigation conformance checks. See [BAICS Governance Contract](BAICS_GOVERNANCE_CONTRACT.md).

Protected backend coverage includes `IapFoundationTest`, `IapWorkflowTest`,
`IapAuditUniverseTest`, `IapRiskPeriodWorkflowTest`,
`IapPlanPrioritizationTest`, `IapPrioritizationWorkflowTest`,
`IapSchedulingTest`, `IapResourceCapacityTest`, `IapSupportingRecordsTest`,
`IapReportsTest`, `IapDashboardTest`, `IapBaicsFoundationTest`,
`IapBaicsControlAssessmentTest`, `IapBaicsControlUniverseTest`, and
`IapBaicsIntegrationTest`, plus `IapBaicsG6AcceptanceTest` for the final
role/scope/SoD, schema, ownership, and navigation conformance contract.

## 6. Audit Engagement Management (AEMS)

### 6.1 Canonical sidebar workspaces

| Screen | Route | SCR / permission | Main functions |
| --- | --- | --- | --- |
| AEMS Dashboard | `/audit-engagement-management/dashboard` | Portfolio / `aems.engagement.view` | Scope-aware phase cards, overdue procedures, WP review, evidence gaps, findings, responses, conferences, reports, transfer exceptions, closure readiness, queues, and export actions |
| Audit Engagement Workspace | `/audit-engagement-management` | SCR-210 / `aems.engagement.view` | IAP import or special engagement creation (both start as Draft), editable foundation metadata before authorization/planning, dedicated Engagement Scope transaction, one-office scope, filters, status, archive/restore, detail launch |
| Audit Scope | `/audit-engagement-management/scope` | Foundation / `aems.foundation.view` | Select an engagement, then maintain its SCR-212 office, Area/Focus coverage, boundaries, limitations, and source variance before team assignment |
| Audit Team | `/audit-engagement-management/team` | SCR-213 / `aems.team.view` | Assign members, roles, competencies, availability, workload, objectivity/independence, conflicts, ARMIS provider status, person-days and approval |
| Engagement Orders | `/audit-engagement-management/aeo` | SCR-214 / `aems.aeo.view` | AEO preparation, signatures, review, approval, issue, distribution, transmittal, acknowledgement, amendment, supersession, cancel/void |
| Planning Workspace | `/audit-engagement-management/planning-package` | SCR-221 / `aems.planning-package.view` | Preliminary survey, process-flow documentation, AEP + KPIs, risk matrices/items, audit programs/procedures, objective/risk/procedure/WP traceability, readiness, review, approval, return and immutable baseline; locked until ENGAGEMENT_PLANNING |
| Engagement Plan | `/audit-engagement-management/aep` | SCR-222 / `aems.aep.view` | Scope/objectives, criteria, period, resources, procedures, communication/effectivity, draft/review/return/approve/issue/revise |
| Audit Program | `/audit-engagement-management/audit-program` | SCR-223 / `aems.program.view` | Program/procedure register, areas/focuses, risks, methods, criteria, planned days, procedure execution state, reviewer notes and traceability |
| Audit Procedure Details | `/audit-engagement-management/audit-procedure-details` | SCR-224 / `aems.program.view` | Dedicated procedure-detail surface for process/risk lineage, responsible person, planned days, expected evidence, working-paper reference, due date, results, conclusion, review, and issue creation |
| Execution Workspace | `/audit-engagement-management/execution` | SCR-226 / `aems.fieldwork.view` | Fieldwork records, procedure execution, timeline, tasks/due dates, reviewer notes, WP/evidence links, blockers and create-issue action |
| Conference Management | `/audit-engagement-management/conferences` | SCR-225 / `aems.conference.view` | Single workspace for Entry and Exit timelines, schedules, venue/mode, agenda, participants, attendance, briefing paper, agreements/disagreements, findings discussed, waivers, revised dates, minutes, acknowledgements and dialogue history |
| Entry Conferences | `/audit-engagement-management/entry-conferences` | compatibility child / `aems.entry-conference.view` | Entry conference scheduling and records; surfaced below Conference Management and retained alongside the combined workspace |
| Exit Conferences | `/audit-engagement-management/exit-conferences` | compatibility child / `aems.conference.view` | Exit conference scheduling and records; surfaced below Conference Management and retained alongside the combined workspace |
| Working Papers & Evidence | `/audit-engagement-management/working-papers` | SCR-228 / `aems.working-paper.view` | WP index, objective/procedure/population/sample/results/conclusion, preparer/reviewer, cross-references, revisions, locked approvals and linked evidence |
| Evidence Management | `/audit-engagement-management/evidence` | SCR-229 / `aems.evidence-request.view` | Evidence requests, submission/receipt/assessment, custody/checksum/confidentiality, restrictions/gaps, versions and links to WP/fieldwork/issues/findings/reports |
| Audit Issues | `/audit-engagement-management/issues` | SCR-230 / `aems.issue.view` | Issue register, validation, dismissal, conversion, merge, referral, observation, resolution, withdrawal and terminal dispositions |
| Findings & Recommendations | `/audit-engagement-management/findings` | SCR-240 / `aems.finding.view` | Criteria, condition, cause, conclusion, effect, risk, evidence, responsible office, management response, rejoinder, recommendation, corrections, amendments, withdrawal, supersession and immutable finalization |
| Auditee Responses | `/audit-engagement-management/auditee-responses` | SCR-241 / `aems.management-response.view` | Formally communicated findings only; agree/partial/disagree, comments, corrective actions, owners, target dates, attachments, clarifications, extensions and response history |
| Audit Reporting Workspace | `/audit-engagement-management/reports` | SCR-250 / `aems.report.view` or `aems.report.view_issued` | Interim/draft/final assembly, section ordering, executive summary, quality review, finalized-finding selection, approval, issue, distribution, acknowledgements, amendment, withdrawal, supersession and protected PDF |
| Operational Work Queues | `/audit-engagement-management/work-queues` | operational / `aems.task.view` | Tasks, assignments, due/overdue state, review notes, due process, escalation candidates, notifications and controlled transitions |
| Audit Calendar | `/audit-engagement-management/calendar` | operational / `aems.calendar.view` | Milestones, owners, due/overdue indicators, completion and closure-related dates |
| Registers & Exports | `/audit-engagement-management/registers` | operational / `aems.engagement.view` | Engagement/progress/queue/document/report register surfaces and authenticated protected CSV/PDF actions |
| Records & Administrative Closure | `/audit-engagement-management/records-closure` | operational / closure/records/retention permissions | Completion assessment, blocker reconciliation, retention, archive/disposition, legal hold, destruction eligibility, records search, formal closure and controlled reopening |

### 6.2 AEMS lifecycle and professional controls

```text
IAP approved source / special authorization
  → engagement authorization and team safeguards
  → planning package and AEP approval
  → audit program and fieldwork execution
  → working papers and evidence assessment
  → issues → findings → management dialogue
  → entry/exit conferences and reporting
  → finalized recommendations → one-time CMS transfer
  → completion assessment → COMPLETED → records reconciliation → CLOSED
```

- Fieldwork is blocked until an approved, genuinely complete planning baseline
  exists. Required process-flow, risk, KPI, sampling, and planned-WP gates are
  evaluated by the backend rather than a frontend flag.
- Engagement creation starts as Draft without SCR-212 details. The dedicated
  Engagement Scope transaction must have exactly one office and at least one
  audit area before Audit Team assignment. Team mutation is locked in `DRAFT`
  and unlocks in `AUTHORIZATION_PREPARATION`; AEO and aggregate authorization
  gates then enforce team completeness before the engagement becomes
  `AUTHORIZED`.
- Evidence has a request lifecycle (Draft, Submitted, Sent, Acknowledged,
  For Review, Partially Received, Received, Assessed, Overdue, Extension,
  Escalated, Cancelled, or Closed Without Submission) and a separate technical
  evidence status/outcome. `LOCKED` is not professional acceptance.
- Negative, incomplete, restricted, unresolved-gap, or not-assessed evidence
  cannot support a validated/finalized finding unless an approved exception is
  recorded.
- A finding requires Conclusion. Finding authors cannot validate their own
  finding. Recommendations become immutable at finalization and transfer to
  CMS only once.
- AEO authority guidance is visible in the AEO workspace: the preparer submits,
  an assigned reviewer records review, an active `cias_management` account
  approves, and an active `cias_management` account issues. If the preparer is
  the sole active CIAS Head, that account may perform the complete review,
  approval, and issuance sequence as a controlled exception. Auditee office
  heads and the City Mayor receive/acknowledge issued AEOs through the
  recipient-scoped CMS portal; they are not internal AEO approvers or issuers.
  The matrix shows candidate accounts and the immutable actor, username,
  position, timestamp, and status once acted.
- When that active CIAS Head is the sole active CIAS Management authority, the
  same controlled exception permits her to review and approve her own AEMS
  inputs across planning, execution, findings, reporting, transfer, and
  closure. Readiness, evidence, immutable-version, status, and audit controls
  remain enforced, and the exception is unavailable when another active CIAS
  Management authority exists.
- Auditee access is restricted to findings formally communicated to the office.
  Every response/rejoinder/conference exchange records actor, timestamp,
  content, attachments, version, engagement, and linked finding/conference.
- Issued reports are immutable and reproducible from exact approved sources;
  corrections create new versions. `COMPLETED` is substantive completion;
  `CLOSED` is formal administrative closure and cannot bypass records blockers.
- AEMS consumes ARMIS through a provider boundary. `ARMIS_AUTHORITATIVE` is the
  only operational provider mode; historical IAP/fallback values are lineage
  or reconciliation evidence only and cannot satisfy live approval gates when
  ARMIS data is missing or stale. AEMS owns the recommendation and transfer
  provenance; CMS owns post-transfer compliance monitoring.
- Engagement details remain locked until the AEO is approved. Lifecycle remains
  available while detail workspaces are locked, and each locked workspace
  displays the phase and action that unlocks it.

### 6.3 AEMS contextual SCR inventory

The current registry contains 32 canonical SCR identifiers, including reserved
`SCR-243`. SCR-212 is available as the dedicated
`/audit-engagement-management/scope?engagementId=...` transaction and as the
Foundation Audit Scope selector. Process Flow and Risk Matrix are artifacts
inside SCR-221, not duplicate sidebar pages. SCR-224 is also available as the
Planning Audit Procedure Details surface. SCR-220 engagement tabs are
Overview, Planning, Execution, Audit Issues, AFRs, Conferences, Audit Reports,
Completion & Transfer, and Activity. Entry and Exit Conferences are surfaced
below Conference Management while remaining linked to the combined workspace.
See the registry in `src/config/navigation.js` and the semantic acceptance test
`AemsG10EAcceptanceTest`.

### 6.4 AEMS verification evidence

Backend coverage includes engagement lifecycle, foundation/scope, planning,
team/AEO, safeguards, fieldwork, working papers/evidence, evidence request
lifecycle, issues/findings/recommendations, conferences/dialogue, reports,
completion/closure, work queues, cross-module integration, G4–G10 governance,
and final acceptance tests. Frontend coverage includes shell, planning,
execution, evidence, issues/findings, conferences/dialogue, reporting,
responsive navigation, G9/G10 conformance, operational queues, and records/
closure specs. The current acceptance record is
[AEMS Governance and Acceptance](AEMS_GOVERNANCE_AND_ACCEPTANCE.md).

## 7. Compliance Management (CMS)

### 7.1 Sidebar workspaces

| Workspace | Route | Functions |
| --- | --- | --- |
| CMS Dashboard | `/compliance-management/dashboard` | Scope-aware cases, phases, overdue responses, validation, extensions, escalations, closure readiness and candidate monitoring |
| Recommendation Registry | `/compliance-management/recommendations` | Immutable AEMS intake registry, search/filter, office/risk/status/assignment views and detail launch |
| AEO Acknowledgements | `/compliance-management/aeo-acknowledgements` | Recipient-scoped issued AEO transmittals; auditee users inspect the immutable issued AEO details, download its protected approved PDF, and acknowledge copies addressed to their account or office without internal AEMS access |
| Automation & Candidates | `/compliance-management/automation` | Configurable reminders/rules, run history, closure-readiness candidates, escalation candidates, administrative review; no automatic final decisions |
| Reports & Exports | `/compliance-management/reports` | Backend-generated scope/confidentiality-aware reports, protected CSV/PDF exports, reproducible runs and authenticated downloads |

### 7.2 CMS business functions

- Immutable intake from an issued AEMS report, one case per recommendation,
  source snapshot, responsible-office snapshot, transfer provenance and
  idempotent intake.
- Compliance Monitor assignment with current-assignment uniqueness,
  assignment history and separation from owner/preparer where required.
- Corrective Action Plans with immutable versions, milestones, measurable
  indicators, responsible personnel, review/return/acceptance and controlled
  revision.
- Progress Updates with reporting periods, accepted-baseline milestone
  comparison, exact Core Document Version evidence and immutable revisions.
- Independent Validation with validator assignment, procedures, evidence
  assessment, conclusions, supervisory review, return and finalization.
- Target-date extension requests, escalation notices/responses, formal closure,
  Accepted-Risk and No-Longer-Applicable dispositions, and controlled
  reopening. Original closure/disposition decisions remain preserved.
- Automation may generate reminders, readiness signals, candidates, or drafts;
  it cannot close, dispose, reopen, or issue a notice automatically.
- Recommendations remain editable until finalization; finalized snapshots are
  immutable and are not transferred more than once.

CMS backend tests are `CmsIntakeTest`, `CmsRecommendationApiTest`,
`CmsActionPlanTest`, `CmsProgressUpdateTest`, `CmsValidationTest`,
`CmsTargetDateExtensionTest`, `CmsEscalationTest`, `CmsClosureTest`,
`CmsAutomationTest`, `CmsReopeningTest`, and `CmsReportTest`; matching React
specs are under `tests/e2e/cms-*.spec.js`.

## 8. Audit Resource Management (ARMIS)

### 8.1 Workspaces

| Workspace | Route | Functions |
| --- | --- | --- |
| Resource Registry | `/audit-resource-management/resources` | Resource profiles linked to Core users/offices, lifecycle, scope, archive/restore |
| Competencies & Certifications | `/audit-resource-management/competencies` | Competency catalogue, certifications, specializations, evidence and expiry |
| Planning & Utilization | `/audit-resource-management/planning` | Availability, capacity, workload allocations, planned person-days and utilization |
| Assignments & Actuals | `/audit-resource-management/assignments` | Engagement assignments, required competencies, planned/actual days, conflicts and approvals |
| Provider Monitoring | `/audit-resource-management/provider-monitoring` | Current ARMIS provider health, read-path checks, notifications and protected history |
| Reports & Administration | `/audit-resource-management/reports` | Scope-pinned reports, CSV/PDF exports, administration status and protected downloads |

### 8.2 ARMIS controls and provider boundary

- ARMIS owns resource, competency, availability, assignment, actual-person-day,
  provider monitoring, and report records.
- AEMS reads ARMIS `ResourcePlanningGateway` data. `ARMIS_AUTHORITATIVE` is the
  only operational mode. Historical `ARMIS_SHADOW` and
  `IAP_INTERIM_FALLBACK` values remain only in lineage/reconciliation snapshots
  and are not runtime options or active providers.
- IAP interim resource tables remain only for historical compatibility and
  lineage. They are backfilled into ARMIS by `ArmisResourceBackfillService`,
  are no longer current write sources, and are not read by AEMS readiness.
- The former ARMIS provider-reconciliation route is retained only as a
  compatibility redirect to Provider Monitoring. Historical comparison tables
  and APIs are not an operational gate and cannot switch ownership back to IAP.
- ARMIS assignments enforce competency, availability, capacity, leave/training,
  conflict-of-interest, independence, planned/actual reconciliation, and
  optimistic locking rules.
- The demo/reference roster is seeded idempotently by
  `ArmisResourceProfileSeeder`: four auditors, five CIAS administrative/support
  staff, one CIAS head, and one AGIS project-manager consultant (eleven ARMIS
  resource profiles). Core users remain the identity source; ARMIS stores the
  resource profile and category only. The historical `cias.employee` login is
  retained as a non-roster compatibility alias and has no ARMIS profile.

ARMIS backend tests cover foundation, competency, planning, assignments,
reports, provider adapter, reconciliation, monitoring, security, and deployment
hardening; React specs are `tests/e2e/armis-*.spec.js`.

## 9. Shared functions and integration ownership

| Function | Owner | Consumers/behavior |
| --- | --- | --- |
| Users, offices, roles, permissions, scopes | Core | All modules; every API repeats backend scope checks |
| Audit areas and focuses | Core | IAP planning; AEMS engagement scope and traceability; ARMIS office/resource scope |
| Documents and immutable versions | Core | Evidence, WP support, CMS progress/validation/disposition evidence, reports and exports |
| Workflow engine and transitions | Core plus module services | Module-specific statuses/actions; final authority is backend service/policy |
| Notifications | Core | AEMS/CMS/ARMIS assignments, reviews, overdue states, escalations and approvals |
| Activity Log and Audit Trail | Core | Every material mutation, transition, download, assignment and authority decision |
| Runtime configuration and numbering | Core | Module codes, report/export metadata, engagement/AEO/program numbering |
| IAP lineage | IAP | AEMS imports approved plans once and preserves source references |
| Resource provider | ARMIS boundary | ARMIS is the sole operational provider; historical fallback/shadow values are lineage-only |
| Recommendation transfer | AEMS | Finalized recommendation snapshot is sent to CMS once |
| Compliance monitoring and closure | CMS | CMS owns Action Plans, monitoring, validation, dispositions and post-transfer closure |
| AIS | Governance, analytics, protected reporting, and read-only source integration | `/audit-intelligence-system`, `/audit-intelligence-system/integration-health`, `/audit-intelligence-system/reports`, `/api/ais/contract`, `/api/ais/hardening`, `/api/ais/integration-contract`, `/api/ais/integration-health`, `/api/ais/integration-health/snapshots`, `/api/ais/aggregations`, `/api/ais/dashboard`, `/api/ais/reports` | AIS-1 through AIS-5D implemented and verified; AIS remains read-only |

## 10. Documented exceptions and compatibility items

- AEMS uses `AEMS` and `aems.*` identifiers for compatibility while the display
  name is Audit Engagement Management.
- Legacy `aem.*` compatibility permissions are preserved.
- `iap_risk_assessments` and `iap_universe_risk_assessments` coexist.
- AFR remains a navigation compatibility entry; operational Findings and
  Recommendations are AEMS-owned.
- Reserved `SCR-243` is retained in the SCR registry but is not a standalone
  operational route.
- Some historical phase sections in workflow documents describe an earlier
  checkpoint. The current status is the module summary in section 2 plus the
  latest acceptance documents.

## 10.1 Deferred standards-governance roadmap

The following items are deliberately recorded as future governance scope. They
are not implemented merely because related audit, risk, competency or
lessons-learned features exist:

1. **BAICS / Baseline Assessment of Internal Control System** — foundation
   cycle, source lineage, assignments and lifecycle are implemented; the
   five-component instruments, Control Universe, BAR and IAP risk-consumption
   gate are implemented through BAICS-4; BAICS-5 and BAICS-6 harden the
   integration permissions/notifications and complete final conformance
   verification. Future governance enhancements remain separately tracked.
2. **Internal Audit Charter and organizational independence governance** — no
   Charter version/approval lifecycle, Head of Internal Audit authority and
   reporting relationship record, access-rights confirmation, or periodic
   organizational-independence declaration.
3. **IASPPS QAIP** — no dedicated ongoing monitoring, internal assessment,
   external assessment, remediation, or QAIP reporting workflow. Informational
   QAIP references do not close this gap.
4. **Professional development and CPD/CPE compliance** — ARMIS competencies,
   certifications, availability and training-conflict checks exist, but a
   complete development-plan, CPE ledger, needs assessment, completion and
   competency-gap remediation lifecycle is not implemented.
5. **Standards traceability and control-component mapping** — no complete
   PGIAM/IASPPS/NGICS/ICSPPS requirement-to-code-to-test-to-evidence matrix or
   universal structured mapping of the five internal-control components.

These gaps do not invalidate AGIS's operational audit workflows. They prevent
an unconditional formal IASPPS/PGIAM/NGICS/ICSPPS conformance claim until the
governance decisions, supporting records and (for IASPPS) QAIP assessments are
completed. See the detailed roadmap and implementation rules in [AEMS
Governance and Acceptance](AEMS_GOVERNANCE_AND_ACCEPTANCE.md#14-deferred-standards-governance-roadmap).

## 11. Comparison checklist

When comparing a new external specification, record each row as:

| Requirement | Module/owner | UI route or contextual screen | API/model/service evidence | Permission/scope | Test evidence | Status / gap / decision |
| --- | --- | --- | --- | --- | --- | --- |
| Example: Evidence must be accepted before a finding is validated | AEMS | Evidence Management; Findings | `AemsEvidenceAssessmentService`, finding validation service, Core `document_versions` | `aems.evidence.*`, `aems.finding.*`, engagement/office scope | AEMS evidence and G10E acceptance tests | Implemented |

Do not mark a requirement implemented solely because a menu item, model, or
documentation sentence exists. Confirm the route, protected API action,
persisted state/transition, audit record, and focused test. Update this catalog,
the relevant workflow document, the API reference, and the testing guide when a
behavior changes.

## 12. Related documents

- [System Flow](SYSTEM_FLOW.md)
- [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md)
- [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md)
- [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md)
- [AEMS Governance and Acceptance (compiled G0-G10E)](AEMS_GOVERNANCE_AND_ACCEPTANCE.md)
- [CMS Workflow Design](CMS_WORKFLOW_DESIGN.md)
- [ARMIS Workflow and Implementation Checkpoint](ARMIS_WORKFLOW_DESIGN.md)
- [API and Data Reference](API_AND_DATA_REFERENCE.md)
- [End-to-End Testing Guide](END_TO_END_TESTING_GUIDE.md)
- [Operations Guide](OPERATIONS_GUIDE.md)
