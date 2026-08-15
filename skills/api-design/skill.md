---
name: api-design
description: Diseño y revisión de endpoints REST del proyecto Mis Vales — nombres de rutas, verbos HTTP, estructura de request/response, códigos de estado, paginación y manejo de errores. Actívala antes de implementar cualquier endpoint nuevo o al revisar uno existente.
---

# Skill: api-design

## Cuándo activarse

- Antes de crear un endpoint nuevo (diseñar el contrato primero, implementar después).
- Al revisar si un endpoint existente sigue las convenciones del proyecto.
- Al definir la forma de un API Resource.

## Reglas de diseño REST del proyecto

### Rutas y recursos

- Recurso en plural, en español, alineado a `TERMINOLOGY.md`: `/api/relaciones`, `/api/cortes`, `/api/pagos`, `/api/conciliaciones`.
- Recursos anidados cuando la pertenencia es real: `/api/relaciones/{relacion}/pagos`, no `/api/pagos?relacion_id=` cuando el pago no tiene sentido fuera de la relación.
- Verbos HTTP estándar: `GET` (listar/ver), `POST` (crear), `PUT/PATCH` (actualizar), `DELETE` (eliminar — casi nunca usado sobre datos financieros; preferir una acción de estado, ver abajo).
- Acciones de negocio que no son CRUD puro se modelan como sub-recursos o verbos explícitos: `POST /api/cortes/{corte}/cerrar`, `POST /api/relaciones/{relacion}/conciliaciones`, no `PATCH /api/cortes/{corte}` con un campo `estado` escondido.

### Respuestas

- **Siempre** un API Resource, nunca un array crudo.
- Recurso único:
  ```json
  { "data": { "id": 1, "tipo": "relacion", "...": "..." } }
  ```
- Colección paginada:
  ```json
  {
    "data": [ { "...": "..." } ],
    "meta": { "current_page": 1, "per_page": 15, "total": 42 },
    "links": { "next": "...", "prev": null }
  }
  ```
- Prohibido: `{ "ok": true }`, `{ "success": 1 }` o cualquier respuesta sin estructura de recurso.

### Códigos HTTP

| Código | Uso |
|---|---|
| 200 | Éxito con contenido (GET, PUT/PATCH exitoso, acción de negocio exitosa) |
| 201 | Recurso creado (POST exitoso) |
| 204 | Éxito sin contenido (ej. eliminación cuando aplique) |
| 401 | No autenticado |
| 403 | Autenticado pero sin permiso (Policy) |
| 404 | Recurso no encontrado |
| 422 | Error de validación (Form Request) |
| 409 | Conflicto de negocio (ej. intentar modificar una relación cerrada) |

### Errores

Formato consistente para todos los errores:
```json
{
  "message": "Descripción legible del error",
  "errors": { "campo": ["Detalle de validación"] }
}
```
El campo `errors` solo aparece en errores de validación (422). Para errores de negocio (409), usar solo `message` con texto claro sobre por qué la operación no es válida (ej. "No se puede modificar una relación cerrada").

### Paginación, filtros y ordenamiento

- Paginación estándar de Laravel (`?page=`), tamaño configurable con `?per_page=` (máximo razonable, ej. 100).
- Filtros como query params explícitos: `?estado=activa`, `?distribuidora_id=`.
- Ordenamiento: `?sort=-created_at` (prefijo `-` para descendente), nunca ordenar por defecto de forma no determinista.

### Versionado

Prefijo de versión en la ruta cuando se prevea un cambio incompatible futuro: `/api/v1/...`. Mientras no exista una v2, se puede omitir, pero cualquier breaking change debe justificar la creación de `/api/v2/...` en vez de modificar el contrato existente.

## Checklist antes de implementar un endpoint

- [ ] ¿El nombre del recurso usa el término correcto de `TERMINOLOGY.md`?
- [ ] ¿Es un recurso anidado si la pertenencia lo amerita?
- [ ] ¿El verbo HTTP es el correcto para la acción?
- [ ] ¿Las acciones de negocio no-CRUD están modeladas como sub-recurso/acción explícita, no como PATCH oculto?
- [ ] ¿La respuesta usa un API Resource con estructura `data`/`meta`/`links`?
- [ ] ¿Los códigos de error siguen la tabla de este documento?
- [ ] ¿Se definieron los filtros y el ordenamiento necesarios?

## Ejemplo completo

**Tarea:** diseñar el endpoint para cerrar un corte.

```
POST /api/cortes/{corte}/cerrar
```

Request: sin body (o `{ "observaciones": "string|nullable" }` si el negocio lo requiere — pendiente confirmar en `BUSINESS_RULES.md`).

Response 200:
```json
{
  "data": {
    "id": 10,
    "tipo": "corte",
    "estado": "cerrado",
    "cerrado_en": "2026-08-06T10:00:00Z"
  }
}
```

Response 409 (si ya estaba cerrado):
```json
{ "message": "El corte ya se encuentra cerrado." }
```

## Relación con otras skills

- Usar `project-context` primero para confirmar el vocabulario correcto del recurso.
- Usar `laravel` para implementar el controller/Service que soporta este contrato una vez diseñado.
