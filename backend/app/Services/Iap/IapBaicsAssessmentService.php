<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsComponent;
use App\Models\IapBaicsComponentVersion;
use App\Models\IapBaicsEvidenceLink;
use App\Models\IapBaicsException;
use App\Models\IapBaicsExceptionVersion;
use App\Models\IapBaicsMethod;
use App\Models\IapBaicsMethodVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** BAICS-2 internal-control components, corroboration, evidence, and exceptions. */
class IapBaicsAssessmentService
{
    public function __construct(private readonly IapSupport $support, private readonly NotificationService $notifications, private readonly DocumentAccessService $documentAccess) {}

    public function initializeComponents(IapBaicsAssessment $assessment, User $actor): void
    {
        foreach (IapBaicsComponent::CODES as $code) {
            $component = $assessment->components()->firstOrCreate(['component_code' => $code], ['status' => 'DRAFT', 'version_number' => 1, 'lock_version' => 1]);
            if ($component->wasRecentlyCreated) $this->componentSnapshot($component, $actor, 'Component initialized');
        }
    }

    public function cloneComponents(IapBaicsAssessment $source, IapBaicsAssessment $target, User $actor): void
    {
        $source->load(['components.methods.evidenceLinks', 'components.exceptions']);
        if ($source->components->isEmpty()) { $this->initializeComponents($target, $actor); return; }
        foreach ($source->components as $oldComponent) {
            $component = $target->components()->create(['component_code' => $oldComponent->component_code, 'status' => 'DRAFT', 'conclusion' => $oldComponent->conclusion, 'supporting_summary' => $oldComponent->supporting_summary, 'limitations' => $oldComponent->limitations, 'assessor_id' => $oldComponent->assessor_id, 'reviewer_id' => $oldComponent->reviewer_id, 'version_number' => 1, 'lock_version' => 1]);
            foreach ($oldComponent->methods as $oldMethod) {
                $method = $component->methods()->create(['family_uuid' => $oldMethod->family_uuid, 'version_number' => $oldMethod->version_number + 1, 'method_type' => $oldMethod->method_type, 'title' => $oldMethod->title, 'description' => $oldMethod->description, 'performed_by' => $oldMethod->performed_by, 'office_id' => $oldMethod->office_id, 'process_reference' => $oldMethod->process_reference, 'performed_on' => $oldMethod->performed_on, 'procedure' => $oldMethod->procedure, 'result' => $oldMethod->result, 'limitations' => $oldMethod->limitations, 'reviewer_id' => $oldMethod->reviewer_id, 'status' => 'DRAFT', 'is_current_revision' => true, 'lock_version' => 1]);
                foreach ($oldMethod->evidenceLinks as $link) $method->evidenceLinks()->create(['component_id' => $component->id, 'document_version_id' => $link->document_version_id, 'evidence_role' => $link->evidence_role, 'description' => $link->description, 'created_by' => $actor->id]);
                $this->methodSnapshot($method, $actor, 'Copied into cycle revision');
            }
            foreach ($oldComponent->evidenceLinks()->whereNull('method_id')->get() as $link) $component->evidenceLinks()->create(['document_version_id' => $link->document_version_id, 'evidence_role' => $link->evidence_role, 'description' => $link->description, 'created_by' => $actor->id]);
            foreach ($oldComponent->exceptions as $oldException) {
                $exception = $target->exceptions()->create(['component_id' => $component->id, 'reason' => $oldException->reason, 'authority_user_id' => $oldException->authority_user_id, 'compensating_evidence' => $oldException->compensating_evidence, 'expiry_date' => $oldException->expiry_date, 'status' => 'DRAFT', 'created_by' => $actor->id, 'version_number' => 1, 'lock_version' => 1]);
                $this->exceptionSnapshot($exception, $actor, 'Copied into cycle revision');
            }
            $this->componentSnapshot($component, $actor, 'Copied into cycle revision');
        }
    }

