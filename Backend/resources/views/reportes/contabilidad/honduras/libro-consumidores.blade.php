<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro de Ventas a Consumidor Final</title>
    <style>
        body{ font-family: Arial, sans-serif; font-size: 8px; }
        h1, h2{ margin: 2px 0; text-align: center; }
        .meta{ margin: 4px 0 6px; }
        .meta span{ margin-right: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        thead th, tbody td, tfoot td { padding: 2px 3px; border: 1px solid #000; vertical-align: middle; }
        thead th { font-weight: bold; text-align: center; }
        .text-right{ text-align: right; }
        .text-center{ text-align: center; }
        .resumen{ width: 45%; margin-top: 1rem; border-collapse: collapse; }
        .resumen td{ padding: 2px 4px; border: 1px solid #000; }
        .firma{ margin-top: 1.5rem; }
    </style>
</head>
<body>
@php
    // ponytail: $empresa inyectable para render sin sesión (techo: Auth en request real).
    $empresa = $empresa ?? Auth::user()?->empresa()->first();
    $filas = $filas ?? [];
    $resumen = $resumen ?? [];
    $inicio = $request->inicio ?? now()->toDateString();
@endphp

    <h1>{{ $empresa->nombre ?? 'EMPRESA' }}</h1>
    <h2>LIBRO DE VENTAS A CONSUMIDOR FINAL</h2>
    <div class="meta">
        <span>MES: {{ ucfirst(\Carbon\Carbon::parse($inicio)->translatedFormat('F')) }}</span>
        <span>AÑO: {{ \Carbon\Carbon::parse($inicio)->format('Y') }}</span>
        <span>NIT: {{ $empresa->nit ?? '' }}</span>
        <span>NRC: {{ $empresa->ncr ?? '' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">N°</th>
                <th rowspan="2">Fecha</th>
                <th rowspan="2">Factura N°</th>
                <th rowspan="2">CAI N°</th>
                <th rowspan="2">N° de Maquina<br>registradora</th>
                <th rowspan="2">Ventas<br>Exentas</th>
                <th rowspan="2">Ventas<br>Exoneradas</th>
                <th colspan="2">Ventas Gravadas</th>
                <th rowspan="2">Total Ventas</th>
                <th rowspan="2">Ventas a Cuenta de<br>Terceros</th>
            </tr>
            <tr>
                <th>15%</th>
                <th>18%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td class="text-center">{{ $fila['no'] ?? '' }}</td>
                    <td class="text-center">{{ !empty($fila['fecha']) ? \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $fila['factura_no'] ?? '' }}</td>
                    <td>{{ $fila['cai_no'] ?? '' }}</td>
                    <td>{{ $fila['maquina_registradora'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['exentas'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['exoneradas'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['gravadas_15'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['gravadas_18'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['total_ventas'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['cuenta_terceros'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-center"><b>TOTAL</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('exentas'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('exoneradas'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('gravadas_15'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('gravadas_18'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('total_ventas'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('cuenta_terceros'), 2) }}</b></td>
            </tr>
        </tfoot>
    </table>

    <table class="resumen">
        <tr><td colspan="2"><b>Resumen</b></td></tr>
        <tr>
            <td>Exentas</td>
            <td class="text-right">{{ number_format((float) ($resumen['total_exentas'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Exoneradas</td>
            <td class="text-right">{{ number_format((float) ($resumen['total_exoneradas'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Netas Gravadas 15%</td>
            <td class="text-right">{{ number_format((float) ($resumen['netas_15'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Netas Gravadas 18%</td>
            <td class="text-right">{{ number_format((float) ($resumen['netas_18'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Debito Fiscal</td>
            <td class="text-right">{{ number_format((float) ($resumen['debito_fiscal'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Credito Fiscal</td>
            <td class="text-right">{{ number_format((float) ($resumen['credito_fiscal'] ?? 0), 2) }}</td>
        </tr>
    </table>

    <div class="firma">__________________________<br>Nombre y Firma de Contador</div>
</body>
</html>
