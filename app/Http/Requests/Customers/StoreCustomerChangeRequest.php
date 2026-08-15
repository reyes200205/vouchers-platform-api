<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Enums\ChangeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreCustomerChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'change_type' => ['required', new Enum(ChangeType::class)],
            'new_values' => ['required', 'array'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}