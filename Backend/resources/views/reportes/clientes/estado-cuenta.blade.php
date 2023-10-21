<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Estado de cuenta</title>
</head>

<style>
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    *{ font-family: sans-serif; color: #333; }
    @page { margin: 70px 70px; }
    #logo { position: fixed; top: -20px; left: 0px; width: 100px }
    .header { position: fixed; top: -40px; opacity: .7;  }
    .footer{ position: fixed; bottom: 0px; opacity: .7; }
    .bg{ width: 840px; position: fixed; top: -150px; left: -120px; opacity: .5; z-index: -1;}
    .table {width: 100%; border-collapse: collapse; margin: 100px auto 0px auto;}
    .table-bordered td, .table-bordered th, .table-bordered td, {border: 0.5px solid gray; padding: 5px 10px; }
    hr{ border: 0.5px solid #D1D0D0; }
    .notas>br:before {content: "*"; color: black; }
    p{ text-align: justify; }
    .badge{ background-color: #1B5FFA; color: white; padding: 5px;}
    .completado{text-decoration:line-through; color: gray;}

</style>

<body>
    <img id="logo" src="{{ asset('img/'. $cliente->empresa()->pluck('logo')->first()) }}" alt="Logo">
    <div class="header text-center">
        <h2 class="text-center" style="margin-bottom: 0px;">
            {{ $cliente->empresa()->pluck('nombre')->first() }}<br>
            <small>ESTADO DE CUENTA</small>
        </h2>
        <p class="text-center">
            Fecha: {{ Carbon\Carbon::now()->format('d/m/Y') }}
        </p>
    </div>
    <div class="footer">
        <hr>
        <h4 class="text-center">
            {{ $cliente->empresa()->pluck('nombre')->first() }} | {{ $cliente->empresa()->pluck('correo')->first() }} | {{ $cliente->empresa()->pluck('telefono')->first() }}
        </h4>
    </div>

    <table class="table">
        <tr>
            <td><b>Cliente:</b> {{ $cliente->nombre }}</td>
            <td><b>DUI:</b> {{ $cliente->dui }}</td>
            <td><b>Dirección:</b> {{ $cliente->direccion }} {{ $cliente->municipio }} {{ $cliente->departamento }}</td>
        </tr>
        <tr>
            <td><b>Saldo pendiente:</b> ${{ number_format($cliente->ventas->sum('total') + $cliente->fletes->sum('total'),2) }}</td>
        </tr>
    </table>
    @if ($cliente->ventas->count() > 0)
    <table class="table table-bordered"  style="margin-top: 20px;">
        <thead>
            <tr>
                <th colspan="7" class="text-center">Ventas pendientes</th>
            </tr>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Usuario</th>
                <th class="text-center">Tipo</th>
                <th class="text-center">Correlativo</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cliente->ventas as $venta)
                <tr>
                    <td>{{ $venta->id }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $venta->nombre_usuario }}</td>
                    <td class="text-center">{{ $venta->tipo_documento }}</td>
                    <td class="text-center">{{ $venta->correlativo }}</td>
                    <td class="text-center">{{ $venta->estado }}</td>
                    <td class="text-center">${{ number_format($venta->total,2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if ($cliente->fletes->count() > 0)
    <table class="table table-bordered"  style="margin-top: 20px;">
        <thead>
            <tr>
                <th colspan="7" class="text-center">Fletes pendientes</th>
            </tr>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Motorista</th>
                <th class="text-center">Cabezal</th>
                <th class="text-center">Remolque</th>
                <th class="text-center">Estado</th>
                <th class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cliente->fletes as $flete)
                <tr>
                    <td>{{ $flete->id }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($flete->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $flete->nombre_motorista }}</td>
                    <td class="text-center">{{ $flete->nombre_cabezal }}</td>
                    <td class="text-center">{{ $flete->nombre_remolque }}</td>
                    <td class="text-center">{{ $flete->estado }}</td>
                    <td class="text-right">${{ number_format($flete->total,2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    

</body>
</html>