<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use App\Models\Application;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DecideApplicationRequest extends FormRequest
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
        // La categoria elegida debe pertenecer a la misma sucursal de la
        // solicitud; de lo contrario la distribuidora queda con una categoria
        // de otra sucursal (y por lo tanto con una comision que no corresponde
        // a su sucursal, rompiendo el calculo del vale).
        /** @var Application|null $application */
        $application = $this->route('application');

        return [
            'decision' => ['required', 'in:APPROVE,REJECT'],
            'credit_limit' => ['required_if:decision,APPROVE', 'nullable', 'decimal:0,2', 'min:0'],
            'category_id' => [
                'required_if:decision,APPROVE',
                'nullable',
                'integer',
                Rule::exists('distributor_categories', 'id')
                    ->when($application, fn ($query) => $query->where('branch_id', $application->branch_id)),
            ],
            'coordinator_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'rejection_reason' => ['required_if:decision,REJECT', 'nullable', 'string'],
        ];
    }
}
