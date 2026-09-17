<?php

namespace Database\Seeders;

use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\MasterListItem;
use App\Models\Office;
use Illuminate\Database\Seeder;

/**
 * Seeds realistic audit areas, subareas, focuses, and office coverage.
 */
class AuditAreaSeeder extends Seeder
{
    public const AREAS = [
        [
            'code' => 'PROCUREMENT',
            'name' => 'Procurement and Supply Management',
            'description' => 'How city offices plan purchases, select suppliers, receive, record, store, and distribute goods, equipment, and services.',
            'responsible_office' => 'CGSO',
            'offices' => ['*'],
            'focuses' => [
                ['PROC-PLAN', 'Procurement Planning', 'Preparation of the Annual Procurement Plan, budget alignment, and identification of procurement requirements.'],
                ['PROC-COMP', 'Procurement Process Compliance', 'Compliance with procurement laws and procedures, including bidding, quotations, evaluation, approvals, and documentation.'],
                ['PROC-SUPP', 'Supplier Selection and Contracting', 'Fair supplier selection, eligibility verification, and accuracy of purchase orders and contracts.'],
                ['PROC-RECV', 'Receiving and Inspection', 'Verification of delivery quantity and quality and completion of inspection and acceptance reports.'],
                ['PROC-PAY', 'Payment Processing', 'Complete supporting documents, correct payment amounts, and prevention of duplicate or unauthorized payments.'],
                ['PROC-INV', 'Inventory and Property Recording', 'Timely recording, accurate inventory and property records, tagging, and accountability assignment.'],
                ['PROC-STOR', 'Storage and Distribution', 'Secure storage, controlled issuance, and prevention of loss, damage, misuse, or excessive stock.'],
                ['PROC-MON', 'Monitoring and Reporting', 'Physical counts, reconciliations, and reporting of missing, damaged, or obsolete property.'],
            ],
        ],
        [
            'code' => 'FINANCIAL',
            'name' => 'Financial Management and Disbursement',
            'description' => 'Budget execution, accounting, cash handling, payroll, collections, disbursements, and financial reporting.',
            'responsible_office' => 'CFO',
            'offices' => ['CAD', 'CBO', 'CFO', 'CGSO', 'CEEBDA', 'EWBT', 'BPLD', 'CAO', 'CSWDD'],
            'focuses' => [
                ['FIN-BUD', 'Budget Authorization and Utilization', 'Approved appropriations, allotments, obligations, and budget utilization.'],
                ['FIN-CASH', 'Cash Receipts and Deposits', 'Collection, acknowledgement, safeguarding, and timely deposit of city funds.'],
                ['FIN-DISB', 'Disbursement Controls', 'Authorization, supporting documents, accuracy, and timeliness of disbursements.'],
                ['FIN-PAY', 'Payroll and Benefits', 'Payroll accuracy, employee eligibility, deductions, and benefit payments.'],
                ['FIN-REC', 'Account Reconciliation', 'Bank, subsidiary-ledger, and general-ledger reconciliation procedures.'],
                ['FIN-REP', 'Financial Reporting', 'Completeness, accuracy, timeliness, and disclosure in financial reports.'],
            ],
        ],
        [
            'code' => 'HR',
            'name' => 'Human Resource and Payroll Administration',
            'description' => 'Recruitment, appointments, attendance, compensation, performance, training, and employee-record controls.',
            'responsible_office' => 'HRMO',
            'offices' => ['HRMO', 'CAD', 'CFO', 'OCA', 'CIAS'],
            'focuses' => [
                ['HR-RECR', 'Recruitment and Appointment', 'Merit-based selection, qualification verification, and appointment documentation.'],
                ['HR-201', 'Personnel Records', 'Completeness, security, retention, and authorized access to employee 201 files.'],
                ['HR-ATT', 'Attendance and Leave', 'Timekeeping, leave credits, approvals, and prevention of unsupported attendance entries.'],
                ['HR-PERF', 'Performance Management', 'Performance commitments, reviews, ratings, and development actions.'],
                ['HR-TRAIN', 'Learning and Development', 'Training needs, approvals, attendance, and application of learning.'],
            ],
        ],
        [
            'code' => 'REVENUE',
            'name' => 'Revenue, Tax, Permit and Fee Administration',
            'description' => 'Assessment, billing, collection, recording, reconciliation, and enforcement for city taxes, permits, and fees.',
            'responsible_office' => 'CFO',
            'offices' => ['BPLD', 'CFO', 'CASS', 'OCBO', 'CEEBDA', 'EWBT', 'CTCAO', 'CVO'],
            'focuses' => [
                ['REV-ASS', 'Assessment and Billing', 'Complete taxpayer records and accurate tax, permit, and fee assessments.'],
                ['REV-COLL', 'Collection and Official Receipts', 'Authorized collection, sequential receipts, and safeguarding of collections.'],
                ['REV-DEP', 'Deposit and Remittance', 'Complete and timely deposit or remittance of collections.'],
                ['REV-DELQ', 'Delinquency Monitoring', 'Identification, notification, enforcement, and reporting of delinquent accounts.'],
                ['REV-REC', 'Revenue Reconciliation', 'Reconciliation between operational records, collections, deposits, and accounting records.'],
            ],
        ],
        [
            'code' => 'ICT',
            'name' => 'Information Systems and Data Protection',
            'description' => 'IT governance, access control, cybersecurity, change management, backup, recovery, privacy, and system availability.',
            'responsible_office' => 'CMISID',
            'offices' => ['CMISID', 'CINFO', 'CCRO', 'CAD', 'CFO', 'HRMO', 'BPLD', 'CIAS'],
            'focuses' => [
                ['ICT-GOV', 'IT Governance and Planning', 'Technology strategy, project governance, ownership, policies, and investment alignment.'],
                ['ICT-ACC', 'Logical Access Management', 'User provisioning, role assignment, privileged access, and periodic access review.'],
                ['ICT-CHG', 'System Change Management', 'Authorized, tested, documented, and reversible application and infrastructure changes.'],
                ['ICT-SEC', 'Cybersecurity Operations', 'Vulnerability management, endpoint protection, incident response, and security monitoring.'],
                ['ICT-BCP', 'Backup and Disaster Recovery', 'Backup completeness, restoration testing, recovery objectives, and continuity readiness.'],
                ['ICT-PRIV', 'Data Privacy and Retention', 'Lawful processing, access, retention, disposal, and breach management for personal data.'],
            ],
        ],
        [
            'code' => 'ASSET',
            'name' => 'Property, Equipment and Fleet Management',
            'description' => 'Acquisition, recording, custody, maintenance, utilization, inventory, transfer, and disposal of city assets.',
            'responsible_office' => 'CGSO',
            'offices' => ['CGSO', 'CED', 'CENG', 'CPSO', 'RTA', 'CDRRMD', 'CHD', 'JRBGH'],
            'focuses' => [
                ['AST-REC', 'Asset Recording and Tagging', 'Complete property cards, asset tags, classification, valuation, and custodianship.'],
                ['AST-CUST', 'Custody and Accountability', 'Acknowledgement receipts, transfers, returns, and accountability monitoring.'],
                ['AST-MAINT', 'Preventive Maintenance', 'Maintenance plans, work orders, repair costs, downtime, and service histories.'],
                ['AST-UTIL', 'Utilization and Fuel', 'Deployment, trip authorization, fuel usage, mileage, and idle-asset monitoring.'],
                ['AST-COUNT', 'Physical Inventory', 'Periodic counts, reconciliation, investigation, and adjustment of discrepancies.'],
                ['AST-DISP', 'Disposal of Unserviceable Property', 'Inspection, authorization, valuation, bidding, disposal, and derecognition.'],
            ],
        ],
        [
            'code' => 'PROGRAM',
            'name' => 'Program and Service Delivery',
            'description' => 'Eligibility, delivery, performance, beneficiary records, outcomes, and reporting for public programs and frontline services.',
            'responsible_office' => 'CSWDD',
            'offices' => ['CHD', 'CHIO', 'JRBGH', 'CCCDO', 'CSO', 'CSWDD', 'CID', 'OYDO', 'CAO', 'PESO', 'CHUDD'],
            'focuses' => [
                ['PRG-PLAN', 'Program Planning and Targeting', 'Evidence-based design, target beneficiaries, resources, indicators, and timelines.'],
                ['PRG-ELIG', 'Beneficiary Eligibility', 'Documented, consistent, transparent, and privacy-conscious eligibility screening.'],
                ['PRG-DEL', 'Service Delivery Controls', 'Authorization, timeliness, completeness, quality, and acknowledgement of services.'],
                ['PRG-DATA', 'Beneficiary Records and Data Quality', 'Complete, accurate, deduplicated, secure, and traceable beneficiary records.'],
                ['PRG-PERF', 'Performance and Outcome Monitoring', 'Reliable accomplishment data, outcome measurement, variance analysis, and corrective action.'],
            ],
        ],
        [
            'code' => 'COMPLIANCE',
            'name' => 'Legal, Regulatory and Governance Compliance',
            'description' => 'Compliance with laws, ordinances, policies, delegations, contracts, records requirements, and governance standards.',
            'responsible_office' => 'CLO',
            'offices' => ['CLO', 'PLEB', 'CCRO', 'BPLD', 'OCBO', 'CLENRO', 'RTA', 'CVO', 'CIAS'],
            'focuses' => [
                ['CMP-LAW', 'Legal and Regulatory Compliance', 'Identification, implementation, monitoring, and evidence of applicable requirements.'],
                ['CMP-DEL', 'Delegation of Authority', 'Clear approval limits, authorized signatories, segregation of duties, and exception handling.'],
                ['CMP-CON', 'Contract Administration', 'Authorized terms, deliverables, amendments, performance monitoring, and closeout.'],
                ['CMP-REC', 'Records Management', 'Classification, filing, retention, retrieval, protection, and authorized disposal of records.'],
                ['CMP-ETH', 'Ethics and Conflict of Interest', 'Disclosure, recusal, gifts, complaints, and response to ethical concerns.'],
            ],
        ],
        [
            'code' => 'DRRM',
            'name' => 'Disaster Preparedness, Public Safety and Continuity',
            'description' => 'Hazard planning, emergency readiness, command, response, relief, recovery, safety, and continuity of city operations.',
            'responsible_office' => 'CDRRMD',
            'offices' => ['CDRRMD', 'CSU', 'CPSO', 'CHD', 'JRBGH', 'RTA', 'CENG', 'CLENRO'],
            'focuses' => [
                ['DRR-RISK', 'Risk Assessment and Preparedness', 'Hazard maps, risk registers, plans, drills, training, supplies, and public awareness.'],
                ['DRR-CMD', 'Emergency Command and Coordination', 'Incident command, communications, escalation, coordination, and decision logs.'],
                ['DRR-RESP', 'Response and Relief Operations', 'Deployment, beneficiary controls, distribution, medical response, and resource tracking.'],
                ['DRR-BCP', 'Business Continuity', 'Critical services, alternate arrangements, recovery priorities, and continuity exercises.'],
                ['DRR-REP', 'Post-Incident Reporting', 'Damage assessment, expenditure accountability, lessons learned, and corrective actions.'],
            ],
        ],
        [
            'code' => 'GOVERNANCE',
            'name' => 'Governance, Planning and Performance Management',
            'description' => 'Strategic alignment, risk management, internal controls, performance reporting, transparency, and corrective action.',
            'responsible_office' => 'CPDO',
            'offices' => ['*'],
            'focuses' => [
                ['GOV-PLAN', 'Strategic and Operational Planning', 'Mandate alignment, objectives, performance measures, targets, initiatives, and resources.'],
                ['GOV-RISK', 'Enterprise Risk Management', 'Risk identification, assessment, ownership, response, monitoring, and escalation.'],
                ['GOV-CTRL', 'Internal Control Environment', 'Accountability, segregation, documented procedures, supervision, and control self-assessment.'],
                ['GOV-PERF', 'Performance Reporting', 'Reliable accomplishments, variance analysis, management review, and corrective action.'],
                ['GOV-TRAN', 'Transparency and Citizen Accountability', 'Required disclosures, public information, complaints, feedback, and response monitoring.'],
            ],
        ],
    ];

