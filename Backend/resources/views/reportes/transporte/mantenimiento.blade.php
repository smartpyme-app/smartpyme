<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mantenimiento</title>
</head>

<style>
    .text-center { text-align: center; }
    .text-right { text-align: right !important;}
    .text-left { text-align: left; }
    *{ font-family: sans-serif; color: #333; margin:0px; padding: 0px;}
    @page { margin: 150px 70px 70px 70px !important; }
    .header { position: fixed; top: -120px;}
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
        <img class="m-0" src="{{ asset('img/'. $mantenimiento->sucursal->empresa->logo) }}" alt="Logo" width="100">
    </div>
    <div class="footer">
        <h4 class="text-center">
            {{ $mantenimiento->sucursal->empresa->nombre }} |
            {{ $mantenimiento->sucursal->empresa->correo }} |
            {{ $mantenimiento->sucursal->empresa->telefono }}
        </h4> 
    </div>

    <table class="table table-bordered">
        <tr>
            <td colspan="2"><h2 class="text-center"> Mantenimiento N° {{ $mantenimiento->id }} </h2></td>
        </tr>
        <tr>
            <td colspan="2"><h3 class="text-center">Datos</h3></td>
        </tr>
        <tr>
            <td><b>Mantenimiento:</b> <br>
                Fecha: {{ \Carbon\Carbon::parse($mantenimiento->fecha)->format('d/m/Y') }} <br>
                Tipo: {{ $mantenimiento->tipo }}
            </td>
            <td><b>Flota:</b> <br>
                Matriculas: {{ $mantenimiento->flota()->pluck('placa')->first() }} <br>
                Tipo: {{ $mantenimiento->flota()->pluck('tipo')->first() }} <br>
            </td>
        </tr>
    </table>

    <table class="table table-bordered">
        <tr>
            <td colspan="5"><h3 class="text-center">Detalles del mantenimiento</h3></td>
        </tr>
        <tr>
            <th width="50">N°</th>
            <th>Descripción</th>
            <th width="50">Cantidad</th>
            <th width="50" class="text-right">Costo</th>
            <th width="50" class="text-right">Total</th>
        </tr>
        @foreach ($mantenimiento->detalles as $detalle)
        <tr>
            <td>{{ $loop->index + 1 }}</td>
            <td>{{ $detalle->nombre_producto }}</td>
            <td>{{ $detalle->cantidad }}</td>
            <td class="text-right">${{ number_format($detalle->costo,2) }}</td>
            <td class="text-right">${{ number_format($detalle->total,2) }}</td>
        </tr>
        @endforeach
        <tr>
            <td colspan="4" class="text-right">Totales</td>
            <td class="text-right">${{ number_format($mantenimiento->total,2) }}</td>
        </tr>
        <tr>
            <td colspan="5">
                <br>
                <p><b>Notas:</b> <br> 
                    {!! nl2br($mantenimiento->nota) !!}
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
                Mecanico
            </td>
            <td width="50%">
                <br><br><br><br>
                Supervisor
            </td>
        </tr>
    </table>

</body>
</html>
