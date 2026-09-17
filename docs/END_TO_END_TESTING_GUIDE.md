# AGIS End-to-End User Acceptance Testing Guide

This guide is for a new tester who wants to exercise the implemented AGIS
system from login through Core, IAP, AEMS, CMS, and ARMIS. It is a manual
acceptance guide; the source code and feature tests remain the final authority.

Read [AGIS As-Built System Manual](AGIS_AS_BUILT_SYSTEM_MANUAL.md) for the
page/function inventory and [AGIS Operational Playbooks](AGIS_OPERATIONAL_PLAYBOOKS.md)
for the prerequisite/action/result sequence before starting a test.

## 1. Know what is currently testable

The following areas are operational and should be included in acceptance
testing:

- AGIS Core: authentication, users, offices, roles, permissions, Audit Areas,
  Audit Focuses, Master Lists, documents, workflows, notifications, logs, and
  configuration;
- IAP: strategic planning, Audit Universe, risk, prioritization, annual plans,
  scheduling, resource capacity, and reports;
- AEMS: engagement registry, team, AEO, AEP, Audit Program, Entry/Exit
  Conferences, Working Papers, Evidence, Issues, Findings, Responses, Reports,
  Completion Assessment, Closure, Dashboard, and controlled reopening;
- CMS: recommendation intake, assignments, Action Plans, Progress Updates,
  Validation, target-date extensions, Escalations, Closure, dispositions, and
  controlled reopening, automation/candidate review, reports, and protected
  CSV/PDF exports;
- ARMIS: resource registry, competencies/certifications, planning/utilization,
  assignments/actuals, reports/exports, provider monitoring, and
  deployment/security checks.

AFR remains a navigation compatibility entry while AEMS owns Findings and
Recommendations. AIS-5D is a read-only analytical/source-health workspace and
must be tested for scope, confidentiality, freshness, lineage, no-write, and
protected downloads. AEMS uses ARMIS as the sole operational resource provider;
no IAP fallback or provider-reconciliation gate is required. CMS automation cannot
make final professional decisions or issue escalation notices automatically.

## 2. Read these documents first

1. [System Flow](SYSTEM_FLOW.md) — overall data and authorization flow;
2. [Core Workflow Design](CORE_WORKFLOW_DESIGN.md) — shared platform behavior;
3. [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md) — planning workflow;
4. [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md) — engagement workflow;
5. [CMS Workflow Design](CMS_WORKFLOW_DESIGN.md) — compliance workflow;
6. [API and Data Reference](API_AND_DATA_REFERENCE.md) — routes and records;
7. [Operations Guide](OPERATIONS_GUIDE.md) — setup, deployment, and checks.

For a hosted Render environment, follow [Render Deployment](RENDER_DEPLOYMENT.md)
first. Use the deployed database and never run `migrate:fresh` there.

## 3. Prepare a safe test environment

Use a local or dedicated demonstration database. Do not run destructive reset
commands against retained or production data.

For a new local database:

```powershell
php backend/artisan migrate:fresh --seed
```

For an existing database, use normal migrations only:

```powershell
php backend/artisan migrate --force
```

Enable demo accounts only in a controlled test environment:

```dotenv
DEMO_ACCOUNTS_ENABLED=true
DEMO_DEFAULT_PASSWORD=your-temporary-test-password
```

The login form expects **Employee ID**, not the account username. The password
is the configured `DEMO_DEFAULT_PASSWORD`, unless an account-specific demo
password is configured.

## 4. Demo accounts and what to test with them

| Employee ID | Account | Role | Primary test use |
|---|---|---|---|
| `AGIS-PLATFORM-001` | `admin` | Platform Administrator | Full administration and security controls |
| `AGIS-ADMIN-001` | `agisadmin` | AGIS Administrator | Core administration without platform-admin actions |
| `CIAS-HEAD-001` | `departmenthead` | CIAS Management | Approvals, independent reviews, closure decisions |
| `CIAS-AUD-001` | `auditor` | AGIS User | Auditor preparation, procedures, Working Papers, findings |
| `AUDITEE-001` | `auditee` | Auditee Representative | Office-scoped responses and conference acknowledgement |
| `MAYOR-001` | `mayor` | Read Only User | Authorized read-only visibility and confidentiality |

Sign out between role tests. Never use demo passwords in a shared or
production deployment.

## 5. How to record each test

For every scenario, record:

- tester and role;
- date and environment URL;
- scenario ID;
- expected result;
- actual result;
- screenshot or screen recording when relevant;
- API response/status when testing an API;
- Activity Log/Audit Trail record ID when a change is expected;
- defect details, including the exact record code and lock version.

Do not mark a scenario passed only because a page loaded. Confirm the saved
record, permission boundary, workflow status, and audit evidence.

## 6. Page-by-page operating and testing guide

### 6.1 How workflow buttons work

The buttons on a record are controlled by its current status, the signed-in
user's permission, office or engagement scope, and separation of duties. A
missing button is not automatically a defect. First confirm all four of those
conditions.

Use at least two browser sessions for approval tests:

- `CIAS-AUD-001` prepares, edits, and submits auditor-owned records;
- `CIAS-HEAD-001` performs management review, return, approval, validation,
  issuance, or closure actions;
- `AUDITEE-001` performs office-scoped acknowledgement and management-response
  actions;
- `MAYOR-001` verifies authorized read-only behavior.

After every workflow action:

1. record the old and new status;
2. refresh the page and confirm the saved status remains;
3. inspect the record's workflow or revision history;
4. inspect Notifications for the next actor;
5. inspect Activity Log and Audit Trail for the actor, action, time, and record;
6. try the same request with a stale browser tab to confirm optimistic locking;
7. confirm the previous approved or issued version did not change.

### 6.2 Internal Audit Planning pages

#### IAP Dashboard

**Purpose:** This is the live summary and navigation page for planning. It is
not a data-entry or approval page.

What to do:

1. Review the cards for strategic plans, Audit Universe, risk cycles,
   prioritization, annual plans, schedules, and resource pressure.
2. Use the status, year, office, or other available filters and confirm the
   totals change consistently.
3. Open records from quick links and confirm they lead to the correct registry
   or workspace.
4. Compare at least one dashboard total with the underlying registry.

Miscellaneous checks:

- a user should see only records within their authorized scope;
- a zero card should display as a valid empty state, not an error;
- refreshing must not change totals unless source data changed;
- cards and links must remain usable at mobile width.

#### Strategic Audit Plan

**Purpose:** Create and control the multi-year Strategic Internal Audit Plan
(SIAP), including objectives, priorities, expected outcomes, and linked Audit
Areas.

Create a plan as the preparer:

1. Select **New Strategic Plan**.
2. Enter the planning years, title, coordinator, strategic context, vision,
   mission alignment, planning methodology, and overall expected outcomes.
3. Add at least one objective. Each objective needs a code, title, expected
   outcome, and at least one linked Audit Area.
4. Add at least one priority or theme with its expected outcome.
5. Save the record and confirm it is `DRAFT`.

The exact workflow is:

| Current status | Available action | Next status | Important rule |
|---|---|---|---|
| `DRAFT` | Submit for review | `PENDING_REVIEW` | Required objectives, Audit Area links, priorities, and outcomes must be complete. |
| `PENDING_REVIEW` | Return for revision | `RETURNED_FOR_REVISION` | CIAS Management performs it and a comment is required. |
| `PENDING_REVIEW` | Approve | `APPROVED` | The submitter cannot approve their own plan. |
| `RETURNED_FOR_REVISION` | Resubmit | `RESUBMITTED` | Correct the returned content before resubmission. |
| `RESUBMITTED` | Return for revision | `RETURNED_FOR_REVISION` | A return comment is required. |
| `RESUBMITTED` | Approve | `APPROVED` | Must be an authorized management user other than the submitter. |
| `APPROVED` | Activate | `ACTIVE` | Indicates that the strategic planning period is in effect. |
| `ACTIVE` | Complete | `COMPLETED` | A comment and explicit completion confirmation are required. |
| `APPROVED` or `ACTIVE` | Create revision | New `DRAFT` revision | A reason is required; the prior formal version remains unchanged. |

Therefore, **Approve**, **Return**, and **Complete** are not valid actions on a
draft. A draft can be edited, submitted, or, with permission, archived.

