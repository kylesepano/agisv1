<?php

namespace App\Policies;

use App\Models\ExitConference;
use App\Models\User;
use App\Services\AemsAccessService;
use Throwable;

/**
 * Limits conference records to the audit team and offices covered by the engagement.
 */
class ExitConferencePolicy
{
    public function __construct(private readonly AemsAccessService $access) {}

    public function view(User $user, ExitConference $conference): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeConferenceView($user, $conference),
        );
    }

    public function manage(User $user, ExitConference $conference): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $conference->engagement,
                'aems.conference.manage',
            ),
        );
    }

    public function acknowledge(User $user, ExitConference $conference): bool
    {
        return $user->hasPermission('aems.conference.acknowledge')
            && $this->view($user, $conference);
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
