<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .section { page-break-before: always; }
        .section:first-child { page-break-before: auto; }
        h1 { font-size: 13px; text-align: center; }
        h2 { font-size: 11px; text-align: center; color: #333; }
    </style>
</head>
<body>
    <div class="section">
        @include('reportes.contabilidad.balance_general', ['balance' => $balance, 'empresa' => $empresa, 'fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin])
    </div>
    <div class="section">
        @include('reportes.contabilidad.estado_resultados', ['estado' => $estado, 'empresa' => $empresa, 'fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin])
    </div>
    <div class="section">
        @include('reportes.contabilidad.flujo_efectivo', ['flujo' => $flujo, 'empresa' => $empresa, 'fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin])
    </div>
    <div class="section">
        @include('reportes.contabilidad.cambios_patrimonio', ['estado' => $patrimonio, 'empresa' => $empresa, 'fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin])
    </div>
    <div class="section">
        @include('reportes.contabilidad.notas_estados_financieros', ['notas' => $notasPayload, 'empresa' => $empresa, 'fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin])
    </div>
</body>
</html>