Miscellaneous checks:

- edit is allowed only in `DRAFT` or `RETURNED_FOR_REVISION`;
- archive is allowed for draft, returned, or completed records and restore is a
  separate permission-controlled action;
- only one revision should be marked current for the planning period;
- the revision must copy objectives, priorities, and Audit Area links without
  changing the superseded record;
- use History to verify the preparer, submitter, approver, activator, and
  completer with their dates.

#### Audit Universe

**Purpose:** Maintain the inventory of auditable programs, processes, systems,
offices, services, or other subjects that may be assessed and selected for
audit.

What to do:

1. Select **Add Auditable Subject**.
2. Enter a unique subject code, subject type, name, and description.
3. Select the responsible office and primary Audit Area.
4. Add stakeholder offices where applicable.
5. Select the materiality or exposure level and enter the materiality/exposure
   narrative.
6. Record the last audit date, indicative audit scope, and historical audit
   summary when known.
7. Save, open the detail panel, and verify all relationships.

This page has no submit/approve workflow. Authorized users can create, edit,
archive, and restore records. Inactive and archived are different conditions;
test the appropriate filter for each.

Miscellaneous checks:

- duplicate subject codes must be rejected;
- a subject should appear in later Risk Assessment selection lists;
- archived subjects must remain in historical records but not be offered for
  inappropriate new work;
- verify the **Never Audited** and high/critical exposure totals;
- check search by subject, office, Audit Area, and exposure.

#### Risk Assessment

**Purpose:** Open a controlled risk cycle, score Audit Universe subjects, have
the results independently validated, and lock the approved baseline.

What to do:

1. Select **Open New Period** and enter the code, name, assessment year, date
   range, methodology, and criterion weights. The weights must total 100%.
2. The new period begins as `DRAFT`. Review the setup, then choose **Open
   Assessment Period** to move it to `OPEN`.
3. In `OPEN`, add an assessment for each relevant Audit Universe subject.
4. Enter criterion ratings and comments, control effectiveness, inherent-risk
   notes, control-environment notes, and assessment justification.
5. Upload supporting evidence where applicable. If professional judgment
   overrides the calculated result, select the override level and enter a clear
   override reason.
6. Submit the complete period for validation.

Workflow:

| Current status | Action | Next status |
|---|---|---|
| `DRAFT` | Open Assessment Period | `OPEN` |
| `OPEN` | Submit for validation | `PENDING_VALIDATION` |
| `PENDING_VALIDATION` | Return for revision | `RETURNED_FOR_REVISION` |
| `PENDING_VALIDATION` | Validate assessment | `VALIDATED` |
| `RETURNED_FOR_REVISION` | Resubmit | `RESUBMITTED` |
| `RESUBMITTED` | Return for revision | `RETURNED_FOR_REVISION` |
| `RESUBMITTED` | Validate assessment | `VALIDATED` |
| `VALIDATED` | Lock assessment period | `LOCKED` |

Miscellaneous checks:

- the same Audit Universe subject cannot be assessed twice in one period;
- computed inherent and residual scores must match the displayed risk levels;
- an override without a reason must be rejected;
- compare the period with a previous cycle and check the displayed score
  changes;
- assessments are editable only while the cycle permits correction;
- lock only after validation and confirm the locked baseline is immutable;
- test evidence download, archive, and restore with authorized and unauthorized
  accounts.

#### Audit Prioritization

**Purpose:** Convert a validated risk baseline into a ranked and documented
selection decision for annual planning.

What to do:

1. Create a prioritization run from a validated or locked risk period.
2. Review the calculated ranking, risk score, proposed decision, capacity, and
   other displayed factors for every subject.
3. Set the decision for each item, such as selected, deferred, or not selected.
4. Enter a decision reason for every deferred or not-selected item and whenever
   the decision differs from the system recommendation.
5. If using a manual override, mark it and enter the override reason.
6. Submit the run for review.

Workflow:

`DRAFT -> PENDING_REVIEW -> RETURNED_FOR_REVISION -> RESUBMITTED -> FINALIZED`

From `PENDING_REVIEW` or `RESUBMITTED`, a reviewer may **Return for revision**
or an approver may **Finalize ranking**. Finalization must fail if a required
decision or override reason is missing.

Miscellaneous checks:

- verify rank ordering and search/filter behavior;
- confirm source risk scores remain traceable and are not silently rewritten;
- confirm the finalizer is different from the preparer where separation of
  duties applies;
- open the finalized run from Annual Audit Plan and verify the same selected
  items are available for import;
- archive only at an allowed status and confirm history remains visible.

#### Annual Audit Plan

**Purpose:** Assemble the fiscal-year plan, import prioritized engagements,
assign resources and dates, collect supporting records, and obtain formal
approval.

What to do:

1. Create the plan with fiscal year, code, planning-period type, title, dates,
   preparer, coordinator, executive summary, methodology, objective, scope, and
   known limitations.
2. Connect a finalized prioritization run.
3. Import selected items as planned engagements. Confirm the engagement code,
   type, audit approach, priority, objectives, preliminary scope, quarter,
   dates, person-days, cost, offices, and Audit Areas.
4. Complete plan-local risk records when used and retain their source links.
5. Add reviewer comments and upload/download/archive/restore supporting
   documents from the Supporting Records section.
6. Use Scheduling and Resource Capacity to complete dates, proposed team, and
   capacity information before submission.
7. Review the completeness panel and resolve every blocking item.

Workflow:

| Current status | Action | Next status | Notes |
|---|---|---|---|
| `DRAFT` | Submit | `PENDING_REVIEW` | Content becomes review-controlled. |
| `PENDING_REVIEW` | Return | `RETURNED_FOR_REVISION` | Correction comment required. |
| `PENDING_REVIEW` | Reject | `REJECTED` | Rejection reason required. |
| `PENDING_REVIEW` | Approve | `APPROVED` | Submitter cannot self-approve. |
| `RETURNED_FOR_REVISION` | Resubmit | `RESUBMITTED` | Resubmit corrected plan. |
| `RESUBMITTED` | Return, Reject, or Approve | Corresponding status | Management action. |
| `APPROVED` | Activate | `ACTIVE` | Authorizes implementation. |
| `ACTIVE` | Complete | `COMPLETED` | Confirmation and comment required. |
| `APPROVED` or `ACTIVE` | Create revision | New `DRAFT` revision | Original version is preserved. |

Miscellaneous checks:

- edit the plan, imported engagements, risk records, and schedules only while
  the plan is `DRAFT` or `RETURNED_FOR_REVISION`;
- confirm duplicate prioritization imports are prevented;
- inspect revision carry-forward and ensure only the new revision is editable;
- confirm reviewer comments and attachments retain their actor/version history;
- verify rejected, superseded, and archived records remain historically
  discoverable only to authorized users.

#### Audit Scheduling

**Purpose:** Put planned engagements on the calendar and test availability,
capacity, overlap, and required skills.

What to do:

1. Select an unscheduled planned engagement.
2. Enter planned start, planned end, and expected report date.
3. Add proposed team members, assignment roles, and allocated person-days.
4. Select **Check Conflicts** and review date overlap, unavailable periods,
   person-day capacity, and skill warnings.
5. Save the schedule. Use the table/calendar view to verify it.
6. To change it, enter a reschedule reason and save the new dates or team.
7. To stop it, choose **Cancel Schedule** and enter the required reason. Open
   the cancelled record and use **Reinstate Schedule** when appropriate.

Important: schedule editing is part of plan preparation. Once the Annual Audit
Plan is submitted or formally approved, scheduling becomes read-only; do not
wait until approval to build the initial schedule.

Miscellaneous checks:

- planned end cannot precede planned start;
- expected report date should be consistent with the engagement timeline;
- a warning should not disappear without resolving or documenting its cause;
- cancellation must retain schedule history rather than delete it;
- compare assigned person-days with Resource Capacity totals.

#### Resource Capacity (retired compatibility route)

The former `/internal-audit-planning/resource-capacity` page is retained only
as a compatibility redirect to ARMIS Planning and Utilization. ARMIS now owns
current capacity, availability, competency, engagement requirements, and actual
person-days. Historical IAP resource records may be inspected for lineage but
are not an AEMS provider or readiness source.

