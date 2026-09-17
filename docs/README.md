# AGIS Documentation

This directory is the as-built documentation set for AGIS Core, Internal Audit
Planning (IAP), Audit Engagement Monitoring (AEMS), Compliance Management (CMS),
and Audit Resource Management (ARMIS). The source code and automated tests remain
the implementation authority; these documents explain the behavior, controls,
interfaces, operations, and acceptance procedures.

**Last synchronized with source and tests:** 24 August 2026.

## Document map

| Document | Audience | Contents |
| --- | --- | --- |
| [System Flow](SYSTEM_FLOW.md) | Product owners, developers, reviewers | End-to-end browser, API, database, file, authorization, logging, configuration, notification, IAP, AEMS, CMS, and integration flows |
| [AGIS As-Built System Manual](AGIS_AS_BUILT_SYSTEM_MANUAL.md) | New users, administrators, reviewers, developers, and testers | Consolidated module-by-module features, page functions, lifecycle rules, ownership boundaries, controls, and troubleshooting |
| [AGIS Operational Playbooks](AGIS_OPERATIONAL_PLAYBOOKS.md) | Operators, auditors, reviewers, compliance monitors, and acceptance teams | Step-by-step procedures for create, review, return, approve, issue, transfer, decline, disposition, reopening, closure, exports, and blocked actions |
| [As-Built Feature Catalog](AS_BUILT_FEATURE_CATALOG.md) | Product owners, reviewers, testers, and developers | Feature-by-feature comparison of Core, IAP, AEMS, CMS, ARMIS, shared controls, routes, workflows, boundaries, and verification evidence |
| [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md) | Core administrators, analysts, developers | Authentication, registries, roles/scopes, master lists, documents, workflows, notifications, logs, and configuration |
| [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md) | CIAS management, auditors, analysts, developers | Strategic planning, Audit Universe, coexisting risk systems, prioritization, annual plans, schedules, capacity, approvals, and reports |
| [BAICS Governance Contract](BAICS_GOVERNANCE_CONTRACT.md) | CIAS governance, IAP product owners, auditors, reviewers, developers, and testers | BAICS-0 through BAICS-4: cycle scope, source lineage, assignments, lifecycle, five control components, distinct methods, exact Core evidence, corroboration exceptions, Control Universe, interim analysis, BAR assembly, approved IAP consumer decisions, legacy exceptions, protected exports, readiness and immutable versions |
| [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md) | CIAS management, auditors, auditee representatives, developers | Engagement authorization, planning, fieldwork, evidence, findings, responses, conferences, reporting, transfer, closure, and reopening |
| [AEMS Governance and Acceptance (compiled)](AEMS_GOVERNANCE_AND_ACCEPTANCE.md) | CIAS authorities, product owners, reviewers, developers, and testers | Single module-level compilation of AEMS-G0 through G10E decisions, features, controls, routes, boundaries, and acceptance evidence |
| [AEMS Implementation Baseline](AEMS_IMPLEMENTATION_BASELINE.md) | Product owners, architects, developers, reviewers | AEMS-0 through AEMS-G10E baseline, planning/team safeguard contracts, linked fieldwork execution workspace, Evidence Management workspace, operational queues, records/closure workspace, procedure and finding traceability gates, cross-module boundaries, migration rules, and phase gates |
| [AEMS Cross-Module Integration](AEMS_CROSS_MODULE_INTEGRATION.md) | Architects, security reviewers, Core/IAP/ARMIS/CMS integrators | AEMS-11 ownership, read-only IAP lineage, ARMIS provider modes, CMS immutable intake provenance, scope/security controls, and verification contract |
| [CMS Workflow Design](CMS_WORKFLOW_DESIGN.md) | Responsible offices, Compliance Monitors, validators, CIAS management, developers | Immutable intake, Action Plans, progress, validation, extensions, escalations, closure, dispositions, reopening, automation, reports, and exports |
| [ARMIS Workflow and Implementation Checkpoint](ARMIS_WORKFLOW_DESIGN.md) | Resource administrators, planners, auditors, developers, deployers | Resource registry, competencies, planning, assignments, actuals, reports, provider boundary, reconciliation, monitoring, security, and deployment gates |
| [API and Data Reference](API_AND_DATA_REFERENCE.md) | Frontend/backend developers and integrators | Endpoint families, request conventions, entities, relationships, permissions, configuration, and protected downloads |
| [Operations Guide](OPERATIONS_GUIDE.md) | Developers, deployers, administrators | Setup, migrations, seeders, storage, SMTP, verification, production, backup, monitoring, troubleshooting, and deployment gates |
| [Render Deployment](RENDER_DEPLOYMENT.md) | Render deployers and operators | Free-tier service/database setup, environment variables, startup, private storage, hardening, and smoke verification |
| [End-to-End Testing Guide](END_TO_END_TESTING_GUIDE.md) | New users, testers, reviewers, and acceptance teams | Step-by-step Core, IAP, AEMS, CMS, ARMIS, security, integrity, responsive, and automated acceptance testing |
| [Development Standards](DEVELOPMENT_STANDARDS.md) | Everyone changing the system | Security, integrity, UX, reliability, testing, and definition of done |

