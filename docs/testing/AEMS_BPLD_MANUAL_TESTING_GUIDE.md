# AEMS manual testing guide

Special engagement → planning → entry conference → fieldwork → Issues and AFRs → exit conference → final report → CMS transfer

**BPLD business permit assessment, collection and reconciliation**

Prepared for AGIS local acceptance testing • 8 September 2026 • Version 1.0

Primary auditee: **Oxanna S. Custodio, BPLD Head**, Business Permits and Licensing Division. Maria L. Santos remains an additional BPLD representative; use Oxanna for this guide's recipient, custodian and responsible-person selections.

This is an executable manual test specification, not a report that the full journey has passed. Record actual results and defects as you run it. Workflow and field coverage were checked against the current repository's forms, request validation and transition services. Where a field is missing, a selector is empty or a gate rejects correct input, record a defect rather than altering the database to force a pass.

All business amounts, observations, policies, correspondence and attachments in this guide are **synthetic test fixtures**. Named accounts identify application test actors. The example finding is not an allegation about BPLD or any person. The fictional SOP is test criteria, not a statement of an actual ordinance or COA requirement.

## 01 · Test setup, dates and result recording

Use a fresh special engagement for each run. Do not reuse the earlier AEMS-2026-001 engagement: its versions, approvals and links may already be locked. Keep the generated engagement code and all generated record IDs in your run sheet.

The normal path below uses one audit program, three procedures, three process flows, three risk items, three working papers and three fieldwork records. It produces one finding with two recommendations and therefore two expected CMS cases. Each repeated form row has a defined value below.

| Setup field | Value / instruction |
|---|---|
| Test run code | BPLD-UAT-20260908-A; replace date and suffix on repeat runs |
| Application | Your running AGIS local site; open the normal login page |
| T (test date) | Actual local date when running; example 2026-09-08 |
| Authority date | T minus 1 day; example 2026-09-07 |
| Planned engagement period | T through T+14; example 2026-09-08 to 2026-09-22 |
| Expected report date | T+21; example 2026-09-29 |
| Record request deadline | T+2; example 2026-09-10; must not be before today |
| Evidence obtained / work performed | T; evidence obtained cannot be a future date |
| Audit population period | 2026-08-01 through 2026-08-31; historical transaction period, distinct from engagement dates |
| Recommendation target 1 / 2 | T+30 / T+45; example 2026-10-08 / 2026-10-23 |
| Conference scheduling | Schedule T at the next convenient time. Record actual held time only after the simulated meeting; use chronological real test times |
| Business data | 60 fictional permit transactions, 12 sampled, 3 exceptions; use accompanying CSV and SOP fixtures |
| Browser sessions | Separate profiles/private windows for preparer, reviewer/approver and Oxanna, or sign out between actors |
| Status per test | NOT RUN, PASS, FAIL or BLOCKED |
| Evidence per test | Screenshot filename, generated record code, current version, acting account, timestamp, actual result and defect ID |

A same-day demonstration may simulate all meetings and procedures on T, with future planned end dates. Do not claim that future events have occurred. For a calendar-realistic run, perform the steps over the planned period. Avoid future evidence dates simply to match planned dates.

For every save: wait for success, refresh, reopen the record and compare each input below. A toast alone is not proof of persistence. For every transition: verify status, actor, timestamp and history. System-generated codes, IDs, checksums, version numbers and lock versions are captured from the application; do not type example database IDs.

## 02 · Login accounts, permissions and ownership

On the login page click the demo account card to populate the employee ID and configured password, then click Sign in. The card is a credential selector. Local demo passwords default to `lala` unless overridden by environment configuration. Use the card's configured value rather than assuming a deployed password.

| Actor / account | Use in this run |
|---|---|
| Oxanna S. Custodio — BPLD-HEAD | Main auditee; BPLD office. AEO acknowledgement, entry notes, requested evidence upload and responsible person for recommendations |
| Maria L. Santos — AUDITEE-001 | Secondary BPLD representative; use in recipient-scope checks, not as the main custodian |
| Marissa Barcellona — CIAS-AUD-004 | Engagement Team Leader; prepare AEO, AEP, program and report |
| Charry Bagongon — CIAS-AUD-001 | Auditor; execute all three procedures and prepare working papers / fieldwork records |
| Kristine Yare — CIAS-AUD-002 | Reviewer; independent reviews where permissions and engagement assignment allow |
| Cherrybelle A. Lao — CIAS-HEAD-001 | Supervisor and approval/finalization actor where granted permissions allow |
| Michele Dampog — CIAS-AUD-003 | Additional independent issuer/approver if an action requires a different person; assign and grant relevant permission before use |
| Kim V. Lao — AGIS-ADMIN-001 | Test account/permission configuration; administrative role alone does not confer audit approval |

Precondition: use Administration → roles/permissions to inspect the actual allowed actions. Titles are examples of responsibility, not authorization rules. A reviewer needs the relevant review permission and engagement scope; an approver needs the relevant approve/finalize permission. Team roles must be assigned within this engagement.

| Capability | Permission family / expected restriction |
|---|---|
| Registry, scope and lifecycle | `aems.engagement.*`, foundation scope permissions; use the named permission shown by the relevant screen |
| Team and safeguards | `aems.team.*`; current approved ARMIS capacity and accepted independence declarations |
| AEO / AEP / Program | `aems.aeo.*`, `aems.aep.*`, `aems.program.*` |
| Planning package | `aems.planning-package.*` |
| Fieldwork | `aems.fieldwork.create`, `aems.fieldwork.review`, `aems.fieldwork.finalize`, plus view permission |
| Evidence and Working Papers | `aems.evidence.*`, `aems.evidence-request.*`, `aems.working-paper.*` |
| Auditee evidence response | `aems.evidence-request.view`, `aems.evidence-request.respond`, `access.auditee_scope`; office/user recipient checks still apply |
| AEO acknowledgement | `aems.aeo.acknowledge`; exact recipient office/user must match |
| Own-submission exception | Explicit self-review/self-approval permission, where supported. Never infer it from CIAS Head title. Use independent actors for the main run |
| Findings, dialogue, reports | Relevant `aems.issue.*`, finding, management-response, rejoinder and report permissions exposed in the permission registry |
| Completion reconciliation | `aems.completion-transfer.view`, `.reconcile`, `.approve`; independent approval may still be required |

**LOGIN-01:** click Oxanna's card, sign in, confirm Oxanna's name and BPLD office. Open CMS. Expected: authorized CMS pages load and other offices' records remain hidden. Log out and confirm protected pages require login. Do not modify Maria's identity to imitate Oxanna.

## 03 · Lifecycle roadmap and record dependencies

| Order | Required result before moving forward |
|---|---|
| 1. DRAFT | Special authority and exactly one auditee office with area/focus coverage saved |
| 2. AUTHORIZATION_PREPARATION | Required team, safeguards and current AEO prepared/reviewed/approved |
| 3. AUTHORIZED | Issue Authorization gate passes; issue and transmit AEO, obtain BPLD acknowledgement |
| 4. ENGAGEMENT_PLANNING | Create planning draft first; approve AEP and program, complete planning traceability and approve package |
| 5. ENTRY_CONFERENCE | Start Entry Conference; schedule, hold, record notes, complete/acknowledge |
| 6. FIELDWORK | Start Fieldwork; separately activate approved audit program; receive evidence, assess, approve papers, finalize records, complete procedure progress |
| 7. FINDINGS_COMMUNICATION | End Fieldwork; resolve issues, communicate AFR, receive/dispose response and finalize findings |
| 8. EXIT_CONFERENCE | Start Exit Conference only after issues are dismissed/converted and current findings finalized; schedule then complete |
| 9. REPORTING | Completed exit conference and reporting gates; draft review then final approval/issuance |
| 10. ISSUED / Completion & Transfer | Transfer issued recommendations once, verify CMS cases, reconcile transfer and resource effort |

Always execute lifecycle actions from the engagement's Overview/Lifecycle workspace when child records are ready. Completing a child document does not guarantee the aggregate automatically advances. Reload the allowed actions and read each blocker.

Planning's initial “Blocked” label concerns fieldwork readiness. It is expected before planning approval; a planning draft does not need an already-created Audit Program. Execution can be in FIELDWORK while the approved program has not yet been activated; that explains empty procedure lists.

## 04 · Create the special engagement

**ENG-01** — Actor: Marissa or another permitted engagement creator. Navigation: AEMS → Audit Engagement Workspace → Create special engagement. Use these registry fields; complete the dedicated Scope screen next.

| Field | Input |
|---|---|
| Engagement code | Leave blank for automatic code. Record the returned value |
| Title | BPLD-UAT-20260908-A — Special Audit of Business Permit Assessment, Collection and Reconciliation |
| Source type | Special (system sets this for special creation) |
| Special authority reference | DEMO-MO-2026-091 |
| Special authority type | Mayor Directive / MAYOR_DIRECTIVE if available |
| Special authority class | SPECIAL |
| Authority date | T-1 |
| Approving authority / Approved by | City Mayor, selected from available users; documentary source authority is separate from application approver |
| Audit type | Compliance Audit, or the configured compliance-audit label |
| Engagement approach | Risk-based, using the configured matching option |
| Background | A synthetic August 2026 register contains 60 permit transactions. Management requests assurance that assessment, official-receipt recording and daily reconciliation controls operate consistently. |
| Objectives | Determine whether assessment computations are accurate, recorded receipts agree to assessments, and daily reconciliation differences are identified and resolved. |
| Scope | BPLD records for 1–31 August 2026 covering assessment, receipt references retained by BPLD, and daily reconciliation of permit transactions. |
| Exclusions | Treasury-wide cash custody, bank confirmation, procurement, payroll and periods outside August 2026 are excluded. Treasury source copies are used only to corroborate BPLD records. |
| Planned start / end | T / T+14 |
| Expected report date | T+21 |
| Planned person-days | 26 |
| Office / audit areas / focuses if shown here | BPLD / REVENUE / REV-ASS, REV-COLL, REV-REC; dedicated Scope page remains the authoritative coverage step |

