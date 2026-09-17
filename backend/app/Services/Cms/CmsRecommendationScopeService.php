<?php

namespace App\Services\Cms;

use App\Models\CmsRecommendationCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Authoritative CMS discovery scope. Every CMS collection and record
 * resolution starts here so permissions, role authority, office ownership,
 * monitor assignment, account state, and confidentiality remain aligned.
 */
class CmsRecommendationScopeService
{
    /** @var list<string> */
    public function visibleCases(
        Builder $query,
        User $user,
        string $permission = 'cms.recommendation.view',
    ): Builder {
        if (! $this->isUsableAccount($user)
            || ! $this->hasInquiryPermission($user, $permission)) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $authority) use ($user): void {
            if ($user->hasPermission('cms.recommendation.assign')) {
                $authority->whereRaw('1 = 1');

                return;
            }

            if ($user->hasPermission('cms.administration.monitor')
                && $user->hasPermission('cms.recommendation.view')) {
                // Administrative monitoring never implies this branch; the
                // operational inquiry permission must be granted separately.
                $authority->whereRaw('1 = 1');

                return;
            }

            $authority->whereHas(
                'currentAssignment',
                fn (Builder $assignment): Builder => $assignment
                    ->where('user_id', $user->id)
                    ->where('assignment_role_code', 'COMPLIANCE_MONITOR')
                    ->where(function (Builder $effective): void {
                        $effective
                            ->whereNull('effective_from')
                            ->orWhere('effective_from', '<=', now());
                    })
                    ->where(function (Builder $effective): void {
                        $effective
                            ->whereNull('effective_until')
                            ->orWhere('effective_until', '>', now());
                    }),
            );

            $authority->orWhereHas(
                'activeValidationReview.currentAssignment',
                fn (Builder $assignment): Builder => $assignment
                    ->where('user_id', $user->id)
                    ->where('assignment_role_code', 'PRIMARY_VALIDATOR')
                    ->where('is_current', true)
                    ->where('effective_from', '<=', now())
                    ->where(function (Builder $effective): void {
                        $effective
                            ->whereNull('effective_until')
                            ->orWhere('effective_until', '>', now());
                    }),
            );

            if ($user->hasAnyPermission(['access.auditee_scope', 'access.read_only_scope'])
                && $user->office_id) {
                $authority->orWhere(function (Builder $office) use ($user): void {
                    $office
                        ->where(
                            'cms_recommendation_cases.lead_responsible_office_id',
                            $user->office_id,
                        )
                        ->orWhereHas(
                            'recommendation',
                            fn (Builder $intake): Builder => $intake
                                ->where('responsible_office_id', $user->office_id)
                                ->orWhere(
                                    'lead_responsible_office_id',
                                    $user->office_id,
                                ),
                        );
                });
            }
        });

        return $this->applyConfidentiality($query, $user);
    }

    public function resolveVisibleCase(
        User $user,
        int $caseId,
        string $permission = 'cms.recommendation.view',
        bool $lock = false,
    ): CmsRecommendationCase {
        $query = CmsRecommendationCase::query()->whereKey($caseId);
        if ($lock) {
            $query->lockForUpdate();
        }

        $record = $this->visibleCases($query, $user, $permission)->first();
        throw_unless(
            $record,
            new HttpException(404, 'The CMS recommendation is unavailable.'),
        );

        return $record;
    }

    public function authorizeAssignmentAuthority(User $user): void
    {
        throw_unless(
            $this->isUsableAccount($user)
                && $user->hasPermission('cms.recommendation.assign')
                && $user->hasPermission('cms.recommendation.assign'),
            new HttpException(403, 'You cannot manage Compliance Monitor assignments.'),
        );
    }

    public function authorizeValidationAssignmentAuthority(User $user): void
    {
        throw_unless(
            $this->isUsableAccount($user)
                && $user->hasPermission('cms.validation.assign')
                && $user->hasPermission('cms.recommendation.assign'),
            new HttpException(403, 'You cannot manage independent-validator assignments.'),
        );
    }

    public function canViewClassification(User $user, ?string $code): bool
    {
        return match (strtoupper((string) $code)) {
            'RESTRICTED' => $user->hasPermission('documents.view_restricted'),
            'CONFIDENTIAL' => $user->hasPermission('documents.view_confidential')
                || $user->hasPermission('documents.view_restricted'),
            default => true,
        };
    }

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        return [
            'portfolioWide' => $user->hasPermission('cms.recommendation.assign'),
            'officeId' => $user->hasAnyPermission(['access.auditee_scope', 'access.read_only_scope'])
                ? $user->office_id
                : null,
            'assignmentScoped' => ! $user->hasPermission('cms.recommendation.assign'),
            'confidentiality' => [
                'confidential' => $user->hasPermission('documents.view_confidential')
                    || $user->hasPermission('documents.view_restricted'),
                'restricted' => $user->hasPermission('documents.view_restricted'),
            ],
        ];
    }

    public function isUsableAccount(User $user): bool
    {
        return $user->is_active && ! $user->trashed() && ! $user->isLocked();
    }

    private function hasInquiryPermission(User $user, string $permission): bool
    {
        return $user->hasPermission($permission)
            || ($permission !== 'cms.recommendation.assign'
                && $user->hasPermission('cms.view'));
    }

    private function applyConfidentiality(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('documents.view_restricted')) {
            return $query;
        }

        $codes = ['PUBLIC', 'INTERNAL'];
        if ($user->hasPermission('documents.view_confidential')) {
            $codes[] = 'CONFIDENTIAL';
        }

        return $query->whereHas(
            'recommendation',
            fn (Builder $intake): Builder => $intake
                ->whereNull('confidentiality_code_snapshot')
                ->orWhereIn('confidentiality_code_snapshot', $codes),
        );
    }
}
