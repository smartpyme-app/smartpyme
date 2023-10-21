<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orden de carga</title>
</head>

<style>
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    *{ font-family: sans-serif; color: #333; margin:0px; padding: 0px;}
    @page { margin: 130px 70px 70px 70px !important; }
    .header { position: fixed; top: -90px;}
    .footer{ position: fixed; bottom: 0px; opacity: .6; }
    .bg{ width: 840px; position: fixed; top: -150px; left: -120px; opacity: .5; z-index: -1;}
    .table {width: 100%; border-collapse: collapse; margin: auto;}
    .table-bordered td, .table-bordered th, .table-bordered td, {border: 0.5px solid gray; padding: 5px 10px; font-size: 14px; }
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
        <h2 class="text-center"> Orden de carga </h2>
    </div>
    <div class="footer">
        <h4 class="text-center"> {{ $flete->sucursal->empresa->nombre }} </h4> 
    </div>



    <table class="table table-bordered">
        <tr style="background: lightgray;"><td colspan="4"><h3 class="text-center">Datos Transporte</h3></td> </tr>
        <tr>
            <td><b>Nombre:</b></td>
            <td>{{ $flete->sucursal->empresa->nombre }}</td>
            <td><b>Correo:</b></td>
            <td>{{ $flete->sucursal->empresa->correo }}</td>
        </tr>
        <tr style="background: lightgray;"><td colspan="4"><h3 class="text-center">Datos Piloto</h3></td> </tr>
        <tr>
            <td><b>Nombre:</b></td>
            <td>{{ $flete->motorista()->pluck('nombre')->first() }}</td>
            <td><b>Nacionalidad:</b></td>
            <td>{{ $flete->motorista()->pluck('nacionalidad')->first() }}</td>
        </tr>
        <tr>
            <td><b>Tipo licencia:</b></td>
            <td>{{ $flete->motorista()->pluck('tipo_licencia')->first() }}</td>
            <td><b>N° de licencia:</b></td>
            <td>{{ $flete->motorista()->pluck('num_licencia')->first() }}</td>
        </tr>
        <tr>
            <td><b>Pais de identificación:</b></td>
            <td>{{ $flete->motorista()->pluck('pais')->first() }}</td>
            <td><b>Fecha de nacimiento:</b></td>
            <td>{{ Carbon\Carbon::parse($flete->motorista()->pluck('fecha_nacimiento')->first())->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><b>Vencimiento licencia:</b></td>
            <td>{{ Carbon\Carbon::parse($flete->motorista()->pluck('fecha_vencimiento')->first())->format('d/m/Y') }}</td>
            <td><b>Pais de nacimiento:</b></td>
            <td>{{ $flete->motorista()->pluck('pais')->first() }}</td>
        </tr>
        <tr style="background: lightgray;"><td colspan="4"><h3 class="text-center">Datos del transporte</h3></td> </tr>
        <tr>
            <td><b>Pais de Origen:</b></td>
            <td>{{ $flete->punto_origen }}</td>
            <td><b>Placa cabezal:</b></td>
            <td>{{ $flete->cabezal()->pluck('placa')->first() }}</td>
        </tr>
        <tr>
            <td><b>Placa remolque:</b></td>
            <td>{{ $flete->remolque()->pluck('placa')->first() }}</td>
            <td><b>Marca Furgon:</b></td>
            <td>{{ $flete->cabezal()->pluck('marca')->first() }}</td>
        </tr>
        <tr>
            <td><b>Modelo:</b></td>
            <td>{{ $flete->cabezal()->pluck('modelo')->first() }}</td>
            <td><b>Color y Año:</b></td>
            <td>{{ $flete->cabezal()->pluck('color')->first() }} {{ $flete->cabezal()->pluck('anio')->first() }}</td>
        </tr>
        <tr style="background: lightgray;"><td colspan="4"><h3 class="text-center">Datos de la carga</h3></td> </tr>
        <tr>
            <td><b>Cliente:</b></td>
            <td colspan="3">{{ $flete->cliente()->pluck('nombre')->first() }}</td>
        </tr>
        <tr>
            <td><b>Destino:</b></td>
            <td>El Salvador</td>
            <td><b>Número de pedido:</b></td>
            <td>{{ $flete->num_pedido }}</td>
        </tr>
        <tr>
            <td><b>Aduana de entrada:</b></td>
            <td>{{ $flete->aduana_entrada }}</td>
            <td><b>Aduana de salida:</b></td>
            <td>{{ $flete->aduana_salida }}</td>
        </tr>
        <tr>
            <td><b>Valor del flete:</b></td>
            <td>${{ $flete->no_sujeto }}</td>
            <td><b>Seguro:</b></td>
            <td>0.32%</td>
        </tr>
        <tr style="background: lightgray;"><td colspan="4"><h3 class="text-center">Datos de la carga</h3></td> </tr>
        <tr>
            <td><b>Fecha de carga:</b></td>
            <td>{{ Carbon\Carbon::parse($flete->fecha_carga)->format('d/m/Y') }}</td>
            <td><b>Hora de carga:</b></td>
            <td>{{ Carbon\Carbon::parse($flete->fecha_carga)->format('H:i:s a') }}</td>
        </tr>
        <tr style="background: lightgray;"><td colspan="4"><h3 class="text-center">Datos para solicitar Carta porte y manifiesto de carga</h3></td> </tr>
        <tr>
            <td><b>Correo:</b></td>
            <td colspan="3">{{ $flete->sucursal->empresa->correo }}</td>
        </tr>
    </table>

    @if ($flete->nota)
        <p>Nota: {{ $flete->nota }}</p>
    @endif

</body>
</html>