Save and reload. Expected: DRAFT, special-source lineage preserved, correct dates and 26 days. Objectives/scope/exclusions must display your text. If any field is unavailable, capture the missing control and the resulting “Not specified” as a defect; do not silently treat empty content as tested.

Upload `BPLD-UAT-criteria-and-authority.txt` into Document Management if a source-document control is available. Record its actual Core Document Version. It is conspicuously marked synthetic and must not be represented as a genuine mayoral directive.

## 05 · Define BPLD scope and coverage

**SCOPE-01** — AEMS → Audit Scope; select the new engagement. Save while scope is editable, before authorization.

| Field | Input |
|---|---|
| Engagement Office | BPLD — Business Permits and Licensing Division; exactly one |
| Scope boundaries | Assessment-to-reconciliation records for 60 August 2026 permit transactions; include BPLD's retained receipt and reconciliation references. |
| Known limitations | Synthetic records only; no real taxpayer records or independent bank confirmations. Findings are limited to the 12 sampled transactions. |
| Source variance decision | NOT_APPLICABLE — special engagement has no imported IAP baseline |
| Variance authority | DEMO-MO-2026-091 |
| Variance explanation | No IAP source is imported. Coverage follows the special directive and is confined to BPLD. |
| In-scope Audit Areas | REVENUE — Revenue, Tax, Permit and Fee Administration |
| Audit Focuses | REV-ASS — Assessment and Billing; REV-COLL — Collection and Official Receipts; REV-REC — Revenue Reconciliation |
| Area objective | Evaluate accuracy, completeness and timely reconciliation of BPLD permit assessment and receipt records. |
| Area boundary | BPLD assessment worksheets, receipt-reference register and daily reconciliation files for August 2026. |
| Area limitations | No assurance over Treasury operations outside the BPLD record trail. |
| Area source variance | Not applicable — coverage established directly by DEMO-MO-2026-091. |

Expected: one office, one area, three focuses persist after refresh. Other areas' focuses cannot be selected. Switch to Overview/Lifecycle → Prepare Authorization; expected AUTHORIZATION_PREPARATION. Team workspace should now unlock.

## 06 · Assign team and approve safeguards

**TEAM-01** — AEMS → Audit Team. For every assignment set assigned from T, until T+14. Select the user and role from the application's lists. The named team is independent of auditee Oxanna.

| User / assignment role | Planned days / assignment notes |
|---|---|
| Cherrybelle A. Lao / SUPERVISOR | 4 / Supervise scope and approve controlled outputs within granted authority. |
| Marissa Barcellona / TEAM_LEADER | 6 / Coordinate survey, planning, conferences and report preparation. |
| Charry Bagongon / AUDITOR | 12 / Execute PROC-01, PROC-02 and PROC-03; prepare work and evidence trail. |
| Kristine Yare / REVIEWER | 4 / Independently review planning and execution support. |

For optional assignment Reason enter “Initial team established for BPLD-UAT-20260908-A.” Amendment authority and consequence assessment remain blank for initial assignments. In the amendment branch use authority “DEMO-TEAM-AMEND-01”, reason “Auditor unavailable for the remaining test period” and consequence “Reallocate remaining work; reassess safeguards and capacity before proceeding.”

ARMIS is the resource source. If an assignee lacks approved availability/capacity/competencies for T..T+14, record BLOCKED, resolve the actual ARMIS setup, then reassess. Merely entering 26 days into AEMS does not satisfy provider checks. Do not change provider authority or disable gates to make this test pass.

**TEAM-02** — Each team member submits all required declaration types. Repeat the following for OBJECTIVITY, CONFLICT_OF_INTEREST and INDEPENDENCE.

| Declaration field | Input |
|---|---|
| Type | OBJECTIVITY, then CONFLICT_OF_INTEREST, then INDEPENDENCE |
| Outcome | CLEAR, only for this fictional test scenario |
| Statement | I have no personal, financial or operational involvement in the BPLD records used in this synthetic test and can perform my assigned work objectively. |
| Mitigation plan | Blank for CLEAR; branch DISCLOSED uses “Reassign the affected procedure and document independent review.” |
| Evidence Document Version | Optional: actual uploaded declaration fixture version; otherwise blank |
| Revision reason | Blank initially; if returned: “Clarified the period and scope covered by the declaration.” |
| Reviewer decision / notes | ACCEPT / Reviewed the declaration for the engagement period; no disclosed conflict in the test scenario. |
| Assessment approval comment | Team declarations and current ARMIS suitability checks reviewed; required assignments are ready. |

Generate/assess safeguards, then use an eligible independent approver. Expected: required declarations accepted and current assessment approved. Missing/stale provider information or any unresolved conflict must block authorization/fieldwork.

## 07 · AEO approval, issuance and Oxanna acknowledgement

**AEO-01** — AEMS → Engagement Orders → Create. Marissa prepares; Kristine reviews; Cherrybelle approves subject to permissions and separation rules.

| Field | Input |
|---|---|
| Authority | DEMO-MO-2026-091, dated T-1, authorizes a synthetic special audit of BPLD permit controls. |
| Objectives | Verify accurate assessment, complete receipt references and documented reconciliation for August 2026 permit records. |
| Scope | BPLD assessment-to-reconciliation records; 60 transactions, 12 risk-selected samples; exclusions as saved in engagement definition. |
| Effectivity date | T |
| Planned start / end | T / T+14 |
| Change reason | Blank initially; revision: “Clarified audit period and BPLD-only coverage.” |
| Submit comment | AEO ready for independent review against the special authority and scope. |
| Review comment | Authority, one-office coverage, dates and team responsibilities agree to the engagement. |
| Approval comment | Approve this reviewed AEO version for the BPLD test engagement. |
| Signature method if prompted | IN_APP_ATTESTATION |
| Signature reference | DEMO-AEO-ATTEST-01 |

Save → Submit → Record review → Approve the AEO. From Lifecycle run Issue Authorization to move from AUTHORIZATION_PREPARATION to AUTHORIZED; the current active AEO must be APPROVED or ISSUED. Then issue and transmit the AEO using an appropriately permitted actor. Start Planning becomes available once the issued AEO's current version has been acknowledged by the engagement office or a recipient belonging to that office. Verify the aggregate lifecycle and child AEO status separately; one does not automatically advance the other. Authorizing your own engagement still requires the explicit own-submission review permission.

**AEO-02** — Transmit the issued version.

| Distribution field | Input |
|---|---|
| Recipient type | USER |
| Recipient user | Oxanna S. Custodio — BPLD-HEAD |
| Recipient office if enabled | BPLD |
| Recipient name if enabled | Oxanna S. Custodio |
| Transmittal method | SECURE_PORTAL |
| Reference | DEMO-AEO-TX-01 |
| Acknowledgement note | Received the issued AEO for BPLD. Oxanna S. Custodio will coordinate records and conference attendance. |

Sign in as Oxanna → CMS → AEO Acknowledgements → select the exact transmittal → acknowledge. Refresh as auditor; acknowledgement actor/date must be present. Download the issued AEO PDF and check office, scope, code and version. Lifecycle → Start Planning must now pass; a missing auditee acknowledgement must block it.

## 08 · Planning workspace draft and preliminary survey

**PLAN-01** — AEMS → Planning Workspace → select engagement → Create draft. Create it before Audit Program if needed. Do not interpret fieldwork-blocked status as a prohibition on draft creation.

| Preliminary Survey field | Input |
|---|---|
| Purpose | Understand BPLD assessment, receipt-reference and reconciliation controls and identify procedures for August 2026 testing. |
| Background | Synthetic register contains 60 permit transactions; manual reviewer sign-off supports daily reconciliation. |
| Information sources | BPLD-UAT-permit-register.csv; BPLD-UAT-criteria-and-authority.txt; walkthrough and interview notes. |
| Interviews | Oxanna S. Custodio explained record ownership, assessment review and daily reconciliation responsibilities on T. |
| Walkthroughs | Traced DEMO-PERMIT-005 from application to assessment, receipt reference and reconciliation; observed missing secondary sign-off. |
| Observations | Daily records contain three sampled items without reviewer sign-off; other sample attributes are populated. |
| Planning implications | Test assessment calculations, receipt matching and independent reconciliation review separately; preserve sample-level exceptions. |
| Core Document Version ID | Optional actual uploaded survey/support version; otherwise leave blank, since narrative is supplied |

| Planning attribute field | Input |
|---|---|
| Methodology | Risk-based walkthrough, inspection, recalculation and reconciliation using 12 selected transactions. |
| Criteria framework | Synthetic SOP BPLD-UAT-SOP-01, sections 1–3, supplied with this guide. |
| Evidence requirements | Controlled register export, assessment and receipt references, reviewer sign-off and documented interview corroboration. |
| Milestones | Planning approval; entry conference; 3 procedures finalized; AFR dialogue; exit conference; final report; CMS transfer. |
| Schedule | Planning and entry T; fieldwork T..T+14; final report planned by T+21. |

Save a new version and reopen. Record version number. Initial risks/program links may still be incomplete; finish sections 09–13 before approval.

## 09 · Objectives and structured process flows

**PLAN-02** — Add each objective. Source type: SPECIAL where offered, otherwise keep the form's supported default and enter the authority in Source reference. Do not type an unsupported option.

