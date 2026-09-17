# AGIS Operational Playbooks

This playbook turns the [AGIS As-Built System Manual](AGIS_AS_BUILT_SYSTEM_MANUAL.md)
into repeatable user procedures. Each playbook states the prerequisites, the
page/action, the resulting state, and the audit/notification expectation.

## 1. Before any transaction

1. Sign in with employee ID and password.
2. Confirm the selected office, role, and visible module cards. If a record is
   absent, first check office/engagement scope and confidentiality; do not ask
   an administrator to bypass a policy.
3. Open the record detail and verify its current status, current revision,
   lock/version number, owner, and availableActions.
4. Save attachments through the protected uploader. Do not email a file and
   then mark it received without registering the document version.
5. After every mutation, confirm the success message and inspect the Activity
   Log/timeline. If the browser disconnects, inspect the record before retrying.

## 2. Core administration

### 2.1 Create a user and grant access

Prerequisites: administrator role, office record, and approved role/permission
catalogue.

1. Open User Registry > New user.
2. Enter the employee ID, normalized name, one office, employment details, and
   active state.
3. Assign only the roles required for the job. Review the effective permission
   preview and office/engagement scope.
4. Save. Confirm the new user appears in the registry and that an audit event
   records the actor and role changes.
5. Ask the user to sign in and confirm that forbidden modules are absent. A role
   change is not complete until the user signs out/in or the session is refreshed.

To remove access, disable or lock the account. Use archive only for an
administrative record-retention decision. Restore the same record instead of
creating a duplicate employee.

### 2.2 Register an office, area, focus, or master value

1. Open the relevant Core registry and select New.
2. Enter the controlled code/name, responsible office, description, active state,
   and any required parent/list value.
3. Save and inspect duplicate/cycle validation. Area hierarchy cannot contain a
   cycle; a focus belongs to one area.
4. Archive rather than delete a value already referenced by IAP/AEMS/CMS. Restore
   only when the authority confirms it is active again.

### 2.3 Upload and release a protected document

1. Create metadata and select confidentiality and retention classification.
2. Upload the file and wait for server checksum/size/MIME/version response.
3. Link the exact document version to the business record.
4. For a reviewer, use protected view/download and compare checksum with the
   displayed version. For an issued document, do not edit metadata that changes
   the meaning; create a revision.
5. If a download is denied, verify permission, office, engagement, and
   confidentiality scope. Never make storage public to solve a 403.

## 3. IAP planning

### 3.1 Build and approve a Strategic Audit Plan

Prerequisites: current audit universe and risk source.

1. Open Strategic Audit Plan > New draft.
2. Enter period, objectives, risk themes, proposed coverage, assumptions,
   responsible office, and supporting documents.
3. Save while Draft. Use the validation/readiness panel before submitting.
4. Select Submit. The plan moves to Pending Review and the submitted snapshot is
   preserved.
5. Reviewer opens the review queue. Select Return and record precise corrections,
   or Approve after independently checking scope, risk, resources, and source
   lineage.
6. If returned, the preparer edits only the returned/current draft, records the
   revision, and selects Resubmit. Do not create a second unrelated plan.
7. Activate the approved plan for the operating period, then mark it Completed
   after its period closes. Approved history remains immutable.

### 3.2 Maintain the Audit Universe and choose a risk system

1. Register/update auditable entities, offices, areas, focuses, objectives, and
   coverage exclusions in Audit Universe.
2. Use Risk Assessment for the plan-level system when assessing a plan, or the
   universe-level system when assessing an individual universe item. Confirm
   which source is selected before scoring.
3. Enter criteria, inherent/residual scores, rationale, evidence, reviewer, and
   version. Submit for independent review and finalize the prioritization run.
4. Do not copy values between iap_risk_assessments and
   iap_universe_risk_assessments merely to make totals match. Their lineage is
   intentionally distinct.
5. Open Audit Prioritization, review the finalized ranking, and use it as the
   source for the Annual Audit Plan.

### 3.3 Approve the Annual Audit Plan and schedule work

