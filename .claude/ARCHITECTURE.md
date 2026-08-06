# ARCHITECTURE.md — Arquitectura de Mis Vales

## 1. Visión general

Backend Laravel 12 (API) + Frontend Angular (SPA), comunicados vía API REST autenticada con Laravel Sanctum.

```
┌─────────────┐        HTTPS/JSON        ┌──────────────────┐
│   Angular   │ ───────────────────────▶ │   Laravel API     │
│   (SPA)     │ ◀─────────────────────── │   (Sanctum)        │
└─────────────┘                          └──────────────────┘
                                                   │
                                                   ▼
                                          ┌──────────────────┐
                                          │  MySQL/MariaDB     │
                                          └──────────────────┘
```

## 2. Capas del backend

```
Route
 └─ Middleware (auth, roles, throttling)
     └─ Form Request (validación de entrada)
         └─ Controller (delgado — orquesta, no decide)
             └─ Service / Action (lógica de negocio, transacciones)
                 └─ Model / Eloquent (persistencia)
                     └─ Policy (autorización por recurso)
                 └─ Events → Listeners / Jobs / Notifications
             └─ API Resource (transformación de salida)
         └─ Response JSON
```

### Responsabilidad de cada capa

- **Controller:** recibe el Form Request ya validado, llama a un Service/Action, devuelve un Resource. No contiene `if` de negocio, no arma queries complejas.
- **Form Request:** valida y autoriza (o delega autorización a Policy) la entrada. Aquí viven las reglas de "campos requeridos", no reglas financieras.
- **Service / Action:** lógica de negocio real (cálculo de relaciones, generación de cortes, aplicación de pagos, conciliación). Debe ser testeable de forma aislada, sin HTTP.
- **Model (Eloquent):** representa la entidad y sus relaciones (`hasMany`, `belongsTo`, etc.), scopes de consulta, casts, accessors puntuales. No lógica de negocio compleja dentro del modelo.
- **Policy:** decide si el usuario autenticado puede ver/crear/editar/eliminar un recurso específico.
- **API Resource:** define exactamente qué campos se exponen en la respuesta. Nunca se expone el modelo crudo.
- **Events/Jobs/Notifications:** para efectos secundarios (enviar notificación de corte generado, procesar conciliación en background, etc.), desacoplados del flujo principal.

### Repository pattern

Se usa **solo si aporta valor real** (por ejemplo, si una consulta compleja se reutiliza en varios Services y Eloquent por sí solo no la expresa bien). No se impone como capa obligatoria en todos los módulos — evitar abstracción prematura.

### DTOs

Se recomienda usar DTOs (objetos de transferencia de datos, p. ej. con `readonly class` de PHP 8.4) cuando un Service recibe muchos parámetros sueltos, para evitar firmas de método con 6+ argumentos primitivos.

## 3. Estructura de carpetas backend (propuesta)

```
app/
├── Actions/            # Acciones de un solo propósito (ej. GenerarCorteAction)
├── Services/           # Lógica de negocio más amplia/orquestada
├── Models/
├── Http/
│   ├── Controllers/Api/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
├── Policies/
├── Events/
├── Listeners/
├── Jobs/
├── Notifications/
├── Observers/
├── DTOs/
└── Enums/
```

## 4. Arquitectura frontend (Angular)

```
src/app/
├── core/          # Servicios singleton, interceptores, guards, modelos base
├── shared/         # Componentes/pipes/directivas reutilizables sin lógica de negocio propia
└── features/
    ├── distribuidoras/
    ├── clientes/
    ├── relaciones/
    ├── cortes/
    ├── pagos/
    ├── conciliaciones/
    └── comisiones/
        ├── data-access/     # Servicios HTTP + estado del feature
        ├── ui/              # Componentes "dumb" (presentacionales)
        └── feature-*.component.ts   # Componente "smart" que orquesta
```

Reglas:
- Componentes standalone; sin `NgModule` nuevos.
- Un componente "smart" por pantalla, que consume `data-access` y pasa datos a componentes "dumb" vía `@Input()`/`@Output()`.
- Los cálculos financieros nunca se recalculan en el frontend: siempre se consume el resultado ya calculado por el backend.
- Lazy loading por feature.

## 5. Transacciones y consistencia

Cualquier operación que toque más de una tabla relacionada con dinero (generar corte, aplicar pago, conciliar) debe envolverse en una transacción de base de datos (`DB::transaction()`), y debe quedar registrada en auditoría (ver `BUSINESS_RULES.md` §13).

## 7. Multi-sucursal y control de acceso por rol

Confirmado por el negocio: existe una sucursal matriz y varias sucursales, todas con las mismas capacidades. Esto implica que **toda entidad operativa (distribuidora, cliente final, relación, corte) pertenece a una sucursal**, y las consultas/policies deben filtrar por sucursal salvo para el rol Gerente General (visibilidad global) y Administrador (visibilidad global, solo lectura).

Roles a modelar (con permisos muy distintos entre sí, ver `BUSINESS_RULES.md` §13):
- Gerente General — CRUD + autorización, alcance global.
- Gerente de Sucursal — CRUD + autorización, alcance limitado a su sucursal.
- Coordinador — captura/edición de altas, alcance limitado a sus distribuidoras.
- Verificador — validación/corrección en el proceso de alta, sin autorización final.
- Cajero/Cajera — operación de depósitos y conciliación, alcance de su sucursal.
- Distribuidora — autoservicio de sus propios vales/relaciones.
- Administrador — solo lectura, alcance global, sin autorizar ni descargar relaciones.

Esto se traduce en Policies por recurso que combinan **rol** + **alcance de sucursal** (no solo rol). Ejemplo: `RelacionPolicy::view()` debe verificar rol Y que la relación pertenezca a una sucursal visible para ese usuario.

## 8. Múltiples clientes de la API

El negocio define tres superficies distintas consumiendo la misma API:
- App web optimizada para tablet (Coordinador, Verificador) — no de escritorio.
- App móvil no responsiva (Distribuidora) — solo celular.
- App web estándar (Gerentes).

La API debe diseñarse agnóstica del cliente (REST estándar, ver `skills/api-design/skill.md`); las diferencias de UI/UX se resuelven en cada frontend, no en el backend.

## 9. Configuración, no hardcodeo (requisito no negociable)

El negocio exige explícitamente que nada de lo siguiente esté fijo en código: fechas de corte, fechas de pago, porcentajes de interés/comisión por categoría, catálogo de productos (montos de vale), margen de tolerancia, y cualquier parámetro similar. Esto implica:

- Una tabla/módulo de **Configuraciones** (clave-valor o entidades dedicadas: `CategoriaDistribuidora`, `Producto`, `ConfiguracionCorte`) editable solo por los roles autorizados (típicamente Gerente General).
- Los Services que calculan una Relación (§3 de `BUSINESS_RULES.md`) deben leer estos valores en tiempo de ejecución, nunca como constantes de clase.
- Cambiar un porcentaje o fecha de corte no debe requerir un despliegue de código.


- `DATABASE_RULES.md`: convenciones de nombres de tablas, llaves, índices, soft deletes.
- `LARAVEL_GUIDELINES.md`: detalle extendido de convenciones Laravel (hoy resumido en `CLAUDE.md`).
- `ANGULAR_GUIDELINES.md`: detalle extendido de convenciones Angular.
- `SECURITY_GUIDELINES.md`: autenticación, roles, permisos, rate limiting.
