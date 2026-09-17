# API and Data Reference

## 1. API conventions

Base path: `/api`.

Authentication uses Laravel Sanctum first-party SPA cookies. Mutating browser
requests obtain `/sanctum/csrf-cookie` before sending the request.

Standard JSON envelope:

```json
{
  "success": true,
  "message": "Optional human-readable result.",
  "data": {}
}
```

Validation error envelope:

```json
{
  "success": false,
  "message": "The submitted data is invalid.",
  "errors": {
    "field": ["Actionable validation message."]
  }
}
```

Identifiers in URLs are database IDs. Business codes such as `CIAS`,
`DOC-2026-00001`, or `IAP-2027` are returned in resource data and reports.

## Current API coverage

The route inventory in this document covers the current operational Core, IAP,
AEMS, CMS, and ARMIS surfaces. CMS is implemented through CMS-12B, including
protected report and CSV/PDF export routes. ARMIS is implemented through
ARMIS-7C, including provider monitoring and deployment-verification controls;
the ARMIS deployment command and Render smoke script are operator tools rather
than public API routes. AFR remains a compatibility placeholder because AEMS
owns Findings and Recommendations. AIS-5D provides a verified hardened read-only contract,
versioned Core/IAP/AEMS/CMS/ARMIS source reconciliation, aggregation, snapshot,
analytical dashboard, review-indicator, report, and protected export routes; it
does not mutate source modules or make professional
decisions. ARMIS provider authority is
activated only through its separate reconciliation gate.

## 2. Public/session endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/health` | Database/application health |
| GET | `/runtime-configuration` | Safe public branding/display configuration |
| GET | `/demo-accounts` | Local demo accounts when enabled |
| POST | `/login` | Employee-ID/password authentication |
| GET | `/me` | Restore authenticated session |
| POST | `/logout` | End authenticated session |

## 3. Core endpoints

All entries below require `auth:sanctum`; each action also has its route
permission where applicable.

### 3.1 Profile

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/profile` | `profile.view` |
| PUT | `/profile` | `profile.update` |
| PUT | `/profile/password` | `profile.change_password` |

### 3.2 Offices, Audit Areas, and Audit Focuses

| Record | List | Create | Update | Archive | Restore |
| --- | --- | --- | --- | --- | --- |
| Office | `GET /offices` | `POST /offices` | `PUT /offices/{id}` | `DELETE /offices/{id}` | `POST /offices/{id}/restore` |
| Audit Area | `GET /audit-areas` | `POST /audit-areas` | `PUT /audit-areas/{id}` | `DELETE /audit-areas/{id}` | `POST /audit-areas/{id}/restore` |
| Audit Focus | `GET /audit-focuses` | `POST /audit-focuses` | `PUT /audit-focuses/{id}` | `DELETE /audit-focuses/{id}` | `POST /audit-focuses/{id}/restore` |

### 3.3 Users and account controls

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/users` | Permission-scoped user registry |
| GET | `/users/{id}` | User detail, effective roles, history |
| POST | `/users` | Create account |
| PUT | `/users/{id}` | Update identity/employment/roles |
| DELETE | `/users/{id}` | Archive |
| POST | `/users/{id}/activate` | Activate |
| POST | `/users/{id}/disable` | Disable |
| POST | `/users/{id}/lock` | Manual lock |
| POST | `/users/{id}/unlock` | Remove manual/temporary lock |
| POST | `/users/{id}/restore` | Restore archived account |
| PUT | `/users/{id}/password` | Administrative password reset |

### 3.4 Roles, permissions, and master lists

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/roles` | Roles, user count, scopes, permissions |
| POST | `/roles` | Create role |
| POST | `/roles/{id}/clone` | Clone role and permissions/scopes |
| PUT | `/roles/{id}` | Update role |
| DELETE | `/roles/{id}` | Archive unassigned role |
| POST | `/roles/{id}/restore` | Restore |
| GET | `/permissions` | Permission catalogue |
| GET | `/master-lists` | Configurable/reference lists |
| POST | `/master-lists` | Create a list |
| PUT | `/master-lists/{id}` | Update items/order/status |

### 3.5 Documents

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/documents` | Confidentiality-filtered repository |
| POST | `/documents` | Upload metadata and immutable version 1 |
| PUT | `/documents/{id}` | Update metadata, classification, links |
| POST | `/documents/{id}/versions` | Create immutable version |
| DELETE | `/documents/{id}` | Archive document |
| POST | `/documents/{id}/restore` | Restore document |
| GET | `/documents/{id}/download` | Download current authorized version |
| GET | `/documents/{id}/versions/{version}/download` | Download authorized historical version |

Create/update document fields use camelCase, including:

```text
documentTypeId
confidentialityLevelId
title
referenceNumber
issuingAuthority
publicationDate
version (create only)
description
isActive
file (create only)
links[]
```

### 3.6 Workflow Management

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/workflows` | Definitions and visible instances |
| GET | `/workflows/{id}` | Definition graph/detail |
| POST | `/workflows` | Create draft definition |
| PUT | `/workflows/{id}` | Update draft graph |
| POST | `/workflows/{id}/publish` | Publish and retire previous family version |
| POST | `/workflows/{id}/revisions` | Create draft revision |
| DELETE | `/workflows/{id}` | Archive eligible definition |
| POST | `/workflows/{id}/restore` | Restore |
| POST | `/workflow-instances` | Start explicit or module-default workflow |
| GET | `/workflow-instances/{id}` | Instance and event history |
| POST | `/workflow-instances/{id}/transitions/{action}` | Perform transition |
| POST | `/workflow-instances/{id}/cancel` | Cancel with history |

### 3.7 Notifications

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/notifications` | Inbox with filters |
| GET | `/notifications/recent` | Header badge/recent list |
| POST | `/notifications` | Authorized targeted delivery |
| POST | `/notifications/read-all` | Mark all read |
| PUT | `/notifications/preferences` | Update delivery preferences |
| POST | `/notifications/{id}/read` | Mark read |
| POST | `/notifications/{id}/unread` | Mark unread |
| DELETE | `/notifications/{id}` | Archive |
| POST | `/notifications/{id}/restore` | Restore |

### 3.8 Configuration and logs

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/system-configurations` | Safe administrative setting values and constraints |
| PUT | `/system-configurations` | Validate, save, and apply settings |
| POST | `/system-configurations/logo` | Upload managed runtime logo |
| POST | `/system-configurations/test-email` | Send configuration test |
| GET | `/activity-logs` | Filtered operational history |
| GET | `/activity-logs/export` | Authorized activity export |
| GET | `/audit-logs` | Filtered data-change history |
| GET | `/audit-logs/export` | Authorized audit export |
| POST | `/record-views` | Permission-check and deduplicate detail-view activity |

## 4. IAP endpoints

BAICS-1A/1B foundation, BAICS-2A/2B assessment, BAICS-3A/3B Control
Universe/BAR, BAICS-4/BAICS-5 integration endpoints, and BAICS-6 conformance
verification are part of the IAP inventory.
They manage cycle scope, five control components, distinct methods, exact Core
evidence links, corroboration exceptions, traceable controls, interim
analysis, BAR assembly, immutable report versions and protected exports. BAICS-4
adds approved BAR/legacy-exception decisions for IAP consumers without
mutating source records; BAICS-5 adds granular integration authorization and
participant-scoped, deduplicated Core notifications. BAICS-6 verifies the
role/scope/SoD permission matrix, migration-rehearsed schema, shared-owner
boundaries and unique canonical navigation paths. The focused acceptance test
is `IapBaicsG6AcceptanceTest`; Playwright remains committed but paused.

| Method | Endpoint | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/iap/baics` | `iap.baics.view` | Scope-aware cycle register |
| POST | `/iap/baics` | `iap.baics.create` | Create cycle and immutable Audit Universe source snapshots |
| GET | `/iap/baics/{assessment}` | `iap.baics.view` | Cycle detail, readiness, assignments and versions |
| PUT | `/iap/baics/{assessment}` | `iap.baics.update` | Edit an eligible draft/revision with lock version |
| DELETE / POST | `/iap/baics/{assessment}`, `/restore` | `iap.baics.archive` | Soft archive/restore |
| POST | `/iap/baics/{assessment}/transitions/{action}` | action-specific BAICS permission | Guarded lifecycle transition |
| POST | `/iap/baics/{assessment}/revisions` | `iap.baics.update` | Create a new immutable revision family member |
| GET | `/iap/baics/{assessment}/versions` | `iap.baics.view` | Version snapshot metadata and hashes |
| POST / DELETE | `/iap/baics/{assessment}/assignments` | `iap.baics.assign` | Add/end assessment assignments |
| GET | `/iap/baics/{assessment}/readiness` | `iap.baics.view` | Five-component and cycle readiness diagnostics |
| GET | `/iap/baics/{assessment}/components` | `iap.baics.view` | Component register and readiness |
| GET / PUT | `/iap/baics/{assessment}/components/{component}` | view / `iap.baics.manage-controls` | Inspect or save component conclusion, assessor and reviewer |
| POST | `/iap/baics/{assessment}/components/{component}/transitions/{action}` | action-specific | Submit, return or approve a component |
| GET / POST | `/iap/baics/{assessment}/components/{component}/methods` | view / `iap.baics.manage-controls` | List or record distinct assessment methods |
| PUT | `/iap/baics/{assessment}/components/{component}/methods/{method}` | `iap.baics.manage-controls` | Edit an eligible method with optimistic lock |
| POST | `/iap/baics/{assessment}/components/{component}/methods/{method}/transitions/{action}` | action-specific | Submit, return or independently approve a method |
| GET / POST / DELETE | `/iap/baics/{assessment}/components/{component}/evidence` | view / `iap.baics.manage-controls` | Inspect, link or unlink exact Core Document Versions |
| GET / POST | `/iap/baics/{assessment}/exceptions` | view / `iap.baics.manage-controls` | List or draft a time-limited corroboration exception |
| PUT | `/iap/baics/{assessment}/exceptions/{exception}` | `iap.baics.manage-controls` | Edit a draft exception with optimistic lock |
| POST | `/iap/baics/{assessment}/exceptions/{exception}/transitions/{action}` | action-specific | Submit, return, approve or reject an exception |
| GET / POST / PUT | `/iap/baics/{assessment}/controls`, `/controls/{control}` | view / `iap.baics.manage-controls` | Control Universe register and editable draft controls |
| POST | `/iap/baics/{assessment}/controls/{control}/transitions/{action}` | action-specific | Submit, return or independently approve a control |
| GET / POST / PUT | `/iap/baics/{assessment}/interim-analyses`, `/interim-analyses/{analysis}` | view / `iap.baics.manage-controls` | Interim analysis sources and revisions |
| POST | `/iap/baics/{assessment}/interim-analyses/{analysis}/transitions/{action}` | action-specific | Submit, return or approve interim analysis |
| GET / POST / PUT | `/iap/baics/{assessment}/reports`, `/reports/{report}` | view / `iap.baics.manage-controls` | BAR assembly from selected Control Universe and interim analysis sources |
| POST | `/iap/baics/{assessment}/reports/{report}/transitions/{action}` | action-specific | Submit, return, approve, issue or supersede a BAR |
| GET | `/iap/baics/{assessment}/reports/{report}/export?format=pdf|csv` | `iap.baics.export` | Authenticated protected deterministic BAR export |
| GET | `/iap/baics/integrations/candidates` | `iap.baics.integration.view` or legacy `iap.baics.view` | Scope-aware IAP consumers, enforcement flag, and ARMIS provider status |
| GET | `/iap/baics/integrations/readiness?consumerType=...&consumerId=...` | `iap.baics.integration.view` or legacy `iap.baics.view` | Readiness and active approved decision for one IAP consumer |
| GET | `/iap/baics/{assessment}/integrations` | `iap.baics.integration.view` or legacy `iap.baics.view` | Integration decisions and immutable version history for a BAICS cycle |
| POST / PUT | `/iap/baics/{assessment}/integrations`, `/integrations/{integration}` | `iap.baics.integration.create/update` or legacy `iap.baics.manage-controls`/`iap.baics.update` | Draft or revise a BAR-backed or legacy-exception decision with optimistic lock |
| POST | `/iap/baics/integrations/{integration}/transitions/{action}` | matching `iap.baics.integration.*` permission or documented legacy alias | Submit, independently review, return, approve, or retire a decision; participant eligibility and separation are rechecked by the service |

Foundation tables are `iap_baics_assessments`, `iap_baics_scope_items`,
`iap_baics_assignments`, and `iap_baics_versions`. BAICS-2 adds
`iap_baics_components`, `iap_baics_methods`, `iap_baics_component_versions`,
`iap_baics_method_versions`, `iap_baics_evidence_links`, and
`iap_baics_exceptions`/`iap_baics_exception_versions`. BAICS-3 adds
`iap_baics_controls`, `iap_baics_control_versions`, control-method/evidence
trace tables, `iap_baics_interim_analyses`/versions, and
`iap_baics_reports`/`iap_baics_report_versions` with report source-manifest and
checksum metadata. They reference Core users,
offices, Areas, Focuses, and IAP Audit Universe records; they do not duplicate
those owners. BAICS-4 adds `iap_baics_integrations` and immutable
`iap_baics_integration_versions`; the ledger stores consumer/source/provider
snapshots, reviewer/authority decisions, expiry, hashes and lock versions.

### 4.1 Dashboard and reports

| Method | Endpoint |
| --- | --- |
| GET | `/iap/dashboard` |
| GET | `/iap/reports` |
| GET | `/iap/reports/{report}` |
| GET | `/iap/reports/{report}/export` |

### 4.2 Strategic plans

```text
GET    /iap/strategic-plans
POST   /iap/strategic-plans
GET    /iap/strategic-plans/{id}
PUT    /iap/strategic-plans/{id}
DELETE /iap/strategic-plans/{id}
POST   /iap/strategic-plans/{id}/restore
GET    /iap/strategic-plans/{id}/completeness
POST   /iap/strategic-plans/{id}/transitions/{action}
POST   /iap/strategic-plans/{id}/revisions
```

### 4.3 Audit Universe

```text
GET    /iap/audit-universe
POST   /iap/audit-universe
PUT    /iap/audit-universe/{id}
DELETE /iap/audit-universe/{id}
POST   /iap/audit-universe/{id}/restore
```

### 4.4 Risk periods and subject assessments

```text
GET    /iap/risk-periods
POST   /iap/risk-periods
GET    /iap/risk-periods/{period}
PUT    /iap/risk-periods/{period}
DELETE /iap/risk-periods/{period}
POST   /iap/risk-periods/{period}/restore
POST   /iap/risk-periods/{period}/transitions/{action}

POST   /iap/risk-periods/{period}/assessments
PUT    /iap/risk-periods/{period}/assessments/{assessment}
DELETE /iap/risk-periods/{period}/assessments/{assessment}
POST   /iap/risk-periods/{period}/assessments/{assessment}/restore
POST   /iap/risk-periods/{period}/assessments/{assessment}/evidence
GET    /iap/risk-periods/{period}/assessments/{assessment}/evidence/{evidence}
DELETE /iap/risk-periods/{period}/assessments/{assessment}/evidence/{evidence}
```

### 4.5 Prioritization

```text
GET    /iap/prioritizations
POST   /iap/prioritizations
GET    /iap/prioritizations/{id}
PUT    /iap/prioritizations/{id}
PUT    /iap/prioritizations/{id}/items/{item}
POST   /iap/prioritizations/{id}/transitions/{action}
DELETE /iap/prioritizations/{id}
POST   /iap/prioritizations/{id}/restore
```

### 4.6 Annual plans, risks, engagements, and support

```text
GET    /iap/plans
POST   /iap/plans
GET    /iap/plans/{plan}
PUT    /iap/plans/{plan}
DELETE /iap/plans/{plan}
POST   /iap/plans/{plan}/restore
GET    /iap/plans/{plan}/completeness
POST   /iap/plans/{plan}/transitions/{action}
POST   /iap/plans/{plan}/revisions

GET    /iap/plans/{plan}/risk-assessments
POST   /iap/plans/{plan}/risk-assessments
PUT    /iap/plans/{plan}/risk-assessments/{assessment}
DELETE /iap/plans/{plan}/risk-assessments/{assessment}
POST   /iap/plans/{plan}/risk-assessments/{assessment}/restore

POST   /iap/plans/{plan}/engagements
PUT    /iap/plans/{plan}/engagements/{engagement}
DELETE /iap/plans/{plan}/engagements/{engagement}
POST   /iap/plans/{plan}/engagements/{engagement}/restore
PUT    /iap/plans/{plan}/engagements/{engagement}/team
PUT    /iap/plans/{plan}/prioritization

GET    /iap/plans/{plan}/supporting-records
POST   /iap/plans/{plan}/attachments
GET    /iap/plans/{plan}/attachments/{attachment}/download
DELETE /iap/plans/{plan}/attachments/{attachment}
POST   /iap/plans/{plan}/attachments/{attachment}/restore
POST   /iap/plans/{plan}/comments
```