1. Add eligible universe items from a finalized prioritization run.
2. Assign office, proposed engagement owner, area/focus, period, resource
   estimate, and priority. Validate duplicate source/plan references.
3. Submit, review/return/resubmit, and approve with separation of duties.
4. Activate the plan, then open Audit Scheduling to place approved items on
   dates/milestones. A schedule revision records the old/new date and reason.
5. Use ARMIS Planning and Utilization to review current capacity, leave,
   competencies, and requirements before handing a plan to AEMS. IAP resource
   values are historical planning lineage only and are not an operational
   fallback.

## 4. AEMS engagement execution

### 4.1 Import an approved IAP plan

Prerequisites: IAP plan is Approved or Active, source is in scope, and the user
has the AEMS create/import permission.

1. Open Audit Engagement Workspace > Import approved IAP plan.
2. Search by source plan, year, office, area/focus, and engagement option.
3. Open the preview and confirm the source ID/version, risk source, office,
   schedule, and snapshot hash.
4. Confirm Import once. Wait for the response before clicking again.
5. Open the new engagement and verify IAP lineage. If a duplicate warning is
   returned, use the existing engagement; do not force a second import.

### 4.2 Prepare and issue an AEO

1. Confirm the engagement is in `DRAFT`, open Engagement Scope, select exactly
   one office, then select only office-linked Audit Areas and Area-linked Audit
   Focuses. Save the scope and verify the selected Focuses persist after reload.
2. Open Lifecycle and select **Prepare Authorization**. Confirm Audit Team is
   locked in `DRAFT` and becomes available in `AUTHORIZATION_PREPARATION`.
3. Assign the team and complete the required ARMIS competency, capacity,
   workload, objectivity, conflict-of-interest, and independence safeguards.
4. Open Engagement Orders for the engagement and create a Draft version.
5. Complete objective, authority, scope/office, period, team/roles, signatory
   matrix, recipients, transmittal method, and attachments.
6. Save and run readiness. Fix missing signature/distribution fields.
7. Select Submit. The assigned reviewer records review or selects Return with a
   reason. If the preparer is the active CIAS Head, she may record the AEO
   review herself under the documented exception. When no alternate CIAS
   Management authority is available, she may also approve and issue it.
8. Otherwise, an active CIAS Management account approves the reviewed AEO. A
   different active CIAS Management account then records the issuing signature,
   issue date, recipients,
   transmittal, and acknowledgement requirements, then selects Issue.
9. Verify status ISSUED, immutable version/checksum, and notifications.
   Auditee recipients do not open the internal AEMS workspace. They use the
   CMS **AEO Acknowledgements** page (or its notification link) to acknowledge
   the issued transmittal addressed to their user or office. The page shows
   the exact issued AEO version (authority, objectives, scope, dates, office,
   audit areas, and signatories) and offers an authenticated **Download
   approved AEO** action. The transmittal recipient is selected from the
   engagement-scoped office or active auditee-representative user list; IDs
   must not be typed manually.
10. Open the engagement **Lifecycle** tab and select **Issue Authorization**.
   This is the aggregate gate that follows child AEO issuance and changes the
   engagement status to **AUTHORIZED**. If the active CIAS Head is the sole
   CIAS Management authority, she may execute this gate for an engagement she
   created; the exception is restricted to this authorization action and is
   logged. Then select **Start Planning** to move to **ENGAGEMENT_PLANNING**.
11. To correct an issued AEO, select Amend/Supersede and create a new version.
   Cancel/Void is reserved for the authorized invalidation decision and requires
   a reason; it does not delete the prior version.

When the active CIAS Head is the sole active CIAS Management authority, the
same controlled exception applies to her own AEMS review/acceptance actions in
planning, execution, findings, reporting, transfer, and closure. The exception
does not skip readiness, evidence, version-lock, status, or audit requirements;
it only permits the same account to act where no alternate CIAS Management
reviewer or approver exists.

### 4.3 Prepare the AEP and Planning Package

1. After an issued AEO, create the AEP Draft: objective, criteria, scope,
   communication plan, approach, resources, and limitations.
2. Submit for independent review; Return includes a reason, Resubmit creates a
   new revision, Approve establishes the baseline.
