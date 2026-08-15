---
name: project-context
description: Activa el contexto de dominio de Mis Vales antes de trabajar en cualquier tarea del proyecto. Debe ser la primera skill considerada al iniciar trabajo en el repositorio, o cuando una tarea mencione entidades del negocio (distribuidora, cliente, relación, corte, pago, conciliación, comisión, crédito, solicitud, morosidad).
---

# Skill: project-context

## Cuándo activarse

- Al iniciar cualquier sesión de trabajo en el proyecto Mis Vales.
- Cuando la tarea mencione cualquier entidad de negocio (ver lista en `description`).
- Cuando exista duda sobre qué significa un término del dominio.
- Antes de nombrar un modelo, tabla, endpoint o variable relacionada con el negocio.

## Qué hace

Carga y aplica el contexto de `.claude/TERMINOLOGY.md` y `.claude/BUSINESS_RULES.md` para asegurar que cualquier código o diseño generado use el vocabulario y las reglas correctas del dominio Mis Vales, en lugar de asumir un dominio genérico de "créditos" o "facturación".

## Flujo de razonamiento

1. Leer la tarea solicitada y extraer qué entidades de negocio están involucradas.
2. Contrastar cada término contra `TERMINOLOGY.md` — especialmente las distinciones marcadas (Distribuidora ≠ Cliente, Relación ≠ Pago, Solicitud ≠ Crédito, Referencia ≠ Folio, Corte ≠ Relación).
3. Revisar si `BUSINESS_RULES.md` tiene una sección relevante ya definida o marcada ⚠️ PENDIENTE.
4. Si está pendiente, seguir el protocolo de `AI_CONTEXT.md` §2 (no inventar reglas financieras).
5. Proceder con la tarea usando el vocabulario correcto en nombres de clases, tablas, rutas y variables.

## Restricciones

- No usar sinónimos genéricos ("orden", "factura", "cuenta") cuando el dominio ya define un término específico.
- No mezclar el concepto de "relación" con "pago" ni con "corte".
- No asumir reglas de cálculo no documentadas.

## Checklist antes de continuar a otra skill

- [ ] Identifiqué correctamente qué entidades de negocio están involucradas.
- [ ] Verifiqué las distinciones de `TERMINOLOGY.md` relevantes.
- [ ] Revisé si `BUSINESS_RULES.md` tiene la sección completa o pendiente.
- [ ] Si falta información de negocio, la señalé explícitamente en vez de inventarla.

## Errores comunes que esta skill previene

- Crear un modelo `Pago` que en realidad debería ser una `Relacion`.
- Nombrar un endpoint `/api/creditos` cuando la operación real es sobre una `Solicitud`.
- Confundir el campo `referencia` (bancario) con `folio` (interno) al construir la lógica de conciliación.
- Implementar una fórmula de comisión o morosidad inventada porque "sonaba razonable".

## Ejemplo

**Tarea:** "Agrega el endpoint para listar los pagos de una relación."

**Aplicación de la skill:**
1. Entidades: `Relacion` (contenedor) y `Pago` (movimiento dentro de la relación) — confirmado en `TERMINOLOGY.md` (*Relación ≠ Pago*).
2. Ruta correcta: `GET /api/relaciones/{relacion}/pagos` (anidada, refleja que el pago pertenece a la relación), no `GET /api/pagos?relacion_id=`.
3. `BUSINESS_RULES.md` §7 (Pagos) está ⚠️ PENDIENTE en cuanto a reglas de pago parcial → se implementa el listado (que no depende de esa regla) y se señala que la lógica de aplicación de pagos parciales requiere definición adicional.
