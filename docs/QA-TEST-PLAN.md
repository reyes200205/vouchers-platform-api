# Plan de pruebas — Mis Vales (Backend + Frontend)

> Generado a partir del análisis del código actual: rutas (`routes/api/v1.php`), reglas de negocio
> (`config/business-authorization.php`), controladores/servicios de `app/Http` y `app/Services`,
> y las páginas ya conectadas en `nuxt-app/app/pages`. No se ejecutó nada — esto es un plan para
> correr manualmente (o automatizar después) contra el backend + frontend levantados.

## 0. Preparación del entorno

- **Backend**: `php artisan serve` (o Docker) con `.env` apuntando a una BD real (no la de test en
  memoria) + `php artisan migrate:fresh --seed` para tener roles, sucursales y usuarios base.
- **Frontend**: `nuxt-app` con `.env` → `NUXT_PUBLIC_API_BASE` (o variable equivalente) apuntando a
  `http://localhost:8000/api/v1`.
- **Usuarios de prueba necesarios** (crear vía seeder o Tinker si no existen):
  - 1 `super-admin` (con MFA/OTP — es el único rol que lo requiere, ver
    `config/business-authorization.php:11`).
  - 1 `general_manager`.
  - 2 `branch_manager` en sucursales distintas (para probar aislamiento por sucursal).
  - 1 `coordinator`, 1 `verifier`, 1 `cashier`, 1 `distributor`.
- Tener a la mano una CURP y RFC válidos de prueba (hay validador propio `ValidCurp`/`ValidRfc`,
  no aceptan cualquier string).

Convención de cada caso: **Rol** · **Pasos** · **Resultado esperado** · **Dónde se valida en código**.

---

## 1. Autenticación y sesión (`AuthController`)

Cobertura automatizada existente: `tests/Feature/Api/V1/AuthTest.php` (22 casos), `PasswordResetTest.php`, `EmailVerificationTest.php`. Igual conviene pasarlo manualmente una vez por el flujo de UI.

| # | Caso | Rol | Pasos | Esperado |
|---|------|-----|-------|----------|
| 1.1 | Login normal | branch_manager / distributor / etc. | Login con usuario+password válidos | Token emitido, redirige a su portal (`/general`, `/distributor-portal`, `/registro-verificacion` según `useAuth.ts`) |
| 1.2 | Login con MFA | super-admin | Login → sistema pide OTP por correo → se ingresa código | Solo tras OTP correcto se emite el token; revisar que llega el correo (Mailtrap/log) |
| 1.3 | OTP incorrecto | super-admin | Ingresar código erróneo 1 vez | Rechazo con mensaje claro, se audita `MFA_FAILED` (ver `AuthController.php` diff reciente, ahora con severidad `WARNING`) |
| 1.4 | OTP con reintentos agotados | super-admin | Fallar el código N veces | Bloqueo/mensaje específico, no un 500 genérico |
| 1.5 | Credenciales inválidas | cualquiera | Password incorrecto | 401/422 sin filtrar si el usuario existe o no |
| 1.6 | Usuario inactivo | cualquiera | Desactivar un staff y loguear con él | Login rechazado explícitamente ("usuario inactivo"), no error genérico |
| 1.7 | Redirección por rol equivocado | verifier | Loguear y navegar manualmente a `/general` | Middleware de Nuxt redirige a `/registro-verificacion` (pendiente en checklist `ROLES_Y_TAREAS.md`, confirmar si ya está) |
| 1.8 | Reset de password | cualquiera | Flujo "olvidé mi contraseña" | Correo con link, cambio exitoso, login con password nueva funciona y la vieja no |

---

## 2. Gestión de usuarios / personal (`StaffController`) — ejemplo pedido

Cobertura automatizada: `tests/Feature/Api/V1/StaffTest.php` (14 casos, ya cubre bastante a nivel API). El plan aquí es validar lo mismo **end-to-end desde la UI** (`nuxt-app/app/pages/general/staff.vue` + `components/staff/MemberModal.vue`), donde puede haber bugs de formulario que el test de API no detecta.

