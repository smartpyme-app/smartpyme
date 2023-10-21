<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manifiesto de carga</title>
</head>

<style>
    .text-center { text-align: center; }
    .text-right { text-align: right !important;}
    .text-left { text-align: left; }
    *{ font-family: sans-serif; color: #333; margin:0px; padding: 0px;}
    @page { margin: 130px 70px 70px 70px !important; }
    .header { position: fixed; top: -90px;}
    .footer{ position: fixed; bottom: 0px; opacity: .6; }
    .bg{ width: 840px; position: fixed; top: -150px; left: -120px; opacity: .5; z-index: -1;}
    .table {width: 100%; border-collapse: collapse;}
    .table td, .table th, .table td, { padding: 5px 10px; font-size: 14px; text-align: left;}
    .table-bordered td, .table-bordered th, .table-bordered td, { border: 0.5px solid gray; }
    hr{ border: 0.5px solid #D1D0D0; }
    .notas>br:before {content: "*"; color: black; }
    p{ text-align: justify; }
    h2{ margin-bottom: 15px; }
    .badge{ background-color: #1B5FFA; color: white; padding: 5px;}
    .completado{text-decoration:line-through; color: gray;}
    .m-0{ margin:0px; }

</style>

<body>
    {{-- <img class="bg" src="imgs/logo.jpg"> --}}
    <div class="header text-center">
        <img class="m-0" src="{{ asset('img/'. $flete->sucursal->empresa->logo) }}" alt="Logo" width="50">
        {{-- <h2 class="text-center"> Manifiesto de carga N° {{ $flete->venta()->pluck('correlativo')->first() }} </h2> --}}
        <h2 class="text-center"> Manifiesto de carga N° {{ $flete->id }} </h2>
    </div>
    <div class="footer">
        <h4 class="text-center">
            {{ $flete->sucursal->empresa->nombre }} |
            {{ $flete->sucursal->empresa->correo }} |
            {{ $flete->sucursal->empresa->telefono }}
        </h4> 
    </div>

    @php
        $meses = array("enero","febrero","marzo","abril","mayo","junio","julio","agosto","septiembre","octubre","noviembre","diciembre");
        $dias = array("lunes","martes","miercoles","jueves","viernes","sabado","domingo");
        $fecha = \Carbon\Carbon::parse($flete->fecha);
        $flete->fecha         = $dias[$fecha->format('L')]. ' ' . $fecha->format('d') . ' de ' . $meses[($fecha->format('n')) - 1] . ' de ' . $fecha->format('Y');

    @endphp

    <p style="margin-bottom: 10px;">Expedida en El Salvador, {{ $flete->fecha }}</p>
    <table class="table table-bordered">
        <tr>
            <td colspan="2"><h3 class="text-center">Datos del remitente y destinatario</h3></td>
        </tr>
        <tr>
            <td width="50%">
                <b>Remitente:</b> <br>
                Nombre: {{ $flete->proveedor()->pluck('nombre')->first() }} <br>
                Dirección: {{ $flete->proveedor()->pluck('direccion')->first() }} <br>
                Tel: {{ $flete->proveedor()->pluck('telefono')->first() }} <br>
                N° Fiscal: {{ $flete->proveedor()->pluck('registro')->first() }} <br>
            </td>
            <td width="50%">
                <b>Destinatario:</b> <br>
                Nombre: {{ $flete->cliente()->pluck('nombre')->first() }} <br>
                Dirección: {{ $flete->cliente()->pluck('direccion')->first() }} <br>
                Tel: {{ $flete->cliente()->pluck('telefono')->first() }} <br>
                N° Fiscal: {{ $flete->cliente()->pluck('registro')->first() }} <br>
            </td>
        </tr>
        <tr>
            <td colspan="2"><h3 class="text-center">Datos de transportista y transporte</h3></td>
        </tr>
        <tr>
            <td><b>Motorista:</b> <br>
                Nombre: {{ $flete->motorista()->pluck('nombre')->first() }} <br>
                Licencia N° {{ $flete->motorista()->pluck('num_licencia')->first() }}
            </td>
            <td><b>Matriculas:</b> <br>
                Cabezal: {{ $flete->cabezal()->pluck('placa')->first() }} <br>
                Remolque: {{ $flete->remolque()->pluck('placa')->first() }}
            </td>
        </tr>
        <tr>
            <td colspan="2"><h3 class="text-center">Itinerario y fechas</h3></td>
        </tr>
        <tr>
            <td><b>Fecha de transporte:</b> <br>
                Carga: {{ Carbon\Carbon::parse($flete->fecha_carga)->format('d/m/Y h:i a') }} <br>
                Descarga: {{ Carbon\Carbon::parse($flete->fecha_descarga)->format('d/m/Y h:i a') }}
            </td>
            <td><b>Medio de transporte:</b> <br>
                Tipo: {{ $flete->tipo_transporte }} <br>
                Destino: {{ $flete->punto_destino }}
            </td>
        </tr>
    </table>

    <table class="table table-bordered">
        <tr>
            <td colspan="7"><h3 class="text-center">Datos de la mercancía</h3></td>
        </tr>
        <tr>
            <th>N°</th>
            <th>Descripción</th>
            <th width="50">Embalaje</th>
            <th width="50" class="text-right">Bultos</th>
            <th width="50" class="text-right">Unidades</th>
            <th width="50" class="text-right">Peso <small>(KG)</small></th>
            <th width="50" class="text-right">Valor</th>
        </tr>
        @foreach ($flete->detalles as $detalle)
        <tr>
            <td>{{ $loop->index + 1 }}</td>
            <td>{{ $detalle->descripcion }}</td>
            <td>{{ $detalle->tipo_embalaje }}</td>
            <td class="text-right">{{ number_format($detalle->bultos,0) }}</td>
            <td class="text-right">{{ number_format($detalle->unidades,0) }}</td>
            <td class="text-right">{{ number_format($detalle->peso,2) }}</td>
            <td class="text-right">${{ number_format($detalle->valor_carga,2) }}</td>
        </tr>
        @endforeach
        <tr>
            <td colspan="3" class="text-right">Totales</td>
            <td class="text-right">{{ number_format($flete->detalles->sum('bultos'),0) }}</td>
            <td class="text-right">{{ number_format($flete->detalles->sum('unidades'),0) }}</td>
            <td class="text-right">${{ number_format($flete->detalles->sum('peso'),2) }}</td>
            <td class="text-right">${{ number_format($flete->detalles->sum('valor_carga'),2) }}</td>
        </tr>
        <tr>
            <td colspan="6" class="text-right">Seguro</td>
            <td class="text-right">${{ number_format($flete->seguro,2) }}</td>
        </tr>
        <tr>
            <td colspan="6" class="text-right">Flete</td>
            <td class="text-right">${{ number_format($flete->total,2) }}</td>
        </tr>
        <tr>
            <td colspan="7">
                <br>
                <p><b>Observaciones, condiciones y términos del transporte:</b> <br> 
                    {!! nl2br($flete->nota) !!}
                </p>
                <br>
            </td>
        </tr>
    </table>
    <table class="table table-bordered">
        <tr>
            <td colspan="2"><h3 class="text-center">Firmas y sellos</h3></td>
        </tr>
        <tr>
            <td width="50%">
                <br><br><br><br>
                Remitente
            </td>
            <td width="50%">
                <br><br><br><br>
                Transportista
            </td>
            {{-- <td width="50%">
                <br><br><br><br>
                Destinatario
            </td> --}}
        </tr>
    </table>

</body>
</html>
