<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Boletas de pago</title>
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
    .table {width: 100%; border-collapse: collapse; margin: 0px auto 0px auto;}
    .table-bordered td, .table-bordered th, .table-bordered td, {border: 0.5px solid gray; padding: 5px 10px; }
    hr{ border: 0.5px solid #D1D0D0; }
    .notas>br:before {content: "*"; color: black; }
    p{ text-align: justify; }
    .badge{ background-color: #1B5FFA; color: white; padding: 5px;}
    .completado{text-decoration:line-through; color: gray;}

</style>

<body>
    @foreach ($planilla->detalles as $key => $detalle)
    <div class="text-center">
        <h2 class="text-center" style="margin-bottom: 0px;">
            {{ $planilla->empresa->nombre }}<br>
            <small>Boleta de pago</small>
        </h2>
        <p class="text-center">
            Planilla de sueldos del 
            {{ Carbon\Carbon::parse($planilla->fecha_inicio)->isoformat('D [de] MMMM') }}
            al
            {{ Carbon\Carbon::parse($planilla->fecha_fin)->isoformat('D [de] MMMM [del] Y') }}
        </p>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Nombre:{{ $detalle->nombre_empleado }}</th>
                <th>Dias: {{ $detalle->dias }}</th>
                <th>Horas: {{ $detalle->horas }}</th>
                <th>Horas Extras: {{ $detalle->horas_extras }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <br>Sueldo:
                    <br>Extras:
                    <br>Otos:
                </td>
                <td>
                    <br>${{ number_format($detalle->sueldo, 2) }}
                    <br>${{ number_format($detalle->extras, 2) }}
                    <br>${{ number_format($detalle->otros, 2) }}
                </td>
                <td>
                    <br>Renta:
                    <br>ISS:
                    <br>AFP:
                    <br>TOTAL:
                </td>
                <td>
                    <br>${{ number_format($detalle->renta, 2) }}
                    <br>${{ number_format($detalle->isss, 2) }}
                    <br>${{ number_format($detalle->afp, 2) }}
                    <br>${{ number_format($detalle->total, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <hr style="margin-top:20px;">
    @endforeach


</body>
</html>
