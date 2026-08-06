# TERMINOLOGY.md — Glosario de dominio de Mis Vales

> **Actualizado con documentos fuente reales:** *Análisis de cálculo de relación*, *Relación (ejemplo)*, *Excel de Banco*.
>
> ⚠️ **Corrección respecto a la versión Sprint 1 inicial:** con los documentos fuente en mano, la relación entre "Relación" y "Corte" es **la inversa** de lo que se había asumido sin evidencia. Se corrige aquí. Si algo de esto no coincide con tu entendimiento del negocio, dilo explícitamente para ajustarlo — esto ya no es una suposición, pero puede afinarse.

## Entidades centrales (confirmadas por los documentos)

### Corte
Proceso/periodo de cierre (aparentemente quincenal) que **genera**, para todas las distribuidoras activas: (a) el cálculo de pagos a realizar de cada cliente/vale, y (b) el cálculo de puntos. El negocio distingue explícitamente "corte de pagos" y "corte de puntos" como dos cierres relacionados al mismo periodo (`"cuando el corte esté listo, cuando el corte de puntos esté listo"`).

### Relación
**Es el documento/estado de cuenta que se genera para una distribuidora específica dentro de un corte específico.** No es el vínculo comercial genérico — es el corte de cobro puntual de una distribuidora. Contiene:
- Datos de la distribuidora (número, nombre, domicilio, límite de crédito, crédito disponible, puntos acumulados).
- Una **referencia de pago única** para ese corte (para conciliación bancaria).
- Fecha límite de pago y rango de fechas consideradas "pago anticipado".
- Una tabla con una línea por cada **vale** (producto/cliente) que la distribuidora administra: producto, cliente, pagos realizados (fracción X/Y), comisión, pago, recargos, total.
- Totales agregados de la relación completa.
- Datos bancarios de depósito (banco, convenio, CLABE) para que la distribuidora pague.

**Corrección:** en la primera versión de este glosario se definió "Relación" como el vínculo comercial continuo distribuidora–cliente, y "Corte" como una agrupación periódica derivada de ella. Los documentos reales muestran lo contrario: el **Corte** es el proceso periódico global, y la **Relación** es el documento de cobro específico de una distribuidora dentro de ese corte. *Distribuidora ≠ Cliente sigue siendo correcto y no cambia.*

### Vale
Cada línea de producto/crédito otorgado a un **cliente final**, administrado por una distribuidora. En la tabla de una Relación, cada fila (producto + cliente) representa un vale. El nombre del sistema ("Mis Vales") viene de esta entidad. Un vale tiene un plan de pagos (ver "Producto" abajo) y un progreso de pagos realizados.

### Producto (plan del vale)
Código que identifica el plan de pagos del vale, observado en los ejemplos como `"2/10 Plus"`, `"4/8"`, `"4/12 Normal"`, `"5/10"`. Aparenta combinar número de quincenas totales con una modalidad (`Plus`, `Normal`). ⚠️ **PENDIENTE confirmar**: el significado exacto del primer número (¿quincena actual de inicio? ¿otra variable?) y el catálogo completo de modalidades — no inventar más planes de los observados.

### Distribuidora
Confirmado: entidad que administra el crédito otorgado a sus clientes finales. Tiene: número de distribuidora, límite de crédito, crédito disponible, puntos, y una **categoría** (Cobre / Plata / Oro) que determina su porcentaje de ganancia (ver `BUSINESS_RULES.md` §2).

### Cliente final
La persona que recibe el vale/crédito, administrada bajo una distribuidora. Distinto de la distribuidora (*Distribuidora ≠ Cliente* se mantiene).

### Presolicitud / Solicitud
- **Presolicitud:** etapa inicial de una petición de crédito, evaluada por un **Verificador**.
- **Solicitud:** ya evaluada/verificada, pasa por **Coordinador** (autoriza) y **Gerente** (verificación final). *Solicitud ≠ Crédito* se mantiene: el crédito es el resultado ya aprobado.

### Referencia (de pago) ≠ Folio (de pago)
Confirmado con el Excel bancario:
- **Referencia:** identificador único generado por Mis Vales para cada Relación (una por distribuidora por corte), usado para that la distribuidora deposite/transfiera identificando su pago (`"16A67819042"` en el ejemplo).
- **Folio de pago:** identificador que asigna el banco/canal de pago a la transacción (`"A178934"`, `"561290"`, etc. en el Excel). Al conciliar, Mis Vales compara la **Referencia** interna contra el registro bancario, no el folio bancario contra sí mismo.

