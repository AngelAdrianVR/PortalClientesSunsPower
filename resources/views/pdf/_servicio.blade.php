@php
    $statusText = [
        'paid' => 'Pagada',
        'on_time' => 'Pagada a tiempo',
        'upcoming' => 'Por vencer',
        'pending' => 'Pendiente',
        'late' => 'Vencida',
        'defaulted' => 'Vencida (+10 días)',
    ];

    $statusClass = static fn ($s) => match ($s) {
        'paid', 'on_time' => 'status-paid',
        'late' => 'status-late',
        'defaulted' => 'status-defaulted',
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
                <th class="right" style="width:13%">Monto</th>
                <th style="width:16%">Estatus</th>
                <th class="right" style="width:13%">Interés moratorio</th>
                <th class="right" style="width:14%">Total a pagar</th>
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
                    <td class="right">{{ $money($i['total_with_interest']) }}</td>
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
                <th style="width:18%">Fecha</th>
                <th class="right" style="width:18%">Monto</th>
                <th class="right" style="width:16%">Interés</th>
                <th style="width:20%">Método</th>
                <th>Referencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payload['payments'] as $p)
                <tr>
                    <td>{{ $date($p['payment_date']) }}</td>
                    <td class="right">{{ $money($p['amount']) }}</td>
                    <td class="right">{{ $money($p['interest_amount']) }}</td>
                    <td>{{ $p['method'] ?: '—' }}</td>
                    <td>{{ $p['reference'] ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Aún no hay pagos registrados.</td>
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
    @endif
    <tr class="grand">
        <td class="label">Saldo pendiente:</td>
        <td class="right">{{ $money($payload['balance']) }}</td>
    </tr>
</table>