### 4.7 Scheduling and capacity

```text
GET  /iap/schedules
POST /iap/schedules/{engagement}/conflicts
PUT  /iap/schedules/{engagement}
POST /iap/schedules/{engagement}/cancel

GET    /iap/resources
PUT    /iap/resources/auditors/{user}/capacity
POST   /iap/resources/auditors/{user}/unavailability
PUT    /iap/resources/unavailability/{id}
DELETE /iap/resources/unavailability/{id}
POST   /iap/resources/unavailability/{id}/restore
PUT    /iap/resources/auditors/{user}/skills
PUT    /iap/resources/engagements/{engagement}/requirements
```

## 5. Common list query parameters

Where supported:

| Parameter | Type | Meaning |
| --- | --- | --- |
| `search` | string | Code/name/description/domain search |
| `status` | string | Domain status |
| `officeId` | integer | Office filter |
| `auditAreaId` | integer | Audit Area filter |
| `fiscalYear` | integer | Planning year |
| `includeArchived` or `include_archived` | boolean | Include recoverable records if authorized |
| `sortBy` | string | Whitelisted sortable column |
| `sortDirection` | `asc`/`desc` | Sort direction |
| `page` | integer | Page number |
| `perPage` | integer | Page size within enforced bounds |

## 6. Core data model

| Entity | Main relationships |
| --- | --- |
| `User` | belongs to Office; has primary legacy Role and many assigned Roles; has notifications/logs/IAP assignments |
| `Role` | many Users and Permissions; stores office and engagement scopes |
| `Permission` | belongs to many Roles; stable module/action code |
| `Office` | has Users; many Audit Areas; independent, no parent |
| `AuditArea` | optional parent/children; responsible Office; many covered Offices; many Audit Focuses |
| `AuditFocus` | belongs to exactly one Audit Area |
| `MasterList` | has ordered, soft-deletable MasterListItems |
| `SystemConfiguration` | key/value/type/group; updated by User |
| `Document` | type/confidentiality items; versions; current version; module links; uploader/updater |
| `DocumentVersion` | belongs to Document; immutable stored file metadata/checksum |
| `DocumentLink` | belongs to Document; typed module record reference |
| `WorkflowDefinition` | versioned family with Steps, Transitions, and Instances |
| `WorkflowInstance` | pinned definition/current step; immutable Events |
| `SystemNotification` | recipient/actor, subject/deep link, read/archive state |
| `NotificationPreference` | one per User |
| `ActivityLog` | operational event and metadata |
| `AuditLog` | polymorphic old/new value history |

## 7. IAP data model

| Entity | Main relationships |
| --- | --- |
| `StrategicInternalAuditPlan` | objectives, priorities/themes, workflow events, revision lineage |
| `SiapObjective` | belongs to SIAP; maps to Audit Areas |
| `SiapPriority` | belongs to SIAP |
| `IapAuditUniverseItem` | responsible Office, primary Audit Area, stakeholder Offices, history |
| `IapRiskPeriod` | criteria, assessments, period events |
| `IapUniverseRiskAssessment` | period + universe item; scores, evidence, risk levels |
| `IapRiskAssessment` | legacy annual-plan-scoped risk, scores, plan attachments, and engagement references |
| `IapPrioritizationRun` | source risk period; ranked Items and Events |
| `IapPrioritizationItem` | snapshot of subject, scores, rank, decision, and source IDs |
| `InternalAuditPlan` | fiscal-year revision; engagements, risks, attachments, comments, workflow events |
| `IapPlanEngagement` | plan, source item/assessment, coverage, team, skills, schedule |
| `IapEngagementTeamMember` | engagement + User + planned person-days/lead flag |
| `IapAuditorCapacity` | User/year capacity |
| `IapAuditorUnavailability` | User/date range/type |
| `IapAuditorSkill` | User/master skill |
| `IapEngagementSkillRequirement` | engagement/master skill requirement |
| `IapScheduleEvent` | immutable schedule old/new values and reason |
| `IapAttachment` | plan-owned supporting file |
| `IapComment` | plan-owned review/management comment |
| `IapWorkflowEvent` | immutable annual-plan transition |

Both `iap_risk_assessments` and `iap_universe_risk_assessments` remain active.
The former supports legacy annual-plan-local risk records and revisions; the
latter supports the current period/universe workflow, prioritization, newer
plan lineage, and AEMS source snapshots. They are not interchangeable, and
neither system is being migrated or removed as part of current maintenance.

## 8. AEMS data model

The AEMS database/model foundation, Engagement Registry, aggregate lifecycle,
Entry Conference, Audit Team, AEO, AEP,
Audit Program, Working Paper, Audit Evidence, Issue, Finding, Management
Response, Auditor Rejoinder, Recommendation, Exit Conference, and Audit Report
endpoints are implemented. The Engagement Tracker endpoint derives portfolio
metrics and per-engagement progress from those records.
`AemsEngagementTransitionService` now moves the parent through controlled
actions and cross-workflow gates to `CLOSURE_REVIEW`, distinct `COMPLETED`, and
the guarded atomic `CLOSED` transition for the implemented formal Completion
Assessment and Engagement Closure aggregate.
CMS is operational through CMS-12B. CMS-1 provides the hardened immutable
intake foundation: every valid AEMS transfer creates one source envelope, one
separate operational case initialized in `TRANSFERRED`, and one append-only
intake event. Later CMS records preserve that lineage through monitoring,
professional decisions, closure/dispositions, automation, and reporting.

| Entity | Main relationships |
| --- | --- |
| `AuditEngagement` | optional approved IAP engagement source; offices, audit areas/focuses, team, AEO, AEP, Entry Conference, programs, working papers, evidence, issues, findings, conferences, reports, Completion Assessments, Closures, final index, retention, lessons, reopening requests, events |
| `AemsEngagementScopeBackfillReview` | reviewed legacy office IDs, canonical office, resolution state, and reviewer metadata used to enforce the one-office foundation invariant |
| `EntryConference` | one official PGIAM record per engagement; schedule, briefing, notes, completion/waiver, and optimistic lock |
| `EntryConferenceParticipant` | internal, auditee, or external participant and attendance |
| `EntryConferenceMatter` | matter, materiality, disposition, responsibility, and due date |
| `EntryConferenceAgreement` | commitment, responsibility, due date, and status |
| `EntryConferenceAcknowledgement` | immutable actor/office/version acknowledgement or reservation |
| `EntryConferenceAttachment` | immutable link to one exact private Core DocumentVersion |
| `EngagementTeam` | engagement + User assignment, role, person-days, active dates |
| `EngagementTeamHistory` | append-only team assignment old/new values |
| `AuditEngagementOrder` | one active family per engagement; immutable versions |
| `AuditEngagementOrderVersion` | exact authority/scope/team snapshot and optional DocumentVersion |
| `AuditEngagementPlan` | one active family per engagement; programs and immutable versions |
| `AuditEngagementPlanVersion` | objectives, scope, methodology, criteria, dates, resources, risks |
| `AuditProgram` | engagement/AEP revision with ordered procedures |
| `AuditProgramProcedure` | assignee, target date, evidence expectation, completion/waiver |
| `WorkingPaper` | engagement/procedure family with reviewer and immutable versions |
| `WorkingPaperVersion` | objective, work performed, population/sample, result, conclusion, cross-references, and exact cited evidence versions |
| `AuditEvidence` | immutable version family pinned to Core DocumentVersion, checksum, type, source, custodian, and confidentiality; many working-paper versions/issues/findings |
| `AuditIssue` | engagement exception linked to working-paper versions and evidence; optional one Finding; structured terminal disposition metadata |
| `AuditFinding` | engagement/issue revision with criteria, condition, cause, effect, conclusion, significance/effect classification, exact fieldwork versions, evidence, recommendations, responses, and immutable revision snapshots |
| `AuditRecommendation` | finding + responsible office/target date + idempotent CMS transfer lineage |
| `ManagementResponse` | versioned auditee response to a communicated Finding |
| `AuditorRejoinder` | auditor disposition and response dialogue conclusion |
| `AemsDialogueAttachment` | immutable link from one response or rejoinder to an exact private Core DocumentVersion |
| `ExitConference` | engagement schedule, minutes, agreements, participants, acknowledgement |
| `ExitConferenceParticipant` | internal/external attendee and attendance state |
| `ExitConferenceAttachment` | immutable private Core DocumentVersion pinned to one conference |
| `ExitConferenceAcknowledgement` | immutable actor-, office-, date-, comment-, status-, and version-specific auditee acknowledgement |
| `AuditReport` | one active report family per engagement with confidentiality and immutable versions |
| `AuditReportVersion` | generated content/file snapshot with selected Findings and recipients |
| `ReportRecipient` | internal/external delivery and acknowledgement for an exact report version |
| `AuditReportReviewComment` | immutable reviewer action and comment pinned to one exact report version |
| `CmsRecommendation` | immutable AEMS source envelope created once from an eligible recommendation in the exact issued report version; preserves report checksum, confidentiality, risk, office, original target, actor/date, and complete JSON snapshot |
| `CmsRecommendationCase` | one-to-one operational root initialized in `TRANSFERRED`, with effective target date, lead office, creator/opened date, and optimistic lock |
| `CmsRecommendationAssignment` | non-deletable Compliance Monitor history; only one effective current monitor is supported per case in CMS-2A |
| `CmsRecommendationEvent` | append-only case history for intake and assignment changes |
| `CmsCorrectiveActionPlan` | stable Action Plan family with current and accepted immutable-version pointers |
| `CmsActionPlanVersion` | controlled management plan content and immutable accepted baseline |
| `CmsActionPlanMilestone` | measurable accepted-baseline commitment owned by one plan version |
| `CmsProgressUpdate` | stable case/reporting-period family pinned to one exact accepted Action Plan Version |
| `CmsProgressUpdateVersion` | immutable-after-submission management-reported content and current/recorded lineage |
| `CmsMilestoneProgress` | management-reported state, percentage, narrative, and immutable accepted-milestone snapshot |
| `CmsProgressEvidenceLink` | exact Core DocumentVersion/checksum/confidentiality link, optionally tied to milestone progress |
| `EngagementEvent` | append-only action, actor, state, lock version, and old/new snapshots |
| `CompletionAssessment` | current/revision family evaluating 25 required completion criteria |
| `CompletionAssessmentItem` | criterion result, source, blocker, and elevated acceptance |
| `CompletionAssessmentVersion` | immutable assessment snapshot pinned to an exact private Core DocumentVersion |
| `EngagementClosure` | current/revision family, approval/closed snapshots, status, and exact Closure DocumentVersion |
| `EngagementClosureChecklistItem` | evaluated authoritative gate and source record/path |
| `EngagementClosureEvent` | append-only controlled Closure transition event |
| `EngagementDocumentIndexItem` | exact indexed source record/DocumentVersion, checksum/file health, inclusion or authorized exclusion |
| `EngagementRetentionRecord` | replaceable-provider custody, retention, permanent/disposition, and legal-hold snapshot |
| `EngagementLessonLearned` | confidential post-engagement improvement record separate from issued results |
| `EngagementReopenRequest` | written-authority exceptional reopening workflow and original closed snapshot |

Important database constraints:

- one non-cancelled, non-archived AEMS engagement per IAP plan engagement;
- planned engagements require an IAP source;
- PostgreSQL special engagements require separate authority metadata;
- engagement coverage uses explicit office, audit-area, and audit-focus pivots;
- a team user has at most one current assignment per engagement;
- issue-to-finding conversion is one-to-one;
- version and revision numbers are unique within their families;
- current program, evidence, finding, and response revisions are unique;
- Working Paper indexes are unique within an engagement;
- exact Working Paper version/evidence links cannot be duplicated;
- CMS transfer keys and source AEMS recommendation IDs cannot be duplicated;
- AEMS `cms_recommendation_id` is a restrictive foreign key after an
  orphan-pointer migration preflight;
- each CMS intake has exactly one operational case;
- each initial event uses a unique idempotency key, so retry cannot duplicate
  `INTAKE_CREATED`;
- immutable intake status is constrained to `TRANSFERRED`; operational CMS
  states belong to the separate case;
- PostgreSQL partial unique indexes enforce one current Compliance Monitor per
  case and prevent a duplicate current user/case/role assignment;
- assignment rows are ended, never deleted or overwritten.

### 8.0 CMS-2A backend and CMS-2B React workspace

CMS routes use the **operational `cms_recommendation_cases.id`** as the
`{recommendation}` identifier. Cases are created only by the AEMS transfer
boundary; there is no generic CMS create, update, or delete endpoint.

| Method | Endpoint | Permission | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/cms/dashboard` | `cms.dashboard.view` | Live aggregates for the actor's visible case population |
| `GET` | `/api/cms/recommendations` | `cms.recommendation.view` | Scoped search/filter/sort/paginated registry |
| `GET` | `/api/cms/recommendations/{recommendation}` | `cms.recommendation.view` | Safe case, intake, source-lineage, assignment, and event detail |
| `GET` | `/api/cms/recommendations/{recommendation}/assignments` | `cms.recommendation.view` | Assignment history and, for authorized CIAS Management, eligible monitor options |
| `POST` | `/api/cms/recommendations/{recommendation}/assignments` | `cms.recommendation.assign` | Assign or replace the Compliance Monitor |
| `POST` | `/api/cms/recommendations/{recommendation}/assignments/{assignment}/end` | `cms.recommendation.assign` | End the current assignment without deleting history |

Registry query parameters are `search`, `status`, `officeId`, `risk`,
`confidentiality`, `monitorId`, `assigned`, `hasTargetDate`, `overdue`,
`transferredFrom`, `transferredTo`, `targetFrom`, `targetTo`, `sortBy`,
`sortDirection`, `perPage`, and `page`. Sort fields are limited to
`recommendationCode`, `transferredAt`, `targetDate`, `responsibleOffice`,
`risk`, `status`, and `assignedMonitor`. Pagination totals and filter options
are computed after visibility and confidentiality scope.

Assignment payload:

```json
{
  "userId": 42,
  "reason": "Required when replacing the current monitor.",
  "effectiveFrom": "2026-08-01T08:00:00+08:00",
  "effectiveUntil": null,
  "lockVersion": 1
}
```

Ending requires `reason` and the latest case `lockVersion`. A stale lock,
invalid monitor, duplicate assignment, or current-state conflict returns the
standard `422` validation envelope. A scoped nonexistent/inaccessible case
returns `404`. Assignment creation returns `201`; reads and ending return
`200`.

List items contain the generated display code (`CMS-REC-` plus the zero-padded
case ID), current state and monitor, effective target, derived overdue flag,
source summary, risk, confidentiality, and responsible office. Detail adds the
immutable transfer envelope, exact AEMS engagement/finding/recommendation/report
lineage, checksum, original office/target snapshots, assignment history, and
visible event timeline. It never returns document storage paths or unrestricted
AEMS Working Papers/evidence.

Dashboard cards include total, transferred/open, assigned, unassigned,
overdue, no-target, current-month transfer, high-risk, and high-risk-overdue
counts, plus visible office/risk/confidentiality/monitor groups, recent
transfers, and oldest unresolved targets. `OVERDUE` is derived, not persisted.
Due-soon is reported unavailable until an approved runtime threshold exists.

CMS-2A adds:

- `cms.dashboard.view`;
- `cms.recommendation.view`;
- `cms.recommendation.assign`;
- `cms.recommendation.monitor`;
- `cms.administration.monitor`.

The six legacy `cms.*` compatibility permissions remain unchanged.
`cms.view` is only a temporary basic-inquiry alias inside the authoritative
scope and never bypasses office, assignment, role, confidentiality, account, or
record-state restrictions. Seeded CIAS Management receives portfolio view,
assignment, and monitoring. AGIS Users may monitor only assigned cases.
Auditee Representatives see their responsible-office cases subject to
confidentiality. Read Only Users remain office/assignment scoped. Platform and
AGIS administration do not receive professional assignment authority;
`cms.administration.monitor` alone cannot open operational records.

Compliance Monitor changes use case row locking and `lockVersion`, retain ended
rows, append one of `COMPLIANCE_MONITOR_ASSIGNED`,
`COMPLIANCE_MONITOR_REPLACED`, or
`COMPLIANCE_MONITOR_ASSIGNMENT_ENDED`, write Activity Log and Audit Trail
records, and notify affected monitors after commit. Assignment does not change
case status and grants no validation or closure authority.

The CMS-2B React workspace consumes these APIs without recreating backend
scope, overdue, eligibility, or workflow rules:

| Frontend route | Permission | Behavior |
| --- | --- | --- |
| `/compliance-management` | `cms.dashboard.view` | Redirects to the CMS dashboard |
| `/compliance-management/dashboard` | `cms.dashboard.view` | Live scoped dashboard with attention links and supported summaries |
| `/compliance-management/recommendations` | `cms.recommendation.view` | URL-backed, server-searched/filtered/sorted/paginated registry |
| `/compliance-management/recommendations/{caseId}` | `cms.recommendation.view` | Safe overview, source lineage, assignment history, and event timeline |

Users with `cms.recommendation.assign` see assign, replace, and end controls.
Eligible monitor options come only from the assignment endpoint. Mutations
include `lockVersion`, retain backend validation messages, disable repeat
submission, refresh after success, and reload after a stale-lock response.
The sidebar contains live CMS destinations but no static operational counts.
Due-soon remains explicitly unavailable pending an approved runtime threshold.

### 8.0.1 CMS-3A Corrective Action Plans

CMS-3A adds one management-owned Action Plan family per recommendation case,
immutable content versions, measurable version-owned milestones, and controlled
review/acceptance.

| Method | Endpoint | Permission | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/cms/recommendations/{recommendation}/action-plan` | `cms.action-plan.view` | Case context and existing plan or actor-specific create availability |
| `POST` | `/api/cms/recommendations/{recommendation}/action-plans` | `cms.action-plan.create` | Create family and version 1 draft |
| `GET` | `/api/cms/action-plans/{actionPlan}` | `cms.action-plan.view` | Safe family, versions, milestones, completeness, and actions |
| `PUT` | `/api/cms/action-plans/{actionPlan}/versions/{version}` | `cms.action-plan.update` | Replace current draft content and milestones |
| `POST` | `/api/cms/action-plans/{actionPlan}/versions/{version}/transitions/submit` | `cms.action-plan.submit` | Validate and snapshot a complete draft |
| `POST` | `/api/cms/action-plans/{actionPlan}/versions/{version}/transitions/start-review` | `cms.action-plan.review` | Start independent compliance review |
| `POST` | `/api/cms/action-plans/{actionPlan}/versions/{version}/transitions/return` | `cms.action-plan.return` | Return immutable reviewed version with instructions |
| `POST` | `/api/cms/action-plans/{actionPlan}/versions/{version}/transitions/accept` | `cms.action-plan.accept` | Establish the official accepted baseline |
| `POST` | `/api/cms/action-plans/{actionPlan}/versions/{version}/revisions` | `cms.action-plan.revise` | Copy a returned/accepted current version into a new draft |