| Objective code | Statement / source reference |
|---|---|
| OBJ-01 | Determine whether permit assessments are correctly computed. / DEMO-MO-2026-091; SOP section 1 |
| OBJ-02 | Determine whether BPLD receipt references completely match permit assessments. / DEMO-MO-2026-091; SOP section 2 |
| OBJ-03 | Determine whether daily reconciliation exceptions receive documented independent review. / DEMO-MO-2026-091; SOP section 3 |

**PLAN-03** — Add three process flows using this template. Each list field receives one entry per line. Save flows before selecting them in procedures/risk items.

| Flow field | FLOW-01 value |
|---|---|
| Flow code / Title | FLOW-01 / Permit assessment and billing |
| Process owner office | BPLD |
| Audit area / focus | REVENUE / REV-ASS |
| Core Document Version ID | Blank; use narrative and source reference |
| Source reference | BPLD-UAT-SOP-01 section 1 |
| Scope statement | August 2026 BPLD assessment records for 60 synthetic permit transactions. |
| Description / narrative | Intake validates application; assessor computes the test fee; reviewer checks arithmetic; billing reference is recorded. |
| Steps | Receive application; validate classification; calculate fee; independent arithmetic check; release assessment. |
| Inputs | Application reference; test fee schedule; declared classification. |
| Outputs | Assessment amount; billing reference; reviewer approval. |
| Records / systems | Synthetic permit register; assessment worksheet. |
| Controls | CTRL-01: independent recomputation before release. |
| Decision points | Is the application complete? Does the computed fee agree to the test schedule? |
| Risk points | Incorrect classification; calculation error; missing reviewer check. |
| Limitations | Synthetic workflow only; excludes unrelated charges. |

For FLOW-02 and FLOW-03 replace every process-specific field below; retain BPLD owner, REVENUE area, August population and blank Core version.

| Field | FLOW-02 / FLOW-03 |
|---|---|
| Title / focus | Receipt-reference matching / REV-COLL; Daily reconciliation review / REV-REC |
| Source reference | SOP section 2 / SOP section 3 |
| Scope statement | BPLD retained receipt references for August permits / BPLD daily reconciliation evidence for August permits |
| Description | Match receipt reference and amount to assessment before release / Reconcile assessment and receipt records, investigate differences and obtain secondary sign-off |
| Steps | Receive receipt reference; match permit; compare amount; record receipt / Extract daily list; match totals; investigate differences; reviewer signs; archive |
| Inputs | Assessment amount; receipt copy / Assessment register; receipt-reference register |
| Outputs | Matched receipt entry; exception list / Signed reconciliation; resolved exception record |
| Records / systems | Synthetic permit register and receipt copies / Synthetic reconciliation review register |
| Controls | CTRL-02: one-to-one assessment and receipt matching / CTRL-03: independent reconciliation sign-off |
| Decision points | Does receipt match amount and permit? / Are differences explained and independently reviewed? |
| Risk points | Missing or duplicate reference; mismatch / Unreviewed exceptions; delayed follow-up |
| Limitations | No independent Treasury-wide cash opinion / No external bank confirmation |

Save/reload. All three scoped focuses must be selectable. Record a defect if selecting REVENUE produces “No audit focuses” despite saved scope.

## 10 · Audit Engagement Plan

**AEP-01** — AEMS → Engagement Plan. Create and approve the AEP before final planning-package approval.

| Field | Input |
|---|---|
| Objectives | OBJ-01 assessment accuracy; OBJ-02 receipt completeness; OBJ-03 documented independent reconciliation review. |
| Scope | BPLD August 2026 assessment, receipt-reference and reconciliation records; 60 transactions and 12 selected samples. |
| Exclusions | Treasury-wide cash custody, external bank confirmation, procurement, payroll and other periods. |
| Methodology | Walkthrough and interview; recalculate 12 assessments; match receipts; inspect independent reconciliation sign-off; corroborate exceptions. |
| Audit criteria | BPLD-UAT-SOP-01 sections 1, 2 and 3; synthetic acceptance-test criteria. |
| Materiality | Assess control significance qualitatively. Report missing review separately from monetary error; do not project sample exceptions to the whole population. |
| Sampling approach | Judgmental sample of 12 IDs: 005, 010, 015, 020, 025, 030, 035, 040, 045, 050, 055 and 060. No statistical projection. |
| Planned start / end / expected report | T / T+14 / T+21 |
| Planned person-days | 26 |
| Staffing | Supervisor 4, Team Leader 6, Auditor 12, Reviewer 4 person-days. |
| Skills | Permit assessment review, spreadsheet recalculation, record matching and internal-control testing. |
| Tools | AGIS, spreadsheet application and private document storage. |
| Logistics | BPLD conference room; read-only synthetic exports; no real taxpayer data. |
| Contact person | Oxanna S. Custodio |
| Contact details | bpld.test@example.invalid — synthetic contact; do not send external email |
| Kickoff details | Entry conference on T after planning readiness; confirm BPLD records coordinator and sample scope. |
| Records deadline | T+2 |
| Coordination notes | Submit requested files using CMS Evidence Requests; maintain original filenames and request links. |
| Confidentiality | Internal |
| Change reason | Blank initially; return branch: “Expanded sampling description to identify all 12 items.” |

Save → Submit → independent Review → Approve. Use descriptive comments: “Sampling, criteria, scope and resources reconciled to approved authority.” Reopen the approved version and verify all fields. A formal revision must preserve the old version and require reapproval where applicable.

## 11 · Audit Program and three procedure details

**AP-01** — AEMS → Audit Program → Create. Keep one current program for this run.

| Program field | Input |
|---|---|
| Title | BPLD August 2026 permit controls — UAT program |
| Objective | Execute OBJ-01 through OBJ-03 and preserve evidence-backed conclusions for each. |
| AEP source | Select the current approved AEP if editable; otherwise verify automatic link |
| Audit area / type | REVENUE / Compliance Audit |
| Audit period start / end | 2026-08-01 / 2026-08-31 |
| Audit criteria | BPLD-UAT-SOP-01 sections 1–3. |
| Risk statements, one per line | R-01 inaccurate assessment; R-02 unmatched receipt; R-03 unreviewed reconciliation. |
| Sampling approach | 12 judgmental samples from 60 transactions; IDs at increments of five. |
| Planned working-paper requirements | WP-01 assessment recalculation; WP-02 receipt matching; WP-03 reconciliation review. |

**AP-02** — AEMS → Audit Procedure Details → select engagement/program → Add procedure. Repeat the full template for three rows.

| Procedure field | PROC-01 input |
|---|---|
| Procedure code / sequence | PROC-01 / 1 |
| Objective | Verify assessment accuracy for the 12 sampled permits. |
| Procedure description | Recalculate the synthetic assessment amount for each selected ID and compare it with the recorded assessment. Investigate differences. |
| Expected evidence | Synthetic permit register, stated test-fee formula and recalculation results. |
| Assigned auditor | Charry Bagongon |
| Target date | T+7 |
| Working-paper reference | WP-01 |
| Audit area / focus | REVENUE / REV-ASS |
| Process name | Permit assessment and billing |
| Process flow | Select saved FLOW-01; if the screen still asks for an ID, use the actual saved flow ID, never a fabricated number |
| Audit method | Inspection and recalculation |
| Planned person-days | 4 |
| Procedure criteria | SOP section 1: test fee must equal PHP 1,000 plus PHP 100 multiplied by transaction number. |
| Sampling method | Judgmental, 12 items at increments of five |
| Population | 60 synthetic August 2026 permit transactions |
| Sample size | 12 |
| Planned Working Paper reference | WP-01 |
| Required evidence | Register rows and recalculation worksheet for the 12 IDs. |
| Risk statement IDs if displayed | Select R-01 / use the risk code offered by the form |

| Replacement field | PROC-02 / PROC-03 |
|---|---|
| Sequence / working-paper reference | 2 / WP-02; 3 / WP-03 |
| Objective | Verify receipt completeness and amount agreement / Verify independent reconciliation review |
| Description | Match each sample's receipt reference and amount to the permit assessment / Inspect reconciliation and reviewer sign-off for all 12 selected IDs; list missing sign-offs |
| Expected / required evidence | Receipt-reference and amount columns plus assessment entries / Reconciliation reference, reviewer field and confirmation of three exceptions |
| Focus / process flow | REV-COLL / FLOW-02; REV-REC / FLOW-03 |
| Process name | Receipt-reference matching / Daily reconciliation review |
| Audit method | Inspection and matching / Inspection and inquiry |
| Criteria | SOP section 2: unique receipt reference and matching amount / SOP section 3: independent reviewer sign-off on every reconciliation |
| Target date / days | T+10 / 4; T+14 / 4 |
| Sampling method / population / size | Same 12-ID judgmental sample / 60 transactions / 12, for both |
| Risk statement | R-02 / R-03 |

Keep the assigned auditor Charry for all three. Submit, review and approve the program once procedures are complete. Expected: APPROVED, three saved procedures with all classifications and sampling details. Activation is performed after Start Fieldwork (section 15), not assumed from approval.

## 12 · Risk Matrix register and risk items

**RISK-01** — Planning Workspace → Risk Matrix. Use one register matrix covering REVENUE, with three risk items differentiated by focus.

