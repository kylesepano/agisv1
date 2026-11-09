<?php

namespace App\Console\Commands;

use App\Models\AuditEngagement;
use App\Models\User;
use App\Services\AemsTeamService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SyncAemsInitialTeams extends Command
{
    protected $signature = 'aems:sync-initial-teams {--dry-run : List affected engagements without changing assignments}';

    protected $description = 'Repair missing team access for engagements created with a JSON-only initial team';

    public function handle(AemsTeamService $teams): int
    {
        $repaired = 0;
        $failed = 0;
        $engagements = AuditEngagement::query()
            ->where('is_active', true)
            ->whereNotIn('status', ['DRAFT', 'CLOSED', 'CANCELLED'])
            ->whereNotNull('initial_team_plan')
            ->whereDoesntHave('teamMembers', fn ($query) => $query->withTrashed())
            ->whereDoesntHave('teamHistory');

        foreach ($engagements->lazyById(100) as $engagement) {
            $plan = $engagement->initial_team_plan ?? [];
            if (empty($plan['departmentHeadId']) && empty($plan['teamLeaderId'])
                && empty($plan['teamMemberIds']) && empty($plan['supportStaffIds'])) {
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line($engagement->engagement_code.' needs initial team assignments.');
                continue;
            }
            $actor = User::withTrashed()->find($engagement->created_by);
            if (! $actor) {
                $this->error($engagement->engagement_code.': original assignment author not found.');
                $failed++;
                continue;
            }
            $request = Request::create('/artisan/aems/sync-initial-teams', 'POST');
            $request->setUserResolver(fn () => $actor);
            try {
                $count = $teams->syncInitialPlan($request, $engagement, onlyWhenUnassigned: true);
                $this->info($engagement->engagement_code.': '.$count.' assignments restored.');
                $repaired += $count > 0 ? 1 : 0;
            } catch (ValidationException $error) {
                $this->error($engagement->engagement_code.': '.collect($error->errors())->flatten()->implode(' '));
                $failed++;
            }
        }
        $this->info("Repaired {$repaired} engagement(s); {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