Regla de negocio clave (`StoreStaffService.php`):
- Solo `super-admin` puede crear un usuario con rol `general_manager`.
- `branch_manager` solo puede crear `cashier`, `coordinator`, `verifier` **dentro de su propia sucursal**.
- `general_manager`/`super-admin` pueden crear cualquier rol "de staff" en cualquier sucursal.
- CURP y RFC se validan con reglas propias y deben ser únicos.
- `username` único.

| # | Caso | Rol actor | Pasos | Esperado |
|---|------|-----------|-------|----------|
| 2.1 | Alta feliz — super-admin crea gerente general | super-admin | Login → módulo Personal → Nuevo → llenar datos + rol `general_manager` sin sucursal | Usuario creado, aparece en listado, login con ese usuario funciona |
| 2.2 | Alta feliz — gerente general crea cajera | general_manager | Nuevo usuario, rol `cashier`, elegir sucursal | Creado y visible; login funcional con permisos de cajera únicamente |
| 2.3 | Alta feliz — gerente de sucursal crea coordinador en su sucursal | branch_manager | Nuevo usuario, rol `coordinator`, sucursal = la suya (el selector no debería ni mostrar otras) | Creado correctamente |
| 2.4 | Bloqueo — gerente de sucursal intenta crear en otra sucursal | branch_manager | Vía API (Postman/inspector), forzar `branch_id` de otra sucursal | 403 "Solo puedes asignar personal a tus sucursales" (`StoreStaffService.php:60`) |
| 2.5 | Bloqueo — gerente de sucursal intenta crear gerente general | branch_manager | Intentar rol `general_manager` | 403 "Solo el super administrador puede crear un gerente general" |
| 2.6 | Bloqueo — gerente de sucursal intenta crear otro branch_manager | branch_manager | Rol `branch_manager` | 403 "Solo el gerente general puede crear personal de otro tipo" (`ListStaffService::BRANCH_MANAGER_ROLES`) |
| 2.7 | Validación — CURP inválida | general_manager | CURP con formato incorrecto | Error de validación en el campo, sin llegar a crear nada |
| 2.8 | Validación — CURP duplicada | general_manager | Reusar CURP de un usuario existente | Error "ya existe" antes de tocar BD (unique en `people.curp`) |
| 2.9 | Validación — username duplicado | general_manager | Reusar username existente | Error claro, no 500 |
| 2.10 | Validación — password corta | general_manager | Password de 4 caracteres | Rechazado (`min:8`) |
| 2.11 | Rol no administrable | general_manager | Intentar asignar un rol fuera de `ListStaffService::STAFF_ROLES` (p.ej. `distributor` o `super-admin`) desde este módulo | 422 "El rol seleccionado no es administrable desde el módulo de personal" |
| 2.12 | Edición — cambio de estatus | general_manager | Desactivar un usuario desde el listado (`staff.vue` toggle `is_active`) | Usuario ya no puede loguear; se audita `STAFF_UPDATED` |
| 2.13 | Edición — cambio de rol de un existente | branch_manager | Subir a un cashier a coordinador dentro de su sucursal | Permitido (caso ya cubierto en Pest: "lets a branch manager upgrade...") |
| 2.14 | Auditoría | super-admin | Tras crear/editar staff, ir a `AuditLogController` / pantalla de logs | Debe listar el evento `STAFF_CREATED`/`STAFF_UPDATED` con actor, antes/después y sucursal |
| 2.15 | Visibilidad — listado filtrado por sucursal | branch_manager | Ver módulo Personal | Solo ve staff de su(s) sucursal(es), no el universo completo (`ListStaffService`) |
| 2.16 | Permiso — cashier/coordinator intenta acceder al módulo | cashier | Navegar a `/general/staff` o llamar `GET /staff` | 403, no debería ni ver la opción en el menú (`staff.view` no incluye esos roles) |

