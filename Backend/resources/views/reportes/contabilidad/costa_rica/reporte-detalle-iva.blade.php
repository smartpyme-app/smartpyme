<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 6px; }
        h1, h2 { margin: 2px 0; text-align: center; }
        .meta { margin: 4px 0 6px; }
        .meta span { margin-right: 1.2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1px 2px; border: 1px solid #000; }
        th { font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
@php
    $empresa = $empresa ?? Auth::user()?->empresa()->first();
    $filas = $filas ?? [];
    $totales = $totales ?? [];
    $esVentas = (bool) ($esVentas ?? false);
    $inicio = $request->inicio ?? now()->toDateString();
    $fin = $request->fin ?? $inicio;
    $n = static fn ($v) => number_format((float) ($v ?? 0), 2);
@endphp

    <h1>{{ $empresa->nombre ?? 'EMPRESA' }}</h1>
    <h2>{{ $titulo }}</h2>
    <div class="meta">
        <span>Periodo: {{ $inicio }} — {{ $fin }}</span>
        <span>Identificación: {{ $empresa->nit ?? '' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>NombreEmisor</th>
                <th>RFC_Emisor</th>
                <th>Fecha</th>
                <th>NombreReceptor</th>
                <th>RFC_Receptor</th>
                <th>TipoDoc</th>
                <th>CantLineas</th>
                <th>Exoneracion</th>
                @if ($esVentas)
                    <th>ExoPorc</th>
                @endif
                <th>Retenciones</th>
                <th>Folio</th>
                <th>Clave</th>
                <th>MedioPago</th>
                <th>CodMoneda</th>
                <th>TipoCambio</th>
                <th>Subtotal13</th>
                <th>Subtotal8</th>
                <th>Subtotal4</th>
                <th>Subtotal2</th>
                <th>Subtotal1</th>
                <th>SubtotalExonerado</th>
                <th>SubtotalExento</th>
                <th>SubtotalGravado</th>
                <th>IVA13</th>
                <th>IVA8</th>
                <th>IVA4</th>
                <th>IVA2</th>
                <th>IVA1</th>
                <th>IVADevuelto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $i => $fila)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $fila['nombre_emisor'] ?? '' }}</td>
                    <td>{{ $fila['rfc_emisor'] ?? '' }}</td>
                    <td class="text-center">{{ $fila['fecha'] ?? '' }}</td>
                    <td>{{ $fila['nombre_receptor'] ?? '' }}</td>
                    <td>{{ $fila['rfc_receptor'] ?? '' }}</td>
                    <td>{{ $fila['tipo_doc'] ?? '' }}</td>
                    <td class="text-center">{{ $fila['cant_lineas'] ?? '' }}</td>
                    <td>{{ $fila['exoneracion'] ?? '' }}</td>
                    @if ($esVentas)
                        <td>{{ $fila['exo_porc'] ?? '' }}</td>
                    @endif
                    <td class="text-right">{{ $n($fila['retenciones'] ?? 0) }}</td>
                    <td>{{ $fila['folio'] ?? '' }}</td>
                    <td>{{ $fila['clave'] ?? '' }}</td>
                    <td>{{ $fila['medio_pago'] ?? '' }}</td>
                    <td>{{ $fila['cod_moneda'] ?? '' }}</td>
                    <td class="text-right">{{ $n($fila['tipo_cambio'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['subtotal_13'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['subtotal_8'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['subtotal_4'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['subtotal_2'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['subtotal_1'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['subtotal_exonerado'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['subtotal_exento'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['subtotal_gravado'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['iva_13'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['iva_8'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['iva_4'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['iva_2'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['iva_1'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($fila['iva_devuelto'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
        @if ($totales)
            <tfoot>
                <tr>
                    <td colspan="{{ $esVentas ? 10 : 9 }}"><strong>TOTALES</strong></td>
                    <td class="text-right">{{ $n($totales['retenciones'] ?? 0) }}</td>
                    <td colspan="5"></td>
                    <td class="text-right">{{ $n($totales['subtotal_13'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['subtotal_8'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['subtotal_4'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['subtotal_2'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['subtotal_1'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['subtotal_exonerado'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['subtotal_exento'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['subtotal_gravado'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['iva_13'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['iva_8'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['iva_4'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['iva_2'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['iva_1'] ?? 0) }}</td>
                    <td class="text-right">{{ $n($totales['iva_devuelto'] ?? 0) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
