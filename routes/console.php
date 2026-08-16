<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cutoffs:generate')->dailyAt('23:50');
Schedule::command('cutoffs:mark-overdue')->dailyAt('23:55');
Schedule::command('vouchers:mark-overdue')->dailyAt('00:05');
Schedule::command('vouchers:send-reminders')->dailyAt('09:00');
