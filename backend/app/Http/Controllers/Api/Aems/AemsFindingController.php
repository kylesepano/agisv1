<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aems\AemsFindingRequest;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditRecommendation;
use App\Models\AemsDialogueAttachment;
use App\Models\AemsFindingTransmittal;
use App\Models\AemsFindingTransmittalRecipient;
use App\Models\AuditorRejoinder;
use App\Models\ManagementResponse;
use App\Services\AemsAccessService;
use App\Services\AemsFindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exposes the finding, response, rejoinder, and recommendation workspace. */
class AemsFindingController extends Controller
{
    public function __construct(
        private readonly AemsFindingService $findings,
        private readonly AemsAccessService $access,
    ) {}

    public function engagements(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['engagements' => $this->findings->engagements($request)],
        ]);
    }

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        if ($request->user()->hasPermission('access.auditee_scope')) {
            abort_unless(
                AuditFinding::query()
                    ->visibleTo($request->user())
                    ->where('audit_engagement_id', $engagement->id)
                    ->exists(),
                403,
                'No communicated findings from this engagement are visible to your office.',
            );
        } else {
            $this->access->authorizeEngagementAction(
                $request->user(),
                $engagement,
                'aems.finding.view',
            );
        }

        return response()->json([
            'success' => true,
            'data' => $this->findings->workspace($request, $engagement),
        ]);
    }

    public function store(AemsFindingRequest $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('create', [AuditFinding::class, $engagement]);
        $finding = $this->findings->createFinding(
            $request,
            $engagement,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft finding created.',
            'data' => ['finding' => $this->findings->findingData($finding)],
        ], 201);
    }

    public function update(
        AemsFindingRequest $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): JsonResponse {
        Gate::authorize('prepare', $finding);
        $finding = $this->findings->updateFinding(
            $request,
            $engagement,
            $finding,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft finding updated.',
            'data' => ['finding' => $this->findings->findingData($finding)],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                Rule::in([
                    'SUBMIT',
                    'VALIDATE',
                    'COMMUNICATE',
                    'REQUEST_RESPONSE',
                    'RECORD_NON_RESPONSE',
                    'FINALIZE',
                ]),
            ],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
            'recipients' => ['sometimes', 'array', 'max:100'],
            'recipients.*' => ['required', 'string', 'max:255'],
            'dueDate' => ['nullable', 'date'],
            'confidentiality' => [
                'nullable',
                Rule::in(['PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'RESTRICTED']),
            ],
        ]);
        Gate::authorize(match ($validated['action']) {
            'SUBMIT' => 'prepare',
            'VALIDATE' => 'validate',
            'COMMUNICATE', 'REQUEST_RESPONSE' => 'communicate',
            'RECORD_NON_RESPONSE' => 'review',
            'FINALIZE' => 'finalize',
        }, $finding);
        $finding = $this->findings->transitionFinding(
            $request,
            $engagement,
            $finding,
            $validated['action'],
            $validated['lockVersion'],
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Finding workflow action completed.',
            'data' => ['finding' => $this->findings->findingData($finding)],
        ]);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): JsonResponse {
        Gate::authorize('revise', $finding);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['CORRECT', 'AMEND', 'SUPERSEDE', 'WITHDRAW'])],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $revision = $this->findings->reviseFinding(
            $request,
            $engagement,
            $finding,
            $validated['action'],
            $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Immutable finding revision created.',
            'data' => ['finding' => $this->findings->findingData($revision)],
        ], 201);
    }

    public function saveRecommendation(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ?AuditRecommendation $recommendation = null,
    ): JsonResponse {
        Gate::authorize('prepare', $finding);
        $validated = $request->validate([
            'recommendation' => ['required', 'string', 'min:5', 'max:20000'],
            'responsibleOfficeId' => ['required', 'integer'],
            'targetImplementationDate' => ['nullable', 'date'],
            'findingLockVersion' => ['required', 'integer', 'min:1'],
            'lockVersion' => [$recommendation ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
        $record = $this->findings->saveRecommendation(
            $request,
            $engagement,
            $finding,
            $recommendation,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => $recommendation ? 'Recommendation updated.' : 'Recommendation created.',
            'data' => ['recommendation' => $this->findings->recommendationData($record)],
        ], $recommendation ? 200 : 201);
    }

    public function deleteRecommendation(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        AuditRecommendation $recommendation,
    ): JsonResponse {
        Gate::authorize('prepare', $finding);
        $validated = $request->validate([
            'findingLockVersion' => ['required', 'integer', 'min:1'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $this->findings->deleteRecommendation(
            $request,
            $engagement,
            $finding,
            $recommendation,
            $validated['findingLockVersion'],
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft recommendation removed.',
        ]);
    }

    public function createResponse(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): JsonResponse {
        Gate::authorize('submitManagementResponse', $finding);
        $response = $this->findings->createResponse(
            $request,
            $engagement,
            $finding,
            $this->responseValidation($request, true),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft management response created.',
            'data' => ['response' => $this->findings->responseData($response)],
        ], 201);
    }

    public function updateResponse(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
    ): JsonResponse {
        Gate::authorize('submitManagementResponse', $finding);
        $response = $this->findings->updateResponse(
            $request,
            $engagement,
            $finding,
            $response,
            $this->responseValidation($request, false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft management response updated.',
            'data' => ['response' => $this->findings->responseData($response)],
        ]);
    }

    public function transitionResponse(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['SUBMIT', 'START_REVIEW', 'REQUEST_CLARIFICATION', 'REQUEST_EXTENSION', 'APPROVE_EXTENSION', 'REJECT_EXTENSION'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
            'extensionDueDate' => ['nullable', 'date'],
            'lateReason' => ['nullable', 'string', 'max:4000'],
        ]);
        $response = $this->findings->transitionResponse(
            $request,
            $engagement,
            $finding,
            $response,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Management response workflow action completed.',
            'data' => ['response' => $this->findings->responseData($response)],
        ]);
    }

    public function createTransmittal(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): JsonResponse {
        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1', 'max:100'],
            'recipients.*' => ['required'],
            'transmittalMethod' => ['nullable', Rule::in(['OFFICIAL_LETTER', 'EMAIL', 'PORTAL', 'HAND_DELIVERY'])],
            'transmittalReference' => ['nullable', 'string', 'max:255'],
            'dueDate' => ['nullable', 'date'],
            'confidentiality' => ['nullable', Rule::in(['PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'RESTRICTED'])],
        ]);
        $transmittal = $this->findings->createTransmittal($request, $engagement, $finding, $validated);

        return response()->json([
            'success' => true,
            'message' => 'AFR transmittal recorded.',
            'data' => ['transmittal' => $this->findings->transmittalData($transmittal)],
        ], 201);
    }

    public function transitionTransmittalRecipient(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        AemsFindingTransmittal $transmittal,
        AemsFindingTransmittalRecipient $recipient,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['DELIVER', 'ACKNOWLEDGE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);
        $recipient = $this->findings->transitionTransmittalRecipient(
            $request,
            $engagement,
            $finding,
            $transmittal,
            $recipient,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'AFR recipient state updated.',
            'data' => ['recipient' => [
                'id' => $recipient->id,
                'deliveryStatus' => $recipient->delivery_status,
                'deliveredAt' => $recipient->delivered_at?->toIso8601String(),
                'acknowledgedAt' => $recipient->acknowledged_at?->toIso8601String(),
                'lockVersion' => $recipient->lock_version,
            ]],
        ]);
    }

    public function reviseResponse(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
    ): JsonResponse {
        Gate::authorize('submitManagementResponse', $finding);
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $revision = $this->findings->reviseResponse(
            $request,
            $engagement,
            $finding,
            $response,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'A new management-response version was created.',
            'data' => ['response' => $this->findings->responseData($revision)],
        ], 201);
    }

    public function saveRejoinder(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        ?AuditorRejoinder $rejoinder = null,
    ): JsonResponse {
        $validated = $request->validate([
            'disposition' => ['required', Rule::in(AuditorRejoinder::DISPOSITIONS)],
            'rejoinder' => ['required', 'string', 'min:5', 'max:20000'],
            'responseLockVersion' => ['required', 'integer', 'min:1'],
            'lockVersion' => [$rejoinder ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
        $record = $this->findings->saveRejoinder(
            $request,
            $engagement,
            $finding,
            $response,
            $rejoinder,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => $rejoinder ? 'Auditor rejoinder updated.' : 'Auditor rejoinder created.',
            'data' => ['rejoinder' => $this->findings->rejoinderData($record)],
        ], $rejoinder ? 200 : 201);
    }

    public function finalizeRejoinder(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        AuditorRejoinder $rejoinder,
    ): JsonResponse {
        $validated = $request->validate([
            'responseLockVersion' => ['required', 'integer', 'min:1'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $record = $this->findings->finalizeRejoinder(
            $request,
            $engagement,
            $finding,
            $response,
            $rejoinder,
            $validated['responseLockVersion'],
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Auditor rejoinder and dialogue finalized.',
            'data' => ['rejoinder' => $this->findings->rejoinderData($record)],
        ]);
    }

    public function uploadResponseAttachment(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
    ): JsonResponse {
        Gate::authorize('submitManagementResponse', $finding);
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,zip',
            ],
            'caption' => ['nullable', 'string', 'max:255'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $attachment = $this->findings->uploadResponseAttachment(
            $request,
            $engagement,
            $finding,
            $response,
            $request->file('file'),
            $validated['caption'] ?? null,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Supporting document attached to this response version.',
            'data' => ['attachment' => $this->findings->attachmentData($attachment)],
        ], 201);
    }

    public function uploadRejoinderAttachment(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        AuditorRejoinder $rejoinder,
    ): JsonResponse {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,zip',
            ],
            'caption' => ['nullable', 'string', 'max:255'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $attachment = $this->findings->uploadRejoinderAttachment(
            $request,
            $engagement,
            $finding,
            $response,
            $rejoinder,
            $request->file('file'),
            $validated['caption'] ?? null,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Supporting document attached to this rejoinder version.',
            'data' => ['attachment' => $this->findings->attachmentData($attachment)],
        ], 201);
    }

    public function downloadAttachment(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        AemsDialogueAttachment $attachment,
    ): StreamedResponse {
        Gate::authorize('view', $finding);
        $version = $this->findings->downloadAttachment(
            $request,
            $engagement,
            $finding,
            $attachment,
        );

        return Storage::disk('local')->download(
            $version->storage_path,
            $version->original_file_name,
            ['Content-Type' => $version->mime_type],
        );
    }

    /** @return array<string, mixed> */
    private function responseValidation(Request $request, bool $creating): array
    {
        return $request->validate([
            'agreementPosition' => ['required', Rule::in(ManagementResponse::AGREEMENT_POSITIONS)],
            'managementComment' => ['required', 'string', 'min:5', 'max:20000'],
            'proposedAction' => ['nullable', 'string', 'max:20000'],
            'responsibleUserId' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'proposedTargetDate' => ['nullable', 'date'],
            'responseKind' => ['nullable', Rule::in(ManagementResponse::RESPONSE_KINDS)],
            'supplementalReason' => ['nullable', 'string', 'max:4000'],
            'findingLockVersion' => [$creating ? 'required' : 'nullable', 'integer', 'min:1'],
            'lockVersion' => [$creating ? 'nullable' : 'required', 'integer', 'min:1'],
        ]);
    }
}
