<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_configurations')) {
            return;
        }

        $rows = [
            ['aems_reminders_enabled', 'Enable AEMS Reminders', 'true', 'boolean', 'notifications', 'Allow scheduled AEMS due-date, overdue, response, and conference reminders to be delivered.'],
            ['aems_reminder_due_hours', 'AEMS Task Due Window', '48', 'integer', 'notifications', 'Hours before an open AEMS task is due when a due-soon reminder is generated.'],
            ['aems_response_reminder_days', 'AEMS Response Reminder Window', '3', 'integer', 'notifications', 'Days ahead of a management-response due date for an AEMS reminder.'],
            ['aems_conference_reminder_days', 'AEMS Conference Reminder Window', '7', 'integer', 'notifications', 'Days ahead of a scheduled AEMS conference for a reminder.'],
        ];

        foreach ($rows as [$key, $name, $value, $type, $group, $description]) {
            DB::table('system_configurations')->updateOrInsert(
                ['key' => $key],
                compact('name', 'value', 'type', 'group', 'description') + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_configurations')) {
            DB::table('system_configurations')->whereIn('key', [
                'aems_reminders_enabled',
                'aems_reminder_due_hours',
                'aems_response_reminder_days',
                'aems_conference_reminder_days',
            ])->delete();
        }
    }
};
