<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta {{ $relation->relation_number }}</title>
    <style>
        @page { margin: 26px 32px; }
        body { font-family: Arial, Helvetica, sans-serif; color: #0f172a; font-size: 11px; }

        .top { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .top td { vertical-align: top; }
        .logo-cell { width: 90px; }
        .logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: #002366;
            color: #ffffff;
            text-align: center;
            line-height: 72px;
            font-size: 11px;
            font-weight: bold;
        }
        .brand-name { margin-top: 6px; font-size: 11px; font-weight: bold; color: #002366; text-align: center; width: 72px; }

        .info-cell { text-align: right; }
        .info-cell p { margin: 2px 0; font-size: 11px; }
        .info-cell .info-title { font-size: 15px; font-weight: bold; color: #002366; margin: 0 0 6px 0; }

        .dates-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
        }
        .dates-box p { margin: 3px 0; font-size: 12px; }
        .dates-box .total-pay { font-size: 14px; font-weight: bold; color: #002366; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th {
            background-color: #002366;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
            padding: 6px 6px;
            text-align: left;
        }
        table.items td { padding: 6px; font-size: 10px; border-bottom: 1px solid #e2e8f0; }
        table.items tr:nth-child(even) td { background-color: #f8fafc; }
        table.items td.num, table.items th.num { text-align: right; }
        table.items tfoot td {
            font-weight: bold;
            background-color: #eff6ff;
            border-top: 2px solid #002366;
        }

        .tag { font-size: 8px; font-weight: bold; text-transform: uppercase; padding: 2px 5px; border-radius: 5px; }
        .tag-carryover { background-color: #fef3c7; color: #b45309; }
        .tag-late { background-color: #fee2e2; color: #991b1b; }

        .bank-section { margin-top: 20px; }
        .bank-section h3 { font-size: 12px; color: #1e293b; margin: 0 0 8px 0; }
        table.banks { width: 100%; border-collapse: collapse; }
        table.banks td {
            width: 50%;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 10px;
            vertical-align: top;
        }
        table.banks .bank-name { font-size: 12px; font-weight: bold; color: #002366; margin: 0 0 6px 0; }

        .footer { margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: center; }
        .empty { padding: 16px; text-align: center; color: #64748b; font-size: 10px; }
    </style>
</head>
<body>
    <table class="top">
        <tr>
            <td class="logo-cell">
                <div class="logo">{{ $brandInitials }}</div>
                <div class="brand-name">{{ $brandName }}</div>
            </td>
            <td class="info-cell">
                <p class="info-title">Estado de cuenta · {{ $relation->relation_number }}</p>
                <p><strong>Número Distribuidora:</strong> {{ $distributorNumber ?? '—' }}</p>
                <p><strong>Nombre:</strong> {{ $distributorName }}</p>
                <p><strong>Domicilio:</strong> {{ $distributorAddress }}</p>
                <p><strong>Límite de crédito:</strong> ${{ number_format((float) $relation->credit_limit_snapshot, 2) }}</p>
                <p><strong>Crédito disponible:</strong> ${{ number_format((float) $relation->available_credit_snapshot, 2) }}</p>
                <p><strong>Puntos:</strong> {{ number_format((float) $relation->points_snapshot, 0) }}</p>
                <p><strong>Referencia de Pago:</strong> {{ $relation->payment_reference ?? '—' }}</p>
            </td>
        </tr>
    </table>

    <div class="dates-box">
        <p><strong>Fecha límite de pago:</strong> {{ $dueDateLabel }}</p>
        <p><strong>Pago anticipado:</strong> {{ $periodLabel }}</p>
        <p class="total-pay">Total a PAGAR: ${{ number_format((float) $relation->total_amount_due, 2) }}</p>
    </div>

    @if($relation->items->isEmpty())
        <div class="empty">Esta relación no incluye vales (solo saldo arrastrado).</div>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Cliente</th>
                    <th>Pagos Realizados</th>
                    <th class="num">Comisión</th>
                    <th class="num">Pago</th>
                    <th class="num">Recargos</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($relation->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            {{ $item->product_name_snapshot ?? 'Vale #'.$item->voucher_id }}
                            @if($item->origin_relation_id !== null)
                                <span class="tag tag-carryover">Arrastre</span>
                            @endif
                            @if($item->is_late_payment)
                                <span class="tag tag-late">Atrasado</span>
                            @endif
                        </td>
                        <td>{{ $item->customer?->person ? trim(($item->customer->person->first_name ?? '').' '.($item->customer->person->last_name ?? '')) : 'Cliente #'.$item->customer_id }}</td>
                        <td>{{ $item->payments_made }}/{{ $item->total_payments }}</td>
                        <td class="num">${{ number_format((float) $item->commission_amount, 2) }}</td>
                        <td class="num">${{ number_format((float) $item->payment_amount, 2) }}</td>
                        <td class="num">${{ number_format((float) $item->late_fee_amount, 2) }}</td>
                        <td class="num">${{ number_format((float) $item->line_total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">Totales</td>
                    <td class="num">${{ number_format((float) $relation->total_commission, 2) }}</td>
                    <td class="num">${{ number_format((float) $relation->total_payment, 2) }}</td>
                    <td class="num">${{ number_format((float) $relation->total_late_fees, 2) }}</td>
                    <td class="num">${{ number_format((float) $relation->total_amount_due, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    @if($bankAccounts->isNotEmpty())
        <div class="bank-section">
            <h3>Cuentas para remitir el pago</h3>
            <table class="banks">
                <tr>
                    @foreach($bankAccounts as $account)
                        <td>
                            <p class="bank-name">{{ $account->bank }}</p>
                            <p>Convenio: {{ $account->agreement ?? '—' }}</p>
                            <p>Clabe: {{ $account->clabe ?? '—' }}</p>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    <p class="footer">Documento generado automáticamente por {{ $brandName }}. Válido como comprobante del estado de cuenta al {{ $generatedAtLabel }}.</p>
</body>
</html>
