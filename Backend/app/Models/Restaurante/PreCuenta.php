<?php

namespace App\Models\Restaurante;

use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;

class PreCuenta extends Model
{
    protected $table = 'pre_cuentas_restaurante';

    protected $fillable = [
        'sesion_id',
        'division_cuenta_id',
        'subtotal',
        'descuento',
        'impuesto',
        'propina_monto',
        'propina_porcentaje_aplicado',
        'total',
        'estado',
        'factura_id',
        'numero_pre_cuenta',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'propina_monto' => 'decimal:2',
        'propina_porcentaje_aplicado' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function sesion()
    {
        return $this->belongsTo(SesionMesa::class, 'sesion_id');
    }

    public function divisionCuenta()
    {
        return $this->belongsTo(DivisionCuenta::class, 'division_cuenta_id');
    }

    public function factura()
    {
        return $this->belongsTo(Venta::class, 'factura_id');
    }

    public function ordenDetalles()
    {
        return $this->belongsToMany(OrdenDetalle::class, 'pre_cuenta_orden_detalle', 'pre_cuenta_id', 'orden_detalle_id');
    }
}
