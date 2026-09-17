<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\MasterList;
use App\Models\User;
use App\Services\RuntimeConfiguration;
use App\Support\ActivityRecorder;
use App\Support\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Allows the current user to maintain profile data and change their password.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly RuntimeConfiguration $configuration) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['profile' => $this->data($request->user())],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'employeeId' => ['required', 'string', 'max:40', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'extension' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'position' => ['nullable', 'string', 'max:255'],
            'employmentType' => ['nullable', 'string', 'max:80'],
            'contactNumber' => ['nullable', 'string', 'max:100'],
            'birthDate' => ['nullable', 'date', 'before:today'],
        ]);
        $oldValues = $this->profileValues($user);
        $name = PersonName::fromParts(
            $validated['firstName'],
            $validated['middleName'] ?? null,
            $validated['lastName'],
            $validated['extension'] ?? null,
        );

        $user->update([
            'username' => 'employee:'.strtolower(trim($validated['employeeId'])),
            'employee_id' => strtoupper(trim($validated['employeeId'])),
            ...$name,
            'email' => $validated['email'] ?? null,
            'position' => $validated['position'] ?? null,
            'employment_type' => $validated['employmentType'] ?? null,
            'contact_number' => $validated['contactNumber'] ?? null,
            'birth_date' => $validated['birthDate'] ?? null,
        ]);
        $this->syncPosition($user->position);

        ActivityRecorder::record(
            $request,
            'profile.updated',
            "{$user->name} updated their profile.",
            $user,
            $oldValues,
            $this->profileValues($user->fresh()),
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'profile' => $this->data($user->fresh()),
                'user' => new UserResource($user->fresh()),
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min($this->configuration->passwordMinLength())],
        ]);

        if (! Hash::check($validated['currentPassword'], $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'lock_version' => $user->lock_version + 1,
        ])->save();

        ActivityRecorder::record(
            $request,
            'profile.password_changed',
            "{$user->name} changed their password.",
            $user,
            ['passwordChanged' => false],
            ['passwordChanged' => true],
        );

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /** @return array<string, mixed> */
    private function data(User $user): array
    {
        $user->loadMissing(['office:id,code,name', 'role:id,code,name']);

        return [
            ...$this->profileValues($user),
            'id' => $user->id,
            'initials' => $user->initials,
            'position' => $user->position,
            'employmentType' => $user->employment_type,
            'isOfficeHead' => $user->is_office_head,
            'office' => $user->office?->name,
            'officeCode' => $user->office?->code,
            'role' => $user->role?->name,
            'roleCode' => $user->role?->code,
            'positionOptions' => $this->masterListOptions('POSITION'),
            'employmentTypeOptions' => $this->masterListOptions('GOVERNMENT_EMPLOYMENT_TYPE'),
        ];
    }

    /** @return array<string, mixed> */
    private function profileValues(User $user): array
    {
        return [
            'employeeId' => $user->employee_id,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'extension' => $user->name_extension,
            'name' => $user->name,
            'email' => $user->email,
            'position' => $user->position,
            'employmentType' => $user->employment_type,
            'contactNumber' => $user->contact_number,
            'birthDate' => $user->birth_date?->toDateString(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function masterListOptions(string $code): array
    {
        return MasterList::query()
            ->where('code', $code)
            ->first()?->items()
            ->where('is_active', true)
            ->get(['code', 'label', 'description'])
            ->map(fn ($item): array => [
                'value' => $item->label,
                'label' => $item->label,
                'description' => $item->description,
                'keywords' => $item->code,
            ])
            ->all() ?? [];
    }

    private function syncPosition(?string $position): void
    {
        $position = trim((string) $position);
        $list = MasterList::query()->where('code', 'POSITION')->first();
        if ($position === '' || ! $list) {
            return;
        }

        $code = Str::of($position)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->limit(60, '')
            ->toString();
        $item = $list->items()->withTrashed()->firstOrNew(['code' => $code]);
        $item->fill([
            'label' => $position,
            'description' => $item->exists
                ? $item->description
                : 'Custom position title added from the profile editor.',
            'display_order' => $item->exists
                ? $item->display_order
                : ($list->items()->max('display_order') ?? 0) + 1,
            'is_active' => true,
        ])->save();

        if ($item->trashed()) {
            $item->restore();
        }
    }
}
