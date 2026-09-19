@php
    $statusText = [
        'paid' => 'Pagado',
        'pending' => 'Pendiente',
        'due_soon' => 'Por vencer',
        'overdue' => 'Vencido',
    ];

    $statusClass = static fn ($s) => match ($s) {
        'paid' => 'status-paid',
        'due_soon' => 'status-due',
        'overdue' => 'status-overdue',
        default => 'status-other',
    };

    $money = static fn ($n) => '$ '.number_format((float) $n, 2);
    $date = static fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
@endphp

<div class="box">
    <h3>Servicio</h3>
    <table>
        <tr>
            <th style="width:14%">Tipo de sistema</th>
            <th style="width:12%">Estatus</th>
            <th style="width:14%">Fecha de inicio</th>
            <th>Dirección de instalación</th>
            <th style="width:14%">Plan de pago</th>
        </tr>
        <tr>
            <td>{{ $payload['system_type'] ?: '—' }}</td>
            <td>{{ $payload['status'] }}</td>
            <td>{{ $date($payload['start_date']) }}</td>
            <td>{{ $payload['installation_address'] }}</td>
            <td>{{ $payload['payment_method'] ?: '—' }}</td>
        </tr>
    </table>
</div>

<div class="box">
    <h3>Cuotas del plan de pago</h3>
    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th>Concepto</th>
                <th style="width:13%">Vencimiento</th>
                <th class="right" style="width:12%">Monto</th>
                <th style="width:14%">Estatus</th>
                <th class="right" style="width:12%">Interés</th>
                <th class="right" style="width:14%">Interés pagado</th>
                <th class="right" style="width:14%">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payload['installments'] as $i)
                <tr>
                    <td>{{ $i['installment_number'] }}</td>
                    <td>{{ $i['label'] }}</td>
                    <td>{{ $date($i['projected_date']) }}</td>
                    <td class="right">{{ $money($i['amount']) }}</td>
                    <td class="{{ $statusClass($i['status']) }}">{{ $statusText[$i['status']] ?? $i['status'] }}</td>
                    <td class="right">{{ $money($i['interest']) }}</td>
                    <td class="right">{{ $money($i['paid_interest']) }}</td>
                    <td class="right">{{ $money($i['status'] === 'paid' ? $i['paid_total'] : $i['total_with_interest']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Este servicio no tiene cuotas registradas (plan personalizado).</td>
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
                <th style="width:13%">Fecha</th>
                <th class="right" style="width:18%">Capital pagado</th>
                <th class="right" style="width:16%">Interés pagado</th>
                <th class="right" style="width:16%">Total pagado</th>
                <th style="width:16%">Método</th>
                <th>Referencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payload['payments'] as $p)
                <tr>
                    <td>{{ $date($p['payment_date']) }}</td>
                    <td class="right">{{ $money($p['principal']) }}</td>
                    <td class="right">{{ $money($p['interest_amount']) }}</td>
                    <td class="right">{{ $money($p['amount']) }}</td>
                    <td>{{ $p['method'] ?: '—' }}</td>
                    <td>{{ $p['reference'] ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Aún no hay pagos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<table class="totals">
    <tr>
        <td class="label">Costo total del servicio:</td>
        <td class="right">{{ $money($payload['total_amount']) }}</td>
    </tr>
    <tr>
        <td class="label">Pagado a capital:</td>
        <td class="right">{{ $money($payload['paid']) }}</td>
    </tr>
    @if ((float) $payload['overdue_interest'] > 0)
        <tr class="interest-line">
            <td class="label">Interés moratorio acumulado:</td>
            <td class="right">{{ $money($payload['overdue_interest']) }}</td>
        </tr>
        <tr>
            <td class="label">Saldo de capital pendiente:</td>
            <td class="right">{{ $money($payload['balance']) }}</td>
        </tr>
    @endif
    <tr class="grand">
        <td class="label">
            {{ (float) $payload['overdue_interest'] > 0
                ? 'Total pendiente a pagar (capital + interés):'
                : 'Saldo pendiente:' }}
        </td>
        <td class="right">
            {{ $money((float) $payload['balance'] + (float) $payload['overdue_interest']) }}
        </td>
    </tr>
</table>
