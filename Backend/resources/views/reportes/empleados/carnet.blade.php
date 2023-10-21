<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Asistencia</title>
</head>

<style>
    *{ font-family: sans-serif; color: #333; margin:0px; padding:0px; line-height: 12px;}
    body{ width: 100%; height: 100%; }
    .card{ position: relative; width: 4.5cm; height: 7cm; padding: 20px;}
    .text-center { text-align: center !important; }
    #logo {width: 30px }
    #foto {width: 90px; border-radius: 40px 40px 40px 40px; display: inline-block;}
    #qrcode {width: 40px }
    h1{font-size: 16px;}
    h2{font-size: 14px;}
    h3{font-size: 12px;}
    p{font-size: 10px;}
    #bg{ width: 5.5cm; height: 8.5cm; position: fixed; top: 0px; left: 0px; opacity: .2; z-index: -1;}
</style>

<body>
    <img id="bg" src="img/bg-carnet.jpg" alt="bg">
    <div class="card text-center">
        <p><img id="logo" src="img/{{$empleado->sucursal()->first()->empresa()->first()->logo }}" alt="Logo"></p>
        <h3>{{ $empleado->sucursal()->first()->empresa()->first()->nombre }} </h3>
        <br>
        <p><img id="foto" src="img/{{$empleado->img }}" alt="Logo"></p>
        <br>
        <p>{!! '<img id="qrcode" src="data:image/png;base64,' . DNS2D::getBarcodePNG('123', 'QRCODE', 10, 10) . '" alt="barcode"   />' !!}</p>
        <br>
        <h1>{{ $empleado->nombre }}</h1>
        <h3>{{ $empleado->nombre_cargo }}</h3>

        <p style="position: absolute; bottom: 0px; width: 100%;">ID: {{ $empleado->id }} <span style="margin: 0px 30px; display: inline-block;"></span> Año: {{ date('Y') }}</p>
    </div>
</body>
</html>