3. Open Planning Package. Complete Preliminary Survey, Process Flow steps,
   inputs/outputs/controls/decision and risk points, risk matrix/items,
   relationships to objectives/procedures/WPs, KPIs, sampling, and planned WP.
4. Run readiness and fix every blocking item. Submit, review, return/resubmit, or
   approve. The approved package version is immutable.
5. If a planning fact changes, use New Revision. Fieldwork remains on the last
   approved baseline until the revision is approved.

### 4.4 Build the Audit Program and start fieldwork

1. Create a Draft program using the approved AEP/planning baseline.
2. Add each procedure with process, method, criterion, risk, area/focus,
   sample, planned days, WP, and required fieldwork record type.
3. Submit for independent review. Approve only after readiness and team provider
   status pass.
4. Select Start/Activate only when AEO, AEP, Planning Package, program, and
   assignment safeguards are approved.
5. For each procedure, create Fieldwork Records as work occurs. Record date,
   location, participants, record type, results, conclusion, reviewer note,
   procedure/WP/evidence links, tasks, and status.
6. Mark a procedure complete only when the traceability panel shows an execution
   record. Overdue procedures appear in the dashboard/work queue.

### 4.5 Request, receive, and assess evidence

1. Open Evidence Management > New Request. Enter evidence type, source,
   custodian, date needed, confidentiality, restrictions, requested records,
   and linked procedure/WP/finding.
2. Save Draft, then Submit. The responsible sender marks Sent and records
   correspondence. The custodian/auditee can acknowledge or submit a partial
   response.
3. Upload each file through the protected document flow. Verify checksum, size,
   MIME, custody, and version. Do not mark a file Received before the upload
   succeeds.
4. Use Partial Receipt until all expected items arrive. For lateness, request an
   extension or escalate; record due-process events and notifications.
5. On complete receipt, assess every professional attribute. Select an explicit
   outcome such as accepted, limited, additional required, rejected,
   superseded, or duplicate, and document limitations/gaps.
6. Close the request only when receipt/assessment/outcome is complete or an
   authorized no-submission/cancellation decision is recorded.
7. Before linking evidence to a finding, confirm the finding page displays an
   eligible assessment. Negative, partial, not-assessed, restricted-without-
   exception, and unresolved-gap evidence must remain unavailable for final use.

### 4.6 Submit and finalize a Working Paper

1. Create Draft WP with unique index, objective, procedure, population/sample,
   result, conclusion, preparer/date, cross-references, and evidence links.
2. Save and submit. Reviewer returns with reason or approves.
3. On Return, create the correction in the current returned draft and Resubmit.
4. On Approval, verify the reviewer/date and immutable version. Use New Revision
   for a post-approval correction; do not edit the approved record.

### 4.7 Handle an Issue and create a Finding

1. Create an Issue from a fieldwork result or other authorized source. Link
   procedure, WP, evidence, area/focus, and source record.
2. Submit and validate it with an independent actor. Choose the terminal/working
   disposition: convert to finding, merge, resolve during audit, observation,
   refer, close without finding, dismiss, or withdraw. Record reason and target
   status; a withdrawn issue remains auditable.
3. If policy permits direct finding creation, enter the authorized reason and
   authority before saving; do not bypass that form.
4. Draft the finding with Criteria, Condition, Cause, Conclusion, Effect,
   significance, risk, responsible office, evidence, recommendation, and
   traceability. Submit for independent review.
5. The reviewer returns or validates. The author cannot validate the finding.
6. Communicate the validated finding formally to the responsible office. Record
   recipient, due date, delivery/transmittal, and acknowledgement.
7. A communicated auditee submits Agree, Partially Agree, or Disagree with
   comments, corrective action, responsible person, target date, and support.
8. The auditor Accepts, Partially Accepts, Rejects, Requests Clarification,
   grants a governed extension, and adds a rejoinder. Each exchange is a
   versioned timeline item.
9. Finalize dialogue only after the response/rejoinder/no-response due-process
   path is complete. Finalize the finding/recommendation only after independent
   review and eligible evidence. Corrections create immutable amendment or
   supersession snapshots.