| Matrix register field | Input |
|---|---|
| Matrix code / title | RM-01 / BPLD permit controls risk matrix |
| Audit area | REVENUE |
| Audit focus | Blank for the area-wide matrix; individual items carry specific focuses |
| Methodology | Synthetic 5×5 likelihood-impact model. Score = likelihood × impact. Low 1–4, Moderate 5–9, High 10–16, Critical 17–25. Assess controls before setting residual risk. |
| Risk appetite | Low tolerance for inaccurate billing, unmatched receipts and unreviewed reconciliations. |
| Overall conclusion | Prioritize reconciliation review; test assessment and receipt controls to determine whether weaknesses are isolated or recurring. |

**RISK-02** — Add each risk item. Use REVENUE area and BPLD responsible office for all. Numbers follow the synthetic rubric, not an assertion about an official approved risk model.

| Field | R-01 |
|---|---|
| Risk code / category / status | R-01 / Revenue accuracy / OPEN |
| Risk statement | Incorrect assessment calculations may result in inaccurate permit billing. |
| Audit focus / flow / process | REV-ASS / FLOW-01 / Permit assessment and billing |
| Risk area | Assessment computation |
| Responsible office | BPLD |
| Risk response | Reduce |
| Inherent likelihood / impact / score | 3 / 4 / 12 |
| Control description | CTRL-01 independent recomputation before assessment release. |
| Control effectiveness | Partially effective — to be validated by sample testing. |
| Residual likelihood / impact / score / rating | 2 / 3 / 6 / Moderate |
| Planned audit approach | Recalculate all 12 selected assessments and document discrepancies. |
| Criteria | BPLD-UAT-SOP-01 section 1 |
| Response rationale | Independent checking should reduce arithmetic error; obtain operating evidence. |
| Linked planning objectives | OBJ-01 from current package version |
| Linked approved-program procedures | PROC-01 |
| Working-paper references | WP-01; link basis if requested: Recalculation supports assessment-risk conclusion. |

| Replacement field | R-02 / R-03 |
|---|---|
| Category | Receipt completeness / Reconciliation oversight |
| Risk statement | Missing or mismatched receipt references may leave billing unsettled in BPLD records / Missing independent review may allow reconciliation errors to remain undetected |
| Focus / flow / process | REV-COLL / FLOW-02 / Receipt-reference matching; REV-REC / FLOW-03 / Daily reconciliation review |
| Risk area | Assessment-to-receipt matching / Independent reconciliation review |
| Inherent likelihood, impact, score | 3, 4, 12 / 4, 4, 16 |
| Control | CTRL-02 one-to-one reference/amount check / CTRL-03 secondary sign-off and exception follow-up |
| Control effectiveness | Partially effective, pending test / Weak evidence of independent review in survey |
| Residual likelihood, impact, score, rating | 2, 3, 6, Moderate / 3, 4, 12, High |
| Response / rationale | Reduce; test completeness of matching / Reduce; prioritize evidence of secondary review |
| Approach | Match references and amounts for 12 items / Inspect reconciliation review for the 12 items |
| Criteria | SOP section 2 / SOP section 3 |
| Objective / procedure / paper links | OBJ-02 / PROC-02 / WP-02; OBJ-03 / PROC-03 / WP-03 |
| Working-paper relationship basis | Receipt match supports completeness conclusion / Sign-off test supports oversight finding |

**RISK-03 persistence regression:** Save new version, refresh and switch tabs away/back. Confirm methodology, appetite and overall conclusion remain verbatim, along with all three items and links. Add an optional second matrix RM-02 titled “Reconciliation focus supplement”, REVENUE / REV-REC, using the same rubric, appetite “Low tolerance for missing secondary review” and conclusion “Track reviewer sign-off exceptions.” Save/reload; both matrices must retain independent text. An optional matrix can remain without items if the server permits; do not create a duplicate R-03 merely to populate it.

## 13 · KPIs, planned papers and planning approval

**PLAN-04** — Add the following KPIs. Where offered, responsible office is CIAS (City Internal Audit Office), status is the form's active/default value and source reference is the program code.

| Code / name | Target / measurement method |
|---|---|
| KPI-01 / Sample testing completion | 12 of 12 per procedure / Count documented tested sample IDs against 12 in each approved paper |
| KPI-02 / Procedure completion | 3 of 3 / Count finalized fieldwork records and completed program procedures |
| KPI-03 / Findings disposition | 100% / Finalized current findings divided by current communicated findings |

Add these planned Working Papers. These are requirements, not actual execution papers; actual papers are created in section 17.

| Reference / procedure | Title / objective / required evidence |
|---|---|
| WP-01 / PROC-01 | Assessment recalculation / Verify OBJ-01 / Register and 12-item recalculation worksheet |
| WP-02 / PROC-02 | Receipt matching / Verify OBJ-02 / Register and 12 receipt references with amounts |
| WP-03 / PROC-03 | Reconciliation sign-off testing / Verify OBJ-03 / 12 reconciliation records and missing-sign-off exception list |

Risk item selector, if shown: use matching R-01/02/03. Required flag: checked for all. Sequence: 1/2/3. Save with change reason “Completed program, risk, KPI and planned-paper traceability.”

Readiness & Review must show complete survey, objectives, structured flows, area coverage, approved AEP/program, risk-to-objective/procedure/paper links, complete program/procedure definitions, KPIs and planned evidence. Resolve each failed check by editing the actual source before submitting.

Submit as preparer; independent Review; Approve as authorized approver. Expected approved version equals current version and fieldwork readiness is true. Compare Versions before/after and check text retention. If editing a process flow creates new IDs, refresh and reselect the current links; do not reuse IDs copied from another engagement.

Lifecycle → Start Entry Conference. Expected ENTRY_CONFERENCE. Any configured progress-assessment control must later receive its required completion status and evidence reference before reporting. If your UI lacks a control requested by a server gate, record that specific gap as BLOCKED.

## 14 · Entry Conference: schedule, hold, notes and acknowledgement

**ENTRY-01** — Conference Management → Entry. First schedule without claiming the conference has already happened.

| Scheduling / participant field | Input |
|---|---|
| Scheduled start / end | T at agreed time / 60 minutes later |
| Venue | BPLD meeting room — synthetic test session |
| Meeting link | Blank for in-person session; optional URL branch uses https://example.invalid/bpld-uat |
| Online meeting details | Blank in-person; URL branch: Synthetic link only, no external meeting is launched. |
| Agenda | Authority and scope; objectives and sample; records access; confidentiality; timetable and contacts. |
| Participant 1 type / user / office / role | AUDIT_TEAM / Marissa / CIAS / Team Leader |
| Participant 2 | AUDIT_TEAM / Charry / CIAS / Auditor |
| Participant 3 | AUDITEE / Oxanna / BPLD / BPLD records coordinator |
| External name / email | Blank for registered users; optional EXTERNAL branch uses Alex Demo / alex@example.invalid |

Save schedule. Expected: conference exists with scheduled state, can be reopened to add actual details. Auditee dropdown must show eligible auditee users, audit-team dropdown eligible auditors.

**ENTRY-02** — Complete all briefing-paper fields before finalizing notes.

| Briefing field | Input |
|---|---|
| Audit selection background | Special request to test synthetic August permit controls after survey identified incomplete sign-off. |
| Audit authority | DEMO-MO-2026-091 dated T-1; issued AEO code recorded in run sheet. |
| Preliminary objectives | OBJ-01 assessment accuracy; OBJ-02 receipt matching; OBJ-03 independent reconciliation review. |
| Scope and exclusions | BPLD August records; excludes Treasury-wide custody and unrelated processes. |
| Methodology | 12-item judgmental sample, walkthrough, recalculation, matching and sign-off inspection. |
| Audit criteria | BPLD-UAT-SOP-01 sections 1–3. |
| Planned timing | T..T+14 fieldwork; report planned by T+21. |
| Team members and roles | Cherrybelle supervisor; Marissa team leader; Charry auditor; Kristine reviewer. |
| Previous audit matters | No prior findings imported into this synthetic run. |
| Engagement milestones | Entry; evidence receipt; testing; AFR response; exit; report; CMS handoff. |
| Expected deliverables | Approved papers, finalized fieldwork, supported AFR, final report and two CMS recommendations. |
| Initial information requirements | Synthetic permit register, test SOP and reviewer-sign-off records. |
| Auditee views | Oxanna confirms BPLD will provide the synthetic register and explain reconciliation responsibilities. |
| Auditee expectations | Clear requests, protected file access and opportunity to comment on supported findings. |
| Conference notes | Team explained objectives and sample; Oxanna accepted records coordination; no change to approved scope was requested. |
| Material matters disposition | Records-access matter resolved through CMS upload; no unresolved material restriction. |

| Matter / commitment field | Input |
|---|---|
| Matter type / description | Records access / Supply the August synthetic permit and reconciliation register. |
| Material matter | Checked |
| Disposition status / disposition | RESOLVED / Oxanna demonstrated that the file can be uploaded through CMS. |
| Responsible person / office / due date | Oxanna / BPLD / T+2 |
| Agreement / commitment | BPLD will upload the requested synthetic records and retain the request reference. |
| Commitment person / office / due / status | Oxanna / BPLD / T+2 / COMPLETED after upload, otherwise IN_PROGRESS |
| CIAS commitment (optional second row) | Charry / CIAS / T+3 / OPEN; “Validate receipt completeness and notify BPLD of gaps.” |
| Held at / actual attendance | Actual test meeting time; PRESENT for the three participants |
| Attendance notes / attended at | Attended the complete synthetic briefing / actual meeting time |
| Attachment / caption | Optional criteria file or test minutes / Synthetic entry briefing and attendance record |
| Notes acknowledgement | Reviewed the official entry notes; they accurately record the synthetic briefing and BPLD commitments. |

