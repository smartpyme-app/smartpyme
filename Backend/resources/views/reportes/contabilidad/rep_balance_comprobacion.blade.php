<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance de Comprobación</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid black; padding: 5px; text-align: right; }
        th { background-color: #f2f2f2; text-align: center; }
        .nivel-0 { font-weight: bold; font-size: 14px; background-color: #d1e7fd; }
        .nivel-1 { font-weight: bold; background-color: #f8f9fa; }
        .nivel-2 { font-style: italic; }
        .nivel-3, .nivel-4 { padding-left: 20px; }
    </style>
</head>
<body>
<h2>Balance de Comprobación - {{ $month_name }} {{ $year }}</h2>
<h4>Empresa: {{ $empresa->nombre }}</h4>

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