What to do:

1. Open `/audit-resource-management/planning` and select the fiscal year.
2. Set each auditor's available person-days.
3. Add unavailable periods, including type, dates, and notes. Edit, archive,
   restore, and confirm archived periods no longer create scheduling conflicts.
4. Maintain auditor specializations and proficiency levels.
5. Open a planned engagement and set its skill requirements, minimum auditor
   count, and required proficiency.
6. Compare available, assigned, remaining, and utilization values and inspect
   capacity or competency gaps.

Miscellaneous checks:

- only an authorized manager should change capacity and requirements;
- engagement requirements follow the Annual Plan editability rules;
- allocations must agree with ARMIS assignments and the approved engagement
  schedule;
- over-capacity and unavailable assignments should be clearly flagged;
- treat the ARMIS ledger as the operational source; historical IAP values do
  not change ARMIS authority.

#### IAP Reports

**Purpose:** Preview and export planning records using the user's current
authorization scope.

Available reports are:

- Approved Strategic Internal Audit Plan;
- Audit Universe Report;
- Risk-assessment Matrix and Risk Heat Map;
- Prioritization Ranking;
- Approved Annual Internal Audit Plan;
- Annual Audit Schedule;
- Auditor Allocation Report;
- Plan Revision History.

What to do:

1. Choose each report from the catalog.
2. Select its required Strategic Plan, risk period, prioritization, Annual Plan,
   or fiscal-year filter.
3. Preview and compare sample rows with the source page.
4. If authorized, export PDF, Excel, and CSV and use Print.
5. Sign in as a preview-only or scoped user and confirm unauthorized records and
   export buttons are not available.

Miscellaneous checks:

- only approved/finalized/validated source choices should appear where the
  report requires them;
- generated totals, actor names, dates, and revision numbers must match source
  records;
- confidential or out-of-scope records must not leak through exports;
- verify long text, page breaks, special characters, and empty results.

### 6.3 Audit Engagement Monitoring pages

#### AEMS Dashboard

**Purpose:** Monitor the engagement portfolio from authorization through CMS
transfer and closure readiness. This page summarizes work; it does not replace
the operational workspaces.

What to do:

1. Check active, planning, fieldwork, overdue procedure, Working Paper review,
   Finding response, conference, report approval, and closure-ready cards.
2. Filter by search, status, and office; compare totals with Engagement
   Registry and the underlying workspaces.
3. Open an engagement's progress view and inspect all tracked stages.
4. Export progress CSV if the action is available and compare it with the page.

Miscellaneous checks: cards should be hoverable/clickable where designed, zero
states must be valid, overdue calculations must use the target date, and every
result must remain office- and engagement-scope aware.

#### Engagement Registry and Engagement Detail

**Purpose:** Establish the official AEMS engagement, preserve IAP or special-
authority lineage, and control its aggregate lifecycle.

Create the engagement:

1. Prefer **Import Approved IAP Engagement** for planned work. Select the
   approved source and confirm the code, title, objectives, scope, offices,
   Audit Areas, dates, risks, and source snapshot.
2. Try importing the same IAP engagement again and confirm duplicate prevention.
3. Use **Create Special Engagement** only with the required authority details
   and approval evidence.
4. Confirm both imported and special engagements start in `DRAFT` and that
   SCR-212 details are completed through the separate Engagement Scope page.
5. Select the office first, confirm only office-linked Audit Areas are offered,
   then confirm only Area-linked Audit Focuses are offered and remain selected
   after saving and reopening the scope.
6. Open the engagement detail and verify its source and historical snapshot.
7. Confirm Audit Team actions are unavailable in `DRAFT`. Use Lifecycle →
   Prepare Authorization, then confirm team assignment becomes available.
8. Confirm AEP, planning, execution, reporting, completion, records, and
   closure workspaces show a lock notice until their phase prerequisites are
   met. Lifecycle remains available so the next authorized transition can be
   performed.

Aggregate lifecycle:

| Current status | Main action | Next status | Primary gate |
|---|---|---|---|
| `DRAFT` | Prepare Authorization | `AUTHORIZATION_PREPARATION` | Valid IAP or special-authority source, valid one-office scope, and required source metadata. Team assignment is intentionally locked until this phase. |
| `AUTHORIZATION_PREPARATION` | Issue Authorization | `AUTHORIZED` | Current AEO is approved and issued. |
| `AUTHORIZED` | Start Planning | `ENGAGEMENT_PLANNING` | Issued AEO and active engagement. |
| `ENGAGEMENT_PLANNING` | Start Entry Conference | `ENTRY_CONFERENCE` | Approved AEP and approved/current Audit Program. |
| `ENTRY_CONFERENCE` | Start Fieldwork | `FIELDWORK` | Entry Conference completed or waived and required team roles active. |
| `FIELDWORK` | End Fieldwork / Start Findings Communication | `FINDINGS_COMMUNICATION` | Procedures and Working Papers are terminal. |
| `FINDINGS_COMMUNICATION` | Start Reporting | `REPORTING` | Issues are terminal and Findings are finalized. |
| `REPORTING` | Issue Final Report | `ISSUED` | Controlled Final Report issuance gates pass. |
| `ISSUED` | Submit for Closure | `CLOSURE_REVIEW` | Completion and closure prerequisites pass. |
| `CLOSURE_REVIEW` | Formal closure actions | `CLOSED` | Approved Completion Assessment and Closure record; use the Closure workspace. |

Returnable stages can be **Returned for Revision** and then **Resubmitted** to
the recorded prior stage. Eligible active stages can also be suspended and
resumed. Cancellation requires a reason, authority, and effect on IAP. `CLOSED`
and `CANCELLED` are terminal.

The Engagement Detail also contains work that is not a separate sidebar page:

- lifecycle requirements and blockers;
- Completion Assessment: draft, submit, return, resubmit, approve, revise, and
  controlled blocker acceptance;
- formal Closure: draft, submit, return, resubmit, approve, then close;
- Document Index refresh/inclusion/exclusion and export;
- retention settings and approval;
- lessons learned;
- controlled reopening requests after closure.

Miscellaneous checks:

- a lifecycle action with a failed gate must show the blocker and make no
  partial status change;
- return, suspend, resume, cancel, and submit-for-closure require comments;
- archive/restore must retain source lineage and history;
- only assigned or otherwise scoped users should open the engagement;
- the formal Closure service, not a general transition button, must produce
  `CLOSED`.

AEMS-1B shell and registry checks:

1. Open the AEMS sidebar and confirm implemented screens are grouped under
   Portfolio, Foundation, Planning, Execution, Issues & AFR, Conferences, and
   Reporting. Confirm reference-only screens are not exposed as broken links.
2. Open an engagement detail and verify the engagement-centered tabs: Overview,
   Planning, Execution, Audit Issues, AFRs, Conferences, Audit Reports,
   Completion & Transfer, and Activity.
3. Follow Planning, Execution, Issues, AFR, Conference, and Report tabs and
   confirm the selected engagement is retained in the destination workspace.
4. In Engagement Registry, filter by lifecycle phase and administrative status.
   Verify the table displays the detailed workflow status together with its
   phase/admin projection and canonical office.
5. Use a historical multi-office fixture, if available, and verify the warning
   is visible while the record remains read-only from the foundation contract.
6. Repeat the checks at desktop and mobile widths. The grouped sidebar must
   remain scrollable, the workspace tabs must be horizontally usable, and no
   page may introduce horizontal document overflow.

#### Audit Team

**Purpose:** Establish who may work on and see the engagement and record the
planned resource commitment.

What to do:

1. Select the engagement and add the supervisor, team leader, auditor, and
   reviewer roles required by the engagement gates.
2. For each assignment, select the user, role, planned person-days, effective
   dates, and notes.
3. Edit or reassign a current member when authorized.
4. End an assignment using the effective end date and reason instead of
   deleting history.
5. Inspect availability, capacity, competency, and workload warnings from the
   interim resource provider.

Miscellaneous checks:

- do not assign inactive users or invalid date ranges;
- check duplicate/overlapping assignment protection;
- ended members must lose current engagement access where their role was the
  source of that access;