Create/update payloads use camelCase narratives, dates, owner/focal IDs,
`lockVersion`, and a `milestones` array. Submit requires `confirmation`; return
requires `returnReason`; accept requires `acceptanceComment`, `confirmation`,
and the latest lock; revision requires `revisionReason`.

The family derives `CAP-CMS-REC-{case ID}` and each version derives a `-Vn`
suffix. `currentVersionId` identifies the active working version;
`acceptedVersionId` alone identifies the official monitoring baseline. Older
accepted records remain `ACCEPTED` and resources derive `isSuperseded`.

Version states are `DRAFT`, `SUBMITTED`, `UNDER_REVIEW`, `RETURNED`, and
`ACCEPTED`. Case states implemented through CMS-3A are `TRANSFERRED`,
`FOR_ACTION_PLAN`, and `MONITORING`. Clients cannot set either state or a
version/pointer value.

Milestone weights are optional. If one is supplied, every milestone requires a
weight and the total must equal 100%. This does not represent validated
progress. A plan target cannot exceed the case effective target; when the case
target is missing, a plan date remains only a proposal and does not update the
case.

The recommendation detail resource adds backward-compatible
`actionPlanSummary`. CMS-3B consumes the dedicated endpoints at the protected
React route
`/compliance-management/recommendations/{caseId}/action-plan`. The workspace
supports draft narratives and ordered milestones, submission, review start,
return, acceptance, controlled revision, accepted-baseline visibility, and
read-only version history. It sends `lockVersion` on each mutation and treats
resource `availableActions` and Laravel authorization as authoritative. No new
CMS-3B endpoint or payload shape was introduced.

### 8.0.2 CMS-4A management-reported Progress Updates

CMS-4A adds reporting-period families, immutable versions, accepted-baseline
milestone reports, and exact Core Document Version evidence links. `RECORDED`
means completeness-reviewed management information and never independent
validation.

| Method | Endpoint | Permission | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/cms/recommendations/{recommendation}/progress-updates` | `cms.progress.view` | Scoped families, versions, case/baseline context, and create availability |
| `POST` | `/api/cms/recommendations/{recommendation}/progress-updates` | `cms.progress.create` | Create one reporting-period family and version 1 draft |
| `GET` | `/api/cms/progress-updates/{progressUpdate}` | `cms.progress.view` | Safe family, version history, reported progress, evidence, completeness, and actions |
| `PUT` | `/api/cms/progress-updates/{progressUpdate}/versions/{version}` | `cms.progress.update` | Update current draft narratives and milestone reports |
| `POST` | `/api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/submit` | `cms.progress.submit` | Validate, calculate, and snapshot a complete management submission |
| `POST` | `/api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/start-review` | `cms.progress.review` | Start independent completeness review |
| `POST` | `/api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/return` | `cms.progress.return` | Return an immutable version with required instructions |
| `POST` | `/api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/record` | `cms.progress.record` | Record completeness-reviewed management information without validation |
| `POST` | `/api/cms/progress-updates/{progressUpdate}/versions/{version}/revisions` | `cms.progress.revise` | Copy a returned or recorded current version to a new draft |
| `POST` | `/api/cms/progress-updates/{progressUpdate}/versions/{version}/evidence` | `cms.evidence.upload` | Create private Core document/version and pin the exact evidence version |
| `GET` | `/api/cms/progress-evidence/{evidence}/download` | `cms.evidence.download` | Stream exact authorized private evidence |
| `DELETE` | `/api/cms/progress-evidence/{evidence}` | `cms.evidence.remove_draft` | Mark a draft link removed while retaining the Core document/version |

Create/update payloads use `reportingPeriodStart`, `reportingPeriodEnd`,
management narratives, optional `managementReportedOverallPercentage`,
`milestoneProgress[]`, and `lockVersion`. Milestone entries use the accepted
`actionPlanMilestoneId`, a management-reported status and percentage,
accomplishment/constraint/next-step narratives, optional forecast and
no-evidence explanation, and display order.

Weighted baselines expose a server-calculated result using
`sum(percentage × weight) / 100`, rounded half-up to two decimals. Unweighted
baselines require management's overall percentage and are not averaged.
Resources expose `managementReportsComplete`,
`reportedCompleteAwaitingValidation`, and `notIndependentlyValidated`; they do
not expose an implementation result.

Evidence responses include exact document and version IDs, checksum, safe file
metadata, and effective confidentiality, but never a storage path. The stricter
recommendation/document confidentiality controls access. Later document
versions do not redirect a historical evidence link.

The recommendation detail adds `progressUpdateSummary`, and dashboard cards add
management-reported no-update, awaiting-review, recorded, and reported-complete
counts without changing existing fields.

CMS-4B provides the recommendation-specific React workspace. Independent
validation and implementation conclusions are implemented by CMS-5A below.
Due-soon configuration, reminders, escalation closure, accepted risk,
no-longer-applicable decisions, reopening, and CMS reports/exports remain
unimplemented; CMS-6A/6B target-date extensions are operational.

### 8.0.3 CMS-5A independent validation backend

CMS-5A adds `cms_validation_reviews`, `cms_validation_versions`,
`cms_validation_items`, `cms_validation_evidence_assessments`,
`cms_validation_assignments`, and `cms_validation_evidence_links`. Restrictive
foreign keys and portable active-slot uniqueness enforce one review per exact
recorded Progress Update Version, one active review per case, one active
version per review, and one current Primary Validator.

| Method | Endpoint | Permission |
| --- | --- | --- |
| `GET` / `POST` | `/api/cms/recommendations/{recommendation}/validations` | `cms.validation.view` / `cms.validation.create` |
| `GET` | `/api/cms/validations/{validation}` | `cms.validation.view` |
| `GET` / `POST` | `/api/cms/validations/{validation}/assignments` | `cms.validation.view` / `cms.validation.assign` |
| `POST` | `/api/cms/validations/{validation}/assignments/{assignment}/end` | `cms.validation.assign` |
| `PUT` | `/api/cms/validations/{validation}/versions/{version}` | `cms.validation.update` |
| `POST` | `/api/cms/validations/{validation}/versions/{version}/transitions/submit` | `cms.validation.submit` |
| `POST` | `/api/cms/validations/{validation}/versions/{version}/transitions/start-review` | `cms.validation.review` |
| `POST` | `/api/cms/validations/{validation}/versions/{version}/transitions/return` | `cms.validation.return` |
| `POST` | `/api/cms/validations/{validation}/versions/{version}/transitions/finalize` | `cms.validation.finalize` |
| `POST` | `/api/cms/validations/{validation}/versions/{version}/revisions` | `cms.validation.revise` |
| `POST` | `/api/cms/validations/{validation}/versions/{version}/evidence` | `cms.validation-evidence.upload` |
| `GET` | `/api/cms/validation-evidence/{evidence}/download` | `cms.validation-evidence.download` |
| `DELETE` | `/api/cms/validation-evidence/{evidence}` | `cms.validation-evidence.remove_draft` |

Create requires `recordedProgressUpdateVersionId`, `validatorUserId`,
`assignmentReason`, and the case `lockVersion`. Draft updates accept the
professional narrative fields, optional validated percentage,
`validationItems[]`, `evidenceAssessments[]`, and version `lockVersion`.
Submit requires confirmation; return and revision require reasons. Finalize
requires confirmation, `finalConclusionCode`, `finalizationComment`, and
`overrideReason` when changing the proposed conclusion.

Version statuses are `DRAFT`, `SUBMITTED`, `UNDER_REVIEW`, `RETURNED`, and
`FINALIZED`. Conclusions are `NOT_IMPLEMENTED`, `PARTIALLY_IMPLEMENTED`,
`IMPLEMENTED`, and `INADEQUATE_BASIS`. Case statuses now also include
`FOR_VALIDATION`, `PARTIALLY_IMPLEMENTED`, and `IMPLEMENTED`; no closure status
is added. Resources expose exact source IDs, safe source context, assignment
history, versions/items/assessments/evidence, completeness, derived history,
and actor-specific actions without storage paths.

### 8.1 AEMS authorization contract

There are 88 `aems.<resource>.<action>` permissions, including
`aems.engagement.export` for the access-scoped Engagement Progress Report.
The lifecycle and Entry Conference additions are
`aems.engagement.transition`, `aems.entry-conference.view`,
`aems.entry-conference.manage`, `aems.entry-conference.acknowledge`, and
`aems.entry-conference.waive`.
Formal closure adds 21 granular Completion Assessment, Closure, document-index,
retention, and exceptional-reopening operations. The verified runtime
catalogue contains 231 permissions in total, including 44 `cms.*` permissions.
Administrators receive monitoring access but do not receive audit approval or
issuance permissions. CIAS Management has global audit authority. AGIS Users
require an active `engagement_teams` assignment, and the assignment role limits
the actions they may perform.

Collection endpoints must apply the corresponding model scope:

```php
AuditEngagement::query()->visibleTo($request->user());
AuditIssue::query()->visibleTo($request->user());
AuditFinding::query()->visibleTo($request->user());
AuditReport::query()->visibleTo($request->user());
WorkingPaper::query()->visibleTo($request->user());
AuditEvidence::query()->visibleTo($request->user());
```

Record and transition endpoints must also call `$this->authorize(...)`.
Currently registered policies cover `AuditEngagement`, `EntryConference`, `AuditEvidence`,
`AuditIssue`, `AuditFinding`, `WorkingPaper`, `ExitConference`, and
`AuditReport`.

Auditee Representatives receive only communicated findings for their office,
covered exit-conference records, and issued reports addressed to their user or
office. Read-Only Users receive only issued reports naming their user as a
recipient. Review, approval, issue, authorization, and closure operations also
enforce preparer/originator separation.

### 8.2 Engagement Registry endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements` | Search, filter, sort, paginate, and include archived engagements |
| `GET` | `/api/aems/engagements/import-options` | List approved IAP engagements that have never been imported |
| `POST` | `/api/aems/engagements/import` | Create an engagement from an approved IAP item |
| `POST` | `/api/aems/engagements` | Create a Draft special/unplanned engagement with recorded external authority; internal authorization remains a controlled lifecycle transition |
| `GET` | `/api/aems/engagements/{engagement}` | Return coverage, team, event, lineage, and snapshot details |
| `GET` | `/api/aems/engagements/{engagement}/scope` | SCR-212 scope contract, one-office status, structured Area/Focus coverage, and lineage discriminator |
| `PUT` | `/api/aems/engagements/{engagement}/scope` | Save one-office scope boundaries, limitations, source variance, and structured Area/Focus metadata with optimistic locking |
| `PUT` | `/api/aems/engagements/{engagement}` | Update an editable engagement with optimistic locking |
| `DELETE` | `/api/aems/engagements/{engagement}` | Soft-archive an engagement |
| `POST` | `/api/aems/engagements/{engagement}/restore` | Restore an archived engagement when no active duplicate exists |

The collection accepts `search`, `sourceType`, `status`, `officeId`,
`auditAreaId`, `includeArchived`, `sortBy`, `sortDirection`, `page`, and
`perPage`. Every query is constrained by the engagement-level visibility scope.

An IAP import stores both direct lineage identifiers and an immutable
`source_snapshot`. The snapshot preserves the plan and version, approval,
prioritization decision and rank, original risk scores and residual-risk level,
audit-universe subject, offices, audit areas/focuses, objectives, scope, dates,
and planned person-days. Later IAP changes therefore do not rewrite the
engagement's planning baseline.

### 8.3 Audit Team endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/team` | Current team, candidates, history, resource summary, and warnings |
| `POST` | `/api/aems/engagements/{engagement}/team` | Assign a Supervisor, Team Leader, Auditor, Reviewer, Specialist, or Authorized Participant |
| `PUT` | `/api/aems/engagements/{engagement}/team/{member}` | Update role, effort, dates, or notes |
| `POST` | `/api/aems/engagements/{engagement}/team/{member}/reassign` | End the old assignment and create a replacement with linked history |
| `DELETE` | `/api/aems/engagements/{engagement}/team/{member}` | End and soft-delete an assignment with a required reason |

### 8.3.1 Team safeguards and ARMIS readiness endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/team/safeguards` | ARMIS provider status, competency/capacity/availability checks, person-day reconciliation, declarations, and assessment history |
| `POST` | `/api/aems/engagements/{engagement}/team/safeguards/assess` | Record an immutable pending provider and independence assessment for separate decision |
| `POST` | `/api/aems/engagements/{engagement}/team/safeguards/approve` | Independently approve a blocker-free assessment and create an immutable approved baseline |
| `POST` | `/api/aems/engagements/{engagement}/team/{member}/safeguards/declarations` | Submit a versioned Objectivity, Conflict-of-Interest, or Independence declaration |
| `POST` | `/api/aems/engagements/{engagement}/team/{member}/safeguards/declarations/{declaration}/review` | Accept or return a current declaration; the assigned resource is view-only, while CIAS Management may review a declaration it prepared for that resource |

Safeguard declaration payloads require `declarationType`, `outcome`, and a
statement. `DISCLOSED` outcomes require a mitigation plan before assessment;
`CONFLICT` remains a blocker. An accepted declaration cannot be overwritten;
submitting a correction requires `revisionReason` and creates a new version.
Optional `evidenceDocumentVersionId` values must reference an exact Core
`document_versions` row. Assessments capture the resolved ARMIS provider mode,
current ARMIS checks, effort reconciliation, blockers, warnings, and actor
decisions.

The permission codes are `aems.team.safeguard_view`,
`aems.team.safeguard_declare`, `aems.team.safeguard_review`, and
`aems.team.safeguard_approve`. Approval is CIAS Management-only and is
separate from the reviewer/assessor. `ARMIS_AUTHORITATIVE` is the only
operational mode; no IAP fallback or provider-reconciliation freshness gate is
used. Missing ARMIS data, competency/capacity/leave/workload conflicts,
unresolved declarations, or unreconciled person-days still block the aggregate
AEMS authorization and fieldwork gates.

