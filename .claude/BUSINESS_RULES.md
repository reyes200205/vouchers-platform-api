# BUSINESS_RULES.md — Reglas de negocio de Mis Vales

> **Actualizado con documentos fuente:** *Análisis de cálculo de relación*, *Relación (ejemplo)*, *Excel de Banco*.
> Las fórmulas de esta versión están tomadas directamente del documento de análisis, incluyendo su ejemplo numérico. Donde el documento no es explícito, se marca ⚠️ PENDIENTE en vez de inventar la regla. Antes de que esto se convierta en código de producción, valida con el negocio los puntos marcados como pendientes o como interpretación.

## 1. Módulos de negocio

Distribuidoras, Clientes finales, Presolicitudes/Solicitudes, Créditos/Vales, Cortes, Relaciones (documento de cobro por distribuidora/corte), Pagos, Conciliación bancaria, Comisiones, Categorías, Puntos, Morosidad, Notificaciones, Reportes, Auditorías/Autorizaciones.

## 2. Categorías de distribuidora y comisión (ganancia)

Cada distribuidora tiene una categoría que determina su **porcentaje de ganancia** sobre el costo del producto otorgado:

| Categoría | % de ganancia (comisión distribuidora) |
|---|---|
| Cobre | 3% |
| Plata | 6% |
| Oro | 10% |

Esta ganancia se calcula **sobre el costo del producto (principal del vale)** y se reparte entre las quincenas del plazo:

```
ganancia_distribuidora_total = costo_producto * porcentaje_categoria
ganancia_distribuidora_por_quincena = ganancia_distribuidora_total / numero_de_quincenas
```

Ejemplo del documento fuente (categoría Plata, 6%, producto de $15,000, 8 quincenas):
```
15,000 * 6% = 900
900 / 8 = 112.5   → ganancia de la distribuidora por quincena
```

⚠️ **PENDIENTE:** confirmar si esta ganancia se paga a la distribuidora inmediatamente por quincena o se acumula y liquida al final del plazo del vale, y si depende de que el cliente final haya pagado esa quincena.

## 3. Cálculo del vale (préstamo) — costo total al cliente final

Ejemplo del documento fuente: préstamo de $15,000 a 8 quincenas.

Componentes del costo total a financiar:

| Concepto | Cálculo | Monto (ejemplo) |
|---|---|---|
| Principal | monto solicitado | $15,000 |
| Comisión (del crédito) | 10% del principal | $1,500 |
| Seguro | monto fijo, varía según la cantidad solicitada | $100 |
| Interés | 5% del principal, por quincena, × número de quincenas | 5% × 15,000 × 8 = $6,000 |
| **Total a financiar** | suma de los anteriores | **$22,600** |

```
total_a_financiar = principal + comision_credito + seguro + (tasa_quincenal * principal * num_quincenas)
pago_quincenal_base = total_a_financiar / num_quincenas
```

Ejemplo: `22,600 / 8 = $2,825` por quincena.

**Nota sobre la comisión del pago quincenal:** el documento indica *"Pago Quincenal − comisión, o sin comisión (se suma multa a la deuda total)"* — interpretación: el pago quincenal regular puede aplicarse restando la parte proporcional de comisión, **o** si no se paga en tiempo, la multa/comisión por no pago se suma a la deuda total en lugar de descontarse. ⚠️ **PENDIENTE confirmar la mecánica exacta** de cuándo se resta vs. cuándo se suma.

⚠️ **PENDIENTE:**
- Si el 10% de comisión y el 5% de interés quincenal son **fijos para todos los productos** o varían por plan/producto (`2/10 Plus`, `4/8`, `4/12 Normal`, `5/10`, etc.).
- Tabla de montos de seguro según rango de monto solicitado (el documento solo dice "varía según la cantidad").

## 4. Comisión por no pago (multa/recargo)

Ejemplo del documento fuente: **$300** por incumplimiento de pago en una quincena. Este monto se suma a la deuda total del vale (no se descuenta del pago quincenal).

⚠️ **PENDIENTE:** confirmar si $300 es un monto fijo siempre, o varía según el monto del vale/plazo restante; y si se aplica una sola vez por vale o cada quincena que se incumple.

## 5. Puntos

Los puntos se calculan **por corte**, y **solo aplican si el pago fue anticipado**:

```
puntos_del_corte = floor(total_productos_otorgados_en_el_corte / 1200) * 3
```

- `total_productos_otorgados_en_el_corte`: suma en pesos de los vales/productos otorgados durante ese corte (⚠️ pendiente confirmar si es por distribuidora o global — el contexto sugiere que es por distribuidora, ya que los puntos se reportan "por distribuidora al corte").
- El redondeo es siempre hacia abajo (piso).
- **1 punto = $2 MXN** (valor de conversión/canje).

