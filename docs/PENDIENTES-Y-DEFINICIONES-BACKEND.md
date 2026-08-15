# Pendientes y Definiciones para Completar el Backend

Fecha de actualización: 2026-08-15
Rama de trabajo: `Axel`

Este documento permite repartir el backend entre dos personas sin duplicar trabajo. Las
reglas respondidas aquí se convierten en contratos de API, validaciones y pruebas.

## 1. Estado actual

### Implementado o en implementación avanzada

| Módulo | Estado | Superficie disponible |
|---|---|---|
| Autenticación | Hecho | Inicio/cierre de sesión y usuario actual con Sanctum. |
| Autorización | Hecho | Capacidades configurables en `config/business-authorization.php`; alcance por sucursal y rol global. |
| Productos financieros | Hecho | Consulta, alta y actualización/desactivación. |
| Sucursales y configuración | Incluido en este commit | CRUD operativo de sucursales y configuración/bitácora financiera por sucursal. |
| Solicitudes de distribuidora | Incluido en este commit | Captura, asignación de verificador, verificación y decisión final. |
| Auditoría de cambios nuevos | Parcial | Registro de eventos para sucursales y solicitudes. |

### No implementar de nuevo

- No usar Spatie Permission para endpoints nuevos. La fuente de autorización es
  `roles` + `user_role` + `business-authorization.php`.
- No exponer contraseñas, tokens de activación ni hashes en respuestas administrativas.
- No usar `email` como credencial: el acceso usa `username` y `password_hash`.
- No aplicar reglas de sucursal en controladores de forma fija. Usar el middleware
  `business.ability` y `User::hasBusinessAbility()`.
- No borrar ni reescribir migraciones existentes. Los cambios de esquema se agregan como
  migraciones nuevas y reversibles.

### Organización de controladores por rol

Los controladores se agrupan por el actor principal que inicia la operación. Esta
organización no reemplaza la autorización: las rutas continúan protegidas por capacidades
en `business-authorization.php` y alcance de sucursal en `business.ability`.

| Directorio | Responsabilidad actual |
|---|---|
| `GeneralManager` | Catálogo de productos, administración de sucursales y decisión final de solicitudes. |
| `BranchManager` | Configuración financiera y bitácora de su sucursal. |
| `Coordinator` | Consulta, captura y asignación de verificador para solicitudes. |
| `Checker` | Verificación de campo de solicitudes; corresponde al rol de negocio `verifier`. |
| `Distributor` | Reservado para emisión de vales, cartera y transferencias iniciadas por distribuidora. |
| `Cashier` | Reservado para cobros, validación documental y conciliación. |
| `Administrator` | Reservado para consultas globales y auditoría de sólo lectura. |
| `Auth` | Acceso transversal: inicio de sesión, cierre de sesión y usuario actual. |

La decisión final de una solicitud vive en `GeneralManager` porque ése es el responsable
global del proceso, pero también puede ejecutarla el gerente de sucursal cuando la
capacidad `applications.decide` y el alcance de la sucursal se lo permiten. De la misma
forma, las consultas de sucursal y productos pueden atender a roles de sólo lectura aunque
el controlador esté agrupado bajo el responsable de administrarlos.

La aprobación de una solicitud garantiza que exista el rol de negocio `distributor` antes
de asignarlo al usuario recién creado. Esto evita que el onboarding dependa del seeder
antiguo de Spatie. El rol `administrator` tiene alcance global únicamente sobre las
capacidades de consulta que la matriz le concede; no obtiene capacidades de escritura por
estar listado como global.

## 2. Reparto de trabajo recomendado

### Bloque A: clientes, transferencias y vales

1. Clientes y vínculos cliente-distribuidora.
2. Solicitudes de modificación de datos de cliente.
3. Transferencias entre distribuidoras.
4. Cálculo, emisión, aprobación, activación y cancelación de pre-vales/vales.
5. Pagos de cliente y actualización de saldos.

### Bloque B: cortes, conciliación y administración financiera

1. Categorías de distribuidora y cuentas bancarias.
2. Cortes, relaciones de corte y sus partidas.
3. Importación de movimientos bancarios y conciliación.
4. Puntos, score e incrementos de línea.
5. Reportes, notificaciones y administración de usuarios/roles.

Antes de editar un archivo compartido (`routes/api/v1.php`,
`config/business-authorization.php`, `app/Models/User.php`), avisar y hacer un cambio
pequeño. Cada bloque debe incluir controladores, `FormRequest`, `Resource`, servicio
transaccional, capacidades y pruebas Feature.