- person-days should reconcile with the IAP plan while remaining an AEMS
  historical assignment record.

#### Team safeguards and ARMIS readiness (AEMS-3A/AEMS-3B)

After the four core roles are assigned, also test the optional `SPECIALIST` and
`AUTHORIZED_PARTICIPANT` roles. Open the Team Safeguards workspace/API and, as
each assigned resource, submit separate Objectivity, Conflict-of-Interest, and
Independence declarations. Use `CLEAR` for an uncomplicated case; use
`DISCLOSED` without a mitigation plan and `CONFLICT` to confirm that readiness
is blocked. Attach an exact Core Document Version when evidence is required.

As a different reviewer, accept or return each declaration. Assigned resources
must remain view-only for their own submitted versions. CIAS Management may
prepare a declaration for an assigned resource and review that prepared
version. A preparer must not review their own declaration unless the preparer is
CIAS Management acting on behalf of another assigned resource. After an accepted declaration, attempt a
correction without `revisionReason` (it must fail), then submit the correction
with a reason and confirm that the original version remains immutable.

Record a pending safeguard assessment as a reviewer, then approve it as a
different CIAS Management authority. Confirm that the approved assessment is
immutable and that notifications, Activity Log, Audit Trail, and engagement
events contain actor, version, provider mode, and reconciliation metadata.

Verify ARMIS authority directly: remove the active ARMIS profile, approved
capacity, verified competency, availability, or independence declaration one
at a time. Each omission must block assessment approval and the aggregate
AEO/fieldwork gate. Restore the data and confirm readiness. A historical
provider reconciliation is not required.

In the Audit Team page, confirm that the provider card, planned-versus-actual
cards, competency/workload matrix, declaration status, readiness blockers, and
approval panel display the same values as the API response. Use the resolver
text on each blocker to verify that the interface identifies who must act next.
Submit a declaration from the form, review it as another actor, and confirm
that returned declarations can be revised while accepted versions remain
visible in history. Record an assessment and approve it from the separate
approval action; verify that the assessor and approver are visibly distinct.

Finally, create an approved ARMIS actual-person-day record and compare it with
the AEMS team and engagement totals. A variance must be recorded and block an
authoritative assessment until reconciled. These checks are protected by
`AemsTeamSafeguardTest`, `AemsTeamAeoTest`, `ArmisAssignmentTest`, and
`ArmisProviderMonitoringTest`.

#### Fieldwork Records and Execution Workspace (AEMS-4A/AEMS-4B)

**Purpose:** Record and independently review the execution of every Audit
Program procedure with immutable, evidence-backed traceability.

1. Open an engagement in active fieldwork and confirm its current active Audit
   Program and procedure list are visible.
2. Create one draft for each supported type: Interview, Observation,
   Walkthrough, Inspection, Testing, Sampling, and Analysis. For every record,
   enter the performed date/location, objective, procedure performed,
   population/sample, result, conclusion, participants, Audit Area and Focus,
   related tasks/records, at least one Working Paper version, and at least one
   Evidence record linked to an exact Core Document Version.
3. Attempt to submit an incomplete or in-progress record. Confirm the server
   rejects it until execution is `COMPLETED`, participants exist, and both
   Working Paper and Evidence traceability are present.
4. Submit a complete record as its preparer. As a different authorized
   reviewer, record an independent review. Confirm the preparer cannot review
   or return their own record.
5. Return the submitted record with a reason, edit it as a new version, and
   resubmit. Confirm the prior version, return reason, actor, and timestamp
   remain visible and immutable.
6. Attempt finalization with an unapproved Working Paper, unverified Evidence,
   or the independent reviewer as finalizer. Each attempt must be blocked.
   Finalize as a different authorized authority after all links are approved or
   verified/locked. Confirm the record is locked and the procedure fields show
   completed execution, results, conclusion, reviewer state, related items,
   and completion actor/time.
7. Attempt to progress a separate procedure directly to `COMPLETED` without a
   finalized Fieldwork Record. Confirm the API returns a fieldwork validation
   error. After finalizing a linked record, repeat the progress action and
   confirm it succeeds with optimistic lock versions refreshed.
8. Start a formal correction on a finalized record. Confirm it creates a new
   draft version, resets the procedure fieldwork state to in progress, and
   leaves the finalized snapshot untouched.

Verify the Fieldwork Record events, Activity Log, Audit Trail, notifications,
engagement scope, and stale lock-version errors. The protected backend gate is
covered by `AemsFieldworkRecordTest`.

The AEMS-4B browser acceptance flow is:

1. Open **Execution Workspace** from the AEMS sidebar or from an engagement's
   Execution tab. Select an engagement and confirm the selected engagement,
   active procedures, target dates, and Fieldwork Records remain in context.
2. Select a procedure and verify its Audit Program status, results/conclusion,
   task list, overdue state, and links to Working Papers/Evidence and Issues.
3. Select a Fieldwork Record and verify its immutable version number, execution
   narrative, participants, reviewer notes, linked Working Paper/Evidence
   versions, and event timeline. Use the record-type filter and record search.
4. Create or edit a draft, enter task assignee/due date, and save. Confirm the
   server response creates a new version on correction and that finalized
   records are not edited in place.
5. Use Submit, Record Review, Return for Revision, Resubmit, Finalize, and
   Start Correction only with the appropriate role. Confirm the UI surfaces
   server blockers, separation-of-duties failures, stale lock conflicts, and
   traceability requirements rather than bypassing them.
6. Use **Create issue** from a record with linked Working Paper/Evidence and
   verify the draft issue opens with exact linked version IDs. Complete the
   Issue workflow on the Issues page independently.

The focused browser contract for this flow is
`tests/e2e/aems-execution.spec.js`.

#### Engagement Orders

**Purpose:** Prepare, review, approve, issue, and formally revise the Audit
Engagement Order (AEO).

What to do:

1. Select an engagement in authorization preparation and create the AEO.
2. Enter authority, objectives, scope, offices, team, dates, deliverables,
   confidentiality, and other order content.
3. Use **Record Review** as a reviewer, then either return or proceed to an
   independent approval.

Workflow:

`DRAFT -> PENDING_REVIEW -> RETURNED_FOR_REVISION -> RESUBMITTED -> APPROVED -> ISSUED`

- `DRAFT`: edit and **Submit for Review**;
- `PENDING_REVIEW` or `RESUBMITTED`: **Record Review**, **Return for Revision**,
  or **Approve AEO** according to permission;
- `RETURNED_FOR_REVISION`: edit and **Resubmit**;
- `APPROVED`: **Issue AEO**;
- `APPROVED` or `ISSUED`: **Create Revision** with a reason.

Miscellaneous checks:

- a clear comment is required when returning;
- required team-role gates must pass before submission;
- preparer, reviewer, approver, and issuer separation must be enforced;
- download the approved/issued PDF and verify its exact immutable version;
- revision must preserve the old AEO, PDF, checksum, actors, and dates.

#### Engagement Plan

**Purpose:** Create the Audit Engagement Plan (AEP) from the issued AEO and
preserve the approved IAP risk and planning lineage.

What to do:

1. Select an engagement with an issued AEO and create the AEP.
2. Complete objectives, scope, methodology, criteria, risk response, sampling,
   resource plan, timetable, communication, quality controls,
   confidentiality, and the displayed risk linkage.
3. Save, submit, record review, return/resubmit if necessary, and approve.

Workflow:

`DRAFT -> PENDING_REVIEW -> RETURNED_FOR_REVISION -> RESUBMITTED -> APPROVED`

At pending or resubmitted status, reviewers can **Record Review** or **Return**,
and an independent approver can **Approve AEP**. An approved AEP can only be
corrected using **Create Formal Revision**.

Miscellaneous checks:

- creation/submission must fail without the required issued AEO;
- edit only draft or returned content;
- ensure the IAP risk snapshot/source link remains unchanged;
- confirm approved versions are immutable and only the new revision is current.

#### Audit Program

**Purpose:** Turn approved engagement objectives into assigned audit procedures
and control fieldwork completion.

What to do:

1. Create the program title and objective.
2. Add procedures with sequence, objective, procedure to perform, expected
   evidence, assignee, target date, and other displayed requirements.
