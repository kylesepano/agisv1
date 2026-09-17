<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\AuditLog;
use App\Models\MasterList;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\WorkflowDefinition;
use App\Services\RuntimeConfiguration;
use App\Support\ActivityRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Serves the shared Core registries and administrator-managed configuration.
 */
class CoreRegistryController extends Controller
{
    private const INTERNAL_REFERENCE_LISTS = [
        'RISK_LEVEL',
        'IAP_COMMENT_TYPE',
    ];

    public function auditAreas(Request $request): JsonResponse
    {
        $includeArchived = $request->boolean('include_archived');
        $areas = AuditArea::query()
            ->when($includeArchived, fn ($query) => $query->withTrashed())
            ->when(
                ! $request->user()->hasGlobalOfficeAccess(),
                fn ($query) => $query->where(function ($query) use ($request): void {
                    $query
                        ->where('responsible_office_id', $request->user()->office_id)
                        ->orWhereHas(
                            'offices',
                            fn ($offices) => $offices->whereKey($request->user()->office_id),
                        );
                }),
            )
            ->with([
                'parent:id,code,name',
                'children:id,parent_audit_area_id,code,name,is_active',
                'auditAreaType:id,code,label',
                'responsibleOffice:id,code,name',
                'offices:id,code,name',
                'focuses' => fn ($query) => $query
                    ->when($includeArchived, fn ($query) => $query->withTrashed())
                    ->select(['id', 'audit_area_id', 'code', 'name', 'description', 'display_order', 'is_active', 'deleted_at']),
                'engagements' => fn ($query) => $query
                    ->select(['iap_plan_engagements.id', 'plan_id', 'engagement_code', 'title', 'schedule_status'])
                    ->with('plan:id,plan_code,title,status,fiscal_year'),
                'auditLogs.user:id,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (AuditArea $area): array => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'description' => $area->description,
                'scope' => $area->scope,
                'parentAuditAreaId' => $area->parent_audit_area_id,
                'parentAuditArea' => $this->auditAreaReference($area->parent),
                'children' => $area->children
                    ->map(fn (AuditArea $child): array => $this->auditAreaReference($child))
                    ->values(),
                'auditAreaTypeId' => $area->audit_area_type_id,
                'auditAreaType' => $area->auditAreaType ? [
                    'id' => $area->auditAreaType->id,
                    'code' => $area->auditAreaType->code,
                    'label' => $area->auditAreaType->label,
                ] : null,
                'responsibleOfficeId' => $area->responsible_office_id,
                'responsibleOffice' => $this->officeReference($area->responsibleOffice),
                'isActive' => $area->is_active,
                'isArchived' => $area->trashed(),
                'offices' => $area->offices->map(fn (Office $office): array => [
                    'id' => $office->id,
                    'code' => $office->code,
                    'name' => $office->name,
                ])->values(),
                'focuses' => $area->focuses->map(fn (AuditFocus $focus): array => $this->focusData($focus))->values(),
                'relatedAudits' => $this->relatedAudits($area),
                'history' => $this->areaHistory($area),
            ]);

        return $this->success(['auditAreas' => $areas]);
    }

