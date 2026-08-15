# CLAUDE.md

Documento técnico base del proyecto Mis vales.

## Filosofía del sistema

- La regla de negocio vive en configuración o dominio, nunca en un valor fijo si cambia por negocio.
- El motor financiero es la fuente de verdad para cálculos, pagos, recargos, seguros, puntos, bonificaciones y conciliación.
- Cada cambio financiero debe ser auditable, rastreable y explicable.
- Los documentos fuente mandan sobre ejemplos aislados; los ejemplos sirven para validar la interpretación.
- Si una decisión depende de fecha, corte, producto, sucursal, distribuidora, rol o estado, debe modelarse explícitamente.
- Si una regla afecta crédito activo, la regla debe considerar el estado actual, no solo el alta original.

## Visión funcional

Mis vales es un sistema tipo bancario para prestar productos o vales en efectivo a distribuidoras. El negocio se sostiene sobre relaciones, corte de pagos, conciliación bancaria, autorizaciones, control de crédito y trazabilidad operativa.

La intención del sistema no es solo registrar movimientos, sino decidir correctamente quién puede recibir crédito, cuánto se le presta, cuándo paga, qué se le cobra, qué pasa si paga antes o tarde, cómo se concilia ese pago y cómo se deja evidencia de cada cambio.

## Arquitectura

- Presentación: Angular para captura, consulta, tablas, corte, seguimiento operativo y pantallas por rol.
- API: Laravel 12 como capa de aplicación, validación, auth, orquestación, políticas y entrega de recursos.
- Dominio: servicios y motores de negocio para créditos, relaciones, conciliaciones, auditoría, reglas configurables y reportes.
- Persistencia: MySQL con integridad, trazabilidad y datos normalizados.
- Infraestructura: servidor para imágenes y evidencia documental.
- Observabilidad: logging estructurado, errores controlados y eventos de negocio visibles.

## Flujos principales

### Autorizaciones

- Solicitud de cambio o alta.
- Validación por rol y alcance.
- Aprobación o rechazo con motivo.
- Emisión del efecto autorizado.
- Registro de fecha, hora, usuario, dispositivo y evidencia.

### Créditos

- Disponibilidad inicial.
- Consumo por vale o relación.
- Incremento o ajuste de línea.
- Control de topes y reglas del 50% cuando aplique.
- Bloqueo operativo por mora o incumplimiento.

### Relaciones

- Corte configurable.
- Cálculo de deuda, comisión, seguro, puntos y recargos.
- Generación de referencia bancaria única.
- Estado de cuenta con trazabilidad por distribuidora.

### Conciliaciones

- Recepción de archivo bancario.
- Cruce por referencia, monto, fecha y forma de pago.
- Match automático contra la relación.
- Conciliación manual cuando exista error o referencia mal capturada.
- Evidencia y autorización para ajustes manuales.

### Auditoría

- Todo cambio sensible debe registrar actor, antes, después, fecha, hora, motivo y origen.
- Si un dato cambia con vales activos, debe quedar claro si la versión histórica se conserva o si la regla nueva aplica a futuro.

### Nuevo distribuidor

- Captura inicial por coordinador.
- Verificación física por verificador.
- Correcciones auditadas si hubo captura incorrecta.
- Validación final por gerente general o gerente de sucursal.
- Asignación de límite de crédito y credenciales.

### Vale y crédito

- Primer vale: pre-vale.
- Vales subsecuentes: vale digital.
- El primer vale no debe superar el 50% del crédito disponible si así lo dicta la regla.
- Un cliente no debe poder registrarse con otra distribuidora si la CURP ya existe y la regla lo bloquea.

### Cambio y transferencia

- Cambio de distribuidora sujeto a autorización y reglas de saldo.
- Cambio de sucursal sujeto al alcance del rol.
- El gerente general puede ver todo el universo, pero el gerente de sucursal solo su ámbito.

## Roles y alcance

- Gerente general: ve y autoriza todo el sistema, incluyendo sucursales y movimientos globales.
- Gerente de sucursal: administra su sucursal y lo que opera dentro de ella.
- Coordinador: primer filtro de captura y seguimiento; puede tener muchas distribuidoras.
- Administrador: ve todo, pero no escribe ni autoriza; revisa historial, movimientos y logs.
- Distribuidora: consume el sistema en su flujo operativo.
- Verificador: valida la información capturada y deja evidencia de visita.
- Cajera: ejecuta operaciones de sucursal y procesos de conciliación y corrección con autorización.

## Convenciones Laravel