3. Submit, record review, return/resubmit if needed, and approve the baseline.
4. Select **Start Fieldwork** to move the approved program to `ACTIVE`.
5. For each procedure, start it, record work/results and Working Paper reference,
   and mark it completed; use waiver only with an authorized reason.
6. Record the procedure review result and make sure its Working Paper is
   approved.
7. Select **Complete Program** only after all procedures are completed or waived
   and all review/Working Paper gates pass.

Program workflow:

`DRAFT -> PENDING_REVIEW -> RETURNED_FOR_REVISION -> RESUBMITTED -> APPROVED -> ACTIVE -> COMPLETED`

An approved or active program can use **Create Revision**. The existing
approved baseline remains immutable.

Miscellaneous checks:

- procedure assignment should be limited to the active team;
- overdue procedures should appear on the AEMS Dashboard;
- a waived procedure must keep its reason and actor;
- program completion must be blocked by any nonterminal procedure, missing
  review, or missing approved Working Paper.

#### Entry Conferences

**Purpose:** Record the pre-fieldwork conference, attendance, auditee concerns,
agreements, official notes, and acknowledgement.

What to do:

1. Move the aggregate engagement to `ENTRY_CONFERENCE`; the system will not
   create the conference record before that stage.
2. Create the draft with schedule, venue or meeting link, agenda, briefing
   paper, participants, roles, auditee views, expected information, matters,
   agreements, and responsible persons/dates.
3. Upload briefing or other supporting attachments.
4. Use **Schedule**. If changed, use **Reschedule** and enter the reason.
5. Use **Mark Held** and record attendance for each participant.
6. Complete the conference notes and disposition of material matters, then use
   **Circulate Notes**.
7. Sign in as the Auditee Representative and acknowledge, or acknowledge with a
   reservation and enter the reservation.
8. Use **Complete** after acknowledgement or from notes-for-acknowledgement when
   the permitted completion rules are satisfied.

Workflow:

`DRAFT -> SCHEDULED/RESCHEDULED -> HELD -> NOTES_FOR_ACKNOWLEDGEMENT -> ACKNOWLEDGED -> COMPLETED`

A draft or scheduled conference may be cancelled. An authorized waiver requires
a reason, approving authority, and any required waiver support. `COMPLETED`,
`WAIVED`, and `CANCELLED` are terminal.

Miscellaneous checks:

- completion requires the AEO to remain issued, AEP approved, Audit Program
  approved/current, a held date, briefing/agenda support, required attendance,
  notes, and material-matter dispositions;
- acknowledgement is restricted to the appropriate auditee office;
- attachments must download only through authenticated scoped endpoints;
- changes after circulation must be traceable by version/lock history.

#### Working Papers & Evidence

**Purpose:** Preserve the audit work performed and the exact evidence on which
procedures, Issues, and Findings rely.

Working Paper steps:

1. Create a unique Working Paper index/title and link the applicable Audit
   Program procedure.
2. Enter objective, procedure performed, population and sample, results,
   conclusion, cross-references, preparer/date, and reviewer. If evidence is not
   applicable, record the controlled reason rather than leaving unexplained
   gaps.
3. Create or upload Evidence with type/category, source, date obtained,
   custodian, confidentiality, and file.
4. Verify the recorded file name, MIME type, size, SHA-256 checksum, Core
   Document, and exact Document Version.
5. Link Evidence to the Working Paper and later to the Issue/Finding where used.

Working Paper workflow:

`DRAFT -> SUBMITTED -> RETURNED_FOR_REVISION -> RESUBMITTED -> APPROVED`

- draft: edit, create immutable content versions, submit, or void;
- submitted/resubmitted: reviewer returns with instructions or independently
  approves and locks;
- returned: correct by saving a new content version, then resubmit;
- approved: use formal revision for correction; the approved version becomes
  superseded only through the controlled revision path.

Evidence workflow is `DRAFT -> VERIFIED -> LOCKED`, with controlled `VOIDED`
where permitted. Use **Verify** after checking metadata and checksum. Replace or
revise evidence through its version/replacement control rather than overwriting
the file.

Miscellaneous checks:

- duplicate Working Paper indexes within the engagement must be rejected;
- the preparer cannot perform the independent approval;
- approved Working Papers and their cited Evidence must be immutable;
- altered-file/checksum validation must fail;
- confidentiality must control discovery and download;
- voiding retains the file/version/history but prevents future reliance.

#### Evidence Requests and professional assessment (AEMS-5A)

Use an assigned auditor and an independent reviewer for this acceptance flow:

1. Create an Evidence Request and confirm version 1 is immutable. Edit the
   draft once and verify a new request version and lock version are created.
2. Submit and send the request. Record one exact current Evidence/Core
   `DocumentVersion` as partially received, then mark the request received.
3. Attempt **Assess** before recording an assessment and confirm the backend
   rejects the request. Assess the received evidence with the complete
   sufficiency/appropriateness/relevance/reliability/competence/accuracy/
   completeness/corroboration/contradiction/authenticity/integrity and
   confidentiality contract. Confirm the assessment cites the exact document
   version and is immutable.
4. Create a corrected assessment and verify the prior assessment is retained
   as a superseded version. Attempt to assess a replaced, voided, draft, or
   mismatched document version and confirm rejection.
5. Mark the evidence restricted or add access restrictions. Confirm the
   assessment and Finding validation report it as ineligible until a separate
   authorized user approves the exception with a reason. Confirm the assessor
   cannot approve their own exception.
6. Approve the exception, re-run Finding validation, and confirm the exact
   assessment/document version is now eligible. Verify events, Activity Log,
   Audit Trail, lock versions, and Core notifications for request transitions,
   assessment, and exception decision.
7. Close the request with a reason only after it is assessed. Verify protected
   downloads still require authenticated engagement scope and no public URL is
   returned.

Focused backend checks:

```text
cd backend
php artisan test --filter=AemsEvidenceRequestTest --testdox
php artisan test --filter='Aems' --testdox --no-coverage
```

Expected AEMS-5A checkpoint: `AemsEvidenceRequestTest` passes 2 tests and 21
assertions; the AEMS regression passes 51 tests and 877 assertions. New
evidence uploaded through the AEMS service is marked `assessment_required`.
Historical evidence rows created before AEMS-5A retain `false` for compatibility
and continue to use their existing verification/locking gate.

Browser acceptance for AEMS-5B:

1. Open **Evidence Management** from the AEMS Execution navigation and select
   an authorized engagement.
2. In **Evidence Requests**, inspect the register, lifecycle stepper, due date,
   requested items, immutable request versions, and receipt notes. Record
   partial and complete receipt only through the server actions.
3. Open **Evidence Register** and confirm each record distinguishes requested,
   received, assessed, restricted, insufficient/gapped, and accepted-for-
   reporting states. Inspect the exact Core Document Version, custody actor,
   checksum, file size, MIME type, and version comparison.
4. Use **Assess evidence** to record the professional assessment attributes.
   Confirm evidence gaps, limitations, and restricted access are visually
   prominent. Restricted evidence must show that reporting use is blocked until
   an independent exception approval is recorded.
5. Use the linked context actions to navigate to Working Papers, Fieldwork,
   Issues, Findings, and Reports while retaining the engagement ID.

Focused browser check:

```text
npx.cmd playwright test tests/e2e/aems-evidence-management.spec.js --project=desktop-chrome
```

#### Audit Issues

**Purpose:** Record a potential exception, validate whether it is supportable,
then dismiss it with a reason or convert it once into a Finding.

What to do:

1. Create the Issue with title, exception description, risk rating, responsible
   office, reviewer, exact approved Working Paper versions, and Evidence.
2. While `DRAFT`, edit and **Submit**.
3. As an independent validator, **Validate** the `SUBMITTED` Issue.
4. From `VALIDATED`, either **Dismiss** with a clear reason or **Convert to
   Finding**.

Workflow:

`DRAFT -> SUBMITTED -> VALIDATED -> DISMISSED or CONVERTED_TO_FINDING`

Miscellaneous checks:

- unsupported or draft Working Papers must block validation;
- dismissal and conversion are terminal, auditable, and mutually exclusive;
- conversion should carry the source content and exact support into one Finding;
- use **Open Finding** to verify the lineage after conversion.

#### Findings & Recommendations