## Current as-built module status

The table below is the current status baseline. Increment-specific notes in the
workflow documents are historical checkpoints and must not be read as a current
feature inventory unless they are explicitly marked as current.

| Module | Current implementation | Documentation coverage |
| --- | --- | --- |
| AGIS Core | Authentication, users/offices, roles and scoped permissions, audit areas/focuses, master lists, documents and immutable versions, workflows, notifications, runtime configuration, Activity Log, Audit Trail, soft deletion, and optimistic locking | Complete in [Core Workflow Design](CORE_WORKFLOW_DESIGN.md), [API and Data Reference](API_AND_DATA_REFERENCE.md), [System Flow](SYSTEM_FLOW.md), and [Operations Guide](OPERATIONS_GUIDE.md) |
| IAP | Strategic plan, Audit Universe, both coexisting risk-assessment systems, BAICS-0 through BAICS-4, prioritization, annual plan, scheduling, capacity, reports, approvals, imports, and scope/history controls | Complete in [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md) and [BAICS Governance Contract](BAICS_GOVERNANCE_CONTRACT.md), with API, testing, and operations references |
| AEMS | Approved operational engagement authorization, planning, team safeguards and ARMIS provider gate, fieldwork execution, Evidence Requests and immutable evidence assessments, Working Papers/Evidence, Issues, Findings, responses, conferences, interim/final reporting and distribution, CMS transfer, completion, closure, retention/index, controlled reopening, scope-aware dashboard, work queues, protected exports, notification monitoring, calendar, records/disposition controls, and cross-module ownership/security hardening are implemented and accepted through AEMS-G10E. AIS integration remains outside the current scope; compatibility aliases, reserved SCR-243, and the two IAP risk systems remain explicitly preserved. | As-built behavior in [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md), [AEMS Governance and Acceptance](AEMS_GOVERNANCE_AND_ACCEPTANCE.md), [AEMS Cross-Module Integration](AEMS_CROSS_MODULE_INTEGRATION.md), and [AEMS Implementation Baseline](AEMS_IMPLEMENTATION_BASELINE.md), with API, testing, and system-flow references |
| CMS | CMS-1 through CMS-12B: intake, registry/detail, assignments, Action Plans, Progress Updates, Validation, extensions, escalations, closure, Accepted-Risk, No-Longer-Applicable, controlled reopening, automation/candidates, reports, and protected CSV/PDF exports | Complete in [CMS Workflow Design](CMS_WORKFLOW_DESIGN.md), [API and Data Reference](API_AND_DATA_REFERENCE.md), [Operations Guide](OPERATIONS_GUIDE.md), and the end-to-end guide |
| ARMIS | ARMIS-0 through ARMIS-7C: resource registry, competencies/certifications, planning/utilization, assignments/actuals, reports/exports, provider adapter, reconciliation/authority gate, monitoring, security regression, deployment preflight, and Render smoke verification | Complete in [ARMIS Workflow and Implementation Checkpoint](ARMIS_WORKFLOW_DESIGN.md), with API, operations, Render, and acceptance-testing references |
| AFR | Placeholder navigation/routes only; AEMS currently owns its implemented Findings and Recommendations workspace | Explicitly documented as not implemented |
| AIS | AIS-5D verified read-only analytical dashboard, reporting, and responsive source-health workspace over scope-aware Core/IAP/AEMS/CMS/ARMIS adapters, with freshness/reconciliation checks, fail-closed aggregation, immutable integration snapshots, source cards, scope/confidentiality indicators, authoritative-module links, review indicators, immutable reports, protected CSV/PDF exports, private responses, rate limits, and audit events; operational writes remain disabled | Documented in [AIS Governance and Data Contract](AIS_GOVERNANCE_CONTRACT.md), with API, testing, and operations references |

### Standards-conformance caveat

