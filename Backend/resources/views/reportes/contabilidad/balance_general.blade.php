<!DOCTYPE html>
<html>
<head>
    <title>Balance General</title>
    <style>
        * {
            font-size: 10px;
            margin: 0;
            padding: 0;
        }

        html, body {
            font-family: Arial, Helvetica, sans-serif;
        }

        #balance {
            margin: 0.8cm;
        }

        .header {
            text-align: center;
            margin-bottom: 18px;
        }

        .logo {
            max-height: 64px;
            max-width: 180px;
            margin-bottom: 8px;
        }

        .header h1 {
            font-size: 15px;
            margin-bottom: 4px;
        }

        .header h2 {
            font-size: 13px;
            margin-bottom: 4px;
            font-weight: bold;
        }

        .header .sub {
            font-size: 10px;
            margin-bottom: 2px;
        }

        .note {
            font-size: 9px;
            font-style: italic;
            margin-top: 6px;
            color: #333;
        }

        table.main {
            width: 100%;
            border-collapse: collapse;
        }

        table.main > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0 8px;
        }

        .section-major {
            font-weight: bold;
            font-size: 11px;
            text-align: center;
            text-decoration: underline;
            margin: 10px 0 6px 0;
        }

        .section-minor {
            font-weight: bold;
            font-size: 10px;
            margin: 8px 0 4px 0;
        }

        .row-line td {
            padding: 2px 0;
        }

        .col-label {
            width: 72%;
        }

        .col-amt {
            width: 28%;
            text-align: right;
        }

        .subtotal td {
            border-top: 1px solid #000;
            font-weight: bold;
            padding-top: 4px;
        }

        .total-block td {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-weight: bold;
            padding: 5px 0;
        }

        .verify-ok {
            margin-top: 14px;
            padding: 6px;
            text-align: center;
            background: #d4edda;
            font-weight: bold;
        }

        .verify-warn {
            margin-top: 14px;
            padding: 6px;
            text-align: center;
            background: #f8d7da;
            font-weight: bold;
        }

        .period {
            font-size: 9px;
            color: #444;
            margin-top: 4px;
        }
    </style>
</head>
<body>
@php
    $fmt = function ($n) {
        $n = (float) $n;
        if (abs($n) < 0.0005) {
            return '—';
        }
        if ($n < 0) {
            return '(' . number_format(abs($n), 2) . ')';
        }
        return number_format($n, 2);
    };
    $logoSrc = null;
    if (!empty($empresa->logo)) {
        $logoRel = ltrim(str_replace('\\', '/', (string) $empresa->logo), '/');
        if ($logoRel !== '' && strpos($logoRel, '..') === false) {
            $fullLogo = public_path('img' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoRel));
            if (is_file($fullLogo)) {
                $mime = null;
                if (function_exists('finfo_open')) {
                    $fi = finfo_open(FILEINFO_MIME_TYPE);
                    if ($fi) {
                        $mime = finfo_file($fi, $fullLogo) ?: null;
                        finfo_close($fi);
                    }
                }
                if (! $mime && function_exists('mime_content_type')) {
                    $mime = @mime_content_type($fullLogo) ?: null;
                }
                if ($mime && strpos($mime, 'image/') === 0) {
                    $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullLogo));
                }
            }
        }
    }
@endphp