### Conciliación
Proceso de cruzar los movimientos del banco (columnas del Excel: `Concepto`, `Referencia`, `Pago`, `Folio de pago`, `Fecha de pago`, `Hora`, `tipo de pago`) contra la Referencia de pago única de cada Relación, para determinar qué distribuidora pagó, qué día y qué total. `tipo de pago` observado: Transferencia, Banca en línea, Pago en ventanilla.

### Roles de usuario (confirmados)
| Rol | Qué hace / qué recibe |
|---|---|
| Distribuidora | Administra clientes/vales; recibe notificaciones de vale feriado, corte listo, corte de puntos listo, cambios de límite de crédito |
| Verificador | Evalúa presolicitudes |
| Coordinador | Autoriza solicitudes ya evaluadas por el Verificador; es notificado si un cliente final cambia a moroso |
| Gerente | Verificación final de solicitudes; notificado al terminar corte de pagos y de puntos |
| Cajera | Notificada cuando una distribuidora es deshabilitada/cae en cuenta morosa, o un cliente final cambia a moroso |

### Categoría
Nivel asignado a una distribuidora (Cobre, Plata, Oro) que determina su porcentaje de ganancia/comisión sobre el costo del producto. Ver `BUSINESS_RULES.md` §2.

### Puntos
Sistema de recompensa acumulado por distribuidora, calculado por corte, condicionado al comportamiento de pago (ver fórmula en `BUSINESS_RULES.md` §5). 1 punto = $2 MXN.

### Comportamiento de pago (tres tipos, confirmados)
- **Pago anticipado:** dentro del rango de días marcado como "pago anticipado" en la Relación (ej. 13, 14, 15 de febrero, si el límite es el 16). Es el único que genera puntos.
- **Pago puntual:** en la fecha límite.
- **Pago fuera de tiempo:** después de la fecha límite. Elimina el 20% de los puntos acumulados y genera comisión por no pago / recargo.

### Recargo / Multa / Comisión por no pago
Monto que se suma a la deuda total del vale cuando no se paga en la quincena correspondiente (ejemplo observado: $300). Ver `BUSINESS_RULES.md` §4.

## Tabla resumen actualizada

| Término | Qué es | Qué NO es |
|---|---|---|
| Corte | Proceso periódico global que calcula pagos y puntos de todas las distribuidoras | No es el documento individual de una distribuidora |
| Relación | Documento de cobro de **una** distribuidora dentro de **un** corte, con su referencia de pago única | No es el vínculo comercial genérico distribuidora–cliente (corrección vs. v1) |
| Vale | Línea de crédito/producto de un cliente final dentro de una Relación | No es la Relación completa (una Relación agrupa varios vales) |
| Distribuidora | Otorga y administra crédito a clientes finales | No es el cliente final |
| Cliente final | Recibe el vale/crédito | No es la distribuidora |
| Presolicitud | Petición inicial, evaluada por Verificador | No es la Solicitud ya autorizada |
| Solicitud | Petición evaluada, en proceso de autorización (Coordinador) y verificación (Gerente) | No es el Crédito ya activo |
| Referencia (de pago) | Identificador interno único por Relación/corte | No es el Folio de pago bancario |
| Folio de pago | Identificador que asigna el banco/canal a la transacción | No es la Referencia interna |
| Categoría | Nivel de la distribuidora (Cobre/Plata/Oro) que fija su % de ganancia | No es la categoría del cliente final (no documentada) |

## Pendiente de confirmar

- Significado exacto del primer número en el código de producto (`"2/10 Plus"`).
- Definición completa del estado "moroso" (días de atraso, umbral) para clientes finales y distribuidoras.
- Qué significa exactamente que "un vale sea feriado" (¿el corte cae en día feriado y se recorre la fecha?).
- Catálogo completo de modalidades de producto más allá de `Plus`/`Normal` observadas.

---

## Ampliación — Estructura organizacional, roles y ciclo de vida del vale

> Fuente: notas de reunión sobre el proyecto (documento "PROYECTO Mis vales"). Este documento confirma y refuerza el modelo corregido arriba (Relación = estado de cuenta por distribuidora/corte), y agrega la capa organizacional que faltaba. También trae preguntas sin resolver del propio negocio — se preservan como tal, no se responden por interpretación.

### Sucursal
Unidad organizacional. Existe una **sucursal matriz** y varias **sucursales**; la matriz funciona igual que cualquier sucursal (confirmado explícitamente en la fuente). Una distribuidora se da de alta en una sucursal y se administra ahí. El Gerente General ve todas las sucursales; el Gerente de Sucursal solo ve la suya.