### 8.4 Audit Engagement Order endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/aeo` | AEO workspace, versions, workflow events, readiness, and the responsible-account authority matrix |
| `POST` | `/api/aems/engagements/{engagement}/aeo` | Create the initial immutable draft version |
| `PUT` | `/api/aems/engagements/{engagement}/aeo/{order}` | Create a new immutable content version |
| `POST` | `/api/aems/engagements/{engagement}/aeo/{order}/transition` | Submit, review, return, resubmit, approve, or issue |
| `POST` | `/api/aems/engagements/{engagement}/aeo/{order}/revise` | Start a formal revision from an approved or issued order |
| `GET` | `/api/aems/engagements/{engagement}/aeo/{order}/pdf` | Download the exact approved AEO version |
| `GET` | `/api/aems/aeo-acknowledgements` | Recipient-scoped issued AEO transmittals for the signed-in user or office |
| `POST` | `/api/aems/aeo-acknowledgements/{distribution}/acknowledge` | Record recipient acknowledgement without opening the internal AEMS workspace |
| `GET` | `/api/aems/aeo-acknowledgements/{distribution}/pdf` | Protected download of the exact approved and issued AEO version addressed to the signed-in recipient |

All AEO mutations require the current `lockVersion`. The active CIAS Head may
record review of an AEO she prepared as the documented single-authority
exception and, when no alternate CIAS Management authority is available, may
also approve and issue that same AEO. Otherwise approval and issuance use the
separate active `cias_management` authorities. The workspace authority matrix
identifies candidate accounts while workflow events and signatories record the
actual actor, username, position, timestamp, and signature reference. Auditee
recipients acknowledge issued copies through the recipient-scoped CMS portal;
they do not receive internal AEMS workspace access. The AEO workspace exposes
engagement-scoped office and auditee-representative selectors for transmittal
recipients; arbitrary user or office IDs are rejected by the backend. The
recipient portal displays the immutable issued version and provides an
authenticated protected PDF download for that exact version.

### 8.5 Audit Engagement Plan endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/aep` | AEP content, versions, risk lineage, team, and workflow history |
| `POST` | `/api/aems/engagements/{engagement}/aep` | Create the initial immutable AEP version after AEO issuance |
| `PUT` | `/api/aems/engagements/{engagement}/aep/{plan}` | Append an immutable draft/returned content version |
| `POST` | `/api/aems/engagements/{engagement}/aep/{plan}/transition` | Submit, review, return, resubmit, or approve |
| `POST` | `/api/aems/engagements/{engagement}/aep/{plan}/revise` | Start a formal revision of an approved AEP |

The server copies risk, prioritization, and Audit Universe lineage from the
engagement source snapshot. Clients cannot replace this lineage.

### 8.6 Audit Program endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/programs` | Program revision families, procedures, team choices, and events |
| `POST` | `/api/aems/engagements/{engagement}/programs` | Create a program from the approved AEP |
| `PUT` | `/api/aems/engagements/{engagement}/programs/{program}` | Edit current draft/returned program metadata |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/transition` | Submit, review, return, resubmit, approve, start, or complete |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/revise` | Create a documented draft revision from an approved/active baseline |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/procedures` | Add a draft procedure |
| `PUT` | `/api/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}` | Edit a draft procedure |
| `DELETE` | `/api/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}` | Soft-delete a draft procedure |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}/progress` | Record active-baseline progress, WP reference, completion, or waiver |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}/review` | Record the independent reviewer result |

Every mutation carries the parent program `lockVersion`; procedure mutations
also carry the procedure `lockVersion`.

### 8.7 Working Paper endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/working-papers` | Scoped Working Paper/Evidence workspace, active procedures, versions, events, and options |
| `POST` | `/api/aems/engagements/{engagement}/working-papers` | Create a procedure-linked draft and immutable content version 1 |
| `PUT` | `/api/aems/engagements/{engagement}/working-papers/{paper}` | Append an immutable draft/returned content version |
| `POST` | `/api/aems/engagements/{engagement}/working-papers/{paper}/transition` | Submit, return, resubmit, approve/lock, or void |
| `POST` | `/api/aems/engagements/{engagement}/working-papers/{paper}/revise` | Copy an approved version into a documented correction revision |

Submission requires population/sample descriptions and verified or locked
evidence, or a documented explanation that no attachment is required. Approval
enforces preparer separation, locks the exact evidence rows cited by the current
content version, and prevents content overwrite. Every mutation carries the
Working Paper `lockVersion`.

### 8.8 Audit Evidence endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/evidence` | Upload private evidence file and create immutable evidence/document version 1 |
| `POST` | `/api/aems/engagements/{engagement}/evidence/{evidence}/revisions` | Upload an immutable replacement version with required change reason |
| `POST` | `/api/aems/engagements/{engagement}/evidence/{evidence}/transition` | Verify metadata/checksum or void eligible evidence with a reason |
| `GET` | `/api/aems/engagements/{engagement}/evidence/{evidence}/download` | Download the exact authorized evidence version |

Evidence uploads use multipart camelCase fields. Files are stored privately as
hidden AEMS-owned Core documents. Verification checks the stored checksum;
Working Paper approval changes cited `VERIFIED` evidence to `LOCKED`. Locked
evidence cannot be voided, while replacement creates a new `DRAFT` current
version and preserves the locked historical row.

### 8.8A Evidence Request and evidence assessment endpoints (AEMS-5A)

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/evidence-requests` | Scoped Evidence Request, received-link, assessment, and eligible-evidence workspace |
| `POST` | `/api/aems/engagements/{engagement}/evidence-requests` | Create an immutable-versioned `DRAFT` Evidence Request |
| `PUT` | `/api/aems/engagements/{engagement}/evidence-requests/{evidenceRequest}` | Save a new draft request version with optimistic locking |
| `POST` | `/api/aems/engagements/{engagement}/evidence-requests/{evidenceRequest}/transition` | Submit, send, mark partial/received, assess, or close the request |
| `POST` | `/api/aems/engagements/{engagement}/evidence-requests/{evidenceRequest}/evidence` | Receive and pin an exact Evidence/Core `DocumentVersion` |
| `POST` | `/api/aems/engagements/{engagement}/evidence-assessments` | Create an immutable assessment version for the exact current evidence version |
| `POST` | `/api/aems/engagements/{engagement}/evidence-assessments/{assessment}/approve-exception` | Record the separate authorized exception decision for restricted evidence |

The request lifecycle is `DRAFT -> SUBMITTED -> SENT -> PARTIALLY_RECEIVED ->
RECEIVED -> ASSESSED -> CLOSED`. Each request version is retained in
`aems_evidence_request_versions`; each received link is retained in
`aems_evidence_request_evidence` with `document_version_id` and cannot point to
a replaced or voided evidence version.

`aems_evidence_assessments` stores immutable versions with sufficiency,
appropriateness, relevance, reliability, competence, accuracy, completeness,
corroboration, contradiction, authenticity, integrity, confidentiality,
access restrictions, limitations, evidence gaps, and exception decision
metadata. Assessment corrections create a new version and supersede the old
one. A request cannot be assessed until every received exact version has a
current eligible assessment. New uploads are marked `audit_evidence.assessment_required`;
Finding validation requires their assessment to cite the exact current Core
Document Version. Restricted/access-restricted evidence also requires a
separate approved exception. The assessor cannot approve that exception.
Historical evidence rows created before AEMS-5A retain `assessment_required =
false` for compatibility and remain governed by their existing verification
and locking rules.

Permissions are engagement-scoped: `aems.evidence-request.view/create/update/
submit/send/receive/assess/close`, `aems.evidence.assess`, and
`aems.evidence.exception_approve`. Request assessment, exception approval, and
closure are separated by role. All mutations enforce scope and optimistic
locking and emit AEMS events, Core Activity Log, Audit Trail, and protected
notifications. No endpoint returns a public document URL.

The AEMS-5B React workspace is `/audit-engagement-management/evidence`. It
uses the same engagement query parameter as the other AEMS workspaces and
supports request register/detail, receipt tracking, assessment and gap views,
restricted-evidence status, exact custody/checksum metadata, family-version
comparison, and links to Working Papers, Fieldwork, Issues, Findings, and
Reports. It is a presentation client; the server response remains the source
of truth for reporting eligibility.

### 8.9 Issue endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/issues` | Create a supported draft issue |
| `PUT` | `/api/aems/engagements/{engagement}/issues/{issue}` | Update a draft issue and its exact support links |
| `POST` | `/api/aems/engagements/{engagement}/issues/{issue}/transition` | Submit, independently validate, or apply a terminal disposition (dismiss, merge, resolve, observe, refer, close without finding, convert) |

The implemented issue states are `DRAFT`, `SUBMITTED`, `VALIDATED`,
`DISMISSED`, and `CONVERTED_TO_FINDING`; the `disposition` field carries the
professional terminal outcome. Submission requires a Working Paper
version or evidence link. Validation requires approved Working Papers and
verified/locked evidence. Terminal issues cannot be edited or deleted.

### 8.10 Finding workspace and lifecycle endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/findings-workspaces` | List engagements visible to an audit-team user or responsible auditee office |
| `GET` | `/api/aems/engagements/{engagement}/findings-workspace` | Scoped Issue/Finding workspace and controlled options |
| `POST` | `/api/aems/engagements/{engagement}/findings` | Create a draft criteria-condition-cause-effect Finding |
| `PUT` | `/api/aems/engagements/{engagement}/findings/{finding}` | Update draft content and exact support links |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/transition` | Submit, validate, communicate, request response, record non-response, or finalize |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/revisions` | Create a correction, amendment, supersession, or withdrawal revision with a mandatory reason |

Finding validation requires independent authority, approved Working Paper
versions, verified evidence, and a recommendation or documented reason for
none. Direct Fieldwork Record version links are returned and, when supplied,
must reference finalized execution records at validation. Validation locks cited evidence. Communication records an immutable
snapshot containing the finding elements, recommendations, recipients,
confidentiality, due date, and exact supporting IDs. Finalized findings are
immutable.

### 8.11 Recommendation endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/recommendations` | Add a draft recommendation |
| `PUT` | `/api/aems/engagements/{engagement}/findings/{finding}/recommendations/{recommendation}` | Edit a draft recommendation |
| `DELETE` | `/api/aems/engagements/{engagement}/findings/{finding}/recommendations/{recommendation}` | Remove a draft recommendation |

Recommendations remain editable while their Finding is not finalized. Finding
finalization stores a recommendation snapshot, changes every draft
recommendation to `FINALIZED`, and prevents content mutation. CMS lineage fields
remain reserved for an idempotent later transfer.

Finding revisions preserve the prior row, exact Working Paper/evidence/
Fieldwork links, recommendation content, and a `revisionSnapshot`. A withdrawal
creates a terminal `WITHDRAWN` revision; correction, amendment, and supersession
create an editable `DRAFT` successor. The prior finalized Finding and any
finalized recommendation snapshots are never overwritten.

### 8.12 Management Response endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses` | Responsible-office Auditee Representative creates a draft response |
| `PUT` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}` | Update the current draft response |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/attachments` | Upload a private supporting document to the current draft response |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/transition` | Submit/resubmit, start review, or request clarification |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/revisions` | Create an immutable successor after clarification |

Only the communicated responsible office can author a response. Submitted
versions remain historical; clarification creates a new current version rather
than overwriting the submitted response.

### 8.13 Auditor Rejoinder endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders` | Create a draft auditor disposition and rejoinder |
| `PUT` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}` | Update a draft rejoinder |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}/attachments` | Upload a private supporting document to the draft rejoinder |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}/finalize` | Independently finalize the rejoinder and dialogue |
| `GET` | `/api/aems/engagements/{engagement}/findings/{finding}/dialogue-attachments/{attachment}/download` | Download an authorized response or rejoinder attachment |

Disposition values are `ACCEPT`, `PARTIALLY_ACCEPT`, and `REJECT`. Finalized
responses and rejoinders are immutable and satisfy the Finding finalization
dialogue gate.

Every exchange returns its actor, creation/update dates, content, status, and
version. Attachment metadata includes the exact document-version ID, original
file name, file size, MIME type, SHA-256 checksum, uploader, and upload date.
The file remains private and is served only through the engagement- and
finding-scoped authorization boundary.

### 8.14 Aggregate lifecycle and Entry Conference endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/lifecycle` | Return states, permitted actions, cross-workflow requirements, blockers, related links, and immutable transition history |
| `POST` | `/api/aems/engagements/{engagement}/transitions/{action}` | Execute an authorized action with optimistic locking; never accepts a target status |
| `GET` | `/api/aems/entry-conference-workspaces` | List engagements available to the actor's assignment or auditee-office scope |
| `GET` | `/api/aems/engagements/{engagement}/entry-conference` | Load the office-scoped Entry Conference, reference lists, exact attachments, acknowledgements, and history |
| `POST` | `/api/aems/engagements/{engagement}/entry-conference` | Create the one official draft Entry Conference |
| `PUT` | `/api/aems/engagements/{engagement}/entry-conference/{conference}` | Update an editable conference, participants, matters, and agreements |
| `POST` | `/api/aems/engagements/{engagement}/entry-conference/{conference}/transitions/{action}` | Schedule, reschedule, mark held, circulate notes, complete, waive, or cancel |
| `POST` | `/api/aems/engagements/{engagement}/entry-conference/{conference}/acknowledgements` | Record an immutable acknowledgement with or without reservation |
| `POST` | `/api/aems/engagements/{engagement}/entry-conference/{conference}/attachments` | Pin an uploaded briefing, agenda, notes, waiver support, or other file to an exact private Core DocumentVersion |
| `GET` | `/api/aems/engagements/{engagement}/entry-conference/{conference}/attachments/{attachment}/download` | Download an authorized exact attachment version |

Entry Conference statuses are `DRAFT`, `SCHEDULED`, `RESCHEDULED`, `HELD`,
`NOTES_FOR_ACKNOWLEDGEMENT`, `ACKNOWLEDGED`, `COMPLETED`, `WAIVED`, and
`CANCELLED`. Completion re-checks issued AEO, approved AEP/program, held date,
agenda/briefing, both attendance classes, notes, and material-matter
dispositions. Waiver requires CIAS authority, reason, separation, and any
required supporting document. Completed and waived records are immutable.

### 8.15 Exit Conference endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/exit-conference-workspaces` | List conference-enabled engagements in the caller's assignment or office scope |
| `GET` | `/api/aems/engagements/{engagement}/exit-conferences` | Load schedules, participants, linked Findings, outcomes, files, and acknowledgements |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences` | Schedule a conference with Findings and participants |
| `PUT` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}` | Update or reschedule an editable conference |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/complete` | Record attendance, finding outcomes, revised dates, minutes, and lock the completion snapshot |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/transition` | Formally waive or cancel with a reason |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/attachments` | Upload an immutable private minutes or supporting document |
| `GET` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/attachments/{attachment}/download` | Download an authorized conference document |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/acknowledgements` | Invited Auditee Representative acknowledges completed minutes |

Only current formally communicated or finalized Findings from the engagement
can be selected. Completion requires attendance for every participant, an
outcome for every linked Finding, and minutes. Completed, cancelled, and waived
records are immutable; acknowledgement remains a separate append-only action.

### 8.16 Audit Report endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/report-workspaces` | List internal report workspaces or issued reports visible to the recipient |
| `GET` | `/api/aems/engagements/{engagement}/reports` | Load the report family, immutable versions, comments, recipients, and CMS transfers |
| `POST` | `/api/aems/engagements/{engagement}/reports` | Generate the initial Draft Report PDF from validated or later Findings |
| `POST` | `/api/aems/engagements/{engagement}/reports/{report}/versions` | Generate an immutable draft or final revision |
| `POST` | `/api/aems/engagements/{engagement}/reports/{report}/final` | Generate the Final Report draft using finalized Findings only |
| `POST` | `/api/aems/engagements/{engagement}/reports/{report}/transition` | Submit, return, approve, or issue |
| `GET` | `/api/aems/engagements/{engagement}/reports/{report}/versions/{version}/download` | Download an authorized private PDF version |
| `POST` | `/api/aems/engagements/{engagement}/reports/{report}/cms-transfer` | Idempotently synchronize included recommendations into CMS intake |

Each generated version preserves its arranged sections, executive summary,
selected Finding snapshots, exact Core document-version ID, PDF file name,
size, and SHA-256 checksum. Reviewer comments, recipients, approval, and
issuance metadata remain version- or family-bound as appropriate. Recipients
can access only the current issued version; confidentiality gates other
authorized downloads.

