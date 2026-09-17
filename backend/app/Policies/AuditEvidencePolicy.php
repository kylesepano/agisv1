<?php

namespace App\Policies;

use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\User;
use App\Services\AemsAccessService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Applies engagement assignment and Core confidentiality rules to evidence.
 */
class AuditEvidencePolicy
{
    public function __construct(
        private readonly AemsAccessService $access,
    ) {}

    public function view(User $user, AuditEvidence $evidence): bool
    {
        return $this->allows(function () use ($user, $evidence): void {
            $this->access->authorizeEngagementAction(
                $user,
                $evidence->engagement,
                'aems.evidence.view',
            );
            $code = $evidence->confidentialityLevel?->code ?? 'INTERNAL';
            $allowed = (int) $evidence->uploaded_by === (int) $user->id
                || in_array($code, ['PUBLIC', 'INTERNAL'], true)
                || ($code === 'CONFIDENTIAL'
                    && $user->hasPermission('documents.view_confidential'))
                || $user->hasPermission('documents.view_restricted');
            throw_unless(
                $allowed,
                new HttpException(403, 'You are not authorized to access this evidence classification.'),
            );
        });
    }

    public function upload(User $user, AuditEngagement $engagement): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.evidence.upload',
            ),
        );
    }

    public function verify(User $user, AuditEvidence $evidence): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $evidence->engagement,
                'aems.evidence.verify',
            ),
        );
    }

    public function void(User $user, AuditEvidence $evidence): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $evidence->engagement,
                'aems.evidence.void',
            ),
        );
    }

    public function outcome(User $user, AuditEvidence $evidence): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $evidence->engagement,
                'aems.evidence.outcome',
                $evidence->uploaded_by,
            ),
        );
    }

    private function allows(callable $authorization): bool
    {
        try {
            $authorization();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