    public function loadComponent(IapBaicsComponent $component): IapBaicsComponent
    {
        return $component->load(['assessor:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position', 'methods.performer:id,employee_id,name,initials,position', 'methods.office:id,code,name', 'methods.reviewer:id,employee_id,name,initials,position', 'methods.evidenceLinks.documentVersion.document', 'evidenceLinks.documentVersion.document', 'exceptions.authority:id,employee_id,name,initials,position', 'exceptions.creator:id,employee_id,name,initials,position', 'exceptions.reviewer:id,employee_id,name,initials,position', 'exceptions.approver:id,employee_id,name,initials,position', 'versions.creator:id,employee_id,name,initials', 'methods.versions.creator:id,employee_id,name,initials'])->setAttribute('component_readiness', $this->componentReadiness($component));
    }

    public function readiness(IapBaicsAssessment $assessment): array
    {
        $assessment->loadMissing('components');
        $components = $assessment->components->keyBy('component_code');
        $items = [];
        foreach (IapBaicsComponent::CODES as $code) {
            $component = $components->get($code);
            $items[$code] = $component ? $this->componentReadiness($component) + ['status' => $component->status, 'id' => $component->id] : ['ready' => false, 'status' => null, 'id' => null, 'checks' => ['componentPresent' => false], 'methodCount' => 0, 'corroboratingMethodCount' => 0];
        }
        return ['ready' => collect($items)->every(fn (array $item): bool => $item['ready']), 'requiredComponents' => count(IapBaicsComponent::CODES), 'components' => $items];
    }

    public function assertReady(IapBaicsAssessment $assessment, bool $requireApprovedComponents = false): void
    {
        $readiness = $this->readiness($assessment);
        if (! $readiness['ready']) throw ValidationException::withMessages(['components' => ['Every BAICS control component must have a conclusion, independent corroborating methods, exact evidence, and no unresolved exception before this cycle can proceed.']]);
        if ($requireApprovedComponents) {
            $notApproved = collect($readiness['components'])->filter(fn (array $item): bool => $item['status'] !== 'APPROVED')->keys()->values()->all();
            if ($notApproved !== []) throw ValidationException::withMessages(['components' => ['All five control components must be independently approved before the BAICS cycle can be approved.', 'Pending components: '.implode(', ', $notApproved).'.']]);
        }
    }

    public function saveComponent(Request $request, IapBaicsComponent $component, array $data): IapBaicsComponent
    {
        $this->assertEditable($component);
        $this->assertLock($component->lock_version, (int) $data['lockVersion']);
        $assessorId = (int) ($data['assessorId'] ?? $component->assessor_id ?? $request->user()->id);
        if ((int) $data['reviewerId'] === $assessorId) throw ValidationException::withMessages(['reviewerId' => ['The component reviewer must be independent of the assessor.']]);
        $component->forceFill(['conclusion' => $data['conclusion'], 'supporting_summary' => $data['supportingSummary'] ?? null, 'limitations' => $data['limitations'] ?? null, 'assessor_id' => $assessorId, 'reviewer_id' => $data['reviewerId'], 'lock_version' => $component->lock_version + 1])->save();
        $this->componentSnapshot($component, $request->user(), 'Component draft saved');
        $this->support->audit($request, 'iap.baics.component.updated', $component, null, $this->componentValues($component));
        return $this->loadComponent($component->fresh());
    }