## 3. Inventario de endpoints faltantes

Los nombres son una propuesta de contrato; se pueden ajustar antes de publicar API docs.

### 3.1 Clientes y relación con distribuidora

| Endpoint propuesto | Acción | Rol/capacidad propuesta |
|---|---|---|
| `GET /customers` | Buscar/listar clientes según sucursal y distribuidora. | `customers.view` |
| `POST /customers` | Registrar persona, cliente y vínculo inicial. | `customers.create` |
| `GET /customers/{customer}` | Consultar expediente, vínculos, vales y estado. | `customers.view` |
| `PATCH /customers/{customer}` | Actualizar sólo cambios ya autorizados. | `customers.manage` |
| `POST /customers/{customer}/change-requests` | Solicitar cambio de identidad/contacto/evidencia. | `customers.update.request` |
| `POST /customer-change-requests/{request}/decision` | Aprobar/rechazar y aplicar cambio. | `customers.update.approve` |
| `POST /customers/{customer}/verification` | Registrar validación documental al cobrar pre-vale. | `customers.verify` |

**Trabajo de esquema necesario:** crear `customer_change_requests` con solicitante,
aprobador, tipo de cambio, valores previos/nuevos, evidencia, estado, motivo y marcas de
tiempo. No deben alterarse directamente datos sensibles sin esta evidencia.

### 3.2 Transferencias de cliente

| Endpoint propuesto | Acción | Rol/capacidad propuesta |
|---|---|---|
| `GET /customer-transfer-requests` | Consultar solicitudes por estado. | `customers.transfer.view` |
| `POST /customers/{customer}/transfer-requests` | Distribuidora destino inicia solicitud. | `customers.transfer.request` |
| `POST /customer-transfer-requests/{request}/decision` | Coordinador aprueba/rechaza; aprobar ejecuta transferencia. | `customers.transfer.decide` |
| `POST /customer-transfer-requests/{request}/cancel` | Distribuidora destino cancela mientras esté pendiente. | `customers.transfer.cancel` |

Reglas confirmadas: no hay código de confirmación; la distribuidora destino inicia; el
coordinador decide; al aprobar se cierra el vínculo origen y se crea/activa el destino;
se notifica a la distribuidora origen. La solicitud debe usar los estados existentes
`PENDIENTE_COORDINADOR`, `EJECUTADA`, `RECHAZADA`, `CANCELADA`; los campos de código
antiguos permanecen nulos.

### 3.3 Vales y pagos del cliente

| Endpoint propuesto | Acción | Rol/capacidad propuesta |
|---|---|---|
| `POST /voucher-quotes` | Calcular importe, calendario, comisión, seguro e interés sin persistir. | `vouchers.quote` |
| `GET /vouchers` | Listar vales con filtros de cliente, distribuidora, estado y atraso. | `vouchers.view` |
| `POST /vouchers` | Crear borrador o solicitar emisión. | `vouchers.pre-issue` / `vouchers.issue` |
| `GET /vouchers/{voucher}` | Ver vale, snapshots y pagos. | `vouchers.view` |
| `POST /vouchers/{voucher}/approve` | Autorizar y descontar línea una única vez. | `vouchers.approve` |
| `POST /vouchers/{voucher}/activate` | Activar cuando aplique. | `vouchers.activate` |
| `POST /vouchers/{voucher}/cancel` | Cancelar/reversar según estado y devolver línea si corresponde. | `vouchers.cancel` |
| `POST /vouchers/{voucher}/payments` | Registrar abono del cliente. | `customer-payments.create` |
| `POST /customer-payments/{payment}/reverse` | Revertir un abono con motivo y trazabilidad. | `customer-payments.reverse` |

Reglas confirmadas:

- Un pre-vale se identifica porque el cliente no tiene vales históricos.
- Su máximo es 50% de la línea disponible más tolerancia máxima de $500.
- Esa restricción se reactiva después de aumentar el límite de crédito. Una vez que la
  línea se recupera, los vales digitales posteriores pueden usar el total disponible.
- La línea se descuenta una vez cuando el vale queda aprobado/activo, nunca en borrador.
- Deuda total: principal + comisión de empresa + seguro + interés de todas las quincenas.
- Los parámetros se congelan en snapshots del vale al emitirlo.
- Un vale activo no bloquea otro; la deuda en mora sí bloquea nuevos vales y genera interés.

