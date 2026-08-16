<?php

declare(strict_types=1);

namespace App\Services\Financial;

/**
 * Snapshot financiero de un vale. Inmutable: se calcula al emitir y no cambia
 * aunque el catalogo o la configuracion se modifiquen despues.
 */
final class VoucherSnapshot
{
    public function __construct(
        public readonly float $principal,
        public readonly float $companyCommissionPercentage,
        public readonly float $companyCommissionAmount,
        public readonly float $insuranceAmount,
        public readonly float $interestPercentage,
        public readonly float $interestPerFortnight,
        public readonly float $interestAmount,
        public readonly float $lateFeeAmount,
        public readonly float $distributorProfitPercentage,
        public readonly float $distributorProfitTotal,
        public readonly float $distributorProfitPerFortnight,
        public readonly float $totalDebt,
        public readonly float $fortnightlyPayment,
        public readonly int $totalFortnights,
    ) {
    }

    /**
     * Retorna el snapshot con las mismas claves que las columnas de la tabla
     * vouchers, para persistirlo 1 a 1 al aprobar el vale.
     *
     * @return array<string, float|int>
     */
    public function toArray(): array
    {
        return [
            'principal' => $this->principal,
            'company_commission_percentage_snapshot' => $this->companyCommissionPercentage,
            'company_commission_amount' => $this->companyCommissionAmount,
            'insurance_amount_snapshot' => $this->insuranceAmount,
            'interest_percentage_snapshot' => $this->interestPercentage,
            'interest_amount' => $this->interestAmount,
            'late_fee_amount_snapshot' => $this->lateFeeAmount,
            'distributor_profit_percentage_snapshot' => $this->distributorProfitPercentage,
            'distributor_profit_amount' => $this->distributorProfitTotal,
            'total_debt_amount' => $this->totalDebt,
            'fortnightly_payment_amount' => $this->fortnightlyPayment,
            'total_fortnights' => $this->totalFortnights,
        ];
    }
}