- Controladores delgados.
- Reglas en Form Requests, Services, Actions o Domain Classes.
- Consultas complejas aisladas y testeadas.
- Nombres explícitos, orientados a intención de negocio.
- Eventos, jobs y notifications para efectos secundarios.
- Cualquier operación que cambie estado financiero debe pasar por una capa de dominio, no por el controller directo.

## Convenciones Angular

- Formularios reactivos para flujos operativos.
- Componentes por responsabilidad, no por pantalla gigante.
- El frontend consume reglas, no las redefine.
- Estados de carga, error y vacío siempre visibles.
- Las apps deben respetar el tipo de dispositivo previsto por rol: tablet, teléfono o web de escritorio.

## Convenciones MySQL

- Columnas y tablas en singular o plural consistente, sin ambigüedad.
- Claves foráneas, índices y constraints donde protejan el dominio.
- Los montos monetarios deben tener precisión definida.
- Toda relación crítica debe poder reconstruirse por llaves y referencias.
- Los catálogos configurables deben permitir versionado o historial cuando la regla lo requiera.

## Naming

- Usar nombres de intención: RelationCalculator, CreditAvailabilityService, ConciliationMatch.
- Evitar siglas internas salvo que sean estándar del dominio.
- Los nombres de archivo y carpeta deben coincidir con el concepto del negocio.
- Los nombres de roles, movimientos y flujos deben ser legibles por negocio, no solo por tecnología.

## Estructura de carpetas

- `skills/`: conocimiento operativo por dominio.
- `architecture/`: decisiones arquitectónicas, diagramas y flujos.
- `prompts/`: prompts internos para tareas repetibles y decisiones de diseño.
- `templates/`: esqueletos para módulos, documentos y entregables.
- `commands/`: secuencias internas para tareas recurrentes.

## Reglas financieras clave

- El cálculo de relaciones debe considerar producto, plazo, comisión, seguro, bonificación, puntos, recargos y fecha.
- Todo porcentaje, umbral, categoría y fecha de corte debe ser configurable.
- El monto a pagar debe ser explicable paso a paso.
- Las categorías de distribuidora pueden cambiar la comisión o el trato financiero.
- La conciliación bancaria se hace por referencia única y evidencia de pago.
- Los puntos deben poder convertirse a valor económico bajo regla configurable.
- Si una distribuidora entra en mora, se deben activar reglas de contención o restricción.

## Manejo de errores

- Los errores deben indicar si falló validación, autorización, configuración, cálculo o conciliación.
- Los errores de negocio deben ser comprensibles para operación y para auditoría.
- Un error de referencia, corte o folio debe dejar evidencia para conciliación posterior.

## Logging

- Registrar movimientos sensibles con contexto suficiente para reconstrucción.
- Guardar usuario, rol, sucursal, dispositivo, hora y origen cuando aplique.
- No registrar secretos ni información sensible en texto plano si no es indispensable.

## Seguridad

- Segregación estricta por rol y sucursal.
- El administrador observa, pero no ejecuta acciones que cambian estado.
- Los datos personales sensibles deben tener visibilidad controlada.
- La evidencia y las autorizaciones deben quedar protegidas contra alteración.

## Testing

- Cada regla financiera debe tener pruebas de escenario feliz, borde y error.
- Las reglas de autorizaciones deben probar alcance por rol y por sucursal.
- Las reglas de conciliación deben probar coincidencia, mismatch, duplicado y ajuste manual.
- Las pruebas deben cubrir cambios de configuración sin romper el cálculo histórico.

## Checklist antes de hacer commit

- Revisar que no exista hardcode de reglas financieras.
- Confirmar que el cambio esté cubierto por pruebas.
- Confirmar que no se rompió la trazabilidad de auditoría.
- Revisar naming, contratos API y dependencias entre capas.
- Verificar logs, validaciones y permisos.
- Confirmar que la regla nueva no contradice el comportamiento histórico de vales activos.

## Cómo usar las skills

Antes de responder o implementar, identificar la skill principal y luego combinar la secundaria si el flujo cruza más de un dominio. Para cambios de negocio complejos, pensar en este orden: arquitectura -> modelo -> migración -> policies -> requests -> resources -> services -> tests -> Angular -> API -> documentación.

## Preguntas abiertas de negocio

- Si un parámetro cambia con vales activos, ¿se versiona por vigencia o se recalcula todo?
- ¿Un usuario puede tener más de un rol a la vez?
- ¿El administrador puede exportar reportes o solo consultar?
- ¿Cómo consume saldo el cliente si su interfaz es limitada o no existe aún?
- ¿El folio del pre-vale expira si no se presenta a la sucursal?