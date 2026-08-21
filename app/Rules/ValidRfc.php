<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Formato oficial del RFC mexicano: persona fisica (13) o moral (12),
 * letras/&/N iniciales + AAMMDD + homoclave alfanumerica de 3 caracteres.
 */
final class ValidRfc implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El campo :attribute debe ser una cadena de texto.');

            return;
        }

        if (! preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', mb_strtoupper($value))) {
            $fail('El campo :attribute no tiene un formato de RFC valido.');
        }
    }
}
