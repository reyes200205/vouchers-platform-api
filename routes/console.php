<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cutoffs:generate')->dailyAt('23:50');
// El atraso (multa/interés, quita de comisión, y marcar los vales MOROSO) se
// resuelve todo a nivel de la relación de corte de la distribuidora — ver
// MarkOverdueRelationsService. Ya no existe un comando aparte a nivel de vale
// (vouchers:mark-overdue): duplicaba esa misma decisión con su propia fecha.
Schedule::command('cutoffs:mark-overdue')->dailyAt('23:55');
Schedule::command('vouchers:cancel-expired-requests')->dailyAt('00:03');
Schedule::command('vouchers:cancel-expired')->dailyAt('00:05');
Schedule::command('vouchers:send-reminders')->dailyAt('09:00');
