<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Keeps offices independent while adding hierarchy and ownership to Audit Areas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table): void {
            $table->foreignId('office_type_id')
                ->nullable()
                ->after('acronym')
                ->constrained('master_list_items')
                ->nullOnDelete();
        });

        Schema::table('audit_areas', function (Blueprint $table): void {
            $table->foreignId('parent_audit_area_id')
                ->nullable()
                ->after('id')
                ->constrained('audit_areas')
                ->nullOnDelete();
            $table->foreignId('audit_area_type_id')
                ->nullable()
                ->after('parent_audit_area_id')
                ->constrained('master_list_items')
                ->nullOnDelete();
            $table->foreignId('responsible_office_id')
                ->nullable()
                ->after('audit_area_type_id')
                ->constrained('offices')
                ->nullOnDelete();
            $table->text('scope')->nullable()->after('description');
        });

        $officeTypeListId = $this->upsertList(
            'OFFICE_TYPE',
            'Office Type',
            'Independent organizational classifications used by the Office Registry.',
        );
        $officeTypes = [
            'DEPARTMENT' => 'Department',
            'OFFICE' => 'Office',
            'DIVISION' => 'Division',
            'SECTION' => 'Section',
            'UNIT' => 'Unit',
            'SPECIAL_BODY' => 'Special Office / Body',
        ];
        $officeTypeIds = [];
        foreach ($officeTypes as $code => $label) {
            $officeTypeIds[$code] = $this->upsertItem($officeTypeListId, $code, $label);
        }

        DB::table('offices')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->each(function (object $office) use ($officeTypeIds): void {
                $name = strtoupper((string) $office->name);
                $type = match (true) {
                    str_contains($name, 'DEPARTMENT') => 'DEPARTMENT',
                    str_contains($name, 'DIVISION') => 'DIVISION',
                    str_contains($name, 'SECTION') => 'SECTION',
                    str_contains($name, 'UNIT') => 'UNIT',
                    str_contains($name, 'HOSPITAL'),
                    str_contains($name, 'BOARD'),
                    str_contains($name, 'COUNCIL') => 'SPECIAL_BODY',
                    default => 'OFFICE',
                };
                DB::table('offices')
                    ->where('id', $office->id)
                    ->update(['office_type_id' => $officeTypeIds[$type]]);
            });

        $areaTypeListId = $this->upsertList(
            'AUDIT_AREA_TYPE',
            'Audit Area Type',
            'Reusable classifications for parent and sub-audit areas.',
        );
        $areaTypes = [
            'PROCESS' => 'Process',
            'SYSTEM' => 'System',
            'PROGRAM' => 'Program',
            'FUNCTION' => 'Function',
            'THEME' => 'Theme',
        ];
        $areaTypeIds = [];
        foreach ($areaTypes as $code => $label) {
            $areaTypeIds[$code] = $this->upsertItem($areaTypeListId, $code, $label);
        }

        DB::table('audit_areas')
            ->select(['id', 'description'])
            ->orderBy('id')
            ->each(function (object $area) use ($areaTypeIds): void {
                $responsibleOfficeId = DB::table('audit_area_office')
                    ->where('audit_area_id', $area->id)
                    ->min('office_id');
                DB::table('audit_areas')
                    ->where('id', $area->id)
                    ->update([
                        'audit_area_type_id' => $areaTypeIds['PROCESS'],
                        'responsible_office_id' => $responsibleOfficeId,
                        'scope' => $area->description,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('audit_areas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_audit_area_id');
            $table->dropConstrainedForeignId('audit_area_type_id');
            $table->dropConstrainedForeignId('responsible_office_id');
            $table->dropColumn('scope');
        });

        Schema::table('offices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('office_type_id');
        });

        DB::table('master_lists')
            ->whereIn('code', ['OFFICE_TYPE', 'AUDIT_AREA_TYPE'])
            ->delete();
    }

    private function upsertList(string $code, string $name, string $description): int
    {
        DB::table('master_lists')->updateOrInsert(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('master_lists')->where('code', $code)->value('id');
    }

    private function upsertItem(int $listId, string $code, string $label): int
    {
        DB::table('master_list_items')->updateOrInsert(
            ['master_list_id' => $listId, 'code' => $code],
            [
                'label' => $label,
                'description' => null,
                'display_order' => 0,
                'is_active' => true,
                'deleted_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('master_list_items')
            ->where('master_list_id', $listId)
            ->where('code', $code)
            ->value('id');
    }
};
