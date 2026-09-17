<?php

namespace App\Policies;

use App\Models\EntryConference;
use App\Models\User;
use App\Services\AemsAccessService;
use Throwable;

class EntryConferencePolicy
{
    public function __construct(private readonly AemsAccessService $access) {}

    public function view(User $user, EntryConference $conference): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEntryConferenceView($user, $conference),
        );
    }

    public function manage(User $user, EntryConference $conference): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $conference->engagement,
                'aems.entry-conference.manage',
            ),
        );
    }

    public function acknowledge(User $user, EntryConference $conference): bool
    {
        return $user->hasPermission('aems.entry-conference.acknowledge')
            && $this->view($user, $conference);
    }

    public function waive(User $user, EntryConference $conference): bool
    {
        return $user->hasPermission('aems.entry-conference.waive')
            && (int) $conference->created_by !== (int) $user->id
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
