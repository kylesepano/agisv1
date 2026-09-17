<?php

namespace Database\Seeders;

use App\Models\AuditArea;
use App\Models\IapAuditUniverseHistory;
use App\Models\IapAuditUniverseItem;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds representative auditable subjects linked to Core registry data.
 */
class AuditUniverseSeeder extends Seeder
{
    /** @var list<array<string, mixed>> */
    private const SUBJECTS = [
        [
            'code' => 'AU-REV-001',
            'name' => 'Business Tax Assessment and Collection Process',
            'type' => 'PROCESS',
            'area' => 'REVENUE',
            'office' => 'CTO',
            'materiality' => 'CRITICAL',
            'last_audit' => '2023-08-18',
            'description' => 'Assessment, billing, collection, recording, reconciliation, and enforcement of local business taxes.',
            'scope' => 'Business tax registration, assessment, payment channels, collection records, delinquencies, and revenue reconciliation.',
            'exposure' => 'A major locally generated revenue stream with direct effect on fiscal sustainability and taxpayer confidence.',
        ],
        [
            'code' => 'AU-PROC-001',
            'name' => 'Citywide Procurement Planning and Competitive Selection',
            'type' => 'CROSS_CUTTING',
            'area' => 'PROCUREMENT',
            'office' => 'BAC',
            'materiality' => 'CRITICAL',
            'last_audit' => '2024-03-22',
            'description' => 'Annual procurement planning, competitive selection, quotation, evaluation, award, and contracting across city offices.',
            'scope' => 'APP preparation, bidding and alternative modes, eligibility, evaluation, award, notices, contracts, and procurement reporting.',
            'exposure' => 'Citywide expenditure, legal-compliance, integrity, supplier, service-delivery, and reputational exposure.',
        ],
        [
            'code' => 'AU-ICT-001',
            'name' => 'Identity, Access and Privileged Account Management',
            'type' => 'SYSTEM',
            'area' => 'ICT',
            'office' => 'CIO',
            'materiality' => 'HIGH',
            'last_audit' => null,
            'description' => 'Provisioning, modification, review, and removal of access to city information systems and data.',
            'scope' => 'User lifecycle, privileged access, authentication, segregation of duties, access reviews, logs, and account termination.',
            'exposure' => 'Unauthorized access may affect financial records, citizen information, service continuity, privacy, and accountability.',
        ],
        [
            'code' => 'AU-HR-001',
            'name' => 'Payroll Preparation and Employee Compensation',
            'type' => 'PROCESS',
            'area' => 'HR',
            'office' => 'HRMO',
            'materiality' => 'HIGH',
            'last_audit' => '2022-11-10',
            'description' => 'Personnel master data, attendance inputs, payroll calculation, deductions, approvals, and payment.',
            'scope' => 'Permanent, temporary, casual, contractual, job-order, and contract-of-service personnel payroll controls.',
            'exposure' => 'Recurring personnel expenditure with risk of inaccurate, duplicate, unauthorized, or unsupported payments.',
        ],
        [
            'code' => 'AU-FIN-001',
            'name' => 'Cash Receipts, Deposits and Bank Reconciliation',
            'type' => 'PROCESS',
            'area' => 'FINANCIAL',
            'office' => 'CTO',
            'materiality' => 'CRITICAL',
            'last_audit' => '2023-08-18',
            'description' => 'Collection, acknowledgement, safeguarding, deposit, recording, and reconciliation of city cash receipts.',
            'scope' => 'Collecting officers, official receipts, deposit timeliness, bank records, subsidiary ledgers, and reconciliations.',
            'exposure' => 'High-volume cash and electronic receipts susceptible to loss, delay, recording error, and fraud.',
        ],
        [
            'code' => 'AU-PROG-001',
            'name' => 'Social Assistance Beneficiary Management',
            'type' => 'PROGRAM',
            'area' => 'PROGRAM',
            'office' => 'CSWDO',
            'materiality' => 'HIGH',
            'last_audit' => '2021-06-30',
            'description' => 'Eligibility, validation, approval, assistance delivery, beneficiary records, and outcome monitoring.',
            'scope' => 'Beneficiary intake, eligibility evidence, approvals, distribution, duplicate detection, complaints, and reporting.',
            'exposure' => 'Direct impact on vulnerable citizens and risk of exclusion, duplication, leakage, privacy incidents, and unsupported assistance.',
        ],
        [
            'code' => 'AU-DRRM-001',
            'name' => 'Emergency Preparedness and Disaster Response Readiness',
            'type' => 'SERVICE',
            'area' => 'DRRM',
            'office' => 'CDRRMD',
            'materiality' => 'CRITICAL',
            'last_audit' => null,
            'description' => 'Preparedness planning, command, logistics, emergency operations, response coordination, and recovery readiness.',
            'scope' => 'Plans, operations center, warning systems, equipment, supplies, exercises, incident command, response, and after-action reporting.',
            'exposure' => 'Life, safety, service continuity, emergency spending, asset readiness, and public-confidence exposure.',
        ],
        [
            'code' => 'AU-ASSET-001',
            'name' => 'Property, Plant and Equipment Accountability',
            'type' => 'ASSET_FACILITY',
            'area' => 'PROCUREMENT',
            'office' => 'CGSO',
            'materiality' => 'HIGH',
            'last_audit' => '2022-04-15',
            'description' => 'Receipt, recording, tagging, assignment, physical count, transfer, maintenance, and disposal of city property.',
            'scope' => 'Property cards, inventory reports, accountability documents, tagging, physical verification, losses, transfers, and disposal.',
            'exposure' => 'Material city assets distributed across many accountable offices and operational locations.',
        ],
        [
            'code' => 'AU-COMP-001',
            'name' => 'Business Permit and Licensing Compliance',
            'type' => 'SERVICE',
            'area' => 'COMPLIANCE',
            'office' => 'BPLD',
            'materiality' => 'HIGH',
            'last_audit' => '2024-09-12',
            'description' => 'Application, verification, assessment, approval, issuance, renewal, monitoring, and enforcement of business permits.',
            'scope' => 'Requirements, clearances, assessment, approvals, system controls, processing time, issuance, renewal, and enforcement.',
            'exposure' => 'Revenue, regulatory, public-safety, ease-of-doing-business, data-integrity, and reputational exposure.',
        ],
        [
            'code' => 'AU-GOV-001',
            'name' => 'Annual Investment Programming and Performance Monitoring',
            'type' => 'PROCESS',
            'area' => 'GOVERNANCE',
            'office' => 'CPDO',
            'materiality' => 'HIGH',
            'last_audit' => '2021-12-17',
            'description' => 'Strategic alignment, project prioritization, investment programming, target setting, and performance monitoring.',
            'scope' => 'Development plans, annual investment program, project proposals, prioritization criteria, targets, monitoring, and reporting.',
            'exposure' => 'Citywide resource allocation and achievement of development outcomes depend on reliable planning and performance information.',
        ],
        [
            'code' => 'AU-HEALTH-001',
            'name' => 'Hospital Pharmacy and Medical Supply Management',
            'type' => 'SERVICE',
            'area' => 'PROCUREMENT',
            'office' => 'JRBGH',
            'materiality' => 'CRITICAL',
            'last_audit' => null,
            'description' => 'Forecasting, procurement, receipt, storage, dispensing, inventory control, and expiry management of medicines and medical supplies.',
            'scope' => 'Formulary demand, purchasing, cold chain, controlled medicines, stock records, dispensing, losses, expiries, and physical counts.',
            'exposure' => 'Patient safety, service continuity, high-value inventory, regulatory, wastage, theft, and emergency-readiness exposure.',
        ],
        [
            'code' => 'AU-CONTRACT-001',
            'name' => 'Infrastructure Contract Administration and Progress Billing',
            'type' => 'CONTRACT',
            'area' => 'PROCUREMENT',
            'office' => 'CENG',
            'materiality' => 'CRITICAL',
            'last_audit' => '2023-02-24',
            'description' => 'Contract implementation, inspection, variation orders, progress measurement, billing, retention, acceptance, and warranty monitoring.',
            'scope' => 'Approved plans, contracts, work program, progress reports, inspection, variation orders, billings, completion, and defects liability.',
            'exposure' => 'High-value capital expenditure, public safety, quality, delay, overpayment, variation, and contractor-performance exposure.',
        ],
    ];

