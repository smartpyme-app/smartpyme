<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro de Ventas a Contribuyentes</title>
    <style>
        body{ font-family: Arial, sans-serif; font-size: 7.5px; }
        h1, h2{ margin: 2px 0; text-align: center; }
        .meta{ margin: 4px 0 6px; }
        .meta span{ margin-right: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        thead th, tbody td, tfoot td { padding: 2px 3px; border: 1px solid #000; vertical-align: middle; }
        thead th { font-weight: bold; text-align: center; }
        .text-right{ text-align: right; }
        .text-center{ text-align: center; }
        .resumen{ width: 70%; margin-top: 1rem; border-collapse: collapse; }
        .resumen th, .resumen td{ padding: 2px 4px; border: 1px solid #000; }
        .firma{ margin-top: 1.5rem; text-align: center; }
    </style>
</head>
<body>
@php
    // ponytail: $empresa inyectable para render sin sesión (techo: Auth en request real).
    $empresa = $empresa ?? Auth::user()?->empresa()->first();
    $filas = $filas ?? [];
    $resumen_operaciones = $resumen_operaciones ?? [];
    $inicio = $request->inicio ?? now()->toDateString();
    $totalesDetalle = $resumen_operaciones['totales_detalle'] ?? [];
    $consumidorFinal = $resumen_operaciones['consumidor_final'] ?? [];
    $contribuyentes = $resumen_operaciones['contribuyentes'] ?? [];
    $ctaTerceros = $resumen_operaciones['cta_terceros'] ?? [];
@endphp

    <h1>{{ $empresa->nombre ?? 'EMPRESA' }}</h1>
    <h2>LIBRO DE VENTAS A CONTRIBUYENTES</h2>
    <div class="meta">
        <span>MES: {{ ucfirst(\Carbon\Carbon::parse($inicio)->translatedFormat('F')) }}</span>
        <span>AÑO: {{ \Carbon\Carbon::parse($inicio)->format('Y') }}</span>
        <span>NIT: {{ $empresa->nit ?? '' }}</span>
        <span>NRC: {{ $empresa->ncr ?? '' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Fecha<br>Emisión</th>
                <th>Numero<br>Correlativo de<br>Documento</th>
                <th>NRC</th>
                <th>Nombre del Contribuyente</th>
                <th>Ventas<br>Exentas</th>
                <th>No Sujetas</th>
                <th>Gravadas<br>Locales</th>
                <th>Débito Fiscal</th>
                <th>Ventas a Cuenta de<br>Terceros</th>
                <th>Debito F. a Cta.<br>De Terceros</th>
                <th>IVA Percibido</th>
                <th>IVA Retenido</th>
                <th>Total Ventas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td class="text-center">{{ $fila['no'] ?? '' }}</td>
                    <td class="text-center">{{ !empty($fila['fecha']) ? \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $fila['correlativo'] ?? '' }}</td>
                    <td>{{ $fila['nrc'] ?? '' }}</td>
                    <td>{{ $fila['nombre'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['exentas'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['no_sujetas'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['gravadas_locales'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['debito_fiscal'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['cta_terceros'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['debito_cta_terceros'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['iva_percibido'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['iva_retenido'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['total'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-center"><b>TOTAL</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('exentas'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('no_sujetas'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('gravadas_locales'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('debito_fiscal'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('cta_terceros'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('debito_cta_terceros'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('iva_percibido'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('iva_retenido'), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format(collect($filas)->sum('total'), 2) }}</b></td>
            </tr>
        </tfoot>
    </table>

    <table class="resumen">
        <thead>
            <tr>
                <th>Resumen Operaciones</th>
                <th>Gravadas</th>
                <th>Exportaciones</th>
                <th>Debito Fiscal</th>
                <th>IVA Percibido</th>
                <th>IVA Retenido</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><b>Total</b></td>
                <td class="text-right">{{ number_format((float) ($totalesDetalle['gravadas'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($totalesDetalle['exportaciones'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($totalesDetalle['debito_fiscal'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($totalesDetalle['iva_percibido'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($totalesDetalle['iva_retenido'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Consumidor Final</td>
                <td class="text-right">{{ number_format((float) ($consumidorFinal['gravadas'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($consumidorFinal['exportaciones'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($consumidorFinal['debito_fiscal'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($consumidorFinal['iva_percibido'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($consumidorFinal['iva_retenido'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Contribuyentes</td>
                <td class="text-right">{{ number_format((float) ($contribuyentes['gravadas'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($contribuyentes['exportaciones'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($contribuyentes['debito_fiscal'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($contribuyentes['iva_percibido'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($contribuyentes['iva_retenido'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Ventas a Cta de Terceros</td>
                <td class="text-right">{{ number_format((float) ($ctaTerceros['gravadas'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($ctaTerceros['exportaciones'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($ctaTerceros['debito_fiscal'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($ctaTerceros['iva_percibido'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float) ($ctaTerceros['iva_retenido'] ?? 0), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="firma">__________________________<br>Nombre y Firma de Contador</div>
</body>
</html>
