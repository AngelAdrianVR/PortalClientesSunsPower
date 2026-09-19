<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Mantenimiento</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body { margin: 0; padding: 32px 16px; background: #eef2ff; color: #1f2937; }
        .card { max-width: 780px; margin: 0 auto; background: #fff; border-radius: 14px;
                box-shadow: 0 10px 30px -18px rgba(30, 58, 138, .5); padding: 26px 28px; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #1e3a8a; }
        h2 { margin: 26px 0 8px; font-size: 14px; text-transform: uppercase; letter-spacing: .4px;
             color: #1e3a8a; border-left: 4px solid #facc15; padding-left: 8px; }
        .sub { margin: 0 0 18px; font-size: 13px; color: #64748b; }
        .result { border: 1px solid #dbe3f5; border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; }
        .result.is-ok { border-color: #bbf7d0; background: #f0fdf4; }
        .result.is-fail { border-color: #fecaca; background: #fef2f2; }
        .cmd { font-size: 13px; font-weight: 700; margin-bottom: 8px; }
        .is-ok .cmd { color: #15803d; }
        .is-fail .cmd { color: #b91c1c; }
        code { padding: 2px 6px; font-size: 12px; background: #eef2ff; border-radius: 5px; color: #1e3a8a; }
        pre { margin: 0; padding: 10px; font-size: 12px; line-height: 1.5; white-space: pre-wrap;
              word-break: break-word; background: #0f172a; color: #e2e8f0; border-radius: 8px; }
        ul { margin: 0; padding-left: 18px; font-size: 13px; }
        li { margin-bottom: 6px; }
        .links { margin: 24px 0 0; padding-top: 14px; border-top: 1px solid #e5e7eb; font-size: 13px; }
        a { color: #1e3a8a; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $title }}</h1>
        <p class="sub">Ejecutado el {{ $ranAt }}. Esta página no está enlazada en el portal.</p>

        @foreach ($results as $result)
            <div class="result {{ $result['ok'] ? 'is-ok' : 'is-fail' }}">
                <div class="cmd">
                    {{ $result['ok'] ? '✔ Listo' : '✖ Error' }}
                    <code>{{ $result['command'] }}</code>
                </div>
                <pre>{{ $result['output'] }}</pre>
            </div>
        @endforeach

        @if (! empty($checks))
            <h2>Comprobaciones</h2>
            <ul>
                @foreach ($checks as $label => $passed)
                    <li>{{ $passed ? '✅' : '❌' }} {{ $label }}</li>
                @endforeach
            </ul>
        @endif

        <p class="links">
            <a href="/tutoriales">Ir a Tutoriales</a> ·
            <a href="/gestion-tutoriales">Panel de gestión</a> ·
            <a href="/dashboard">Dashboard</a>
        </p>
    </div>
</body>
</html>
