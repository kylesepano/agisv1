<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrder;
use App\Models\AemsAeoDistribution;
use App\Models\EngagementEvent;
use App\Services\AemsAccessService;
use App\Services\AemsAeoService;
use App\Services\AemsSupport;
use App\Services\RuntimeConfiguration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Provides the versioned Audit Engagement Order workspace and its controlled
 * review, approval, issuance, revision, and approved-version PDF operations.
 */
class AemsAeoController extends Controller
{
    public function __construct(
        private readonly AemsAeoService $orders,
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly RuntimeConfiguration $configuration,
    ) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aeo.view',
        );

        return response()->json([
            'success' => true,
            'data' => $this->orders->workspace($engagement),
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $order = $this->orders->create($request, $engagement, $this->content($request));

        return response()->json([
            'success' => true,
            'message' => 'Draft Audit Engagement Order created.',
            'data' => ['order' => $order],
        ], 201);
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
    ): JsonResponse {
        $validated = $this->content($request);
        $validated['lockVersion'] = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
        ])['lockVersion'];
        $updated = $this->orders->update($request, $engagement, $order, $validated);

        return response()->json([
            'success' => true,
            'message' => 'A new immutable AEO version was created.',
            'data' => ['order' => $updated],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['SUBMIT', 'REVIEW', 'RETURN', 'RESUBMIT', 'APPROVE', 'ISSUE', 'CANCEL', 'VOID', 'SUPERSEDE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
            'signatureMethod' => ['nullable', Rule::in(['IN_APP_ATTESTATION', 'QUALIFIED_E_SIGNATURE', 'MANUAL_TRANSCRIPT'])],
            'signatureReference' => ['nullable', 'string', 'max:160'],
        ]);
        $updated = $this->orders->transition(
            $request,
            $engagement,
            $order,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
            $validated['signatureMethod'] ?? 'IN_APP_ATTESTATION',
            $validated['signatureReference'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'AEO workflow action completed.',
            'data' => ['order' => $updated],
        ]);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $updated = $this->orders->revise(
            $request,
            $engagement,
            $order,
            $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Formal AEO revision started.',
            'data' => ['order' => $updated],
        ]);
    }

    public function amend(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $updated = $this->orders->amend(
            $request,
            $engagement,
            $order,
            $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'AEO amendment draft created from the issued version.',
            'data' => ['order' => $updated],
        ]);
    }

    public function distribution(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
    ): JsonResponse {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.aeo.view');
        return response()->json(['success' => true, 'data' => $this->orders->distributionWorkspace($engagement, $order)]);
    }

    public function distribute(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'recipientType' => ['required', Rule::in(['USER', 'OFFICE'])],
            'recipientUserId' => ['nullable', 'integer', 'exists:users,id'],
            'recipientOfficeId' => ['nullable', 'integer', 'exists:offices,id'],
            'recipientName' => ['nullable', 'string', 'max:180'],
            'transmittalMethod' => ['required', Rule::in(['SECURE_PORTAL', 'OFFICIAL_EMAIL', 'PHYSICAL_TRANSMITTAL', 'IN_PERSON'])],
            'transmittalReference' => ['nullable', 'string', 'max:160'],
        ]);
        $distribution = $this->orders->distribute($request, $engagement, $order, $validated);
        return response()->json(['success' => true, 'message' => 'AEO transmittal recorded.', 'data' => ['distribution' => $distribution]], 201);
    }

    public function acknowledgeDistribution(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        AemsAeoDistribution $distribution,
    ): JsonResponse {
        $validated = $request->validate(['note' => ['required', 'string', 'min:2', 'max:4000']]);
        $acknowledgement = $this->orders->acknowledge($request, $engagement, $order, $distribution, $validated['note']);
        return response()->json(['success' => true, 'message' => 'AEO transmittal acknowledged.', 'data' => ['distribution' => $acknowledgement]]);
    }

    public function recipientAcknowledgements(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'distributions' => $this->orders->recipientAcknowledgements($request->user()),
            ],
        ]);
    }

    public function acknowledgeRecipient(
        Request $request,
        AemsAeoDistribution $distribution,
    ): JsonResponse {
        $validated = $request->validate(['note' => ['required', 'string', 'min:2', 'max:4000']]);
        $acknowledgement = $this->orders->acknowledgeRecipient(
            $request,
            $distribution,
            $validated['note'],
        );

        return response()->json([
            'success' => true,
            'message' => 'AEO transmittal acknowledged.',
            'data' => ['distribution' => $acknowledgement],
        ]);
    }

    /**
     * Download the exact issued AEO version addressed to the authenticated
     * recipient. This endpoint is intentionally separate from the internal
     * AEMS PDF route and never grants access to the engagement workspace.
     */
    public function recipientPdf(
        Request $request,
        AemsAeoDistribution $distribution,
    ): Response {
        $context = $this->orders->recipientPdfContext($request->user(), $distribution);
        $this->support->audit(
            $request,
            'aems.aeo.recipient_pdf_downloaded',
            $context['engagement'],
            null,
            [
                'distributionId' => $distribution->id,
                'aeoId' => $context['order']->id,
                'versionNumber' => $context['version']->version_number,
            ],
            ['aeoCode' => $context['order']->order_code],
        );

        return Pdf::loadView('reports.aeo', [
            'engagement' => $context['engagement'],
            'order' => $context['order'],
            'version' => $context['version'],
            'approvalEvent' => $context['approvalEvent'],
            'issueEvent' => $context['issueEvent'],
            'configuration' => $this->configuration->publicValues(),
        ])->setPaper('a4')->download(
            "{$context['order']->order_code}-v{$context['version']->version_number}.pdf",
        );
    }

    public function pdf(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
    ): Response {
        Gate::authorize('view', $engagement);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aeo.view',
        );
        $version = $this->orders->approvedVersion($engagement, $order);
        $approvalEvent = EngagementEvent::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('subject_type', 'AEO')
            ->where('subject_id', $order->id)
            ->where('subject_version', $version->version_number)
            ->where('action', 'AEO_APPROVE')
            ->with('actor')
            ->latest('created_at')
            ->first();
        $issueEvent = EngagementEvent::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('subject_type', 'AEO')
            ->where('subject_id', $order->id)
            ->where('subject_version', $version->version_number)
            ->where('action', 'AEO_ISSUE')
            ->with('actor')
            ->latest('created_at')
            ->first();
        $order->loadMissing(['preparer', 'approver', 'issuer']);
        $engagement->loadMissing(['offices:id,code,name', 'auditAreas:id,code,name']);
        $this->support->audit(
            $request,
            'aems.aeo.pdf_downloaded',
            $engagement,
            null,
            ['aeoId' => $order->id, 'versionNumber' => $version->version_number],
            ['aeoCode' => $order->order_code],
        );

        return Pdf::loadView('reports.aeo', [
            'engagement' => $engagement,
            'order' => $order,
            'version' => $version,
            'approvalEvent' => $approvalEvent,
            'issueEvent' => $issueEvent,
            'configuration' => $this->configuration->publicValues(),
        ])->setPaper('a4')->download(
            "{$order->order_code}-v{$version->version_number}.pdf",
        );
    }

    /** @return array<string, mixed> */
    private function content(Request $request): array
    {
        return $request->validate([
            'authority' => ['required', 'string', 'min:5', 'max:10000'],
            'objectives' => ['required', 'string', 'min:5', 'max:20000'],
            'scope' => ['required', 'string', 'min:5', 'max:20000'],
            'effectivityDate' => ['nullable', 'date'],
            'plannedStartDate' => ['nullable', 'date'],
            'plannedEndDate' => ['nullable', 'date', 'after_or_equal:plannedStartDate'],
            'changeReason' => ['nullable', 'string', 'max:4000'],
        ]);
    }
}
