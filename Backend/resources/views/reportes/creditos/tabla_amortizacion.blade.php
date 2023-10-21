<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TABLA DE AMORTIZACIÓN DE PRESTAMO</title>
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
        <h2 class="text-center m-0"> {{ $credito->nombre_empresa }} </h2>
        <h3 class="text-center m-0">TABLA DE PAGOS DE PRESTAMO</h3>
    </div>
    <div class="footer">
        <hr>
        <h4 class="text-center">
            {{ $credito->nombre_empresa }}
        </h4>
    </div>


    <table class="table table-bordered">
        <tr>
            <td><b>Fecha:</b></td>
            <td><b>Monto:</b></td>
            <td><b>Cuotas:</b></td>
            <td><b>Forma de pago:</b></td>
            <td><b>Interés anual:</b></td>
            <td><b>Cliente:</b></td>
        </tr>
        <tr>
            <td>{{ \Carbon\Carbon::parse($credito->fecha)->format('d/m/Y') }}</td>
            <td>${{ $credito->total }}</td>
            <td>{{ $credito->numero_de_cuotas }}</td>
            <td>{{ $credito->forma_de_pago }}</td>
            <td>{{ number_format($credito->interes,2) }}%</td>
            <td>{{ \Illuminate\Support\Str::limit($credito->nombre_cliente, 20, $end = '.') }}</td>
        </tr>
    </table>
    @if ($credito->nota)
        <p>Nota: {{ $credito->nota }}</p>
    @endif

    <table class="table table-bordered"  style="margin-top: 20px;">
        <thead>
            <tr>
                <th class="text-center" width="30">N°</th>
                <th class="text-left" width="100">Fecha de pago</th>
                <th class="text-right" width="80">Saldo inicial</th>
                <th class="text-left">Cuota</th>
                <th class="text-right" width="70">Intereses</th>
                <th class="text-right" width="70">Abono a capital</th>
                <th class="text-right" width="70">Saldo del Crédito</th>
            </tr>
        </thead>
        <tbody>
            @if ($credito->pagos->count() == 0)
                <tr>
                    <td colspan="7" class="text-center">No hay pagos realizados</td>
                </tr>
            @endif
            @foreach ($credito->pagos as $pago)
                <tr>
                    <td class="text-center">{{ $loop->index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($pago['fecha'])->format('d/m/Y') }}</td>
                    <td class="text-right">${{ number_format($pago['saldo_inicial'], 2) }}</td>
                    <td>${{ number_format($pago['cuota'], 2) }}</td>
                    <td class="text-right">${{ number_format($pago['interes'], 2) }}</td>
                    <td class="text-right">${{ number_format($pago['abono'], 2) }}</td>
                    <td class="text-right">${{ number_format($pago['saldo_final'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-right">Totales:</th>
                <th class="text-right">${{ number_format($credito->pagos->sum('abono'), 2) }}</th>
                <th class="text-right">${{ number_format($credito->pagos->sum('interes'), 2) }}</th>
                <th class="text-right">${{ number_format($credito->pagos->sum('abono') + $credito->pagos->sum('interes'), 2) }}</th>
            </tr>
        </tfoot>
    </table>
    <br><br>
    <p>Hecho por: {{ $credito->nombre_usuario }}</p>
    

</body>
</html>