### 8.17 Engagement Tracker endpoint

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/dashboard` | Return access-scoped portfolio cards, engagement progress, alerts, and pre-closure readiness |
| `GET` | `/api/aems/dashboard/export` | Download the filtered, access-scoped Engagement Progress Report as CSV |

The endpoint accepts `search`, `status`, `officeId`, `page`, `perPage`,
`sortBy`, and `sortDirection`. Portfolio cards always represent the actor's
complete visible portfolio; filters and pagination affect the engagement
tracker list. The response includes:

- active, planning, and fieldwork engagement counts;
- overdue procedures, Working Papers awaiting review, Findings awaiting
  response, upcoming Exit Conferences, and reports pending approval;
- engagements whose currently implemented pre-closure gates are satisfied;
- 14 derived stages per engagement: AEO, AEP, Audit Program, Entry Conference, fieldwork
  procedures, Working Papers, Evidence, Findings, Management Responses, Exit
  Conference, Draft Report, Final Report, CMS transfer, and Engagement
  Closure;
- overdue and schedule alerts, overall percentage, office/status filter
  options, and pagination metadata;
- the closure gate checklist and remaining blocker labels.
- active integration metadata for Core, IAP, CMS, and the current
  ARMIS-compatible resource provider.

The endpoint uses `AuditEngagement::visibleTo()` before aggregation and record
loading. It persists no dashboard-owned status. A stage changes only when its
authoritative workflow record changes. “Ready for closure” means only that the
pre-closure gates are met. The response separately reports the formal
Completion Assessment and Closure status. Neither a tracker stage nor a 100%
value can execute `CLOSED`.

The CSV export accepts `search`, `status`, `officeId`, `sortBy`, and
`sortDirection`, requires `aems.engagement.export`, and reuses the same
visibility scope. Each export records an Activity Log and Audit Log containing
the filters, row count, file name, actor, and request context.

### 8.18 Completion Assessment and Closure endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/api/aems/engagements/{engagement}/completion-assessments` | Load revision/version history or create the current 25-criterion assessment |
| `PUT` | `/api/aems/engagements/{engagement}/completion-assessments/{assessment}` | Update editable content and criterion results with optimistic locking |
| `POST` | `/api/aems/engagements/{engagement}/completion-assessments/{assessment}/transitions/{action}` | Submit, return, resubmit, or approve through a controlled action |
| `POST` | `/api/aems/engagements/{engagement}/completion-assessments/{assessment}/items/{item}/accept-blocker` | Elevated formal acceptance of a documented unresolved blocker |
| `POST` | `/api/aems/engagements/{engagement}/completion-assessments/{assessment}/revisions` | Create a controlled current correction from an approved assessment |
| `GET`, `POST` | `/api/aems/engagements/{engagement}/closure` | Load the formal workspace or create the current Closure record |
| `PUT` | `/api/aems/engagements/{engagement}/closures/{closure}` | Update an editable Closure summary |
| `GET` | `/api/aems/engagements/{engagement}/closures/{closure}/checklist` | Return evaluated authoritative gates and sources |
| `POST` | `/api/aems/engagements/{engagement}/closures/{closure}/refresh-checklist` | Re-evaluate and persist current source-derived checklist results |
| `POST` | `/api/aems/engagements/{engagement}/closures/{closure}/transitions/{action}` | Submit, return, resubmit, approve, or atomically close |
| `GET`, `POST` | `/api/aems/engagements/{engagement}/document-index` | Load index/readiness or add an authorized supporting record |
| `POST` | `/api/aems/engagements/{engagement}/document-index/refresh` | Discover eligible exact document versions without copying files |
| `POST` | `/api/aems/engagements/{engagement}/document-index/{item}/exclude` | Exclude with mandatory reason and authority |
| `GET` | `/api/aems/engagements/{engagement}/document-index/export` | Export the access-scoped final index as CSV |
| `PUT` | `/api/aems/engagements/{engagement}/retention` | Save interim classification, custody, disposition, and legal-hold metadata |
| `POST` | `/api/aems/engagements/{engagement}/retention/{retention}/approve` | Independently approve and lock retention metadata |
| `POST` | `/api/aems/engagements/{engagement}/lessons-learned` | Record a confidential improvement item separate from issued results |
| `GET`, `POST` | `/api/aems/engagements/{engagement}/reopen-requests` | Load history or draft a written-authority exceptional request |
| `POST` | `/api/aems/engagements/{engagement}/reopen-requests/{reopen}/transitions/{action}` | Submit, approve, reject, or implement reopening |

The server accepts action codes, never arbitrary status targets. An approved
Completion Assessment or a 100% tracker value does not close the engagement.
`CLOSE_ENGAGEMENT` locks and re-evaluates the current engagement and Closure,
then atomically writes both `CLOSED` states, final snapshots, immutable events,
Activity Log, and Audit Trail. Notifications are queued after commit.

### 8.19 AEMS module integration contracts

Cross-module operations resolve through container-bound contracts:

| Contract | Current provider | Boundary |
| --- | --- | --- |
| `IapEngagementGateway` | `DatabaseIapEngagementGateway` | Lists and locks only approved/active IAP engagement sources, then preserves the imported source link |
| `CmsRecommendationGateway` | `DatabaseCmsRecommendationGateway` | Thin adapter to `CmsIntakeService`; creates one immutable intake/case/event per eligible issued recommendation and returns the same source-matching record on retry |
| `ResourcePlanningGateway` | `ConfigurableResourcePlanningGateway` | Supplies ARMIS-authoritative capacity, availability, workload inputs, competencies, and person-days; historical IAP values are lineage only |
| `EngagementRetentionProvider` | `InterimAemsRetentionProvider` | Preserves approved AEMS retention/custody snapshots behind a Core Records Management-replaceable boundary; never destroys records |

The protected integration contract is also available directly:

| Method | Endpoint | Permission | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/aems/integrations/status` | `aems.engagement.view` | Return scope-safe Core/IAP/ARMIS/CMS provider status, lineage/health checks, ownership, and security flags |

The IAP provider is strictly read-only. Import lineage is owned by the AEMS
`audit_engagements.iap_plan_engagement_id` relationship and immutable
`source_snapshot`; the legacy IAP `aem_engagement_id` value is a computed
compatibility projection and is not written by AEMS. The active-source unique
index and transactional lock prevent duplicate imports. CMS intake remains
idempotent and its source envelope contains AEMS lineage and the source
snapshot hash. AIS is intentionally absent from this contract.

The dashboard response includes `integrations.core`, `integrations.iap`,
`integrations.cms`, and `integrations.armis`. ARMIS reports
`ARMIS_AUTHORITATIVE` as the only operational mode. Historical
`ARMIS_SHADOW`/`IAP_INTERIM_FALLBACK` values may remain in old reconciliation
snapshots but are not runtime options or active providers.

### 8.20 ARMIS-1A foundation API

The ARMIS-1A backend exposes a protected, office-scoped resource registry and
read snapshot. The profile API is the first ARMIS mutation boundary; it does
not switch the AEMS provider or mutate IAP records.

| Method | Path | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/api/armis/metadata` | `armis.resource.view` | Lifecycle/category metadata and current provider status |
| GET | `/api/armis/foundation` | `armis.resource.view` | Scope-aware profiles, competency, availability, capacity, requirement, workload, and actuals snapshot |
| GET | `/api/armis/resources` | `armis.resource.view` | Filtered resource registry |
| POST | `/api/armis/resources` | `armis.resource.create` | Create a draft resource profile linked to a Core user and office |
| GET | `/api/armis/resources/{profile}` | `armis.resource.view` | Resource detail and loaded foundation records |
| GET | `/api/armis/resources/{profile}/events` | `armis.resource.view` | Immutable ARMIS workflow timeline |
| PUT | `/api/armis/resources/{profile}` | `armis.resource.update` | Optimistically locked profile update |
| POST | `/api/armis/resources/{profile}/transition` | `armis.resource.update` | Draft/active/suspended/inactive/archive transition |
| POST | `/api/armis/resources/{profile}/restore` | `armis.resource.restore` | Restore an archived profile as inactive |

Mutation requests include `lockVersion`. A stale version returns `422` and no
record is changed. Profile mutations create an Activity Log, Audit Trail row,
and append-only `armis_workflow_events` record. Resource visibility follows
the Core office scope; non-global users cannot access another office's profile.

ARMIS-1A foundation tables are `armis_resource_profiles`,
`armis_competencies`, `armis_availability_periods`,
`armis_capacity_submissions`, `armis_resource_requirements`,
`armis_requirement_competencies`, `armis_workload_allocations`,
`armis_actual_person_days`, and `armis_workflow_events`. Competency evidence
stores an exact `document_versions.id`, never a mutable document path.

Core remains authoritative for Users, Offices, Roles, Permissions, Scopes,
Audit Areas, Audit Focuses, Master Lists, private Documents and
`DocumentVersion`s, reusable workflow infrastructure, Notifications, Activity
Logs, Audit Trails, runtime settings, and document numbering. AEMS
domain-specific transitions remain code-guarded because configurable workflow
records must not create or bypass audit states. Team assignments and issued
reports, AEO/AEP review, returned Working Papers, communicated Findings, Exit
Conferences, and report review actions create deduplicated Core Notifications
after the surrounding transaction commits. The scheduled reminder command also
covers overdue procedures, Management Response deadlines, and upcoming Exit
Conferences.

### 8.21 ARMIS-2A competency and certification API

ARMIS-2A adds the controlled certification ledger on top of the ARMIS-1A
competency foundation. Competency catalogue items come from the Core
`IAP_AUDITOR_SPECIALIZATION` list (and may later come from
`ARMIS_COMPETENCY`). Evidence is pinned to an exact active Core
`document_versions` row.

| Method | Path | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/api/armis/competencies/metadata` | `armis.competency.view` | Status, proficiency, and Core catalogue metadata |
| GET | `/api/armis/competencies` | `armis.competency.view` | Scope-aware current claims; `includeHistory=1` includes revisions |
| POST | `/api/armis/competencies` | `armis.competency.manage` | Create a Draft claim for a resource profile |
| GET | `/api/armis/competencies/{competency}` | `armis.competency.view` | Read a claim and exact evidence metadata |
| GET | `/api/armis/competencies/{competency}/events` | `armis.competency.view` | Read the immutable competency event timeline |
| PUT | `/api/armis/competencies/{competency}` | `armis.competency.manage` | Edit a Draft or Returned claim with optimistic locking |
| POST | `/api/armis/competencies/{competency}/submit` | `armis.competency.manage` | Submit a current claim for independent verification |
| POST | `/api/armis/competencies/{competency}/review` | `armis.competency.verify` | Verify, Return, or Revoke a claim |
| POST | `/api/armis/competencies/{competency}/revisions` | `armis.competency.manage` | Create a new Draft correction from a Verified version |

The lifecycle is `DRAFT/RETURNED -> PENDING_VERIFICATION -> VERIFIED`, with
controlled `EXPIRED` and `REVOKED` outcomes. Verified data cannot be edited in
place. `competency_family_uuid`, `version_number`, `supersedes_id`, and
`is_current_revision` preserve lineage; a PostgreSQL partial unique index and
row-locking service transaction enforce one current claim per resource and Core
catalogue item. Submitters and resource owners are barred from independent
review. All create, update, submit, review, and revision actions write Core
Activity Log, Audit Trail, and ARMIS workflow-event records. Notifications are
generated for verification queues and review outcomes. ARMIS remains
non-authoritative for AEMS; this phase does not alter IAP records or the
`ResourcePlanningGateway` binding.

The ARMIS-2B React workspace consumes these contracts at
`/audit-resource-management/competencies` and its competency detail route.
The browser presents only backend-authorized preparation, submission, review,
and revision actions; it does not create public document URLs or bypass Core
Document Version access controls.

### 8.22 ARMIS-3A planning backend

ARMIS-3A adds the backend planning ledger without switching the AEMS provider
or modifying IAP planning records. Availability periods, annual capacity
submissions, and planned workload allocations are office-scoped and use the
shared `DRAFT -> SUBMITTED -> RETURNED/APPROVED -> LOCKED` workflow. Draft and
Returned records may be edited with `lockVersion`; approved and locked records
are immutable. Corrections to completed capacity or workload records create a
new current revision and preserve `supersedes_id` and the version number.

| Method | Path | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/api/armis/planning/metadata` | `armis.availability.view` | Planning statuses, types, years, and workflow metadata |
| GET | `/api/armis/availability` | `armis.availability.view` | Current scope-aware availability periods; `includeHistory=1` includes revisions |
| POST | `/api/armis/availability` | `armis.availability.manage` | Create a Draft period; overlapping current periods are rejected |
| GET/PUT | `/api/armis/availability/{availability}` | `armis.availability.view/manage` | Read or edit a current Draft/Returned period with optimistic locking |
| POST | `/api/armis/availability/{availability}/submit` | `armis.availability.manage` | Submit a current period for independent review |
| POST | `/api/armis/availability/{availability}/revisions` | `armis.availability.manage` | Create a new Draft correction from an approved or locked period |
| POST | `/api/armis/availability/{availability}/review` | `armis.availability.review` | Approve or Return a submitted period |
| POST | `/api/armis/availability/{availability}/lock` | `armis.availability.approve` | Lock an approved period |
| GET/POST | `/api/armis/capacity` | `armis.capacity.view/manage` | List or create annual capacity versions |
| GET/PUT | `/api/armis/capacity/{capacity}` | `armis.capacity.view/manage` | Read or edit a current Draft/Returned capacity version |
| POST | `/api/armis/capacity/{capacity}/submit` | `armis.capacity.manage` | Submit capacity for review |
| POST | `/api/armis/capacity/{capacity}/review` | `armis.capacity.review` | Approve or Return submitted capacity |
| POST | `/api/armis/capacity/{capacity}/lock` | `armis.capacity.approve` | Lock approved capacity |
| GET/POST | `/api/armis/workload` | `armis.workload.view/manage` | List or create planned workload allocations |
| GET/PUT | `/api/armis/workload/{workload}` | `armis.workload.view/manage` | Read or edit a current Draft/Returned workload version |
| POST | `/api/armis/workload/{workload}/submit` | `armis.workload.manage` | Submit workload for review |
| POST | `/api/armis/workload/{workload}/review` | `armis.workload.review` | Approve or Return; approval requires capacity and cannot exceed it |
| POST | `/api/armis/workload/{workload}/lock` | `armis.workload.approve` | Lock approved workload |
| GET | `/api/armis/utilization?fiscalYear=YYYY` | `armis.capacity.view` | Scope-aware capacity, planned workload, availability, remaining days, and utilization summary |

Every mutation records an ARMIS workflow event, Core Activity Log, and Audit
Trail row, and review queues/outcomes create Core notifications after commit.
The submitter and linked resource owner cannot independently review the same
record. Utilization is a planning read model using only current approved or
locked ARMIS capacity, workload, and availability; ARMIS-4A actual person-days
remain a separate non-authoritative ledger. The ARMIS-3B React workspace consumes these
contracts at `/audit-resource-management/planning`. It provides
Overview/Utilization, Availability Calendar, Capacity, and Workload tabs with
responsive tables, search and status filters, fiscal-year selection,
permission-aware create/edit, submit, independent review, lock, and correction
revision actions. It displays historical IAP resource lineage for comparison;
ARMIS is the sole operational provider and the page does not expose actual
person-days or public document URLs.

### 8.23 ARMIS-4A assignments and actual person-days

ARMIS-4A adds a separate assignment and actuals ledger linked to AEMS
engagements and ARMIS resource profiles. It does not modify the existing AEMS
`engagement_teams` rows or switch the `ResourcePlanningGateway` provider.

