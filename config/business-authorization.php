<?php

declare(strict_types=1);

return [
    'abilities' => [
        'platform.view' => ['administrator', 'general_manager'],
        'branches.view' => ['administrator', 'general_manager', 'branch_manager', 'coordinator', 'verifier', 'cashier'],
        'branches.manage' => ['general_manager'],
        'products.view' => ['administrator', 'general_manager'],
        'products.manage' => ['general_manager'],
        'distributors.manage' => ['general_manager'],
        'credit-accounts.open' => ['general_manager', 'branch_manager'],
        'credit-limits.increase' => ['general_manager'],
        'voucher-plans.manage' => ['general_manager'],
        'vouchers.pre-issue' => ['general_manager', 'branch_manager', 'coordinator', 'verifier'],
        'vouchers.issue' => ['general_manager', 'branch_manager', 'coordinator', 'verifier'],
        'customers.update.request' => ['cashier', 'general_manager'],
        'customers.transfer.request' => ['distributor', 'coordinator', 'general_manager'],
        'customers.transfer.approve-source' => ['coordinator', 'general_manager'],
        'customers.transfer.accept-destination' => ['distributor', 'general_manager'],
        'reconciliations.manual' => ['cashier', 'general_manager'],
    ],
];
