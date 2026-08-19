<?php

declare(strict_types=1);

namespace App\Services\Financial;

/**
 * Motor de calculo financiero puro (sin estado, sin BD).
 *
 * Formula del vale (documento del proyecto):
 *   Comision empresa   = Principal x comision%
 *   Seguro             = monto de seguro
 *   Interes quincenal  = Principal x interes%  (solo sobre el principal)
 *   Total deuda        = Principal + Comision + Seguro + (Interes quincenal x quincenas)
 *   Pago quincenal     = Total deuda / quincenas
 *   Utilidad dist.     = Principal x comision de la categoria
 *
 * Ejemplo del documento: $15,000 a 8 quincenas, comision 10%, seguro $100,
 * interes 5% => $15,000 + $1,500 + $100 + $6,000 = $22,600 ($2,825 por quincena).
 *
 * Regla del pre-vale: cuando la distribuidora tiene el 100% de su credito
 * disponible, el primer vale no puede superar el 50% del disponible mas una
 * tolerancia de redondeo (default $500) para respetar multiplos de 100/500.
 */
final class FinancialCalculationService
{
    public function calculateVoucherSnapshot(
        float $principal,
        float $companyCommissionPercentage,
        float $insuranceAmount,
        float $fortnightlyInterestPercentage,
        int $totalFortnights,
        float $categoryCommissionPercentage,
        float $lateFeeAmount = 0.0,
    ): VoucherSnapshot {
        $companyCommissionAmount = round($principal * $companyCommissionPercentage / 100, 2);
        $interestPerFortnight = round($principal * $fortnightlyInterestPercentage / 100, 2);
        $interestAmount = round($interestPerFortnight * $totalFortnights, 2);
        $totalDebt = round(
            $principal + $companyCommissionAmount + $insuranceAmount + $interestAmount,
            2
        );
        $fortnightlyPayment = round($totalDebt / $totalFortnights, 2);
        $distributorProfitTotal = round($principal * $categoryCommissionPercentage / 100, 2);
        $distributorProfitPerFortnight = round($distributorProfitTotal / $totalFortnights, 2);

        return new VoucherSnapshot(
            principal: $principal,
            companyCommissionPercentage: $companyCommissionPercentage,
            companyCommissionAmount: $companyCommissionAmount,
            insuranceAmount: $insuranceAmount,
            interestPercentage: $fortnightlyInterestPercentage,
            interestPerFortnight: $interestPerFortnight,
            interestAmount: $interestAmount,
            lateFeeAmount: $lateFeeAmount,
            distributorProfitPercentage: $categoryCommissionPercentage,
            distributorProfitTotal: $distributorProfitTotal,
            distributorProfitPerFortnight: $distributorProfitPerFortnight,
            totalDebt: $totalDebt,
            fortnightlyPayment: $fortnightlyPayment,
            totalFortnights: $totalFortnights,
        );
    }

    /**
     * Valida la regla del pre-vale. La regla aplica cuando el credito
     * disponible es exactamente el total (100% disponible) -es decir, el primer
     * vale de la vida de la distribuidora- o cuando `$reactivationPending` es
     * true porque un gerente acaba de autorizar un aumento de linea de credito
     * (ver `Distributor::prevale_required_after_credit_increase_at`): en ese
     * caso el 50% + tolerancia vuelve a aplicar sobre el credito disponible del
     * siguiente vale, aunque la distribuidora ya tuviera vales activos.
     *
     * @param  float  $maxPercentage  Porcentaje maximo del pre-vale (default 50).
     * @param  float  $toleranceAmount  Tolerancia de redondeo en pesos (default 500).
     * @param  bool  $reactivationPending  True si la distribuidora tiene un aumento
     *                                     de linea reciente que aun no se aplico a
     *                                     ningun vale.
     */
    public function validatePreVale(
        float $requestedAmount,
        float $availableCredit,
        float $totalCreditLimit,
        float $maxPercentage,
        float $toleranceAmount,
        bool $reactivationPending = false,
    ): PreValeValidationResult {
        $hasFullCreditAvailable = $totalCreditLimit > 0 && abs($availableCredit - $totalCreditLimit) < 0.01;

        if (! $hasFullCreditAvailable && ! $reactivationPending) {
            return PreValeValidationResult::allowed();
        }

        $maxAllowedAmount = min(
            $availableCredit,
            round($availableCredit * $maxPercentage / 100 + $toleranceAmount, 2)
        );

        if ($requestedAmount > $maxAllowedAmount) {
            return PreValeValidationResult::denied(
                'El monto supera el máximo permitido para el primer vale (50% del crédito disponible).',
                $maxAllowedAmount
            );
        }

        return PreValeValidationResult::allowed(true, $maxAllowedAmount);
    }

    public function isMultipleOfStep(float $amount, int $step): bool
    {
        if ($step <= 0) {
            return true;
        }

        $remainder = fmod($amount, $step);

        return abs($remainder) < 0.001 || abs($remainder - $step) < 0.001;
    }

    public function roundDownToStep(float $amount, int $step): float
    {
        return floor($amount / $step) * $step;
    }

    public function roundUpToStep(float $amount, int $step): float
    {
        return ceil($amount / $step) * $step;
    }
}
