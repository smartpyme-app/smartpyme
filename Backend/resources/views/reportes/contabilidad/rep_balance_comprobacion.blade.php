<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance de Comprobación</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            position: relative;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid black; padding: 5px; text-align: right; }
        th { background-color: #f2f2f2; text-align: center; }
    </style>
</head>
<body>
<div class="header">
    <p id="empresa_nombre">{{$empresa->nombre}}</p>
    <h2 id="titulo_balance">Balance de Comprobación</h2>
    <p id="periodo">Periodo: {{$month_name}} - {{$year}}</p>
    <p id="c_costos">Todos los Centros de Costos</p>
    <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>
    <p id="naturaleza">ACTIVOS Y GASTOS</p>
</div>

<table>
    <thead>
    <tr>
        <th>Código</th>
        <th>Nombre</th>
        <th>Saldo Inicial</th>
        <th>Debe</th>
        <th>Haber</th>
        <th>Saldo Final</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($balance as $cuenta)
        <tr class="nivel-{{ $cuenta['nivel'] }}">
            <td style="text-align: left;">{{ $cuenta['codigo'] }}</td>
            <td style="text-align: left;">{{ $cuenta['nombre'] }}</td>
            <td>{{ number_format($cuenta['saldo_inicial'], 2) }}</td>
            <td>{{ number_format($cuenta['debe'], 2) }}</td>
            <td>{{ number_format($cuenta['haber'], 2) }}</td>
            <td>{{ number_format($cuenta['saldo_final'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
