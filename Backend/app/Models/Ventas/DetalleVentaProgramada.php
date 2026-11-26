<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class DetalleVentaProgramada extends Model
{
    protected $table = 'detalles_venta_programada';

    protected $fillable = [
        'id_venta_programada',
        'id_producto',
        'id_presentacion',
        'descripcion',
        'cantidad',
        'precio',
        'precio_sin_iva',
        'precio_con_iva',
        'costo',
        'descuento',
        'sub_total',
        'tipo_gravado',
        'no_sujeta',
        'exenta',
        'gravada',
        'cuenta_a_terceros',
        'total_costo',
        'total',
        'id_vendedor',
        'iva',
        'porcentaje_impuesto',
        'fe_cr_exoneracion',
    ];

    protected $casts = [
        'fe_cr_exoneracion' => 'array',
    ];

    public function ventaProgramada()
    {
        return $this->belongsTo(VentaProgramada::class, 'id_venta_programada');
    }

    public function producto()
    {
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function vendedor()
    {
        return $this->belongsTo('App\Models\User', 'id_vendedor');
    }
}