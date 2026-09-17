<?php

namespace Database\Seeders;

use App\Models\MasterList;
use App\Models\Document;
use App\Services\RuntimeConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds administrator-managed reference categories and their initial values.
 */
class MasterListSeeder extends Seeder
{
    public const REMOVED_SYSTEM_LISTS = [
        'ENGAGEMENT_STATUS',
        'RECOMMENDATION_STATUS',
        'IAP_PLAN_STATUS',
        'IAP_APPROVAL_ACTION',
    ];

    public const LISTS = [
        [
            'code' => 'CONFERENCE_TYPE',
            'name' => 'Conference Type',
            'description' => 'Standard conferences conducted during an audit engagement.',
            'items' => [
                ['ENTRANCE', 'Entrance Conference', 'Opening conference to confirm scope, objectives, responsibilities, and schedules.'],
                ['EXIT', 'Exit Conference', 'Closing conference to discuss observations, responses, and next actions.'],
            ],
        ],
        [
            'code' => 'RISK_LEVEL',
            'name' => 'Risk Level',
            'description' => 'Standard risk ratings used across AGIS.',
            'items' => [
                ['LOW', 'Low', 'Limited likelihood or impact.'],
                ['MEDIUM', 'Medium', 'Moderate likelihood or impact requiring monitoring.'],
                ['HIGH', 'High', 'Significant likelihood or impact requiring prompt action.'],
                ['CRITICAL', 'Critical', 'Severe exposure requiring immediate management attention.'],
            ],
        ],
        [
            'code' => 'AEMS_CONTROL_EFFECTIVENESS',
            'name' => 'AEMS Control Effectiveness',
            'description' => 'Controlled assessment of how well a risk-mitigating control is designed and operating.',
            'items' => [
                ['EFFECTIVE', 'Effective', 'The control is suitably designed and operating as intended.'],
                ['PARTIALLY_EFFECTIVE', 'Partially effective', 'The control exists but has design or operating gaps that require follow-up.'],
                ['INEFFECTIVE', 'Ineffective', 'The control is absent, poorly designed, or not operating as intended.'],
                ['NOT_ASSESSED', 'Not assessed', 'Control effectiveness has not yet been assessed.'],
            ],
        ],
        [
            'code' => 'AEMS_RESIDUAL_RATING',
            'name' => 'AEMS Residual Rating',
            'description' => 'Controlled residual-risk ratings used for AEMS risk matrix items.',
            'items' => [
                ['LOW', 'Low', 'Residual exposure is within normal tolerance.'],
                ['MODERATE', 'Moderate', 'Residual exposure requires monitoring or planned treatment.'],
                ['HIGH', 'High', 'Residual exposure requires prompt management attention.'],
                ['CRITICAL', 'Critical', 'Residual exposure requires immediate escalation and action.'],
            ],
        ],
        [
            'code' => 'FINDING_CLASSIFICATION',
            'name' => 'Finding Classification',
            'description' => 'Common classifications for audit findings.',
            'items' => [
                ['COMPLIANCE', 'Compliance', 'Noncompliance with laws, rules, contracts, or policies.'],
                ['CONTROL', 'Internal Control', 'Design or operating weakness in an internal control.'],
                ['EFFICIENCY', 'Efficiency', 'Resources are not used economically or efficiently.'],
                ['EFFECTIVENESS', 'Effectiveness', 'Objectives or intended outcomes are not being achieved.'],
                ['GOVERNANCE', 'Governance', 'Weakness in oversight, accountability, risk, or decision-making.'],
            ],
        ],
        [
            'code' => 'AEMS_EVIDENCE_CATEGORY',
            'name' => 'AEMS Evidence Category',
            'description' => 'Classification of immutable audit evidence collected during engagement fieldwork.',
            'items' => [
                ['DOCUMENTARY', 'Documentary', 'Policies, contracts, records, reports, forms, and other documentary support.'],
                ['ANALYTICAL', 'Analytical', 'Spreadsheets, calculations, reconciliations, data extracts, and analytical results.'],
                ['TESTIMONIAL', 'Testimonial', 'Interview notes, confirmations, representations, and other testimonial support.'],
                ['PHYSICAL', 'Physical', 'Photographs, inspection records, inventories, and other physical-observation support.'],
                ['ELECTRONIC', 'Electronic', 'System logs, screenshots, exports, messages, and other electronically generated support.'],
            ],
        ],
        [
            'code' => 'AEMS_EVIDENCE_SOURCE_TYPE',
            'name' => 'AEMS Evidence Source Type',
            'description' => 'Origin of evidence collected for an audit engagement.',
            'items' => [
                ['AUDITEE', 'Auditee Provided', 'Obtained from the audited office, process owner, or designated representative.'],
                ['AUDITOR_GENERATED', 'Auditor Generated', 'Prepared or generated by the audit team through fieldwork procedures.'],
                ['THIRD_PARTY', 'Third Party', 'Obtained from an independent external person, office, or organization.'],
                ['SYSTEM', 'System Generated', 'Produced directly by an information system or controlled data export.'],
                ['PUBLIC_SOURCE', 'Public Source', 'Obtained from an authoritative publicly available source.'],
            ],
        ],
    ];

