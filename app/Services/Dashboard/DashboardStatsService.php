<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;

final class DashboardStatsService
{
    private const ACTIVE_VOUCHER_STATUSES = ['ACTIVO', 'PAGO_PARCIAL', 'MOROSO'];

    /**
     * @return array{credit_placed: float, delinquency_rate: float, collections_today: float, active_vouchers: int}
     */
    public function summary(?int $branchId = null): array
    {
        $creditPlaced = DB::table('distributors')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'ACTIVA')
            ->sum('credit_limit');

        $delinquency = DB::table('cutoff_relations')
            ->when($branchId, function ($query) use ($branchId) {
                return $query->whereIn('cutoff_id', function ($sub) use ($branchId) {
                    $sub->select('id')->from('cutoffs')->where('branch_id', $branchId);
                });
            })
            ->selectRaw("SUM(CASE WHEN status IN ('VENCIDA','CERRADA') THEN total_amount_due ELSE 0 END) as overdue")
            ->selectRaw("SUM(CASE WHEN status IN ('GENERADA','PARCIAL','VENCIDA','CERRADA') THEN total_amount_due ELSE 0 END) as total")
            ->first();

        $overdue = (float) ($delinquency->overdue ?? 0);
        $total = (float) ($delinquency->total ?? 0);
        $delinquencyRate = $total > 0 ? round($overdue / $total * 100, 2) : 0.0;

        $collectionsToday = DB::table('customer_payments')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereNull('reversed_at')
            ->whereDate('payment_date', now()->toDateString())
            ->sum('amount');

        $activeVouchers = DB::table('vouchers')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', self::ACTIVE_VOUCHER_STATUSES)
            ->count();

        return [
            'credit_placed' => (float) $creditPlaced,
            'delinquency_rate' => $delinquencyRate,
            'collections_today' => (float) $collectionsToday,
            'active_vouchers' => $activeVouchers,
        ];
    }

    /**
     * @return array{monthly_placement: array<int, array{month: string, amount: float}>, monthly_collections: array<int, array{month: string, amount: float}>}
     */
    public function monthlySeries(?int $branchId = null): array
    {
        $months = collect(range(11, 0))
            ->map(fn (int $i): string => now()->startOfMonth()->subMonths($i)->format('Y-m'));
        $from = $months->first().'-01';

        $placement = DB::table('vouchers')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereNotNull('issued_at')
            ->where('issued_at', '>=', $from)
            ->selectRaw($this->monthExpression('issued_at').' as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $collections = DB::table('customer_payments')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereNull('reversed_at')
            ->where('payment_date', '>=', $from)
            ->selectRaw($this->monthExpression('payment_date').' as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'monthly_placement' => $months
                ->map(fn (string $month): array => ['month' => $month, 'amount' => (float) ($placement[$month] ?? 0)])
                ->values()
                ->all(),
            'monthly_collections' => $months
                ->map(fn (string $month): array => ['month' => $month, 'amount' => (float) ($collections[$month] ?? 0)])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(?int $branchId = null): array
    {
        return array_merge($this->summary($branchId), $this->monthlySeries($branchId));
    }

    private function monthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}