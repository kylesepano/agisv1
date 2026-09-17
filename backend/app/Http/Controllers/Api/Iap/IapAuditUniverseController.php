<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapAuditUniverseRequest;
use App\Http\Resources\IapAuditUniverseResource;
use App\Models\IapAuditUniverseItem;
use App\Services\IapSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintains auditable subjects and their history for downstream IAP processes.
 */
class IapAuditUniverseController extends Controller
{
    public function __construct(private readonly IapSupport $support) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'auditAreaId' => ['nullable', 'integer', 'exists:audit_areas,id'],
            'subjectTypeId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'materialityLevelId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE,ARCHIVED,NEVER_AUDITED'],
            'includeArchived' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortBy' => ['nullable', 'in:subject_code,name,last_audit_date,updated_at'],
            'sortDirection' => ['nullable', 'in:asc,desc'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $maySeeArchived = $request->user()->hasPermission('iap.manage_universe');

        $query = IapAuditUniverseItem::query()
            ->when(
                $maySeeArchived && $request->boolean('includeArchived'),
                fn ($query) => $query->withTrashed(),
            )
            ->with([
                'subjectType',
                'responsibleOffice:id,code,name',
                'primaryAuditArea:id,code,name',
                'materialityLevel',
                'stakeholderOffices:id,code,name',
                'auditHistory',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('subject_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('materiality_exposure', 'like', "%{$search}%")
                    ->orWhereHas('responsibleOffice', fn ($office) => $office
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('primaryAuditArea', fn ($area) => $area
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('stakeholderOffices', fn ($office) => $office
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"));
            }))
            ->when(isset($validated['officeId']), fn ($query) => $query->where(function ($query) use ($validated): void {
                $query
                    ->where('responsible_office_id', $validated['officeId'])
                    ->orWhereHas('stakeholderOffices', fn ($office) => $office->whereKey($validated['officeId']));
            }))
            ->when(isset($validated['auditAreaId']), fn ($query) => $query->where('primary_audit_area_id', $validated['auditAreaId']))
            ->when(isset($validated['subjectTypeId']), fn ($query) => $query->where('subject_type_id', $validated['subjectTypeId']))
            ->when(isset($validated['materialityLevelId']), fn ($query) => $query->where('materiality_level_id', $validated['materialityLevelId']))
            ->when(isset($validated['status']), function ($query) use ($validated): void {
                match ($validated['status']) {
                    'ACTIVE' => $query->where('is_active', true)->whereNull('deleted_at'),
                    'INACTIVE' => $query->where('is_active', false)->whereNull('deleted_at'),
                    'ARCHIVED' => $query->whereNotNull('deleted_at'),
                    'NEVER_AUDITED' => $query->whereNull('last_audit_date'),
                };
            });

        $items = $query
            ->orderBy($validated['sortBy'] ?? 'name', $validated['sortDirection'] ?? 'asc')
            ->paginate((int) ($validated['perPage'] ?? app(\App\Services\RuntimeConfiguration::class)->paginationSize()))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'auditUniverse' => IapAuditUniverseResource::collection($items->getCollection()),
                'pagination' => [
                    'currentPage' => $items->currentPage(),
                    'lastPage' => $items->lastPage(),
                    'perPage' => $items->perPage(),
                    'total' => $items->total(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                ],
            ],
        ]);
    }

    public function store(IapAuditUniverseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->validateDomain($validated);

        $item = DB::transaction(function () use ($request, $validated): IapAuditUniverseItem {
            $item = IapAuditUniverseItem::withTrashed()
                ->where('subject_code', $validated['subjectCode'])
                ->lockForUpdate()
                ->first();
            if ($item && ! $item->trashed()) {
                throw ValidationException::withMessages([
                    'subjectCode' => ['This Audit Universe subject code is already in use.'],
                ]);
            }

            $attributes = [
                ...$this->attributes($validated),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'lock_version' => 1,
            ];
            if ($item?->trashed()) {
                $item->restore();
                $item->fill($attributes)->save();
            } else {
                $item = IapAuditUniverseItem::query()->create($attributes);
            }
            $item->stakeholderOffices()->sync($validated['stakeholderOfficeIds'] ?? []);
            $this->support->audit(
                $request,
                'iap.audit_universe.created',
                $item,
                null,
                $this->snapshot($item),
            );

            return $item;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit Universe subject created successfully.',
            'data' => ['auditUniverseItem' => new IapAuditUniverseResource($this->load($item))],
        ], 201);
    }

    public function update(
        IapAuditUniverseRequest $request,
        IapAuditUniverseItem $auditUniverse,
    ): JsonResponse {
        $validated = $request->validated();
        $this->validateDomain($validated);

        DB::transaction(function () use ($request, $auditUniverse, $validated): void {
            $locked = IapAuditUniverseItem::query()
                ->lockForUpdate()
                ->findOrFail($auditUniverse->id);
            if ($locked->lock_version !== (int) $validated['lockVersion']) {
                throw ValidationException::withMessages([
                    'lockVersion' => ['This Audit Universe subject was changed by another user. Refresh before saving.'],
                ]);
            }

            $old = $this->snapshot($locked);
            $locked->fill([
                ...$this->attributes($validated),
                'updated_by' => $request->user()->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $locked->stakeholderOffices()->sync($validated['stakeholderOfficeIds'] ?? []);
            $this->support->audit(
                $request,
                'iap.audit_universe.updated',
                $locked,
                $old,
                $this->snapshot($locked),
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit Universe subject updated successfully.',
            'data' => [
                'auditUniverseItem' => new IapAuditUniverseResource(
                    $this->load($auditUniverse->fresh()),
                ),
            ],
        ]);
    }

    public function destroy(Request $request, IapAuditUniverseItem $auditUniverse): JsonResponse
    {
        DB::transaction(function () use ($request, $auditUniverse): void {
            $old = $this->snapshot($auditUniverse);
            $auditUniverse->forceFill([
                'is_active' => false,
                'updated_by' => $request->user()->id,
                'lock_version' => $auditUniverse->lock_version + 1,
            ])->save();
            $this->support->audit(
                $request,
                'iap.audit_universe.archived',
                $auditUniverse,
                $old,
                $auditUniverse->toArray(),
            );
            $auditUniverse->delete();
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit Universe subject archived successfully.',
        ]);
    }

    public function restore(Request $request, int $auditUniverse): JsonResponse
    {
        $item = IapAuditUniverseItem::onlyTrashed()->findOrFail($auditUniverse);

        DB::transaction(function () use ($request, $item): void {
            $item->restore();
            $item->forceFill([
                'is_active' => true,
                'updated_by' => $request->user()->id,
                'lock_version' => $item->lock_version + 1,
            ])->save();
            $this->support->audit(
                $request,
                'iap.audit_universe.restored',
                $item,
                ['is_archived' => true, 'is_active' => false],
                ['is_archived' => false, 'is_active' => true],
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit Universe subject restored successfully.',
            'data' => ['auditUniverseItem' => new IapAuditUniverseResource($this->load($item))],
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function validateDomain(array $validated): void
    {
        $this->support->masterItem(
            (int) $validated['subjectTypeId'],
            'IAP_AUDIT_UNIVERSE_SUBJECT_TYPE',
        );
        if (isset($validated['materialityLevelId'])) {
            $this->support->masterItem(
                (int) $validated['materialityLevelId'],
                'RISK_LEVEL',
            );
        }

        $covered = DB::table('audit_area_office')
            ->where('office_id', $validated['responsibleOfficeId'])
            ->where('audit_area_id', $validated['primaryAuditAreaId'])
            ->exists();
        if (! $covered) {
            throw ValidationException::withMessages([
                'primaryAuditAreaId' => ['The primary audit area must be linked to the responsible office.'],
            ]);
        }

        if (in_array(
            (int) $validated['responsibleOfficeId'],
            array_map('intval', $validated['stakeholderOfficeIds'] ?? []),
            true,
        )) {
            throw ValidationException::withMessages([
                'stakeholderOfficeIds' => ['The responsible office must not be repeated as a stakeholder office.'],
            ]);
        }
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'subject_code' => $validated['subjectCode'],
            'name' => $validated['name'],
            'subject_type_id' => $validated['subjectTypeId'],
            'responsible_office_id' => $validated['responsibleOfficeId'],
            'primary_audit_area_id' => $validated['primaryAuditAreaId'],
            'materiality_level_id' => $validated['materialityLevelId'] ?? null,
            'description' => $validated['description'],
            'audit_scope' => $validated['auditScope'] ?? null,
            'materiality_exposure' => $validated['materialityExposure'] ?? null,
            'last_audit_date' => $validated['lastAuditDate'] ?? null,
            'historical_audit_summary' => $validated['historicalAuditSummary'] ?? null,
            'is_active' => $validated['isActive'] ?? true,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(IapAuditUniverseItem $item): array
    {
        $item->load(['stakeholderOffices:id']);

        return [
            ...$item->toArray(),
            'stakeholder_office_ids' => $item->stakeholderOffices->pluck('id')->all(),
        ];
    }

    private function load(IapAuditUniverseItem $item): IapAuditUniverseItem
    {
        return $item->load([
            'subjectType',
            'responsibleOffice:id,code,name',
            'primaryAuditArea:id,code,name',
            'materialityLevel',
            'stakeholderOffices:id,code,name',
            'auditHistory',
        ]);
    }
}
