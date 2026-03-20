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
        'total',
        'estado',
        'factura_id',
        'numero_pre_cuenta',
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
