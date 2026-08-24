<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Boleta de Pago</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            font-size: 11px; 
            color: #333;
            line-height: 1.3;
        }
        .boleta {
            padding: 10px;
        }
        .header { 
            text-align: center; 
            margin-bottom: 15px; 
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
        }
        .header h2 {
            margin: 0 0 4px 0;
            font-size: 16px;
            color: #2c3e50;
            text-transform: uppercase;
        }
        .header h3 {
            margin: 0 0 4px 0;
            font-size: 13px;
            color: #555;
            letter-spacing: 1px;
        }
        .header p {
            margin: 0;
            font-size: 11px;
            color: #777;
        }
        .employee-info { 
            margin-bottom: 15px; 
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 8px 12px;
        }
        .employee-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .employee-info td {
            padding: 3px 6px;
            font-size: 11px;
            border: none;
        }
        .details { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 15px; 
        }
        .details th { 
            background-color: #2c3e50; 
            color: #ffffff;
            border: 1px solid #2c3e50; 
            padding: 6px 8px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .details td { 
            border: 1px solid #ddd; 
            padding: 5px 8px;
            font-size: 10.5px;
        }
        .text-end {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-container {
            width: 100%;
            margin-bottom: 25px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px 8px;
            font-size: 11px;
            border: 1px solid #ddd;
        }
        .signature-section {
            margin-top: 40px;
            width: 100%;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 220px;
            text-align: center;
            margin: 0 auto;
            padding-top: 5px;
            font-size: 11px;
        }
        .page-break { 
            page-break-after: always; 
        }
    </style>
</head>
<body>
    @php
        $simbolo = \App\Helpers\CurrencyHelper::symbol($empresa);
    @endphp
    @foreach($detalles as $detalle)
    @php
        $ingresos = [];
        $salarioDevengado = (float)($detalle->salario_devengado ?? $detalle->salario_base);
        $diasLaborados = $detalle->dias_laborados ?? 30;
        $labelSalario = 'Salario Devengado';
        if ($diasLaborados) {
            $labelSalario .= " ({$diasLaborados} días)";
        }
        $ingresos[] = [
            'concepto' => $labelSalario,
            'monto' => $salarioDevengado
        ];

        if ((float)($detalle->monto_horas_extra ?? 0) > 0) {
            $hrsText = (float)($detalle->horas_extra ?? 0) > 0 ? ' (' . number_format($detalle->horas_extra, 1) . ' hrs)' : '';
            $ingresos[] = [
                'concepto' => 'Horas Extra' . $hrsText,
                'monto' => (float)$detalle->monto_horas_extra
            ];
        }

        if ((float)($detalle->comisiones ?? 0) > 0) {
            $ingresos[] = [
                'concepto' => 'Comisiones',
                'monto' => (float)$detalle->comisiones
            ];
        }

        if ((float)($detalle->bonificaciones ?? 0) > 0) {
            $ingresos[] = [
                'concepto' => 'Bonos / Bonificaciones',
                'monto' => (float)$detalle->bonificaciones
            ];
        }

        if ((float)($detalle->otros_ingresos ?? 0) > 0) {
            $ingresos[] = [
                'concepto' => 'Otros Ingresos',
                'monto' => (float)$detalle->otros_ingresos
            ];
        }

        if ((float)($detalle->abonos ?? 0) > 0) {
            $tagRetencion = ($detalle->abonos_sin_retencion ?? true) ? ' (Sin retención)' : '';
            $ingresos[] = [
                'concepto' => 'Otros Abonos' . $tagRetencion,
                'monto' => (float)$detalle->abonos
            ];
        }

        $deducciones = [];
        $esCR = ($detalle->pais_configuracion ?? 'SV') === 'CR';
        if (!$esCR && (float)($detalle->isss_empleado ?? 0) > 0) {
            $deducciones[] = [
                'concepto' => 'ISSS (3%)',
                'monto' => (float)$detalle->isss_empleado
            ];
        }

        if (!$esCR && (float)($detalle->afp_empleado ?? 0) > 0) {
            $deducciones[] = [
                'concepto' => 'AFP (7.25%)',
                'monto' => (float)$detalle->afp_empleado
            ];
        }

        if ((float)($detalle->renta ?? 0) > 0) {
            $deducciones[] = [
                'concepto' => $esCR ? 'Renta CR' : 'Renta (ISR)',
                'monto' => (float)$detalle->renta
            ];
        }

        if ((float)($detalle->prestamos ?? 0) > 0) {
            $deducciones[] = [
                'concepto' => 'Préstamos',
                'monto' => (float)$detalle->prestamos
            ];
        }

        if ((float)($detalle->anticipos ?? 0) > 0) {
            $deducciones[] = [
                'concepto' => 'Anticipos',
                'monto' => (float)$detalle->anticipos
            ];
        }

        if ((float)($detalle->descuentos_judiciales ?? 0) > 0) {
            $deducciones[] = [
                'concepto' => 'Descuentos Judiciales',
                'monto' => (float)$detalle->descuentos_judiciales
            ];
        }

        if ((float)($detalle->otros_descuentos ?? 0) > 0) {
            $deducciones[] = [
                'concepto' => 'Otros Descuentos',
                'monto' => (float)$detalle->otros_descuentos
            ];
        }

        if (!empty($detalle->conceptos_personalizados) && is_array($detalle->conceptos_personalizados)) {
            foreach ($detalle->conceptos_personalizados as $cp) {
                if (($cp['tipo'] ?? '') === 'deduccion' && (float)($cp['valor'] ?? 0) > 0) {
                    $deducciones[] = [
                        'concepto' => $cp['nombre'] ?? 'Deducción',
                        'monto' => (float)$cp['valor']
                    ];
                }
            }
        }

        $maxRows = max(count($ingresos), count($deducciones));
        $totalIngresosCalculado = array_sum(array_column($ingresos, 'monto'));
        $totalDeduccionesCalculado = array_sum(array_column($deducciones, 'monto'));
        $sueldoNetoCalculado = (float)($detalle->sueldo_neto ?? ($totalIngresosCalculado - $totalDeduccionesCalculado));
        $viaticosMonto = (float)($detalle->viaticos ?? 0);
        $totalPagarCalculado = $sueldoNetoCalculado + $viaticosMonto;
    @endphp
    <div class="boleta">
        <div class="header">
            <h2>{{ $empresa->nombre }}</h2>
            <h3>BOLETA DE PAGO</h3>
            <p>
                <strong>Planilla:</strong> {{ $planilla->codigo }} &nbsp;|&nbsp; 
                <strong>Período:</strong> {{ date('d/m/Y', strtotime($planilla->fecha_inicio)) }} - {{ date('d/m/Y', strtotime($planilla->fecha_fin)) }}
                @if($planilla->tipo_planilla)
                    &nbsp;({{ ucfirst($planilla->tipo_planilla) }})
                @endif
            </p>
        </div>

        <div class="employee-info">
            <table>
                <tr>
                    <td style="width: 50%;"><strong>Empleado:</strong> {{ $detalle->empleado->nombres }} {{ $detalle->empleado->apellidos }}</td>
                    <td style="width: 50%;"><strong>Código:</strong> {{ $detalle->empleado->codigo }}</td>
                </tr>
                <tr>
                    <td><strong>Cargo:</strong> {{ $detalle->empleado->cargo->nombre ?? 'N/A' }}</td>
                    <td><strong>DUI:</strong> {{ $detalle->empleado->dui ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td><strong>Departamento:</strong> {{ $detalle->empleado->departamento->nombre ?? 'N/A' }}</td>
                    <td><strong>Salario Base Mensual:</strong> {{ $simbolo }}{{ number_format($detalle->salario_base, 2) }}</td>
                </tr>
            </table>
        </div>

        <table class="details">
            <thead>
                <tr>
                    <th style="width: 35%; text-align: left;">Ingresos</th>
                    <th style="width: 15%; text-align: right;">Monto</th>
                    <th style="width: 35%; text-align: left;">Deducciones</th>
                    <th style="width: 15%; text-align: right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @for($i = 0; $i < $maxRows; $i++)
                <tr>
                    <td>{{ $ingresos[$i]['concepto'] ?? '' }}</td>
                    <td class="text-end">
                        {{ isset($ingresos[$i]) ? $simbolo . number_format($ingresos[$i]['monto'], 2) : '' }}
                    </td>
                    <td>{{ $deducciones[$i]['concepto'] ?? '' }}</td>
                    <td class="text-end">
                        {{ isset($deducciones[$i]) ? $simbolo . number_format($deducciones[$i]['monto'], 2) : '' }}
                    </td>
                </tr>
                @endfor
            </tbody>
        </table>

        <div class="totals-container">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 48%; border: none; vertical-align: top;">
                        @if($viaticosMonto > 0)
                        <div style="background-color: #f8f9fa; border: 1px dashed #6c757d; padding: 8px; border-radius: 4px; font-size: 10.5px;">
                            <strong>Nota Viáticos:</strong> Incluye {{ $simbolo }}{{ number_format($viaticosMonto, 2) }} no gravables (Art. 3 LISR) sumados al total líquido.
                        </div>
                        @endif
                    </td>
                    <td style="width: 4%; border: none;"></td>
                    <td style="width: 48%; border: none; vertical-align: top;">
                        <table class="totals-table">
                            <tr>
                                <td><strong>Total Ingresos:</strong></td>
                                <td class="text-end">{{ $simbolo }}{{ number_format($totalIngresosCalculado, 2) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total Deducciones:</strong></td>
                                <td class="text-end">{{ $simbolo }}{{ number_format($totalDeduccionesCalculado, 2) }}</td>
                            </tr>
                            <tr style="background-color: #f8f9fa;">
                                <td><strong>Sueldo Neto:</strong></td>
                                <td class="text-end">{{ $simbolo }}{{ number_format($sueldoNetoCalculado, 2) }}</td>
                            </tr>
                            @if($viaticosMonto > 0)
                            <tr>
                                <td><strong>(+) Viáticos:</strong></td>
                                <td class="text-end">{{ $simbolo }}{{ number_format($viaticosMonto, 2) }}</td>
                            </tr>
                            @endif
                            <tr style="background-color: #e8f4f8; font-size: 12px;">
                                <td><strong>TOTAL A PAGAR:</strong></td>
                                <td class="text-end"><strong>{{ $simbolo }}{{ number_format($totalPagarCalculado, 2) }}</strong></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <table class="signature-section">
            <tr>
                <td style="width: 50%; text-align: center; border: none;">
                    <div class="signature-line">
                        Firma del Empleado
                    </div>
                </td>
                <td style="width: 50%; text-align: center; border: none;">
                    <div class="signature-line">
                        Firma de RRHH / Autorizado
                    </div>
                </td>
            </tr>
        </table>

        @if(!$loop->last)
        <div class="page-break"></div>
        @endif
    </div>
    @endforeach
</body>
</html>