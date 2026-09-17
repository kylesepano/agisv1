<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapBaicsComponentRequest;
use App\Http\Requests\Iap\IapBaicsEvidenceLinkRequest;
use App\Http\Requests\Iap\IapBaicsExceptionRequest;
use App\Http\Requests\Iap\IapBaicsMethodRequest;
use App\Http\Resources\IapBaicsComponentResource;
use App\Http\Resources\IapBaicsExceptionResource;
use App\Http\Resources\IapBaicsMethodResource;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsComponent;
use App\Models\IapBaicsEvidenceLink;
use App\Models\IapBaicsException;
use App\Models\IapBaicsMethod;
use App\Services\IapBaicsAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** BAICS-2 API for control components, methods, evidence, and exceptions. */
class IapBaicsControlAssessmentController extends Controller
{
    public function __construct(private readonly IapBaicsAssessmentService $service) {}

    public function components(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->visible($request, $assessment);
        $assessment->load('components');
        $components = $assessment->components->map(fn (IapBaicsComponent $component) => new IapBaicsComponentResource($this->service->loadComponent($component)));
        return response()->json(['success' => true, 'data' => ['components' => $components->values(), 'readiness' => $this->service->readiness($assessment)]]);
    }

    public function showComponent(Request $request, IapBaicsAssessment $assessment, IapBaicsComponent $component): JsonResponse
    {
        $this->componentVisible($request, $assessment, $component);
        return response()->json(['success' => true, 'data' => ['component' => new IapBaicsComponentResource($this->service->loadComponent($component))]]);
    }

    public function updateComponent(IapBaicsComponentRequest $request, IapBaicsAssessment $assessment, IapBaicsComponent $component): JsonResponse
    {
        $this->manage($request); $this->componentVisible($request, $assessment, $component);
        $updated = $this->service->saveComponent($request, $component, $request->validated());
        return response()->json(['success' => true, 'message' => 'Control component saved.', 'data' => ['component' => new IapBaicsComponentResource($updated)]]);
    }