    public function run(): void
    {
        if (! config('demo.full_render_seeders')) {
            MasterList::query()
                ->whereIn('code', self::REMOVED_SYSTEM_LISTS)
                ->each(fn (MasterList $list) => $list->delete());
        }

        foreach ([
            ...self::LISTS,
            $this->documentTypeList(),
            $this->documentConfidentialityList(),
            ...$this->iapLists(),
            $this->officeTypeList(),
            $this->auditAreaTypeList(),
            $this->sectorList(),
            $this->employmentTypeList(),
            $this->positionList(),
        ] as $listData) {
            $list = MasterList::query()->updateOrCreate(
                ['code' => $listData['code']],
                [
                    'name' => $listData['name'],
                    'description' => $listData['description'],
                    'is_active' => true,
                ],
            );

            foreach ($listData['items'] as $index => [$code, $label, $description]) {
                $item = $list->items()->withTrashed()->firstOrNew(['code' => $code]);
                $item->fill([
                    'label' => $label,
                    'description' => $description,
                    'display_order' => $index + 1,
                    'is_active' => true,
                ]);
                $item->save();

                if ($item->trashed()) {
                    $item->restore();
                }
            }
        }

        if (Schema::hasColumn('documents', 'confidentiality_level_id')) {
            $internalId = MasterList::query()
                ->where('code', 'DOCUMENT_CONFIDENTIALITY')
                ->first()
                ?->items()
                ->where('code', 'INTERNAL')
                ->value('id');

            if ($internalId) {
                Document::query()
                    ->withTrashed()
                    ->whereNull('confidentiality_level_id')
                    ->update(['confidentiality_level_id' => $internalId]);
            }

            Document::query()
                ->withTrashed()
                ->whereNull('document_code')
                ->orderBy('id')
                ->each(function (Document $document): void {
                    $document->forceFill([
                        'document_code' => app(RuntimeConfiguration::class)
                            ->formatNumber('document_number_format', $document->id),
                    ])->save();
                });
        }
    }

