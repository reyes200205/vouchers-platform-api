# CLAUDE.md — Guía maestra del proyecto Mis Vales

Este archivo es el contexto persistente que Claude Code carga automáticamente. Define **cómo pensar, cómo diseñar y qué nunca hacer** en este proyecto. Las reglas de negocio detalladas viven en `BUSINESS_RULES.md`, el glosario en `TERMINOLOGY.md`, la arquitectura en `ARCHITECTURE.md` y el protocolo de razonamiento en `AI_CONTEXT.md`. Este documento los conecta.

---

## 1. Qué es Mis Vales

Mis Vales administra el crédito que las **distribuidoras** otorgan a sus **clientes finales** a través de **vales** (líneas de crédito individuales). Periódicamente corre un **corte** (proceso global que calcula pagos y puntos de todas las distribuidoras), y por cada corte se genera, para cada distribuidora, una **Relación**: el documento de cobro con su referencia de pago única, sus vales, comisiones, recargos y total a pagar. Los pagos de las distribuidoras se **concilian** contra el banco usando esa referencia, y según el comportamiento de pago (anticipado/puntual/fuera de tiempo) se generan **puntos** o **recargos**. Ver `TERMINOLOGY.md` para las distinciones exactas — especialmente Relación vs. Corte, que es la más fácil de confundir en este dominio.

## 2. Stack y versiones

- **Backend:** Laravel 12, PHP 8.4.
- **Frontend:** Angular (standalone components, sin NgModules nuevos).
- **Base de datos:** MySQL/MariaDB.
- **Auth:** Laravel Sanctum (SPA + tokens API).
- **Testing:** Pest.
- **Calidad estática:** PHPStan (nivel máximo alcanzable de forma incremental).

## 3. Principios de arquitectura (resumen — detalle en ARCHITECTURE.md)

```
Request HTTP
   → Route
   → Form Request (validación)
   → Controller (delgado, solo orquesta)
   → Service / Action (lógica de negocio)
   → Model / Eloquent (persistencia)
   → Policy (autorización)
   → API Resource (transformación de salida)
   → Response
```

Reglas duras:

- **Controllers delgados.** Un controller nunca contiene lógica de negocio, cálculos financieros ni queries complejas. Su trabajo es: validar (delegado a Form Request), llamar a un Service/Action, devolver un Resource.
- **Nunca lógica de negocio en Blade ni en el frontend.** Los cálculos de relaciones, cortes, comisiones y conciliaciones viven en el backend, en Services/Actions testeados.
- **Nunca `DB::table()` si existe un Model.** Usa Eloquent salvo reportes agregados muy específicos, y en ese caso documenta el porqué en un comentario.
- **Nunca autorización manual dispersa (`if ($user->role == 'admin')` repetido).** Usa Policies y Gates centralizados.
- **Nunca perder auditoría ni historial.** Ver reglas de `SoftDeletes` y logs en `BUSINESS_RULES.md` y `DATABASE_RULES.md` (Sprint 2).
- **Siempre tipar.** Parámetros, retornos y propiedades con tipos explícitos (PHP 8.4). Evitar `mixed` salvo que sea inevitable.
- **Siempre validar con Form Requests**, nunca validar a mano dentro del controller.
- **Siempre devolver API Resources**, nunca arrays crudos (`return ['ok' => true]` está prohibido).

## 4. Cómo pensar antes de escribir código

Antes de generar cualquier código para este proyecto, Claude debe seguir el protocolo de `AI_CONTEXT.md`. En resumen:

1. Identificar qué entidad(es) de negocio están involucradas (consultar `TERMINOLOGY.md`).
2. Verificar si existe una regla de negocio aplicable en `BUSINESS_RULES.md`.
3. Ubicar la capa arquitectónica correcta (`ARCHITECTURE.md`).
4. Diseñar el contrato de la API si aplica (`skills/api-design/skill.md`).
5. Escribir el código siguiendo `skills/laravel/skill.md`.
6. Nunca inventar una regla de negocio que no esté documentada — si falta información, se debe señalar explícitamente en vez de asumir un comportamiento financiero.

## 5. Cómo construir un módulo nuevo

Para un módulo nuevo (ej. "conciliaciones"), el orden de construcción es:

1. Modelo(s) Eloquent + migración.
2. Policy del recurso.
3. Form Requests (Store/Update).
4. Service o Action con la lógica de negocio.
5. Controller (delgado) + rutas.
6. API Resource(s) de salida.
7. Eventos/Jobs/Notifications si el flujo lo requiere.
8. Tests (Pest) del Service/Action y del endpoint.
9. Documentación del endpoint según `API_GUIDELINES` (Sprint 2) / `skills/api-design/skill.md`.

## 6. Cómo generar Angular

- Standalone components, sin módulos legacy.
- Separación por `core/`, `shared/`, `features/`.
- Un componente "smart" por página que orquesta servicios; componentes "dumb" reciben datos por `@Input()`.
- Los cálculos financieros **nunca** se replican en el frontend; el frontend consume el resultado que ya calculó el backend.
- Detalle completo en `ANGULAR_GUIDELINES.md` (Sprint 2).

## 7. Cómo revisar código

Antes de dar por terminada una tarea, Claude debe verificar (checklist mínimo hasta que exista la skill `code-review` en Sprint 2):

- [ ] ¿El controller quedó delgado?
- [ ] ¿Hay una Policy protegiendo el recurso?
- [ ] ¿La validación está en un Form Request?
- [ ] ¿La respuesta usa un API Resource?
- [ ] ¿Existe algún N+1 evidente (falta `with()`/eager loading)?
- [ ] ¿Los nombres de variables/métodos reflejan el dominio de `TERMINOLOGY.md`?
- [ ] ¿Se agregó al menos un test?

## 8. Qué nunca hacer

- Nunca devolver arrays crudos como respuesta de API.
- Nunca poner queries SQL o lógica financiera en las vistas/Blade/Angular.
- Nunca omitir la Policy de un recurso sensible (créditos, pagos, conciliaciones).
- Nunca eliminar físicamente registros históricos de relaciones, cortes o pagos.
- Nunca asumir una regla de negocio no documentada: preguntar o señalarlo explícitamente.
- Nunca mezclar terminología (ver `TERMINOLOGY.md`) — por ejemplo, no llamar "pago" a una "relación".

## 9. Relación con las skills

| Situación | Skill a activar |
|---|---|
| Empezar a trabajar en el proyecto / duda sobre el dominio | `project-context` |
| Escribir/modificar código Laravel | `laravel` |
| Diseñar o revisar un endpoint | `api-design` |
| (Sprint 2) Revisar base de datos | `database` |
| (Sprint 2) Revisar seguridad | `security-review` |
| (Sprint 2) Revisar calidad general | `code-review` |
| (Sprint 2) Preparar commit/PR | `git` |

## 10. Estado de este documento

Este `CLAUDE.md` corresponde al **Sprint 1**. Se ampliará en sprints posteriores con las secciones específicas de `LARAVEL_GUIDELINES.md`, `ANGULAR_GUIDELINES.md`, `SECURITY_GUIDELINES.md`, `DATABASE_RULES.md`, `CODE_STYLE.md` y `GIT_GUIDELINES.md`, que hoy solo existen resumidas dentro de este archivo.
