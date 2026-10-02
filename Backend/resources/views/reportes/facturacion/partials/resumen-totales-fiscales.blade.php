@php
    $descuentoImp = \App\Support\Honduras\DocumentoImpresionHn::descuentoImpresion(
        (float) ($venta->descuento ?? 0),
        $totales
    );
    $filasTasa = collect($totales['isv_por_tasa'] ?? [])->sortBy('tasa', SORT_NUMERIC);
    $prefijoMoneda = $prefijoMoneda ?? ($moneda ?? '');
    $etiquetaImp = $etiquetaImpuesto ?? ($esHonduras ?? false ? 'ISV' : 'IVA');
@endphp
<tr>
    <td>Subtotal</td>
    <td class="val">{{ $prefijoMoneda }} {{ number_format((float) ($venta->sub_total ?? 0), 2) }}</td>
</tr>
@if (($totales['exonerado'] ?? 0) > 0)
<tr>
    <td>Exonerado</td>
    <td class="val">{{ $prefijoMoneda }} {{ number_format((float) $totales['exonerado'], 2) }}</td>
</tr>
@endif
<tr>
    <td>Exento</td>
    <td class="val">{{ $prefijoMoneda }} {{ number_format(max((float) ($venta->exenta ?? 0), (float) ($totales['exento'] ?? 0)), 2) }}</td>
</tr>
@foreach ($filasTasa as $fila)
    @php
        $tasaFmt = rtrim(rtrim(number_format((float) $fila['tasa'], 2, '.', ''), '0'), '.');
    @endphp
    <tr>
        <td>Gravado {{ $tasaFmt }}% {{ $etiquetaImp }}</td>
        <td class="val">{{ $prefijoMoneda }} {{ number_format((float) $fila['gravado'], 2) }}</td>
    </tr>
    <tr>
        <td>{{ $etiquetaImp }} {{ $tasaFmt }}%</td>
        <td class="val">{{ $prefijoMoneda }} {{ number_format((float) $fila['isv'], 2) }}</td>
    </tr>
@endforeach
@if ($filasTasa->isEmpty())
    <tr>
        <td>{{ $etiquetaImp }}</td>
        <td class="val">{{ $prefijoMoneda }} {{ number_format((float) ($venta->iva ?? 0), 2) }}</td>
    </tr>
@endif
@if ($descuentoImp > 0)
<tr>
    <td>Descuentos y rebajas</td>
    <td class="val">{{ $prefijoMoneda }} {{ number_format($descuentoImp, 2) }}</td>
</tr>
@endif
<tr>
    <td class="b">Total</td>
    <td class="val b">{{ $prefijoMoneda }} {{ number_format((float) $venta->total, 2) }}</td>
</tr>
