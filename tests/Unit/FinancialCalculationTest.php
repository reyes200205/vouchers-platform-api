<?php

declare(strict_types=1);

use App\Services\Financial\FinancialCalculationService;

function financial(): FinancialCalculationService
{
    return new FinancialCalculationService();
}

describe('Voucher snapshot (formula del documento)', function (): void {
    it('calculates the documented example: $15,000 x 8 quincenas', function (): void {
        $snapshot = financial()->calculateVoucherSnapshot(
            principal: 15000.00,
            companyCommissionPercentage: 10.0,
            insuranceAmount: 100.00,
            fortnightlyInterestPercentage: 5.0,
            totalFortnights: 8,
            categoryCommissionPercentage: 8.0,
        );

        expect($snapshot->companyCommissionAmount)->toBe(1500.00)
            ->and($snapshot->interestPerFortnight)->toBe(750.00)
            ->and($snapshot->interestAmount)->toBe(6000.00)
            ->and($snapshot->totalDebt)->toBe(22600.00)
            ->and($snapshot->fortnightlyPayment)->toBe(2825.00)
            ->and($snapshot->distributorProfitTotal)->toBe(1200.00)
            ->and($snapshot->distributorProfitPerFortnight)->toBe(150.00)
            ->and($snapshot->totalFortnights)->toBe(8);
    });

    it('keeps the snapshot immutable regardless of later config changes', function (): void {
        $snapshot = financial()->calculateVoucherSnapshot(
            principal: 5000.00,
            companyCommissionPercentage: 10.0,
            insuranceAmount: 100.00,
            fortnightlyInterestPercentage: 5.0,
            totalFortnights: 12,
            categoryCommissionPercentage: 8.0,
        );

        $asArray = $snapshot->toArray();

        expect($asArray['total_debt_amount'])->toBe(8600.00)
            ->and($asArray['fortnightly_payment_amount'])->toBe(716.67)
            ->and($asArray['company_commission_percentage_snapshot'])->toBe(10.0)
            ->and($asArray['interest_percentage_snapshot'])->toBe(5.0)
            ->and($asArray['distributor_profit_percentage_snapshot'])->toBe(8.0);
    });

    it('produces zero commission and profit when percentages are zero', function (): void {
        $snapshot = financial()->calculateVoucherSnapshot(
            principal: 2000.00,
            companyCommissionPercentage: 0.0,
            insuranceAmount: 0.00,
            fortnightlyInterestPercentage: 0.0,
            totalFortnights: 4,
            categoryCommissionPercentage: 0.0,
        );

        expect($snapshot->totalDebt)->toBe(2000.00)
            ->and($snapshot->fortnightlyPayment)->toBe(500.00)
            ->and($snapshot->distributorProfitTotal)->toBe(0.00);
    });
});

describe('Pre-vale rule (50% del limite + tolerancia)', function (): void {
    it('allows a pre-vale up to 50% of the credit limit plus tolerance', function (): void {
        $result = financial()->validatePreVale(
            requestedAmount: 5000.00,
            availableCredit: 10000.00,
            totalCreditLimit: 10000.00,
            maxPercentage: 50.0,
            toleranceAmount: 500.00,
            ruleRequired: true,
        );

        expect($result->allowed)->toBeTrue()
            ->and($result->ruleApplied)->toBeTrue()
            ->and($result->maxAllowedAmount)->toBe(5500.00);
    });

    it('allows rounding up to the tolerance margin (e.g. $5,200 for a 50% of $10,000)', function (): void {
        $result = financial()->validatePreVale(
            requestedAmount: 5200.00,
            availableCredit: 10000.00,
            totalCreditLimit: 10000.00,
            maxPercentage: 50.0,
            toleranceAmount: 500.00,
            ruleRequired: true,
        );

        expect($result->allowed)->toBeTrue();
    });

    it('denies amounts beyond 50% plus tolerance', function (): void {
        $result = financial()->validatePreVale(
            requestedAmount: 5600.00,
            availableCredit: 10000.00,
            totalCreditLimit: 10000.00,
            maxPercentage: 50.0,
            toleranceAmount: 500.00,
            ruleRequired: true,
        );

        expect($result->allowed)->toBeFalse()
            ->and($result->ruleApplied)->toBeTrue()
            ->and($result->maxAllowedAmount)->toBe(5500.00)
            ->and($result->reason)->not->toBeNull();
    });

    it('caps the maximum at the available credit when 50% plus tolerance exceeds it', function (): void {
        $result = financial()->validatePreVale(
            requestedAmount: 800.00,
            availableCredit: 800.00,
            totalCreditLimit: 800.00,
            maxPercentage: 50.0,
            toleranceAmount: 500.00,
            ruleRequired: true,
        );

        expect($result->allowed)->toBeTrue()
            ->and($result->maxAllowedAmount)->toBe(800.00);
    });

    it('does not apply the rule when the customer already has voucher history', function (): void {
        $result = financial()->validatePreVale(
            requestedAmount: 6000.00,
            availableCredit: 6000.00,
            totalCreditLimit: 10000.00,
            maxPercentage: 50.0,
            toleranceAmount: 500.00,
            ruleRequired: false,
        );

        expect($result->allowed)->toBeTrue()
            ->and($result->ruleApplied)->toBeFalse();
    });

    it('does not apply the rule with a zero credit limit', function (): void {
        $result = financial()->validatePreVale(
            requestedAmount: 0.00,
            availableCredit: 0.00,
            totalCreditLimit: 0.00,
            maxPercentage: 50.0,
            toleranceAmount: 500.00,
            ruleRequired: false,
        );

        expect($result->allowed)->toBeTrue()
            ->and($result->ruleApplied)->toBeFalse();
    });
});

describe('Step helpers (multiplos de 100/500)', function (): void {
    it('checks multiples of 100 and 500', function (): void {
        expect(financial()->isMultipleOfStep(5000.00, 100))->toBeTrue()
            ->and(financial()->isMultipleOfStep(5050.00, 100))->toBeFalse()
            ->and(financial()->isMultipleOfStep(5000.00, 500))->toBeTrue()
            ->and(financial()->isMultipleOfStep(5500.00, 500))->toBeTrue()
            ->and(financial()->isMultipleOfStep(5200.00, 500))->toBeFalse();
    });

    it('rounds down and up to the configured step', function (): void {
        expect(financial()->roundDownToStep(5150.00, 100))->toBe(5100.00)
            ->and(financial()->roundUpToStep(5150.00, 100))->toBe(5200.00)
            ->and(financial()->roundDownToStep(5250.00, 500))->toBe(5000.00)
            ->and(financial()->roundUpToStep(5250.00, 500))->toBe(5500.00);
    });
});