**Purpose:** Develop the formal criteria-condition-cause-effect Finding,
communicate it, complete management dialogue, and finalize immutable
recommendations.

What to do:

1. Create or open the converted Finding.
2. Complete title, criteria, condition, cause, effect, risk rating, responsible
   office, exact Working Paper versions, and Evidence.
3. Add one or more Recommendations with action, responsible office, and target
   implementation date. If no recommendation is appropriate, enter the formal
   no-recommendation reason.
4. Submit for review and have an independent user validate it.
5. Communicate the validated Finding with confidentiality, recipients, and the
   management-response due date.
6. Use **Request Response** to move it into the auditee response stage.
7. After the dialogue is finalized, use **Finalize Finding**.

Finding workflow:

`DRAFT -> PENDING_REVIEW -> VALIDATED -> COMMUNICATED -> AWAITING_MANAGEMENT_RESPONSE -> UNDER_DIALOGUE -> FINALIZED`

If the response deadline passes without a response, an authorized auditor may
use **Record Non-response** with the required reason instead of inventing a
management response.

Miscellaneous checks:

- Recommendations can be added, edited, or removed before finalization only;
- validation must be blocked by unsupported evidence/Working Papers;
- only the formally communicated responsible office should receive access;
- finalization must lock the Finding and Recommendations and preserve exact
  evidence links;
- verify the communication and finalization notifications and history.

#### Auditee Responses

**Purpose:** Provide the responsible office a restricted workspace for its
formal position, corrective action, clarification, and supporting documents.

As the Auditee Representative:

1. Confirm only Findings formally communicated to the user's office appear.
2. Draft a response and choose agree, partially agree, or disagree.
3. Enter management comments, proposed corrective action, responsible person,
   and proposed target date.
4. Upload supporting documents and verify their protected download.
5. Submit the response.

As the auditor:

1. Open a `SUBMITTED` or `RESUBMITTED` response and choose **Start Review**.
2. Either **Request Clarification** with clear instructions or add an auditor
   rejoinder with disposition accept, partially accept, or reject.
3. The auditee uses **Create Revision** after clarification, edits the new
   version, adds attachments if needed, and resubmits.
4. An authorized independent user selects **Finalize Dialogue** on the rejoinder.

Miscellaneous checks:

- every response/rejoinder version must retain actor, date, content, and exact
  attachments;
- the prior submitted response must not be overwritten by a clarification
  revision;
- users from another office must not discover the Finding or download files;
- finalized dialogue is immutable and permits Finding finalization.

#### Exit Conferences

**Purpose:** Record the post-fieldwork discussion of Findings, agreements,
disagreements, revised dates, minutes, attendance, and auditee acknowledgement.

What to do:

1. Create the conference with schedule, venue or online details, agenda,
   participants, and the Findings to discuss.
2. Reschedule when needed and retain the schedule history.
3. Record attendance.
4. For every linked Finding, record discussion status, agreement/partial
   agreement/disagreement, details, discussion notes, and revised target date
   where applicable.
5. Enter overall agreements, disagreements, summary, and minutes; upload
   supporting attachments.
6. Select **Complete and Lock** only after every required Finding outcome and
   attendance item is complete.
7. Sign in as the Auditee Representative and record acknowledgement or the
   available acknowledgement-with-comment outcome.

An editable scheduled/rescheduled conference may be **Waived** or **Cancelled**
with the required reason. Waiver is an exceptional professional decision, not a
shortcut for incomplete minutes.

Miscellaneous checks:

- only communicated or later Findings should be selectable;
- each Finding link must open the correct Finding;
- completed minutes must be immutable;
- revised target dates here must remain traceable and must not silently bypass
  later CMS target-date controls;
- verify attendance, attachments, acknowledgement, and status history.

#### Audit Reports

**Purpose:** Generate controlled Draft and Final Reports, retain immutable
versions, issue the approved final document, and transfer recommendations to
CMS once.

Draft Report steps:

1. Create a Draft Report from validated-or-later Findings.
2. Select included Findings, arrange sections, enter executive summary and
   report content, set confidentiality, and record recipients/reviewer content.
3. Submit for review. A reviewer may return it; generate a corrected version and
   resubmit; an independent approver then approves it.

Final Report steps:

1. Generate the Final Report from the approved Draft Report. Only finalized
   Findings are eligible.
2. Record approving authority, confidentiality, recipients, and issuance data.
3. Submit, return/revise/resubmit when needed, and approve.
4. Select **Issue** only on the approved Final Report.
5. Download the exact PDF and verify version, checksum, recipients, and issued
   date.
6. Select the CMS transfer action. Retry it and confirm the same
   Recommendations are not duplicated.

Report workflow:

`DRAFT -> PENDING_REVIEW -> RETURNED_FOR_REVISION -> RESUBMITTED -> APPROVED -> ISSUED`

Only a Final Report may reach `ISSUED`; the Draft Report ends at approved and is
the controlled source used to generate the Final Report.

Miscellaneous checks:

- return comments and reviewer comments must be retained by version;
- issued versions and their files are locked;
- confidentiality controls both visibility and download;
- Findings included in each version must be reproducible;
- CMS transfer must be idempotent and each Recommendation transfers only once;
- after issuance, finish the Completion Assessment, formal Closure, retention,
  Document Index, and lessons in Engagement Detail.

### 6.4 Audit Resource Management pages

ARMIS is a protected operational module. Use a resource administrator, planner,
independent reviewer, and provider-authority approver as separate accounts. Do
not use one account for every action: ARMIS enforces scope, optimistic locking,
immutable versions, and separation of duties.

#### ARMIS Dashboard and module context

Open `/audit-resource-management`. Confirm that the module redirects to the
Resource Registry and that only pages permitted by `armis.*` permissions appear.
Check that the provider banner identifies ARMIS as the sole operational
provider and that no IAP fallback or provider-switch action is exposed. The
current ARMIS ledger must be the AEMS read path without an authority decision.

#### Resource Registry

Open `/audit-resource-management/resources` and create a resource profile as a
draft. Verify identity, office scope, employment/status fields, search and
filters, optimistic-lock conflict handling, lifecycle submission, archive, and
restore. A second office-scoped user must not see the record. A reviewer must
approve a submitted profile independently; corrections use a new revision rather
than editing an approved version.

#### Competencies and Certifications

Open `/audit-resource-management/competencies`. Create a competency claim from
the allowed Core catalogue and link evidence to one exact Core
`document_versions` record. Upload/download through the protected controller,
submit for verification, and approve it with a different account. Confirm that
the evidence checksum, MIME type, size, confidentiality, actor, and dates are
visible and that verified records are read-only. Test returned revision,
expiry/revocation, and scope-safe access.

#### Planning and Utilization

Open `/audit-resource-management/planning`. Create availability, annual
capacity, and planned-workload records for a fiscal year. Submit, independently
review, return/revise, and lock each record. Confirm date-overlap validation,
annual capacity and utilization calculations, ARMIS demand lineage,
optimistic locking, and notifications. The utilization cards and calendar are
read-only projections of approved/current ARMIS records; they must not silently
rewrite IAP data.

#### Assignments and Actuals

Open `/audit-resource-management/assignments`. Create an engagement assignment
with role, required competency, planned person-days, dates, and office scope.
Confirm missing-role, competency, date-overlap, and capacity warnings. Submit,
independently review, and lock the assignment. Record actual person-days through
the separate actuals workflow, submit/review/lock them, and use a revision for a
correction. Verify that planned and actual ledgers remain separate and that
assignment conflicts cannot be bypassed by the browser.

#### Retired Provider Reconciliation Route

The former `/audit-resource-management/provider-reconciliation` path is kept
only as a compatibility redirect to Provider Monitoring. It does not generate
new comparisons, change ownership, or offer rollback to IAP.

#### Provider Monitoring

Open `/audit-resource-management/provider-monitoring`. Run a health/cutover
check with the monitoring permission and inspect the immutable result, expected
versus observed values, source snapshot, checksum, and notification on failure.
Monitoring must never activate or roll back provider authority.

#### Reports and Administration

