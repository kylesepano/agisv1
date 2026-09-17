<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\EntryConference;
use App\Models\EntryConferenceAcknowledgement;
use App\Models\EntryConferenceAttachment;
use App\Services\AemsEntryConferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exposes official Entry Conference preparation, conduct, and acknowledgement. */
class AemsEntryConferenceController extends Controller
{
    public function __construct(
        private readonly AemsEntryConferenceService $conferences,
    ) {}

    public function engagements(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['engagements' => $this->conferences->engagements($request)],
        ]);
    }

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->conferences->workspace($request, $engagement),
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $conference = $this->conferences->create(
            $request,
            $engagement,
            $this->recordValidation($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Entry Conference record created.',
            'data' => ['conference' => $this->conferences->conferenceData($conference)],
        ], 201);
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
    ): JsonResponse {
        $conference = $this->conferences->update(
            $request,
            $engagement,
            $conference,
            [
                ...$this->recordValidation($request),
                ...$request->validate(['lockVersion' => ['required', 'integer', 'min:1']]),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Entry Conference record updated.',
            'data' => ['conference' => $this->conferences->conferenceData($conference)],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        string $action,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:10000'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'authority' => ['nullable', 'string', 'max:255'],
            'supportingDocumentRequired' => ['sometimes', 'boolean'],
            'scheduledStartAt' => ['nullable', 'date'],
            'scheduledEndAt' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'meetingLink' => ['nullable', 'url:http,https', 'max:2000'],
            'onlineMeetingDetails' => ['nullable', 'string', 'max:4000'],
            'heldAt' => ['nullable', 'date'],
            'participantAttendance' => ['sometimes', 'array'],
            'participantAttendance.*.participantId' => ['required', 'integer'],
            'participantAttendance.*.attendanceStatus' => [
                'required',
                Rule::in(['ATTENDED', 'ABSENT', 'EXCUSED']),
            ],
            'participantAttendance.*.attendedAt' => ['nullable', 'date'],
            'participantAttendance.*.attendanceNotes' => ['nullable', 'string', 'max:4000'],
        ]);
        $conference = $this->conferences->transition(
            $request,
            $engagement,
            $conference,
            $action,
            $validated['lockVersion'],
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Entry Conference workflow transition completed.',
            'data' => ['conference' => $this->conferences->conferenceData($conference)],
        ]);
    }

    public function acknowledge(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(EntryConferenceAcknowledgement::STATUSES)],
            'reservation' => ['nullable', 'string', 'max:10000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $acknowledgement = $this->conferences->acknowledge(
            $request,
            $engagement,
            $conference,
            $validated['status'],
            $validated['reservation'] ?? null,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Entry Conference Notes acknowledged.',
            'data' => ['acknowledgement' => [
                'id' => $acknowledgement->id,
                'status' => $acknowledgement->acknowledgement_status,
                'reservation' => $acknowledgement->reservation,
                'acknowledgedAt' => $acknowledgement->acknowledged_at?->toISOString(),
            ]],
        ], 201);
    }

    public function uploadAttachment(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
    ): JsonResponse {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,zip',
            ],
            'category' => ['required', Rule::in(EntryConferenceAttachment::CATEGORIES)],
            'caption' => ['nullable', 'string', 'max:255'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $attachment = $this->conferences->uploadAttachment(
            $request,
            $engagement,
            $conference,
            $request->file('file'),
            $validated['category'],
            $validated['caption'] ?? null,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Entry Conference document uploaded.',
            'data' => ['attachment' => $this->conferences->attachmentData($attachment)],
        ], 201);
    }

    public function downloadAttachment(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        EntryConferenceAttachment $attachment,
    ): StreamedResponse {
        $version = $this->conferences->downloadAttachment(
            $request,
            $engagement,
            $conference,
            $attachment,
        );

        return Storage::disk('local')->download(
            $version->storage_path,
            $version->original_file_name,
            ['Content-Type' => $version->mime_type],
        );
    }

    /** @return array<string, mixed> */
    private function recordValidation(Request $request): array
    {
        return $request->validate([
            'scheduledStartAt' => ['nullable', 'date'],
            'scheduledEndAt' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'meetingLink' => ['nullable', 'url:http,https', 'max:2000'],
            'onlineMeetingDetails' => ['nullable', 'string', 'max:4000'],
            'agenda' => ['nullable', 'string', 'max:30000'],
            'briefingPaper' => ['nullable', 'array'],
            'briefingPaper.auditSelectionBackground' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.auditAuthority' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.preliminaryObjectives' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.scopeAndExclusions' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.methodology' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.auditCriteria' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.plannedTiming' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.teamMembersAndRoles' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.previousAuditMatters' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.engagementMilestones' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.expectedDeliverables' => ['nullable', 'string', 'max:10000'],
            'briefingPaper.initialInformationRequirements' => ['nullable', 'string', 'max:10000'],
            'auditeeViews' => ['nullable', 'string', 'max:30000'],
            'auditeeExpectations' => ['nullable', 'string', 'max:30000'],
            'conferenceNotes' => ['nullable', 'string', 'max:60000'],
            'materialMattersDisposition' => ['nullable', 'string', 'max:30000'],
            'participants' => ['present', 'array'],
            'participants.*.userId' => ['nullable', 'integer'],
            'participants.*.officeId' => ['nullable', 'integer'],
            'participants.*.participantType' => [
                'required',
                Rule::in(['AUDIT_TEAM', 'AUDITEE', 'EXTERNAL']),
            ],
            'participants.*.participantRole' => ['nullable', 'string', 'max:100'],
            'participants.*.externalName' => ['nullable', 'string', 'max:255'],
            'participants.*.externalEmail' => ['nullable', 'email', 'max:255'],
            'matters' => ['present', 'array'],
            'matters.*.matterType' => ['nullable', 'string', 'max:50'],
            'matters.*.description' => ['required', 'string', 'max:30000'],
            'matters.*.isMaterial' => ['sometimes', 'boolean'],
            'matters.*.dispositionStatus' => [
                'nullable',
                Rule::in(['OPEN', 'AGREED', 'RESOLVED', 'DEFERRED']),
            ],
            'matters.*.disposition' => ['nullable', 'string', 'max:30000'],
            'matters.*.responsibleUserId' => ['nullable', 'integer'],
            'matters.*.responsibleOfficeId' => ['nullable', 'integer'],
            'matters.*.dueDate' => ['nullable', 'date'],
            'agreements' => ['present', 'array'],
            'agreements.*.agreement' => ['required', 'string', 'max:30000'],
            'agreements.*.responsibleUserId' => ['nullable', 'integer'],
            'agreements.*.responsibleOfficeId' => ['nullable', 'integer'],
            'agreements.*.dueDate' => ['nullable', 'date'],
            'agreements.*.status' => [
                'nullable',
                Rule::in(['OPEN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED']),
            ],
        ]);
    }
}
