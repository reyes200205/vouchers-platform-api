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
 *   Utilidad dist.     = Principal x comision de la categoria
 *   Total deuda        = Principal + Comision + Seguro + (Interes quincenal x quincenas) - Utilidad dist.
 *   Pago quincenal     = floor(Total deuda / quincenas)
 *
 * La utilidad de la distribuidora NO se le cobra al cliente aparte: sale de
 * lo que ya cobra la empresa, asi que se resta del total antes de dividir
 * entre quincenas (y el pago quincenal siempre se redondea al piso, nunca al
 * mas cercano).
 *
 * Ejemplo: $15,000 a 8 quincenas, comision 10% ($1,500), seguro $100,
 * interes 3% ($3,600 en 8 quincenas), categoria 6% ($900 de utilidad
 * distribuidora) => $15,000 + $1,500 + $100 + $3,600 - $900 = $19,300
 * ($2,412.50 -> $2,412 por quincena, redondeado al piso).
 *
 * Regla del pre-vale: cuando la distribuidora tiene el 100% de su credito
 * disponible, el primer vale no puede superar el 50% del disponible mas una
 * tolerancia de redondeo (default $500) para respetar multiplos de 100/500.
 * La misma regla se fuerza tambien cuando reactivationPending es true (la
 * distribuidora acaba de recibir un aumento de linea de credito), aunque en
 * ese momento no tenga el 100% disponible, para que el primer vale tras el
 * aumento tambien respete el limite del 50%.
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
        $distributorProfitTotal = round($principal * $categoryCommissionPercentage / 100, 2);
        $distributorProfitPerFortnight = round($distributorProfitTotal / $totalFortnights, 2);
        // La utilidad de la distribuidora sale de lo que ya cobra la empresa: no es
        // un cargo adicional para el cliente, asi que se resta del total que el
        // cliente realmente debe.
        $totalDebt = round(
            $principal + $companyCommissionAmount + $insuranceAmount + $interestAmount - $distributorProfitTotal,
            2
        );
        // El pago quincenal siempre se redondea al piso, al peso entero (regla de
        // negocio) -- no a los centavos y nunca al mas cercano: floor(totalDebt /
        // quincenas), no round().
        $fortnightlyPayment = floor($totalDebt / $totalFortnights);

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
     * Valida la regla del pre-vale.
     *
     * @param  float  $maxPercentage  Porcentaje maximo del pre-vale (default 50).
     * @param  float  $toleranceAmount  Tolerancia de redondeo en pesos (default 500).
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