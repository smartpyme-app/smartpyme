<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro de Compras</title>
    <style>
        body{ font-family: Arial, sans-serif; font-size: 7px; }
        h1, h2{ margin: 2px 0; text-align: center; }
        .meta{ margin: 4px 0 6px; }
        .meta span{ margin-right: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        thead th, tbody td, tfoot td { padding: 2px 3px; border: 1px solid #000; vertical-align: middle; }
        thead th { font-weight: bold; text-align: center; }
        .text-right{ text-align: right; }
        .text-center{ text-align: center; }
        .firma{ margin-top: 1.5rem; text-align: center; }
    </style>
</head>
<body>
@php
    // ponytail: $empresa inyectable para render sin sesión (techo: Auth en request real).
    $empresa = $empresa ?? Auth::user()?->empresa()->first();
    $filas = $filas ?? [];
    $totales = $totales ?? [];
    $inicio = $request->inicio ?? now()->toDateString();
@endphp

    <h1>{{ $empresa->nombre ?? 'EMPRESA' }}</h1>
    <h2>LIBRO DE COMPRAS</h2>
    <div class="meta">
        <span>MES: {{ ucfirst(\Carbon\Carbon::parse($inicio)->translatedFormat('F')) }}</span>
        <span>AÑO: {{ \Carbon\Carbon::parse($inicio)->format('Y') }}</span>
        <span>NIT: {{ $empresa->nit ?? '' }}</span>
        <span>NRC: {{ $empresa->ncr ?? '' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">No.</th>
                <th rowspan="2">FECHA DE<br>EMISION</th>
                <th rowspan="2">N° DE<br>DOCUMENTO</th>
                <th rowspan="2">NRC</th>
                <th rowspan="2">NIT O DUI DE<br>SUJETO<br>EXCLUIDO</th>
                <th rowspan="2">NOMBRE DE<br>PROVEEDOR</th>
                <th colspan="3">COMPRAS EXENTAS</th>
                <th colspan="3">COMPRAS GRAVADAS</th>
                <th colspan="4">CONTRIBUCION ESPECIAL</th>
                <th rowspan="2">ANTICIPO A CUENTA<br>IVA PERCIBIDO</th>
                <th rowspan="2">TOTAL<br>COMPRAS</th>
                <th rowspan="2">RETENCION A<br>TERCEROS</th>
                <th rowspan="2">COMPRAS A<br>SUJETOS<br>EXCLUIDOS</th>
            </tr>
            <tr>
                <th>INTERNAS</th>
                <th>INTERNACIONES</th>
                <th>IMPORTACIONES</th>
                <th>INTERNAS</th>
                <th>INTERNACIONES</th>
                <th>IMPORTACIONES</th>
                <th>CREDITO<br>FISCAL</th>
                <th>FOVIAL</th>
                <th>COTRANS</th>
                <th>CESC</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td class="text-center">{{ $fila['no'] ?? '' }}</td>
                    <td class="text-center">{{ !empty($fila['fecha_emision']) ? \Carbon\Carbon::parse($fila['fecha_emision'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $fila['numero_documento'] ?? '' }}</td>
                    <td>{{ $fila['nrc'] ?? '' }}</td>
                    <td>{{ $fila['nit_o_dui'] ?? '' }}</td>
                    <td>{{ $fila['nombre_proveedor'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['exentas_internas'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['exentas_internaciones'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['exentas_importaciones'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['gravadas_internas'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['gravadas_internaciones'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['gravadas_importaciones'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['credito_fiscal'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['fovial'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['cotrans'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['cesc'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['anticipo_iva_percibido'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['total'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['retencion_terceros'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['compras_sujetos_excluidos'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-center"><b>TOTAL</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['exentas_internas'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['exentas_internaciones'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['exentas_importaciones'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['gravadas_internas'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['gravadas_internaciones'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['gravadas_importaciones'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['credito_fiscal'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['fovial'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['cotrans'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['cesc'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['anticipo_iva_percibido'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['total'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['retencion_terceros'] ?? 0), 2) }}</b></td>
                <td class="text-right"><b>{{ number_format((float) ($totales['compras_sujetos_excluidos'] ?? 0), 2) }}</b></td>
            </tr>
        </tfoot>
    </table>

    <div class="firma">__________________________<br>Nombre y Firma de Contador</div>
</body>
</html>