| Method | Path | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/api/armis/assignments/metadata` | `armis.assignment.view` | Assignment/actual statuses, roles, proficiency, and rule metadata |
| GET | `/api/armis/assignments` | `armis.assignment.view` | Scope-aware current assignments; `includeHistory=1` includes revisions |
| POST | `/api/armis/assignments` | `armis.assignment.manage` | Create a Draft engagement assignment and required-competency snapshot |
| GET/PUT | `/api/armis/assignments/{assignment}` | `armis.assignment.view/manage` | Read or edit a current Draft/Returned assignment with optimistic locking |
| POST | `/api/armis/assignments/{assignment}/submit` | `armis.assignment.manage` | Validate conflicts, capacity, and competencies before submission |
| POST | `/api/armis/assignments/{assignment}/review` | `armis.assignment.review` | Independently Approve or Return a submitted assignment |
| POST | `/api/armis/assignments/{assignment}/lock` | `armis.assignment.approve` | Lock an approved assignment |
| POST | `/api/armis/assignments/{assignment}/revisions` | `armis.assignment.manage` | Create an immutable correction revision |
| GET | `/api/armis/assignments/{assignment}/conflicts` | `armis.assignment.view` | Return overlap, availability, capacity, and competency conflicts |
| GET | `/api/armis/actuals` | `armis.actuals.view` | Scope-aware actual person-day records; `includeHistory=1` includes revisions |
| POST | `/api/armis/actuals` | `armis.actuals.record` | Create Draft actual person-days for an approved/locked assignment |
| GET/PUT | `/api/armis/actuals/{actual}` | `armis.actuals.view/record` | Read or edit a current Draft/Returned actual record |
| POST | `/api/armis/actuals/{actual}/submit` | `armis.actuals.record` | Submit actuals for independent review |
| POST | `/api/armis/actuals/{actual}/review` | `armis.actuals.review` | Independently Approve or Return actuals |
| POST | `/api/armis/actuals/{actual}/lock` | `armis.actuals.approve` | Lock approved actuals |
| POST | `/api/armis/actuals/{actual}/revisions` | `armis.actuals.revise` | Create a correction revision; variance reasons are required for overruns |

Assignment and actuals approval is atomic and row-locked. Current hard rules
include active resource/profile and engagement-office checks, overlapping
assignment prevention, approved-capacity enforcement, engagement planned-day
limits, approved availability conflicts, current verified competency claims,
assignment/actual date bounds, and optimistic-lock checks. The submitter and
resource owner cannot independently review the same record. Every mutation
records an ARMIS workflow event, Activity Log, Audit Trail, and review/outcome
notification. The preceding paragraph describes the historical pre-cutover
checkpoint. ARMIS actuals are now authoritative for AEMS; provider activation
and rollback compatibility endpoints return `409` and cannot change the
operational read path.

### 8.23.1 ARMIS-4B assignment and actuals workspace

The React workspace is available at
`/audit-resource-management/assignments`, protected by
`armis.assignment.view`. It consumes the 8.23 APIs and provides separate
Assignments and Actual Person-Days sections with permission-aware workflow
actions, conflict inspection, revision controls, search, status filters, and
responsive loading/empty/error states. The page does not make backend
eligibility decisions and does not change AEMS team or provider data.

### 8.24 ARMIS-5A reports and administration API

ARMIS-5A provides a backend-only, scope-aware reporting boundary. Report runs
pin visible resource and assignment identifiers, filters, the source-query
version, result rows, and a SHA-256 checksum in immutable `armis_report_runs`
records. Exports are immutable private artifacts in `armis_report_exports` and
are served only through authenticated, permission-protected download routes.

| Method | Route | Permission | Contract |
| --- | --- | --- | --- |
| GET | `/api/armis/reports` | `armis.report.view` | Report catalog, columns, filters, formats, scope, and provider status |
| GET | `/api/armis/reports/runs` | `armis.report.view` | Scope-visible immutable report runs and export metadata |
| GET | `/api/armis/reports/runs/{run}` | `armis.report.view` | One scope-rechecked report snapshot |
| POST | `/api/armis/reports/{report}/generate` | `armis.report.view` | Generate a reproducible snapshot from visible ARMIS ledgers |
| POST | `/api/armis/reports/runs/{run}/exports` | `armis.report.export` | Generate or reuse a protected CSV/PDF artifact |
| GET | `/api/armis/report-exports/{export}/download` | `armis.report.export` | Authenticated private download with `X-AGIS-Checksum-SHA256` |
| GET | `/api/armis/administration` | `armis.report.view` | Scope, workflow, notification, provider, and hardening status |

The available report codes are `resource-utilization`, `assignment-register`,
`capacity-workload`, and `competency-coverage`. Supported filters are
`search`, `status`, `officeId`, and `fiscalYear` where applicable. CSV export
mitigates spreadsheet formula injection by prefixing values beginning with
`=`, `+`, `-`, or `@`; PDF/CSV files are generated by the backend and never
expose a public document URL. Report runs and exports are immutable and every
generation, export, and download records Core Activity Log and Audit Trail
entries.

ARMIS is now the active AEMS `ResourcePlanningGateway`. The administration
contract reports ARMIS authority, historical IAP lineage, and any explicit
rollback mode.

### 8.24.1 ARMIS-5B reports and administration workspace

The protected React route `/audit-resource-management/reports` consumes the
8.24 contracts. Its Reports tab selects one of the four catalog definitions,
sends supported filters to the backend, renders the immutable snapshot and
run history, and requests CSV/PDF artifacts through the protected export
routes. The Administration tab presents the provider status, scope,
permissions, workflow status families, notification counts, and hardening
flags returned by `GET /api/armis/administration`.

The UI checks `armis.report.export` before presenting export actions and uses
authenticated download requests. It does not make professional decisions,
change provider authority, expose storage paths, or mutate report runs and
exports. Focused desktop/mobile coverage is in
`tests/e2e/armis-reports.spec.js`; provider authority, reconciliation, and
AIS integration remains out of scope; provider authority, reconciliation,
monitoring, and deployment hardening are documented in ARMIS-6B through ARMIS-7C.

### 8.25 ARMIS-6A provider adapter and mode contract

ARMIS-6A adds no new public route. The existing AEMS dashboard and ARMIS
metadata, foundation, planning, reports, and administration responses consume
the mode-aware `ResourcePlanningGateway` status. The status includes:

- `mode`: always `ARMIS_AUTHORITATIVE`;
- `configuredMode`: the normalized runtime setting;
- `activeProvider`: the ARMIS provider used by AEMS after cutover;
- `shadowProvider`: `null` (historical comparison providers are not active);
- `shadowAvailable`: `false`;
- `supportedModes`: `["ARMIS_AUTHORITATIVE"]`; and
- `authoritySwitchAllowed`: `false`.

The protected Core endpoint `PUT /api/system-configurations` accepts the
`armis_provider_mode` setting for users with `system_configuration.manage`.
`ARMIS_AUTHORITATIVE` is the operational setting; the dedicated
`armis:resource-backfill --approve --activate` command records the immutable
cutover decision. Configuration changes retain Core Activity Log and Audit
Trail behavior.

`ArmisResourcePlanningGateway` reads approved/current ARMIS records and maps
capacity, availability, competency, requirement, assignment actuals, and
engagement actuals to the AEMS gateway shape. It is read-only and does not
modify IAP, AEMS, CMS, or ARMIS ledgers.

### 8.26 ARMIS-6B reconciliation and authority gate

ARMIS-6B adds protected provider integration routes:

| Method | Route | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/api/armis/provider/status` | `armis.provider.view` | Active provider, reconciliation, and authority-gate status |
| GET | `/api/armis/provider/reconciliations` | `armis.provider.view` | Scope-filtered immutable reconciliation runs |
| POST | `/api/armis/provider/reconciliations` | `armis.provider.reconcile` | Generate an IAP-versus-ARMIS snapshot for a fiscal year |
| GET | `/api/armis/provider/reconciliations/{run}` | `armis.provider.view` | View exact snapshot rows, checksum, review, and authority history |
| POST | `/api/armis/provider/reconciliations/{run}/review` | `armis.provider.review` | Record one immutable independent review and discrepancy decisions |
| POST | `/api/armis/provider/reconciliations/{run}/activate` | `armis.provider.switch` | Compatibility endpoint; returns `409` because ARMIS is already sole provider |
| POST | `/api/armis/provider/rollback` | `armis.provider.rollback` | Compatibility endpoint; returns `409` because provider rollback is disabled |

`armis_provider_reconciliation_runs` stores the source query version, fiscal
year, provider mode, authorized scope, normalized comparison rows, summary,
and SHA-256 checksum. Each row compares the IAP interim provider with the
approved/current ARMIS adapter for capacity, skills, unavailability,
requirements, engagement actuals, or assignment actuals. Runs cannot be
updated or deleted. `armis_provider_reconciliation_reviews` and
`armis_provider_authority_decisions` are separate immutable records.

Reconciliation is a historical IAP-versus-ARMIS quality snapshot. It can be
independently reviewed, but activation and rollback routes now return `409`
because ARMIS is already the sole operational provider. No provider switch or
fallback read is possible. Generation and review operations continue to emit
ARMIS workflow events, Core Activity Log/Audit Trail records, and notifications.

### 8.27 ARMIS-6C reconciliation and authority workspace

The protected React route `/audit-resource-management/provider-reconciliation`
consumes the
provider status, reconciliation list/detail, review, activation, and rollback
contracts above. It renders immutable snapshot rows and discrepancy decisions,
keeps provider status and authority history read-only, and exposes mutation
dialogs only when the backend returns the required `availableActions`. The
workspace does not calculate discrepancy eligibility or write provider mode
through the generic configuration endpoint. Desktop and mobile coverage is
provided by `tests/e2e/armis-provider-reconciliation.spec.js`.

### 8.28 ARMIS-6D provider monitoring API

Provider health and cutover verification are separate immutable monitoring
records:

