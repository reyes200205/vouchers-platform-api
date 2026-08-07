---
description: "Guía interna para diseñar un módulo nuevo en Mis vales"
---

# Nuevo módulo

Actúa como arquitecto y analista funcional del proyecto Mis vales.

Quiero que empieces el proyecto módulo por módulo, validando primero que entiendes y puedes aplicar las skills, los comandos y la arquitectura disponible antes de proponer código.

## Instrucciones de arranque

0. Antes de comenzar, identifica el primer módulo a trabajar y explícame qué skill, comando o documento vas a usar para validarlo.
1. Revisa el contexto del proyecto y confirma qué skills y comandos aplican.
2. Si falta alguna skill o comando relevante, dilo explícitamente antes de avanzar.
3. No asumas reglas de negocio: extrae primero el comportamiento esperado del módulo.
4. Trabaja un módulo a la vez y no saltes al siguiente hasta cerrar el anterior.
5. Para cada módulo, entrega el resultado siguiendo el orden:

Arquitectura -> Modelo -> Migración -> Policies -> Requests -> Resources -> Services -> Tests -> Angular -> API -> Documentación

## Cómo debe trabajar

- Antes de escribir código, define el objetivo del módulo, sus actores y su problema de negocio.
- Verifica que el módulo respete las reglas del sistema financiero.
- Confirma qué configuración afecta el cálculo, validación o autorización.
- Identifica qué auditoría, evidencia y trazabilidad deja el módulo.
- Si un módulo depende de otro, explícitalo y marca la relación.
- Si una decisión cambia por rol, sucursal, fecha o estado, debe quedar modelada.

## Salida esperada por módulo

Para cada módulo, responde con:

- Nombre del módulo.
- Problema de negocio.
- Actores.
- Flujo principal.
- Reglas configurables.
- Entidades involucradas.
- Estado inicial y estado final esperado.
- API esperada.
- UI esperada.
- Validaciones.
- Autorizaciones.
- Auditoría y evidencia.
- Pruebas necesarias.
- Riesgos o preguntas abiertas.

## Regla

No te detengas al crear el controlador. Explica cómo se calcula, valida, autoriza, audita y consulta el comportamiento. Si algo no está definido, detente y pide la información mínima necesaria antes de inventarlo.
