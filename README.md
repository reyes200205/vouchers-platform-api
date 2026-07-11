# Laravel API Kit

Un starter kit de Laravel 13 diseñado para el desarrollo rápido de APIs REST listas para producción. Este kit sigue los más altos estándares del ecosistema de APIs, eliminando cualquier dependencia de frontend (Blade, Vite, etc.) para servir como una API puramente headless (móviles, SPAs, microservicios).

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## 🚀 Documentación Automática de la API (Swagger UI)

Este kit cuenta con **documentación autogenerada e interactiva** gracias a [Dedoc Scramble](https://github.com/dedoc/scramble). 

> [!IMPORTANT]
> **No necesitas escribir anotaciones complejas ni código repetitivo.** Scramble analiza estáticamente el código, los Form Requests y los API Resources en tiempo real. Cualquier cambio en tus validaciones se reflejará instantáneamente en la documentación.

Una vez que el servidor esté en ejecución (`php artisan serve`), puedes acceder a:
*   **Documentación Interactiva (Swagger/Stoplight UI):** [http://localhost:8000/docs/api](http://localhost:8000/docs/api)
*   **Especificación OpenAPI JSON:** [http://localhost:8000/docs/api.json](http://localhost:8000/docs/api.json)

> [!TIP]
> **Autenticación en la documentación:** La documentación incluye soporte nativo para **Bearer Tokens (Sanctum)**. Solo debes registrarte o hacer login desde la UI, copiar el token devuelto, hacer clic en el botón **"Authorize"** en la esquina superior derecha y pegarlo. Las peticiones a rutas protegidas se enviarán con el token automáticamente.

---

## 🛠️ Buenas Prácticas y Patrones de Diseño

Este kit fomenta la separación de responsabilidades y la mantenibilidad del código:

*   **Capa de Servicios (Service Layer):** Toda la lógica de negocio compleja y las transacciones de base de datos se encapsulan en clases de servicio dedicadas (ej. `StoreBranchService.php`). Los controladores se mantienen limpios ("Slim Controllers") e inyectan estos servicios.
*   **Validaciones Dedicadas (Form Requests):** Las reglas de validación se separan en clases `FormRequest` personalizadas y organizadas por carpetas (ej. `StoreBranchRequest.php`), manteniendo el código del controlador enfocado únicamente en gestionar la respuesta HTTP.
*   **Respuestas Estandarizadas (ApiResponse Trait):** Respuestas JSON uniformes para toda la API (éxitos, errores de validación, no encontrados, no autorizados, etc.) mediante el trait `ApiResponse`.
*   **Transformación de Datos (API Resources):** Uso de `JsonResource` (ej. `BranchResource.php`) para desacoplar la estructura de la base de datos de los JSON expuestos al cliente, protegiendo información sensible.
*   **Versionado de API Eficiente:** Versionado de rutas basado en URIs con soporte para cabeceras de obsolescencia (Deprecation y Sunset) mediante `apiroute`.
*   **Pruebas Robustas:** Setup de pruebas moderno utilizando Pest PHP, configurado con base de datos en memoria para mayor velocidad de ejecución.

---

## 📁 Estructura del Proyecto

El código está organizado de manera modular por dominios de negocio:

```text
laravel-api-kit/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                   # Controladores para Autenticación (Login, Registro, Password)
│   │   │   ├── Branches/               # Controladores del módulo de Sucursales
│   │   │   └── ApiController.php       # Controlador base con ApiResponse integrado
│   │   ├── Requests/
│   │   │   ├── Auth/                   # Validaciones de autenticación (LoginRequest, etc.)
│   │   │   └── Branches/               # Validaciones de sucursales (StoreBranchRequest, etc.)
│   │   ├── Resources/                  # Recursos API para transformar respuestas JSON
│   │   │   ├── BranchResource.php
│   │   │   └── UserResource.php
│   ├── Services/                       # Capa de lógica de negocio (Servicios)
│   │   └── Branches/                   # Servicios de sucursales (ej. StoreBranchService)
│   ├── Models/                         # Modelos Eloquent de base de datos
│   │   ├── Address.php
│   │   ├── Branch.php
│   │   ├── Employee.php
│   │   ├── Person.php
│   │   └── User.php
│   ├── Traits/
│   │   └── ApiResponse.php             # Métodos helpers para respuestas JSON estructuradas
│   └── Providers/
│       └── AppServiceProvider.php      # Proveedor central y configuración de rate limiters
├── database/
│   ├── migrations/                     # Migraciones de base de datos
│   └── seeders/                        # Semillas para inicialización de datos de prueba
├── routes/
│   ├── api.php                         # Punto de entrada de la API
│   └── api/
│       └── v1.php                      # Rutas correspondientes a la Versión 1
└── tests/
    └── Feature/Api/V1/                 # Pruebas funcionales e HTTP escritas con Pest
```

---

## 🚀 Inicio Rápido

1. **Instalar Dependencias:**
   ```bash
   composer install
   ```

2. **Configurar el Entorno:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Migrar y Sembrar la Base de Datos:**
   ```bash
   touch database/database.sqlite
   php artisan migrate:fresh --seed
   ```

4. **Levantar el Servidor de Desarrollo:**
   ```bash
   php artisan serve
   ```

---

## 🧪 Pruebas y Calidad de Código

El kit incluye una suite completa de herramientas para asegurar la calidad de software:

### Ejecutar Pruebas con Pest
```bash
# Ejecutar todas las pruebas
./vendor/bin/pest

# Ejecutar un archivo específico
./vendor/bin/pest tests/Feature/Api/V1/BranchTest.php
```

### Calidad de Código y Estilos
```bash
# Corregir de forma automática estilos y reglas (Rector + Pint)
composer lint

# Ejecutar análisis estático con PHPStan / Larastan (Nivel Máximo)
composer test:types
```

---

## 🛡️ Limitación de Tasa (Rate Limiting)

El kit cuenta con limitadores de tasa configurados y aplicados a nivel de middleware en `app/Providers/AppServiceProvider.php` para proteger la API de abusos:

| Limitador | Límite | Caso de Uso |
|-----------|--------|-------------|
| `api`     | 60 peticiones/min | Límite por defecto para rutas públicas o genéricas |
| `auth`    | 5 peticiones/min  | Endpoints de Login y Registro (protección contra fuerza bruta) |
| `authenticated` | 120 peticiones/min | Para usuarios autenticados a través de Sanctum |

### Cabeceras de Rate Limit devueltas:
```http
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 119
Retry-After: 60  # Indica los segundos de espera si se supera el límite (Error 429)
```

---

## 🚦 Códigos de Estado HTTP

Todas las respuestas devuelven códigos de estado semánticos estándar:

| Código | Estado | Descripción |
|------|-------------|-------------|
| **200** | OK | Éxito para solicitudes de lectura o actualización general |
| **201** | Created | Recurso creado exitosamente (ej. tras un POST exitoso) |
| **204** | No Content | Solicitud procesada correctamente sin datos de retorno (ej. DELETE) |
| **400** | Bad Request | Solicitud incorrecta o mal estructurada |
| **401** | Unauthorized | Falta autenticación o las credenciales no son válidas |
| **403** | Forbidden | Usuario autenticado pero sin los permisos o roles necesarios |
| **404** | Not Found | El recurso solicitado no existe |
| **422** | Unprocessable Content | Error de validación de datos (la petición no cumple las reglas) |
| **429** | Too Many Requests | Límite de tasa excedido (Rate Limit alcanzado) |
| **500** | Internal Server Error | Error inesperado en el servidor |

---

## 🛠️ Tecnologías Incluidas

*   **Laravel Sanctum:** Autenticación por tokens segura para SPAs y apps móviles.
*   **spatie/laravel-query-builder:** Filtrado, ordenamiento e inclusión de relaciones dinámicas a través de la URL.
*   **spatie/laravel-permission:** Roles y permisos robustos integrados.
*   **Dedoc Scramble:** Generador OpenAPI 3.1 sin boilerplate.

---

## Licencia

Este proyecto es software de código abierto licenciado bajo la licencia [MIT](LICENSE).