    public function transitionComponent(Request $request, IapBaicsComponent $component, string $action, ?string $comment = null): IapBaicsComponent
    {
        $action = strtoupper($action);
        $map = ['SUBMIT' => [['DRAFT', 'RETURNED'], 'PENDING_REVIEW'], 'RETURN' => [['PENDING_REVIEW'], 'RETURNED'], 'APPROVE' => [['PENDING_REVIEW'], 'APPROVED']];
        if (! isset($map[$action])) abort(404);
        $this->assertRelation($component);
        $this->assertLock($component->lock_version, (int) $request->input('lockVersion'));
        if (! in_array($component->status, $map[$action][0], true)) throw ValidationException::withMessages(['status' => ["{$action} is not available while this component is {$component->status}."]]);
        if ($action === 'RETURN' && trim((string) $comment) === '') throw ValidationException::withMessages(['comment' => ['A return reason is required.']]);
        if ($action === 'APPROVE') {
            $this->assertComponentReady($component);
            if ((int) $request->user()->id === (int) $component->assessor_id) throw ValidationException::withMessages(['approver' => ['The component assessor cannot approve the same component.']]);
            if (! $component->reviewer_id || (int) $request->user()->id !== (int) $component->reviewer_id) throw ValidationException::withMessages(['reviewer' => ['The assigned independent reviewer must approve this component.']]);
            $component->loadMissing('assessment');
            if ($this->isResponsibleOfficeRespondent($component->assessment, $request->user())) throw ValidationException::withMessages(['approver' => ['A responsible-office respondent cannot approve the final control assessment.']]);
        }
        $old = $component->status;
        $attributes = ['status' => $map[$action][1], 'lock_version' => $component->lock_version + 1];
        if ($action === 'RETURN') $attributes['reviewed_at'] = now();
        if ($action === 'APPROVE') $attributes += ['approved_by' => $request->user()->id, 'approved_at' => now(), 'immutable_at' => now(), 'version_number' => $component->version_number + 1];
        $component->forceFill($attributes)->save();
        $this->componentSnapshot($component, $request->user(), $comment ?: $action);
        $this->support->audit($request, 'iap.baics.component.'.strtolower($action), $component, ['status' => $old], ['status' => $component->status], ['comment' => $comment]);
        $this->notify($component->assessment, $request->user(), 'BAICS_COMPONENT_WORKFLOW', "{$component->component_code} {$action}", "{$component->component_code} moved to {$component->status}.");
        return $this->loadComponent($component->fresh());
    }

