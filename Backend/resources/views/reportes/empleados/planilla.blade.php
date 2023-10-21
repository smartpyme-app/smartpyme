<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Planilla</title>
</head>

<style>
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    *{ font-family: sans-serif; color: #333; }
    @page { margin: 70px 70px; }
    #logo { position: fixed; top: -20px; left: 0px; width: 80px }
    .header { position: fixed; top: -40px; opacity: .7;  }
    .footer{ position: fixed; bottom: 0px; opacity: .7; }
    .firma{ position: fixed; bottom: 50px; text-align: center;}
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
    {{-- <img class="bg" src="imgs/bg.jpg"> --}}
    <img id="logo" src="img/{{$planilla->empresa->logo }}" alt="Logo">
    <div class="header text-center">
        <h2 class="text-center" style="margin-bottom: 0px;">
            {{ $planilla->empresa->nombre }}<br>
            <small>PLANILLA DE EMPLEADOS</small>
        </h2>
        <p class="text-center">
            Planilla de sueldos del 
            {{ Carbon\Carbon::parse($planilla->fecha_inicio)->isoformat('D [de] MMMM') }}
            al
            {{ Carbon\Carbon::parse($planilla->fecha_fin)->isoformat('D [de] MMMM [del] Y') }}
        </p>
    </div>
    <div class="footer">
        <hr>
        <h4 class="text-center">
            {{ $planilla->empresa->nombre }} | {{ $planilla->empresa->correo }} | {{ $planilla->empresa->telefono }}
        </h4>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th rowspan="2" class="text-center">N°</th>
                <th rowspan="2" class="text-center">Empleado</th>
                <th rowspan="2" class="text-center">Dias</th>
                {{-- <th rowspan="2" class="text-center">Horas</th> --}}
                {{-- <th rowspan="2" class="text-center">Horas <br> Extras</th> --}}
                <th colspan="3" class="text-center">Ingresos</th>
                <th colspan="3" class="text-center">Descuentos</th>
                <th rowspan="2" class="text-center">Total <br> a pagar</th>
                <th width="80" rowspan="2" class="text-center">Firma</th>
            </tr>
            <tr>
                <th class="text-center">Sueldo</th>
                <th class="text-center">Extras</th>
                <th class="text-center">Otros</th>
                <th class="text-center">Renta</th>
                <th class="text-center">ISSS</th>
                <th class="text-center">AFP</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($planilla->detalles as $detalle)
            <tr>
                <td class="text-center"> {{ $loop->index + 1 }}</td>
                <td> {{ $detalle->nombre_empleado }} </td>
                <td class="text-center">{{ $detalle->dias }}</td>
                {{-- <td class="text-center">{{ $detalle->horas }}</td> --}}
                {{-- <td class="text-center">{{ $detalle->horas_extras }}</td> --}}
                <td class="text-center">${{ number_format($detalle->sueldo, 2) }}</td>
                <td class="text-center">${{ number_format($detalle->extras, 2) }}</td>
                <td class="text-center">${{ number_format($detalle->otros, 2) }}</td>
                <td class="text-center">${{ number_format($detalle->renta, 2) }}</td>
                <td class="text-center">${{ number_format($detalle->isss, 2) }}</td>
                <td class="text-center">${{ number_format($detalle->afp, 2) }}</td>
                <td class="text-center">${{ number_format($detalle->total, 2) }}</td>
                <td class="text-center"> </td>
            </tr>
            @endforeach
            <tr>
                <td colspan="3" class="text-right"><b>Total:</b></td>
                <td class="text-center"><b>${{ number_format( $planilla->sueldo, 2) }}</b></td>
                <td class="text-center"><b>${{ number_format( $planilla->extras, 2) }}</b></td>
                <td class="text-center"><b>${{ number_format( $planilla->otros, 2) }}</b></td>
                <td class="text-center"><b>${{ number_format( $planilla->renta, 2) }}</b></td>
                <td class="text-center"><b>${{ number_format( $planilla->isss, 2) }}</b></td>
                <td class="text-center"><b>${{ number_format( $planilla->afp, 2) }}</b></td>
                <td class="text-center"><b>${{ number_format( $planilla->total, 2) }}</b></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <p class="firma">
        Firma:________________________________
        <br> {{ $planilla->empresa->propietario }}
    </p>

</body>
</html>