| Method | Route | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/api/armis/provider/monitoring/status` | `armis.provider.view` | Current provider health, authority, and latest-check summary |
| GET | `/api/armis/provider/monitoring/checks` | `armis.provider.view` | Scope-filtered monitoring and cutover-check history |
| POST | `/api/armis/provider/monitoring/checks` | `armis.provider.monitor` | Run and record one provider health/cutover check |
| GET | `/api/armis/provider/monitoring/checks/{check}` | `armis.provider.view` | View an immutable check and its evidence |

Monitoring checks record provider mode, expected/observed values, pass/fail
result, source snapshot, checksum, actor, and timestamps. Failures create Core
notifications and Activity Log/Audit Trail records. Monitoring does not activate,
roll back, or otherwise change provider authority.

### 8.29 ARMIS-7 verification and deployment tools

ARMIS-7A is a security/regression gate over protected ARMIS routes and
workspaces. ARMIS-7B adds the read-only Laravel preflight command:

```text
php artisan armis:deployment-check
php artisan armis:deployment-check --strict
```

The strict command verifies migrations, PostgreSQL, provider mode and authority
lineage, HTTPS/application configuration, debug state, writable runtime paths,
and private document storage. ARMIS-7C adds the operator-invoked PowerShell
smoke verifier:

```powershell
scripts/verify-armis-render.ps1 -BaseUrl https://<service>.onrender.com
```

The smoke verifier checks `/health`, the compiled SPA shell, nested ARMIS route
fallback, anonymous ARMIS API rejection, and deployment security headers. Both
tools are read-only; neither bypasses authentication, performs migrations, or
changes provider authority.

## 9. Reference/master-list codes

Important configurable list families include:

```text
OFFICE_TYPE
SECTOR
POSITION
EMPLOYMENT_TYPE
AUDIT_AREA_TYPE
DOCUMENT_TYPE
DOCUMENT_CONFIDENTIALITY
AEMS_EVIDENCE_CATEGORY
AEMS_EVIDENCE_SOURCE_TYPE
RISK_LEVEL
IAP_AUDIT_UNIVERSE_SUBJECT_TYPE
IAP_PLANNING_PERIOD_TYPE
IAP_PLANNING_PRIORITY
IAP_RISK_CRITERION
IAP_ENGAGEMENT_TYPE
IAP_AUDIT_APPROACH
IAP_COMMENT_TYPE
IAP_ATTACHMENT_TYPE
IAP_AUDITOR_SKILL
IAP_UNAVAILABILITY_TYPE
```

Use the seeded `MasterListSeeder` as the source of exact current values.

## 10. Runtime configuration keys

| Key | Type |
| --- | --- |
| `system_name`, `system_short_name`, `organization_name`, `system_version` | string |
| `pagination_size`, `session_timeout_minutes` | integer |
| `date_format`, `timezone` | string |
| `password_min_length`, `failed_login_limit`, `account_lock_minutes` | integer |
| `fiscal_year_start_month`, `iap_default_annual_person_days` | integer |
| `document_upload_max_mb`, `notification_refresh_seconds` | integer |
| `document_number_format`, `iap_plan_number_format`, `siap_plan_number_format` | string |
| `risk_period_number_format`, `prioritization_number_format` | string |
| `default_risk_level_code`, `default_workflow_sla_hours` | string/integer |
| `workflow_mapping_core`, `workflow_mapping_iap` | workflow code |
| `logo_url` | managed URL |
| `mail_enabled`, `mail_mailer`, `mail_host`, `mail_port` | boolean/string/integer |
| `mail_encryption`, `mail_username`, `mail_password` | string/encrypted secret |
| `mail_from_address`, `mail_from_name` | string |

## 11. Source-of-truth rule

This reference describes the current implementation. When behavior and this file
disagree, verify `backend/routes/api.php`, Form Requests, services, migrations,
and feature tests, then update the documentation in the same change.

## 12. CMS-4B frontend integration

The CMS Progress Update React workspace is recommendation-specific:

```text
/compliance-management/recommendations/{recommendationId}/progress-updates
/compliance-management/recommendations/{recommendationId}/progress-updates/{progressUpdateId}
```

It consumes the CMS-4A Progress Update routes listed in the CMS workflow
design. The client uses the existing `cmsApi` request wrapper, Sanctum/CSRF
handling, Core document confidentiality options, and protected download helper.
No direct storage URL or PostgreSQL access is permitted. The UI treats
`availableActions`, server status, scope-safe 404 responses, and lock-version
conflicts as authoritative.

## CMS-5B validation frontend

The React routes are:

```text
/compliance-management/recommendations/{recommendationId}/validations
/compliance-management/recommendations/{recommendationId}/validations/{validationId}
```

They call the CMS-5A validation routes using camelCase payloads and the shared
request wrapper. The creation and replacement selectors call:

```text
GET /api/cms/recommendations/{recommendation}/validation-options
```

The response contains only eligible recorded Progress Update Versions, safe
Primary Validator display fields, case lock/context, and unavailable reasons.
The endpoint reuses the CMS-5A aggregate independence and confidentiality
guards; the frontend never loads the unrestricted User Registry.

## CMS-6A target-date extension API

The backend exposes the following additive routes:

```text
GET    /api/cms/recommendations/{recommendation}/extensions
GET    /api/cms/recommendations/{recommendation}/extensions/history
GET    /api/cms/recommendations/{recommendation}/extension-options
POST   /api/cms/recommendations/{recommendation}/extensions
GET    /api/cms/extensions/{extension}
PUT    /api/cms/extensions/{extension}/versions/{version}
POST   /api/cms/extensions/{extension}/versions/{version}/transitions/submit
POST   /api/cms/extensions/{extension}/versions/{version}/transitions/start-review
POST   /api/cms/extensions/{extension}/versions/{version}/transitions/return
POST   /api/cms/extensions/{extension}/versions/{version}/transitions/recommend
POST   /api/cms/extensions/{extension}/versions/{version}/transitions/approve
POST   /api/cms/extensions/{extension}/versions/{version}/transitions/reject
POST   /api/cms/extensions/{extension}/versions/{version}/revisions
POST   /api/cms/extensions/{extension}/versions/{version}/evidence
GET    /api/cms/extension-evidence/{evidence}/download
DELETE /api/cms/extension-evidence/{evidence}
```

The migration creates request families, immutable versions, assessments,
decisions, exact evidence links, and append-only target-date history. The
extension permissions are `cms.extension.*` and `cms.extension-evidence.*`;
the verified CMS permission total at that increment was 76; CMS-9A raises the
current runtime total to 104. Existing
legacy `aem.*` compatibility permissions are retained.

### CMS-6B frontend integration

The React workspace uses the existing `cmsApi` request wrapper and the CMS-6A
routes above. Its protected recommendation-specific routes are:

```text
/compliance-management/recommendations/:recommendationId/extensions
/compliance-management/recommendations/:recommendationId/extensions/:extensionId
```

The frontend sends only CMS-6A camelCase payload fields and current lock
versions. It never submits baseline source IDs, actors, statuses, approved
dates, or case-status changes. Evidence uploads use multipart requests and
protected authenticated downloads; internal storage paths are never rendered.

## CMS-7A escalation management API

CMS-7A adds a backend-only, scoped escalation workflow. The escalation family
is separate from recommendation case status and uses immutable notice and
response versions.

```text
GET  /api/cms/recommendations/{recommendation}/escalations
GET  /api/cms/recommendations/{recommendation}/escalation-options
POST /api/cms/recommendations/{recommendation}/escalations
GET  /api/cms/escalations/{escalation}
PUT  /api/cms/escalations/{escalation}/notice-versions/{version}
POST /api/cms/escalations/{escalation}/notice-versions/{version}/transitions/submit
POST /api/cms/escalations/{escalation}/notice-versions/{version}/transitions/start-review
POST /api/cms/escalations/{escalation}/notice-versions/{version}/transitions/return
POST /api/cms/escalations/{escalation}/notice-versions/{version}/transitions/issue
POST /api/cms/escalations/{escalation}/notice-versions/{version}/revisions
POST /api/cms/escalations/{escalation}/acknowledgements
GET  /api/cms/escalations/{escalation}/response
POST /api/cms/escalations/{escalation}/response
PUT  /api/cms/escalation-responses/{response}/versions/{version}
POST /api/cms/escalation-responses/{response}/versions/{version}/transitions/submit
POST /api/cms/escalation-responses/{response}/versions/{version}/transitions/start-review
POST /api/cms/escalation-responses/{response}/versions/{version}/transitions/return
POST /api/cms/escalation-responses/{response}/versions/{version}/transitions/accept
POST /api/cms/escalation-responses/{response}/versions/{version}/revisions
POST /api/cms/escalations/{escalation}/resolve
```

Notice and response evidence pins exact Core Document Versions with checksum
and confidentiality snapshots. The options endpoint returns trigger codes,
source target-date and overdue context, pending extension context, active
monitor, prior escalation count, safe recipient summaries, lock version, and
authoritative unavailability reasons. The permission catalogue adds fourteen
`cms.escalation.*` permissions and four `cms.escalation-evidence.*` permissions.

The React workspace is available at the recommendation-scoped routes
`/compliance-management/recommendations/:recommendationId/escalations` and
`/compliance-management/recommendations/:recommendationId/escalations/:escalationId`.
It uses the existing `cmsApi` request wrapper, Sanctum-protected multipart
uploads, and authenticated evidence downloads. Notice/response drafts are the
only editable versions; returned, issued, accepted, and resolved records are
rendered read-only and corrections use revision endpoints. The frontend keeps
source snapshots separate from current recommendation context and does not
change recommendation status or closure state.

At the CMS-7A checkpoint, the resources left `availableActions` empty; until the
backend added those computed actions, the UI combined exact status and seeded
permission visibility while Laravel remained authoritative for every mutation.
The following historical boundary note records the later increments.
> Historical boundary note: The CMS-7A API description above predates the
> CMS-8 through CMS-10 increments. Formal closure, Accepted-Risk,
> No-Longer-Applicable, and controlled reopening APIs are implemented below;
> Historical boundary note: scheduled automation was deferred at the CMS-7A
> checkpoint; CMS-11A automation endpoints are documented below. CMS reports/
> exports remain deferred to CMS-12.

> Current-state correction: the preceding paragraph is the CMS-7A checkpoint
> contract. CMS-8 through CMS-12B now provide closure, dispositions, reopening,
> automation, reports, and protected CSV/PDF exports. AIS-5D is implemented as a
> hardened read-only analytical and protected reporting surface with private
> responses, rate limits, and audit events; operational AIS writes
> remain reserved for later phases. ARMIS is documented separately
> and its provider authority is independently gated.

## CMS-8A closure API

Closure endpoints are available under `/api/cms/recommendations/{recommendation}/closure-requests`, `/closure-options`, and `/api/cms/closure-requests/{closureRequest}`. Version transitions are exposed through `submit`, `start-review`, `return`, `recommend`, `approve`, `reject`, and `revisions` routes. The backend selects and pins the finalized Validation, accepted Action Plan, and recorded Progress Update lineage; clients cannot submit source IDs or case statuses.

The closure schema consists of `cms_closure_requests`, `cms_closure_request_versions`, `cms_closure_review_assessments`, `cms_closure_decisions`, and `cms_closure_evidence_links`. Closure evidence pins exact Core Document Versions and is never a second storage system.

The React client uses the existing `cmsApi` request wrapper for closure options, request families, version mutations, transitions, revisions, and protected evidence operations. It never submits a target case status or constructs a storage URL.

## CMS-9A disposition API and data reference

CMS-9A adds the shared disposition request family and immutable version,
assessment, decision, and evidence-link tables:

```text
cms_disposition_requests
cms_disposition_request_versions
cms_disposition_review_assessments
cms_disposition_decisions
cms_disposition_evidence_links
```

The request supports `ACCEPTED_RISK` and `NO_LONGER_APPLICABLE`. Evidence links
store both the Core `document_id` and exact `document_version_id`, checksum, and
confidentiality snapshot. Submitted versions, assessments, decisions, and
evidence links cannot be edited or deleted.

Protected endpoints are:

```text
GET    /api/cms/recommendations/{recommendation}/dispositions
GET    /api/cms/recommendations/{recommendation}/disposition-options
POST   /api/cms/recommendations/{recommendation}/dispositions
GET    /api/cms/disposition-requests/{request}
PUT    /api/cms/disposition-requests/{request}/versions/{version}
POST   /api/cms/disposition-requests/{request}/versions/{version}/transitions/submit
POST   /api/cms/disposition-requests/{request}/versions/{version}/transitions/start-review
POST   /api/cms/disposition-requests/{request}/versions/{version}/transitions/return
POST   /api/cms/disposition-requests/{request}/versions/{version}/transitions/recommend
POST   /api/cms/disposition-requests/{request}/versions/{version}/transitions/approve
POST   /api/cms/disposition-requests/{request}/versions/{version}/transitions/reject
POST   /api/cms/disposition-requests/{request}/versions/{version}/revisions
POST   /api/cms/disposition-requests/{request}/versions/{version}/evidence
DELETE /api/cms/disposition-evidence/{evidence}
GET    /api/cms/disposition-evidence/{evidence}/download
```

The CMS-9A permission catalogue contains ten `cms.disposition.*` permissions
and four `cms.disposition-evidence.*` permissions. The verified runtime CMS
permission total was **104** at CMS-9A and is **118** after CMS-10A; all legacy
`aem.*` compatibility permissions remain unchanged. The React disposition
workspace is implemented in CMS-9B; the reopening workspace is reserved for
CMS-10B.

### CMS-9B frontend contract

The React client uses the existing `cmsApi` request wrapper and the protected
recommendation-scoped routes:

```text
/compliance-management/recommendations/:recommendationId/dispositions
/compliance-management/recommendations/:recommendationId/dispositions/:dispositionId
```

Frontend actions are shown only when both the current frontend permission and
backend `availableActions` allow them. Readiness and creation reasons are
rendered from the backend; React does not recalculate eligibility or submit a
case status. Evidence is linked to an exact Core Document Version and downloaded
through the authenticated endpoint. Internal storage paths are never displayed.

The CMS-9A request Resource currently exposes the authoritative current version
and does not include the complete historical `versions` collection. CMS-9B
therefore renders the current version and a safe history-unavailable state rather
than inventing historical records. Extending that Resource is a future
backward-compatible backend contract decision.

## CMS-10A reopening API and data reference

CMS-10A adds `cms_reopening_requests`, immutable
`cms_reopening_request_versions`, `cms_reopening_review_assessments`,
`cms_reopening_decisions`, and `cms_reopening_evidence_links`. Case cycle
lineage is held in `active_cycle_number`, `reopening_count`,
`last_reopened_at`, and `last_reopening_decision_id`. Evidence pins an exact
Core `DocumentVersion`, checksum, and confidentiality snapshot.

Protected routes are listed below; all require authentication and the matching
`cms.reopening.*` or `cms.reopening-evidence.*` permission:

```text
GET    /api/cms/recommendations/{recommendation}/reopenings
GET    /api/cms/recommendations/{recommendation}/reopening-options
POST   /api/cms/recommendations/{recommendation}/reopenings
GET    /api/cms/reopening-requests/{request}
PUT    /api/cms/reopening-requests/{request}/versions/{version}
POST   /api/cms/reopening-requests/{request}/versions/{version}/transitions/submit
POST   /api/cms/reopening-requests/{request}/versions/{version}/transitions/start-review
POST   /api/cms/reopening-requests/{request}/versions/{version}/transitions/return
POST   /api/cms/reopening-requests/{request}/versions/{version}/transitions/recommend
POST   /api/cms/reopening-requests/{request}/versions/{version}/transitions/approve
POST   /api/cms/reopening-requests/{request}/versions/{version}/transitions/reject
POST   /api/cms/reopening-requests/{request}/versions/{version}/revisions
POST   /api/cms/reopening-requests/{request}/versions/{version}/evidence
DELETE /api/cms/reopening-evidence/{evidence}
GET    /api/cms/reopening-evidence/{evidence}/download
```

Recommendation Detail and dashboard responses expose reopening summaries,
readiness, active-cycle counters, request actions, and review/decision lineage.
CMS-10B consumes these routes through the existing same-origin `cmsApi` client;
no alternate API client, public document URL, or frontend-only endpoint is
introduced. Direct refresh and deep links require the existing authenticated
route guard and `cms.reopening.view`.

## CMS-11A automation API and data

Protected CMS automation endpoints are available to authorized users:

```text
GET    /api/cms/automation/rules
POST   /api/cms/automation/rules
PUT    /api/cms/automation/rules/{rule}
POST   /api/cms/automation/run
GET    /api/cms/automation/runs
GET    /api/cms/automation/dashboard
GET    /api/cms/automation/candidates
POST   /api/cms/automation/closure-candidates/{candidate}/review
POST   /api/cms/automation/escalation-candidates/{candidate}/review
GET    /api/cms/recommendations/{recommendation}/closure-readiness
```

The `cms.automation.view`, `manage`, `run`, `review`, and `dismiss`
permissions are scope-aware. Rule versions are immutable. Runs and actions
are idempotent and preserve dedupe keys; closure and escalation candidates
retain readiness/trigger snapshots and can only be acknowledged or dismissed
from this increment. Automation may send reminders and candidate notifications,
but cannot close recommendations, approve dispositions, reopen cases, or issue
escalation notices. CMS-11B consumes these contracts; reports and protected
CSV/PDF exports remain CMS-12.

## CMS-11B React workspace contract

The React workspace uses the CMS-11A endpoints through the existing same-origin
`cmsApi` client. It does not create a second API or reproduce backend
eligibility rules. The protected navigation route is
`/compliance-management/automation` and requires `cms.automation.view`.
Rule editing requires `cms.automation.manage`; manual execution requires
`cms.automation.run`; candidate acknowledgement requires
`cms.automation.review`; dismissal additionally requires
`cms.automation.dismiss`. The workspace renders backend scope, status,
readiness, run, and candidate data as authoritative and never exposes a
professional final-decision control.

## CMS-12A report and export API

CMS-12A adds `cms_report_runs` and `cms_report_exports`. Runs are immutable
scope-pinned snapshots; exports are immutable private file versions derived
only from one run. The `cms.report.view` permission is required for report
catalog, generation, run history, and run detail. `cms.report.export` is
required to generate or download CSV/PDF files.

```text
GET    /api/cms/reports
GET    /api/cms/reports/runs
GET    /api/cms/reports/runs/{run}
POST   /api/cms/reports/{report}/generate
POST   /api/cms/reports/runs/{run}/exports       { format: csv|pdf }
GET    /api/cms/report-exports/{export}/download
```

The supported report codes are `portfolio-status`, `implementation-progress`,
`target-date-monitoring`, and `closure-readiness`. Generation accepts
`search`, `status`, `officeId`, `riskCode`, `dateFrom`, and `dateTo`; the
backend validates filters and applies the same CMS scope and confidentiality
rules as the Recommendation Registry. Responses include report columns,
ordered rows, source-query version, row count, and a result checksum. Export
responses include only filename, MIME type, size, checksum, version, and an
authenticated download endpoint; private storage paths and public URLs are
never exposed. The CMS-12B React workspace is available at
`/compliance-management/reports`, requires `cms.report.view`, and renders the
backend columns, rows, checksums, scope summary, run history, and export
metadata without recreating eligibility rules. CSV/PDF generation and download
remain protected by `cms.report.export`. AIS-5D provides separate hardened
read-only analytical and protected reporting views and does not mutate CMS records; ARMIS provider integration is
outside the CMS boundary.

## AIS-5D read-only integration health and snapshots

| Method | Endpoint | Permission | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/ais/integration-contract` | `ais.view` | Return the versioned read-only Core/IAP/AEMS/CMS/ARMIS source adapters, scope and confidentiality rechecks, freshness, reconciliation status, ownership boundaries, and fail-closed rules |
| `GET` | `/api/ais/integration-health` | `ais.view` | Return current per-source health, reconciliation diagnostics, validation checks, and read-only controls |
| `GET` | `/api/ais/integration-health/snapshots` | `ais.view` | Return immutable integration-health snapshots generated by the authenticated actor |
| `POST` | `/api/ais/integration-health/snapshots` | `ais.view` | Capture an immutable integration-health snapshot, including blocked diagnostics when reconciliation is not ready |

The endpoint is authenticated, private-cacheable for a short interval, and
audited as `ais.integration.viewed`. AIS aggregation and report generation
re-evaluate this contract and return `503` when any authoritative source is
missing, stale, out of scope, or not reconciled. AIS-5B stores only AIS-owned
diagnostics in `ais_integration_snapshots`; it does not write to any source
module or create duplicate ownership tables. AIS-5D verification covers role
and office scope, confidentiality, stale/missing source handling, IAP/AEMS/CMS/
ARMIS lineage, no-write protection, authenticated desktop/mobile UI coverage,
and deployment review.

The responsive source-health workspace is `/audit-intelligence-system/integration-health`.
It renders these backend contracts, displays scope/confidentiality status and
exceptions, and links each source card to its authoritative module.

## AEMS-1A foundation contract

The AEMS engagement response now includes `phase`, `administrativeStatus`, and
`engagementOfficeId` alongside the legacy detailed `status`. The projections are
server-maintained and cannot be submitted as arbitrary client status values.

The supported lifecycle phases are:

```text
FOUNDATION, PLANNING, EXECUTION, ISSUES_AFR, CONFERENCES, REPORTING,
COMPLETION_TRANSFER, CLOSURE
```

The supported administrative statuses are:

```text
DRAFT, ACTIVE, RETURNED, ISSUED, SUSPENDED, CANCELLED, CLOSED, ARCHIVED
```

New AEMS registry creates and updates require exactly one `officeIds` value.
The existing `audit_engagement_offices` pivot remains available for historical
compatibility; `engagement_office_id` identifies the canonical office and the
response exposes an `officeRule` summary when the office relation is loaded.

The AEMS-1A foundation permissions are `aems.foundation.view`,
`aems.foundation.manage_scope`, and `aems.foundation.reconcile`. Existing
`aem.*` compatibility permissions remain available.

## AEMS-1B React shell and SCR navigation

The frontend route inventory is exported from `src/config/navigation.js` as
`aemsScreenRegistry`. Each entry records a stable screen identifier, canonical
portfolio route, navigation group, parent engagement tab, and required view
permission. The registry is not an authorization source; backend policies and
scope services remain authoritative.

The engagement detail workspace exposes the SCR-220 navigation contract:
Overview, Planning, Execution, Audit Issues, AFRs, Conferences, Audit Reports,
Completion & Transfer, and Activity. Existing portfolio pages remain valid
deep links and receive the engagement context through their established
`engagementId` query parameter.

The Engagement Registry renders `phase`, `administrativeStatus`,
`engagementOfficeId`, and `officeRule` from the AEMS-1A response. Phase and
administrative-status filters are presentation filters over the authorized API
result; they do not permit client-side status changes.

## AEMS-2A Planning Package API

The planning package endpoints are engagement-scoped and require the matching
`aems.planning-package.*` permission:

```text
GET  /api/aems/engagements/{engagement}/planning-package
POST /api/aems/engagements/{engagement}/planning-package
PUT  /api/aems/engagements/{engagement}/planning-package/{package}
POST /api/aems/engagements/{engagement}/planning-package/{package}/transition
POST /api/aems/engagements/{engagement}/planning-package/{package}/revise
```

Transition actions are `SUBMIT`, `REVIEW`, `RETURN`, `RESUBMIT`, and
`APPROVE`; every write includes the package `lockVersion`. The workspace
response includes lineage, the current immutable version, survey, objectives,
process flows, risk matrix/items and links, reviews, readiness checks,
available capabilities, and current-program procedures. Requests can reference
only exact existing Core `DocumentVersion` records, procedures and working
papers from the same engagement.

The additive migration `2026_08_12_000000_create_aems_planning_package_tables`
creates package/version, objective, process-flow, risk-matrix/item, review,
and relationship tables. Version and review rows are append-only; package
metadata carries workflow state, optimistic locking, IAP identifiers, and
approved-version identity. The new permissions are
`aems.planning-package.view/create/update/review/approve/revise`.

### AEMS-2B Planning Package frontend contract

The React route `/audit-engagement-management/planning-package` consumes the
workspace response above and keeps `engagementId` in the URL so the shared AEMS
engagement navigation remains in context. The workspace sections are Overview,
Preliminary Survey, Process Flows, Risk Matrix, Traceability, Readiness & Review,
and Versions. Editors submit the same versioned payload through `POST`/`PUT`;
workflow buttons call the transition and revision endpoints with the current
`lockVersion`. The UI treats `readiness.checks`, `capabilities`, and package
status as server-authoritative, renders approved versions read-only, and uses
the `versions` array for immutable inspection and comparison. No public document
URLs are introduced; survey and process-flow references remain exact Core
`DocumentVersion` IDs.

## AEMS-4A Fieldwork Records API and data contract

Fieldwork execution is exposed through the engagement-scoped routes below:

```text
GET  /api/aems/engagements/{engagement}/fieldwork
POST /api/aems/engagements/{engagement}/fieldwork
PUT  /api/aems/engagements/{engagement}/fieldwork/{record}
POST /api/aems/engagements/{engagement}/fieldwork/{record}/transition
```

The workspace response includes the authorized engagement, current active
procedures, Audit Areas and Focuses, record type/execution-state catalogues,
Fieldwork Records, immutable versions, participants, Working Paper links,
Evidence links, events, and a procedure traceability summary. Create/update
requests carry `recordType`, `procedureId`, `auditAreaId`, `auditFocusId`,
`performedOn`, execution narrative (`procedurePerformed`, `result`,
`conclusion`, and optional population/sample/analysis fields), participants,
`workingPaperIds` or versioned `workingPaperLinks`, required `evidenceIds`,
related tasks/records, and optimistic `lockVersion` on updates.