    public function storeMethod(Request $request, IapBaicsComponent $component, array $data): IapBaicsMethod
    {
        $this->assertComponentEditable($component);
        if ((int) $data['reviewerId'] === (int) $data['performedBy']) throw ValidationException::withMessages(['reviewerId' => ['The method reviewer must be independent of the performer.']]);
        $this->assertOffice($request->user(), $data['officeId'] ?? null);
        $method = $component->methods()->create(['family_uuid' => (string) Str::uuid(), 'version_number' => 1, 'method_type' => $data['methodType'], 'title' => $data['title'], 'description' => $data['description'] ?? null, 'performed_by' => $data['performedBy'], 'office_id' => $data['officeId'] ?? null, 'process_reference' => $data['processReference'] ?? null, 'performed_on' => $data['performedOn'], 'procedure' => $data['procedure'], 'result' => $data['result'], 'limitations' => $data['limitations'] ?? null, 'reviewer_id' => $data['reviewerId'], 'status' => 'DRAFT', 'is_current_revision' => true, 'lock_version' => 1]);
        $this->methodSnapshot($method, $request->user(), 'Method created');
        $this->support->audit($request, 'iap.baics.method.created', $method, null, $this->methodValues($method));
        return $method->load(['performer:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'evidenceLinks.documentVersion']);
    }

    public function updateMethod(Request $request, IapBaicsMethod $method, array $data): IapBaicsMethod
    {
        $this->assertMethodEditable($method);
        $this->assertLock($method->lock_version, (int) $data['lockVersion']);
        if ((int) $data['reviewerId'] === (int) $data['performedBy']) throw ValidationException::withMessages(['reviewerId' => ['The method reviewer must be independent of the performer.']]);
        $this->assertOffice($request->user(), $data['officeId'] ?? null);
        $method->forceFill(['method_type' => $data['methodType'], 'title' => $data['title'], 'description' => $data['description'] ?? null, 'performed_by' => $data['performedBy'], 'office_id' => $data['officeId'] ?? null, 'process_reference' => $data['processReference'] ?? null, 'performed_on' => $data['performedOn'], 'procedure' => $data['procedure'], 'result' => $data['result'], 'limitations' => $data['limitations'] ?? null, 'reviewer_id' => $data['reviewerId'], 'lock_version' => $method->lock_version + 1])->save();
        $this->methodSnapshot($method, $request->user(), 'Method draft updated');
        $this->support->audit($request, 'iap.baics.method.updated', $method, null, $this->methodValues($method));
        return $method->fresh()->load(['performer:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'evidenceLinks.documentVersion']);
    }

    public function transitionMethod(Request $request, IapBaicsMethod $method, string $action, ?string $comment = null): IapBaicsMethod
    {
        $action = strtoupper($action);
        $map = ['SUBMIT' => [['DRAFT', 'RETURNED'], 'PENDING_REVIEW'], 'RETURN' => [['PENDING_REVIEW'], 'RETURNED'], 'APPROVE' => [['PENDING_REVIEW'], 'APPROVED']];
        if (! isset($map[$action])) abort(404);
        $this->assertComponentRelation($method);
        $this->assertLock($method->lock_version, (int) $request->input('lockVersion'));
        if (! in_array($method->status, $map[$action][0], true)) throw ValidationException::withMessages(['status' => ["{$action} is not available while this method is {$method->status}."]]);
        if ($action === 'RETURN' && trim((string) $comment) === '') throw ValidationException::withMessages(['comment' => ['A return reason is required.']]);
        if ($action === 'APPROVE') {
            if (! $method->reviewer_id || (int) $method->reviewer_id === (int) $method->performed_by || (int) $method->reviewer_id !== (int) $request->user()->id) throw ValidationException::withMessages(['reviewer' => ['The assigned independent reviewer must approve this method.']]);
            if ($method->evidenceLinks()->count() < 1) throw ValidationException::withMessages(['evidence' => ['Every approved assessment method must cite at least one exact Core Document Version.']]);
        }
        $old = $method->status;
        $method->forceFill(['status' => $map[$action][1], 'lock_version' => $method->lock_version + 1, ...($action === 'RETURN' ? ['reviewed_at' => now()] : []), ...($action === 'APPROVE' ? ['reviewed_at' => now(), 'immutable_at' => now()] : [])])->save();
        $this->methodSnapshot($method, $request->user(), $comment ?: $action);
        $this->support->audit($request, 'iap.baics.method.'.strtolower($action), $method, ['status' => $old], ['status' => $method->status], ['comment' => $comment]);
        $this->notify($method->component->assessment, $request->user(), 'BAICS_METHOD_WORKFLOW', "BAICS method {$action}", "{$method->title} moved to {$method->status}.");
        return $method->fresh()->load(['performer:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'evidenceLinks.documentVersion']);
    }

    public function linkEvidence(Request $request, IapBaicsComponent $component, array $data): IapBaicsEvidenceLink
    {
        $this->assertComponentEditable($component);
        $method = null;
        if (! empty($data['methodId'])) {
            $method = IapBaicsMethod::query()->whereKey($data['methodId'])->where('component_id', $component->id)->firstOrFail();
        }
        $version = DocumentVersion::query()->with('document')->findOrFail($data['documentVersionId']);
        if ($version->document) $this->documentAccess->authorizeView($request->user(), $version->document);
        $link = IapBaicsEvidenceLink::query()->firstOrCreate(['component_id' => $component->id, 'method_id' => $method?->id, 'document_version_id' => $version->id], ['evidence_role' => $data['evidenceRole'] ?? 'SUPPORTING', 'description' => $data['description'] ?? null, 'created_by' => $request->user()->id]);
        $this->support->audit($request, 'iap.baics.evidence.linked', $component, null, ['linkId' => $link->id, 'methodId' => $method?->id, 'documentVersionId' => $version->id, 'checksumSha256' => $version->checksum_sha256]);
        return $link->load('documentVersion.document');
    }

    public function removeEvidence(Request $request, IapBaicsComponent $component, IapBaicsEvidenceLink $link): void
    {
        $this->assertComponentEditable($component);
        if ((int) $link->component_id !== (int) $component->id) abort(404);
        $this->support->audit($request, 'iap.baics.evidence.unlinked', $component, ['linkId' => $link->id, 'documentVersionId' => $link->document_version_id], null);
        $link->delete();
    }

    public function storeException(Request $request, IapBaicsAssessment $assessment, array $data): IapBaicsException
    {
        $component = IapBaicsComponent::query()->whereKey($data['componentId'])->where('assessment_id', $assessment->id)->firstOrFail();
        $this->assertComponentEditable($component);
        $exception = $assessment->exceptions()->create(['component_id' => $component->id, 'reason' => $data['reason'], 'authority_user_id' => $data['authorityUserId'], 'compensating_evidence' => $data['compensatingEvidence'], 'expiry_date' => $data['expiryDate'], 'status' => 'DRAFT', 'created_by' => $request->user()->id, 'version_number' => 1, 'lock_version' => 1]);
        $this->exceptionSnapshot($exception, $request->user(), 'Exception created');
        $this->support->audit($request, 'iap.baics.exception.created', $exception, null, $this->exceptionValues($exception));
        return $exception->load(['authority:id,employee_id,name,initials,position', 'creator:id,employee_id,name,initials,position']);
    }

    public function updateException(Request $request, IapBaicsException $exception, array $data): IapBaicsException
    {
        $this->assertExceptionEditable($exception);
        $this->assertLock($exception->lock_version, (int) $data['lockVersion']);
        $exception->forceFill(['reason' => $data['reason'], 'authority_user_id' => $data['authorityUserId'], 'compensating_evidence' => $data['compensatingEvidence'], 'expiry_date' => $data['expiryDate'], 'lock_version' => $exception->lock_version + 1])->save();
        $this->exceptionSnapshot($exception, $request->user(), 'Exception draft updated');
        $this->support->audit($request, 'iap.baics.exception.updated', $exception, null, $this->exceptionValues($exception));
        return $exception->fresh()->load(['authority:id,employee_id,name,initials,position', 'creator:id,employee_id,name,initials,position']);
    }

    public function transitionException(Request $request, IapBaicsException $exception, string $action, ?string $comment = null): IapBaicsException
    {
        $action = strtoupper($action);
        $map = ['SUBMIT' => [['DRAFT', 'RETURNED'], 'PENDING_REVIEW'], 'RETURN' => [['PENDING_REVIEW'], 'RETURNED'], 'APPROVE' => [['PENDING_REVIEW'], 'APPROVED'], 'REJECT' => [['PENDING_REVIEW'], 'REJECTED']];
        if (! isset($map[$action])) abort(404);
        $this->assertRelation($exception);
        $this->assertLock($exception->lock_version, (int) $request->input('lockVersion'));
        if (! in_array($exception->status, $map[$action][0], true)) throw ValidationException::withMessages(['status' => ["{$action} is not available while this exception is {$exception->status}."]]);
        if (in_array($action, ['RETURN', 'REJECT'], true) && trim((string) $comment) === '') throw ValidationException::withMessages(['comment' => ['A reason is required.']]);
        if ($action === 'APPROVE') {
            if ((int) $request->user()->id === (int) $exception->created_by || (int) $request->user()->id === (int) $exception->component->assessor_id) throw ValidationException::withMessages(['approver' => ['The exception accepter cannot be the beneficiary or component assessor.']]);
            if ((int) $request->user()->id !== (int) $exception->authority_user_id) throw ValidationException::withMessages(['authorityUserId' => ['The designated exception authority must approve the exception.']]);
        }
        $old = $exception->status;
        $exception->forceFill(['status' => $map[$action][1], 'lock_version' => $exception->lock_version + 1, ...($action === 'RETURN' || $action === 'REJECT' ? ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()] : []), ...($action === 'APPROVE' ? ['approved_by' => $request->user()->id, 'approved_at' => now(), 'immutable_at' => now(), 'version_number' => $exception->version_number + 1] : [])])->save();
        $this->exceptionSnapshot($exception, $request->user(), $comment ?: $action);
        $this->support->audit($request, 'iap.baics.exception.'.strtolower($action), $exception, ['status' => $old], ['status' => $exception->status], ['comment' => $comment]);
        $this->notify($exception->assessment, $request->user(), 'BAICS_EXCEPTION_WORKFLOW', "BAICS exception {$action}", "A corroboration exception moved to {$exception->status}.");
        return $exception->fresh()->load(['authority:id,employee_id,name,initials,position', 'creator:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position']);
    }

    public function componentReadiness(IapBaicsComponent $component): array
    {
        $component->loadMissing(['methods.evidenceLinks', 'evidenceLinks', 'exceptions']);
        $methods = $component->methods;
        $approvedMethods = $methods->where('status', 'APPROVED');
        $corroborating = $approvedMethods->filter(fn (IapBaicsMethod $method): bool => filled($method->method_type) && $method->performed_by && $method->evidenceLinks->isNotEmpty())->unique(fn (IapBaicsMethod $method): string => $method->method_type.'|'.$method->performed_by);
        $validException = $component->exceptions->first(fn (IapBaicsException $exception): bool => $exception->status === 'APPROVED' && $exception->expiry_date?->isFuture());
        $openException = $component->exceptions->contains(fn (IapBaicsException $exception): bool => in_array($exception->status, ['DRAFT', 'PENDING_REVIEW', 'RETURNED'], true));
        $checks = ['conclusion' => filled($component->conclusion), 'assessor' => (bool) $component->assessor_id, 'independentReviewer' => (bool) $component->reviewer_id && (int) $component->reviewer_id !== (int) $component->assessor_id, 'methods' => $corroborating->count() >= 3 || $validException !== null, 'methodsApproved' => $methods->isNotEmpty() && $approvedMethods->count() === $methods->count(), 'methodEvidence' => $methods->isNotEmpty() && $methods->every(fn (IapBaicsMethod $method): bool => $method->evidenceLinks->isNotEmpty()), 'supportingEvidence' => $component->evidenceLinks->isNotEmpty() || $methods->some(fn (IapBaicsMethod $method): bool => $method->evidenceLinks->isNotEmpty()), 'noOpenException' => ! $openException];
        return ['ready' => ! in_array(false, $checks, true), 'checks' => $checks, 'methodCount' => $methods->count(), 'corroboratingMethodCount' => $corroborating->count(), 'exceptionId' => $validException?->id];
    }

    private function assertComponentReady(IapBaicsComponent $component): void { $readiness = $this->componentReadiness($component); if (! $readiness['ready']) throw ValidationException::withMessages(['readiness' => ['The component is not ready for approval. Complete all methods, exact evidence, independent review, and any approved exception.', 'Failed checks: '.implode(', ', array_keys(array_filter($readiness['checks'], fn ($value): bool => ! $value))).'.']]); }
    private function assertEditable(IapBaicsComponent $component): void { $component->loadMissing('assessment'); if (! in_array($component->assessment->status, ['DRAFT', 'PLANNING', 'IN_PROGRESS', 'RETURNED', 'RESUBMITTED'], true) || $component->immutable_at || ! in_array($component->status, ['DRAFT', 'RETURNED'], true)) throw ValidationException::withMessages(['status' => ['Only draft or returned control components can be changed. Approved or submitted components are locked until returned or revised.']]); }
    private function assertComponentEditable(IapBaicsComponent $component): void { $this->assertEditable($component); }
    private function assertMethodEditable(IapBaicsMethod $method): void { $method->loadMissing('component'); $this->assertComponentEditable($method->component); if ($method->immutable_at || ! in_array($method->status, ['DRAFT', 'RETURNED'], true)) throw ValidationException::withMessages(['status' => ['Only draft or returned assessment methods can be changed.']]); }
    private function assertExceptionEditable(IapBaicsException $exception): void { $exception->loadMissing('component'); $this->assertComponentEditable($exception->component); if ($exception->immutable_at || ! in_array($exception->status, ['DRAFT', 'RETURNED'], true)) throw ValidationException::withMessages(['status' => ['Only draft or returned corroboration exceptions can be changed.']]); }
    private function assertRelation(Model $record): void { if (! $record->exists) abort(404); }
    private function assertComponentRelation(IapBaicsMethod $method): void { $method->loadMissing('component.assessment'); if (! $method->component || ! $method->component->assessment) abort(404); }
    private function assertLock(int $current, int $provided): void { if ($current !== $provided) throw ValidationException::withMessages(['lockVersion' => ['This BAICS record changed. Refresh before continuing.']]); }
    private function isResponsibleOfficeRespondent(IapBaicsAssessment $assessment, User $user): bool { return (int) $assessment->responsible_office_id === (int) $user->office_id && $assessment->assignments()->where('user_id', $user->id)->where('role_code', 'RESPONDENT')->where('status', 'ASSIGNED')->exists(); }
    private function assertOffice(User $user, mixed $officeId): void { if ($officeId !== null && ! $user->hasGlobalOfficeAccess() && (int) $user->office_id !== (int) $officeId) throw ValidationException::withMessages(['officeId' => ['The method office is outside your office scope.']]); }
    private function componentValues(IapBaicsComponent $component): array { return ['id' => $component->id, 'componentCode' => $component->component_code, 'status' => $component->status, 'conclusion' => $component->conclusion, 'assessorId' => $component->assessor_id, 'reviewerId' => $component->reviewer_id, 'versionNumber' => $component->version_number, 'lockVersion' => $component->lock_version]; }
    private function methodValues(IapBaicsMethod $method): array { return ['id' => $method->id, 'componentId' => $method->component_id, 'methodType' => $method->method_type, 'status' => $method->status, 'performedBy' => $method->performed_by, 'officeId' => $method->office_id, 'processReference' => $method->process_reference, 'reviewerId' => $method->reviewer_id, 'versionNumber' => $method->version_number]; }
    private function exceptionValues(IapBaicsException $exception): array { return ['id' => $exception->id, 'componentId' => $exception->component_id, 'status' => $exception->status, 'authorityUserId' => $exception->authority_user_id, 'expiryDate' => $exception->expiry_date?->toDateString(), 'versionNumber' => $exception->version_number]; }
    private function componentSnapshot(IapBaicsComponent $component, User $actor, string $reason): void { $snapshot = [...$this->componentValues($component), 'methods' => $component->methods()->get()->map(fn ($method) => $this->methodValues($method))->all(), 'evidenceIds' => $component->evidenceLinks()->pluck('document_version_id')->all()]; IapBaicsComponentVersion::query()->create(['component_id' => $component->id, 'component_code' => $component->component_code, 'version_number' => $component->version_number, 'status' => $component->status, 'snapshot' => $snapshot, 'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'reason' => $reason, 'created_by' => $actor->id]); }
    private function methodSnapshot(IapBaicsMethod $method, User $actor, string $reason): void { $snapshot = $this->methodValues($method) + ['title' => $method->title, 'description' => $method->description, 'procedure' => $method->procedure, 'result' => $method->result, 'limitations' => $method->limitations, 'evidenceIds' => $method->evidenceLinks()->pluck('document_version_id')->all()]; IapBaicsMethodVersion::query()->create(['method_id' => $method->id, 'family_uuid' => $method->family_uuid, 'version_number' => $method->version_number, 'status' => $method->status, 'snapshot' => $snapshot, 'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'reason' => $reason, 'created_by' => $actor->id]); }
    private function exceptionSnapshot(IapBaicsException $exception, User $actor, string $reason): void { $snapshot = $this->exceptionValues($exception) + ['reason' => $exception->reason, 'compensatingEvidence' => $exception->compensating_evidence]; IapBaicsExceptionVersion::query()->create(['exception_id' => $exception->id, 'version_number' => $exception->version_number, 'status' => $exception->status, 'snapshot' => $snapshot, 'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'reason' => $reason, 'created_by' => $actor->id]); }
    private function notify(IapBaicsAssessment $assessment, User $actor, string $type, string $title, string $message): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($assessment): void {
                $query->where('office_id', $assessment->responsible_office_id)
                    ->orWhereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.baics.review'));
            })
            ->pluck('id');
        $this->notifications->send($recipients, ['actorId' => $actor->id, 'type' => $type, 'category' => 'WORKFLOW', 'moduleCode' => 'IAP', 'title' => $title, 'message' => $message, 'actionUrl' => '/internal-audit-planning/baics', 'actionLabel' => 'Open BAICS', 'subjectType' => IapBaicsAssessment::class, 'subjectId' => $assessment->id, 'subjectCode' => $assessment->assessment_code, 'dedupeKey' => 'baics:control:'.$assessment->id.':'.now()->timestamp.':'.$type]);
    }
}