    public function storeAuditArea(Request $request): JsonResponse
    {
        $validated = $this->validateAuditArea($request);
        $this->assertAuditAreaSelection($request, $validated);

        $this->ensureAuditAreaType($validated['auditAreaTypeId'] ?? null);

        $area = DB::transaction(function () use ($validated, $request): AuditArea {
            $officeIds = collect($validated['officeIds'] ?? [])
                ->push($validated['responsibleOfficeId'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $area = AuditArea::query()->create([
                'parent_audit_area_id' => $validated['parentAuditAreaId'] ?? null,
                'audit_area_type_id' => $validated['auditAreaTypeId'] ?? null,
                'responsible_office_id' => $validated['responsibleOfficeId'] ?? collect($officeIds)->first(),
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'scope' => $validated['scope'] ?? null,
                'is_active' => $validated['isActive'] ?? true,
            ]);
            $area->offices()->sync($officeIds);
            $this->recordArea($request, 'audit_area.created', $area, null, $this->areaSnapshot($area));

            return $area;
        });

        return $this->success(['auditArea' => $this->areaData($area)], 'Audit area created successfully.', 201);
    }

    public function updateAuditArea(Request $request, AuditArea $auditArea): JsonResponse
    {
        $this->assertAuditAreaScope($request, $auditArea);
        $validated = $this->validateAuditArea($request, $auditArea);
        $this->assertAuditAreaSelection($request, $validated);
        $this->ensureAuditAreaType($validated['auditAreaTypeId'] ?? null);
        $this->ensureValidAuditAreaParent($auditArea, $validated['parentAuditAreaId'] ?? null);
        $oldValues = $this->areaSnapshot($auditArea);

        DB::transaction(function () use ($validated, $auditArea, $request, $oldValues): void {
            $officeIds = collect($validated['officeIds'] ?? [])
                ->push($validated['responsibleOfficeId'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $auditArea->update([
                'parent_audit_area_id' => $validated['parentAuditAreaId'] ?? null,
                'audit_area_type_id' => $validated['auditAreaTypeId'] ?? null,
                'responsible_office_id' => $validated['responsibleOfficeId'] ?? collect($officeIds)->first(),
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'scope' => $validated['scope'] ?? null,
                'is_active' => $validated['isActive'] ?? $auditArea->is_active,
            ]);
            $auditArea->offices()->sync($officeIds);
            $this->recordArea(
                $request,
                'audit_area.updated',
                $auditArea,
                $oldValues,
                $this->areaSnapshot($auditArea),
            );
        });

        return $this->success(['auditArea' => $this->areaData($auditArea)], 'Audit area updated successfully.');
    }

    public function destroyAuditArea(Request $request, AuditArea $auditArea): JsonResponse
    {
        $this->assertAuditAreaScope($request, $auditArea);
        if ($auditArea->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'auditArea' => ['Archive or reassign the active sub-audit areas before archiving this parent area.'],
            ]);
        }

        $oldValues = $this->areaSnapshot($auditArea);
        DB::transaction(function () use ($request, $auditArea, $oldValues): void {
            $auditArea->focuses()->update(['is_active' => false]);
            $auditArea->focuses()->delete();
            $auditArea->forceFill(['is_active' => false])->save();
            $this->recordArea(
                $request,
                'audit_area.archived',
                $auditArea,
                $oldValues,
                $this->areaSnapshot($auditArea),
            );
            $auditArea->delete();
        });

        return $this->success(message: 'Audit area archived successfully.');
    }

    public function restoreAuditArea(Request $request, int $auditArea): JsonResponse
    {
        $area = AuditArea::onlyTrashed()->findOrFail($auditArea);
        $this->assertAuditAreaScope($request, $area);

        DB::transaction(function () use ($request, $area): void {
            $area->restore();
            $area->forceFill(['is_active' => true])->save();
            $area->focuses()->onlyTrashed()->restore();
            $area->focuses()->update(['is_active' => true]);
            $this->recordArea(
                $request,
                'audit_area.restored',
                $area,
                ['is_active' => false, 'is_archived' => true],
                $this->areaSnapshot($area),
            );
        });

        return $this->success(
            ['auditArea' => $this->areaData($area)],
            'Audit area restored successfully.',
        );
    }

    public function auditFocuses(Request $request): JsonResponse
    {
        $includeArchived = $request->boolean('include_archived');
        $focuses = AuditFocus::query()
            ->when($includeArchived, fn ($query) => $query->withTrashed())
            ->when(
                ! $request->user()->hasGlobalOfficeAccess(),
                fn ($query) => $query->whereHas(
                    'auditArea',
                    fn ($area) => $area
                        ->where('responsible_office_id', $request->user()->office_id)
                        ->orWhereHas(
                            'offices',
                            fn ($offices) => $offices->whereKey($request->user()->office_id),
                        ),
                ),
            )
            ->with([
                'auditArea' => fn ($query) => $query
                    ->when($includeArchived, fn ($query) => $query->withTrashed())
                    ->select(['id', 'code', 'name']),
            ])
            ->orderBy('audit_area_id')
            ->orderBy('display_order')
            ->get()
            ->map(fn (AuditFocus $focus): array => $this->focusData($focus, true));

        return $this->success(['auditFocuses' => $focuses]);
    }

    public function storeAuditFocus(Request $request): JsonResponse
    {
        $validated = $this->validateAuditFocus($request);
        $this->assertAuditAreaScope(
            $request,
            AuditArea::query()->findOrFail($validated['auditAreaId']),
        );
        $focus = AuditFocus::query()->create($this->focusAttributes($validated));
        $this->recordCoreChange(
            $request,
            'audit_focus.created',
            $focus,
            null,
            $this->focusData($focus, true),
        );

        return $this->success(['auditFocus' => $this->focusData($focus, true)], 'Audit focus created successfully.', 201);
    }

    public function updateAuditFocus(Request $request, AuditFocus $auditFocus): JsonResponse
    {
        $this->assertAuditAreaScope($request, $auditFocus->auditArea);
        $validated = $this->validateAuditFocus($request, $auditFocus);
        $this->assertAuditAreaScope(
            $request,
            AuditArea::query()->findOrFail($validated['auditAreaId']),
        );
        $oldValues = $this->focusData($auditFocus, true);
        $auditFocus->update($this->focusAttributes($validated));
        $this->recordCoreChange(
            $request,
            'audit_focus.updated',
            $auditFocus,
            $oldValues,
            $this->focusData($auditFocus->fresh(), true),
        );

        return $this->success(['auditFocus' => $this->focusData($auditFocus, true)], 'Audit focus updated successfully.');
    }

    public function destroyAuditFocus(Request $request, AuditFocus $auditFocus): JsonResponse
    {
        $this->assertAuditAreaScope($request, $auditFocus->auditArea);
        $oldValues = $this->focusData($auditFocus, true);
        $auditFocus->forceFill(['is_active' => false])->save();
        $this->recordCoreChange(
            $request,
            'audit_focus.archived',
            $auditFocus,
            $oldValues,
            [...$this->focusData($auditFocus, true), 'isArchived' => true],
        );
        $auditFocus->delete();

        return $this->success(message: 'Audit focus archived successfully.');
    }

    public function restoreAuditFocus(Request $request, int $auditFocus): JsonResponse
    {
        $focus = AuditFocus::onlyTrashed()->findOrFail($auditFocus);
        $this->assertAuditAreaScope($request, $focus->auditArea);
        $focus->restore();
        $focus->forceFill(['is_active' => true])->save();
        $this->recordCoreChange(
            $request,
            'audit_focus.restored',
            $focus,
            ['isActive' => false, 'isArchived' => true],
            $this->focusData($focus, true),
        );

        return $this->success(
            ['auditFocus' => $this->focusData($focus, true)],
            'Audit focus restored successfully.',
        );
    }

    public function roles(Request $request): JsonResponse
    {
        $includeArchived = $request->boolean('include_archived');
        $roles = Role::query()
            ->when($includeArchived, fn ($query) => $query->withTrashed())
            ->with([
                'permissions:id,code,name,module,action',
                'assignedUsers' => fn ($query) => $query
                    ->withTrashed()
                    ->select([
                        'users.id',
                        'users.role_id',
                        'users.employee_id',
                        'users.name',
                        'users.email',
                        'users.deleted_at',
                    ]),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => $this->roleData($role));

        return $this->success(['roles' => $roles]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $validated = $this->validateRole($request);

        $role = DB::transaction(function () use ($validated): Role {
            $role = Role::query()->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_system' => false,
                'is_active' => $validated['isActive'],
                'office_access_scope' => $validated['officeAccessScope'] ?? 'OWN_OFFICE',
                'engagement_access_scope' => $validated['engagementAccessScope'] ?? 'ASSIGNED',
            ]);
            $role->permissions()->sync($validated['permissionIds']);

            return $role;
        });

        ActivityRecorder::record(
            $request,
            'role.created',
            "{$request->user()->name} created the {$role->name} access role.",
            null,
            null,
            $this->roleActivityValues($role),
            ['roleId' => $role->id],
        );
        $this->recordModelAudit($request, 'role.created', $role, null, $this->roleActivityValues($role));

        return $this->success(
            ['role' => $this->roleData($role)],
            'Access role created successfully.',
            201,
        );
    }

    public function cloneRole(Request $request, Role $role): JsonResponse
    {
        if ($role->permissions()->where('code', 'access.manage_system_roles')->exists()
            && ! $request->user()->hasPermission('access.manage_system_roles')) {
            abort(403, 'Only a Platform Administrator can clone that role.');
        }

        $validated = $this->validateRole($request);
        $source = $role->loadMissing('permissions:id');

        $clone = DB::transaction(function () use ($source, $validated): Role {
            $clone = Role::query()->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $source->description,
                'is_system' => false,
                'is_active' => $validated['isActive'],
                'office_access_scope' => $validated['officeAccessScope']
                    ?? $source->office_access_scope,
                'engagement_access_scope' => $validated['engagementAccessScope']
                    ?? $source->engagement_access_scope,
            ]);
            $clone->permissions()->sync(
                $validated['permissionIds'] ?? $source->permissions->pluck('id')->all(),
            );

            return $clone;
        });

        ActivityRecorder::record(
            $request,
            'role.cloned',
            "{$request->user()->name} cloned {$source->name} as {$clone->name}.",
            null,
            ['sourceRoleId' => $source->id, 'sourceRole' => $source->name],
            $this->roleActivityValues($clone),
            ['roleId' => $clone->id, 'sourceRoleId' => $source->id],
        );
        $this->recordModelAudit($request, 'role.cloned', $clone, null, $this->roleActivityValues($clone));

        return $this->success(
            ['role' => $this->roleData($clone)],
            'Access role cloned successfully.',
            201,
        );
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        if ($role->permissions()->where('code', 'access.manage_system_roles')->exists()
            && ! $request->user()->hasPermission('access.manage_system_roles')) {
            abort(403, 'Only a Platform Administrator can manage that role.');
        }

        $validated = $this->validateRole($request, $role);
        $oldValues = $this->roleActivityValues($role);

        DB::transaction(function () use ($role, $validated): void {
            $role->update([
                'code' => $role->is_system ? $role->code : ($validated['code'] ?? $role->code),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['isActive'],
                'office_access_scope' => $validated['officeAccessScope']
                    ?? $role->office_access_scope,
                'engagement_access_scope' => $validated['engagementAccessScope']
                    ?? $role->engagement_access_scope,
            ]);
            $role->permissions()->sync($validated['permissionIds']);
        });
        $role->unsetRelation('permissions');

        ActivityRecorder::record(
            $request,
            'role.updated',
            "{$request->user()->name} updated the {$role->name} access role.",
            null,
            $oldValues,
            $this->roleActivityValues($role),
            ['roleId' => $role->id],
        );
        $this->recordModelAudit($request, 'role.updated', $role, $oldValues, $this->roleActivityValues($role));

        return $this->success(
            ['role' => $this->roleData($role)],
            'Access role updated successfully.',
        );
    }

