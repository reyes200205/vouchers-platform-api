# Plan de pruebas — Mis Vales

Pega cada sección (##) como una página o bloque separado en Notion. Cada línea con `- [ ]` se
convierte en un checkbox que puedes ir marcando mientras pruebas.

## 0. Preparación del entorno

- [ ] Backend levantado (`php artisan serve`) con BD real migrada y sembrada (`migrate:fresh --seed`)
- [ ] Frontend `nuxt-app` levantado y apuntando al backend correcto
- [ ] Usuario de prueba `super-admin` creado (requiere MFA/OTP por correo)
- [ ] Usuario de prueba `general_manager` creado
- [ ] 2 usuarios `branch_manager` en sucursales distintas (para probar aislamiento)
- [ ] Usuarios de prueba: `coordinator`, `verifier`, `cashier`, `distributor`
- [ ] Tener a la mano una CURP y RFC válidos de prueba (hay validador propio, no acepta cualquier string)

---

## 1. Autenticación y sesión

- [ ] Login normal con usuario/password válidos → token emitido, redirige a su portal correcto
- [ ] Login de super-admin pide OTP por correo antes de emitir token
- [ ] OTP incorrecto → rechazo con mensaje claro, se audita como `MFA_FAILED`
- [ ] OTP con reintentos agotados → bloqueo/mensaje específico, no error 500
- [ ] Password incorrecto → 401/422 sin revelar si el usuario existe
- [ ] Usuario inactivo no puede loguear (mensaje explícito, no error genérico)
- [ ] Rol equivocado navegando a URL de otro portal → redirige automáticamente a su portal
- [ ] Flujo "olvidé mi contraseña" completo: correo, cambio, login con password nueva funciona y la vieja ya no

---

## 2. Gestión de usuarios / personal (Staff)

Regla clave: solo `super-admin` crea `general_manager`. `branch_manager` solo crea
`cashier`/`coordinator`/`verifier` dentro de su propia sucursal.

- [ ] Super-admin crea un `general_manager` → éxito, login funcional con ese usuario
- [ ] Gerente general crea una cajera en cualquier sucursal → éxito
- [ ] Gerente de sucursal crea un coordinador en SU sucursal → éxito
- [ ] Gerente de sucursal intenta asignar personal a OTRA sucursal → bloqueado (403)
- [ ] Gerente de sucursal intenta crear un `general_manager` → bloqueado (403)
- [ ] Gerente de sucursal intenta crear otro `branch_manager` → bloqueado (403)
- [ ] CURP con formato inválido → error de validación, no crea nada
- [ ] CURP duplicada (ya existe en otro usuario) → error claro antes de tocar BD
- [ ] Username duplicado → error claro, no 500
- [ ] Password menor a 8 caracteres → rechazado
- [ ] Intentar asignar un rol no administrable desde este módulo (ej. `distributor`) → 422 con mensaje de negocio
- [ ] Desactivar un usuario desde el listado → ya no puede loguear, se audita `STAFF_UPDATED`
- [ ] Gerente de sucursal sube de rol a un cashier existente (a coordinador/verificador) dentro de su sucursal → permitido
- [ ] Revisar logs de auditoría tras crear/editar staff → aparece `STAFF_CREATED`/`STAFF_UPDATED` con actor, antes/después, sucursal
- [ ] Gerente de sucursal ve el listado de personal → solo ve su(s) sucursal(es), no el universo completo
- [ ] Un rol sin permiso (ej. cashier) intenta acceder al módulo de Personal → 403, opción ni debería aparecer en el menú

---

## 3. Sucursales

⚠️ Módulo con menos pruebas automatizadas hasta ahora — prioridad alta.
Regla clave: crear/editar sucursal es exclusivo de `general_manager` (confirmar si super-admin
debería poder también).

- [ ] Gerente general crea una sucursal nueva (nombre, dirección, teléfono) → éxito
- [ ] Si no se da código, se autogenera algo tipo `BR-NOMBRE-SLUG`
- [ ] Crear dos sucursales con el mismo nombre → la segunda no truena, genera código distinto
- [ ] Forzar un código que ya existe → error de validación, no 500
- [ ] Crear sucursal asignando un gerente (`manager_user_id`) → ese usuario queda como branch_manager de esa sucursal
- [ ] Super-admin intenta crear sucursal → confirmar si se bloquea (según reglas actuales, sí) y si es el comportamiento deseado
- [ ] Gerente de sucursal intenta crear una sucursal → bloqueado, opción no debería aparecer en el menú
- [ ] Coordinador ve el listado de sucursales pero no puede editar
- [ ] Editar sucursal y cambiar el gerente asignado → el anterior pierde el rol, el nuevo lo obtiene
- [ ] Editar sucursal y quitar el gerente (`manager_user_id: null`) → sucursal queda sin gerente
- [ ] Guardar sucursal sin nombre → error de validación
- [ ] Gerente de sucursal ve el listado → solo ve su(s) sucursal(es)
- [ ] Configurar fecha de corte/comisiones en Ajustes de sucursal → se guarda y afecta el próximo cálculo
- [ ] Revisar logs de auditoría tras crear/editar sucursal → aparece `BRANCH_CREATED`/`BRANCH_UPDATED` con antes/después

---

## 4. Alta de distribuidoras (Coordinador → Verificador → Gerencia)

Confirmar primero si el frontend ya tiene el formulario de registro y la pantalla de verificación
en campo implementados (estaban marcados como pendientes en `ROLES_Y_TAREAS.md`).

- [ ] Flujo completo feliz: coordinador registra solicitud → asigna verificador → verificador sube evidencia y checklist → gerencia aprueba → se genera distribuidora + usuario + rol + token de activación
- [ ] Verificador rechaza en la visita → estado pasa a rechazada, no llega a bandeja de gerencia
- [ ] Gerencia rechaza en la decisión final → no se crea distribuidora ni usuario
- [ ] Intentar avanzar sin documentos/fotos obligatorias → bloqueo de validación con mensaje claro
- [ ] Registrar solicitante con CURP que ya existe en otra distribuidora → confirmar comportamiento (bloqueo o advertencia según regla vigente)
- [ ] Al aprobar, asignar límite de crédito inicial y categoría → línea disponible desde el primer vale, respetando el 50% en el primer vale

---

## 5. Vales (Vouchers)

- [ ] Distribuidor emite primer vale ≤ 50% del crédito → se pre-emite sin bloqueo
- [ ] Distribuidor intenta emitir primer vale por más del 50% → bloqueado con mensaje de negocio
- [ ] Vale que requiere aprobación de coordinador → aparece en su bandeja, puede aprobar/rechazar
- [ ] Cajera aprueba y desembolsa vale → estado pasa a activo/entregado, queda auditado
- [ ] Pre-vale sin presentarse pasado el plazo → expira automáticamente (confirmar si ya está implementado)
- [ ] Cajera rechaza un vale en sucursal → se revierte correctamente, no deja crédito "atorado"

---

## 6. Clientes y traspasos

- [ ] Distribuidor registra cliente con CURP válida → creado, visible en su tabla
- [ ] Intentar registrar CURP ya usada por otra distribuidora → confirmar si bloquea
- [ ] Distribuidor solicita traspaso de un cliente de otra distribuidora → aparece en bandeja de decisión
- [ ] Coordinador/gerente aprueba traspaso → cliente cambia de distribuidora, saldo/crédito se recalcula
- [ ] Coordinador/gerente rechaza traspaso → cliente se queda donde estaba
- [ ] Distribuidor cancela su propia solicitud antes de decisión → vuelve a estado neutro

---

## 7. Incrementos de crédito

- [ ] Flujo completo: solicitud → pre-autorización → decisión final, respetando los 3 roles distintos
- [ ] Al pre-autorizar/decidir, el monto final puede ser menor al solicitado, nunca mayor
- [ ] Rechazo en cualquier paso corta el flujo, no aplica el incremento

---

## 8. Conciliaciones bancarias

- [ ] Importar archivo bancario → transacciones se cargan y hacen match automático por referencia/monto/fecha
- [ ] Match manual de un depósito con referencia mal capturada → requiere evidencia/autorización, queda auditado
- [ ] Verificar/cerrar una conciliación → estado final, ya no editable sin nueva autorización
- [ ] Importar el mismo archivo bancario dos veces → no duplica transacciones ni pagos

---

## 9. Puntos

- [ ] Puntos se acumulan automáticamente al pagar, según regla configurable (no hardcode)
- [ ] Distribuidor solicita canjear puntos por dinero/abono → aparece en bandeja de decisión
- [ ] Gerente aprueba canje → puntos se descuentan y valor se aplica
- [ ] Gerente rechaza canje → puntos quedan intactos

---

## 10. Auditoría

- [ ] Solo super-admin puede ver `/audit-logs`, otros roles reciben 403
- [ ] Filtrar logs por severidad (nuevo campo `WARNING`) funciona correctamente
- [ ] Un evento sensible (login fallido, cambio de crédito, conciliación manual) siempre tiene actor, antes/después, fecha y sucursal — sin campos vacíos

---

## Orden sugerido de ejecución

1. Sucursales (sección 3) — menos cobertura automatizada, mayor riesgo
2. Staff/Usuarios (sección 2) — validar la UI real, no solo la API
3. Auth + MFA (sección 1) — crítico, bloquea al super-admin si falla
4. Resto en orden del ciclo de negocio: distribuidora → vales → clientes → crédito → conciliación → puntos
5. Auditoría (10) al final, como verificación cruzada

## Cómo registrar un hallazgo

Por cada bug: módulo, rol usado, pasos exactos, resultado obtenido vs esperado, y si puedes
identificarlo, el archivo/componente donde probablemente está la causa.