    public function run(): void
    {
        $actor = User::query()->where('username', 'departmenthead')->first();

        foreach (self::SUBJECTS as $index => $data) {
            $area = AuditArea::query()->where('code', $data['area'])->firstOrFail();
            $office = Office::query()
                ->where('code', $data['office'])
                ->whereHas('auditAreas', fn ($query) => $query->whereKey($area->id))
                ->first()
                ?? $area->offices()->orderBy('name')->firstOrFail();

            $item = IapAuditUniverseItem::withTrashed()->updateOrCreate(
                ['subject_code' => $data['code']],
                [
                    'name' => $data['name'],
                    'subject_type_id' => $this->item('IAP_AUDIT_UNIVERSE_SUBJECT_TYPE', $data['type'])->id,
                    'responsible_office_id' => $office->id,
                    'primary_audit_area_id' => $area->id,
                    'materiality_level_id' => $this->item('RISK_LEVEL', $data['materiality'])->id,
                    'description' => $data['description'],
                    'audit_scope' => $data['scope'],
                    'materiality_exposure' => $data['exposure'],
                    'last_audit_date' => $data['last_audit'],
                    'historical_audit_summary' => $data['last_audit']
                        ? 'Prior assurance work is available in the historical audit record.'
                        : 'No reliable completed internal audit record is currently linked.',
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                    'is_active' => true,
                ],
            );
            if ($item->trashed()) {
                $item->restore();
            }

            $stakeholders = $area->offices()
                ->where('offices.id', '<>', $office->id)
                ->orderBy('offices.name')
                ->limit(2)
                ->pluck('offices.id');
            $item->stakeholderOffices()->sync($stakeholders);

            if ($data['last_audit']) {
                IapAuditUniverseHistory::withTrashed()->updateOrCreate(
                    [
                        'audit_universe_item_id' => $item->id,
                        'engagement_reference' => sprintf('AEMS-%d-%03d', (int) substr($data['last_audit'], 0, 4), $index + 1),
                    ],
                    [
                        'audited_on' => $data['last_audit'],
                        'title' => $data['name'].' Audit',
                        'outcome' => $index % 2 === 0
                            ? 'Recommendations issued'
                            : 'Controls require improvement',
                        'report_reference' => sprintf('CIAS-%d-%03d', (int) substr($data['last_audit'], 0, 4), $index + 1),
                        'notes' => 'Seeded historical audit reference for planning and demonstration.',
                        'recorded_by' => $actor?->id,
                    ],
                );
            }
        }
    }

    private function item(string $listCode, string $itemCode): MasterListItem
    {
        return MasterListItem::query()
            ->where('code', $itemCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', $listCode))
            ->firstOrFail();
    }
}
