<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Formato oficial de la CURP mexicana (18 caracteres):
 * 4 letras + AAMMDD + sexo (H/M) + 5 consonantes de estado/apellidos + homoclave alfanumerica + digito.
 */
final class ValidCurp implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El campo :attribute debe ser una cadena de texto.');

            return;
        }

        if (! preg_match('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/', mb_strtoupper($value))) {
            $fail('El campo :attribute no tiene un formato de CURP valido.');
        }
    }
}