Open `/audit-resource-management/reports`. Generate a scope-aware ARMIS report,
inspect its immutable rows, query version, scope summary, and checksum, then
create CSV and PDF exports with `armis.report.export`. Confirm formula-injection
neutralization, private storage, MIME/size/checksum metadata, authenticated
downloads, and scope/confidentiality revalidation. The Administration tab is
read-only status and hardening information; it must not expose storage paths or
provider write controls.

#### ARMIS deployment checks

After a deployment, run the read-only preflight and Render smoke verifier from
the repository root. The strict preflight is appropriate for PostgreSQL/HTTPS
deployment; the non-strict command is suitable for local development.

```powershell
php artisan armis:deployment-check
php artisan armis:deployment-check --strict
./scripts/verify-armis-render.ps1 -BaseUrl https://<service>.onrender.com
```

The smoke verifier checks `/health`, the compiled SPA, nested ARMIS route
fallback, anonymous ARMIS API rejection, and security headers. It does not sign
in, mutate data, run migrations, or change provider authority.

## 7. Full business-journey test sequence

### CORE-01 — Login and account security

1. Sign in with each demo account.
2. Confirm the name, role, office, and permitted navigation are correct.
3. Sign out and confirm the protected dashboard redirects to login.
4. Enter an invalid password and confirm the attempt is rejected and logged.
5. Confirm locked or inactive accounts cannot sign in.
6. Change a password with the profile page and sign in again.

### CORE-02 — Registries and access control

As an administrator:

1. Create, edit, archive, and restore an Office.
2. Create an Audit Area with a child area and an Audit Focus.
3. Attempt to create a parent cycle; confirm it is rejected.
4. Create or clone a role and assign permissions/scopes.
5. Create or update a user and assign an office and role.
6. Confirm every mutation appears in Activity Log and Audit Trail.

Repeat the same pages as `auditor`, `auditee`, and `mayor`. Confirm forbidden
actions return an authorization error and do not change data.

### CORE-03 — Documents, workflows, notifications, and configuration

1. Upload a private document and create a new immutable version.
2. Try to download it with an unauthorized or insufficiently scoped account.
3. Start a workflow instance and execute an allowed transition.
4. Open, read, archive, and restore notifications.
5. Change a safe runtime setting as an administrator and confirm it applies.
6. Review the Activity Log and Audit Trail filters and exports.

### IAP-01 — Build and approve an audit plan

1. Open the IAP Dashboard and confirm live cards and filters load.
2. Create or open a Strategic Internal Audit Plan.
3. Review the Audit Universe and confirm office/area/focus relationships.
4. Create a risk period and assessments; verify score calculations and review.
5. Run prioritization and inspect the ranking and any documented override.
6. Create an Annual Audit Plan, add engagements, and submit it for review.
7. Use CIAS Management to review, return, resubmit, and approve the plan.
8. Build and conflict-check the schedule while the plan is editable, then
   approve the plan and confirm the schedule becomes read-only.
9. Open each IAP report and verify its filters, scope, and export behavior.

### AEMS-01 — Create the engagement

1. Import an approved IAP engagement, or create a special-authority engagement.
2. Confirm source lineage and duplicate-import prevention.
3. Assign an audit team and verify assignment history.
4. Prepare, independently review, approve, and issue the AEO.
5. Prepare, review, approve, and revise the AEP when applicable.

### AEMS-02 — Plan and perform fieldwork

1. Create and approve the Audit Program.
2. Add procedures, assign them to team members, and record progress/review.
3. Create Entry Conference details, participants, attendance, matters,
   agreements, attachments, and acknowledgement.
4. Create Working Papers with objective, procedure, population/sample, result,
   conclusion, preparer, reviewer, and cross-references.
5. Upload Evidence and verify its checksum, confidentiality, MIME type, size,
   custodian, date obtained, and exact Core Document Version.
6. Link Evidence to Working Papers and Findings.
7. Submit a Working Paper, return it as a reviewer, resubmit it, and approve it
   as an independent reviewer.
8. Confirm approved Working Papers and cited Evidence are locked. Start a
   correction and confirm a new immutable revision is created.

### AEMS-03 — Issues, Findings, Responses, and Exit Conference

1. Create an Issue with exact Working Paper/Evidence support.
2. Submit and independently validate it, then dismiss it or convert it once.
3. Create a Finding with criteria, condition, cause, effect, risk, office,
   evidence, and recommendation.
4. Communicate the Finding to the responsible office.
5. Sign in as the Auditee Representative and confirm only that office's
   communicated Findings are visible.
6. Submit agreement, partial agreement, or disagreement with comments, action,
   responsible person, target date, and supporting files.
7. As an auditor, request clarification, add a rejoinder, and finalize the
   dialogue.
8. Schedule and complete an Exit Conference with linked Findings, attendance,
   agreements/disagreements, revised dates, minutes, attachments, and
   acknowledgement.

### AEMS-04 — Reports, transfer, and closure

1. Generate a Draft Report from selected Findings and arrange its sections.
2. Add the executive summary and reviewer comments.
3. Return the report for revision, then resubmit it.
4. Generate the Final Report using finalized Findings only.
5. Approve and issue it with an approving authority, recipients, dates,
   confidentiality, PDF checksum, and immutable version.
6. Transfer recommendations to CMS and retry the transfer; confirm it is
   idempotent.
7. Complete the Completion Assessment and resolve or formally accept blockers.
8. Create and approve the formal Closure record.
9. Confirm the engagement changes to `CLOSED` atomically and becomes locked.
10. Submit an authorized reopening request with an exact authority document
    version, approve it, implement it, and confirm the original closure remains
    immutable history.

11. Open the engagement's **Completion & Transfer** tab. Reconcile the CMS
    transfer manifest and ARMIS/IAP effort snapshot. Confirm the issued report
    version, Core Document checksum, transferred/excluded counts, provider
    mode, and planned-versus-actual variance are visible.
12. Reconcile a second time and confirm the same manifest and CMS transfer
    keys are reused (no duplicate CMS case). Introduce an incomplete transfer,
    confirm an open exception blocks Closure, resolve the source record, and
    reconcile again.
13. Have an independent CIAS/reviewer account approve the reconciled manifest
    and effort snapshot. Confirm the generator cannot approve, lock versions
    cannot be edited, and the formal Closure checklist shows the three new
    transfer/effort checks as passed only when their server conditions hold.

### CMS-01 — Monitor a transferred recommendation

1. Open the CMS Dashboard and Recommendation Registry with a scoped account.
2. Open the recommendation detail and verify AEMS source lineage.
3. Assign a Compliance Monitor and confirm separation of duties.
4. Create, submit, review, return, revise, and accept an Action Plan.
5. Submit Progress Updates with milestones and supporting documents.
6. Record independent Validation and confirm management reporting cannot replace
   professional validation.
7. Request and approve a target-date extension.
8. Create an Escalation candidate/draft and complete its controlled review.
9. Create a Closure Request, conduct independent review, and make the final
   closure decision.
10. Test Accepted-Risk and No-Longer-Applicable dispositions separately from
    implementation and ordinary closure.
11. Test controlled reopening and confirm the original closure/disposition
    decision remains immutable.

### CMS-02 - Automation, reports, and protected exports

1. Open `/compliance-management/automation` with `cms.automation.view` and
   confirm rules, recent runs, reminders, closure-readiness candidates, and
   escalation candidates are scope-filtered.
2. As an authorized manager, create or revise a rule and confirm each save
   creates an immutable rule version.
3. As an authorized operator, run automation twice for the same business date
   and confirm replay-safe deduplication. Verify reminders are generated only
   for eligible cases.
4. Acknowledge or dismiss a candidate with a note and confirm the case status,
   closure state, disposition, reopening state, and escalation notice remain
   unchanged.
5. Open `/compliance-management/reports`, generate each supported report, and
   verify scope, confidentiality, deterministic rows, query version, and
   checksum.
6. Export CSV and PDF with `cms.report.export`. Confirm formula-like CSV cells
   are neutralized, artifacts are private, and downloads require authentication
   plus current scope/confidentiality.

### ARMIS-01 - Resource planning, authority, and monitoring

1. Complete the ARMIS page-by-page tests in section 6.4 with separate resource,
   reviewer, planner, and provider-authority accounts.
2. Verify an approved IAP engagement or requirement is visible as source demand
   without modifying the IAP record.