    public function destroyRole(Request $request, Role $role): JsonResponse
    {
        if ($role->permissions()->where('code', 'access.manage_system_roles')->exists()) {
            throw ValidationException::withMessages([
                'role' => ['The Platform Administrator role cannot be archived.'],
            ]);
        }

        $assignedUsers = $role->assignedUsers()->withTrashed()->count();
        if ($assignedUsers > 0) {
            throw ValidationException::withMessages([
                'role' => [
                    "This role cannot be archived while {$assignedUsers} ".
                    str('user')->plural($assignedUsers).' are still assigned to it.',
                ],
            ]);
        }

        $oldValues = $this->roleActivityValues($role);
        $role->forceFill(['is_active' => false])->save();
        $role->delete();

        ActivityRecorder::record(
            $request,
            'role.archived',
            "{$request->user()->name} archived the {$role->name} access role.",
            null,
            $oldValues,
            $this->roleActivityValues($role),
            ['roleId' => $role->id],
        );
        $this->recordModelAudit($request, 'role.archived', $role, $oldValues, $this->roleActivityValues($role));

        return $this->success(message: 'Access role archived successfully.');
    }

    public function restoreRole(Request $request, int $role): JsonResponse
    {
        $record = Role::onlyTrashed()->findOrFail($role);
        $record->restore();
        $record->forceFill(['is_active' => true])->save();

        ActivityRecorder::record(
            $request,
            'role.restored',
            "{$request->user()->name} restored the {$record->name} access role.",
            null,
            ['isActive' => false, 'isArchived' => true],
            $this->roleActivityValues($record),
            ['roleId' => $record->id],
        );
        $this->recordModelAudit(
            $request,
            'role.restored',
            $record,
            ['isActive' => false, 'isArchived' => true],
            $this->roleActivityValues($record),
        );

        return $this->success(
            ['role' => $this->roleData($record)],
            'Access role restored successfully.',
        );
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('action')
            ->get(['id', 'code', 'name', 'module', 'action', 'description']);

        return $this->success(['permissions' => $permissions]);
    }

