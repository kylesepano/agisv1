<?php

namespace Tests\Feature\Api;

use App\Models\AemsEvidenceAssessment;
use App\Models\AemsEvidenceRequest;
use App\Models\AuditEvidence;
use App\Models\DocumentVersion;
use App\Services\AemsEvidenceRequestService;
use Tests\TestCase;

class AemsG5EvidenceLifecycleTest extends TestCase
{
    public function test_request_contract_contains_full_control_states(): void
    {
        $this->assertContains('ACKNOWLEDGED', AemsEvidenceRequest::STATUSES);
        $this->assertContains('EXTENSION_REQUESTED', AemsEvidenceRequest::STATUSES);
        $this->assertContains('OVERDUE', AemsEvidenceRequest::STATUSES);
        $this->assertContains('ESCALATED', AemsEvidenceRequest::STATUSES);
        $this->assertContains('CLOSED_WITHOUT_SUBMISSION', AemsEvidenceRequest::STATUSES);
        $this->assertContains('ACCEPTED', AuditEvidence::OUTCOMES);
        $this->assertContains('ADDITIONAL_REQUIRED', AuditEvidence::OUTCOMES);
        $this->assertContains('SUPERSEDED', AuditEvidence::OUTCOMES);
    }

    public function test_negative_or_incomplete_evidence_is_not_eligible_for_reporting(): void
    {
        $version = (new DocumentVersion())->forceFill(['id' => 10]);
        $evidence = new AuditEvidence([
            'status' => 'VERIFIED', 'outcome' => 'ADDITIONAL_REQUIRED',
            'is_current_revision' => true, 'document_version_id' => 10,
        ]);
        $evidence->setRelation('documentVersion', $version);
        $assessment = $this->assessment($evidence, $version, ['sufficiency' => 'NO', 'evidence_gaps' => 'Missing signed source.']);

        $result = app(AemsEvidenceRequestService::class)->evidenceEligibility($assessment);

        $this->assertFalse($result['eligible']);
        $this->assertNotEmpty($result['reasons']);
    }

    public function test_positive_assessed_evidence_with_accepted_outcome_is_eligible(): void
    {
        $version = (new DocumentVersion())->forceFill(['id' => 11]);
        $evidence = new AuditEvidence([
            'status' => 'LOCKED', 'outcome' => 'ACCEPTED',
            'is_current_revision' => true, 'document_version_id' => 11,
        ]);
        $evidence->setRelation('documentVersion', $version);
        $assessment = $this->assessment($evidence, $version);

        $result = app(AemsEvidenceRequestService::class)->evidenceEligibility($assessment);

        $this->assertTrue($result['eligible'], json_encode($result));
        $this->assertSame([], $result['reasons']);
    }

    public function test_assessment_citing_a_different_core_version_is_rejected(): void
    {
        $current = (new DocumentVersion())->forceFill(['id' => 12]);
        $cited = (new DocumentVersion())->forceFill(['id' => 13]);
        $evidence = new AuditEvidence([
            'status' => 'VERIFIED', 'outcome' => 'ACCEPTED',
            'is_current_revision' => true, 'document_version_id' => 12,
        ]);
        $evidence->setRelation('documentVersion', $current);
        $assessment = $this->assessment($evidence, $cited);

        $result = app(AemsEvidenceRequestService::class)->evidenceEligibility($assessment);

        $this->assertFalse($result['eligible']);
        $this->assertTrue(collect($result['reasons'])->contains(fn (string $reason): bool => str_contains($reason, 'exact current Core Document Version')));
    }

    private function assessment(AuditEvidence $evidence, DocumentVersion $version, array $overrides = []): AemsEvidenceAssessment
    {
        $assessment = new AemsEvidenceAssessment(array_merge([
            'status' => 'ASSESSED', 'is_current_revision' => true,
            'document_version_id' => $version->id, 'confidentiality' => 'INTERNAL',
            'sufficiency' => 'YES', 'appropriateness' => 'YES', 'relevance' => 'YES',
            'reliability' => 'HIGH', 'competence' => 'HIGH', 'accuracy' => 'YES',
            'completeness' => 'YES', 'corroboration' => 'YES', 'contradiction' => 'NO',
            'authenticity' => 'YES', 'integrity' => 'YES', 'is_restricted' => false,
        ], $overrides));
        $assessment->setRelation('evidence', $evidence);
        $assessment->setRelation('documentVersion', $version);

        return $assessment;
    }
}