### 4.8 Hold entry and exit conferences

Entry:

1. Open Entry Conferences, select the engagement, and schedule venue or online
   details, agenda, participants, and notification.
2. Record attendance, opening scope/criteria, agreements, minutes, and
   attachments. Create follow-up tasks for absent records.

Exit:

1. Select only validated/communicated findings in scope.
2. Record participant attendance, each finding discussed, agreement/disagreement,
   revised target dates, minutes, attachments, and acknowledgement.
3. Confirm the acknowledgement is stored as a document/version when a file or
   signature is used. A disagreement remains a dialogue outcome; it does not
   remove the finding.

### 4.9 Assemble, approve, issue, and distribute a report

1. Open Audit Reporting Workspace and select Interim, Draft, Final, or
   Distribution stage.
2. Add sections and executive summary; attach reviewer comments and quality
   checklist; link exact Issues/WPs/Evidence/Fieldwork/Interim sources.
3. For a Final Report, select only Finalized findings. The API rejects a draft,
   validated, or returned finding.
4. Submit for review. Reviewer returns with comments or approves. Record the
   approving authority and signatory matrix.
5. Prepare recipient/transmittal/delivery/acknowledgement data. Record IAU Head
   or LCE authority decision where required.
6. Issue. Verify immutable PDF checksum/version and confidentiality.
7. Distribute through protected delivery/download actions and record each
   recipient decision. To fix an issued report, create amendment, withdrawal, or
   superseding version; never overwrite the issued PDF.

### 4.10 Transfer recommendations to CMS and close AEMS

1. Open Completion & Transfer and run the readiness checklist.
2. Resolve blockers: approved WPs, eligible evidence, finalized findings,
   issued report, distribution, tasks/review notes, ARMIS actual reconciliation,
   retention/index, lessons learned, and limitations disclosure.
3. Select finalized recommendations and inspect the immutable report/version,
   recommendation snapshot, responsible office, and transfer key.
4. Confirm CMS Transfer once. Record the manifest and result. If the API says
   duplicate, open the existing CMS intake instead of retrying.
5. Resolve exceptions and reconcile included/excluded recommendations. CMS owns
   the new case; AEMS preserves source provenance.
6. Submit completion assessment and approval. Complete retention/archive/legal-
   hold/disposition review and lessons learned, then request formal closure.
7. If closure is later necessary to reverse, submit a controlled Reopen Request
   with reason and evidence. Independent authority approves/rejects it; the
   original closure decision remains immutable.

## 5. CMS monitoring

### 5.1 Accept and assign an intake

1. Open Recommendation Registry and inspect source report/version, recommendation
   snapshot, checksum, responsible office, and transfer provenance.
2. Accept/confirm intake only after the idempotency result is clear; do not
   create a second case for a repeated manifest.
3. Assign the responsible monitor/office and verify office scope.
4. If the intake is invalid, use the documented exception/rejection path; do not
   edit AEMS source data.

### 5.2 Create and approve an Action Plan

1. Responsible office opens the case and selects New Action Plan.
2. Enter root cause/corrective action, milestones, personnel, resources, target
   date, evidence plan, and attachments.
3. Save Draft, validate required fields, then Submit.
4. Monitor independently accepts or returns with reasons. On Return, revise and
   Resubmit; never overwrite an accepted plan.
5. Add Progress Updates at the required cadence, with evidence and blockers.

### 5.3 Validate implementation

1. Validator opens Validation and checks the accepted Action Plan, progress,
   target date history, and supporting documents.
2. Record criteria-by-criteria result and any limitation. Accept, partially
   accept, reject, or request clarification as the status permits.
3. Return clarification to the office and wait for a versioned response.
4. Do not mark a case Closed merely because a progress update says complete.
   Closure requires the CMS closure review/approval workflow.

### 5.4 Extend a target date

1. Before the deadline, open Target-Date Extensions and select Request.
2. Enter affected milestone, old/new target, reason, impact, mitigation, and
   evidence.