Use the available hold/complete actions in order, satisfying any participant, notes, material-matter or attachment gates. Sign in as Oxanna → CMS → Entry Conference Acknowledgements. Open the engagement, read notes and acknowledge if offered. Refresh after completion: notes must remain accessible for reference. Empty selector despite correct office/permissions is a FAIL, not expected archival behavior.

## 15 · Start Fieldwork and activate the program

**EXEC-01** — Overview/Lifecycle → Start Fieldwork. Expected FIELDWORK. If blocked, verify completed/waived Entry Conference, approved current package with matching approved/current version, approved AEP/program and current safeguards.

Then open Audit Program → select approved program → Start/Activate. Expected program ACTIVE. Open Execution and Working Papers & Audit Evidence; all three procedures should now be available and new-paper/upload actions enabled for permitted users.

Record both statuses in your run sheet. A planned Working Paper requirement is not an actual Working Paper, and an approved program is not yet an active program. If procedures remain absent after activation, refresh selectors and capture the status/API error as a defect.

## 16 · Evidence request and Oxanna's CMS upload

**EVD-01** — Evidence Management → Evidence Requests → create as auditor.

| Request field | Input |
|---|---|
| Title | August 2026 BPLD synthetic permit and reconciliation register |
| Purpose | Obtain source records for PROC-01 through PROC-03 and reconcile assessment, receipt and reviewer-sign-off attributes. |
| Requested from office | BPLD |
| Requested from user | Oxanna S. Custodio |
| Due date | T+2 |
| Requested items, one per line | BPLD-UAT-permit-register.csv; BPLD-UAT-criteria-and-authority.txt; confirmation of missing sign-offs for sample IDs 005, 025 and 045. |
| Change reason | Blank initially; returned update: “Clarified required sign-off columns and sample IDs.” |
| Submit / send comment | Reviewed request scope; send to the BPLD coordinator through the portal. |

Create → Submit → use Send when available. Expected SENT. SUBMITTED alone is not sufficient for the auditee delivery workflow.

**EVD-02** — Oxanna → CMS → Evidence Requests → select request; acknowledge when available, then upload.

| Auditee upload field | Input |
|---|---|
| Title | BPLD August 2026 synthetic permit register |
| Source description | Synthetic register supplied by Oxanna S. Custodio for BPLD-UAT-20260908-A; 60 fictional transactions. |
| Date obtained | T |
| Response note | Providing the requested register. Sample IDs 005, 025 and 045 have no recorded secondary reconciliation sign-off in this fixture. |
| File | BPLD-UAT-permit-register.csv |

Upload the criteria text as a second response with title “BPLD synthetic SOP and test authority”; source “Test criteria supporting sections 1–3”; note “For demonstration only; not an actual government directive.” Expected: response history shows Oxanna, timestamp and exact uploaded file. Auditor sees response linked to the original request; upload is not automatically professional acceptance of evidence.

**EVD-03** — Auditor: inspect each file; record receipt if available.

| Receipt field | Input |
|---|---|
| Evidence / document version | Select the returned file and its actual version |
| Receipt notes | File readable; row count 60; requested columns present; supplied through CMS by BPLD. |
| Receipt outcome / status | COMPLETE if all requested items are present; PARTIAL if confirmation is still missing |
| Received form | DIGITAL |
| Acquisition method | REQUESTED |

Do not create a second copy of the same received file just to simulate receipt. If the CMS upload does not expose planning links, retain its request lineage and use the separately prepared auditor analysis file below for precise risk/objective traceability.

## 17 · Audit evidence metadata, assessment and Working Papers

**EVD-04** — Upload `BPLD-UAT-sample-results.csv` as auditor-prepared analysis. For three distinct evidence families, use title suffix Assessment / Receipts / Reconciliation and link OBJ/R/CTRL 01, 02 and 03 respectively. The same fixture contains the documented analysis for all three tests; do not misrepresent it as three independent sources.

| Upload field | Input |
|---|---|
| Title | BPLD 12-item sample analysis — Assessment; repeat Receipts / Reconciliation |
| Evidence type | Documentary |
| Source type | Auditor Generated (AUDITOR_GENERATED) |
| Source description | Recalculation and matching of the 12 defined synthetic samples against the CMS-supplied register and test SOP. |
| Date obtained | T |
| Confidentiality | Internal |
| Acquisition method / form | OTHER / DIGITAL; source description explains auditor-prepared recalculation |
| Planning objective | Current OBJ-01 / OBJ-02 / OBJ-03 |
| Risk matrix item | Current R-01 / R-02 / R-03 |
| Control reference | CTRL-01 / CTRL-02 / CTRL-03 |
| Custodian name / office | Charry Bagongon / City Internal Audit Office for auditor analysis; original supplied records retain Oxanna / BPLD |
| Linked Working Papers | Leave blank until papers exist; select corresponding paper if already created |
| File | BPLD-UAT-sample-results.csv |
| Change reason | Blank initially; correction: “Corrected analysis description; source fixture unchanged.” |

Verify files and record checksums. Assess each evidence version used for findings/reporting, including relevant source evidence. The following positive ratings are appropriate only after actually inspecting these fixtures.

| Assessment field | Input |
|---|---|
| Evidence / request / document version | Exact selected evidence; originating request if relevant; actual Core version |
| Sufficiency | YES — all 12 selected sample rows provided |
| Appropriateness | YES — records address the specific procedure |
| Relevance | YES — August BPLD permit controls |
| Reliability | YES — controlled synthetic fixture reconciles to analysis |
| Competence | YES — supplied by identified custodian and examined by assigned auditor |
| Accuracy | YES — recomputation agrees to fixture arithmetic |
| Completeness | YES — required sample attributes are present; blank reviewer fields are test exceptions, not omitted columns |
| Corroboration | YES — analysis agrees to register and criteria |
| Contradiction | NO — no unresolved contradictory source; do not select YES simply because other dimensions are YES |
| Authenticity / integrity | YES / YES — exact test source and checksum checked |
| Confidentiality | INTERNAL |
| Restricted flag / access restrictions | Unchecked / blank for this fixture |
| Limitations | Blank for this complete synthetic evidence set. The planned judgmental-sample boundary is recorded in scope and Working Papers, not treated as an unresolved evidence defect. If an actual evidence limitation exists, record it and obtain the required exception approval |
| Evidence gaps | Blank after supplied files are complete |
| Exception required / reason | Unchecked / blank |
| Evidence outcome | ACCEPTED |
| Change reason for reassessment | Resolved prior incomplete rating after comparing all source and sample rows. |

Expected: verified/locked evidence and eligible professional assessment. “LOCKED” by itself does not mean accepted for reporting; LIMITED or unresolved gaps should block reporting support. Any nonblank assessment Limitations field triggers an exception requirement in the current service, even a sentence such as “None”; leave it genuinely empty when none exists. For an exception branch, document the real limitation, check Exception required, state the proposed constrained use, and obtain independent approval before relying on the evidence.

Finish the request workflow after assessing its linked submissions: Mark received → Queue assessment → Complete assessment → Close request. Expected CLOSED, complete response history retained. Do not close without submission when Oxanna has actually provided files.

**WP-01** — Working Papers & Audit Evidence → New Working Paper. Create three papers, using the returned system codes as actual references.

| Paper field | WP-01 input |
|---|---|
| Procedure | PROC-01 |
| Title | WP-01 — Assessment recalculation |
| Objective | Verify assessment accuracy for the 12 selected permits. |
| Procedure performed | Recomputed PHP 1,000 + PHP 100 × transaction number and compared every selected assessment with the register. |
| Population description | 60 synthetic August 2026 permit transactions. |
| Sample description | IDs 005, 010, 015, 020, 025, 030, 035, 040, 045, 050, 055 and 060; judgmental selection. |
| Result | 12 of 12 assessment amounts agree; zero arithmetic exceptions in the sample. |
| Conclusion | No assessment calculation exception identified in this sample; no population-wide projection made. |
| Evidence | Select accepted source register/criteria and Assessment analysis evidence |
| No-evidence reason | Blank because evidence is linked |
| Cross-references | OBJ-01; R-01; FLOW-01; PROC-01; actual evidence codes, one per line |
| Change reason | Blank initially; returned edit: “Added explicit sample IDs and actual evidence references.” |

For WP-02 select PROC-02, title “WP-02 — Receipt matching”, objective “Verify complete matching receipt references”, procedure “Matched sample receipt references and amounts to assessments”, result “12/12 unique matching receipt references; zero amount differences”, conclusion “No matching exception identified in the 12 samples”, corresponding Receipt evidence and OBJ-02/R-02/FLOW-02/PROC-02 references.

For WP-03 select PROC-03, title “WP-03 — Reconciliation sign-off testing”, objective “Verify independent reconciliation review”, procedure “Inspected reviewer fields for each of the 12 reconciled samples”, result “3/12 lack reviewer sign-off: IDs 005, 025 and 045; 9/12 have review. Sample exception rate 25%. No monetary loss established”, conclusion “Independent review is not consistently evidenced in this sample; investigate and communicate the control weakness”, Reconciliation evidence and matching 03 references. All other population/sample fields remain the same.

Submit each paper, exercise one Return/Resubmit on WP-03, then have an authorized independent reviewer approve. Confirm saved corrected version, APPROVED status, exact evidence links and old version retained.

## 18 · Fieldwork Records and procedure completion

**FW-01** — Execution → choose procedure → New Fieldwork Record. Repeat for each procedure using its corresponding approved paper and evidence.