---

## 3. Sucursales (`BranchController`)

⚠️ Cobertura automatizada muy delgada: solo **2 casos** en `tests/Feature/Api/V1/BranchTest.php`
(alta feliz por gerente general, y que un coordinador solo puede ver). No hay pruebas de
validación, duplicados, ni de que roles no autorizados sean bloqueados — este módulo es el que
más se beneficia de pruebas manuales ahora mismo.

Regla de negocio (`config/business-authorization.php:28`): `branches.manage` es **exclusivo de `general_manager`** (ni siquiera `super-admin` está en la lista — vale la pena confirmar si eso es intencional).

| # | Caso | Rol actor | Pasos | Esperado |
|---|------|-----------|-------|----------|
| 3.1 | Alta feliz | general_manager | Módulo Sucursales → Nueva → nombre, dirección, teléfono | Creada; si no se da `code`, se autogenera como `BR-<slug-del-nombre>` (`BranchController.php:90-101`) |
| 3.2 | Autogeneración de código con colisión | general_manager | Crear dos sucursales con el mismo nombre | La segunda debe recibir código `BR-XXX-1` sin tronar por unique constraint |
| 3.3 | Alta con código manual duplicado | general_manager | Forzar `code` ya existente | Error de validación (`unique:branches,code`), no 500 |
| 3.4 | Alta asignando gerente | general_manager | Crear sucursal + `manager_user_id` de un usuario existente | Se le asigna rol `branch_manager` en esa sucursal (verificar con `Spatie::setPermissionsTeamId`) |
| 3.5 | Bloqueo — super-admin no puede crear sucursal directamente | super-admin | Intentar `POST /branches` | Según config actual, 403 (confirmar si es el comportamiento deseado; si no, es un bug de configuración, no del código) |
| 3.6 | Bloqueo — branch_manager no puede crear sucursal | branch_manager | Intentar desde UI/API | 403, opción ni debería mostrarse en el menú |
| 3.7 | Bloqueo — coordinator solo lectura | coordinator | Ver listado de sucursales, intentar editar | Ve el listado (`branches.view` lo incluye) pero no puede editar (ya cubierto por test existente) |
| 3.8 | Edición — reemplazo de gerente | general_manager | Editar sucursal, cambiar `manager_user_id` a otro usuario | El anterior pierde el rol `branch_manager` en esa sucursal, el nuevo lo obtiene (`BranchController.php:122-135`) |
| 3.9 | Edición — quitar gerente | general_manager | Editar sucursal, mandar `manager_user_id: null` | Gerente actual pierde el rol, sucursal queda sin gerente |
| 3.10 | Validación — nombre vacío | general_manager | Guardar sin nombre | Error de validación, campo requerido |
| 3.11 | Aislamiento — listado por sucursal propia | branch_manager | Ver módulo Sucursales | Solo ve su(s) sucursal(es) (`BranchController::index`, filtra si no tiene `hasGlobalBusinessRole`) |
| 3.12 | Configuración de corte | general_manager / branch_manager | Ir a `branch-settings` de una sucursal y guardar fecha de corte, comisiones, etc. | Cambios persisten y afectan al próximo cálculo de relación (validar contra `08-financial-engine`) |
| 3.13 | Auditoría | super-admin | Revisar logs tras crear/editar sucursal | Debe listar `BRANCH_CREATED`/`BRANCH_UPDATED` con before/after |

---

## 4. Alta de distribuidoras (Applications) — flujo Coordinador → Verificador → Gerencia

Cobertura automatizada: `ApplicationTest.php`, `ApplicationDocumentTest.php`. Frontend: `nuxt-app` aún tiene tareas pendientes marcadas en `ROLES_Y_TAREAS.md` (formulario de registro `new.vue`, pantalla de verificación en campo) — **confirmar primero si ya se implementaron**, si no, este flujo solo se puede probar por API/Postman todavía.

