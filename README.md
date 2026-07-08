# Laravel API Kit

Un kit de inicio (starter kit) de Laravel 13 listo para producción y exclusivo para APIs, que sigue las mejores prácticas del ecosistema de APIs REST para 2025-2026. Sin dependencias de frontend: una API puramente headless para aplicaciones móviles, SPAs o microservicios.

[![Versión de PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://php.net)
[![Versión de Laravel](https://img.shields.io/badge/Laravel-13.x-red)](https://laravel.com)
[![Licencia](https://img.shields.io/badge/License-MIT-green)](LICENSE)

## Características

- **Solo API** - Sin Blade, Vite ni recursos de frontend.
- **Autenticación por Tokens** - Laravel Sanctum para autenticación de móviles/SPAs.
- **Verificación de Correo Electrónico** - Flujo integrado de verificación de correo electrónico con URLs firmadas.
- **Restablecimiento de Contraseña** - Restablecimiento seguro de contraseña con flujo basado en tokens.
- **Versionado de API** - Versionado basado en URIs con soporte de depreciación a través de [grazulex/laravel-apiroute](https://github.com/Grazulex/laravel-apiroute).
- **Construcción de Consultas** - Filtrado, ordenamiento e inclusiones mediante [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder).
- **Objetos de Datos (DTOs)** - DTOs con tipado seguro a través de [spatie/laravel-data](https://github.com/spatie/laravel-data).
- **Documentación Automática** - OpenAPI 3.1 sin anotaciones mediante [dedoc/scramble](https://github.com/dedoc/scramble).
- **Pruebas Modernas** - Pest PHP con pruebas HTTP de Laravel.
- **Calidad de Código** - PHPStan (nivel máximo), Rector y Pint con reglas estrictas.
- **Límite de Tasa (Rate Limiting)** - Limitadores de tasa configurables por ruta.
- **Middleware Reutilizable** - ForceJsonResponse, LogApiRequests, EnsureEmailVerified.
- **Respuestas Estandarizadas** - Formato de respuesta JSON consistente.
- **Opcional: Idempotencia de API** - Idempotencia que cumple con RFC mediante [grazulex/laravel-api-idempotency](https://github.com/Grazulex/laravel-api-idempotency).
- **Opcional: Límite de Tasa Inteligente** - Limitación de tasa basada en planes con cuotas mediante [grazulex/laravel-api-throttle-smart](https://github.com/Grazulex/laravel-api-throttle-smart).

## Requisitos

- PHP 8.3+
- Composer 2.x
- Base de datos (SQLite por defecto, MySQL, PostgreSQL, etc.)

## Inicio Rápido

```bash
# Clonar el repositorio
git clone https://github.com/reyes200205/vouchers-platform-api.git
cd vouchers-platform-api

# Instalar dependencias
composer install

# Configurar el archivo de entorno
cp .env.example .env
php artisan key:generate

# Base de datos (SQLite por defecto)
touch database/database.sqlite
php artisan migrate

# Ejecutar pruebas para verificar la instalación
./vendor/bin/pest
```

## Documentación de la API

Una vez en ejecución, puedes acceder a la documentación autogenerada:

- **Swagger UI**: [http://localhost:8000/docs/api](http://localhost:8000/docs/api)
- **OpenAPI JSON**: [http://localhost:8000/docs/api.json](http://localhost:8000/docs/api.json)

> **Nota:** Puedes iniciar el servidor de desarrollo local ejecutando `php artisan serve`.

## Autenticación

Este kit utiliza **Laravel Sanctum** con autenticación basada en tokens (ideal para aplicaciones móviles y consumidores de API de terceros).

### Registrar un Nuevo Usuario

```bash
curl -X POST http://localhost:8000/api/v1/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

**Respuesta:**
```json
{
  "success": true,
  "message": "User registered successfully. Please check your email to verify your account.",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "email_verified_at": null,
      "created_at": "2025-01-15T10:30:00+00:00"
    },
    "token": "1|abc123..."
  }
}
```

### Iniciar Sesión (Login)

```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### Usar el Token

Incluye el token en la cabecera `Authorization` para las rutas protegidas:

```bash
curl -X GET http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"
```

### Cerrar Sesión (Logout)

```bash
curl -X POST http://localhost:8000/api/v1/logout \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"
```

### Verificación de Correo Electrónico

Después del registro, los usuarios reciben un correo electrónico de verificación. El kit se integra con el contrato `MustVerifyEmail` de Laravel.

**Verificar Correo Electrónico (a través de la URL firmada del correo):**
```bash
curl -X POST "http://localhost:8000/api/v1/email/verify/{id}/{hash}?signature=..." \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"
```

**Reenviar Correo de Verificación:**
```bash
curl -X POST http://localhost:8000/api/v1/email/resend \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "john@example.com"}'
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Verification email sent successfully",
  "data": null
}
```

### Restablecer Contraseña

**Solicitar Enlace de Restablecimiento:**
```bash
curl -X POST http://localhost:8000/api/v1/forgot-password \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "john@example.com"}'
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Password reset link sent to your email",
  "data": null
}
```

**Restablecer Contraseña (con el token del correo):**
```bash
curl -X POST http://localhost:8000/api/v1/reset-password \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "token": "reset-token-from-email",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }'
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Password reset successfully",
  "data": null
}
```

> **Nota:** Después de restablecer la contraseña con éxito, todos los tokens del usuario se revocan por seguridad.

## Puntos de Entrada de la API (Endpoints)

### Versión 1 (`/api/v1`)

| Método | Ruta                         | Autenticación | Descripción                                     | Límite de Tasa |
|--------|------------------------------|---------------|-------------------------------------------------|----------------|
| POST   | /register                    | No            | Registrar nuevo usuario                         | 5/min          |
| POST   | /login                       | No            | Obtener token de autenticación                  | 5/min          |
| POST   | /logout                      | Sí            | Revocar token actual                            | 120/min        |
| GET    | /me                          | Sí            | Obtener perfil del usuario actual               | 120/min        |
| POST   | /email/verify/{id}/{hash}    | Sí            | Verificar dirección de correo electrónico       | 120/min        |
| POST   | /email/resend                | Sí            | Reenviar correo de verificación                 | 6/min          |
| POST   | /forgot-password             | No            | Solicitar enlace de restablecimiento            | 6/min          |
| POST   | /reset-password              | No            | Restablecer contraseña con token                | 6/min          |

## Formato de Respuestas

Todas las respuestas de la API siguen un formato consistente:

### Respuesta Exitosa (Success)

```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Datos de la respuesta aquí
  }
}
```

### Respuesta de Error

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

### Códigos de Estado HTTP

| Código | Descripción |
|------|-------------|
| 200  | Éxito (Success) |
| 201  | Recurso creado |
| 204  | Sin contenido |
| 400  | Solicitud incorrecta (Bad request) |
| 401  | No autorizado (Unauthorized) |
| 403  | Prohibido (Forbidden) |
| 404  | No encontrado (Not found) |
| 422  | Error de validación |
| 429  | Demasiadas solicitudes (Too many requests) |
| 500  | Error del servidor |

## Estructura del Proyecto

```
laravel-api-kit/
├── app/
│   ├── Actions/                    # Clases de acción de propósito único
│   ├── DTOs/                       # Objetos de Transferencia de Datos (spatie/laravel-data)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── ApiController.php      # Controlador base con ApiResponse
│   │   │       └── V1/                    # Controladores de la Versión 1
│   │   │           └── AuthController.php
│   │   ├── Requests/
│   │   │   └── Api/V1/                    # Form Requests por versión
│   │   │       ├── LoginRequest.php
│   │   │       └── RegisterRequest.php
│   │   └── Resources/                     # Recursos de la API
│   │       └── UserResource.php
│   ├── Models/
│   │   └── User.php                       # Con el trait HasApiTokens
│   ├── Providers/
│   │   └── AppServiceProvider.php         # Configuración del límite de tasa
│   ├── Services/                          # Servicios de lógica de negocio
│   └── Traits/
│       └── ApiResponse.php                # Respuestas estandarizadas
├── config/
│   ├── apiroute.php                       # Configuración de versionado de API
│   ├── cors.php                           # Configuración de CORS
│   ├── sanctum.php                        # Configuración de autenticación por token
│   └── scramble.php                       # Configuración de documentación de API
├── routes/
│   ├── api.php                            # Punto de entrada de las rutas de API
│   └── api/
│       └── v1.php                         # Rutas de la versión 1
├── tests/
│   └── Feature/Api/V1/
│       └── AuthTest.php                   # Pruebas de autenticación
└── CLAUDE.md                              # Instrucciones para el asistente de IA
```

## Versionado de API

Este kit utiliza [grazulex/laravel-apiroute](https://github.com/Grazulex/laravel-apiroute) v2.x para el versionado de la API con soporte para:

- **Ruta URI** (por defecto): `/api/v1/users`, `/api/v2/users`
- **Cabecera**: `X-API-Version: 2`
- **Parámetro de consulta (Query)**: `?api_version=2`
- **Cabecera Accept**: `Accept: application/vnd.api.v2+json`

### Agregar una Nueva Versión de API

1. Crea los controladores en `app/Http/Controllers/Api/V2/`
2. Crea las solicitudes (Requests) en `app/Http/Requests/Api/V2/`
3. Crea el archivo de rutas `routes/api/v2.php`:

```php
<?php

use App\Http\Controllers\Api\V2\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
// ... más rutas
```

4. Actualiza `config/apiroute.php`:

```php
'versions' => [
    'v1' => [
        'routes' => base_path('routes/api/v1.php'),
        'status' => 'deprecated',
        'deprecated_at' => '2025-06-01',
        'sunset_at' => '2025-12-01',
        'successor' => 'v2',
    ],
    'v2' => [
        'routes' => base_path('routes/api/v2.php'),
        'status' => 'active',
    ],
],
```

### Cabeceras de Depreciación (Deprecation Headers)

Al acceder a versiones depreciadas, las respuestas incluyen cabeceras conformes con RFC:

```http
Deprecation: Sun, 01 Jun 2025 00:00:00 GMT
Sunset: Mon, 01 Dec 2025 00:00:00 GMT
Link: </api/v2>; rel="successor-version"
```

## Construcción de Consultas (Query Building)

Utiliza [spatie/laravel-query-builder](https://spatie.be/docs/laravel-query-builder) para filtrar, ordenar e incluir relaciones:

```php
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

// En tu controlador
$users = QueryBuilder::for(User::class)
    ->allowedFilters([
        'name',
        'email',
        AllowedFilter::exact('id'),
        AllowedFilter::scope('active'),
    ])
    ->allowedSorts(['name', 'created_at'])
    ->allowedIncludes(['posts', 'comments'])
    ->paginate();

return UserResource::collection($users);
```

**Ejemplos de solicitudes:**
```
GET /api/v1/users?filter[name]=john
GET /api/v1/users?sort=-created_at
GET /api/v1/users?include=posts,comments
GET /api/v1/users?filter[name]=john&sort=name&include=posts
```

## Objetos de Transferencia de Datos (DTOs)

Utiliza [spatie/laravel-data](https://spatie.be/docs/laravel-data) para DTOs con tipado seguro:

```php
// app/DTOs/UserData.php
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
    ) {}
}

// En el controlador - se valida y transforma automáticamente
public function store(UserData $data): JsonResponse
{
    $user = User::create($data->toArray());
    return $this->created(UserResource::make($user));
}
```

## Limitación de Tasa (Rate Limiting)

Configurado en `app/Providers/AppServiceProvider.php`:

| Limitador | Límite | Caso de Uso |
|-----------|--------|-------------|
| `api`     | 60/min | Por defecto para todas las rutas de API |
| `auth`    | 5/min  | Login/registro (protección contra fuerza bruta) |
| `authenticated` | 120/min | Usuarios autenticados |

### Aplicar Limitadores de Tasa

```php
// En routes/api.php
Route::middleware('throttle:auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function () {
    // Rutas protegidas con límites más altos
});
```

### Cabeceras de Límite de Tasa

Las respuestas incluyen información sobre el límite de tasa:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 60  # Cuando se excede el límite
```

## Paquetes Opcionales

Los siguientes paquetes son **sugeridos** (no requeridos) y se pueden instalar de forma individual para ampliar las capacidades del kit. Son completamente opcionales y no afectarán el comportamiento existente.

### Idempotencia de API

[grazulex/laravel-api-idempotency](https://github.com/Grazulex/laravel-api-idempotency) proporciona idempotencia que cumple con RFC para los puntos de entrada de tu API. Evita operaciones duplicadas cuando los clientes reintentan solicitudes (crítico para pagos, creación de pedidos, etc.).

**Instalación:**
```bash
composer require grazulex/laravel-api-idempotency
```

**Publicar configuración (opcional):**
```bash
php artisan vendor:publish --tag="api-idempotency-config"
```

**Uso — aplicar el middleware a las rutas de mutación:**
```php
// routes/api/v1.php
Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function () {
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware('idempotent');

    Route::post('payments', [PaymentController::class, 'store'])
        ->middleware('idempotent:required'); // Requiere la cabecera Idempotency-Key
});
```

**Lado del cliente — incluir la cabecera `Idempotency-Key`:**
```bash
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Idempotency-Key: order_unique_key_123" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 1, "quantity": 2}'
```

> **Atención:**
> - Aplica el middleware `idempotent` únicamente a rutas de mutación (POST, PUT, PATCH). Las solicitudes GET son naturalmente idempotentes.
> - El controlador de almacenamiento por defecto es `cache`. Para entornos de producción con múltiples servidores, utiliza el controlador `redis` o `database`.
> - Las claves están limitadas por usuario de forma predeterminada. Dos usuarios distintos pueden usar la misma clave sin conflicto.

---

### Límite de Tasa Inteligente (Smart Rate Limiting)

[grazulex/laravel-api-throttle-smart](https://github.com/Grazulex/laravel-api-throttle-smart) proporciona límites de tasa según el plan del usuario, con cuotas, múltiples algoritmos (ventana fija, ventana deslizante, cubo de tokens) y soporte multi-inquilino (multi-tenant). Es ideal para APIs SaaS con niveles de suscripción.

**Instalación:**
```bash
composer require grazulex/laravel-api-throttle-smart
```

**Publicar configuración:**
```bash
php artisan vendor:publish --tag="throttle-smart-config"
```

**Uso — aplicar a las rutas donde se necesite limitación basada en planes:**
```php
// routes/api/v1.php
Route::middleware(['auth:sanctum', 'throttle.smart'])->group(function () {
    Route::apiResource('posts', PostController::class);
});
```

> **Atención:**
> - Este paquete **coexiste** con el middleware `throttle:` integrado de Laravel. No necesitas eliminar los limitadores de tasa existentes.
> - Si deseas **reemplazar** el limitador nativo en rutas específicas, cambia `throttle:authenticated` por `throttle.smart` solo en esas rutas.
> - **No** apliques tanto `throttle:authenticated` como `throttle.smart` en el mismo grupo de rutas — elige uno por grupo para evitar una doble limitación de tasa.
> - El controlador por defecto es `cache`. Para entornos de producción, se recomienda `redis` para un mejor rendimiento y consistencia distribuida.
> - Configura tus planes de suscripción en `config/throttle-smart.php` para que coincidan con tu modelo de negocio (Free, Pro, Enterprise, etc.).

---

## Middlewares

El kit incluye tres middlewares listos para producción que puedes aplicar a tus rutas según lo necesites.

### Middlewares Disponibles

| Alias | Clase | Descripción |
|-------|-------|-------------|
| `force.json` | `ForceJsonResponse` | Asegura que todas las respuestas tengan formato JSON |
| `log.api` | `LogApiRequests` | Registra las solicitudes de API con información de tiempo de respuesta |
| `verified` | `EnsureEmailVerified` | Requiere correo electrónico verificado para acceder a la ruta |

### ForceJsonResponse

Establece automáticamente la cabecera `Accept: application/json` y convierte las respuestas que no sean JSON al formato JSON.

```php
Route::middleware('force.json')->group(function () {
    // Todas las respuestas serán JSON
});
```

### LogApiRequests

Registra las solicitudes de API con información detallada y agrega la cabecera `X-Response-Time` a las respuestas.

**Datos registrados:** marca de tiempo, método, URL, IP, ID de usuario, código de estado, duración (ms), user agent.

**Habilitar el registro a través del entorno:**
```env
APP_LOG_API_REQUESTS=true
```

```php
Route::middleware('log.api')->group(function () {
    // Las solicitudes serán registradas
});
```

### EnsureEmailVerified

Protege las rutas que requieren una dirección de correo electrónico verificada. Devuelve 403 si el correo no está verificado.

```php
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Solo accesible para usuarios con correos verificados
});
```

**Respuesta cuando el correo no está verificado:**
```json
{
  "success": false,
  "message": "Your email address is not verified. Please verify your email to continue."
}
```

## Pruebas (Testing)

Este kit utiliza [Pest PHP](https://pestphp.com/) para las pruebas:

```bash
# Ejecutar todas las pruebas
./vendor/bin/pest

# Ejecutar un archivo de prueba específico
./vendor/bin/pest tests/Feature/Api/V1/AuthTest.php

# Ejecutar con cobertura de código
./vendor/bin/pest --coverage

# Ejecutar en paralelo
./vendor/bin/pest --parallel
```

### Escribir Pruebas

```php
// tests/Feature/Api/V1/UserTest.php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists users for authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    User::factory()->count(5)->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'name', 'email']
            ]
        ]);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/users')
        ->assertStatus(401);
});
```

## Calidad de Código

Este kit incluye herramientas estrictas de calidad de código configuradas siguiendo los estándares de [nunomaduro/laravel-starter-kit](https://github.com/nunomaduro/laravel-starter-kit).

### Herramientas

| Herramienta | Propósito | Configuración |
|-------------|-----------|---------------|
| [PHPStan](https://phpstan.org/) + [Larastan](https://github.com/larastan/larastan) | Análisis estático (nivel máximo) | `phpstan.neon` |
| [Rector](https://getrector.com/) | Refactorización automática | `rector.php` |
| [Pint](https://laravel.com/docs/pint) | Estilo de código (reglas estrictas) | `pint.json` |

### Scripts de Composer

```bash
# Aplicar todas las correcciones (Rector + Pint)
composer lint

# Verificar sin corregir (modo CI)
composer test:lint

# Solo análisis estático
composer test:types

# Solo pruebas unitarias
composer test:unit

# Suite de pruebas completa (lint + types + unit)
composer test
```

### Reglas Estrictas Aplicadas

- `declare(strict_types=1)` en todos los archivos
- Clases `final` por defecto
- Declaraciones de tipo obligatorias
- Eliminación de código muerto
- Retornos tempranos (early returns)
- Comparaciones estrictas

### GitHub Actions

Las pruebas se ejecutan automáticamente al realizar push o PR a `main` a través de `.github/workflows/tests.yml`.

## Comandos de Desarrollo

```bash
# Listar todas las rutas
php artisan route:list

# Limpiar todas las cachés
php artisan optimize:clear

# Generar archivos de ayuda para el IDE (si usas Laravel IDE Helper)
php artisan ide-helper:generate
php artisan ide-helper:models -N

# Exportar la especificación OpenAPI a un archivo
php artisan scramble:export
```

## Configuración del Entorno

Variables clave de `.env`:

```env
# Aplicación
APP_NAME="Laravel API Kit"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base de Datos (SQLite para desarrollo)
DB_CONNECTION=sqlite

# Para MySQL/PostgreSQL
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel_api_kit
# DB_USERNAME=root
# DB_PASSWORD=secret

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1

# Versionado de API
API_VERSION_STRATEGY=uri
API_DEFAULT_VERSION=latest

# Límite de Tasa (Rate Limiting)
API_RATE_LIMIT=60

# Documentación
API_DOCS_URL=http://localhost:8000/docs/api
```

## Despliegue

### Lista de Verificación para Producción

- [ ] Establecer `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configurar una base de datos adecuada (MySQL/PostgreSQL)
- [ ] Configurar `APP_URL` con tu URL de producción
- [ ] Configurar `SANCTUM_STATEFUL_DOMAINS` para tus dominios de frontend
- [ ] Revisar y asegurar la configuración de CORS en `config/cors.php`
- [ ] Configurar límites de tasa apropiados para la carga de producción
- [ ] Configurar la caché (se recomienda Redis)
- [ ] Configurar un worker de cola (queue worker) para tareas en segundo plano
- [ ] Habilitar HTTPS y actualizar las URLs

## Extender el Kit

### Agregar un Nuevo Recurso (Ejemplo de CRUD)

1. **Crear Modelo y Migración:**
```bash
php artisan make:model Post -m
```

2. **Crear Controlador:**
```php
// app/Http/Controllers/Api/V1/PostController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Spatie\QueryBuilder\QueryBuilder;

class PostController extends ApiController
{
    public function index()
    {
        $posts = QueryBuilder::for(Post::class)
            ->allowedFilters(['title', 'status'])
            ->allowedSorts(['title', 'created_at'])
            ->allowedIncludes(['author', 'comments'])
            ->paginate();

        return $this->success(PostResource::collection($posts));
    }

    public function show(Post $post)
    {
        return $this->success(new PostResource($post));
    }

    // ... métodos store, update, destroy
}
```

3. **Crear Recurso (Resource):**
```php
// app/Http/Resources/PostResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'author' => new UserResource($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

4. **Agregar Rutas:**
```php
// routes/api/v1.php
Route::middleware('auth:sanctum')->group(function () {
    // ... rutas existentes
    Route::apiResource('posts', PostController::class);
});
```

5. **Crear Pruebas:**
```php
// tests/Feature/Api/V1/PostTest.php
uses(RefreshDatabase::class);

it('lists posts', function () {
    $user = User::factory()->create();
    Post::factory()->count(3)->create();

    $this->actingAs($user)
        ->getJson('/api/v1/posts')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});
```

## Contribuir

1. Hacer un Fork del repositorio
2. Crear una rama para tu característica (`git checkout -b feature/amazing-feature`)
3. Confirmar tus cambios (`git commit -m 'Add amazing feature'`)
4. Empujar a la rama (`git push origin feature/amazing-feature`)
5. Abrir una solicitud de extracción (Pull Request)

## Licencia

Este proyecto es software de código abierto con licencia [MIT](LICENSE).

## Créditos

- [Laravel](https://laravel.com) - El Framework de PHP
- [Laravel Sanctum](https://laravel.com/docs/sanctum) - Autenticación por Tokens de API
- [grazulex/laravel-apiroute](https://github.com/Grazulex/laravel-apiroute) - Versionado de API
- [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) - Construcción de Consultas (Query Building)
- [spatie/laravel-data](https://github.com/spatie/laravel-data) - Objetos de Transferencia de Datos (DTOs)
- [dedoc/scramble](https://github.com/dedoc/scramble) - Documentación de API
- [grazulex/laravel-api-idempotency](https://github.com/Grazulex/laravel-api-idempotency) - Idempotencia de API (opcional)
- [grazulex/laravel-api-throttle-smart](https://github.com/Grazulex/laravel-api-throttle-smart) - Límite de Tasa Inteligente (opcional)
- [Pest PHP](https://pestphp.com) - Framework de Pruebas

## Soporte

- [Documentación](https://github.com/reyes200205/vouchers-platform-api/wiki)
- [Reportar Problemas (Issues)](https://github.com/reyes200205/vouchers-platform-api/issues)
- [Discusiones](https://github.com/reyes200205/vouchers-platform-api/discussions)