    /** @return array<string, mixed> */
    private function documentTypeList(): array
    {
        return [
            'code' => 'DOCUMENT_TYPE',
            'name' => 'Document Type',
            'description' => 'Reference-library classifications for laws, manuals, issuances, policies, books, templates, and other audit resources.',
            'items' => [
                ['INTERNAL_AUDIT_MANUAL', 'Internal Audit Manual / PGIAM', 'Philippine Government Internal Audit Manual volumes and related internal-audit guidance.'],
                ['LAW_STATUTE', 'Law / Statute', 'Republic Acts and other statutes relevant to government auditing and public administration.'],
                ['RULES_REGULATIONS', 'Rules and Regulations', 'Implementing rules, regulations, and related regulatory guidance.'],
                ['COA_ISSUANCE', 'COA Circular / Issuance', 'Circulars, memoranda, decisions, and guidance issued by the Commission on Audit.'],
                ['DBM_ISSUANCE', 'DBM Circular / Issuance', 'Budget, compensation, organization, and expenditure guidance issued by DBM.'],
                ['LOCAL_ISSUANCE', 'Local Ordinance / Executive Issuance', 'City ordinances, executive orders, memoranda, and local administrative issuances.'],
                ['POLICY_GUIDELINE', 'Policy / Guideline', 'Approved institutional policies, procedures, standards, and practice guides.'],
                ['REFERENCE_BOOK', 'Reference Book / Publication', 'Books, research papers, articles, and professional reference publications.'],
                ['TEMPLATE_FORM', 'Template / Form', 'Reusable audit programs, checklists, forms, and working-paper templates.'],
                ['OTHER', 'Other Reference', 'Other authorized material relevant to audit work.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function documentConfidentialityList(): array
    {
        return [
            'code' => 'DOCUMENT_CONFIDENTIALITY',
            'name' => 'Document Confidentiality',
            'description' => 'Access classifications that control who may discover, view, and download documents.',
            'items' => [
                ['PUBLIC', 'Public', 'Material approved for broad public access.'],
                ['INTERNAL', 'Internal', 'Routine working material available to authenticated AGIS users.'],
                ['CONFIDENTIAL', 'Confidential', 'Sensitive material limited to authorized audit personnel and administrators.'],
                ['RESTRICTED', 'Restricted', 'Highly sensitive material limited to explicitly privileged users and its uploader.'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function iapLists(): array
    {
        return [
            [
                'code' => 'IAP_AUDIT_UNIVERSE_SUBJECT_TYPE',
                'name' => 'IAP Audit Universe Subject Type',
                'description' => 'Classifications for processes, programs, systems, services, projects, entities, funds, contracts, and other auditable subjects.',
                'items' => [
                    ['PROCESS', 'Process', 'A recurring operational, financial, administrative, or control process.'],
                    ['PROGRAM', 'Program', 'A coordinated set of activities established to deliver public outcomes.'],
                    ['SYSTEM', 'Information System', 'An application, platform, database, infrastructure service, or technology environment.'],
                    ['SERVICE', 'Public Service', 'A service delivered to citizens, offices, partners, or other stakeholders.'],
                    ['PROJECT', 'Project', 'A time-bound capital, development, technology, or organizational initiative.'],
                    ['ENTITY_UNIT', 'Entity / Organizational Unit', 'An office, division, facility, hospital, market, terminal, or other organizational unit.'],
                    ['FUND_ACCOUNT', 'Fund / Account', 'A fund, account, collection stream, trust, grant, or financial resource.'],
                    ['CONTRACT', 'Contract / Procurement', 'A material contract, procurement arrangement, concession, or supplier relationship.'],
                    ['ASSET_FACILITY', 'Asset / Facility', 'A material physical asset, property portfolio, infrastructure, or facility.'],
                    ['CROSS_CUTTING', 'Cross-office Activity', 'A citywide or cross-office subject involving shared governance, controls, or service delivery.'],
                ],
            ],
            [
                'code' => 'IAP_PLANNING_PERIOD_TYPE',
                'name' => 'IAP Planning Period Type',
                'description' => 'Period classifications used by internal audit plans.',
                'items' => [
                    ['ANNUAL', 'Annual', 'A plan covering one fiscal year.'],
                    ['MULTI_YEAR', 'Multi-year', 'A strategic plan covering more than one fiscal year.'],
                    ['SPECIAL', 'Special Planning Period', 'A formally authorized non-standard planning period.'],
                ],
            ],
            [
                'code' => 'IAP_PLANNING_PRIORITY',
                'name' => 'IAP Planning Priority',
                'description' => 'Priority assigned to proposed audit engagements.',
                'items' => [
                    ['IMMEDIATE', 'Immediate', 'Must be scheduled at the earliest practicable date.'],
                    ['HIGH', 'High', 'Should be included and scheduled early in the plan.'],
                    ['MEDIUM', 'Medium', 'Should be included subject to available resources.'],
                    ['LOW', 'Low', 'May be deferred when higher-priority work consumes available resources.'],
                ],
            ],
            [
                'code' => 'IAP_RISK_CRITERION',
                'name' => 'IAP Risk Criterion',
                'description' => 'Weighted criteria used to score office and audit-area planning risk. Default weights total 100 percent.',
                'items' => [
                    ['FINANCIAL_MATERIALITY', 'Financial Exposure and Materiality', 'Default weight 15%. Considers budget, assets, collections, disbursements, and financial significance.'],
                    ['PRIOR_FINDINGS', 'Prior Findings and Recommendations', 'Default weight 15%. Considers prior findings, overdue actions, recurrence, and unresolved recommendations.'],
                    ['CONTROL_MATURITY', 'Internal-control Maturity', 'Default weight 15%. Considers control design, operation, documentation, monitoring, and known weaknesses.'],
                    ['LEGAL_REGULATORY', 'Legal and Regulatory Exposure', 'Default weight 10%. Considers applicable laws, regulations, contractual duties, and compliance consequences.'],
                    ['COMPLEXITY_CHANGE', 'Operational Complexity and Change', 'Default weight 10%. Considers process complexity, reorganizations, new programs, and rapid change.'],
                    ['FRAUD_INTEGRITY', 'Fraud, Integrity and Safeguarding Exposure', 'Default weight 10%. Considers opportunity, susceptibility, prior incidents, and safeguarding responsibility.'],
                    ['PUBLIC_SERVICE_IMPACT', 'Public-service and Stakeholder Impact', 'Default weight 10%. Considers service criticality, affected stakeholders, health, safety, and reputational impact.'],
                    ['TIME_SINCE_AUDIT', 'Time Since Last Audit', 'Default weight 5%. Considers the time elapsed since adequate independent assurance work.'],
                    ['MANAGEMENT_CONCERN', 'Management or Oversight Concern', 'Default weight 5%. Considers formally raised management, council, oversight, or public concerns.'],
                    ['IT_DATA_DEPENDENCY', 'Information-system and Data Dependency', 'Default weight 5%. Considers cybersecurity, privacy, data integrity, availability, and system reliance.'],
                ],
            ],
            [
                'code' => 'IAP_ENGAGEMENT_TYPE',
                'name' => 'IAP Engagement Type',
                'description' => 'Types of proposed internal audit engagements.',
                'items' => [
                    ['FINANCIAL', 'Financial Audit', 'Examines financial records, reporting, safeguarding, and related controls.'],
                    ['COMPLIANCE', 'Compliance Audit', 'Examines compliance with laws, regulations, contracts, and internal policies.'],
                    ['PERFORMANCE', 'Performance / Value-for-money Audit', 'Examines economy, efficiency, effectiveness, and achievement of outcomes.'],
                    ['OPERATIONAL', 'Operational Audit', 'Examines processes, controls, resources, and operational performance.'],
                    ['INFORMATION_SYSTEMS', 'Information Systems Audit', 'Examines IT governance, security, processing, data, and system controls.'],
                    ['GOVERNANCE', 'Governance and Risk Audit', 'Examines governance, risk management, accountability, and oversight.'],
                    ['FOLLOW_UP', 'Follow-up Audit', 'Validates implementation and effectiveness of prior corrective actions.'],
                    ['SPECIAL', 'Special Audit / Management Request', 'Addresses a specially authorized concern, request, or emerging risk.'],
                ],
            ],
            [
                'code' => 'IAP_AUDIT_APPROACH',
                'name' => 'IAP Audit Approach',
                'description' => 'Primary approaches proposed for audit engagements.',
                'items' => [
                    ['RISK_BASED', 'Risk-based', 'Directs work toward the most significant risks and controls.'],
                    ['SYSTEMS_BASED', 'Systems-based', 'Evaluates systems, processes, and control design and operation.'],
                    ['COMPLIANCE_BASED', 'Compliance-based', 'Tests conformance with identified legal and policy criteria.'],
                    ['TRANSACTION_BASED', 'Transaction-based', 'Examines selected transactions and supporting records.'],
                    ['PERFORMANCE_BASED', 'Performance-based', 'Evaluates economy, efficiency, effectiveness, and outcomes.'],
                    ['DATA_ANALYTICS', 'Data Analytics', 'Uses complete or targeted datasets to identify patterns, anomalies, and risk.'],
                    ['BLENDED', 'Blended Approach', 'Combines two or more audit approaches.'],
                ],
            ],
            [
                'code' => 'IAP_TEAM_ROLE',
                'name' => 'IAP Team Role',
                'description' => 'Roles assigned to proposed-engagement team members.',
                'items' => [
                    ['LEAD_AUDITOR', 'Lead Auditor', 'Leads planning and delivery of the proposed engagement.'],
                    ['TEAM_MEMBER', 'Team Member', 'Performs assigned planning and audit work.'],
                    ['REVIEWER', 'Reviewer', 'Provides supervision and independent review.'],
                    ['SPECIALIST', 'Specialist', 'Provides technical, legal, IT, engineering, or other specialist expertise.'],
                    ['SUPPORT', 'Audit Support', 'Provides authorized administrative, data, or logistical support.'],
                ],
            ],
            [
                'code' => 'IAP_COMMENT_TYPE',
                'name' => 'IAP Comment Type',
                'description' => 'Classifications for plan and proposed-engagement comments.',
                'items' => [
                    ['GENERAL', 'General Planning Comment', 'General internal planning discussion.'],
                    ['REVIEW', 'Reviewer Comment', 'Comment made during formal review.'],
                    ['RETURN_INSTRUCTION', 'Return-for-revision Instruction', 'Required correction or clarification when returning a plan.'],
                    ['MANAGEMENT', 'Management Comment', 'CIAS management direction or observation.'],
                    ['APPROVAL_NOTE', 'Approval Note', 'Comment recorded with an approval decision.'],
                    ['REVISION_EXPLANATION', 'Revision Explanation', 'Explanation of changes made in a formal revision.'],
                ],
            ],
            [
                'code' => 'IAP_ATTACHMENT_TYPE',
                'name' => 'IAP Attachment Type',
                'description' => 'Classifications for files supporting an internal audit plan.',
                'items' => [
                    ['RISK_SUPPORT', 'Risk Assessment Support', 'Evidence or analysis supporting a planning risk score.'],
                    ['PLANNING_WORKPAPER', 'Planning Working Paper', 'Internal working paper used to prepare the plan.'],
                    ['MANAGEMENT_DIRECTIVE', 'Management Directive', 'Authorized management instruction or planning request.'],
                    ['BUDGET_RESOURCE_SUPPORT', 'Budget / Resource Support', 'Resource, budget, capacity, or person-day support.'],
                    ['APPROVAL_SUPPORT', 'Approval Support', 'Document associated with review or approval.'],
                    ['OTHER', 'Other IAP Attachment', 'Other authorized planning attachment.'],
                ],
            ],
            [
                'code' => 'IAP_AUDITOR_SPECIALIZATION',
                'name' => 'IAP Auditor Specialization',
                'description' => 'Professional and technical capabilities used to match IAP engagement requirements with available auditors.',
                'items' => [
                    ['FINANCIAL_AUDIT', 'Financial Audit and Accounting', 'Financial reporting, accounting, treasury, disbursement, and safeguarding controls.'],
                    ['COMPLIANCE', 'Compliance and Regulatory Audit', 'Assessment of compliance with laws, rules, contracts, policies, and regulatory requirements.'],
                    ['PERFORMANCE', 'Performance and Value-for-money Audit', 'Economy, efficiency, effectiveness, service outcomes, and performance measurement.'],
                    ['PROCUREMENT', 'Procurement and Supply Management', 'Procurement planning, bidding, contracting, receiving, inventory, and property controls.'],
                    ['INFORMATION_SYSTEMS', 'Information Systems Audit', 'IT governance, application controls, infrastructure, data integrity, and system availability.'],
                    ['CYBERSECURITY', 'Cybersecurity and Data Protection', 'Cybersecurity, privacy, access control, incident response, backup, and recovery.'],
                    ['REVENUE', 'Revenue and Collection Audit', 'Taxes, fees, permits, collections, deposits, receivables, and revenue assurance.'],
                    ['HR_PAYROLL', 'Human Resources and Payroll', 'Appointments, attendance, compensation, payroll, benefits, and personnel records.'],
                    ['ENGINEERING', 'Engineering and Infrastructure', 'Public works, project delivery, construction, inspection, asset condition, and contract administration.'],
                    ['FRAUD_INVESTIGATION', 'Fraud and Integrity Review', 'Fraud-risk assessment, investigative methods, evidence handling, and integrity controls.'],
                    ['DATA_ANALYTICS', 'Audit Data Analytics', 'Data acquisition, validation, analysis, visualization, anomaly detection, and continuous auditing.'],
                    ['GOVERNANCE_RISK', 'Governance and Risk Management', 'Governance, enterprise risk, internal control, accountability, and management oversight.'],
                ],
            ],
            [
                'code' => 'IAP_UNAVAILABILITY_TYPE',
                'name' => 'IAP Auditor Unavailability Type',
                'description' => 'Reasons an auditor cannot be assigned during a specified period.',
                'items' => [
                    ['LEAVE', 'Leave', 'Approved vacation, sick, parental, or other authorized leave.'],
                    ['TRAINING', 'Training / Professional Development', 'Training, conference, certification, or other professional-development activity.'],
                    ['OFFICIAL_BUSINESS', 'Official Business', 'Authorized assignment or official business outside planned audit work.'],
                    ['OTHER', 'Other Unavailable Period', 'Other documented period when the auditor is unavailable for assignment.'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function sectorList(): array
    {
        return [
            'code' => 'OFFICE_SECTOR',
            'name' => 'Office Sector',
            'description' => 'City government sector groupings used to classify offices in the Office Registry.',
            'items' => [
                ['SYSTEM', 'System', 'Platform-level and shared AGIS administration.'],
                ['ADMIN_FINANCE_LEGAL', 'Administration, Finance and Legal', 'Administrative, fiscal, legal, procurement, records, and governance services.'],
                ['PLANNING_INFRA_TECH_ENV', 'Planning, Infrastructure, Technology and Environment', 'Planning, engineering, infrastructure, ICT, land use, and environmental services.'],
                ['HEALTH_EDUCATION_SOCIAL', 'Health, Education and Social Services', 'Health, education, housing, social welfare, and community support services.'],
                ['AGRI_BUSINESS_EMPLOYMENT', 'Agriculture, Business and Employment', 'Agriculture, enterprise, market, tourism, licensing, and employment services.'],
                ['PUBLIC_SAFETY_OTHER', 'Public Safety, Community and Other Services', 'Public safety, emergency response, community affairs, and other frontline services.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function officeTypeList(): array
    {
        return [
            'code' => 'OFFICE_TYPE',
            'name' => 'Office Type',
            'description' => 'Independent organizational classifications used by the Office Registry. Office records do not inherit from or report to another office in AGIS.',
            'items' => [
                ['DEPARTMENT', 'Department', 'A city department established to perform a broad functional mandate.'],
                ['OFFICE', 'Office', 'An independent city office with a defined service or administrative mandate.'],
                ['DIVISION', 'Division', 'An independently registered division for AGIS assignment and reporting.'],
                ['SECTION', 'Section', 'An independently registered section for AGIS assignment and reporting.'],
                ['UNIT', 'Unit', 'An independently registered operational or support unit.'],
                ['SPECIAL_BODY', 'Special Office / Body', 'An independent special office, board, council, hospital, or other city entity.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function auditAreaTypeList(): array
    {
        return [
            'code' => 'AUDIT_AREA_TYPE',
            'name' => 'Audit Area Type',
            'description' => 'Reusable classifications for parent and sub-audit areas.',
            'items' => [
                ['PROCESS', 'Process', 'An end-to-end operational, financial, administrative, or control process.'],
                ['SYSTEM', 'System', 'An information system, control system, platform, or supporting technology environment.'],
                ['PROGRAM', 'Program', 'A coordinated public program or group of activities intended to deliver outcomes.'],
                ['FUNCTION', 'Function', 'A recurring organizational mandate or management function.'],
                ['THEME', 'Theme', 'A cross-cutting governance, compliance, integrity, or performance theme.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function employmentTypeList(): array
    {
        return [
            'code' => 'GOVERNMENT_EMPLOYMENT_TYPE',
            'name' => 'Government Employment Type',
            'description' => 'Appointment and engagement categories used for government personnel records.',
            'items' => [
                ['PERMANENT', 'Permanent', 'Formal CSC appointment; normally has a plantilla item; provides the highest security of tenure.'],
                ['TEMPORARY', 'Temporary', 'Formal CSC appointment to a plantilla item with limited tenure.'],
                ['SUBSTITUTE', 'Substitute', 'Formal CSC appointment using an absent employee’s item until the incumbent returns.'],
                ['COTERMINOUS', 'Coterminous', 'Formal CSC appointment tied to an official, office, agency, or project; may have an authorized position.'],
                ['FIXED_TERM', 'Fixed-term', 'Formal CSC appointment, usually authorized by law, that ends when the specified term expires.'],
                ['CONTRACTUAL_APPOINTMENT', 'Contractual appointment', 'Formal CSC appointment for a contract or project period and not used to fill a regular vacant plantilla item.'],
                ['CASUAL', 'Casual', 'Formal appointment under a separate casual arrangement, generally for essential services and usually up to one year.'],
                ['JOB_ORDER', 'Job Order', 'Non-appointment engagement without a plantilla item, governed by a Job Order contract.'],
                ['CONTRACT_OF_SERVICE', 'Contract of Service', 'Non-appointment engagement without a plantilla item, governed by a Contract of Service.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function positionList(): array
    {
        $titles = [
            'Platform Administrator',
            'AGIS Administrator',
            'City Internal Audit Officer',
            'Internal Auditor',
            'Office Head',
            'Office Employee',
            'City Mayor',
            'City Government Department Head I',
            'City Government Department Head II',
            'City Government Assistant Department Head I',
            'City Government Assistant Department Head II',
            'Administrative Aide I',
            'Administrative Aide II',
            'Administrative Aide III',
            'Administrative Aide IV',
            'Administrative Aide V',
            'Administrative Aide VI',
            'Administrative Assistant I',
            'Administrative Assistant II',
            'Administrative Assistant III',
            'Administrative Assistant IV',
            'Administrative Assistant V',
            'Administrative Officer I',
            'Administrative Officer II',
            'Administrative Officer III',
            'Administrative Officer IV',
            'Administrative Officer V',
            'Accountant I',
            'Accountant II',
            'Accountant III',
            'Accountant IV',
            'Accounting Clerk I',
            'Accounting Clerk II',
            'Accounting Clerk III',
            'Accounting Clerk IV',
            'Internal Auditor I',
            'Internal Auditor II',
            'Internal Auditor III',
            'Internal Auditor IV',
            'Internal Auditor V',
            'Budget Officer I',
            'Budget Officer II',
            'Budget Officer III',
            'Budget Officer IV',
            'Cashier I',
            'Cashier II',
            'Cashier III',
            'Cashier IV',
            'Supply Officer I',
            'Supply Officer II',
            'Supply Officer III',
            'Supply Officer IV',
            'Records Officer I',
            'Records Officer II',
            'Records Officer III',
            'Records Officer IV',
            'Human Resource Management Officer I',
            'Human Resource Management Officer II',
            'Human Resource Management Officer III',
            'Human Resource Management Officer IV',
            'Planning Officer I',
            'Planning Officer II',
            'Planning Officer III',
            'Planning Officer IV',
            'Project Development Officer I',
            'Project Development Officer II',
            'Project Development Officer III',
            'Project Development Officer IV',
            'Project Development Officer V',
            'Engineer I',
            'Engineer II',
            'Engineer III',
            'Engineer IV',
            'Engineer V',
            'Architect I',
            'Architect II',
            'Architect III',
            'Architect IV',
            'Architect V',
            'Information Systems Analyst I',
            'Information Systems Analyst II',
            'Information Systems Analyst III',
            'Computer Programmer I',
            'Computer Programmer II',
            'Computer Programmer III',
            'Information Technology Officer I',
            'Information Technology Officer II',
            'Information Technology Officer III',
            'Social Welfare Officer I',
            'Social Welfare Officer II',
            'Social Welfare Officer III',
            'Social Welfare Officer IV',
            'Social Welfare Officer V',
            'Medical Officer I',
            'Medical Officer II',
            'Medical Officer III',
            'Medical Officer IV',
            'Medical Officer V',
            'Nurse I',
            'Nurse II',
            'Nurse III',
            'Nurse IV',
            'Nurse V',
            'Agriculturist I',
            'Agriculturist II',
            'Agriculturist III',
            'Agriculturist IV',
            'Veterinarian I',
            'Veterinarian II',
            'Veterinarian III',
            'Veterinarian IV',
            'Legal Officer I',
            'Legal Officer II',
            'Legal Officer III',
            'Legal Officer IV',
            'Legal Officer V',
            'Licensing Officer I',
            'Licensing Officer II',
            'Licensing Officer III',
            'Licensing Officer IV',
            'Revenue Collection Clerk I',
            'Revenue Collection Clerk II',
            'Revenue Collection Clerk III',
            'Tourism Operations Officer I',
            'Tourism Operations Officer II',
            'Tourism Operations Officer III',
            'Tourism Operations Officer IV',
            'Labor and Employment Officer I',
            'Labor and Employment Officer II',
            'Labor and Employment Officer III',
            'Labor and Employment Officer IV',
            'Environmental Management Specialist I',
            'Environmental Management Specialist II',
            'Environmental Management Specialist III',
            'Disaster Risk Reduction and Management Officer I',
            'Disaster Risk Reduction and Management Officer II',
            'Disaster Risk Reduction and Management Officer III',
            'Disaster Risk Reduction and Management Officer IV',
            'Utility Worker I',
            'Utility Worker II',
            'Driver I',
            'Driver II',
            'Driver III',
        ];

        return [
            'code' => 'POSITION',
            'name' => 'Position',
            'description' => 'Government position titles based on the DBM Index of Occupational Services and common LGU plantilla positions. Authorized custom titles may also be added.',
            'items' => collect($titles)
                ->map(fn (string $title): array => [
                    Str::of($title)->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->toString(),
                    $title,
                    'Government position title for user plantilla and assignment records.',
                ])
                ->all(),
        ];
    }
}
