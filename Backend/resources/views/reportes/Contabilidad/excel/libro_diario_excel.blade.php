<!DOCTYPE html>
<html>
<head>
    <title>Reporte Libro Diario</title>
    <style>
        /* Estilos básicos para mejorar el aspecto del Excel */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
<table class="table" width="100%">
    <thead>
    <tr>
        <th colspan="7" style="text-align: center; font-size: 16px;"><strong>Reporte Libro Diario</strong></th>
    </tr>
    <tr>
        <th colspan="7" style="text-align: center; font-size: 16px;"><strong>Empresa: {{ $empresa->nombre }}</strong>
        </th>
    </tr>
    <tr>
        <th colspan="7" style="text-align: center; font-size: 16px;"><strong>Periodo: {{ $fechaInicio }}- {{ $fechaFin }}</strong></th>
    </tr>
    <tr>
        <th colspan="7" style="text-align: center; font-size: 16px;"><strong>VALORES ESPRESADOS EN US DOLARES</strong>
        </th>
    </tr>
    <tr></tr>
    </thead>
    <tbody>
    <table class="table" width="100%">
        <thead>
        <tr>
            <th><strong>ID Partida</strong></th>
            <th><strong>Fecha</strong></th>
            <th><strong>Concepto</strong></th>
            <th><strong>Código Cuenta</strong></th>
            <th><strong>Nombre Cuenta</strong></th>
            <th><strong>Debe</strong></th>
            <th><strong>Haber</strong></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($reporteLibroDiario as $partida)
            {{-- Fila de partida principal --}}
            <tr>
                <td>PART-{{ $partida['partida_num'] }}</td>
                <td>{{ $partida['fecha'] }}</td>
                <td>{{ $partida['concepto'] }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>

            {{-- Detalles de cada partida --}}
            @foreach ($partida['detalles'] as $detalle)
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>{{ $detalle['codigo'] }}</td>
                    <td>{{ $detalle['nombre_cuenta'] }}</td>
                    <td>{{ number_format($detalle['debe'], 2) }}</td>
                    <td>{{ number_format($detalle['haber'], 2) }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
    </tbody>
</table>
</body>
</html>