    public function transitionComponent(Request $request, IapBaicsAssessment $assessment, IapBaicsComponent $component, string $action): JsonResponse
    {
        $permission = match (strtoupper($action)) { 'SUBMIT' => 'iap.baics.submit', 'RETURN' => 'iap.baics.return', 'APPROVE' => 'iap.baics.approve', default => null };
        abort_unless($permission && $request->user()->hasPermission($permission), 403);
        $this->componentVisible($request, $assessment, $component);
        $validated = $request->validate(['lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:10000']]);
        $updated = $this->service->transitionComponent($request, $component, $action, $validated['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Control component workflow updated.', 'data' => ['component' => new IapBaicsComponentResource($updated)]]);
    }

    public function methods(Request $request, IapBaicsAssessment $assessment, IapBaicsComponent $component): JsonResponse
    {
        $this->componentVisible($request, $assessment, $component);
        $component->load(['methods.performer:id,employee_id,name,initials,position', 'methods.reviewer:id,employee_id,name,initials,position', 'methods.evidenceLinks.documentVersion']);
        return response()->json(['success' => true, 'data' => ['methods' => IapBaicsMethodResource::collection($component->methods), 'readiness' => $this->service->componentReadiness($component)]]);
    }

    public function storeMethod(IapBaicsMethodRequest $request, IapBaicsAssessment $assessment, IapBaicsComponent $component): JsonResponse
    {
        $this->manage($request); $this->componentVisible($request, $assessment, $component);
        $method = $this->service->storeMethod($request, $component, $request->validated());
        return response()->json(['success' => true, 'message' => 'Assessment method recorded.', 'data' => ['method' => new IapBaicsMethodResource($method)]], 201);
    }

    public function updateMethod(IapBaicsMethodRequest $request, IapBaicsAssessment $assessment, IapBaicsComponent $component, IapBaicsMethod $method): JsonResponse
    {
        $this->manage($request); $this->methodVisible($request, $assessment, $component, $method);
        $updated = $this->service->updateMethod($request, $method, $request->validated());
        return response()->json(['success' => true, 'message' => 'Assessment method updated.', 'data' => ['method' => new IapBaicsMethodResource($updated)] ]);
    }

    public function transitionMethod(Request $request, IapBaicsAssessment $assessment, IapBaicsComponent $component, IapBaicsMethod $method, string $action): JsonResponse
    {
        $permission = match (strtoupper($action)) { 'SUBMIT' => 'iap.baics.submit', 'RETURN' => 'iap.baics.return', 'APPROVE' => 'iap.baics.review', default => null };
        abort_unless($permission && $request->user()->hasPermission($permission), 403);
        $this->methodVisible($request, $assessment, $component, $method);
        $validated = $request->validate(['lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:10000']]);
        $updated = $this->service->transitionMethod($request, $method, $action, $validated['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Assessment method workflow updated.', 'data' => ['method' => new IapBaicsMethodResource($updated)]]);
    }

    public function evidence(Request $request, IapBaicsAssessment $assessment, IapBaicsComponent $component): JsonResponse
    {
        $this->componentVisible($request, $assessment, $component);
        $component->load('evidenceLinks.documentVersion.document');
        return response()->json(['success' => true, 'data' => ['evidence' => (new IapBaicsComponentResource($component))->toArray($request)['evidence']]]);
    }

    public function storeEvidence(IapBaicsEvidenceLinkRequest $request, IapBaicsAssessment $assessment, IapBaicsComponent $component): JsonResponse
    {
        $this->manage($request); $this->componentVisible($request, $assessment, $component);
        $link = $this->service->linkEvidence($request, $component, $request->validated());
        return response()->json(['success' => true, 'message' => 'Exact Core Document Version linked.', 'data' => ['evidence' => ['id' => $link->id, 'componentId' => $link->component_id, 'methodId' => $link->method_id, 'documentVersionId' => $link->document_version_id, 'fileName' => $link->documentVersion?->original_file_name, 'mimeType' => $link->documentVersion?->mime_type, 'fileSize' => $link->documentVersion?->file_size, 'checksumSha256' => $link->documentVersion?->checksum_sha256]]], 201);
    }

    public function destroyEvidence(Request $request, IapBaicsAssessment $assessment, IapBaicsComponent $component, IapBaicsEvidenceLink $link): JsonResponse
    {
        $this->manage($request); $this->componentVisible($request, $assessment, $component);
        $this->service->removeEvidence($request, $component, $link);
        return response()->json(['success' => true, 'message' => 'Evidence link removed.']);
    }

    public function exceptions(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->visible($request, $assessment);
        return response()->json(['success' => true, 'data' => ['exceptions' => IapBaicsExceptionResource::collection($assessment->exceptions()->with(['component', 'authority:id,employee_id,name,initials,position', 'creator:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position'])->get())]]);
    }

    public function storeException(IapBaicsExceptionRequest $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->manage($request); $this->visible($request, $assessment);
        $exception = $this->service->storeException($request, $assessment, $request->validated());
        return response()->json(['success' => true, 'message' => 'Corroboration exception drafted.', 'data' => ['exception' => new IapBaicsExceptionResource($exception)]], 201);
    }

    public function updateException(IapBaicsExceptionRequest $request, IapBaicsAssessment $assessment, IapBaicsException $exception): JsonResponse
    {
        $this->manage($request); $this->exceptionVisible($request, $assessment, $exception);
        $updated = $this->service->updateException($request, $exception, $request->validated());
        return response()->json(['success' => true, 'message' => 'Corroboration exception updated.', 'data' => ['exception' => new IapBaicsExceptionResource($updated)]]);
    }

    public function transitionException(Request $request, IapBaicsAssessment $assessment, IapBaicsException $exception, string $action): JsonResponse
    {
        $permission = match (strtoupper($action)) { 'SUBMIT' => 'iap.baics.submit', 'RETURN' => 'iap.baics.return', 'APPROVE', 'REJECT' => 'iap.baics.approve', default => null };
        abort_unless($permission && $request->user()->hasPermission($permission), 403);
        $this->exceptionVisible($request, $assessment, $exception);
        $validated = $request->validate(['lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:10000']]);
        $updated = $this->service->transitionException($request, $exception, $action, $validated['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Corroboration exception workflow updated.', 'data' => ['exception' => new IapBaicsExceptionResource($updated)]]);
    }

    public function readiness(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->visible($request, $assessment);
        return response()->json(['success' => true, 'data' => ['readiness' => $this->service->readiness($assessment)]]);
    }

    private function manage(Request $request): void { abort_unless($request->user()->hasPermission('iap.baics.manage-controls') || $request->user()->hasPermission('iap.baics.update'), 403); }
    private function visible(Request $request, IapBaicsAssessment $assessment): void { abort_unless($request->user()->hasGlobalOfficeAccess() || (int) $assessment->responsible_office_id === (int) $request->user()->office_id || $assessment->scopeItems()->where('office_id', $request->user()->office_id)->exists(), 403, 'This BAICS cycle is outside your office scope.'); }
    private function componentVisible(Request $request, IapBaicsAssessment $assessment, IapBaicsComponent $component): void { $this->visible($request, $assessment); abort_unless((int) $component->assessment_id === (int) $assessment->id, 404); }
    private function methodVisible(Request $request, IapBaicsAssessment $assessment, IapBaicsComponent $component, IapBaicsMethod $method): void { $this->componentVisible($request, $assessment, $component); abort_unless((int) $method->component_id === (int) $component->id, 404); }
    private function exceptionVisible(Request $request, IapBaicsAssessment $assessment, IapBaicsException $exception): void { $this->visible($request, $assessment); abort_unless((int) $exception->assessment_id === (int) $assessment->id, 404); }
}