    public function run(): void
    {
        $processTypeId = MasterListItem::query()
            ->where('code', 'PROCESS')
            ->whereHas('masterList', fn ($query) => $query->where('code', 'AUDIT_AREA_TYPE'))
            ->value('id');
        $allOfficeIds = Office::query()
            ->where('code', '!=', 'AGIS-SYS')
            ->pluck('id')
            ->all();

        foreach (self::AREAS as $areaData) {
            $area = AuditArea::withTrashed()->updateOrCreate(
                ['code' => $areaData['code']],
                [
                    'parent_audit_area_id' => null,
                    'audit_area_type_id' => $processTypeId,
                    'name' => $areaData['name'],
                    'description' => $areaData['description'],
                    'scope' => $areaData['description'],
                    'is_active' => true,
                ],
            );

            if ($area->trashed()) {
                $area->restore();
            }

            $officeIds = $areaData['offices'] === ['*']
                ? $allOfficeIds
                : Office::query()->whereIn('code', $areaData['offices'])->pluck('id')->all();
            $area->offices()->sync($officeIds);
            $area->forceFill([
                'responsible_office_id' => Office::query()
                    ->where('code', $areaData['responsible_office'])
                    ->value('id') ?? collect($officeIds)->first(),
            ])->save();

            foreach ($areaData['focuses'] as $index => [$code, $name, $description]) {
                $focus = AuditFocus::withTrashed()->updateOrCreate(
                    ['audit_area_id' => $area->id, 'code' => $code],
                    [
                        'name' => $name,
                        'description' => $description,
                        'display_order' => $index + 1,
                        'is_active' => true,
                    ],
                );

                if ($focus->trashed()) {
                    $focus->restore();
                }
            }
        }
    }
}
