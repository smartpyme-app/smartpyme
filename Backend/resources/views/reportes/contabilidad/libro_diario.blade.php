<!DOCTYPE html>
<html>
<head>
    <title>Reporte Libro Diario</title>
    <style>
        body { font-family: Helvetica, sans-serif; font-size: 9px; }
        .header { text-align: center; font-weight: bold; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 3px; }
        th { background-color: #f2f2f2; text-align: center; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Reporte Libro Diario</h2>
        <p>Empresa: {{ $empresa->nombre }}</p>
        <p>Periodo: {{ $month_name }} - {{ $year }}</p>
        <p>VALORES EXPRESADOS EN US DOLARES</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID Partida</th>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Código Cuenta</th>
                <th>Nombre Cuenta</th>
                <th>Concepto/Detalle</th>
                <th>Debe</th>
                <th>Haber</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($reporteLibroDiario as $partida)
            <tr>
                <td>PART-{{ $partida->id }}</td>
                <td>{{ $partida->correlativo }}</td>
                <td>{{ $partida->fecha }}</td>
                <td colspan="6"><strong>{{ $partida->concepto }}</strong></td>
            </tr>
            @foreach ($partida->detalles as $detalle)
                <tr>
                    <td colspan="4"></td>
                    <td>{{ $detalle->codigo }}</td>
                    <td>{{ $detalle->nombre_cuenta }}</td>
                    <td>{{ $detalle->concepto }}</td>
                    <td class="num">{{ number_format($detalle->debe, 2) }}</td>
                    <td class="num">{{ number_format($detalle->haber, 2) }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</body>
</html>
