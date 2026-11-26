{{-- Requiere: $venta, opcional $cotizacion_color_marca para encabezado con marca --}}
@php
    $brand = $cotizacion_color_marca ?? null;
    $thStyle = $brand
        ? 'background:' . e($brand) . ';color:#fff;border-bottom:1px solid ' . e($brand) . ';'
        : '';
    $thClass = $brand ? '' : 'border-bottom';
@endphp
<table class="table">
    <thead>
        <tr>
            <th class="{{ $thClass }}" @if($thStyle !== '') style="{{ $thStyle }} padding:10px 5px;" @endif>Descripción</th>
            <th class="{{ $thClass }} text-right" @if($thStyle !== '') style="{{ $thStyle }} padding:10px 5px;" @endif>Cantidad</th>
            @php
                $uniqueCustomFields = collect([]);
                foreach ($venta->detalles as $detalle) {
                    foreach ($detalle->customFields as $field) {
                        $uniqueCustomFields->push([
                            'id' => $field->custom_field_id,
                            'name' => $field->customField->name,
                        ]);
                    }
                }
                $uniqueCustomFields = $uniqueCustomFields->unique('id')->sortBy('id');
            @endphp
            @foreach ($uniqueCustomFields as $field)
                <th class="{{ $thClass }} text-right" @if($thStyle !== '') style="{{ $thStyle }} padding:10px 5px;" @endif>{{ $field['name'] }}</th>
            @endforeach
            <th class="{{ $thClass }} text-right" @if($thStyle !== '') style="{{ $thStyle }} padding:10px 5px;" @endif>Precio</th>
            <th style="width: 90px;" class="{{ $thClass }} text-right" @if($thStyle !== '') style="width:90px;{{ $thStyle }} padding:10px 5px;" @endif>Descuento</th>
            <th class="{{ $thClass }} text-right" @if($thStyle !== '') style="{{ $thStyle }} padding:10px 5px;" @endif>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($venta->detalles as $detalle)
            @php
                $subtotal_linea = $detalle->cantidad * $detalle->precio;
                $porcentaje_descuento = $subtotal_linea > 0 ? round(($detalle->descuento / $subtotal_linea) * 100, 2) : 0;
            @endphp
            <tr>
                <td class="@if ($detalle->descuento == 0) border-bottom @endif">@include('reportes.facturacion.partials.cotizacion-detalle-descripcion')</td>
                <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">{{ number_format($detalle->cantidad, 0) }}</td>
                @foreach ($uniqueCustomFields as $field)
                    @php
                        $customValue = $detalle->customFields->first(function ($cf) use ($field) {
                            return $cf->custom_field_id == $field['id'];
                        });
                    @endphp
                    <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">
                        @if ($customValue)
                            @if ($customValue->custom_field_value_id)
                                {{ $customValue->customFieldValue->value }}
                            @else
                                {{ $customValue->value }}
                            @endif
                        @else
                            ----
                        @endif
                    </td>
                @endforeach
                <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($detalle->precio, 2) }}</td>
                <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">
                    @if ($detalle->descuento > 0)
                        {{ $venta->empresa->currency->currency_symbol }}{{ number_format($detalle->descuento, 2) }}
                        <small style="font-size: 12px;">({{ $porcentaje_descuento }}%)</small>
                    @endif
                </td>
                <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($detalle->total, 2) }}</td>
            </tr>
            @if ($detalle->descuento > 0)
                <tr>
                    <td>DESCUENTOS</td>
                    <td></td>
                    @foreach ($uniqueCustomFields as $field)
                        <td></td>
                    @endforeach
                    <td></td>
                    <td class="text-right">- {{ $venta->empresa->currency->currency_symbol }} {{ number_format($detalle->descuento, 2) }}</td>
                    <td></td>
                </tr>
            @endif
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3"></td>
            <td class="text-right">Sumas</td>
            <td class="text-right">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($venta->sub_total, 2) }}</td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td class="text-right">{{ \App\Helpers\CountryTermsHelper::tax('taxLabel', $venta->empresa) }}</td>
            <td class="text-right">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($venta->iva, 2) }}</td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td class="text-right">Subtotal</td>
            <td class="text-right">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($venta->sub_total + $venta->iva, 2) }}</td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td class="text-right"><b>Total</b></td>
            <td class="text-right"><b>{{ $venta->empresa->currency->currency_symbol }}{{ number_format($venta->total, 2) }}</b></td>
        </tr>
    </tfoot>
</table>