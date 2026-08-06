# AI_CONTEXT.md — Protocolo de razonamiento para IA

Este archivo es exclusivo para la IA (Claude Code). Explica **cómo pensar antes de escribir código** en el proyecto Mis Vales, no qué código escribir (eso está en las guías y skills).

## 1. Protocolo antes de escribir código

Ante cualquier tarea ("implementa X", "arregla Y", "diseña el endpoint de Z"), sigue este orden:

1. **Identificar entidades del dominio involucradas.** Consultar `TERMINOLOGY.md`. Si la tarea menciona un término ambiguo (ej. "pago" cuando podría referirse a una relación), pedir aclaración o inferir con cautela y decirlo explícitamente.
2. **Buscar reglas de negocio aplicables.** Consultar `BUSINESS_RULES.md`. Si la sección relevante está marcada como ⚠️ PENDIENTE, **no inventar** la regla financiera: implementar la estructura de código dejando explícito qué parte depende de una regla de negocio aún no definida, y señalarlo en la respuesta al usuario.
3. **Ubicar la capa arquitectónica correcta.** Consultar `ARCHITECTURE.md`. Lógica de negocio → Service/Action. Persistencia → Model. Autorización → Policy. Transformación de salida → Resource.
4. **Diseñar el contrato antes que la implementación.** Si es un endpoint, definir primero: método HTTP, ruta, request esperado, response esperado (usar `skills/api-design/skill.md`).
5. **Escribir el código** siguiendo `skills/laravel/skill.md` (y `ANGULAR_GUIDELINES.md` cuando exista, para frontend).
6. **Verificar contra el checklist de `CLAUDE.md` §7** antes de considerar la tarea terminada.

## 2. Qué hacer cuando falta información

Este proyecto maneja dinero real (créditos, pagos, conciliaciones bancarias). El costo de asumir mal una regla financiera es alto. Por lo tanto:

- Si una tarea requiere una regla de cálculo que no está en `BUSINESS_RULES.md`, **no la inventes**. Implementa la estructura (modelo, endpoint, service vacío o con TODO explícito) y comunica claramente qué falta definir.
- Si una tarea es ambigua sobre qué entidad usar (ej. "cliente" vs "distribuidora"), usa `TERMINOLOGY.md` para resolverlo; si sigue siendo ambiguo, pregunta.
- Nunca generes cifras, porcentajes o fórmulas de ejemplo como si fueran reales del negocio — eso puede terminar en producción por error.

## 3. Prioridad de fuentes de verdad

Cuando haya conflicto o duda, este es el orden de autoridad:

1. Lo que el usuario indique explícitamente en la conversación actual.
2. `BUSINESS_RULES.md` (si la sección no está marcada como pendiente).
3. `ARCHITECTURE.md` / `CLAUDE.md` para decisiones estructurales.
4. `TERMINOLOGY.md` para nombres.
5. Convenciones generales de Laravel/Angular cuando el proyecto no dice nada específico.

## 4. Señales de alerta que deben detener a la IA

Detente y pregunta (o señala explícitamente la limitación) si la tarea implica:

- Modificar una relación, corte o pago ya cerrado/histórico.
- Eliminar físicamente (no soft delete) cualquier registro financiero.
- Definir una fórmula de cálculo financiero no documentada.
- Saltarse una Policy en un recurso sensible "para ir más rápido".
- Devolver datos crudos del modelo sin pasar por un Resource.

## 5. Cómo responder al usuario sobre el estado del conocimiento

Si el usuario pide implementar un módulo cuya regla de negocio está ⚠️ PENDIENTE en `BUSINESS_RULES.md`, la respuesta correcta es: construir el esqueleto técnico correcto (modelo, migración, service con la lógica claramente marcada como pendiente, endpoint, tests de estructura) y decir explícitamente qué información de negocio falta para completarlo — no simular que la regla ya se conoce.
