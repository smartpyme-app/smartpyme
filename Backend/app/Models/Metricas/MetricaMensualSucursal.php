<?php

namespace App\Models\Metricas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricaMensualSucursal extends Model
{
    use HasFactory;

    protected $table = 'ia_metricas_mensuales_sucursales';

    protected $fillable = [
        'fecha',
        'id_empresa',
        'id_sucursal',
        'ventas_sin_iva',
        'ventas_con_iva',
        'egresos_sin_iva',
        'egresos_con_iva',
        'costo_venta_sin_iva',
        'flujo_efectivo_sin_iva',
        'flujo_efectivo_con_iva',
        'rentabilidad_monto',
        'rentabilidad_porcentaje',
        'cxc_totales',
        'cxc_vencidas',
        'cxc_vencimiento_30_dias',
        'cxp_totales',
        'cxp_vencidas',
        'cxp_vencimiento_30_dias',
        'ventas_vs_mes_anterior',
        'egresos_vs_mes_anterior',
        'flujo_efectivo_vs_mes_anterior',
        'rentabilidad_vs_mes_anterior',
        'ventas_vs_presupuesto',
    ];
}
