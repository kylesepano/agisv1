<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AemsDialogueDueProcess;
use App\Models\AemsEngagementTask;
use App\Models\AemsEscalationCandidate;
use App\Models\AemsReviewNote;
use App\Models\AuditEngagement;
use App\Services\AemsWorkQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** AEMS-7A work queues, review exchanges, due process, and escalation candidates. */
class AemsWorkQueueController extends Controller
{
    public function __construct(private readonly AemsWorkQueueService $queue) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        // The service applies the same engagement/finding scope rules used by
        // the other AEMS workspaces; route middleware only provides the coarse
        // permission gate.
        return response()->json(['success' => true, 'data' => $this->queue->workspace($request, $engagement)]);
    }

    public function storeTask(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $task = $this->queue->createTask($request, $engagement, $request->validate($this->taskRules(false)));
        return response()->json(['success' => true, 'message' => 'Engagement task created.', 'data' => ['task' => $this->queue->taskData($task)]], 201);
    }

    public function updateTask(Request $request, AuditEngagement $engagement, AemsEngagementTask $task): JsonResponse
    {
        $task = $this->queue->updateTask($request, $engagement, $task, $request->validate($this->taskRules(true)));
        return response()->json(['success' => true, 'message' => 'Engagement task updated.', 'data' => ['task' => $this->queue->taskData($task)]]);
    }

    public function transitionTask(Request $request, AuditEngagement $engagement, AemsEngagementTask $task): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'string', Rule::in(['START', 'COMPLETE', 'CANCEL', 'REOPEN', 'ESCALATE'])], 'lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:10000']]);
        $task = $this->queue->transitionTask($request, $engagement, $task, $data['action'], (int) $data['lockVersion'], $data['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Engagement task transition recorded.', 'data' => ['task' => $this->queue->taskData($task)]]);
    }

    public function storeReviewNote(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $note = $this->queue->createReviewNote($request, $engagement, $request->validate($this->noteRules()));
        return response()->json(['success' => true, 'message' => 'Review note created.', 'data' => ['reviewNote' => $this->queue->reviewNoteData($note)]], 201);
    }

    public function updateReviewNote(Request $request, AuditEngagement $engagement, AemsReviewNote $note): JsonResponse
    {
        $note = $this->queue->updateReviewNote($request, $engagement, $note, $request->validate(['content' => ['required', 'string', 'min:3', 'max:50000'], 'noteType' => ['nullable', 'string', 'max:50'], 'lockVersion' => ['required', 'integer', 'min:1']]));
        return response()->json(['success' => true, 'message' => 'Review note updated.', 'data' => ['reviewNote' => $this->queue->reviewNoteData($note)]]);
    }

    public function transitionReviewNote(Request $request, AuditEngagement $engagement, AemsReviewNote $note): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'string', Rule::in(['FINALIZE', 'VOID'])], 'lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:10000']]);
        $note = $this->queue->transitionReviewNote($request, $engagement, $note, $data['action'], (int) $data['lockVersion'], $data['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Review note transition recorded.', 'data' => ['reviewNote' => $this->queue->reviewNoteData($note)]]);
    }

    public function reviseReviewNote(Request $request, AuditEngagement $engagement, AemsReviewNote $note): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:10000'], 'lockVersion' => ['required', 'integer', 'min:1']]);
        $note = $this->queue->reviseReviewNote($request, $engagement, $note, (int) $data['lockVersion'], $data['reason']);
        return response()->json(['success' => true, 'message' => 'Review note revision created.', 'data' => ['reviewNote' => $this->queue->reviewNoteData($note)]], 201);
    }

    public function attachReviewNote(Request $request, AuditEngagement $engagement, AemsReviewNote $note): JsonResponse
    {
        $data = $request->validate(['documentVersionId' => ['required', 'integer', 'min:1'], 'caption' => ['nullable', 'string', 'max:255'], 'lockVersion' => ['required', 'integer', 'min:1']]);
        $attachment = $this->queue->attachReviewNote($request, $engagement, $note, (int) $data['documentVersionId'], $data['caption'] ?? null, (int) $data['lockVersion']);
        return response()->json(['success' => true, 'message' => 'Review note attachment linked.', 'data' => ['attachment' => $attachment]]);
    }

    public function storeDueProcess(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $item = $this->queue->recordDueProcess($request, $engagement, $request->validate(['findingId' => ['required', 'integer', 'min:1'], 'responseId' => ['nullable', 'integer', 'min:1'], 'eventType' => ['required', 'string', Rule::in(AemsDialogueDueProcess::TYPES)], 'content' => ['required', 'string', 'min:3', 'max:20000'], 'dueDate' => ['nullable', 'date'], 'metadata' => ['nullable', 'array']]));
        return response()->json(['success' => true, 'message' => 'Due-process exchange recorded.', 'data' => ['dueProcess' => $this->queue->dueProcessData($item)]], 201);
    }

    public function attachDueProcess(Request $request, AuditEngagement $engagement, AemsDialogueDueProcess $item): JsonResponse
    {
        $data = $request->validate(['documentVersionId' => ['required', 'integer', 'min:1'], 'caption' => ['nullable', 'string', 'max:255']]);
        $attachment = $this->queue->attachDueProcess($request, $engagement, $item, (int) $data['documentVersionId'], $data['caption'] ?? null);
        return response()->json(['success' => true, 'message' => 'Due-process attachment linked.', 'data' => ['attachment' => $attachment]]);
    }

    public function reviewCandidate(Request $request, AuditEngagement $engagement, AemsEscalationCandidate $candidate): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'string', Rule::in(['ACKNOWLEDGE', 'RESOLVE', 'DISMISS'])], 'comment' => ['required', 'string', 'min:5', 'max:10000'], 'lockVersion' => ['required', 'integer', 'min:1']]);
        $candidate = $this->queue->reviewCandidate($request, $engagement, $candidate, $data['action'], (int) $data['lockVersion'], $data['comment']);
        return response()->json(['success' => true, 'message' => 'Escalation candidate review recorded.', 'data' => ['candidate' => $this->queue->candidateData($candidate)]]);
    }

    private function taskRules(bool $partial): array
    {
        $rules = [
            'taskType' => [$partial ? 'sometimes' : 'required', 'string', 'max:50'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'subjectType' => ['nullable', 'string', 'max:100'], 'subjectId' => ['nullable', 'integer', 'min:1'],
            'findingId' => ['nullable', 'integer', 'min:1'], 'entryConferenceId' => ['nullable', 'integer', 'min:1'], 'exitConferenceId' => ['nullable', 'integer', 'min:1'],
            'assignedTo' => ['nullable', 'integer', 'min:1'], 'assignedOfficeId' => ['nullable', 'integer', 'min:1'], 'dueAt' => ['nullable', 'date'],
        ];
        if ($partial) $rules['lockVersion'] = ['required', 'integer', 'min:1'];
        return $rules;
    }

    private function noteRules(): array
    {
        return ['content' => ['required', 'string', 'min:3', 'max:50000'], 'noteType' => ['nullable', 'string', 'max:50'], 'findingId' => ['nullable', 'integer', 'min:1'], 'entryConferenceId' => ['nullable', 'integer', 'min:1'], 'exitConferenceId' => ['nullable', 'integer', 'min:1'], 'taskId' => ['nullable', 'integer', 'min:1']];
    }
}