Transition requests carry `action`, `lockVersion`, and an optional comment.
Supported actions are `SUBMIT`, `REVIEW`, `RETURN`, `RESUBMIT`, `FINALIZE`,
and `REVISE`. Submission requires completed execution and Working Paper and
Evidence traceability. Review/return are independent of the preparer;
finalization additionally requires a different finalizer, approved linked
Working Papers, and verified or locked Evidence. `REVISE` starts a new draft
version from a finalized record and preserves the finalized snapshot.

The additive migration
`2026_08_21_000000_create_aems_fieldwork_record_tables` adds the procedure
execution fields and the `aems_fieldwork_records`, immutable version,
participant, Working Paper-link, and Evidence-link tables. Evidence links
retain the exact `audit_evidence.document_version_id`, so Core document
versions remain the integrity boundary. All writes are engagement-scope and
permission checked (`aems.fieldwork.view/create/review/finalize`), use
optimistic locking, and append engagement events, Activity Log, Audit Trail,
and controlled notifications. Audit Program procedure progress to `COMPLETED`
is rejected unless a finalized Fieldwork Record references that procedure.

### AEMS-4B Execution Workspace frontend contract

The React route `/audit-engagement-management/execution` consumes the
engagement-scoped Fieldwork workspace response above. It keeps `engagementId`,
`procedureId`, and `recordId` in the URL and exposes linked navigation to the
Audit Program, Working Papers/Evidence, and Issues pages. The frontend API
client is `aemsFieldworkApi` with `show`, `create`, `update`, and `transition`
methods; all writes use the existing protected routes and optimistic
`lockVersion` contract.

The workspace presents active procedures, target/overdue state, execution
blockers, Fieldwork Record versions, participants, reviewer notes, related
tasks/due dates, event timeline, and exact Working Paper/Evidence links. The
Create-Issue-from-Fieldwork action sends `workingPaperVersionIds` and
`evidenceIds` to the existing issue endpoint, while responsible office, risk,
issue readiness, and all issue workflow decisions remain server validated.

## AEMS-6B Issues and AFR frontend contract

`/audit-engagement-management/issues` and
`/audit-engagement-management/findings` consume the existing
`aemsFindingApi.show()` workspace contract. The Issues page remains distinct
from Findings & Recommendations and renders the issue disposition metadata
(`disposition`, `dispositionReason`, `dispositionRecordedBy`,
`dispositionRecordedAt`, `mergedIntoIssueId`, `referredTo`, and
`resolutionDetails`). It submits the existing transition payload with
`action`, `lockVersion`, `comment`, and, when applicable,
`mergedIntoIssueId`, `referredTo`, or `resolutionDetails`.

The Findings page consumes `conclusion`, `significanceClassification`,
`effectClassification`, `fieldworkRecords`, `revisions`, `revisionType`,
`revisionReason`, and immutable/finalization fields in `findingData`. It uses
the existing recommendation, management-response, rejoinder, attachment,
and lifecycle endpoints. Finding revisions call:

```text
POST /api/aems/engagements/{engagement}/findings/{finding}/revisions
body: { action: CORRECT|AMEND|SUPERSEDE|WITHDRAW,
        reason: string, lockVersion: integer }
```

The React action surface is advisory only. Laravel remains authoritative for
permissions, engagement scope, status transitions, author/reviewer
separation, immutable snapshots, exact support links, and optimistic-lock
conflicts. No public finding, evidence, Working Paper, or dialogue download
URL is introduced.

### AEMS-7A Work Queue and Dialogue API

The engagement-scoped Work Queue endpoint returns server-authoritative tasks,
review notes, due-process exchanges, escalation candidates, and status
catalogues. Task `dueState` is one of `NO_DUE_DATE`, `ON_TRACK`, `DUE_SOON`,
`OVERDUE`, `COMPLETED`, or `CANCELLED`. Completed and cancelled tasks are
immutable; reopening is an explicit permissioned transition and increments
the lock version.

Review Notes use a family UUID and immutable version numbers. A draft can be
edited by its author, finalized by an independent reviewer, and revised only
through a new draft revision that preserves the finalized source. Attachments
store the exact Core `document_version_id` and cannot be replaced or deleted.

Due-process event types are `REMINDER`, `NOTICE_SENT`,
`CLARIFICATION_REQUESTED`, `FINAL_NON_RESPONSE`, and
`ESCALATION_RECOMMENDED`. The finding `RECORD_NON_RESPONSE` transition creates
the exchange in the same transaction as the finding status change. Candidate
review is explicit and never issues notices or makes closure/finalization
decisions automatically.

The new permission families are `aems.task.*`, `aems.review-note.*`,
`aems.due-process.*`, and `aems.escalation-candidate.*`. They are evaluated by
`AemsAccessService` against role, engagement assignment, scope, and existing
separation-of-duties rules. Existing conference, Management Response,
Auditor Rejoinder, notification, Activity Log, Audit Trail, and protected Core
document routes are unchanged.

### AEMS-7B Conference and dialogue workspace

The protected React route `/audit-engagement-management/conferences` is an
aggregate workspace over the existing engagement-scoped endpoints:

```text
GET /api/aems/entry-conference-workspaces
GET /api/aems/engagements/{engagement}/entry-conference
GET /api/aems/exit-conference-workspaces
GET /api/aems/engagements/{engagement}/exit-conferences
GET /api/aems/findings-workspaces
GET /api/aems/engagements/{engagement}/findings-workspace
GET /api/aems/engagements/{engagement}/work-queue
GET /api/notifications/recent
```

The page exposes an engagement timeline, Entry/Exit Conference summaries,
participant and acknowledgement counts, agreements/disagreements, response
and rejoinder history, clarification history, overdue response and task
queues, escalation candidates, and recent AEMS notifications. Links to the
existing detailed conference and dialogue pages remain the action surface for
writes; no new workflow mutation endpoint is introduced by AEMS-7B.

The backend response remains authoritative for permission, engagement scope,
finding communication status, and office visibility. Auditee responses are
therefore limited to findings formally communicated to the auditee's office by
the existing `AemsFindingService::workspace` scope query. The frontend does
not filter in a way that could broaden that result, and shows an empty state
when no formally communicated findings are returned. Protected authenticated
notification and document routes are preserved; no public URLs are emitted.

### AEMS-8 Reporting API additions

The existing report endpoints remain the reporting contract. AEMS-8 adds:

```text
POST /api/aems/engagements/{engagement}/reports/interim
POST /api/aems/engagements/{engagement}/reports/{report}/successors
POST /api/aems/engagements/{engagement}/reports/{report}/withdraw
POST /api/aems/engagements/{engagement}/reports/{report}/versions/{version}/recipients/{recipient}/decision
```

Interim requests use the same assembly payload as Draft Reports and preserve
`sections[].sequenceNumber`, `executiveSummary`, `qualityChecklist`, exact
Finding IDs, confidentiality, and generated Core Document Version metadata.
The Final Report endpoint accepts only an approved Interim or Draft Report and
still rejects any Finding that is not current and `FINALIZED`.

Distribution decisions are `DELIVERED`, `ACKNOWLEDGED`, or `REJECTED` and are
append-only, version-bound, actor-bound, and timestamped. The recipient or
covered office may acknowledge; authorized internal distribution staff may
record delivery. `AMEND` and `SUPERSEDE` reopen the existing report family in
a controlled draft state and create a new immutable successor version linked
through `contentSnapshot.supersedesVersionId`. `WITHDRAW` retires the report
family without changing the issued version. All operations use report lock
versions and write Core
Activity Log, Audit Trail, and Engagement Events. Downloads continue to use
the protected authenticated report-version endpoint; no public PDF URL is
returned.

### AEMS-9A Completion and transfer API/data contract

The completion-transfer workspace is engagement-scoped and permission
protected:

```text
GET  /api/aems/engagements/{engagement}/completion-transfer
POST /api/aems/engagements/{engagement}/completion-transfer/reconcile
POST /api/aems/engagements/{engagement}/completion-transfer/{type}/{id}/approve
```

`type` is `MANIFEST` or `EFFORT`. Approval requires `lockVersion`, a comment
of at least ten characters, `aems.completion-transfer.approve`, and an actor
different from the snapshot generator. The response includes `manifest`,
`effortReconciliation`, `provider`, and permitted actions.

The additive tables are:

| Table | Purpose | Immutability boundary |
| --- | --- | --- |
| `aems_completion_transfer_manifests` | Issued report/version lineage and per-recommendation CMS outcomes | Approved rows cannot be updated or deleted |
| `aems_completion_transfer_exceptions` | Derived open transfer gaps and resolution context | Rebuilt only by controlled reconciliation |
| `aems_effort_reconciliations` | Versioned planned/AEMS/provider actuals and variance | Approved rows cannot be updated or deleted |

Manifest snapshots preserve the exact report `document_version_id` and
`checksum_sha256`. Recommendation transfer delegates to the existing CMS
gateway, which reuses the existing transfer key on retry. Effort snapshots
record `ARMIS_AUTHORITATIVE` provider mode and source status; missing or stale
ARMIS actuals block the closure checklist. Historical provider comparisons are
lineage evidence only. The Closure Checklist derives the blocking
`CMS_TRANSFER_MANIFEST`, `CMS_TRANSFER_EXCEPTIONS`, and
`EFFORT_RECONCILIATION` items from these records.

### AEMS-10A/10B dashboard and work-queue contract

The protected dashboard endpoints are:

```text
GET /api/aems/dashboard
GET /api/aems/dashboard/export
GET /api/aems/dashboard/queues/export
```

The dashboard requires `aems.engagement.view`; both exports additionally
require `aems.engagement.export`. Query parameters are `search`, `status`,
`phase` (`planning`, `fieldwork`, `reporting`, `closure`, or `other`),
`officeId`, `sortBy`, `sortDirection`, `page`, and `perPage`.

The response adds these server-calculated contracts alongside the existing
cards and engagement tracker:

| Property | Source and scope |
| --- | --- |
| `phaseCounts` | Active visible engagements grouped by lifecycle phase |
| `workQueues` | Limited queue items plus total counts for overdue procedures, Working Papers, Evidence Requests, evidence gaps, findings, conferences, reports, CMS transfer exceptions, Review Notes, tasks, and escalation candidates |
| `notifications` | Current user's unread, AEMS-unread, overdue, and recent Core notifications |
| `reminderRules` | Effective AEMS runtime reminder settings |

Queue rows contain only protected record references and engagement codes. The
backend applies `visibleTo`/engagement scope before counting or returning any
row. CSV values beginning with `=`, `+`, `-`, or `@` are prefixed before being
written to mitigate spreadsheet formula injection. The export is streamed only
after authentication and permission checks and is recorded in both Activity
Log and Audit Trail.

Runtime configuration keys used by AEMS reminder dispatch are:
`aems_reminders_enabled` (boolean, default `true`),
`aems_reminder_due_hours` (integer, default `48`),
`aems_response_reminder_days` (integer, default `3`), and
`aems_conference_reminder_days` (integer, default `7`). They are maintained by
the existing Core System Configuration endpoint and do not permit automated
approval, closure, CMS transfer, or other final professional decisions.

### AEMS-G1 evidence and direct-Finding contract

### AEMS-G3 planning conformance contract

Planning Package snapshots now include `riskMatrices`, structured
`processFlows`, `kpis`, and `plannedWorkingPapers` while retaining the legacy
`riskMatrix` alias. Audit Program and procedure snapshots include Area/Focus,
period, criteria, process/method, planned person-days, sampling, and planned
Working Paper fields. `readiness.fieldworkReady` is the authoritative strict
planning gate used by aggregate `START_FIELDWORK`; it must be true in addition
to the approved-package/version check.

`GET /api/aems/engagements/{engagement}/findings-workspace` and Finding detail
responses include `eligibleForFinalizedFinding` and `eligibilityReasons` on
each Evidence item and its current Assessment. The backend requires that flag
to be true for Finding `VALIDATE` and `FINALIZE`; the UI only mirrors the
decision and cannot override it.

Finding create/update payloads require `conclusion`. A direct Finding (one
without `sourceIssueId`) additionally requires:

```json
{
  "directAuthorityReason": "URGENT_OR_MATERIAL_RISK",
  "directAuthorityReference": "Approved authority or risk directive reference"
}
```

The response preserves `directCreationReason`, `directCreationAuthority`,
`directCreatedBy`, and `directCreatedAt`. Evidence Request Version and
Evidence Assessment rows are read-only snapshots; correction or exception
approval creates a superseding version with a new version number and change
reason.

### AEMS-G4 AEO authority and team amendment contract

The AEO authority endpoints are:

```text
POST /api/aems/engagements/{engagement}/aeo/{order}/transition
POST /api/aems/engagements/{engagement}/aeo/{order}/amend
GET  /api/aems/engagements/{engagement}/aeo/{order}/distribution
POST /api/aems/engagements/{engagement}/aeo/{order}/distribution
POST /api/aems/engagements/{engagement}/aeo/{order}/distribution/{distribution}/acknowledge
```

Transition actions `CANCEL`, `VOID`, and `SUPERSEDE` require a lock version,
an authority reason, CIAS Management permission, and create an immutable
engagement event and audit entry. `AMEND` creates a new AEO content version;
prior approved or issued versions remain unchanged.

`aems_aeo_signatories` is the per-version signatory matrix. It records the
independent reviewer, approving authority, and issuing authority, including
signature method, reference, actor, and timestamp. The normal rule separates
preparation, approval, and issuance; when the active CIAS Head is the sole
available CIAS Management authority, that account may use the explicit review,
approval, and issuance exception for her own AEO.

`aems_aeo_distributions` records only issued-version transmittals and their
recipient, method, reference, sent time, acknowledgement actor, note, and
acknowledgement time. `aems_team_amendments` and
`aems_team_access_history` are append-only authority and assignment-access
records; `engagement_team_history` remains the compatibility assignment log.

### AEMS-G7 Reporting and distribution contract

`audit_report_versions.source_manifest` and `source_manifest_sha256` are the
reproducibility contract for issued reports. `aems_report_issue_links`,
`aems_report_working_paper_links`, and `aems_evidence_report_links` preserve
direct source links; Evidence links include the exact Core
`document_version_id` and checksum. Authority decisions, signatories,
transmittals, and protected export records are append-only and version-bound.
The report-family `ADMINISTRATIVELY_CLOSED` status does not mutate the locked
issued version. See `docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md` for the endpoint
and permission matrix.

### AEMS-G8 Records and Audit Calendar API

The protected records workspace is `GET /api/aems/engagements/{engagement}/records`
with optional `q` search, and the calendar is
`GET /api/aems/engagements/{engagement}/calendar`. Milestones are created,
updated, and transitioned through the corresponding `/calendar/milestones`
routes using lock versions. Retention actions use `/retention/{retention}`
archive, `legal-hold-release`, `destruction-review`, and `disposition` routes.
The permission contract is `aems.records.*`, `aems.calendar.*`, and the
controlled `aems.retention.*` action permissions. See
`docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md` for state rules and blocker behavior.

### AEMS-G9 verification and migration rehearsal

`backend/tests/Feature/Api/AemsG9ConformanceTest.php` is the backend contract
index: it creates 35 Rule tests, 32 SCR registry tests, checks authenticated
AEMS download/export routes, and verifies the G3-G8 migration rehearsal
manifest. `tests/e2e/aems-g9-conformance.spec.js` covers duplicate-route
exclusion, role-by-role menu/tab visibility, lock-version mutation payloads,
negative Evidence, protected-download markers, and both Playwright projects.
Run `scripts/verify-aems-g9.ps1` for the repeatable fresh-migration, PHP,
frontend, and G9 browser checks.

### AEMS-G10A conformance additions

`AemsFieldworkRecord::TYPES` is the canonical fieldwork taxonomy and now
includes `INQUIRY`, `MEETING`, `FIELD_NOTE`, and `OTHER`. Findings accept
optional `procedureIds`; the backend also derives procedure links from cited
Working Paper and Fieldwork Record Versions. The normalized
`audit_finding_procedure` pivot stores the approved procedure, criteria
reference, traceability note, and linking actor. Finding detail, communication
snapshots, finalization snapshots, and immutable revisions preserve these
procedure IDs. Cross-engagement procedure references are rejected and finding
review gates require at least one procedure link.

### AEMS-G10E final acceptance

`AemsG10EAcceptanceTest` is the semantic Rule 1–35 runtime contract. The
canonical SCR registry remains 32 unique identifiers and the role/navigation
matrix covers six seeded roles. Final command and migration-rehearsal results
are recorded in `docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md`.
