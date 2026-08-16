<?php

declare(strict_types=1);

namespace App\Services\Financial;

/**
 * Resultado de la validacion de la regla del pre-vale (primer vale de la
 * distribuidora cuando tiene el 100% de su credito disponible).
 */
final class PreValeValidationResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly bool $ruleApplied,
        public readonly ?float $maxAllowedAmount,
        public readonly ?string $reason,
    ) {
    }

    public static function allowed(bool $ruleApplied = false, ?float $maxAllowedAmount = null): self
    {
        return new self(true, $ruleApplied, $maxAllowedAmount, null);
    }

    public static function denied(string $reason, float $maxAllowedAmount): self
    {
        return new self(false, true, $maxAllowedAmount, $reason);
    }
}