3. Submit. An independent authority approves or rejects.
4. Notify the office and monitor. The old date and every decision remain in the
   extension timeline. Automation may remind/candidate; it cannot approve.

### 5.5 Escalate, accept risk, or mark no longer applicable

- Escalation: inspect the candidate, verify overdue/status/office evidence,
  prepare a reviewable notice, obtain authority, then issue through the
  authorized action. A rule must not issue automatically.
- Accepted Risk: record residual risk, rationale, affected controls, evidence,
  independent assessment, and final decision. It is not implementation/closure.
- No Longer Applicable: record the factual change, evidence, effective date,
  independent assessment, and final decision. It is not implementation/closure.
- If the decision is rejected/returned, keep the case in the prior operational
  path and address the recorded reason.

### 5.6 Reopen a closed/disposed recommendation

1. Open the immutable closure/disposition history and select Reopen Request.
2. Enter authorized reason, new facts, risk/impact, requested scope, and
   supporting evidence.
3. An independent reviewer assesses eligibility. The original decision remains
   visible and unchanged.
4. The authority approves/rejects the new immutable Reopening Decision.
5. If approved, the case returns to its governed monitoring path with a new
   version; it does not become an untracked draft.

## 6. ARMIS resource and provider operations

1. Register/maintain the Core-linked resource profile and evidence.
2. Submit competency, availability, capacity, workload, assignment, and actual
   person-day records for independent review.
3. Resolve warnings for leave/training, workload, missing competency, stale
   provider, and independence before approval.
4. Open ARMIS Provider Monitoring and run the protected health check. A
   historical provider comparison is not required for current assignments.
5. Resolve missing, stale, or conflicting ARMIS records in the ARMIS owner
   workspace before approving the assignment.
6. ARMIS remains the sole operational resource provider. Do not restore an IAP
   fallback or attempt a provider-switch action.

## 7. AIS read-only operations

1. Open AIS Dashboard or Integration Health.
2. Read source health, freshness, scope, confidentiality, lineage, and exception
   indicators.
3. Open the authoritative module link to correct the source. AIS has no edit
   control.
4. Generate a report/export only from the protected endpoint. Verify snapshot
   timestamp, scope, source versions, checksum, and audit event.
5. Treat FAIL_CLOSED or stale indicators as a blocked analytic result, not as zero
   data.

## 8. Work queues, calendar, notifications, and exports

### 8.1 Work queues

1. Open Operational Work Queues and filter by engagement, office, status, actor,
   due date, and queue type (Tasks, Review Notes, Due Process, Escalation).
2. Open a queue item and inspect available actions. Assign only within scope.
3. Set/adjust due dates with a reason. Complete or return with a comment and
   attachment where required.
4. Reopen only through the allowed action. Every action updates the timeline and
   notification state.

### 8.2 Calendar and reminders

1. Open Audit Calendar and select engagement, milestone type, status, and date.
2. Use due/overdue filters to create or inspect a work-queue item.
3. A reminder is not an approval or closure. Resolve the underlying record in
   its owning workspace.

### 8.3 Protected exports

1. Apply scope, office, confidentiality, and date filters.
2. Select CSV or PDF export and wait for the authenticated response.
3. Store the returned checksum/file version with the review package.
4. If a CSV contains a value beginning with a spreadsheet formula character, the
   server escapes it. Do not disable that protection.
5. A download denial is a security result; do not use a public storage URL.

## 9. Verification checklist for reviewers

- Sign in as each seeded role and verify module/sidebar visibility.
- Try an unauthorized office/engagement and confirm 403/404-safe behavior.
- Try self-approval, duplicate import, duplicate CMS transfer, stale update,
  negative evidence, non-final finding in a Final Report, and public download.
- Confirm returned records can be revised and approved versions cannot be edited.
- Confirm Activity Log and Audit Trail contain actor, date, old/new state,
  source/version, and related engagement.
- Confirm mobile sidebar/workspace has no horizontal overflow and empty/loading/
  unauthorized/error states are safe.
- Run lint/build, focused Feature tests, migration rehearsal, and relevant
  Playwright suites from END_TO_END_TESTING_GUIDE.md.