3. Create and approve resources, competencies, availability, capacity,
   workloads, assignments, and actual person-days; verify revisions are
   immutable and all evidence uses protected Core document versions.
4. Generate an IAP-versus-ARMIS shadow reconciliation, review every discrepancy
   independently, activate only after all authority gates pass, and verify the
   immutable authority decision. Test rollback separately.
5. Run provider monitoring and confirm failed checks create notifications but do
   not switch authority.
6. Generate ARMIS reports and protected CSV/PDF exports and verify scope,
   confidentiality, checksums, and authenticated downloads.
7. Run `php artisan armis:deployment-check --strict` in the deployment
   environment and the Render smoke verifier after deployment. Record the
   output with the release evidence.

## 8. Cross-cutting security and integrity checks

For each module, verify:

- office and engagement scope prevents unauthorized discovery and mutation;
- preparer, reviewer, approver, validator, and issuer separation is enforced;
- stale `lockVersion` requests fail without partial updates;
- approved/issued/finalized versions cannot be edited or deleted;
- soft-deleted records can be restored only with permission;
- private files require authenticated, scope-aware downloads;
- checksums and exact Core Document Versions are preserved;
- repeated CMS/AEMS transfers do not create duplicates;
- Activity Log and Audit Trail entries contain actor, action, record, and time;
- notifications contain valid scoped deep links.

## 9. Responsive browser checks

Run the desktop and mobile Playwright projects. Manually inspect the Dashboard,
IAP Dashboard, AEMS Dashboard, CMS Dashboard, registries, detail workspaces,
forms, tables, dialogs, file controls, and navigation at narrow widths. Confirm
that content remains reachable without horizontal clipping and that cards do not
leave unexplained empty columns.

## 10. Automated verification before sign-off

From the repository root:

```powershell
npm.cmd run lint
npm.cmd run build
php backend/artisan test --testsuite=Feature
npm.cmd run test:e2e
git diff --check
```

Useful targeted browser commands are:

```powershell
npx.cmd playwright test tests/e2e/iap-responsive.spec.js
npx.cmd playwright test tests/e2e/aems-responsive.spec.js
npx.cmd playwright test tests/e2e/cms-responsive.spec.js
npx.cmd playwright test tests/e2e/cms-dispositions.spec.js
npx.cmd playwright test tests/e2e/cms-reopening.spec.js
npx.cmd playwright test tests/e2e/aems-shell.spec.js tests/e2e/aems-planning-package.spec.js tests/e2e/aems-execution.spec.js tests/e2e/aems-evidence-management.spec.js tests/e2e/aems-issues-findings.spec.js tests/e2e/aems-conference-dialogue.spec.js tests/e2e/aems-reporting.spec.js tests/e2e/aems-responsive.spec.js
npx.cmd playwright test tests/e2e/aems-error-sanitization.spec.js
```

`npm.cmd run test:e2e` starts the Laravel and Vite servers through
`playwright.config.js`. If a test is throttled or times out, record it as
blocked and rerun it after the environment is stable; a timeout is not a pass.

The CMS-11B stabilization gate runs the complete CMS browser set (66 tests)
across the desktop and mobile projects. The Playwright backend server uses
`APP_ENV=testing`, so isolated contexts can sign in repeatedly without
exhausting the production-sized login-rate buckets. This is test-server-only;
production authentication limits and account-lock behavior remain unchanged.

The AEMS final verification gate also runs `php artisan migrate:fresh --seed`
against the explicitly configured local/testing database before the regression
suite. Never run that destructive command against a deployment database. The
browser API boundary redacts SQLSTATE, PDO, and database-driver diagnostics;
the error-sanitization test protects the requirement that SQL errors are not
shown to users.

## 11. Sign-off checklist

- [ ] Core role and scope tests passed
- [ ] IAP plan and approval flow passed
- [ ] AEMS engagement-to-closure flow passed
- [ ] CMS monitoring-to-closure flow passed
- [ ] Negative authorization and separation-of-duties tests passed
- [ ] Immutable version and checksum checks passed
- [ ] Activity Log and Audit Trail evidence captured
- [ ] Desktop and mobile checks passed
- [ ] Automated lint, build, backend, and browser verification passed
- [ ] Known deferred modules/features were recorded separately

## AEMS-2A planning-package acceptance

1. Open an authorized engagement after AEO, AEP, and Audit Program preparation.
2. Create a Planning Package and confirm the response preserves IAP lineage;
   create a second package and confirm the duplicate is rejected.
3. Enter survey, objectives, process flows, risk matrix/items, and links to a
   current Audit Program procedure and working-paper reference. Confirm the
   readiness response identifies incomplete checks before submission.
4. Submit, review as a different user, and approve as a separate authority.
   Confirm the approved version/checksum and review decision are visible.
5. Attempt to edit an approved version and confirm it is rejected; start a
   formal revision and confirm the previous approved version remains intact.
6. Attempt aggregate `START_FIELDWORK` without an approved package and confirm
   a planning-package blocker. Approve the package and confirm the gate passes.

## AEMS-2B planning-package workspace acceptance

1. Open the Planning Package route from an authorized engagement and confirm the
   shared engagement navigation remains visible while the planning sections
   change in place.
2. Create a draft, add objectives, complete the preliminary survey, add a
   process flow, risk matrix, risk item, and objective/procedure/working-paper
   links. Save and confirm a new immutable version is shown.
3. Open Readiness & Review and confirm the checklist is sourced from the API;
   submit, record an independent review as a different user, return the package,
   revise the content, and resubmit it.
4. Approve as a separate authority, open Versions, inspect the approved
   snapshot, and compare two versions. Confirm approved fields and risk-item
   detail are read-only and that the approved version remains intact after a
 formal revision.

## AEMS-10A/10B dashboard and queue acceptance

1. Sign in as management and as an assigned auditor. Open AEMS Dashboard and
   confirm the same endpoint returns different engagement counts and queue
   rows according to each user's scope; confirm the browser source contains no
   dashboard fixture/mock records.
2. Apply Planning, Fieldwork, Reporting, Closure, status, office, and search
   filters. Confirm both the tracker and phase counts are recalculated by the
   backend and that an unauthorized engagement never appears.
3. Create or use records for an overdue procedure, submitted Working Paper,
   sent Evidence Request, evidence assessment gap, pending finding, upcoming
   conference, pending report, open transfer exception, draft Review Note,
   open task, and escalation candidate. Confirm each appears in its queue and
   deep link, while an empty queue has an explicit empty state.
4. Confirm metric cards hover and auto-fit the row on desktop and reflow without
   clipping on mobile. Verify loading, error/retry, unauthorized, and no-result
   states.
5. As an authorized exporter, download Progress CSV and Work Queues CSV;
   verify authentication, scope filtering, Activity Log/Audit Trail entries,
   preserved protected downloads, and formula-prefixing for values beginning
   with `=`, `+`, `-`, or `@`. Confirm a user without
   `aems.engagement.export` receives 403.
6. Change the Core notification settings for the AEMS reminder switch and due
   windows. Run `php artisan notifications:dispatch-reminders` and confirm
   reminders follow the configured windows, remain deduplicated, and never
   approve, close, transfer, or issue a record.

## AEMS-G1 professional-control acceptance

1. Record a current Evidence assessment with a missing or negative rating and
   confirm the workspace shows `Not eligible` with blocking reasons. Verify
   that Evidence is not available in the Finding reporting-support selector
   and that Validate/Finalize actions are absent.
2. Resolve every rating and evidence gap against the exact current Core
   Document Version. Confirm the Evidence state becomes `Accepted for
   reporting` and the Finding actions become available only after all other
   workflow rules pass.
3. Attempt to update an existing Evidence Request Version or Assessment row
   directly. Confirm the API rejects mutation; record a correction and verify
   that a new immutable superseding version is created instead.
4. Create a Finding without Conclusion and confirm request validation fails.
   Create a direct Finding without an authority reason/reference and confirm
   it fails; submit it with an allowed reason and reference and verify the
   actor/timestamp provenance in detail.
5. Configure an approved Planning Package KPI or progress control with missing
   required fields. Confirm the engagement transition reports a specific
   blocker; configure the control completely and verify the gate is evaluated
   from the planning baseline.
