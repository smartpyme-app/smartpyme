<!DOCTYPE html>
<html>
<head>
    <title>Reporte Libro Diario</title>
    <style media="print"> .no-print{display: none; } </style>
</head>
<style>
    body {
        font-family: Arial, sans-serif;
    }
    .header, .footer {
        text-align: center;
        font-weight: bold;
        margin-bottom: 10px;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .table th, .table td {
        border: 1px solid #000;
        padding: 8px;
        text-align: left;
    }
    .table th {
        background-color: #f2f2f2;
        text-align: center;
    }
    .table .sub-header {
        background-color: #f9f9f9;
        font-weight: bold;
        text-align: left;
        padding: 6px;
    }
    .table .detalles td {
        padding: 6px;
    }
    .text-right {
        text-align: right;
    }
</style>
<body>

<section id="factura">
    <div class="header">
        <h2>Reporte Libro Diario</h2>
        <p>Empresa: {{ $empresa->nombre }}</p>
        <p>Periodo: {{ $month_name }} - {{ $year }}</p>
        <p id="valores_expresados">VALORES ESPRESADOS EN US DOLARES</p>
    </div>

    <div style="page-break-after:auto;">
        <table class="table">
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
                    <td class="id_partida"> PART-{{$partida['partida_num'] }}</td>
                    <td class="correlativo">{{ $partida['correlativo'] }}</td>
                    <td class="fecha_partida">{{ $partida['fecha'] }}</td>
                    <td class="concepto" colspan="6"><strong>{{ $partida['concepto'] }}</strong></td>
                    </td>
                </tr>
                <!-- Detalles de la partida -->
                @foreach ($partida['detalles'] as $detalle)
                    <tr class="detalles">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>{{ $detalle['codigo'] }}</td>
                        <td class="concepto">{{ $detalle['nombre_cuenta'] }}</td>
                        <td>{{ $detalle['concepto'] }}</td>
                        <td class="text-right">{{ number_format($detalle['debe'], 2) }}</td>
                        <td class="text-right">{{ number_format($detalle['haber'], 2) }}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</section>

</body>
</html>
