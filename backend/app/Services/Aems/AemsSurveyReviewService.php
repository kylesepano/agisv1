<?php

namespace App\Services;

use App\Models\AemsPlanningPackage;
use App\Models\AuditEngagement;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Section approvals and input provenance live in immutable planning versions. */
class AemsSurveyReviewService
{
    public const SECTIONS = ['meeting', 'auditee', 'ppsp', 'ics'];

    public function __construct(private readonly AemsAccessService $access) {}

    public function isHead(User $user): bool
    {
        return $user->hasPermission('aems.planning-package.approve')
            && $this->access->mayUseSingleCiasHeadReviewException($user, 'aems.planning-package.approve');
    }

    public function canWrite(User $user, AuditEngagement $engagement, ?AemsPlanningPackage $package): bool
    {
        if ($engagement->status !== 'ENGAGEMENT_PLANNING'
            || ($package && ! in_array($package->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true))) {
            return false;
        }
        try {
            $this->access->authorizeEngagementAction($user, $engagement, $package ? 'aems.planning-package.update' : 'aems.planning-package.create');

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    public function canReview(User $user, AuditEngagement $engagement): bool
    {
        if ($this->isHead($user)) {
            return true;
        }
        return $user->hasPermission('aems.planning-package.review')
            && $engagement->teamMembers()->where('user_id', $user->id)
                ->where('assignment_role_code', 'TEAM_LEADER')->where('is_active', true)->whereNull('ended_at')->exists();
    }

    public function capabilities(User $user, AuditEngagement $engagement, ?AemsPlanningPackage $package, array $review): array
    {
        $writable = $this->canWrite($user, $engagement, $package);
        $status = $review['status'] ?? 'DRAFT';
        $pending = $status === 'PENDING_REVIEW';
        $head = $this->isHead($user);
        $reviewer = $this->canReview($user, $engagement);
        $leader = $reviewer && ! $head;
        $saved = ! empty($review['lastSavedBy']);
        $ownInput = (int) ($review['lastSavedBy']['id'] ?? 0) === (int) $user->id
            || collect($review['fieldSaves'] ?? [])->pluck('user.id')->contains(fn ($id): bool => (int) $id === (int) $user->id);
        $ownDraft = $leader && $saved && $ownInput && in_array($status, ['DRAFT', 'RETURNED_FOR_REVISION'], true);
        $headReview = $pending && ($review['reviewTarget'] ?? null) === 'CIAS_HEAD';
        $canReview = $reviewer && (! $headReview || $head);
        $selfApproved = $leader && $status === 'APPROVED'
            && (int) ($review['approvedBy']['id'] ?? 0) === (int) $user->id;

        return [
            'canEdit' => $writable && (! $pending || $head),
            'canSubmit' => $writable && ! $head && ! $pending && $saved && ($status !== 'APPROVED' || $selfApproved),
            'canApprove' => $writable && $canReview && ($pending || $ownDraft),
            'canReturn' => $writable && $pending && $canReview,
            'submitToHead' => $leader,
            'autoApprove' => $head,
        ];
    }

    /** Recover attribution for earlier drafts from their immutable versions. */
    public function reviews(?AemsPlanningPackage $package): array
    {
        if (! $package) {
            return [];
        }
        $latest = $package->latestVersion()->firstOrFail();
        $reviews = $latest->preliminary_survey['workspaceReviews'] ?? [];
        $legacy = array_diff(array_keys($latest->preliminary_survey['workspace'] ?? []), array_keys($reviews));
        if ($legacy === []) {
            return $reviews;
        }
        $previous = [];
        foreach ($package->versions()->with('creator')->orderBy('version_number')->get() as $version) {
            foreach ($legacy as $section) {
                $data = $version->preliminary_survey['workspace'][$section] ?? null;
                if ($data !== null && $data !== ($previous[$section] ?? null)) {
                    $review = $reviews[$section] ?? [];
                    $stamp = ['user' => $this->person($version->creator), 'at' => $version->created_at?->toISOString()];
                    $review['fieldSaves'] = $this->changedFields($previous[$section] ?? [], $data, $review['fieldSaves'] ?? [], $stamp);
                    $reviews[$section] = [...$review, 'status' => 'DRAFT', 'lastSavedBy' => $stamp['user'], 'lastSavedAt' => $stamp['at']];
                    $previous[$section] = $data;
                }
            }
        }

        return $reviews;
    }

    public function save(User $user, array $before, array $data, array $review): array
    {
        if (($review['status'] ?? 'DRAFT') === 'PENDING_REVIEW' && ! $this->isHead($user)) {
            throw ValidationException::withMessages(['review' => ['This section is submitted for review. Return it for revision before editing.']]);
        }
        $stamp = ['user' => $this->person($user), 'at' => now()->toISOString()];
        $changed = $before !== $data || empty($review['lastSavedBy']);
        $approved = $this->isHead($user);
        $review['fieldSaves'] = $this->changedFields($before, $data, $review['fieldSaves'] ?? [], $stamp);
        if ($changed || $approved) {
            $review = [...$review, 'status' => $approved ? 'APPROVED' : 'DRAFT',
                'submittedBy' => null, 'submittedAt' => null, 'approvedBy' => $approved ? $stamp['user'] : null,
                'approvedAt' => $approved ? $stamp['at'] : null, 'returnComment' => null,
                'returnedBy' => null, 'returnedAt' => null];
            $review['reviewTarget'] = null;
        }

        return [...$review, 'lastSavedBy' => $stamp['user'], 'lastSavedAt' => $stamp['at'], 'status' => $review['status'] ?? 'DRAFT'];
    }

    public function transition(User $user, AuditEngagement $engagement, ?AemsPlanningPackage $package, array $review, string $action, ?string $comment): array
    {
        $capabilities = $this->capabilities($user, $engagement, $package, $review);
        $key = ['SUBMIT' => 'canSubmit', 'APPROVE' => 'canApprove', 'RETURN' => 'canReturn'][$action] ?? null;
        if (! $key || ! $capabilities[$key]) {
            throw new HttpException(403, 'Your assignment or this section’s review state does not allow this action.');
        }
        if ($action === 'RETURN' && mb_strlen(trim((string) $comment)) < 5) {
            throw ValidationException::withMessages(['comment' => ['Enter a return instruction of at least 5 characters.']]);
        }
        $person = $this->person($user);
        $now = now()->toISOString();

        return match ($action) {
            'SUBMIT' => [...$review, 'status' => 'PENDING_REVIEW', 'submittedBy' => $person, 'submittedAt' => $now,
                'reviewTarget' => $capabilities['submitToHead'] ? 'CIAS_HEAD' : 'TEAM_LEADER_OR_CIAS_HEAD',
                'approvedBy' => null, 'approvedAt' => null],
            'APPROVE' => [...$review, 'status' => 'APPROVED', 'approvedBy' => $person, 'approvedAt' => $now],
            'RETURN' => [...$review, 'status' => 'RETURNED_FOR_REVISION', 'returnedBy' => $person, 'returnedAt' => $now, 'returnComment' => trim($comment)],
        };
    }

    private function changedFields(array $before, array $data, array $saves, array $stamp): array
    {
        $old = Arr::dot($before);
        $new = Arr::dot($data);
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $path) {
            if (! array_key_exists($path, $old) || ! array_key_exists($path, $new) || $old[$path] !== $new[$path]) {
                $saves[$path] = $stamp;
            }
        }

        return $saves;
    }

    private function person(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'employeeId' => $user->employee_id] : null;
    }
}
