<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de Cuenta general · Sun's Power MX</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        body { margin: 0; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #eef2ff; color: #1e3a8a; font-size: 9px; text-transform: uppercase; }
        .right { text-align: right; }
        .head { width: 100%; border-bottom: 3px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
        .head td { border: none; padding: 0; }
        .logo { height: 56px; }
        .brand-title { font-size: 17px; font-weight: bold; color: #1e3a8a; }
        .brand-sub { font-size: 9px; color: #6b7280; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0 0 3px; font-size: 16px; color: #1e3a8a; }
        .doc-title div { font-size: 9px; color: #6b7280; }
        .box { border: 1px solid #dbe3f5; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; }
        .box h3 { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; letter-spacing: .4px;
                  color: #1e3a8a; border-left: 4px solid #facc15; padding-left: 6px; }
        .service-divider { margin: 14px 0 10px; font-size: 12px; font-weight: bold; color: #1e3a8a;
                           border-bottom: 2px solid #facc15; padding-bottom: 4px; }
        .status-paid { color: #15803d; }
        .status-due { color: #ea580c; }
        .status-overdue { color: #b91c1c; }
        .status-other { color: #1e3a8a; }
        .footer { margin-top: 12px; padding-top: 6px; border-top: 1px solid #e5e7eb;
                  font-size: 8px; color: #9ca3af; text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                @if (! empty($logo) && file_exists($logo))
                    <img class="logo" src="{{ $logo }}" alt="Logo" />
                @endif
                <div class="brand-title">Sun's Power MX</div>
                <div class="brand-sub">Energía solar · Portal de Clientes</div>
            </td>
            <td class="doc-title">
                <h1>Estado de Cuenta</h1>
                <div>Generado: {{ $generatedAt->format('d/m/Y H:i') }}</div>
                <div>Todos los servicios</div>
            </td>
        </tr>
    </table>

    <div class="box">
        <h3>Cliente</h3>
        <table>
            <tr>
                <th style="width:25%">Nombre</th>
                <th style="width:20%">RFC</th>
                <th>Dirección</th>
            </tr>
            <tr>
                <td>{{ $client->name }}</td>
                <td>{{ $client->tax_id ?: '—' }}</td>
                <td>{{ $client->full_address }}</td>
            </tr>
        </table>
    </div>

    @foreach ($services as $service)
        <div @if (! $loop->first) class="page-break" @endif>
            <div class="service-divider">Servicio: {{ $service['service_number'] }}</div>
            @include('pdf._servicio', ['payload' => $service])
        </div>
    @endforeach

    <div class="footer">
        Interés moratorio: 10% mensual sobre saldos vencidos con 5 días de gracia. Documento informativo generado desde el portal de clientes.
    </div>
</body>
</html>
