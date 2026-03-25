<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas y Clientes Perdidos - SmartPyme</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.5;
            color: #333;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 20px;
        }
        h1 {
            color: #1a365d;
            margin: 0 0 8px;
            font-size: 24px;
        }
        .subtitle {
            color: #718096;
            margin: 0 0 24px;
            font-size: 14px;
        }
        .filtros {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        .filtros label {
            display: block;
            font-size: 12px;
            color: #718096;
            margin-bottom: 4px;
        }
        .filtros input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-primary {
            background: #3182ce;
            color: white;
        }
        .btn-primary:hover { background: #2c5282; }
        .btn-success {
            background: #38a169;
            color: white;
        }
        .btn-success:hover { background: #276749; }
        .resumen {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .resumen-item {
            background: #edf2f7;
            padding: 16px;
            border-radius: 6px;
            text-align: center;
        }
        .resumen-num {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
        }
        .resumen-label {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }
        .cliente-grupo {
            margin-bottom: 28px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .cliente-header {
            background: #ebf8ff;
            padding: 12px 16px;
            font-weight: 600;
            color: #2c5282;
            border-bottom: 1px solid #e2e8f0;
        }
        .cliente-header .cliente-id { color: #718096; font-weight: 400; font-size: 13px; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f7fafc;
            font-weight: 600;
            font-size: 12px;
            color: #4a5568;
        }
        tr:hover { background: #f7fafc; }
        .text-right { text-align: right; }
        .clientes-section h2 {
            margin: 0 0 16px;
            color: #2d3748;
            font-size: 18px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 12px; }
        .total-row {
            font-weight: 600;
            background: #f7fafc;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Ventas y Clientes Perdidos</h1>
            <p class="subtitle">Ventas registradas en sp_nova que no existen en vps (11-12 feb 2026)</p>

            <form method="get" action="{{ url('/api/ventas-perdidas') }}" class="filtros">
                <div>
                    <label>Fecha inicio</label>
                    <input type="date" name="fecha_inicio" value="{{ $fechaInicio }}" required>
                </div>
                <div>
                    <label>Fecha fin</label>
                    <input type="date" name="fecha_fin" value="{{ $fechaFin }}" required>
                </div>
                <div>
                    <label>ID Empresa (opcional)</label>
                    <input type="number" name="id_empresa" value="{{ $idEmpresa ?? '' }}" placeholder="Ej: 427" min="1">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
                @if(count($datos['ventas_perdidas']) > 0 || count($datos['clientes_perdidos']) > 0)
                <div>
                    @php
                        $excelParams = ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin];
                        if (!empty($idEmpresa)) { $excelParams['id_empresa'] = $idEmpresa; }
                    @endphp
                    <a href="{{ url('/api/ventas-perdidas/excel?' . http_build_query($excelParams)) }}" class="btn btn-success">
                        Descargar Excel
                    </a>
                </div>
                @endif
            </form>

            <div class="resumen">
                <div class="resumen-item">
                    <div class="resumen-num">{{ count($datos['ventas_perdidas']) }}</div>
                    <div class="resumen-label">Ventas perdidas</div>
                </div>
                <div class="resumen-item">
                    <div class="resumen-num">{{ count($datos['clientes_perdidos']) }}</div>
                    <div class="resumen-label">Clientes perdidos</div>
                </div>
                <div class="resumen-item">
                    <div class="resumen-num">{{ array_sum(array_map(function($g) { return count($g['ventas']); }, $datos['ventas_por_cliente'])) ?: 0 }}</div>
                    <div class="resumen-label">Total ventas</div>
                </div>
            </div>

            @if(count($datos['ventas_perdidas']) === 0 && count($datos['clientes_perdidos']) === 0)
                <div class="empty-state">
                    <div class="icon">✓</div>
                    <p><strong>No se encontraron datos perdidos</strong></p>
                    <p>En el rango {{ $fechaInicio }} a {{ $fechaFin }} no hay ventas ni clientes en sp_nova que no existan en vps.</p>
                </div>
            @else
                <div class="clientes-section">
                    <h2>Ventas perdidas agrupadas por cliente</h2>

                    @php
                        $ventasOrdenadas = $datos['ventas_por_cliente'];
                        usort($ventasOrdenadas, function ($a, $b) {
                            $vA = $a['ventas'][0]['venta'] ?? null;
                            $vB = $b['ventas'][0]['venta'] ?? null;
                            if (!$vA || !$vB) return 0;
                            if ($vA->id_empresa !== $vB->id_empresa) return $vA->id_empresa - $vB->id_empresa;
                            return ($vA->id_sucursal ?? 0) - ($vB->id_sucursal ?? 0);
                        });
                        foreach ($ventasOrdenadas as &$grupo) {
                            usort($grupo['ventas'], fn($x, $y) => ($x['venta']->id_empresa <=> $y['venta']->id_empresa) ?: (($x['venta']->id_sucursal ?? 0) <=> ($y['venta']->id_sucursal ?? 0)));
                        }
                    @endphp
                    @foreach($ventasOrdenadas as $grupo)
                    <div class="cliente-grupo">
                        <div class="cliente-header">
                            {{ $grupo['ventas'][0]['venta']->nombre_empresa ?? '' }} / {{ $grupo['ventas'][0]['venta']->nombre_sucursal ?? '' }} — Cliente ID {{ $grupo['id_cliente'] }}: {{ $grupo['nombre_cliente'] }}
                            <span class="cliente-id">({{ count($grupo['ventas']) }} venta(s))</span>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Sucursal</th>
                                    <th>ID Venta</th>
                                    <th>Fecha</th>
                                    <th>Correlativo</th>
                                    <th class="text-right">Total</th>
                                    <th>Estado</th>
                                    <th>Forma pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $subtotal = 0; @endphp
                                @foreach($grupo['ventas'] as $item)
                                @php $v = $item['venta']; $subtotal += $v->total; @endphp
                                <tr>
                                    <td>{{ $v->nombre_empresa ?? '-' }}</td>
                                    <td>{{ $v->nombre_sucursal ?? '-' }}</td>
                                    <td>{{ $v->id }}</td>
                                    <td>{{ $v->fecha }}</td>
                                    <td>{{ $v->correlativo ?? '-' }}</td>
                                    <td class="text-right">${{ number_format($v->total, 2) }}</td>
                                    <td>{{ $v->estado ?? '-' }}</td>
                                    <td>{{ $v->forma_pago ?? '-' }}</td>
                                </tr>
                                @endforeach
                                <tr class="total-row">
                                    <td colspan="5">Subtotal cliente</td>
                                    <td class="text-right">${{ number_format($subtotal, 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @endforeach
                </div>

                @if(count($datos['clientes_perdidos']) > 0)
                <div class="clientes-section" style="margin-top: 32px;">
                    <h2>Clientes perdidos (no existen en vps)</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre / Empresa</th>
                                <th>NIT</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($datos['clientes_perdidos'] as $c)
                            <tr>
                                <td>{{ $c->id }}</td>
                                <td>{{ $c->tipo === 'Empresa' ? ($c->nombre_empresa ?? '-') : trim(($c->nombre ?? '') . ' ' . ($c->apellido ?? '')) }}</td>
                                <td>{{ $c->nit ?? '-' }}</td>
                                <td>{{ $c->telefono ?? '-' }}</td>
                                <td>{{ $c->correo ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            @endif
        </div>
    </div>
</body>
</html>
