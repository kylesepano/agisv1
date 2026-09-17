<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\ExitConference;
use App\Models\ExitConferenceAcknowledgement;
use App\Models\ExitConferenceAttachment;
use App\Services\AemsExitConferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exposes Exit Conference scheduling, outcomes, files, and acknowledgement. */
class AemsExitConferenceController extends Controller
{
    public function __construct(private readonly AemsExitConferenceService $conferences) {}

    public function engagements(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['engagements' => $this->conferences->engagements($request)],
        ]);
    }

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
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
            $this->scheduleValidation($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Exit Conference scheduled.',
            'data' => ['conference' => $this->conferences->conferenceData($conference)],
        ], 201);
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
    ): JsonResponse {
        $conference = $this->conferences->update(
            $request,
            $engagement,
            $conference,
            [
                ...$this->scheduleValidation($request),
                ...$request->validate(['lockVersion' => ['required', 'integer', 'min:1']]),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => $conference->status === 'RESCHEDULED'
                ? 'Exit Conference rescheduled.'
                : 'Exit Conference updated.',
            'data' => ['conference' => $this->conferences->conferenceData($conference)],
        ]);
    }

    public function complete(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
    ): JsonResponse {
        $validated = $request->validate([
            'discussionSummary' => ['required', 'string', 'max:30000'],
            'minutes' => ['required', 'string', 'max:60000'],
            'agreements' => ['nullable', 'string', 'max:30000'],
            'disagreements' => ['nullable', 'string', 'max:30000'],
            'participantAttendance' => ['required', 'array', 'min:1'],
            'participantAttendance.*.participantId' => ['required', 'integer'],
            'participantAttendance.*.attendanceStatus' => [
                'required',
                Rule::in(['ATTENDED', 'ABSENT', 'EXCUSED']),
            ],
            'participantAttendance.*.attendanceNotes' => ['nullable', 'string', 'max:4000'],
            'findingDiscussions' => ['required', 'array', 'min:1'],
            'findingDiscussions.*.findingId' => ['required', 'integer'],
            'findingDiscussions.*.discussionStatus' => [
                'required',
                Rule::in(['DISCUSSED', 'NOT_DISCUSSED']),
            ],
            'findingDiscussions.*.agreementStatus' => [
                'nullable',
                Rule::in(['AGREED', 'PARTIALLY_AGREED', 'DISAGREED']),
            ],
            'findingDiscussions.*.discussionNotes' => ['nullable', 'string', 'max:10000'],
            'findingDiscussions.*.agreementDetails' => ['nullable', 'string', 'max:10000'],
            'findingDiscussions.*.disagreementDetails' => ['nullable', 'string', 'max:10000'],
            'findingDiscussions.*.revisedTargetDate' => ['nullable', 'date'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $conference = $this->conferences->complete(
            $request,
            $engagement,
            $conference,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Exit Conference completed and its minutes locked.',
            'data' => ['conference' => $this->conferences->conferenceData($conference)],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['WAIVE', 'CANCEL'])],
            'reason' => ['required', 'string', 'max:10000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $conference = $this->conferences->closeWithoutConference(
            $request,
            $engagement,
            $conference,
            $validated['action'],
            $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => $validated['action'] === 'WAIVE'
                ? 'Exit Conference formally waived.'
                : 'Exit Conference cancelled.',
            'data' => ['conference' => $this->conferences->conferenceData($conference)],
        ]);
    }

    public function uploadAttachment(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
    ): JsonResponse {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,zip',
            ],
            'category' => ['required', Rule::in(ExitConferenceAttachment::CATEGORIES)],
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
            'message' => 'Exit Conference document uploaded.',
            'data' => ['attachment' => $this->conferences->attachmentData($attachment)],
        ], 201);
    }

    public function acknowledge(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(ExitConferenceAcknowledgement::STATUSES)],
            'comment' => ['nullable', 'string', 'max:10000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $acknowledgement = $this->conferences->acknowledge(
            $request,
            $engagement,
            $conference,
            $validated['status'],
            $validated['comment'] ?? null,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Conference minutes acknowledged.',
            'data' => [
                'acknowledgement' => $this->conferences->acknowledgementData($acknowledgement),
            ],
        ], 201);
    }

    public function downloadAttachment(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        ExitConferenceAttachment $attachment,
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
    private function scheduleValidation(Request $request): array
    {
        return $request->validate([
            'scheduledStartAt' => ['required', 'date'],
            'scheduledEndAt' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'meetingLink' => ['nullable', 'url:http,https', 'max:2000'],
            'onlineMeetingDetails' => ['nullable', 'string', 'max:4000'],
            'agenda' => ['required', 'string', 'max:30000'],
            'findingIds' => ['required', 'array', 'min:1'],
            'findingIds.*' => ['required', 'integer', 'distinct'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.userId' => ['nullable', 'integer'],
            'participants.*.officeId' => ['nullable', 'integer'],
            'participants.*.externalName' => ['nullable', 'string', 'max:255'],
            'participants.*.externalEmail' => ['nullable', 'email', 'max:255'],
            'participants.*.participantRole' => ['required', 'string', 'max:60'],
        ]);
    }
}