**Penalización por pago fuera de tiempo:**
```
si el pago fue fuera de tiempo → puntos_totales_acumulados -= 20% de puntos_totales_acumulados
```

⚠️ **PENDIENTE:** mecánica exacta de canje de puntos (¿se convierten a saldo de crédito? ¿a efectivo? ¿a descuento?), y si la resta del 20% aplica sobre los puntos de ese corte o sobre el acumulado histórico total de la distribuidora.

## 6. Comportamiento de pago

Tres estados observados, con efectos distintos:

| Comportamiento | Efecto |
|---|---|
| Pago anticipado (dentro del rango de días marcado como anticipado en la Relación) | Genera puntos (§5) |
| Pago puntual (en la fecha límite) | Sin efecto especial documentado — cumple, no genera puntos ni multa |
| Pago fuera de tiempo | Resta 20% de puntos acumulados; genera comisión por no pago/recargo (§4) que se suma a la deuda |

## 7. Relaciones (documento de cobro por distribuidora/corte)

Cada Relación (ver definición corregida en `TERMINOLOGY.md`) contiene, confirmado por el documento de ejemplo:

- Número de distribuidora, nombre, domicilio.
- Límite de crédito y crédito disponible.
- Puntos acumulados.
- **Referencia de pago única** (para esa distribuidora, en ese corte).
- Fecha límite de pago.
- Rango de fechas de "pago anticipado" (ejemplo: 3 días antes de la fecha límite).
- Total a pagar (suma de todos los vales de esa distribuidora en ese corte).
- Tabla de vales: producto, cliente, pagos realizados (X/Y cuotas), comisión, pago, recargos, total por vale.
- Datos bancarios de depósito (nombre beneficiario, banco(s), número de convenio, CLABE) — en el ejemplo: BBVA y Banorte, cada uno con su propio convenio y CLABE.

## 8. Conciliación bancaria

Confirmado por el Excel de Banco (columnas reales): `item, Concepto, Referencia, Pago, Folio de pago, Fecha de pago, Hora, tipo de pago`.

Reglas:
- Cada Relación genera una **Referencia** única por corte (ver §7).
- El banco reporta movimientos con esa Referencia, un **Folio de pago** propio del banco, fecha, hora y tipo de pago (`Transferencia`, `Banca en línea`, `Pago en ventanilla` son los tipos observados).
- La conciliación cruza `Referencia` del banco contra la `Referencia de Pago` de la Relación para determinar automáticamente qué distribuidora pagó, qué día y qué monto.

⚠️ **PENDIENTE:**
- Qué pasa cuando el monto pagado no coincide exactamente con el total a pagar de la Relación (pago parcial vs. pago excedente).
- Si la conciliación es 100% automática por coincidencia de Referencia + monto, o requiere validación manual de un rol (posiblemente Cajera, dado que es notificada de temas de cobranza).

## 9. Notificaciones por rol

| Rol | Se notifica cuando... |
|---|---|
| Distribuidora | Un vale cae en día feriado (fecha se recorre); el corte de pagos está listo; el corte de puntos está listo; su límite de crédito fue autorizado o incrementado |
| Verificador | Una presolicitud queda terminada (lista para evaluar) |
| Coordinador | Un verificador evaluó una de sus solicitudes; un cliente final cambia a moroso; debe autorizar una solicitud |
| Gerente | Una solicitud fue verificada; termina un corte de pagos y de puntos |
| Cajera | Una distribuidora fue deshabilitada o entró en cuenta morosa; un cliente final cambió a estado moroso |

## 10. Reportes requeridos

- Distribuidoras morosas y sus saldos.
- Saldo de cortes.
- Saldo de puntos por distribuidora, al corte.
- Presolicitudes pendientes y validadas.
- Pagos de distribuidoras.

## 11. Auditorías y Autorizaciones (reglas duras — se mantienen de la v1)

- Nunca eliminar historial de relaciones, cortes o pagos.
- Nunca modificar una relación (documento de cobro) ya cerrada/liquidada.
- Toda acción sensible (conciliación manual, ajuste de crédito, autorización de solicitud, cambio a moroso) debe quedar auditada: quién, cuándo, valor anterior, valor nuevo.

⚠️ **PENDIENTE:** flujo exacto de autorización (¿Coordinador autoriza solo, o requiere doble aprobación con Gerente?), y umbral/definición exacta de morosidad (días de atraso o monto).

## 12. Qué sigue pendiente de documentos aún no compartidos

- Definición completa del catálogo de "productos"/planes de vale más allá de los 4 ejemplos observados (`2/10 Plus`, `4/8`, `4/12 Normal`, `5/10`).
- Umbral numérico exacto de morosidad.
- Mecánica de canje de puntos.
- Tabla de montos de seguro según rango de monto solicitado.
- Reglas de pago parcial/excedente en conciliación.

