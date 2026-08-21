<?php

declare(strict_types=1);

use App\Services\Cutoffs\CutoffPeriodCalculator;
use Illuminate\Support\Carbon;

function periodCalculator(): CutoffPeriodCalculator
{
    return new CutoffPeriodCalculator();
}

describe('Cutoff period calculator (periodos 1-15 / 16-31)', function (): void {
    it('rolls a voucher requested late in the period (day 27) to day 15 of the NEXT month', function (): void {
        $result = periodCalculator()->nextPeriodEnd(Carbon::parse('2026-08-27'), 15);

        expect($result->toDateString())->toBe('2026-09-15');
    });

    it('rolls a voucher requested early in the period (day 3) to the end of the SAME month, not the current period', function (): void {
        // La regla es "siempre el periodo siguiente al actual", nunca el
        // periodo donde se pidio -- aunque se haya pedido apenas el dia 3.
        $result = periodCalculator()->nextPeriodEnd(Carbon::parse('2026-08-03'), 15);

        expect($result->toDateString())->toBe('2026-08-31');
    });

    it('treats the cutoff day itself as still inside the first period', function (): void {
        $result = periodCalculator()->nextPeriodEnd(Carbon::parse('2026-08-15'), 15);

        expect($result->toDateString())->toBe('2026-08-31');
    });

    it('rolls the day right after the cutoff day to the next month', function (): void {
        $result = periodCalculator()->nextPeriodEnd(Carbon::parse('2026-08-16'), 15);

        expect($result->toDateString())->toBe('2026-09-15');
    });

    it('clamps the cutoff day to the days actually available in the next month', function (): void {
        // Enero 31 con cutoff_day = 30: el periodo siguiente terminaria el 30
        // de febrero, que no existe -- se ajusta al ultimo dia real de febrero.
        $result = periodCalculator()->nextPeriodEnd(Carbon::parse('2026-01-31'), 30);

        expect($result->toDateString())->toBe('2026-02-28');
    });

    it('defaults to day 15 when the branch has no cutoff_day configured', function (): void {
        $result = periodCalculator()->nextPeriodEnd(Carbon::parse('2026-08-27'), null);

        expect($result->toDateString())->toBe('2026-09-15');
    });
});