**Trabajo de esquema necesario:** no existe una marca fiable del último incremento de
línea para reactivar la regla de pre-vale. Agregar historial de cambios de línea o una
marca `prevale_required_after_credit_increase_at` con la migración correspondiente.

### 3.4 Cortes, pagos de distribuidora y conciliación

| Endpoint propuesto | Acción | Rol/capacidad propuesta |
|---|---|---|
| `GET /cutoffs` | Consultar cortes de sucursal. | `cutoffs.view` |
| `POST /cutoffs` | Programar/generar corte. | `cutoffs.generate` |
| `GET /cutoffs/{cutoff}` | Consultar relaciones y partidas. | `cutoffs.view` |
| `POST /cutoffs/{cutoff}/execute` | Generar relaciones por distribuidora y partidas. | `cutoffs.execute` |
| `POST /cutoffs/{cutoff}/close` | Cerrar corte. | `cutoffs.close` |
| `POST /cutoffs/{cutoff}/reprocess` | Reprocesar con motivo y bitácora. | `cutoffs.reprocess` |
| `GET /cutoff-relations/{relation}` | Ver adeudo y referencia de pago única. | `cutoff-relations.view` |
| `POST /bank-transactions/imports` | Importar estado de cuenta simulado. | `bank-transactions.import` |
| `GET /bank-transactions` | Consultar movimientos importados y no conciliados. | `bank-transactions.view` |
| `POST /reconciliations/run` | Conciliar automáticamente por referencia. | `reconciliations.run` |
| `POST /reconciliations/manual` | Resolver una diferencia de forma auditada. | `reconciliations.manual` |

Reglas confirmadas:

- La distribuidora no captura pagos manualmente en plataforma.
- La cajera procesa el estado de cuenta bancario simulado y compara la columna
  `Referencia` con la referencia única de la relación de corte.
- Al registrar la cobranza en el corte, se recupera línea disponible de la distribuidora.
- La distribuidora retiene la comisión cedida y entera a la empresa el resto.
- El gerente de sucursal cierra y reprocesa cortes.

**Trabajo de esquema pendiente:** crear una entidad de importación de archivo con nombre,
hash, usuario, fecha, número de filas, errores y origen. Evita importar el mismo archivo
dos veces y permite auditar correcciones.

### 3.5 Puntos, score e incrementos de crédito

| Endpoint propuesto | Acción | Rol/capacidad propuesta |
|---|---|---|
| `GET/PATCH /point-settings` | Consultar/configurar parámetros globales. | `points.settings.view/manage` |
| `GET /distributors/{distributor}/point-movements` | Consultar movimientos y saldo. | `points.view` |
| `POST /point-movements/adjustments` | Ajuste excepcional con motivo. | `points.adjust` |
| `POST /credit-scores/run` | Generar evaluaciones mensuales. | `credit-scores.run` |
| `GET /credit-increase-suggestions` | Consultar sugerencias. | `credit-limits.view` |
| `POST /credit-increase-suggestions/{suggestion}/decision` | Aprobar/rechazar incremento. | `credit-limits.increase` |

Los puntos deben generarse desde servicios de pagos/cortes, no desde el controlador. Todo
ajuste manual requiere motivo y un `point_movement` reversible.

### 3.6 Catálogos, cuentas, usuarios y soporte

| Dominio | Endpoints mínimos pendientes |
|---|---|
| Categorías de distribuidora | Listar, crear, actualizar/desactivar. |
| Cuentas bancarias | Listar, crear, actualizar/desactivar por propietario. |
| Distribuidoras | Listar, expediente, bloquear/activar, actualizar categoría y revisar línea. |
| Usuarios y roles | Listar, crear, asignar/revocar rol por sucursal, activar/desactivar usuario. |
| Contraseñas | Solicitar restablecimiento, aprobar/rechazar, consumir token de activación. |
| Notificaciones | Listar, marcar leída y emisión de avisos de transferencia/corte/decisión. |
| Auditoría | Consulta paginada y filtrable por sucursal, usuario, módulo, evento y fecha. |
| Reportes | Cartera, mora, vales, cortes, cobranza, conciliación, puntos y línea disponible. |

## 4. Preguntas obligatorias de negocio

Responder por número. Si una respuesta depende de rol o sucursal, indicarlo expresamente.

### A. Clientes y expediente