---

## 13. Estructura organizacional (sucursales y jerarquía de roles)

- Existe una **sucursal matriz** y varias **sucursales**; la matriz opera con las mismas capacidades que cualquier sucursal (confirmado).
- Una distribuidora se da de alta en una sucursal específica y se administra ahí.
- **Visibilidad:** Gerente General ve y puede operar sobre todas las sucursales. Gerente de Sucursal solo ve/opera la suya. ⚠️ **PENDIENTE:** confirmar si el Gerente General puede ver movimientos que autorizan los Coordinadores directamente, o solo lo que autorizan los Gerentes de sucursal, Distribuidoras y Cajeras.
- **Administrador:** acceso de solo lectura a toda la información (distribuidoras, historial, logs de auditoría con fecha/hora/dispositivo). No puede escribir, autorizar ni descargar relaciones.

## 14. Proceso de alta de una distribuidora (onboarding)

1. El aspirante acude con un **Coordinador**, quien captura toda su información.
2. Un **Verificador** hace una visita física, toma fotos, corrobora los datos capturados por el Coordinador. Si hay errores, el Verificador los corrige (⚠️ el documento fuente tiene una confusión sin resolver sobre si es el Coordinador o el Verificador quien edita — ver `TERMINOLOGY.md` §Preguntas abiertas).
3. Toda corrección de datos en esta etapa debe quedar en auditoría: valor original vs. valor modificado.
4. El Verificador deja comentarios y determina **cumple / no cumple**. Si no cumple, el proceso termina ahí.
5. Si cumple, la autorización final la da el **Gerente General o el Gerente de Sucursal**, quien asigna: límite de crédito inicial, usuario, contraseña y contrato.

## 15. Reglas de crédito, prevale y vale digital

- La distribuidora tiene **una sola línea de crédito**, compartida entre todos los vales (clientes finales) que administra.
- **Prevale** (primer vale de un cliente final nuevo): no puede superar el **50% del crédito disponible** en ese momento. Ejemplo del documento: con $10,000 disponibles, se permite 10 quincenas de $5,000 (=50%), no 8 quincenas de $10,000 (=100%).
- **Vale digital** (vales subsecuentes del mismo cliente, tras liquidar el prevale): la cajera solo pide identificación (para verificar cambios de domicilio), no repite el proceso de alta completo.
- **No se puede otorgar un segundo vale activo a la misma persona.** Debe liquidar el actual antes de recibir otro.
- **Unicidad del cliente:** el sistema debe impedir que un cliente (identificado por CURP) se registre con más de una distribuidora simultáneamente.
- **Margen de tolerancia:** ± $500 (contexto de aplicación no cerrado del todo, aparentemente para conciliación/pagos — ⚠️ confirmar alcance exacto).
- **Catálogo de productos:** los montos de vale disponibles siempre son múltiplos de 100, y el catálogo lo mantiene únicamente el Gerente General.

⚠️ **PENDIENTE (preguntas explícitas del negocio, sin resolver):**
- Si existe un monto mínimo de vale.
- Si la regla del 50% se reactiva cada vez que el cliente vuelve a tener el 100% de crédito disponible, o solo aplica en el registro inicial.

## 16. Alta y depósito del prevale (flujo operativo)

1. El cliente final acude a la sucursal de su distribuidora con el folio de prevale (generado por el sistema).
2. La cajera solicita identificación oficial y comprobante de domicilio.
3. Si todo es correcto, se hace el depósito a la cuenta bancaria del cliente (⚠️ confirmar si siempre es cuenta del cliente final o puede variar).
4. La cajera captura el número de autorización de esa transferencia bancaria.
5. Si hay un error en los datos capturados: la cajera **no puede modificar nada sin autorización previa**. Debe solicitar autorización a Coordinador de la sucursal, Gerente de Sucursal o Gerente General (según el caso). Una vez autorizado, recibe un **token** que habilita la corrección; después se libera el depósito.

## 17. Aumento de línea de crédito

- Lo solicita la distribuidora a su Coordinador, quien revisa su historial y propone una "pre-autorización" (ej. $20,000).
- La solicitud llega al Gerente correspondiente, quien puede aceptar el monto propuesto o reducirlo.
- Si se incrementa el crédito mientras hay vales activos, el crédito ya usado se descuenta del nuevo total (no se otorga crédito de más sobre lo ya comprometido).
- La regla del 50% para primer vale (§15) **aplica sobre el crédito disponible en el momento**, no sobre el crédito total histórico (confirmado explícitamente en la fuente).
- Ejemplo del documento: crédito original $10,000 → se prestan $3,000 (quedan $7,000 disponibles) → autorizan $5,000 más → nuevo total $13,000 (disponible pasa a $12,000).

