<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Movimientos de caja chica</title>
</head>

<style>
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    *{ font-family: sans-serif; color: #333; }
    @page { margin: 100px 70px 70px 70px; }
    .header { position: fixed; top: -70px;}
    .footer{ position: fixed; bottom: 0px; opacity: .6; }
    .bg{ width: 840px; position: fixed; top: -150px; left: -120px; opacity: .5; z-index: -1;}
    .table {width: 100%; border-collapse: collapse; margin: auto;}
    .table-bordered td, .table-bordered th, .table-bordered td, {border: 0.5px solid gray; padding: 5px 10px; }
    hr{ border: 0.5px solid #D1D0D0; }
    .notas>br:before {content: "*"; color: black; }
    p{ text-align: justify; }
    .badge{ background-color: #1B5FFA; color: white; padding: 5px;}
    .completado{text-decoration:line-through; color: gray;}
    .m-0{ margin:0px; }

</style>

<body>
    {{-- <img class="bg" src="imgs/bg.jpg"> --}}
    <div class="header text-center">
        {{-- <img class="m-0" src="img/empresas/default.png" alt="Logo" width="50"> --}}
        <h2 class="text-center m-0"> {{ $cajachica->nombre_sucursal }} </h2>
        <h3 class="text-center m-0">Movimientos de caja chica</h3>
    </div>
    <div class="footer">
        <hr>
        <h4 class="text-center">
            {{ $cajachica->nombre_sucursal }}
        </h4>
    </div>


    <table class="table table-bordered">
        <tr>
            <td><b>Fecha:</b></td>
            <td><b>Entradas:</b></td>
            <td><b>Salidas:</b></td>
            <td><b>Saldo:</b></td>
        </tr>
        <tr>
            <td>{{ \Carbon\Carbon::today()->format('d/m/Y') }}</td>
            <td>${{ number_format($cajachica->entradas,2) }}</td>
            <td>${{ number_format($cajachica->salidas,2) }}</td>
            <td>${{ number_format($cajachica->saldo,2) }}</td>
        </tr>
    </table>
    @if ($cajachica->descripcion)
        <p>Descripción: {{ $cajachica->descripcion }}</p>
    @endif

    <table class="table table-bordered"  style="margin-top: 20px;">
        <thead>
            <tr>
                <th class="text-center" width="30">N°</th>
                <th width="70">Fecha</th>
                <th width="100">Descripción</th>
                <th width="100">Referencia</th>
                <th class="text-right" width="60">Tipo</th>
                <th class="text-right" width="60">Entrada</th>
                <th class="text-right" width="60">Salida</th>
                <th class="text-right" width="60">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @if ($cajachica->detalles->count() == 0)
                <tr>
                    <td colspan="8" class="text-center">No hay movimientos realizados</td>
                </tr>
            @endif
            @foreach ($cajachica->detalles as $detalles)
                <tr>
                    <td class="text-center">{{ $loop->index + 1 }}</td>
                    <td class="text-center">{{ $detalles->fecha }}</td>
                    <td class="text-center">{{ $detalles->descripcion }}</td>
                    <td class="text-center">{{ $detalles->referencia }}</td>
                    <td class="text-center">{{ $detalles->tipo }}</td>
                    <td class="text-right">${{ number_format($detalles->entrada, 2) }}</td>
                    <td class="text-right">${{ number_format($detalles->salida, 2) }}</td>
                    <td class="text-right">${{ number_format($detalles->saldo, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" class="text-right">Totales:</th>
                <th class="text-right">${{ number_format($cajachica->detalles->sum('entrada'), 2) }}</th>
                <th class="text-right">${{ number_format($cajachica->detalles->sum('salida'), 2) }}</th>
                <th class="text-right">${{ number_format($cajachica->saldo, 2) }}</th>
            </tr>
        </tfoot>
    </table>
    <br><br>
    <p>Hecho por: {{ $cajachica->nombre_usuario }}</p>
    

</body>
</html>