1. ¿Quién captura inicialmente al cliente: distribuidora, coordinador, cajera o gerente?
2. ¿La distribuidora puede consultar todos los clientes de su sucursal o sólo los que tienen
   un vínculo activo con ella?
3. ¿Qué campos son obligatorios para crear al cliente: CURP, RFC, teléfono, domicilio,
   referencias, empleo, ingreso, fotos y documentos?
4. ¿Qué documento/evidencia valida la cajera al entregar el primer pre-vale y qué ocurre si
   falta o no coincide?
5. ¿El cliente pasa de `EN_VERIFICACION` a `ACTIVO` al aprobarse el pre-vale o hasta que la
   cajera confirme documentos?
6. ¿Qué roles pueden bloquear, reactivar o declarar moroso a un cliente y con qué causas?
7. ¿Los cambios de nombre, CURP, RFC, teléfono, domicilio y documentos tienen el mismo
   aprobador? ¿Qué cambios puede aprobar el gerente de sucursal y cuáles el general?
8. ¿Una solicitud de modificación rechazada se puede corregir y reenviar? ¿Debe conservar
   historial y evidencia anterior?

### B. Transferencias

9. ¿Qué significa exactamente “sin deudas” para permitir la transferencia: saldo total $0,
   sin pagos vencidos, o sin vales activos?
10. ¿El coordinador que decide pertenece a la sucursal origen, destino o cualquiera de las
    dos? ¿Qué sucede si las distribuidoras pertenecen a sucursales distintas?
11. ¿La transferencia debe conservar el historial de vales/pagos visible para la
    distribuidora destino?
12. ¿Puede existir más de una solicitud pendiente por cliente? Si no, ¿cuál se cancela?
13. ¿Qué notificación exacta recibe la distribuidora origen, por qué canal y con qué datos?
14. ¿La distribuidora destino puede cancelar después de la aprobación? Si no, ¿quién puede
    revertir una transferencia ejecutada y bajo qué procedimiento?

### C. Pre-vales, vales y deuda

15. Confirma la fórmula exacta del máximo pre-vale: ¿`mínimo(línea disponible, 50% de línea
    disponible + 500)`? ¿La tolerancia puede ser menor que $500 o es siempre $500?
16. Después de un aumento de línea, ¿la regla del 50% aplica al primer vale creado, al
    primer vale aprobado o al primer vale cobrado?
17. Si un pre-vale se cancela o revierte, ¿la siguiente solicitud sigue siendo pre-vale?
18. ¿Quién crea el vale, quién lo aprueba y quién lo activa en cada sucursal? ¿La
    distribuidora puede crear borradores o emitir directamente dentro de un límite?
19. ¿`APROBADO` y `ACTIVO` son estados diferentes en la operación? Indicar evento, actor y
    regla de crédito de cada transición para evitar descontar línea dos veces.
20. ¿Qué productos puede elegir una distribuidora y se pueden modificar montos/quincenas al
    emitir un vale?
21. Confirma la fórmula por quincena: para $15,000 a 8 quincenas, ¿se cobra exactamente
    `$15,000 + $1,500 + $100 + $6,000 = $22,600`, es decir $2,825 por quincena?
22. ¿Los intereses se cobran completos desde la emisión o disminuyen con abonos anticipados?
23. ¿Cómo se distribuye un pago parcial: interés, seguro, comisión, principal, recargo?
24. ¿La fecha de pago se define por vale, por cliente, por distribuidora o por corte? ¿Qué
    ventanas de tolerancia y pronto pago existen?
25. ¿Cuándo entra un vale/cliente a mora, qué interés o recargo aplica y quién puede
    condonarlo?
26. ¿Qué estados permiten cancelar/reversar un vale y cómo se trata lo ya cobrado?
27. ¿Qué identificador/firma/comprobante debe tener un vale digital para ser válido?

### D. Cobranza, cortes y conciliación

28. Si la distribuidora no captura pagos, ¿quién registra cada abono del cliente: cajera,
    distribuidora fuera del sistema y luego importación, o un proceso de corte?
29. ¿Los pagos de cliente se registran individualmente antes del corte o sólo al generar el
    corte quincenal?
30. ¿Cómo se calcula exactamente el entero de distribuidora: total cobrado menos comisión
    cedida, menos qué otros conceptos (puntos, seguros, recargos, saldos previos)?
31. ¿La referencia de pago única se genera por relación de corte, por distribuidora, por
    fecha o por partida de vale? ¿Cuánto tiempo permanece válida?
