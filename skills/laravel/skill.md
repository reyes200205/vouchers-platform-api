---
name: laravel
description: Convenciones de arquitectura y código Laravel específicas del proyecto Mis Vales. Actívala al escribir, modificar o revisar cualquier código PHP/Laravel del backend (modelos, controllers, requests, resources, services, policies, migraciones, jobs, eventos).
---

# Skill: laravel

## Cuándo activarse

- Al crear o modificar: Models, Controllers, Form Requests, API Resources, Policies, Services/Actions, migraciones, Jobs, Events, Listeners, Notifications, Observers.
- Al revisar código Laravel existente.

## Reglas de arquitectura (ver detalle en `.claude/ARCHITECTURE.md`)

1. **Controllers delgados.** Máximo: recibir el Form Request, llamar un Service/Action, devolver un Resource. Sin `if` de negocio, sin queries complejas inline.
2. **Validación siempre en Form Requests**, nunca `$request->validate([...])` inline en el controller salvo casos triviales de un solo campo.
3. **Lógica de negocio en Services/Actions**, nunca en el controller ni en el modelo directamente (el modelo puede tener scopes y accessors simples, no cálculos financieros completos).
4. **Autorización siempre vía Policy**, registrada y usada con `$this->authorize()` o `Gate::authorize()`. Nunca `if ($user->role === 'admin')` disperso en controllers.
5. **Respuestas siempre vía API Resource.** Prohibido `return response()->json(['ok' => true])` como respuesta de un recurso; usar `new XResource($model)` o `XResource::collection($models)`.
6. **Eloquent sobre Query Builder crudo.** `DB::table()` solo para reportes agregados que no representan un modelo, y documentando el motivo en un comentario.
7. **Eager loading obligatorio** cuando se recorre una relación en un loop o en un Resource — evitar N+1. Usar `with()`, `load()`, o `withCount()` según el caso.
8. **Tipado estricto.** Declarar tipos de parámetros y retorno en todos los métodos nuevos (PHP 8.4). Usar `readonly` para DTOs/value objects.
9. **Transacciones** (`DB::transaction()`) en cualquier operación que modifique más de una tabla relacionada con dinero.
10. **Enums de PHP** (`enum ... : string`) para estados de negocio (ej. estado de una solicitud, de un corte), no strings mágicos ni constantes sueltas.

## Convenciones de nombres

- Modelos en singular PascalCase, alineados a `TERMINOLOGY.md` (`Relacion`, `Corte`, `Pago`, no abreviaciones).
- Métodos de Service/Action con verbo + entidad: `generarCorte()`, `aplicarPago()`, `conciliarMovimiento()`.
- Form Requests: `StoreRelacionRequest`, `UpdateRelacionRequest`.
- Resources: `RelacionResource`, `RelacionCollection` si se necesita metadata extra.
- Policies: `RelacionPolicy`, con métodos `viewAny`, `view`, `create`, `update`, `delete` (y métodos específicos del dominio si aplica, ej. `cerrar`).

## Checklist antes de dar por terminado el código

- [ ] ¿El controller quedó delgado?
- [ ] ¿La validación está en un Form Request?
- [ ] ¿Hay una Policy protegiendo el recurso?
- [ ] ¿La lógica de negocio está en un Service/Action testeable, no en el controller?
- [ ] ¿La respuesta usa un API Resource?
- [ ] ¿Se evitó N+1 con eager loading?
- [ ] ¿Los nombres siguen `TERMINOLOGY.md`?
- [ ] ¿Operaciones multi-tabla están en una transacción?
- [ ] ¿Se agregó al menos un test (Pest) del Service/Action?

## Antipatrones a evitar explícitamente

```php
// ❌ Prohibido: lógica de negocio en el controller
public function store(Request $request)
{
    $data = $request->validate([...]);
    $relacion = Relacion::create($data);
    if ($relacion->monto > 10000) {
        // cálculo de comisión aquí... NO
    }
    return response()->json(['ok' => true]);
}
```

```php
// ✅ Correcto
public function store(StoreRelacionRequest $request, RelacionService $service)
{
    $this->authorize('create', Relacion::class);
    $relacion = $service->crear($request->validated());
    return new RelacionResource($relacion);
}
```

## Ejemplo de Service/Action

```php
final class RelacionService
{
    public function crear(array $datos): Relacion
    {
        return DB::transaction(function () use ($datos) {
            $relacion = Relacion::create($datos);
            // eventos, notificaciones, etc. según BUSINESS_RULES.md
            return $relacion;
        });
    }
}
```

## Relación con otras skills

- Antes de esta skill, activar `project-context` para confirmar terminología y reglas de negocio disponibles.
- Al diseñar el endpoint, usar `api-design` para el contrato HTTP antes de implementar el controller.
