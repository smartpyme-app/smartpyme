<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Cotización #{{ $venta->correlativo }} - {{ $venta->nombre_cliente }}</title>
    <style>
        * {
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }

        body {
            font-family: serif;
            margin: 50px;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: #000000 !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 0px;
            border-collapse: collapse;
            padding: 10px 5px;
            text-align: left;
        }

        .text-right {
            text-align: right !important;
        }

        .border-bottom {
            border-bottom: 1px solid #000000 !important;
        }
    </style>

</head>

<body>
    {{-- <body onload="javascript:print();"> --}}

    <table>
        <tbody>
            <tr>
                <td>
                    <h1>{{ $venta->empresa()->pluck('nombre')->first() }}</h1>
                    <p>
                        {{ $venta->empresa()->pluck('municipio')->first() }}
                        {{ $venta->empresa()->pluck('departamento')->first() }}
                    </p>
                    <p>{{ $venta->empresa()->pluck('direccion')->first() }}</p>
                    <p>{{ $venta->empresa()->pluck('telefono')->first() }}</p>
                </td>
                <td class="text-right">
                        @if ($venta->empresa()->pluck('logo')->first())
                            <img width="150" height="150" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
            </tr>
        </tbody>
    </table>

        <table>
            <tbody>
                <tr>
                    <td><h4>Cliente</h4></td>
                </tr>
                <tr>
                    <td>
                        <p>{{ $venta->nombre_cliente }}</p>
                        <p>
                        @if ($venta->empresa()->pluck('id')->first() != 420)
                            {{ $venta->cliente()->pluck('municipio')->first() }}
                            {{ $venta->cliente()->pluck('departamento')->first() }}
                        @endif
                            {{ $venta->cliente()->pluck('direccion')->first() }} <br>
                        </p>
                    </td>
                    <td>

                        @if ($venta->empresa()->pluck('id')->first() != 420)
                            @if($venta->empresa->pais == 'El Salvador')
                                <p>NCR:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                                <p>DUI:{{ $venta->cliente()->pluck('dui')->first() }}</p>
                            @else
                                <p>Registro tributario:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                                <p>Número de identificación:{{ $venta->cliente()->pluck('dui')->first() }}</p>
                            @endif
                            <p>Teléfono:{{ $venta->cliente()->pluck('telefono')->first() }}</p>
                        @else
                            <p>RTN:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                            <p>Teléfono:{{ $venta->cliente()->pluck('telefono')->first() }}</p>
                        @endif
                    </td>
                    <td>
                        <p class="text-right">Cotización #{{ $venta->correlativo }}</p>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                        <p class="text-right">Válido hasta: {{ \Carbon\Carbon::parse($venta->fecha_expiracion)->format('d/m/Y') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>

    <br>

        <table class="table">
            <thead>
                <tr>
                    <th class="border-bottom">Descripción</th>
                    <th class="border-bottom text-right">Cantidad</th>
                    <!-- Custom field headers -->
                    @php
                        $uniqueCustomFields = collect([]);
                        foreach($venta->detalles as $detalle) {
                        foreach($detalle->customFields as $field) {
                        $uniqueCustomFields->push([
                        'id' => $field->custom_field_id,
                        'name' => $field->customField->name
                        ]);
                        }
                        }
                        $uniqueCustomFields = $uniqueCustomFields->unique('id')->sortBy('id');
                        $totalColumns = 4 + $uniqueCustomFields->count(); // Base columns + custom fields
                    @endphp
                    @foreach($uniqueCustomFields as $field)
                        <th class="border-bottom text-right">{{ $field['name'] }}</th>
                    @endforeach
                    <th class="border-bottom text-right">Precio</th>
                    <th style="width: 90px;" class="border-bottom text-right">Descuento</th>
                    <th class="border-bottom text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                @php
                    $subtotal_linea = $detalle->cantidad * $detalle->precio;
                    $porcentaje_descuento = $subtotal_linea > 0 ? round(($detalle->descuento / $subtotal_linea) * 100, 2) : 0;
                @endphp
                <tr>
                    <td class="@if ($detalle->descuento == 0) border-bottom @endif">@include('reportes.facturacion.partials.cotizacion-detalle-descripcion')</td>
                    <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <!-- Custom field values -->
                    @foreach($uniqueCustomFields as $field)
                        @php
                            $customValue = $detalle->customFields->first(function($cf) use ($field) {
                            return $cf->custom_field_id == $field['id'];
                            });
                        @endphp
                        <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">
                            @if($customValue)
                                @if($customValue->custom_field_value_id)
                                    {{ $customValue->customFieldValue->value }}
                                @else
                                    {{ $customValue->value }}
                                @endif
                            @else
                                ----
                            @endif
                        </td>
                    @endforeach
                    <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">   {{ $venta->empresa->currency->currency_symbol }} {{number_format($detalle->precio , 2) }}</td>
                    <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">  @if ($detalle->descuento > 0) {{ $venta->empresa->currency->currency_symbol }}{{ number_format($detalle->descuento, 2) }} <small>({{ $porcentaje_descuento }}%)</small> @endif</td>
                    <td class="@if ($detalle->descuento == 0) border-bottom @endif text-right">   {{ $venta->empresa->currency->currency_symbol }} {{ number_format($detalle->total, 2) }}</td>
                </tr>
                @if ($detalle->descuento > 0)
                    <tr>
                        <td>DESCUENTOS</td>
                        <td></td>
                        @foreach($uniqueCustomFields as $field)
                            <td class="@if ($detalle->descuento == 0) border-bottom @endif"></td>
                        @endforeach
                        <td></td>
                        <td class="text-right">- {{ $venta->empresa->currency->currency_symbol }} {{ number_format($detalle->descuento, 2) }} </td>
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
                    <td class="text-right">
                        @if ($venta->empresa->pais == 'Honduras')
                            ISV
                        @else
                            IVA
                        @endif
                    </td>
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

        <br>
        <h4>Términos y condiciones:</h4>
{{--        <p>{{ $venta->observaciones }}</p>--}}
        <p>{!! nl2br(e($venta->observaciones))  !!} </p>
        <br>
        @if($venta->empresa->mostrar_sello_firma_cotizacion)
    <table style="width: 100%; margin-top: 30px;">
        <tr>
            <td style="width: 50%; padding: 20px; text-align: center;">
                @if($venta->empresa->firma)
                <img
                    src="{{ asset('img/'.$venta->empresa->firma) }}"
                    alt="Firma"
                    style="max-width: 150px; max-height: 100px;">
                @else
                <div style="height: 100px; line-height: 100px;">(Sin firma)</div>
                @endif
            </td>
            <td style="width: 50%; padding: 20px; text-align: center;">
                @if($venta->empresa->sello)
                <img
                    src="{{ asset('img/'.$venta->empresa->sello) }}"
                    alt="Sello"
                    style="max-width: 150px; max-height: 100px;">
                @else
                <div style="height: 100px; line-height: 100px;">(Sin sello)</div>
                @endif
            </td>
        </tr>
        <tr>
            <td style="width: 50%; padding: 10px; text-align: center;">
                <p>____________________________</p>
                <h4 style="margin: 0; font-size: 16px; color: #333;">Firma</h4>
            </td>
            <td style="width: 50%; padding: 10px; text-align: center;">
                <p>____________________________</p>
                <h4 style="margin: 0; font-size: 16px; color: #333;">Sello</h4>
            </td>
        </tr>
    </table>
    @endif

    <br>
    <br>
    <br>

    <h4>Firma:</h4>
    <p>____________________________</p>



    </section>


</body>
</html>
