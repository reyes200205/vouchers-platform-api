<?php

declare(strict_types=1);

use App\Exceptions\SpacesStorageException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportException;

it('returns a generic 503 with internal code 105030 when an outbound HTTP call cannot connect, and logs the technical detail', function (): void {
    Route::get('/api/v1/__test/external-service-down', function (): never {
        throw new ConnectionException('cURL error 7: Failed to connect to challenges.cloudflare.com port 443: Connection refused');
    });

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'External service connection failed')
                && ($context['internal_code'] ?? null) === 105030
                && isset($context['trace']);
        });

    $response = $this->getJson('/api/v1/__test/external-service-down');

    $response->assertStatus(503)
        ->assertExactJson([
            'code' => 105030,
            'message' => 'No pudimos completar tu solicitud. Intenta nuevamente más tarde. (Código: 105030)',
        ]);
});

it('returns a generic 500 with internal code 105099 for an unanticipated bug, and logs the technical detail', function (): void {
    Route::get('/api/v1/__test/unexpected-bug', function (): never {
        throw new TypeError('Argument #1 ($x) must be of type int, string given');
    });

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'Unexpected error')
                && ($context['internal_code'] ?? null) === 105099
                && $context['exception_class'] === TypeError::class
                && isset($context['trace']);
        });

    $response = $this->getJson('/api/v1/__test/unexpected-bug');

    $response->assertStatus(500)
        ->assertExactJson([
            'code' => 105099,
            'message' => 'Ocurrió un error inesperado. Intenta nuevamente más tarde. (Código: 105099)',
        ]);
});

it('returns a generic 503 with internal code 105040 when saving to DigitalOcean Spaces fails, and logs the technical detail', function (): void {
    Route::get('/api/v1/__test/spaces-storage-down', function (): never {
        throw new SpacesStorageException('No se pudo guardar el archivo en DigitalOcean Spaces. Revisa la configuración y los permisos de la llave.');
    });

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'DigitalOcean Spaces storage failed')
                && ($context['internal_code'] ?? null) === 105040
                && isset($context['trace']);
        });

    $response = $this->getJson('/api/v1/__test/spaces-storage-down');

    $response->assertStatus(503)
        ->assertExactJson([
            'code' => 105040,
            'message' => 'No pudimos guardar tu archivo. Intenta nuevamente más tarde. (Código: 105040)',
        ]);
});

it('returns a generic 503 with internal code 105050 when sending an email fails, and logs the technical detail', function (): void {
    Route::get('/api/v1/__test/mail-transport-down', function (): never {
        throw new TransportException('Connection could not be established with host smtp.example.com :Connection refused');
    });

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'Mail transport failed')
                && ($context['internal_code'] ?? null) === 105050
                && isset($context['trace']);
        });

    $response = $this->getJson('/api/v1/__test/mail-transport-down');

    $response->assertStatus(503)
        ->assertExactJson([
            'code' => 105050,
            'message' => 'No pudimos enviar el correo. Intenta nuevamente más tarde. (Código: 105050)',
        ]);
});

it('does not intercept a validation exception -- keeps the normal errors array shape', function (): void {
    Route::get('/api/v1/__test/validation-error', function (): never {
        throw ValidationException::withMessages([
            'email' => ['The email field is required.'],
        ]);
    });

    $response = $this->getJson('/api/v1/__test/validation-error');

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'The email field is required.');

    expect($response->json('code'))->toBeNull();
});

it('does not intercept a 404 -- keeps the existing NotFoundHttpException shape', function (): void {
    $response = $this->getJson('/api/v1/__test/this-route-does-not-exist');

    $response->assertStatus(404)
        ->assertExactJson([
            'success' => false,
            'message' => 'Endpoint not found',
        ]);
});

it('does not intercept a 403 -- keeps its own status and message', function (): void {
    Route::get('/api/v1/__test/forbidden-action', function (): never {
        abort(403, 'No tienes permiso para esto.');
    });

    $response = $this->getJson('/api/v1/__test/forbidden-action');

    $response->assertStatus(403);
    expect($response->json('code'))->not->toBe(105099);
});
