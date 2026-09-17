<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\OfficeRequest;
use App\Http\Resources\OfficeResource;
use App\Models\AuditLog;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintains independent offices and their users, heads, sectors, and audit areas.
 */
class OfficeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $offices = Office::query()
            ->when($request->boolean('include_archived'), fn ($query) => $query->withTrashed())
            ->when(
                ! $request->user()->hasGlobalOfficeAccess(),
                fn ($query) => $query->whereKey($request->user()->office_id),
            )
            ->with([
                'officeType:id,code,label',
                'head:id,office_id,employee_id,name,position',
                'users' => fn ($query) => $query
                    ->select(['id', 'role_id', 'office_id', 'employee_id', 'name', 'position', 'is_office_head', 'is_active'])
                    ->with('role:id,code,name'),
                'auditAreas:id,code,name,description',
                'auditLogs.user:id,name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('acronym', 'like', "%{$search}%")
                        ->orWhereHas('officeType', fn ($query) => $query->where('label', 'like', "%{$search}%"))
                        ->orWhereHas('head', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['offices' => OfficeResource::collection($offices)],
        ]);
    }

    public function store(OfficeRequest $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasGlobalOfficeAccess(),
            403,
            'Your role is limited to your assigned office.',
        );

        $office = DB::transaction(function () use ($request): Office {
            $attributes = $this->attributes($request);
            $office = Office::withTrashed()
                ->where('code', $attributes['code'])
                ->lockForUpdate()
                ->first();

            if ($office?->trashed()) {
                $office->fill($attributes);
                $office->restore();
                $office->save();
            } else {
                $office = Office::query()->create($attributes);
            }

            $office->auditAreas()->sync($request->validated('auditAreaIds', []));
            $this->ensureOfficeType($office->office_type_id);
            $this->syncHead($office, $request->validated('headUserId'));
            $this->record($request, 'office.created', $office, null, $this->snapshot($office));

            return $this->loadOffice($office);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Office created successfully.',
            'data' => ['office' => new OfficeResource($office)],
        ], 201);
    }

    public function update(OfficeRequest $request, Office $office): JsonResponse
    {
        $this->assertOfficeAccess($request, $office);
        $oldValues = $this->snapshot($office);

        DB::transaction(function () use ($request, $office): void {
            $office->update($this->attributes($request, $office));
            $this->ensureOfficeType($office->office_type_id);
            $office->auditAreas()->sync($request->validated('auditAreaIds', []));
            $this->syncHead($office, $request->validated('headUserId'));
        }, 3);

        $newValues = $this->snapshot($office);

        $this->record($request, 'office.updated', $office, $oldValues, $newValues);

        return response()->json([
            'success' => true,
            'message' => 'Office updated successfully.',
            'data' => ['office' => new OfficeResource($this->loadOffice($office->fresh()))],
        ]);
    }

    public function destroy(Request $request, Office $office): JsonResponse
    {
        $this->assertOfficeAccess($request, $office);
        if ($office->users()->exists()) {
            throw ValidationException::withMessages([
                'office' => ['Reassign or archive this office’s active users before archiving the office.'],
            ]);
        }

        $oldValues = $this->snapshot($office);

        DB::transaction(function () use ($request, $office, $oldValues): void {
            $office->forceFill(['is_active' => false])->save();
            $this->record($request, 'office.archived', $office, $oldValues, ['is_active' => false]);
            $office->delete();
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Office archived successfully.',
        ]);
    }

    public function restore(Request $request, int $office): JsonResponse
    {
        $record = Office::onlyTrashed()->findOrFail($office);
        $this->assertOfficeAccess($request, $record);

        DB::transaction(function () use ($request, $record): void {
            $record->restore();
            $record->forceFill(['is_active' => true])->save();
            $this->record(
                $request,
                'office.restored',
                $record,
                ['is_active' => false, 'is_archived' => true],
                ['is_active' => true, 'is_archived' => false],
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Office restored successfully.',
            'data' => [
                'office' => new OfficeResource($this->loadOffice($record->fresh())),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(OfficeRequest $request, ?Office $office = null): array
    {
        $validated = $request->validated();

        return [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'acronym' => $validated['acronym'] ?? null,
            'office_type_id' => $validated['officeTypeId'] ?? null,
            'description' => $validated['description'] ?? null,
            'sector' => $validated['sector'] ?? null,
            'contact_number' => $validated['contactNumber'] ?? null,
            'is_active' => $validated['isActive'] ?? $office?->is_active ?? true,
        ];
    }

    private function ensureOfficeType(?int $officeTypeId): void
    {
        if ($officeTypeId === null) {
            return;
        }

        $valid = MasterListItem::query()
            ->whereKey($officeTypeId)
            ->where('is_active', true)
            ->whereHas('masterList', fn ($query) => $query->where('code', 'OFFICE_TYPE')->where('is_active', true))
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'officeTypeId' => ['Select an active value from the Office Type master list.'],
            ]);
        }
    }

    private function assertOfficeAccess(Request $request, Office $office): void
    {
        abort_if(
            ! $request->user()->hasGlobalOfficeAccess()
            && (int) $request->user()->office_id !== (int) $office->id,
            403,
            'Your role is limited to your assigned office.',
        );
    }

    private function syncHead(Office $office, ?int $headUserId): void
    {
        if ($headUserId === null) {
            $office->users()->where('is_office_head', true)->update(['is_office_head' => false]);

            return;
        }

        $head = User::query()->whereKey($headUserId)->lockForUpdate()->firstOrFail();
        if ((int) $head->office_id !== (int) $office->id) {
            throw ValidationException::withMessages([
                'headUserId' => ['The selected office head must already belong to this office.'],
            ]);
        }

        $office->users()->whereKeyNot($head->id)->update(['is_office_head' => false]);
        $head->forceFill(['is_office_head' => true])->save();
    }

    /** @return array<string, mixed> */
    private function snapshot(Office $office): array
    {
        $office->loadMissing(['head:id,office_id,name', 'auditAreas:id']);

        return [
            ...$office->only([
                'code',
                'name',
                'acronym',
                'office_type_id',
                'sector',
                'contact_number',
                'description',
                'is_active',
            ]),
            'head_user_id' => $office->head?->id,
            'head_name' => $office->head?->name,
            'audit_area_ids' => $office->auditAreas->pluck('id')->sort()->values()->all(),
        ];
    }

    private function loadOffice(Office $office): Office
    {
        return $office->load([
            'officeType:id,code,label',
            'head:id,office_id,employee_id,name,position',
            'users' => fn ($query) => $query
                ->select(['id', 'role_id', 'office_id', 'employee_id', 'name', 'position', 'is_office_head', 'is_active'])
                ->with('role:id,code,name'),
            'auditAreas:id,code,name,description',
            'auditLogs.user:id,name',
        ]);
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(
        Request $request,
        string $action,
        Office $office,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => Office::class,
            'auditable_id' => $office->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
        ActivityRecorder::record(
            $request,
            $action,
            str_replace('.', ' ', ucfirst($action)).": {$office->code} — {$office->name}.",
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: ['module' => 'CORE', 'recordType' => Office::class, 'recordId' => $office->id],
        );
    }
}
