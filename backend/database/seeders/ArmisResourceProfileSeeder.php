<?php

namespace Database\Seeders;

use App\Models\ArmisResourceProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Seeds the eleven-person ARMIS reference roster without creating duplicate
 * identities. Core users remain authoritative; ARMIS stores only the resource
 * profile and category used for capacity, competency, and assignment checks.
 */
class ArmisResourceProfileSeeder extends Seeder
{
    /**
     * @var list<array{username:string,resource_code:string,category:string}>
     */
    private const REFERENCE_PROFILES = [
        ['username' => 'charry.bagongon', 'resource_code' => 'ARMIS-CIAS-AUD-001', 'category' => 'AUDIT_RESOURCE'],
        ['username' => 'kristine.yare', 'resource_code' => 'ARMIS-CIAS-AUD-002', 'category' => 'AUDIT_RESOURCE'],
        ['username' => 'michele.dampog', 'resource_code' => 'ARMIS-CIAS-AUD-003', 'category' => 'AUDIT_RESOURCE'],
        ['username' => 'auditor', 'resource_code' => 'ARMIS-CIAS-AUD-004', 'category' => 'AUDIT_RESOURCE'],
        ['username' => 'daphny.roa', 'resource_code' => 'ARMIS-CIAS-ADM-001', 'category' => 'SUPPORT'],
        ['username' => 'sherly.lasacar', 'resource_code' => 'ARMIS-CIAS-ADM-002', 'category' => 'SUPPORT'],
        ['username' => 'cookie.lee', 'resource_code' => 'ARMIS-CIAS-ADM-003', 'category' => 'SUPPORT'],
        ['username' => 'jhonel.mira', 'resource_code' => 'ARMIS-CIAS-ADM-004', 'category' => 'SPECIALIST'],
        ['username' => 'kyle.dacalos', 'resource_code' => 'ARMIS-CIAS-ADM-005', 'category' => 'SPECIALIST'],
        ['username' => 'departmenthead', 'resource_code' => 'ARMIS-CIAS-HEAD-001', 'category' => 'REVIEWER'],
        ['username' => 'agisadmin', 'resource_code' => 'ARMIS-AGIS-PM-001', 'category' => 'SPECIALIST'],
    ];

    public function run(): void
    {
        $actor = User::query()->where('username', 'agisadmin')->first()
            ?? User::query()->where('username', 'admin')->first();

        if (! $actor) {
            throw new LogicException('Seed demo users before seeding ARMIS resource profiles.');
        }

        foreach (self::REFERENCE_PROFILES as $reference) {
            $user = User::query()->where('username', $reference['username'])->first();

            if (! $user || ! $user->office_id) {
                throw new LogicException("The {$reference['username']} user must exist and have an office before ARMIS seeding.");
            }

            $existing = ArmisResourceProfile::withTrashed()
                ->where('resource_code', $reference['resource_code'])
                ->first();
            $profile = ArmisResourceProfile::withTrashed()->updateOrCreate(
                ['resource_code' => $reference['resource_code']],
                [
                    'user_id' => $user->id,
                    'office_id' => $user->office_id,
                    'category' => $reference['category'],
                    'status' => 'ACTIVE',
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'effective_to' => null,
                    'notes' => 'Seeded ARMIS reference roster profile.',
                    'created_by' => $existing?->created_by ?? $actor->id,
                    'updated_by' => $actor->id,
                    'lock_version' => $existing?->lock_version ?? 1,
                ],
            );

            if ($profile->trashed()) {
                $profile->restore();
            }
        }
    }
}
