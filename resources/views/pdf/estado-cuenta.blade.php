<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        body { margin: 0; padding: 24px; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #0f766e; }
        .subtitle { font-size: 11px; color: #6b7280; margin-bottom: 16px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .company { font-weight: bold; font-size: 13px; color: #0f766e; }
        .box { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; }
        .box h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .right { text-align: right; }
        .totals { width: 50%; margin-left: auto; }
        .totals td { border: none; padding: 3px 8px; }
        .totals .label { color: #4b5563; }
        .totals .grand { font-weight: bold; border-top: 1px solid #d1d5db; }
        .footer { margin-top: 18px; font-size: 9px; color: #9ca3af; text-align: center; }
        .badge-paid { color: #15803d; }
        .badge-late { color: #b45309; }
        .badge-defaulted { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Estado de Cuenta</h1>
            <div class="company">Sun's Power MX</div>
        </div>
        <div style="text-align: right;">
            <div>Generado: {{ $generatedAt->format('d/m/Y H:i') }}</div>
            <div>{{ $serviceOrder->service_number ? 'Servicio: '.$serviceOrder->service_number : 'Orden #'.$serviceOrder->id }}</div>
        </div>
    </div>

    <div class="box">
        <h3>Cliente</h3>
        <table>
            <tr>
                <th>Nombre</th>
                <th>RFC</th>
                <th>Dirección</th>
            </tr>
            <tr>
                <td>{{ $client->name }}</td>
                <td>{{ $client->tax_id ?: '—' }}</td>
                <td>{{ $client->full_address }}</td>
            </tr>
        </table>
    </div>

    <div class="box">
        <h3>Servicio</h3>
        <table>
            <tr>
                <th>Tipo de sistema</th>
                <th>Estatus</th>
                <th>Fecha de inicio</th>
                <th>Dirección de instalación</th>
                <th>Plan de pago</th>
            </tr>
            <tr>
                <td>{{ $serviceOrder->system_type ?: '—' }}</td>
                <td>{{ $serviceOrder->status }}</td>
                <td>{{ $serviceOrder->start_date ? $serviceOrder->start_date->format('d/m/Y') : '—' }}</td>
                <td>{{ $serviceOrder->installation_address }}</td>
                <td>{{ $serviceOrder->payment_method ?: '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="box">
        <h3>Cuotas del plan de pago</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Concepto</th>
                    <th>Vencimiento</th>
                    <th class="right">Monto</th>
                    <th>Estatus</th>
                    <th class="right">Interés moratorio</th>
                    <th class="right">Total a pagar</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($installments as $i)
                    <tr>
                        <td>{{ $i->installment_number }}</td>
                        <td>{{ $i->label }}</td>
                        <td>{{ $i->projected_date ? $i->projected_date->format('d/m/Y') : '—' }}</td>
                        <td class="right">$ {{ number_format($i->amount, 2) }}</td>
                        <td class="{{ $i->current_status === 'defaulted' ? 'badge-defaulted' : ($i->current_status === 'late' ? 'badge-late' : ($i->isPaid() ? 'badge-paid' : '')) }}">
                            {{ match ($i->current_status) {
                                'paid' => 'Pagada', 'on_time' => 'Pagada a tiempo', 'upcoming' => 'Por vencer',
                                'pending' => 'Pendiente', 'late' => 'Vencida', 'defaulted' => 'Vencida (+10 días)',
                                default => $i->current_status,
                            } }}
                        </td>
                        <td class="right">$ {{ number_format($i->calculateInterest(), 2) }}</td>
                        <td class="right">$ {{ number_format($i->total_with_interest, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Este servicio no tiene cuotas registradas (plan personalizado).</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="box">
        <h3>Pagos realizados</h3>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th class="right">Monto</th>
                    <th>Método</th>
                    <th>Referencia</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td>{{ $p->payment_date ? $p->payment_date->format('d/m/Y') : '—' }}</td>
                        <td class="right">$ {{ number_format($p->amount, 2) }}</td>
                        <td>{{ $p->method }}</td>
                        <td>{{ $p->reference ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Aún no hay pagos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table class="totals">
        <tr>
            <td class="label">Costo total del servicio:</td>
            <td class="right">$ {{ number_format($serviceOrder->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Pagado a capital:</td>
            <td class="right">$ {{ number_format($serviceOrder->total_amount - $balance, 2) }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Saldo pendiente:</td>
            <td class="right">$ {{ number_format($balance, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        Interés moratorio: 10% mensual sobre saldos vencidos con 5 días de gracia. Documento informativo generado desde el portal de clientes.
    </div>
</body>
</html>