## 18. Conciliación bancaria (ampliación operativa)

- La cajera descarga del banco el Excel de movimientos y lo sube al sistema.
- Cada Relación trae un código único; el sistema hace match automático (abono por abono) contra ese código.
- **Si no hay match** (ej. error de captura del número de relación, como 13334 en vez de 13333): el pago queda sin conciliar y genera **saldo a favor de la sucursal** hasta resolverse.
- El cliente/distribuidora puede levantar una queja adjuntando comprobante de pago (foto).
- La cajera hace entonces una **conciliación manual**: revisa la queja, el folio, corrobora el abono no reconocido, y **requiere autorización** (Gerente de Sucursal, Coordinador de la distribuidora, o Gerente General) para aplicar la conciliación manual.

## 19. Transferencias

### Distribuidora entre sucursales/coordinadores
- Un Gerente de Sucursal puede pasar distribuidoras a otro Coordinador.
- Un Coordinador puede pasar todo lo de una distribuidora a otra distribuidora.
- Un Gerente puede pasar todas las distribuidoras de un Coordinador saliente a otro Coordinador.
- ⚠️ **PENDIENTE:** si al transferir se llevan también sus clientes, crédito y productos activos; qué información/proceso exacto se requiere para un cambio de sucursal; si el Gerente General también puede reasignar coordinadores (no solo el Gerente de Sucursal); si hay límite de solicitudes de cambio.

### Cliente final entre distribuidoras
- Solo es posible si el cliente **no tiene deuda** con la distribuidora actual.
- La distribuidora actual inicia una "previa transferencia"; la distribuidora/coordinador receptor debe aceptarla.
- Al aceptarse, el siguiente vale del cliente es **vale digital**, no prevale (conserva su historial de "ya verificado").

## 20. Morosidad y regla de perdón

- Se cobra siempre a la **distribuidora**, sin importar si el cliente final pagó o no.
- Si una sucursal/distribuidora no paga, el Gerente le puede **retirar la capacidad de otorgar vales** hasta que liquide las relaciones pendientes.
- **Regla de perdón (three-strike):** la primera relación no liquidada se perdona, la segunda también, **la tercera ya no**.
- ⚠️ **PENDIENTE:** qué ocurre exactamente tras el tercer incumplimiento (¿baja definitiva?, ¿suspensión indefinida?).
- Existe un módulo para que los Gerentes vean, después de la fecha de pago, qué distribuidoras: liquidaron completo, abonaron parcial, o no pagaron nada.

## 21. Puntos, categorías y valor en efectivo

- Los puntos se otorgan/quitan según puntualidad de pago (ver fórmula ya definida en §5 de este documento).
- Los puntos son **canjeables por efectivo**; el valor de 1 punto es configurable (ejemplo dado en la fuente: 1 punto = $5 a fin de año — distinto del ejemplo de $2 visto en el documento de análisis; **ambos valores existen en las fuentes y no está claro si son el mismo parámetro en momentos distintos o dos conceptos distintos** — ⚠️ confirmar).
- **Categoría de la distribuidora es dinámica:** una distribuidora puede subir de categoría (ej. Plata → Oro) según su desempeño, lo cual incrementa su porcentaje de comisión (§2). No se documenta el criterio exacto de ascenso — ⚠️ **PENDIENTE**.

## 22. Datos personales requeridos (KYC)

**Para cliente final y distribuidora (datos base):**
Nombre, apellido paterno, apellido materno, CURP, RFC, fecha de nacimiento, calle, número, colonia, código postal, lugar de nacimiento, estado, ciudad.

**Adicional solo para distribuidora:**
Datos de familiares y cónyuge; dónde trabaja / dónde estudia; datos de vehículos; datos de la vivienda (propia, rentada, en proceso de pago, Infonavit, crédito bancario, dimensiones, referencia laboral). Esta información es la base para decidir el límite de crédito a autorizar.

## 23. Reglas transversales de configuración (no hardcodear)

Confirmado explícitamente como requisito no negociable del sistema: **ningún porcentaje, fecha de corte, fecha de pago, tasa, margen de tolerancia o parámetro similar debe quedar fijo en código.** Todo debe vivir en catálogos de configuración editables: fechas de corte, fechas de pago, porcentajes de interés/comisión por categoría, catálogo de productos (montos), catálogo de sucursales, catálogo de roles. Esto es una regla de arquitectura tanto como de negocio — impacta directamente `ARCHITECTURE.md` (ver actualización correspondiente).

## 24. Auditoría (ampliación)

Toda corrección de datos capturados en el proceso de verificación debe registrar: valor original al dar de alta y cada valor modificado posteriormente, con su autor. Todo acceso de autorización debe quedar loggeado con fecha, hora y dispositivo (computadora o celular) usado.
