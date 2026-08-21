<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $username
 * @property string $password
 * @property string|null $channel
 */
final class LoginRequest extends FormRequest
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
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'channel' => ['nullable', 'string', 'in:WEB,VPN_WEB,MOVIL'],
            'cf-turnstile-response' => [
                config('services.turnstile.enabled') ? 'required' : 'nullable',
                new \App\Rules\Turnstile,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cf-turnstile-response.required' => 'Por favor, completa el captcha de seguridad.',
        ];
    }
}
