<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreApplicationVerificationRequest extends FormRequest
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
            'result' => ['required', 'in:VERIFICADA,RECHAZADA'],
            'notes' => [Rule::requiredIf(fn () => $this->input('result') === 'RECHAZADA'), 'nullable', 'string'],
            'verification_latitude' => ['nullable', 'decimal:0,7'],
            'verification_longitude' => ['nullable', 'decimal:0,8'],
            'visit_date' => ['required', 'date'],
            'checklist' => ['nullable', 'array'],
            'justifications' => ['nullable', 'array'],
            // La foto de fachada la toma y sube el verificador durante la visita
            // (ver VerificationPhotoController::store), por lo que es obligatoria aqui.
            // La INE y el comprobante ya los subio el coordinador al capturar la
            // solicitud (id_front_path/proof_of_address_path); el verificador solo
            // los revisa en pantalla, no los vuelve a subir, asi que aqui son opcionales.
            'front_photo' => ['required', 'string', 'max:255'],
            'id_with_person_photo' => ['nullable', 'string', 'max:255'],
            'proof_of_address_photo' => ['nullable', 'string', 'max:255'],
            'additional_evidence' => ['nullable', 'array'],
            'distance_meters' => ['nullable', 'decimal:0,2', 'min:0'],
        ];
    }
}