### Roles (lista completa confirmada)
- **Gerente General:** máxima autoridad; ve y autoriza across todas las sucursales; da de alta gerentes de sucursal y sucursales nuevas; mantiene el catálogo de productos.
- **Gerente de Sucursal:** responsable de una sola sucursal; autoriza altas de distribuidora y aumentos de crédito dentro de su sucursal.
- **Coordinador:** primer filtro; captura la información inicial para que alguien se convierta en distribuidora; puede tener muchas distribuidoras a su cargo.
- **Verificador:** revisa/valida en sitio (visita física, fotos) la información capturada por el Coordinador; puede corregir errores; deja comentarios y determina si la solicitud cumple o no.
- **Administrador:** rol de solo lectura ("logger"). Ve toda la información, historial y logs de auditoría (quién, cuándo, hora, dispositivo), pero **no puede escribir, autorizar, ni descargar relaciones**. Es apoyo a los gerentes.
- **Distribuidora:** entidad con línea de crédito propia, compartida entre los vales que otorga a sus clientes finales. Se le cobra a ella aunque el cliente final no pague.
- **Cliente final:** persona que recibe el vale (crédito en efectivo), identificada de forma única por CURP para evitar registrarse con más de una distribuidora.
- **Cajero/Cajera:** opera en la sucursal; genera folios de prevale, entrega el depósito, captura el número de autorización de la transferencia, y realiza la conciliación bancaria (sube el Excel del banco).

### Aplicaciones por rol (confirmado)
- App web adaptada a tablet (no escritorio): Coordinador y Verificador.
- App móvil no responsiva (solo celular): Distribuidora.
- App web: Gerentes (de sucursal y general).

### Prevale ≠ Vale digital
- **Prevale:** el primer vale que se otorga a un cliente final nuevo (su registro inicial en el sistema).
- **Vale digital:** cualquier vale posterior para ese mismo cliente, una vez que ya liquidó el prevale. En este punto la cajera ya no pide todos los documentos de alta, solo identificación (por posible cambio de domicilio).
- Regla confirmada: **no se puede dar un segundo vale activo a una misma persona** (debe liquidar el actual antes de recibir otro).

### Token de autorización
Mecanismo que habilita a la cajera para corregir datos de un cliente final ya capturados, únicamente después de que un Coordinador, Gerente de Sucursal o Gerente General autoriza la corrección.

### Previa transferencia
Proceso mediante el cual una distribuidora "libera" a un cliente final (que no le debe) hacia otra distribuidora; requiere que la distribuidora/coordinador receptor acepte. Si se acepta, el siguiente vale del cliente ya es vale digital, no prevale.

### Catálogo de productos
Lista de montos de vale disponibles. La mantiene únicamente el Gerente General. Regla confirmada: los montos siempre son múltiplos de 100.

## Preguntas abiertas del negocio (sin resolver — no asumir respuesta)

Estas preguntas están explícitamente sin resolver en la fuente. No se debe codificar ningún comportamiento que las de por hechas:

1. El rol exacto de "Sebastián" en el ejemplo del documento generó confusión incluso en la reunión original: ¿es siempre el cliente final quien solicita el vale, o hay casos donde la distribuidora es también la beneficiaria directa? El resto del documento (ej. "al que le van a cobrar es a la distribuidora, aunque el cliente no pague") sugiere que el crédito es de la distribuidora y el vale se otorga al cliente final, pero esto no quedó cerrado al 100%.
2. ¿Existe un monto mínimo de vale (ej. $5), o solo aplica el máximo del 50% en el primer vale?
3. ¿La regla del 50% en el primer vale se reactiva si el cliente vuelve a tener el 100% del crédito disponible, o solo aplica una vez, en el registro inicial?
4. Al transferir una distribuidora de sucursal/coordinador: ¿se transfieren también sus clientes, su crédito, sus productos activos?
5. ¿Qué se requiere exactamente (datos/procesos) para un cambio de sucursal?
6. ¿Existe límite en el número de solicitudes de cambio de sucursal o de transferencia de cliente?
7. ¿Qué pasa si una distribuidora nunca liquida sus relaciones pendientes después del "tercer strike" de morosidad (se mencionan 2 perdones, la 3ra no) — se da de baja, se suspende indefinidamente?
8. ¿El Gerente General puede ver/autorizar movimientos de los Coordinadores, no solo de Gerentes de sucursal, Distribuidoras y Cajeras?
9. ¿La sucursal matriz hereda automáticamente sus distribuidoras a las demás sucursales, o cada sucursal parte de cero?