| Field | Input for PROC-03; use corresponding paper narrative for PROC-01/02 |
|---|---|
| Record type | TESTING |
| Execution status | IN_PROGRESS for draft test; later change to COMPLETED |
| Procedure / area / focus | PROC-03 / REVENUE / REV-REC |
| Performed on / location | T / BPLD synthetic test workspace |
| Objective | Verify independent reconciliation review for the 12 samples. |
| Procedure performed | Inspected recorded reconciliation and secondary sign-off for all 12 sample IDs; compared exceptions with supplied register. |
| Population / sample | 60 August transactions / the 12 fixed IDs from section 17 |
| Analysis | Three missing sign-offs divided by twelve tested equals 25%; missing review does not by itself establish cash loss. |
| Result | Reviewer sign-off absent for DEMO-PERMIT-005, 025 and 045; nine other samples have sign-off. |
| Conclusion | Independent reconciliation review is not consistently documented; develop a supported control finding. |
| Participant name / role | Charry Bagongon / Assigned auditor |
| Participant user / office if displayed | Charry / CIAS |
| Working Papers | Exact approved WP-03 version |
| Evidence | Accepted source and corresponding verified/locked Reconciliation analysis |
| Related records | PROC-03; OBJ-03; R-03; actual WP/evidence codes, one per line |
| Task / assignee / due | Complete reconciliation test / Charry Bagongon / T+14 |
| Change reason on edit | Completed all 12 reconciliation tests and confirmed source links. |

For PROC-01/02 use REV-ASS/REV-COLL, corresponding actual approved paper and analysis; carry the zero-exception results/conclusions from section 17. Task titles are “Complete assessment test” and “Complete receipt matching”; deadlines T+7 and T+10.

**FW-02 negative:** Submit the IN_PROGRESS draft. Expected rejection: execution must be completed. **Recovery:** Edit draft → Execution status COMPLETED → Save new version → Submit. All participants, papers and evidence must remain linked. Record review as eligible independent reviewer, then Finalize as eligible finalizer. Approved linked papers and verified/locked evidence are required for finalization.

**FW-03 separate program progress:** Finalizing records sets `fieldwork_status` to COMPLETED. The program procedure's `status` is a separate value. Open Audit Procedure Details → procedure progress; set status COMPLETED after its finalized record exists. Supply actual Working Paper reference, the same results/conclusion, review state FINALIZED where offered, related references and comment “Finalized fieldwork and approved support verified.” Leave waiver reason blank. Record procedure review with an available accepted/satisfactory result and comments “Execution and support reviewed.” Verify all three procedure statuses are COMPLETED. Do not merely read the fieldwork badge.

Complete the active program when its completion checks pass. Lifecycle → End Fieldwork / Start Findings Communication. Expected FINDINGS_COMMUNICATION only after all procedures are completed/waived and papers are in terminal review states. Keep every intentional exception supported; do not waive the problematic procedure to bypass this test.

## 19 · Issue, AFR finding and recommendations

**ISS-01** — Create issue from PROC-03 fieldwork or Audit Issues. Exact Working Paper/evidence support must carry forward.

| Issue field | Input |
|---|---|
| Title | Missing independent reconciliation sign-off in three sampled permit records |
| Exception statement | Three of twelve sampled August synthetic permit records, IDs 005, 025 and 045, have no secondary reconciliation reviewer recorded. Nine have sign-off. The review requirement is in synthetic SOP section 3. |
| Responsible office / risk rating | BPLD / High, using configured risk label |
| Working Paper versions | Exact approved WP-03 version |
| Evidence versions | Accepted Reconciliation analysis, source register and test SOP |
| Submit / review comment | Sample exceptions and source support are ready for independent validation. |

Submit → validate using an eligible reviewer → convert to finding once. Expected issue CONVERTED_TO_FINDING. A second conversion must not create another finding. Use a separate optional issue for dismissal testing, with reason “Corroborating source resolved the suspected duplicate; no exception remains.”

**AFR-01** — Open the converted finding and complete all content before communication.

| Finding field | Input |
|---|---|
| Title | Independent reconciliation review not consistently documented |
| Criteria | Synthetic SOP BPLD-UAT-SOP-01 section 3 requires a named independent reviewer to sign each reconciliation before archival. |
| Condition | 3 of 12 sampled records (005, 025 and 045) lack reviewer sign-off; the remaining 9 have a reviewer. |
| Cause | Synthetic walkthrough indicates no mandatory reviewer field or exception follow-up checklist at archival. |
| Effect | Incomplete review evidence increases the risk of undetected reconciliation errors. Testing does not establish an actual monetary loss. |
| Conclusion | BPLD's synthetic control trail does not consistently demonstrate independent reconciliation review for the tested sample. |
| Significance classification | Use the supported significant/high control-weakness option; if free text: Significant control weakness |
| Effect classification | Use supported nonfinancial/control option; if free text: Control assurance |
| Risk / responsible office | High / BPLD |
| No-recommendation reason | Blank; two recommendations will be included |
| Direct creation authority reason/reference | Blank; finding is converted from an issue, not directly created |
| Source issue / procedures | Converted issue / PROC-03 |
| Fieldwork record / version | Actual finalized PROC-03 record and exact version |
| Working Paper / evidence versions | Approved WP-03 and accepted reporting support |

**REC-01** — Add two recommendations before finalization.

| Recommendation field | Input |
|---|---|
| Recommendation 1 text | Introduce a mandatory independent reviewer sign-off checklist for each BPLD reconciliation and retain named reviewer evidence before archival. |
| Responsible office / target | BPLD / T+30 |
| Recommendation 2 text | Review the three sampled missing-sign-off records, document the review outcome, and perform a monthly completeness check of reconciliation sign-offs. |
| Responsible office / target | BPLD / T+45 |

Submit and validate the finding as permitted. Expected: complete criteria-condition-cause-effect-conclusion, eligible support and two saved recommendations. Download/view any generated AFR and verify evidence references and target dates.

## 20 · Communicate AFR, management response and rejoinder

**DIALOGUE-01** — Communicate current finding / create transmittal using available workflow actions. Communication permission alone must not bypass validation.

| Communication field | Input |
|---|---|
| Recipients | Oxanna S. Custodio / BPLD, selecting supported recipient control |
| Due date | T+5 |
| Confidentiality | INTERNAL |
| Transmittal method / reference | PORTAL / DEMO-AFR-TX-01 |
| Comment | Request factual confirmation and corrective action for the three documented sign-off exceptions. |
| Delivery / acknowledgement comment | AFR received for BPLD review and response coordination. |

**DIALOGUE-02** — Use Oxanna's permitted response workspace or issued-record deep link. CMS currently exposes AEO, Entry Conference and Evidence Request recipient pages; do not assume a general CMS AFR page exists. If Oxanna has no reachable authorized management-response route, mark this step BLOCKED and record the missing navigation/permission rather than writing an auditor response under her name.

| Response field | Input |
|---|---|
| Agreement position | AGREE, using matching configured label |
| Response kind | ORIGINAL |
| Management comment | BPLD agrees that the synthetic records 005, 025 and 045 lack recorded secondary sign-off. This does not establish a monetary loss. |
| Proposed action | Introduce the reviewer checklist, review the three items and perform monthly sign-off completeness checks. |
| Responsible user | Oxanna S. Custodio |
| Proposed target date | T+30; keep recommendation 2's T+45 date explicitly in response narrative |
| Supplemental reason | Blank for initial response; branch: Additional clarification on the monthly monitoring schedule. |
| Attachment / caption | BPLD-UAT-management-response.txt / Synthetic BPLD corrective action commitments |
| Submission comment | Submitted for auditor disposition with agreed actions and target dates. |

Submit as response author. Auditor Start Review; optional Request Clarification comment “Confirm who performs monthly completeness review” then record a versioned supplemental reply. Extension branch uses requested new due date T+7, justification “Additional time to obtain reviewer confirmation”, and an explicit authorized approve/reject decision. Do not extend silently.

**DIALOGUE-03** — Auditor rejoinder.

| Rejoinder field | Input |
|---|---|
| Disposition | ACCEPT |
| Rejoinder | The response acknowledges the supported condition and proposes specific actions. Retain both recommendations; implementation will be monitored in CMS after report issuance. |
| Attachment / caption | Optional sample-results file / Analysis supporting the accepted factual condition |
| Finalization comment | Dialogue completed; condition and corrective commitments are documented without implying implementation. |

Finalize dialogue using an authorized actor, then finalize the finding when allowed. Expected current finding FINALIZED and issue converted. Agreement on a recommendation does not mean implemented. Lifecycle → Start Exit Conference must remain locked until every issue and current finding meets the gate.

## 21 · Exit Conference: schedule first, actual details later

**EXIT-01** — Lifecycle → Start Exit Conference → Conference Management → Exit → Schedule.

| Scheduling field | Input |
|---|---|
| Start / end | T at next actual test session / +60 minutes |
| Venue | BPLD meeting room — synthetic exit session |
| Meeting link / online details | Blank for in-person |
| Agenda | Present supported finding; discuss accepted response; confirm two recommendations and targets; explain CMS monitoring. |
| Finding IDs | Select the finalized reconciliation finding |
| Participants | Marissa / CIAS / Team Leader; Charry / CIAS / Auditor; Oxanna / BPLD / Auditee representative |
| External name / email | Blank for registered users |

Expected: SCHEDULED, no requirement to invent minutes at scheduling. Reopen and complete actual details after the simulated meeting.