| # | Caso | Rol | Pasos | Esperado |
|---|------|-----|-------|----------|
| 4.1 | Alta feliz completa | coordinator → verifier → general_manager | Coordinador crea solicitud → asigna verificador → verificador sube evidencia/checklist → gerencia aprueba | Al aprobar, se genera Distribuidora + usuario + rol + token de activación automáticamente |
| 4.2 | Rechazo en verificación | verifier | Marcar visita como rechazada | Estado pasa a `RECHAZADA`, no llega a bandeja de gerencia |
| 4.3 | Rechazo en decisión final | general_manager | Rechazar una solicitud en `POSIBLE_DISTRIBUIDORA` | Estado `RECHAZADA`, no se crea distribuidora ni usuario |
| 4.4 | Documentos requeridos incompletos | coordinator/verifier | Intentar avanzar sin fotos/documentos obligatorios | Bloqueo de validación, mensaje explícito |
| 4.5 | CURP duplicada bloqueando alta | coordinator | Registrar solicitante con CURP de un cliente ya existente en otra distribuidora | Debe bloquear o marcar advertencia según la regla de negocio vigente (ver pregunta abierta en `CLAUDE.md`: "¿un cliente puede registrarse con otra distribuidora si la CURP ya existe?") |
| 4.6 | Asignación de límite inicial | general_manager | Al aprobar, asignar línea de crédito y categoría | Línea queda disponible desde el primer vale, respetando la regla del 50% en el primer vale |

---

## 5. Vales (Vouchers)

Cobertura automatizada: `VoucherTest.php`, `VoucherExpirationTest.php`, `CashierFlowTest.php`.

| # | Caso | Rol | Pasos | Esperado |
|---|------|-----|-------|----------|
| 5.1 | Pre-vale dentro del 50% | distributor | Emitir primer vale a un cliente nuevo, monto ≤ 50% del crédito | Se pre-emite sin bloqueo |
| 5.2 | Pre-vale excede 50% | distributor | Emitir primer vale por más del 50% | Bloqueado con mensaje de regla de negocio, no error genérico |
| 5.3 | Vale requiere aprobación de coordinador | distributor → coordinator | Emitir vale que dispare `VoucherRequest` | Coordinador lo ve en bandeja y puede aprobar/rechazar |
| 5.4 | Desembolso en caja | cashier | Aprobar y desembolsar vale aprobado | Estado pasa a `ACTIVO`/`ENTREGADO`, efectivo entregado queda auditado |
| 5.5 | Expiración de pre-vale | — (job/scheduler) | Dejar un pre-vale sin presentarse pasado el plazo | Expira automáticamente (pregunta abierta en `CLAUDE.md` sobre expiración — confirmar regla implementada) |
| 5.6 | Rechazo en caja | cashier | Rechazar un vale en sucursal | Estado se revierte correctamente, no deja crédito "atorado" |

---

## 6. Clientes y traspasos

Cobertura automatizada: `CustomerTest.php`.

| # | Caso | Rol | Pasos | Esperado |
|---|------|-----|-------|----------|
| 6.1 | Alta de cliente | distributor | Registrar cliente con CURP válida | Creado, visible en tabla de clientes |
| 6.2 | CURP duplicada entre distribuidoras | distributor | Intentar registrar CURP ya usada por otra distribuidora | Bloqueo si la regla de negocio lo exige |
| 6.3 | Solicitud de traspaso | distributor | Solicitar traer cliente de otra distribuidora | Aparece en bandeja del coordinador/gerente para decidir |
| 6.4 | Decisión de traspaso | coordinator/general_manager | Aprobar/rechazar traspaso | Cliente cambia de distribuidora solo si se aprueba; saldo/crédito se recalcula según reglas |
| 6.5 | Cancelación de traspaso | distributor | Cancelar solicitud propia antes de decisión | Vuelve a estado neutro, no queda huérfana |

---

## 7. Incrementos de crédito

