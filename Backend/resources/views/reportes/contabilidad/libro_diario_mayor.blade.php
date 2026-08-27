<!DOCTYPE html>
<html>
<head>
    <title>Reporte Libro Diario Mayor</title>
    <style>
        body { font-family: Helvetica, sans-serif; font-size: 9px; }
        .header { text-align: center; font-weight: bold; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #000; padding: 3px; }
        th { background-color: #f2f2f2; text-align: center; }
        .cuenta { text-align: left; background: none; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Reporte Libro Diario Mayor</h2>
        <p>{{ $empresa->nombre }}</p>
        <p>Periodo: {{ $month_name }} - {{ $year }}</p>
        <p>VALORES EXPRESADOS EN US DOLARES</p>
    </div>
    @foreach($cuentas as $cuenta)
        <table>
            <tr>
                <th class="cuenta" colspan="7">Cuenta: {{ $cuenta->cuenta }} {{ $cuenta->nombre }}</th>
            </tr>
            <tr>
                <th>Partida</th>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Cargo</th>
                <th>Abono</th>
                <th>Saldo</th>
            </tr>
            <tr>
                <td colspan="4">Saldo inicial:</td>
                <td class="num">0.00</td>
                <td class="num">0.00</td>
                <td class="num">{{ number_format($cuenta->saldo_anterior ?? 0, 2) }}</td>
            </tr>
            @foreach($cuenta->detalles as $detalle)
                <tr>
                    <td>PART-{{ $detalle->id_partida }}</td>
                    <td>{{ $detalle->partida->correlativo ?? '' }}</td>
                    <td>{{ $detalle->partida->fecha ?? $detalle->created_at }}</td>
                    <td>{{ $detalle->concepto }}</td>
                    <td class="num">{{ number_format($detalle->debe ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($detalle->haber ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($detalle->saldo_calculado ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="4">Total por cuenta:</th>
                <th class="num">{{ number_format($cuenta->cargo ?? 0, 2) }}</th>
                <th class="num">{{ number_format($cuenta->abono ?? 0, 2) }}</th>
                <th class="num">{{ number_format($cuenta->saldo_actual ?? 0, 2) }}</th>
            </tr>
        </table>
    @endforeach
</body>
</html>