| Completion field | Input |
|---|---|
| Discussion summary | Participants confirmed the 3/12 sign-off exception and the distinction between control risk and established financial loss. |
| Minutes | Presented criteria and sample results; reviewed BPLD response; confirmed checklist target T+30 and retrospective/monthly check target T+45; explained CMS follow-up. |
| Agreements | Retain both recommendations and BPLD ownership; monitor in CMS after final report issuance. |
| Disagreements | None for this synthetic acceptance path. |
| Participant attendance status / notes | PRESENT for each / Attended discussion and confirmed assigned responsibilities. |
| Finding discussion status | Select DISCUSSED / corresponding offered option |
| Finding agreement status | Select AGREED / corresponding offered option |
| Discussion notes | Condition and response were reviewed against source evidence. |
| Agreement details | Two recommendations retained with existing target dates. |
| Disagreement details | Blank |
| Revised target date | Blank because no target changes; branch uses T+45 with documented justification |
| Attachment category / caption | Minutes category offered by form / Synthetic exit meeting record |
| Attachment file | Optional test minutes fixture, never an unsigned file represented as signed |
| Acknowledgement status / comment | Acknowledged option / Confirmed that these minutes reflect the synthetic exit discussion. |

Complete. Expected COMPLETED and exact finding links retained. If a separate acknowledgement is available, record it using the eligible recipient path; do not assume CMS Entry Conference page also handles exits. Lifecycle → Start Reporting should now pass, including any configured progress-assessment gate.

## 22 · Draft report and independent quality review

**REPORT-01** — Audit Reporting Workspace → Draft Report → create from finalized finding. An interim report is an optional branch, not needed for this run's normal path.

| Report field | Input |
|---|---|
| Title | Draft report — BPLD August 2026 permit control review — BPLD-UAT-20260908-A |
| Executive summary | Testing of 12 synthetic permit records found no assessment or receipt-matching exception, but 3 records lacked secondary reconciliation sign-off. Two recommendations address review documentation and follow-up. No monetary loss was established. |
| Confidentiality | Internal |
| Findings | The finalized reconciliation finding |
| Issues | Converted source issue if link control is offered |
| Working Paper versions | Exact approved WP-01, WP-02 and WP-03 versions |
| Evidence | Current accepted supporting source/analysis versions |
| Source interim report version | Blank; no interim report in normal path |
| Interim treatment / source treatments | Blank if no interim source; optional interim branch uses RETAINED_WITH_REVIEW with a stated source-review reason |
| Source link reason | Selected records support sample scope, results and the retained reconciliation finding. |
| Approving authority | Blank for draft unless required by the form; final uses section 23 |
| Recipients | Draft may remain blank unless required; final uses section 23 |
| Change reason | Initial draft blank; revision: “Clarified sample limitation and separated risk from monetary loss.” |

Create these report sections in this order.

| Section title | Section content |
|---|---|
| Background and authority | DEMO-MO-2026-091 authorized this synthetic BPLD control test; actual engagement and AEO codes appear in the record lineage. |
| Objectives and scope | Assessment accuracy, receipt matching and independent reconciliation review for August 2026 BPLD records; 60 transactions and 12 judgmental samples. |
| Methodology and limitations | Recalculation, matching, inspection and inquiry; synthetic SOP sections 1–3. Results apply to selected samples and are not statistically projected. |
| Results | Assessment and receipt testing: 12/12 pass. Reconciliation review: 9/12 pass, 3/12 missing sign-off (005, 025, 045). No quantified monetary loss established. |
| Management response and recommendations | BPLD agreed to a sign-off checklist by T+30 and review/monthly monitoring by T+45; retain both recommendations for CMS follow-up. |
| Overall conclusion | The sample demonstrates a documentation weakness in independent reconciliation review; improvements are needed to provide consistent assurance. |

Quality checklist: check “Only eligible findings are included”, “Evidence and traceability reviewed”, “Confidentiality and recipients verified” and “Quality review completed” only after carrying out the checks. Preserve the generated codes/labels.

Save → Submit → authorized Review. Exercise Return with comment “Explicitly state that no monetary loss was established”; revise the summary and resubmit. Approve the draft if offered/required before creating final. Confirm old draft version and review history remain visible.

## 23 · Final report, authority, issuance and distribution

**REPORT-02** — Create Final from the reviewed draft. Retain section content and exact sources, change title to “Final report — BPLD August 2026 permit control review — BPLD-UAT-20260908-A”. Select finalized findings only.

| Final-only / authority field | Input |
|---|---|
| Approving authority | Cherrybelle A. Lao, CIAS Management — authorized test approver |
| Recipient 1 type / user / delivery | USER / Oxanna S. Custodio / SYSTEM |
| Recipient 2 type / office / delivery | OFFICE / BPLD / SYSTEM, if supported without duplicating the same recipient |
| External name / email | Blank for internal recipients |
| Final change reason | Incorporated independent review and confirmed BPLD commitments for issuance. |
| Submit / review / approval comment | Final text, source versions, recommendations, targets and recipients checked for controlled issuance. |
| Issuance date | Actual T on which issuance is performed |
| Authority role / decision | IAU_HEAD_RECOMMENDATION / RECOMMEND; separately LCE_APPROVAL / APPROVE with actual authorized test actors. Inspect any decisions automatically created by the workflow before adding another |
| Authority comment / decision reference | Final version reviewed for BPLD distribution / DEMO-FINAL-AUTH-01 |
| Signatory user / role | Actual authorized signer / IAU_HEAD, LCE, PRESIDING_OFFICER or REPORT_ISSUER as applicable; these are document responsibilities |
| Signatory name / signed at | Blank when a user is selected / actual test signature time |
| Signature method | CONTROLLED_WORKFLOW for recorded in-app action; never imply a real wet/digital signature |
| Signature reference | DEMO-FINAL-SIGN-01 |
| Transmittal reference / method | DEMO-FINAL-TX-01 / CONTROLLED_SYSTEM |
| Transmittal delivery status / sent at / note | SENT after actual portal issuance / actual time / Synthetic final report distributed through controlled portal. |
| Distribution decision / note | Record delivery when actually visible in portal; acknowledgement only by eligible recipient. Note: Final report made available to BPLD through the test portal. |

Submit → independent review of content → Approve → Issue according to allowed actions, satisfying any authority/signatory/distribution readiness shown. The report transition API has SUBMIT, RETURN, APPROVE and ISSUE; independent review is performed through its available review/approval controls, not an invented REVIEW transition. Then verify aggregate ISSUED; use Issue Final Report lifecycle action if still required. The final report must be issued before recommendation transfer. Record actual actors attached to authority decisions and signatories: automatically populated authority labels must not be mistaken for a real mayoral signature.

Download the final PDF and verify title, scope, sample numbers (60,12,3), named office, two recommendations, target dates and version. Check checksum and actor/time history. Attempts to edit issued text must fail; a formal successor/correction must preserve the issued original.

Optional interim branch: create interim report with the same sources and label its limited purpose; independently review it. If carried forward, select its exact version and record RETAINED_WITH_REVIEW or REVISED plus reason. Do not add an interim report mid-run merely to satisfy a blank optional selector.

## 24 · CMS transfer, reconciliation and handoff acceptance

**TRANSFER-01** — Issuance already attempts recommendation transfer in the current implementation. Inspect the transfer result and both recommendation codes first. On the issued final report use Transfer/Retry transfer to CMS to reconcile or recover it. Refresh before retrying; retry is not a second independent handoff.

| Handoff field / observation | Expected value |
|---|---|
| Source engagement | Actual new BPLD-UAT engagement, not AEMS-2026-001 |
| Source report | Issued final report, exact immutable version |
| Expected recommendations | 2 |
| Expected CMS cases | 2, one for each recommendation |
| Responsible office | BPLD for both |
| Recommendation text | Exact checklist recommendation and retrospective/monthly review recommendation |
| Target dates | T+30 and T+45 |
| Source finding | Finalized reconciliation finding |
| Transfer result / identifier | Capture actual keys and timestamps; do not manufacture IDs |
| Repeat transfer | Same cases reused; total remains 2 for this run |

**TRANSFER-02** — Engagement → Completion & Transfer → Reconcile. Inspect manifest and resource-effort snapshot.

| Reconciliation control | Input / check |
|---|---|
| Reconcile | Run against the issued source and capture generated manifest/snapshot versions |
| Transfer manifest | Two eligible recommendations transferred, no unexplained exclusions or duplicates; exact report document version and checksum |
| Resource effort | Planned 26 days; inspect actual approved ARMIS effort. Do not enter fictional actual effort as if it happened |
| Variance explanation if actual differs | Synthetic run completed in compressed time; retain actual recorded provider values and explain difference from planned 26 days. |
| Open exceptions | Resolve underlying source/transfer/provider issues, then reconcile again; capture anything unresolved as BLOCKED |
| Independent approval comment | Issued report lineage and both BPLD CMS cases reconciled; source counts and exceptions reviewed. |
| Repeat reconcile | Stable transfer keys; no duplicate case generation |

**CMS-01** — Sign in as Oxanna → CMS → Recommendation Registry. Find both cases, open each and verify exact wording, BPLD ownership, target, issued-report lineage and visible permitted actions. Open as an unrelated-office user and expect access denied or hidden records. A same-office representative may see office-addressed material; user-specific recipient restrictions must still follow actual delivery policy.

If a compliance monitor must be assigned, use an authorized CMS manager to assign an eligible monitor (for example Marissa, if granted CMS monitoring permission and not barred by independence rules). The auditee must not independently validate implementation or close their own case solely because they can submit evidence.

**Handoff endpoint:** this guide's main journey passes only when both CMS records exist, remain deduplicated on retry, retain exact AEMS source links and are visible to the appropriate BPLD recipient. CMS action planning and implementation monitoring continue under the CMS workflow; transfer does not equal implemented or closed.

## 25 · Optional completion/closure and exception branches

After CMS handoff, inspect Completion Assessment and formal Closure without claiming that transfer automatically closes the engagement. Complete the assessment's checklist from actual evidence, resolve open exceptions, obtain independent approval, then follow the formal closure route if included in your test scope.