<section id="balance">
    <div class="header">
        @if($logoSrc)
            <img class="logo" src="{{ $logoSrc }}" alt="">
        @endif
        <h1>{{ $empresa->nombre }}</h1>
        <h2>BALANCE GENERAL</h2>
        <p class="sub">Estado de situación financiera</p>
        <p class="sub">Al {{ $balance['fecha_corte_label'] ?? '' }}</p>
        <p class="sub">(Expresado en dólares estadounidenses — USD)</p>
        <p class="period">Periodo de movimientos considerado: {{ $fecha_inicio }} al {{ $fecha_fin }}</p>
    </div>

    <table class="main">
        <tr>
            <td>
                <div class="section-major">ACTIVOS</div>

                <div class="section-minor">{{ $balance['activo_corriente']['titulo'] ?? 'Activo corriente' }}</div>
                <table style="width:100%; border-collapse:collapse;">
                    @foreach(($balance['activo_corriente']['lineas'] ?? []) as $linea)
                        <tr class="row-line">
                            <td class="col-label">{{ $linea['etiqueta'] }}</td>
                            <td class="col-amt">{{ $fmt($linea['monto'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal row-line">
                        <td class="col-label">{{ $balance['activo_corriente']['total_etiqueta'] ?? '' }}</td>
                        <td class="col-amt">{{ $fmt($balance['activo_corriente']['total'] ?? 0) }}</td>
                    </tr>
                </table>

                <div class="section-minor">{{ $balance['activo_no_corriente']['titulo'] ?? 'Activo no corriente' }}</div>
                <table style="width:100%; border-collapse:collapse;">
                    @foreach(($balance['activo_no_corriente']['lineas'] ?? []) as $linea)
                        <tr class="row-line">
                            <td class="col-label">{{ $linea['etiqueta'] }}</td>
                            <td class="col-amt">{{ $fmt($linea['monto'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal row-line">
                        <td class="col-label">{{ $balance['activo_no_corriente']['total_etiqueta'] ?? '' }}</td>
                        <td class="col-amt">{{ $fmt($balance['activo_no_corriente']['total'] ?? 0) }}</td>
                    </tr>
                </table>

                <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                    <tr class="total-block row-line">
                        <td class="col-label">TOTAL ACTIVOS</td>
                        <td class="col-amt">{{ $fmt($balance['totales']['activos'] ?? 0) }}</td>
                    </tr>
                </table>
            </td>
            <td>
                <div class="section-major">PASIVOS Y PATRIMONIO</div>

                <div class="section-minor">{{ $balance['pasivo_corriente']['titulo'] ?? 'Pasivo corriente' }}</div>
                <table style="width:100%; border-collapse:collapse;">
                    @foreach(($balance['pasivo_corriente']['lineas'] ?? []) as $linea)
                        <tr class="row-line">
                            <td class="col-label">{{ $linea['etiqueta'] }}</td>
                            <td class="col-amt">{{ $fmt($linea['monto'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal row-line">
                        <td class="col-label">{{ $balance['pasivo_corriente']['total_etiqueta'] ?? '' }}</td>
                        <td class="col-amt">{{ $fmt($balance['pasivo_corriente']['total'] ?? 0) }}</td>
                    </tr>
                </table>

                <div class="section-minor">{{ $balance['pasivo_no_corriente']['titulo'] ?? 'Pasivo no corriente' }}</div>
                <table style="width:100%; border-collapse:collapse;">
                    @foreach(($balance['pasivo_no_corriente']['lineas'] ?? []) as $linea)
                        <tr class="row-line">
                            <td class="col-label">{{ $linea['etiqueta'] }}</td>
                            <td class="col-amt">{{ $fmt($linea['monto'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal row-line">
                        <td class="col-label">{{ $balance['pasivo_no_corriente']['total_etiqueta'] ?? '' }}</td>
                        <td class="col-amt">{{ $fmt($balance['pasivo_no_corriente']['total'] ?? 0) }}</td>
                    </tr>
                </table>

                <table style="width:100%; border-collapse:collapse; margin-top:6px;">
                    <tr class="subtotal row-line">
                        <td class="col-label">TOTAL PASIVOS</td>
                        <td class="col-amt">{{ $fmt($balance['totales']['pasivos'] ?? 0) }}</td>
                    </tr>
                </table>

                <div class="section-minor">{{ $balance['patrimonio']['titulo'] ?? 'Patrimonio' }}</div>
                <table style="width:100%; border-collapse:collapse;">
                    @foreach(($balance['patrimonio']['lineas'] ?? []) as $linea)
                        <tr class="row-line">
                            <td class="col-label">{{ $linea['etiqueta'] }}</td>
                            <td class="col-amt">{{ $fmt($linea['monto'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal row-line">
                        <td class="col-label">{{ $balance['patrimonio']['total_etiqueta'] ?? '' }}</td>
                        <td class="col-amt">{{ $fmt($balance['patrimonio']['total'] ?? 0) }}</td>
                    </tr>
                </table>

                <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                    <tr class="total-block row-line">
                        <td class="col-label">TOTAL PASIVOS + PATRIMONIO</td>
                        <td class="col-amt">{{ $fmt($balance['totales']['pasivos_mas_patrimonio'] ?? 0) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(!empty($balance['ecuacion_cuadra']) && $balance['ecuacion_cuadra'])
        <div class="verify-ok">Ecuación contable verificada: Total activos = Total pasivos + Patrimonio.</div>
    @else
        <div class="verify-warn">
            Diferencia en ecuación contable: {{ $fmt($balance['diferencia_ecuacion'] ?? 0) }}.
            Revise clasificación de cuentas (rubros y nombres) y partidas del periodo.
        </div>
    @endif
</section>
</body>
</html>
