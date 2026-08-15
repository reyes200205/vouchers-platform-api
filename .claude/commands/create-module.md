# Crear un módulo nuevo

Cuando se solicite crear un módulo nuevo, seguir este orden mental:

1. Arquitectura.
2. Modelo.
3. Migración.
4. Policies.
5. Requests.
6. Resources.
7. Services.
8. Tests.
9. Angular.
10. API.
11. Documentación.

## Qué debe resolver antes de escribir código

- Qué actor usa el módulo.
- Qué regla financiera o operativa gobierna su comportamiento.
- Qué datos se deben guardar para auditarlo.
- Qué configuraciones afectan el resultado.
- Qué estados existen antes, durante y después de la acción.

## Regla

No crear solo un Controller. Primero definir el comportamiento del negocio, su persistencia, sus permisos y su evidencia.