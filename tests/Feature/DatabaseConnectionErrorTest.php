<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

it('returns a generic 503 with internal code 105020 when MySQL connection fails, and logs the technical detail', function (): void {
    Route::get('/api/v1/__test/db-connection-failure', function (): never {
        throw new QueryException(
            'mysql',
            'select * from `users` where `id` = ?',
            [1],
            new PDOException('SQLSTATE[HY000] [2002] Connection refused')
        );
    });

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'Database connection failed')
                && ($context['internal_code'] ?? null) === 105020
                && isset($context['trace'])
                && isset($context['message'])
                && str_contains($context['message'], 'Connection refused');
        });

    $response = $this->getJson('/api/v1/__test/db-connection-failure');

    $response->assertStatus(503)
        ->assertExactJson([
            'code' => 105020,
            'message' => 'Servicio no disponible. Intenta nuevamente más tarde. (Código: 105020)',
        ]);
});

it('does not treat a normal SQL error as a connection failure, but still falls through to the generic fallback (code 105099, not 105020)', function (): void {
    // "Manejo normal de errores existente" para case B, tras agregar el
    // catch-all de UnexpectedErrorResponder, es ESTE: el error SQL genuino
    // ya no queda sin codigo -- tambien se vuelve rastreable por su propio
    // codigo generico, distinto del especifico de fallo de conexion.
    Route::get('/api/v1/__test/db-normal-sql-error', function (): never {
        throw new QueryException(
            'mysql',
            'select * from `nonexistent_table`',
            [],
            new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'vales.nonexistent_table' doesn't exist")
        );
    });

    $response = $this->getJson('/api/v1/__test/db-normal-sql-error');

    $response->assertStatus(500)
        ->assertExactJson([
            'code' => 105099,
            'message' => 'Ocurrió un error inesperado. Intenta nuevamente más tarde. (Código: 105099)',
        ]);
});

it('treats a raw PDOException the same way as a wrapped QueryException', function (): void {
    Route::get('/api/v1/__test/db-raw-pdo-timeout', function (): never {
        throw new PDOException('SQLSTATE[HY000] [2002] Connection timed out');
    });

    $response = $this->getJson('/api/v1/__test/db-raw-pdo-timeout');

    $response->assertStatus(503)
        ->assertExactJson([
            'code' => 105020,
            'message' => 'Servicio no disponible. Intenta nuevamente más tarde. (Código: 105020)',
        ]);
});
