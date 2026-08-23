<?php

declare(strict_types=1);

return [
    'global_role_codes' => ['super-admin', 'general_manager'],

    // Roles que deben verificar un codigo OTP por correo (segundo factor)
    // ademas de su contrasena para poder iniciar sesion. Ver
    // AuthController::login()/verifyMfa() y User::requiresOtp().
    'otp_required_role_codes' => ['super-admin', 'general_manager', 'branch_manager'],

    'abilities' => [
        'audit-logs.view' => ['super-admin'],
        'platform.view' => ['general_manager'],
        'storage.spaces.test' => ['super-admin', 'general_manager'],
        // El gerente de sucursal ya decide sobre solicitudes/incrementos/canjes
        // de SU sucursal (applications.decide, credit-increase.decide,
        // points.redeem.decide), así que también necesita ver la bandeja donde
        // aparecen esas solicitudes pendientes — si no, tiene el permiso para
        // decidir pero no la forma de encontrarlas. InboxController ya filtra
        // por sucursal para roles no globales (activeBusinessBranchIds()).
        'inbox.view' => ['general_manager', 'branch_manager'],
        'users.manage' => ['super-admin'],
        'staff.view' => ['super-admin', 'general_manager', 'branch_manager'],
        'staff.manage' => ['super-admin', 'general_manager', 'branch_manager'],
        'branches.view' => ['super-admin', 'general_manager', 'branch_manager', 'coordinator', 'verifier', 'cashier'],
        'branches.manage' => ['general_manager'],
        'branch-settings.view' => ['general_manager', 'branch_manager'],
        'branch-settings.manage' => ['general_manager', 'branch_manager'],
        'products.view' => ['super-admin', 'general_manager', 'branch_manager', 'distributor'],
        // Solo el gerente general crea/edita productos-vale (tanto los de
        // catálogo global como los de una sucursal específica); el gerente de
        // sucursal únicamente los consulta (products.view). Antes
        // branch_manager también tenía esta ability y podía crear/editar los
        // vales de su propia sucursal.
        'products.manage' => ['general_manager'],
        // Catálogo global (sin sucursal): solo el gerente general lo administra.
        // No reutilizar 'products.manage' aquí: esa ability también protege la ruta
        // por sucursal (/branches/{branch}/products) y, al no llevar el parámetro
        // de sucursal, un branch_manager con CUALQUIER sucursal asignada la superaba
        // y podía crear productos globales visibles para todas las sucursales.
        'products.manage.global' => ['general_manager'],
        'categories.view' => ['general_manager', 'branch_manager', 'coordinator'],
        'categories.manage' => ['general_manager', 'branch_manager'],
        // Mismo caso que products.manage.global: catálogo de categorías global,
        // exclusivo del gerente general.
        'categories.manage.global' => ['general_manager'],
        'point-settings.view' => ['general_manager', 'branch_manager'],
        'point-settings.manage' => ['general_manager'],
        'applications.view' => ['general_manager', 'branch_manager', 'coordinator', 'verifier'],
        'applications.create' => ['coordinator'],
        'applications.assign-verifier' => ['coordinator'],
        'applications.verify' => ['verifier'],
        // Corrección de datos mal capturados por el coordinador. Solo el
        // verificador ASIGNADO a la solicitud, y solo mientras siga
        // EN_REVISION (antes de registrar su verificación) — ver
        // UpdateApplicationService. El coordinador no edita desde aquí
        // (CLAUDE.md deja esa corrección como responsabilidad del
        // verificador cuando detecta el error durante la visita).
        'applications.update' => ['verifier'],
        'applications.decide' => ['general_manager', 'branch_manager'],
        'distributors.manage' => ['general_manager'],
        // Para elegir una distribuidora al pedir un aumento de linea de credito
        // (ver credit-increase.request); no expone datos fuera de la sucursal.
        'distributors.view' => ['administrator', 'general_manager', 'branch_manager', 'coordinator'],
        'credit-accounts.open' => ['general_manager', 'branch_manager'],
        'credit-limits.increase' => ['general_manager'],
        'voucher-plans.manage' => ['general_manager'],
        'vouchers.view' => ['super-admin', 'general_manager', 'branch_manager', 'coordinator', 'cashier', 'distributor'],
        'vouchers.pre-issue' => ['distributor'],
        'vouchers.approve' => ['cashier', 'branch_manager', 'general_manager'],
        'vouchers.reject' => ['cashier', 'branch_manager', 'general_manager'],
        'vouchers.disburse' => ['cashier', 'branch_manager', 'general_manager'],
        'customers.view' => ['general_manager', 'branch_manager', 'coordinator', 'cashier', 'distributor'],
        'customers.create' => ['distributor'],
        'customers.manage' => ['general_manager', 'branch_manager'],
        'customers.update.request' => ['cashier'],
        'customers.update.approve' => ['branch_manager', 'general_manager'],
        'customers.verify' => ['cashier'],
        'customers.transfer.view' => ['general_manager', 'branch_manager', 'coordinator', 'distributor'],
        'customers.transfer.request' => ['distributor'],
        'customers.transfer.decide' => ['coordinator', 'general_manager'],
        'customers.transfer.cancel' => ['distributor'],
        'reconciliations.manual' => ['cashier', 'general_manager'],
        'credit-increase.view' => ['general_manager', 'branch_manager', 'coordinator', 'distributor'],
        'credit-increase.request' => ['distributor', 'coordinator'],
        'credit-increase.pre-authorize' => ['coordinator', 'branch_manager', 'general_manager'],
        'credit-increase.decide' => ['branch_manager', 'general_manager'],
        'payments.view' => ['general_manager', 'branch_manager', 'coordinator', 'distributor'],
        'payments.create' => ['cashier', 'branch_manager', 'general_manager'],
        'payments.reverse' => ['cashier', 'branch_manager', 'general_manager'],
        // La cajera necesita 'cutoffs.view' para el selector de corte/relación
        // del modal "Conciliar depósito" (ManualMatchModal): sin ella,
        // GET /cutoffs y GET /cutoffs/{cutoff} le regresaban Forbidden y no
        // podía elegir a qué relación corresponde una transacción bancaria.
        // El scoping por sucursal ya lo hace el middleware business.ability
        // (,cutoff / activeBusinessBranchIds()), así que solo ve los cortes
        // de su propia sucursal, igual que coordinator/distributor.
        'cutoffs.view' => ['general_manager', 'branch_manager', 'coordinator', 'distributor', 'cashier'],
        'cutoffs.manage' => ['branch_manager', 'general_manager'],
        'distributor-statements.view' => ['distributor'],
        'reconciliations.import' => ['cashier', 'general_manager'],
        'reconciliations.view' => ['general_manager', 'branch_manager', 'coordinator', 'cashier'],
        'reconciliations.verify' => ['branch_manager', 'general_manager'],
        'points.view' => ['general_manager', 'branch_manager', 'coordinator', 'distributor'],
        'points.redeem.request' => ['distributor'],
        'points.redeem.decide' => ['branch_manager', 'general_manager'],
        'points.category' => ['branch_manager', 'general_manager'],
    ],
];