    public function masterLists(Request $request): JsonResponse
    {
        $lists = MasterList::query()
            ->when(
                $request->boolean('configurableOnly'),
                fn ($query) => $query->whereNotIn('code', self::INTERNAL_REFERENCE_LISTS),
            )
            ->with('items')
            ->orderBy('name')
            ->get()
            ->map(fn (MasterList $list): array => $this->masterListData($list));

        return $this->success(['masterLists' => $lists]);
    }

    public function updateMasterList(Request $request, MasterList $masterList): JsonResponse
    {
        $masterList->load('items');
        $oldValues = $this->masterListData($masterList);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.code' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.isActive' => ['required', 'boolean'],
        ]);

        if (in_array($masterList->code, self::INTERNAL_REFERENCE_LISTS, true)) {
            throw ValidationException::withMessages([
                'masterList' => [
                    "{$masterList->name} is an internal system reference and cannot be edited.",
                ],
            ]);
        }

        DB::transaction(function () use ($masterList, $validated): void {
            $masterList->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['isActive'],
            ]);

            $keptIds = [];
            foreach ($validated['items'] as $index => $item) {
                $saved = $masterList->items()->updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'code' => strtoupper($item['code']),
                        'label' => $item['label'],
                        'description' => $item['description'] ?? null,
                        'display_order' => $index + 1,
                        'is_active' => $item['isActive'],
                    ],
                );
                $keptIds[] = $saved->id;
            }

            $masterList->items()->whereNotIn('id', $keptIds)->delete();
        });
        $masterList->load('items');
        $this->recordCoreChange(
            $request,
            'master_list.updated',
            $masterList,
            $oldValues,
            $this->masterListData($masterList),
        );

        return $this->success(message: 'Master list updated successfully.');
    }

    public function storeMasterList(Request $request): JsonResponse
    {
        $request->merge([
            'code' => $this->normalizeCode((string) $request->input('code')),
            'items' => collect($request->input('items', []))
                ->map(fn (array $item): array => [
                    ...$item,
                    'code' => $this->normalizeCode((string) ($item['code'] ?? '')),
                ])
                ->all(),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('master_lists', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.code' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.isActive' => ['required', 'boolean'],
        ]);

        $masterList = DB::transaction(function () use ($validated): MasterList {
            $list = MasterList::query()->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['isActive'],
            ]);

            foreach ($validated['items'] as $index => $item) {
                $list->items()->create([
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'description' => $item['description'] ?? null,
                    'display_order' => $index + 1,
                    'is_active' => $item['isActive'],
                ]);
            }

            return $list->load('items');
        });
        $this->recordCoreChange(
            $request,
            'master_list.created',
            $masterList,
            null,
            $this->masterListData($masterList),
        );

        return $this->success(
            ['masterList' => $this->masterListData($masterList)],
            'Master list created successfully.',
            201,
        );
    }

    public function configurations(): JsonResponse
    {
        $definitions = app(RuntimeConfiguration::class)->definitions();
        $riskOptions = MasterListItem::query()
            ->whereHas('masterList', fn ($query) => $query->where('code', 'RISK_LEVEL'))
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['code', 'label'])
            ->map(fn (MasterListItem $item): array => ['value' => $item->code, 'label' => $item->label])
            ->all();
        $workflowOptions = WorkflowDefinition::query()
            ->where('status', 'PUBLISHED')
            ->where('is_active', true)
            ->orderBy('module_code')
            ->orderBy('name')
            ->get(['code', 'name', 'module_code', 'version'])
            ->groupBy('module_code');

        // Option lists come from live master/workflow records so a configuration
        // cannot silently point at an arbitrary free-text value.
        $definitions['default_risk_level_code']['options'] = $riskOptions;
        foreach (['CORE', 'IAP'] as $module) {
            $definitions['workflow_mapping_'.strtolower($module)]['options'] = $workflowOptions
                ->get($module, collect())
                ->map(fn (WorkflowDefinition $workflow): array => [
                    'value' => $workflow->code,
                    'label' => "{$workflow->name} (v{$workflow->version})",
                ])
                ->values()
                ->all();
        }
        $configurations = SystemConfiguration::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->map(fn (SystemConfiguration $configuration): array => [
                'id' => $configuration->id,
                'key' => $configuration->key,
                'name' => $configuration->name,
                'value' => $configuration->type === 'secret' ? '' : $configuration->value,
                'type' => $configuration->type,
                'group' => $configuration->group,
                  'description' => $configuration->description,
                  'constraints' => collect($definitions[$configuration->key] ?? [])
                      ->except('rules')
                      ->all(),
              ]);

        return $this->success(['configurations' => $configurations]);
    }

    public function updateConfigurations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'configurations' => ['required', 'array', 'min:1'],
            'configurations.*.key' => ['required', 'string', Rule::exists('system_configurations', 'key')],
            'configurations.*.value' => ['present'],
        ]);
        $runtime = app(RuntimeConfiguration::class);
        $definitions = $runtime->definitions();

        foreach ($validated['configurations'] as $index => $item) {
            $rules = $definitions[$item['key']]['rules'] ?? ['present'];
            validator(
                ['value' => $item['value']],
                ['value' => $rules],
                [],
                ['value' => "configuration value for {$item['key']}"],
            )->validate();

            if ($item['key'] === 'armis_provider_mode'
                && strtoupper((string) $item['value']) !== 'ARMIS_AUTHORITATIVE') {
                throw ValidationException::withMessages([
                    "configurations.{$index}.value" => [
                        'ARMIS_AUTHORITATIVE is the only supported operational resource-provider mode.',
                    ],
                ]);
            }
        }

        DB::transaction(function () use ($request, $validated): void {
            foreach ($validated['configurations'] as $item) {
                $configuration = SystemConfiguration::query()->where('key', $item['key'])->firstOrFail();
                // A blank secret means "keep the stored value"; configuration
                // reads intentionally return an empty value instead of a mask.
                if ($configuration->type === 'secret' && blank($item['value'])) {
                    continue;
                }
                $value = match ($configuration->type) {
                    'integer' => (int) $item['value'],
                    'boolean' => filter_var($item['value'], FILTER_VALIDATE_BOOL),
                    'secret' => Crypt::encryptString((string) $item['value']),
                    default => (string) $item['value'],
                };
                $oldValue = $configuration->value;
                $configuration->update(['value' => $value, 'updated_by' => $request->user()->id]);
                if ($oldValue !== $value) {
                    $this->recordCoreChange(
                        $request,
                        'system_configuration.updated',
                        $configuration,
                        ['value' => $oldValue],
                        ['value' => $value],
                    );
                }
            }
          });

          $runtime->forget();
          $runtime->apply();

          return $this->success(
              ['configuration' => $runtime->publicValues()],
              'System configuration updated successfully.',
          );
      }

    public function uploadLogo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $file = $validated['logo'];
        $path = $file->storePublicly('branding', 'public');
        throw_unless($path, ValidationException::withMessages([
            'logo' => ['The logo could not be stored.'],
        ]));

        $configuration = SystemConfiguration::query()->where('key', 'logo_url')->firstOrFail();
        $oldUrl = (string) $configuration->value;
        $configuration->update([
            'value' => Storage::disk('public')->url($path),
            'updated_by' => $request->user()->id,
        ]);

        // Only delete an earlier managed branding asset. Bundled defaults and
        // external URLs must never be removed from disk.
        if (str_starts_with($oldUrl, '/storage/branding/')) {
            Storage::disk('public')->delete(Str::after($oldUrl, '/storage/'));
        }

        $this->recordCoreChange(
            $request,
            'system_configuration.logo_updated',
            $configuration,
            ['logoUrl' => $oldUrl],
            ['logoUrl' => $configuration->value],
        );
        $runtime = app(RuntimeConfiguration::class);
        $runtime->forget();

        return $this->success(
            ['configuration' => $runtime->publicValues()],
            'Runtime logo updated successfully.',
        );
    }

    public function testEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
        ]);
        $runtime = app(RuntimeConfiguration::class);
        throw_unless($runtime->boolean('mail_enabled'), ValidationException::withMessages([
            'recipient' => ['Outbound email is disabled in System Configuration.'],
        ]));

        $runtime->apply();
        Mail::raw(
            'This is a test message confirming that AGIS outbound email configuration is working.',
            fn ($message) => $message
                ->to($validated['recipient'])
                ->subject('AGIS email configuration test'),
        );
        ActivityRecorder::record(
            $request,
            'system_configuration.test_email_sent',
            "Sent a System Configuration test email to {$validated['recipient']}.",
            metadata: ['module' => 'CORE', 'recipient' => $validated['recipient']],
        );

        return $this->success(message: 'Test email sent successfully.');
    }

    public function activityLogs(Request $request): JsonResponse
    {
        $logs = ActivityLog::query()
            ->with(['user:id,name,initials', 'subjectUser:id,name'])
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->latest()
            ->limit(250)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'actor' => $log->user?->name ?? 'System',
                'actorInitials' => $log->user?->initials ?? 'SY',
                'subject' => $log->subjectUser?->name,
                'oldValues' => $log->old_values,
                'newValues' => $log->new_values,
                'ipAddress' => $log->ip_address,
                'createdAt' => $log->created_at?->toIso8601String(),
            ]);

        return $this->success(['activityLogs' => $logs]);
    }

    /** @param array<string, mixed> $validated */
    private function assertAuditAreaSelection(Request $request, array $validated): void
    {
        if ($request->user()->hasGlobalOfficeAccess()) {
            return;
        }

        $officeIds = collect($validated['officeIds'] ?? [])
            ->push($validated['responsibleOfficeId'] ?? null)
            ->filter()
            ->map(fn ($id): int => (int) $id);

        abort_unless(
            $request->user()->office_id
            && $officeIds->contains((int) $request->user()->office_id),
            403,
            'Your role may only manage audit areas covering your assigned office.',
        );

        if (! empty($validated['parentAuditAreaId'])) {
            $this->assertAuditAreaScope(
                $request,
                AuditArea::query()->findOrFail($validated['parentAuditAreaId']),
            );
        }
    }

    private function assertAuditAreaScope(Request $request, AuditArea $area): void
    {
        if ($request->user()->hasGlobalOfficeAccess()) {
            return;
        }

        $allowed = $request->user()->office_id
            && (
                (int) $area->responsible_office_id === (int) $request->user()->office_id
                || $area->offices()->whereKey($request->user()->office_id)->exists()
            );

        abort_unless(
            $allowed,
            403,
            'Your role is limited to audit areas covering your assigned office.',
        );
    }

    /** @return array<string, mixed> */
    private function validateAuditArea(Request $request, ?AuditArea $area = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('audit_areas', 'code')->ignore($area?->id)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'parentAuditAreaId' => [
                'nullable',
                'integer',
                Rule::exists('audit_areas', 'id')->whereNull('deleted_at'),
                Rule::notIn(array_filter([$area?->id])),
            ],
            'auditAreaTypeId' => [
                'nullable',
                'integer',
                Rule::exists('master_list_items', 'id')->where('is_active', true),
            ],
            'responsibleOfficeId' => [
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'isActive' => ['sometimes', 'boolean'],
            'officeIds' => ['required', 'array', 'min:1'],
            'officeIds.*' => ['integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateAuditFocus(Request $request, ?AuditFocus $focus = null): array
    {
        return $request->validate([
            'auditAreaId' => ['required', 'integer', Rule::exists('audit_areas', 'id')->whereNull('deleted_at')],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('audit_focuses', 'code')
                    ->where(fn ($query) => $query->where('audit_area_id', $request->integer('auditAreaId'))->whereNull('deleted_at'))
                    ->ignore($focus?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'displayOrder' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function focusAttributes(array $validated): array
    {
        return [
            'audit_area_id' => $validated['auditAreaId'],
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'display_order' => $validated['displayOrder'] ?? 0,
            'is_active' => $validated['isActive'] ?? true,
        ];
    }

    /** @return array<string, mixed> */
    private function focusData(AuditFocus $focus, bool $includeArea = false): array
    {
        $focus->loadMissing('auditArea:id,code,name');

        return [
            'id' => $focus->id,
            'auditAreaId' => $focus->audit_area_id,
            'auditAreaCode' => $includeArea ? $focus->auditArea?->code : null,
            'auditAreaName' => $includeArea ? $focus->auditArea?->name : null,
            'code' => $focus->code,
            'name' => $focus->name,
            'description' => $focus->description,
            'displayOrder' => $focus->display_order,
            'isActive' => $focus->is_active,
            'isArchived' => $focus->trashed(),
        ];
    }

    /** @return array<string, mixed> */
    private function areaData(AuditArea $area): array
    {
        $area->load([
            'parent:id,code,name',
            'children:id,parent_audit_area_id,code,name,is_active',
            'auditAreaType:id,code,label',
            'responsibleOffice:id,code,name',
            'offices:id,code,name',
            'focuses',
            'engagements' => fn ($query) => $query
                ->select(['iap_plan_engagements.id', 'plan_id', 'engagement_code', 'title', 'schedule_status'])
                ->with('plan:id,plan_code,title,status,fiscal_year'),
            'auditLogs.user:id,name',
        ]);

        return [
            'id' => $area->id,
            'code' => $area->code,
            'name' => $area->name,
            'description' => $area->description,
            'scope' => $area->scope,
            'parentAuditAreaId' => $area->parent_audit_area_id,
            'parentAuditArea' => $this->auditAreaReference($area->parent),
            'children' => $area->children
                ->map(fn (AuditArea $child): array => $this->auditAreaReference($child))
                ->values(),
            'auditAreaTypeId' => $area->audit_area_type_id,
            'auditAreaType' => $area->auditAreaType ? [
                'id' => $area->auditAreaType->id,
                'code' => $area->auditAreaType->code,
                'label' => $area->auditAreaType->label,
            ] : null,
            'responsibleOfficeId' => $area->responsible_office_id,
            'responsibleOffice' => $this->officeReference($area->responsibleOffice),
            'isActive' => $area->is_active,
            'isArchived' => $area->trashed(),
            'offices' => $area->offices,
            'focuses' => $area->focuses->map(fn (AuditFocus $focus): array => $this->focusData($focus)),
            'relatedAudits' => $this->relatedAudits($area),
            'history' => $this->areaHistory($area),
        ];
    }

    private function ensureAuditAreaType(?int $auditAreaTypeId): void
    {
        if ($auditAreaTypeId === null) {
            return;
        }

        $valid = MasterListItem::query()
            ->whereKey($auditAreaTypeId)
            ->where('is_active', true)
            ->whereHas('masterList', fn ($query) => $query
                ->where('code', 'AUDIT_AREA_TYPE')
                ->where('is_active', true))
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'auditAreaTypeId' => ['Select an active value from the Audit Area Type master list.'],
            ]);
        }
    }

    private function ensureValidAuditAreaParent(AuditArea $area, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $candidate = AuditArea::query()->find($parentId);
        while ($candidate !== null) {
            if ($candidate->is($area)) {
                throw ValidationException::withMessages([
                    'parentAuditAreaId' => ['An audit area cannot be placed below one of its own sub-audit areas.'],
                ]);
            }

            $candidate = $candidate->parent;
        }
    }

    /** @return array<string, mixed> */
    private function areaSnapshot(AuditArea $area): array
    {
        $area->unsetRelation('offices');
        $area->load('offices:id');

        return [
            ...$area->only([
                'parent_audit_area_id',
                'audit_area_type_id',
                'responsible_office_id',
                'code',
                'name',
                'description',
                'scope',
                'is_active',
            ]),
            'office_ids' => $area->offices->pluck('id')->sort()->values()->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function auditAreaReference(?AuditArea $area): ?array
    {
        return $area ? [
            'id' => $area->id,
            'code' => $area->code,
            'name' => $area->name,
            'isActive' => $area->is_active,
            'isArchived' => $area->trashed(),
        ] : null;
    }

    /** @return array<string, mixed>|null */
    private function officeReference(?Office $office): ?array
    {
        return $office ? [
            'id' => $office->id,
            'code' => $office->code,
            'name' => $office->name,
            'isArchived' => $office->trashed(),
        ] : null;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function relatedAudits(AuditArea $area)
    {
        return $area->engagements
            ->sortByDesc(fn ($engagement) => $engagement->plan?->fiscal_year)
            ->values()
            ->map(fn ($engagement): array => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'scheduleStatus' => $engagement->schedule_status,
                'planCode' => $engagement->plan?->plan_code,
                'planTitle' => $engagement->plan?->title,
                'planStatus' => $engagement->plan?->status,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function areaHistory(AuditArea $area)
    {
        return $area->auditLogs
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->user?->name ?? 'System',
                'oldValues' => $log->old_values,
                'newValues' => $log->new_values,
                'createdAt' => $log->created_at?->toIso8601String(),
            ]);
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordArea(
        Request $request,
        string $action,
        AuditArea $area,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => AuditArea::class,
            'auditable_id' => $area->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
        ActivityRecorder::record(
            $request,
            $action,
            str_replace('.', ' ', ucfirst($action)).": {$area->code} — {$area->name}.",
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: ['module' => 'CORE', 'recordType' => AuditArea::class, 'recordId' => $area->id],
        );
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordCoreChange(
        Request $request,
        string $action,
        Model $subject,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        $this->recordModelAudit($request, $action, $subject, $oldValues, $newValues);
        $label = collect(['name', 'title', 'code', 'key'])
            ->map(fn (string $key) => $subject->getAttribute($key))
            ->first(fn ($value) => filled($value));
        ActivityRecorder::record(
            $request,
            $action,
            Str::headline(strtolower(str_replace('.', ' ', $action))).': '.($label ?: class_basename($subject).' #'.$subject->getKey()),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: ['module' => 'CORE', 'recordType' => $subject::class, 'recordId' => $subject->getKey()],
        );
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordModelAudit(
        Request $request,
        string $action,
        Model $subject,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['module' => 'CORE'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        if ($request->filled('code')) {
            $request->merge([
                'code' => Str::of($request->string('code')->toString())
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '_')
                    ->trim('_')
                    ->toString(),
            ]);
        }

        return $request->validate([
            'code' => [
                $role ? 'sometimes' : 'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'code')->ignore($role?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['required', 'boolean'],
            'officeAccessScope' => [
                'sometimes',
                'string',
                Rule::in(Role::OFFICE_SCOPES),
            ],
            'engagementAccessScope' => [
                'sometimes',
                'string',
                Rule::in(Role::ENGAGEMENT_SCOPES),
            ],
            'permissionIds' => ['required', 'array'],
            'permissionIds.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')],
        ]);
    }

    /** @return array<string, mixed> */
    private function roleData(Role $role): array
    {
        $role->loadMissing([
            'permissions:id,code,name,module,action',
            'assignedUsers' => fn ($query) => $query
                ->withTrashed()
                ->select([
                    'users.id',
                    'users.role_id',
                    'users.employee_id',
                    'users.name',
                    'users.email',
                    'users.deleted_at',
                ]),
        ]);

        return [
            'id' => $role->id,
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'isSystem' => $role->is_system,
            'isActive' => $role->is_active,
            'isArchived' => $role->trashed(),
            'officeAccessScope' => $role->office_access_scope,
            'engagementAccessScope' => $role->engagement_access_scope,
            'usersCount' => $role->assignedUsers->count(),
            'users' => $role->assignedUsers
                ->sortBy('name')
                ->values()
                ->map(fn ($user): array => [
                    'id' => $user->id,
                    'employeeId' => $user->employee_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isPrimary' => (bool) $user->pivot?->is_primary,
                    'isArchived' => $user->trashed(),
                ]),
            'permissionIds' => $role->permissions->pluck('id')->sort()->values(),
            'permissions' => $role->permissions->pluck('code')->sort()->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function roleActivityValues(Role $role): array
    {
        $role->loadMissing('permissions:id,code');

        return [
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'isActive' => $role->is_active,
            'isArchived' => $role->trashed(),
            'officeAccessScope' => $role->office_access_scope,
            'engagementAccessScope' => $role->engagement_access_scope,
            'permissions' => $role->permissions->pluck('code')->sort()->values()->all(),
        ];
    }

    private function success(array $data = [], ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?: null,
        ], $status);
    }

    /** @return array<string, mixed> */
    private function masterListData(MasterList $list): array
    {
        $list->loadMissing('items');

        return [
            'id' => $list->id,
            'code' => $list->code,
            'name' => $list->name,
            'description' => $list->description,
            'isActive' => $list->is_active,
            'isConfigurable' => ! in_array($list->code, self::INTERNAL_REFERENCE_LISTS, true),
            'items' => $list->items->map(fn ($item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'label' => $item->label,
                'description' => $item->description,
                'displayOrder' => $item->display_order,
                'isActive' => $item->is_active,
            ])->values(),
        ];
    }

    private function normalizeCode(string $code): string
    {
        return Str::of($code)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
