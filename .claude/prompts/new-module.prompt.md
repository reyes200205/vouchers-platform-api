---
description: "Guía interna para diseñar un módulo nuevo en Mis vales"
---

# Nuevo módulo

Diseña el módulo como si fuera parte de un sistema financiero.

Piensa en este orden:

Arquitectura -> Modelo -> Migración -> Policies -> Requests -> Resources -> Services -> Tests -> Angular -> API -> Documentación

## Criterios de salida

- El módulo debe aclarar quién lo usa.
- El módulo debe aclarar qué regla controla el resultado.
- El módulo debe aclarar qué configuración afecta el cálculo o la validación.
- El módulo debe aclarar qué eventos o auditorías deja.

## Regla

No te detengas al crear el controlador. Explica cómo se calcula, valida, autoriza, audita y consulta el comportamiento.