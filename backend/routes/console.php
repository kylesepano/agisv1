<?php

// Registers scheduled notification reminders and local maintenance commands.
use App\Services\NotificationReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'notifications:dispatch-reminders',
    function (NotificationReminderService $reminders): void {
        $count = $reminders->dispatch();
        $this->info("Notification reminder dispatch complete: {$count} deliveries processed.");
    },
)->purpose('Create idempotent workflow, IAP, AEMS, and CMS reminders and reviewable candidates.');

Schedule::command('notifications:dispatch-reminders')
    ->dailyAt('07:00')
    ->withoutOverlapping();