Cobertura automatizada: `CreditIncreaseTest.php`.

| # | Caso | Rol | Esperado |
|---|------|-----|----------|
| 7.1 | Solicitud → pre-autorización → decisión final | distributor/coordinator → coordinator → branch_manager/general_manager | Flujo completo de 3 pasos respetado, cada paso auditado |
| 7.2 | Reducción del monto sugerido | coordinator/general_manager | Al pre-autorizar/decidir, monto final puede ser menor al solicitado, no mayor |
| 7.3 | Rechazo en cualquier paso | cualquiera con `credit-increase.decide` | Corta el flujo, no aplica el incremento |

---

## 8. Conciliaciones bancarias

Cobertura automatizada: `ReconciliationTest.php`.

| # | Caso | Rol | Esperado |
|---|------|-----|----------|
| 8.1 | Importar archivo bancario | cashier/general_manager | Transacciones se cargan y hacen match automático por referencia/monto/fecha |
| 8.2 | Match manual | general_manager | Asociar manualmente un depósito con referencia mal capturada a la relación correcta | Requiere evidencia/autorización, queda auditado |
| 8.3 | Verificación/cierre | branch_manager/general_manager | Firmar conciliación completa | Estado final, ya no editable sin nueva autorización |
| 8.4 | Duplicado | cashier | Importar el mismo archivo dos veces | No debe duplicar transacciones ni pagos aplicados |

---

## 9. Puntos

Cobertura automatizada: `PointTest.php`.

| # | Caso | Rol | Esperado |
|---|------|-----|----------|
| 9.1 | Acumulación de puntos | — (automático al pagar) | Puntos se calculan según regla configurable, no hardcodeada |
| 9.2 | Solicitud de canje | distributor | Solicita canjear puntos por dinero/abono | Aparece en bandeja de decisión |
| 9.3 | Decisión de canje | branch_manager/general_manager | Aprobar/rechazar | Si aprueba, puntos se descuentan y valor se aplica; si rechaza, puntos quedan intactos |

---

## 10. Auditoría (`AuditLogController` + `AuditLogger`)

Nota: `AuditLogger.php` y `AuditLogController.php` están modificados sin commitear en este momento
(agregan un parámetro de severidad, ver diff de `AuthController.php`). Vale la pena revisar ese
cambio junto con quien lo hizo antes de dar el módulo por cerrado.

| # | Caso | Rol | Esperado |
|---|------|-----|----------|
| 10.1 | Visibilidad exclusiva | super-admin | Solo super-admin ve `/audit-logs` (`audit-logs.view`) | Otros roles reciben 403 |
| 10.2 | Filtro por severidad | super-admin | Filtrar logs por `WARNING` (nuevo) vs normales | Filtro funciona y refleja el nuevo campo |
| 10.3 | Integridad | super-admin | Verificar que un evento sensible (login fallido, cambio de crédito, conciliación manual) siempre tiene actor, antes/después, fecha y sucursal | Sin campos vacíos en eventos financieros |

---

## Prioridad sugerida de ejecución

1. **Sucursales (sección 3)** — es el módulo con menos cobertura automatizada; alto riesgo de bugs no detectados.
2. **Staff/Usuarios (sección 2)** — bien cubierto por Pest, pero hay que validar la UI real (`MemberModal.vue`) porque el test de API no prueba el formulario.
3. **Auth + MFA (sección 1)** — crítico por ser el único rol con OTP; un bug aquí bloquea al super-admin.
4. Resto de módulos (4–9) en el orden del ciclo de negocio: alta de distribuidora → vales → clientes → crédito → conciliación → puntos.
5. Auditoría (10) al final, como verificación cruzada de que todo lo anterior dejó rastro.

## Cómo reportar un hallazgo

Para cada bug encontrado, registrar: módulo, rol usado, pasos exactos, resultado obtenido vs
esperado, y si aplica, el archivo/línea de backend o componente de frontend donde probablemente
está la causa (para acelerar el fix).