32. ¿Qué columnas tendrá el archivo bancario simulado, cuál es el formato real (CSV/XLSX),
    codificación, separador y ejemplo de una fila?
33. Si una referencia coincide pero el importe difiere, ¿se puede conciliar parcialmente,
    se deja pendiente o se rechaza automáticamente?
34. ¿Qué tipos de movimientos bancarios se ignoran (comisiones, SPEI sin referencia,
    devoluciones) y cómo se resuelven?
35. ¿La conciliación automática corre al importar o necesita revisión de cajera?
36. Cuando hay una diferencia manual, ¿quién da la segunda autorización: gerente de
    sucursal, gerente general, ambos según monto, o nadie? Definir umbrales y evidencia.
37. ¿Un corte cerrado puede reabrirse? ¿Quién lo hace, qué información se recalcula y cómo
    se comunica a la distribuidora?

### E. Puntos, crédito y administración

38. ¿Cuál es la fórmula de puntos por pago anticipado, puntual y atrasado? Indicar ejemplos
    con importes y fechas.
39. ¿Los puntos se canjean por efectivo, reducción de deuda, incremento de línea u otros
    beneficios? ¿Quién autoriza cada canje?
40. ¿El aumento de línea puede ser automático? Si sí, indicar condiciones, monto máximo y
    excepciones. Si no, ¿quién lo solicita y quién lo aprueba?
41. ¿La línea disponible se recupera con un pago del cliente, con el corte ejecutado o al
    conciliar el depósito de la distribuidora? Indicar cómo se trata cada escenario.
42. ¿Qué roles administran categorías de distribuidora, cuentas bancarias, usuarios, roles
    y configuración de puntos?
43. ¿Qué reportes son obligatorios y quién puede ver datos globales versus su sucursal?
44. ¿Qué acciones críticas requieren doble autorización además de la conciliación manual:
    aumentos de línea, reversos, cancelaciones, reaperturas, ajustes de puntos?

### F. Integración, seguridad y operación

45. ¿Cómo se entrega la contraseña/activación de un solo uso a la distribuidora: SMS,
    WhatsApp, correo, impresión controlada o entrega presencial? ¿Quién confirma entrega?
46. ¿Habrá carga de archivos real? Definir almacenamiento (local/S3), formatos, tamaño
    máximo, retención y quién puede descargar evidencia.
47. ¿Qué canales usará cada rol (web, VPN web, móvil/tableta) y cuáles requieren VPN?
48. ¿Se requieren límites de sesión, 2FA, bitácora de inicio de sesión o bloqueo por intentos
    fallidos?
49. ¿Cuánto tiempo deben retenerse auditorías, evidencias, archivos bancarios y datos
    personales? ¿Hay requisitos legales de protección de datos?
50. ¿Hay sistemas externos para dispersión, banca, SMS/WhatsApp, firma o buró? Incluir API,
    responsable y ambiente de pruebas.

## 5. Datos y decisiones técnicas pendientes

1. Retirar `HasRoles`/Spatie o instalar y migrar formalmente sus tablas. La API nueva no lo
   usa; mantener ambos sistemas sin definición causa errores al ejecutar seeders.
2. Actualizar o retirar el código starter obsoleto: `StoreBranchService`, seeders de usuarios
   y roles, y pruebas de recuperación/verificación por email ya no representan el esquema.
3. Resolver la entrega segura de activación: existe token con hash, pero falta un canal de
   notificación/control de entrega.
4. Elegir librería de importación para XLSX si ése será el formato bancario. CSV puede
   procesarse sin dependencia adicional; XLSX requiere una dependencia validada.
5. Definir API docs: ejemplos de solicitud/respuesta, códigos de error y paginación para cada
   módulo antes de integrar frontend.

## 6. Criterio de terminado por módulo

Un módulo no se considera terminado sólo porque sus rutas respondan. Debe incluir:

1. Migración nueva cuando haga falta persistencia o trazabilidad faltante.
2. Enum/modelo/relaciones coherentes con la migración.
3. Políticas de capacidad y alcance por sucursal.
4. Validación con `FormRequest` y salida con `Resource`.
5. Servicio transaccional para operaciones financieras y bloqueo de filas cuando se actualice
   línea, saldo, corte o puntos.
6. `audit_logs` y notificaciones para decisiones relevantes.
7. Pruebas Feature de éxito, autorización, duplicados, estados inválidos y concurrencia o
   doble procesamiento cuando aplique.
8. Documentación de rutas generada/validada con Scramble.