| Optional branch / fields | Synthetic input / expected result |
|---|---|
| Completion assessment narrative | All three procedures completed, approved papers and finalized fieldwork linked; two recommendations transferred. Record any unresolved provider exception honestly |
| Completion assessment evidence | Actual issued report version, transfer manifest, current effort snapshot and final work index |
| Formal closure reason | Authorized test closure after required completion gates are satisfied |
| Final index / retention reference | BPLD-UAT-FINAL-INDEX-01; use the actual configured retention class and custody office, never invent an official retention period |
| Closure checklist | Mark items only after actual review; independent review and approval must be recorded |
| Suspension | Reason: Test interruption for records availability; authority DEMO-SUSP-01; effective T; review T+2; resume requirements: Files supplied and access verified |
| Resume | Reason: Required test records are now available; return only to recorded prior stage |
| Cancellation | Reason: Duplicate synthetic run; authority DEMO-CANCEL-01; effect on IAP: None, special test; disposition: Preserve evidence/history, discontinue draft work |
| Formal revision | Reason: Corrected narrative or traceability; prior approved/issued version remains immutable |
| Waiver of conference | Separate branch only; reason: Authorized simulation branch; authority and support as required. Do not use waiver to hide incomplete normal-path testing |
| Reopening | Separate authorized scenario; select actual closure and authority document version, state correction need and retain original closure history |

The optional branches are independent test cases. Execute potentially disruptive branches on a duplicate fresh test engagement, not the successfully transferred main run.

## 26 · Negative, persistence and access regression matrix

Record actual messages and whether any partial data was written. Expected rejection must preserve prior saved versions.

| Test ID / action | Expected result |
|---|---|
| NEG-01 Create without special authority/date | Validation blocks creation; no partial engagement |
| NEG-02 Select two offices | Rejected; engagement remains single BPLD office |
| NEG-03 Select another area's focus | Rejected or absent from selector |
| NEG-04 Team work while DRAFT | Locked until Prepare Authorization |
| NEG-05 Missing/stale safeguards | Authorization/fieldwork blocked with actionable reason |
| NEG-06 Self-review without explicit permission | Rejected even for copied role name or management title |
| NEG-07 Clone role with same permitted actions | Scoped allowed actions work without relying on original role code; team/office constraints remain |
| NEG-08 Plan draft without program | Draft creation succeeds in permitted planning stage; approval still blocked until complete |
| NEG-09 Save matrix text | Methodology, appetite and conclusion persist in each matrix after refresh |
| NEG-10 Add risk without objective/procedure/paper | Readiness fails; cannot approve/start fieldwork with incomplete links |
| NEG-11 Approved program not activated | Execution unavailable/empty; activation restores eligible procedures |
| NEG-12 Evidence obtained tomorrow | Validation rejects future obtained date |
| NEG-13 Request SUBMITTED but not SENT | Oxanna should not be treated as having received a sent request |
| NEG-14 Unrelated-office request upload | Hidden or denied; no foreign response or evidence created |
| NEG-15 Unsupported/oversize file | Clear rejection, no orphan visible response |
| NEG-16 Reopen completed entry notes | BPLD can read authorized historical notes |
| NEG-17 IN_PROGRESS fieldwork submit | Rejected; COMPLETED saved execution version can submit when other requirements pass |
| NEG-18 Finalize with unapproved paper | Rejected until linked paper approved |
| NEG-19 Complete procedure before finalized fieldwork | Rejected; separate procedure progress completion required afterwards |
| NEG-20 LOCKED evidence with unresolved assessment | Not eligible for reporting merely because locked |
| NEG-21 Contradiction YES | Unresolved contradiction blocks positive reporting eligibility |
| NEG-22 Issue conversion repeated | No second finding from same source conversion |
| NEG-23 Exit during fieldwork/unresolved AFR | Locked until lifecycle and finalized dialogue requirements pass |
| NEG-24 Start reporting before exit completion | Rejected |
| NEG-25 Transfer unissued report | Rejected; no CMS case created |
| NEG-26 Retry transfer/reconcile | No duplicate CMS cases; source lineage stable |
| NEG-27 Edit old approved/issued version | Rejected or read-only; formal revision preserves prior text |
| NEG-28 Two tabs edit same version | Second stale save rejected; refreshing recovers current version |
| NEG-29 Private file anonymous download | Denied; no public storage bypass |
| NEG-30 Auditor vs auditee permissions | Auditee can supply requested files but cannot professionally approve evidence or issue audit report |
| NEG-31 Browser responsiveness | At 390px and desktop widths, all fields, save buttons and validation text are reachable |
| NEG-32 Audit trail | Saves/transitions/transfer record actual actor, target record, time and version |

## 27 · Troubleshooting and known verification boundaries

| Symptom | Check / next action |
|---|---|
| Planning conformance Blocked at draft | Expected until package prerequisites complete. Create draft, finish AEP/program/traceability, then approve |
| Focus selector empty | Refresh engagement scope and verify saved REVENUE + three focus selections. If still absent, capture a scope-to-selector defect |
| Matrix text disappears | Record exact matrix code and before/after text; failure of persistence, not normal versioning |
| Execution shows no procedures | Check current program is ACTIVE, same engagement selected and view/execute permissions present |
| Cannot submit fieldwork | Edit Execution status to COMPLETED and save new version; then submit. Check participant/paper/evidence links |
| End Fieldwork still blocked | Check program procedure status, not only fieldwork status. Complete procedure progress with actual WP reference after finalization |
| Evidence says LIMITED | Review professional assessment and unresolved gaps; locked file is not automatically accepted support |
| CMS request missing | Confirm SENT/delivered state, BPLD office, Oxanna recipient, permissions and refreshed session |
| CMS entry notes missing | Completed record should remain readable if authorized; capture engagement ID, user office and endpoint result |
| Missing approve/review button | Verify actual permission, record state, team/office scope and separation policy; role name alone is insufficient |
| No AFR reply path for Oxanna | Record navigation/permission gap; CMS Evidence Requests is not a generic AFR response page |
| Reporting progress control blocks | Record required status/reference against configured planning control. Missing UI control is a defect, not grounds to bypass validation |
| Reconciliation blocked by ARMIS | Verify actual approved resource data and provider health; planned figures do not substitute for provider actuals |
| API error after save | Capture visible message and server log correlation; never count an error toast as successful save |

Some older repository workflow documents omit Entry/Exit lifecycle stages or describe older approval assumptions. This guide uses current transition services as the operational reference. It does not certify compliance with COA, NGICS or PGIAM; professional rules must be validated against the applicable authoritative documents separately.

## 28 · Final run sheet and source index

| Record / result | Tester fills actual value |
|---|---|
| Run, date, environment, tester | ______________________________ |
| Engagement / AEO / acknowledged by | ______________________________ |
| AEP / Audit Program / active version | ______________________________ |
| Planning package current and approved versions | ______________________________ |
| OBJ/Flow/Risk/Procedure codes reconciled | ______________________________ |
| Entry Conference / historical CMS visibility | ______________________________ |
| Evidence Request / Oxanna responses | ______________________________ |
| Evidence / professional eligibility / exact versions | ______________________________ |
| WP-01/02/03 actual system codes | ______________________________ |
| Fieldwork record codes / finalized versions | ______________________________ |
| Three procedure statuses COMPLETED | ______________________________ |
| Issue / finding / finalized dialogue | ______________________________ |
| Recommendations 1 and 2 / due dates | ______________________________ |
| Exit Conference / completion time | ______________________________ |
| Final report / issued version / checksum | ______________________________ |
| CMS case 1 / case 2 / retry count | ______________________________ |
| Manifest / effort snapshot / approval | ______________________________ |
| Failed/blocked cases and defect references | ______________________________ |
| Overall outcome / reviewer / date | NOT RUN / PASS / FAIL / BLOCKED — __________________ |

Source files checked for this guide (paths relative to repository root):

- `backend/config/demo.php` and `src/pages/shared/LoginPage.jsx`: actual demo identities and card behavior.
- `backend/app/Services/Aems/AemsEngagementTransitionService.php`: aggregate stage transitions and gates.
- `backend/app/Http/Requests/Aems/`: engagement, scope, evidence, working-paper, fieldwork, issue and finding validation.
- `backend/app/Http/Controllers/Api/Aems/`: AEO, AEP, program, planning package, conferences, dialogue, reports and transfer fields/actions.
- `backend/app/Services/Aems/AemsPlanningPackageService.php`: planning readiness and version relationships.
- `backend/app/Services/Aems/AemsFieldworkService.php` and `AemsProgramService.php`: execution completion versus procedure status.
- `backend/app/Services/Aems/AemsEvidenceService.php` and `AemsEvidenceRequestService.php`: eligibility and evidence-request response behavior.
- `src/pages/aems/`: form labels, record selectors and displayed workflow controls.
- `src/pages/cms/CmsEvidenceRequestsPage.jsx`, `CmsEntryConferenceAcknowledgementsPage.jsx` and `src/config/navigation.js`: auditee entry points.
- `docs/AEMS_WORKFLOW_DESIGN.md`, `docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md` and `docs/END_TO_END_TESTING_GUIDE.md`: supporting repository references; current service gates take precedence for testing behavior.

All unlisted search/filter controls are navigation aids, not business content: select the new engagement, current program/version, matching record type and blank search to show all records. Optional external recipient/contact fields remain blank on this internal BPLD path. Generated metadata and hidden concurrency values are managed by AGIS. For any additional configured custom field, record its label, selected master-list value and actual outcome as an environment-specific addendum.
