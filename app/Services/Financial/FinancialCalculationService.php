<?php

declare(strict_types=1);

namespace App\Services\Financial;

/**
 * Motor de calculo financiero puro (sin estado, sin BD).
 *
 * Formula del vale (documento del proyecto, corregida):
 *   Comision empresa   = Principal x comision%
 *   Seguro             = monto de seguro
 *   Interes quincenal  = Principal x interes%  (solo sobre el principal)
 *   Utilidad dist.     = Principal x comision de la categoria
 *   Total deuda        = Principal + Comision + Seguro + (Interes quincenal x quincenas)
 *   Pago quincenal     = floor(Total deuda / quincenas)
 *
 * La utilidad de la distribuidora NO se resta aqui: la distribuidora le
 * cobra al cliente la quincena COMPLETA (con su comision incluida) para
 * poder ganarsela. La comision solo se descuenta despues, cuando la
 * distribuidora le rinde cuentas a la sucursal en el corte de relacion
 * (GenerateCutoffService) -- ahi si le corresponde remitir nada mas el
 * neto, porque no le va a pagar su propia comision a la sucursal. Este
 * servicio solo calcula el total deuda y calcula el pago quincenal
 * COMPLETO; la utilidad de la distribuidora (distributorProfitTotal /
 * distributorProfitPerFortnight) se sigue calculando aparte, mas que nada
 * como dato informativo y para que otros servicios (limite de credito,
 * corte de relacion) sepan cuanto le corresponde descontar despues.
 *
 * Ejemplo: $15,000 a 8 quincenas, comision 10% ($1,500), seguro $100,
 * interes 3% ($3,600 en 8 quincenas) => $15,000 + $1,500 + $100 + $3,600 =
 * $20,200 ($2,525.00 por quincena, lo que la distribuidora le cobra
 * completo al cliente). Si la categoria de la distribuidora es 6% ($900 de
 * utilidad total, $112.50 por quincena), en el corte de relacion remite
 * $2,525 - $112.50 = $2,412.50 -> $2,412 (redondeado al piso), quedandose
 * ella con los $112.50 de comision.
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
        // Total deuda: lo que el cliente debe pagar en total (completo, con la
        // comision de la distribuidora incluida -- la distribuidora se la cobra al
        // cliente para poder ganarsela). NO se resta aqui la utilidad de la
        // distribuidora; eso se descuenta despues, solo cuando la distribuidora le
        // rinde cuentas a la sucursal (ver GenerateCutoffService).
        $totalDebt = round(
            $principal + $companyCommissionAmount + $insuranceAmount + $interestAmount,
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