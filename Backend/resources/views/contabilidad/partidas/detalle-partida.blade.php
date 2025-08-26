<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Partida #{{ $partida->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .info {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .totals-row td {
            font-weight: bold;
            background: #f9f9f9;
        }
        .totals-label {
            text-align: right;
        }
        .totals-debe, .totals-haber {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Partida Contable</h2>
    </div>

    <div class="info">
        <p><strong>Número de Partida:</strong> {{ $partida->id }}</p>
        @if(!empty($partida->correlativo))
            <p><strong>Número de Correlativo:</strong> {{ $partida->correlativo }}</p>
        @endif
        <p><strong>Fecha:</strong> {{ $partida->fecha }}</p>
        <p><strong>Tipo:</strong> {{ $partida->tipo }}</p>
        <p><strong>Concepto:</strong> {{ $partida->concepto }}</p>
        <p><strong>Estado:</strong> {{ $partida->estado }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cuenta</th>
                <th>Concepto</th>
                <th>Debe</th>
                <th>Haber</th>
            </tr>
        </thead>
        <tbody>
            @foreach($partida->detalles as $detalle)
            <tr>
                <td>{{ $detalle->codigo }} - {{ $detalle->nombre_cuenta }}</td>
                <td>{{ $detalle->concepto }}</td>
                <td style="text-align: right;">{{ number_format($detalle->debe, 2) }}</td>
                <td style="text-align: right;">{{ number_format($detalle->haber, 2) }}</td>
            </tr>
            @endforeach
            <tr class="totals-row">
                <td></td>
                <td class="totals-label">Totales:</td>
                <td class="totals-debe">${{ number_format($partida->detalles->sum('debe'), 2) }}</td>
                <td class="totals-haber">${{ number_format($partida->detalles->sum('haber'), 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="totals-label">Diferencia:</td>
                <td colspan="2" style="text-align: right;">
                    ${{ number_format($partida->detalles->sum('debe') - $partida->detalles->sum('haber'), 2) }}
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