AGIS's operational audit lifecycle is substantially implemented, but formal
PGIAM/NGICS/ICSPPS/IASPPS conformance remains qualified. The deferred roadmap
for future BAICS governance enhancements, Internal Audit Charter and organizational independence, QAIP,
professional development/CPD, and standards traceability is recorded in the
[AEMS Governance and Acceptance](AEMS_GOVERNANCE_AND_ACCEPTANCE.md#14-deferred-standards-governance-roadmap).
These items are not implemented by the current acceptance baseline.

### Integration boundaries

- IAP supplies approved engagement plans to AEMS.
- AEMS transfers finalized recommendations to CMS exactly once.
- ARMIS is the sole operational resource and allocation provider. AEMS and IAP
  scheduling read ARMIS directly. Historical IAP resource records remain for
  planning lineage and reconciliation evidence only; fallback/shadow values
  are not runtime options or active provider paths.
- AIS-5D provides the verified hardened read-only analytical presentation, review indicators,
  immutable reports, and protected CSV/PDF exports. AIS does not mutate source
  modules or make professional decisions. Its integration contract rechecks
  source scope, confidentiality, lineage, freshness, and reconciliation; the
  health backend records immutable, actor-scoped diagnostics and fails closed
  when a source is unavailable or ineligible. The source-health workspace links
  each exception back to its authoritative module. Operational writes remain
  reserved for later AIS phases.
- CMS automation may create reminders or reviewable candidates only. It cannot
  make final professional decisions, close cases, reopen cases, or issue
  escalation notices automatically.
- The two IAP risk systems (`iap_risk_assessments` and
  `iap_universe_risk_assessments`) intentionally coexist for compatibility; no
  migration or removal is implied by this documentation.

## Recommended reading order

For a new developer:

1. [AGIS As-Built System Manual](AGIS_AS_BUILT_SYSTEM_MANUAL.md)
2. [AGIS Operational Playbooks](AGIS_OPERATIONAL_PLAYBOOKS.md)
3. [System Flow](SYSTEM_FLOW.md)
4. [As-Built Feature Catalog](AS_BUILT_FEATURE_CATALOG.md)
5. [Development Standards](DEVELOPMENT_STANDARDS.md)
6. [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md)
7. [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md)
8. [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md)
9. [AEMS Governance and Acceptance (compiled)](AEMS_GOVERNANCE_AND_ACCEPTANCE.md)
10. [AEMS Implementation Baseline](AEMS_IMPLEMENTATION_BASELINE.md)
11. [AEMS Cross-Module Integration](AEMS_CROSS_MODULE_INTEGRATION.md)
12. [CMS Workflow Design](CMS_WORKFLOW_DESIGN.md)
13. [ARMIS Workflow and Implementation Checkpoint](ARMIS_WORKFLOW_DESIGN.md)
14. [API and Data Reference](API_AND_DATA_REFERENCE.md)
15. [Operations Guide](OPERATIONS_GUIDE.md)
16. [End-to-End Testing Guide](END_TO_END_TESTING_GUIDE.md)

For a CIAS reviewer:

1. [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md)
2. [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md)
3. [CMS Workflow Design](CMS_WORKFLOW_DESIGN.md)
4. [ARMIS Workflow and Implementation Checkpoint](ARMIS_WORKFLOW_DESIGN.md)
5. [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md)
6. [System Flow](SYSTEM_FLOW.md)

## Documentation maintenance

Documentation is part of the definition of done. A behavior change must update:

- the relevant workflow document;
- API/data reference when routes, payloads, entities, lists, permissions, or
  configuration change;
- System Flow when a cross-cutting request, security, file, notification, logging,
  or integration path changes;
- Operations and Render guides when deployment, migration, seeding, storage,
  email, backup, monitoring, or testing changes;
- the End-to-End Testing Guide when a user-visible page, role, workflow, or
  acceptance step changes.

Use current code and tests as the implementation source of truth:

- frontend routes: `src/App.jsx`;
- navigation and permissions: `src/config/navigation.js`;
- API client: `src/services/api.js`;
- backend routes: `backend/routes/api.php`;
- business rules: `backend/app/Services` and Form Requests;
- persistence: `backend/app/Models` and `backend/database/migrations`;
- defaults: `backend/database/seeders`;
- verification: `backend/tests/Feature` and `tests/e2e`.

## Verification and checkpoint history

The workflow documents retain increment-specific implementation and verification
checkpoints (CMS-1 through CMS-12B and ARMIS-0 through ARMIS-7C). Those sections
describe what was added at each gate and are historical context, not a statement
that later phases are missing. The current verification commands are maintained
in [Operations Guide](OPERATIONS_GUIDE.md), and the manual acceptance flows are
maintained in [End-to-End Testing Guide](END_TO_END_TESTING_GUIDE.md).

The workflow documents use Mermaid diagrams. GitHub and compatible Markdown
viewers render them automatically. In a viewer without Mermaid support, the
adjacent headings, tables, and numbered rules remain the authoritative text.
