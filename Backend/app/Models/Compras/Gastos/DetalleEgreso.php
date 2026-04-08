<?php

namespace App\Models\Compras\Gastos;

use Illuminate\Database\Eloquent\Model;

class DetalleEgreso extends Model
{
    protected $table = 'detalle_egresos';

    protected $fillable = [
        'id_egreso',
        'numero_item',
        'concepto',
        'tipo',
        'tipo_gravado',
        'id_categoria',
        'cantidad',
        'precio_unitario',
        'sub_total',
        'iva',
        'renta_retenida',
        'iva_percibido',
        'total',
        'area_empresa',
        'id_proyecto',
        'aplica_iva',
        'aplica_renta',
        'aplica_percepcion',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'sub_total' => 'decimal:2',
        'iva' => 'decimal:2',
        'renta_retenida' => 'decimal:2',
        'iva_percibido' => 'decimal:2',
        'total' => 'decimal:2',
        'aplica_iva' => 'boolean',
        'aplica_renta' => 'boolean',
        'aplica_percepcion' => 'boolean',
    ];

    public function egreso()
    {
        return $this->belongsTo(Gasto::class, 'id_egreso');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function proyecto()
    {
        return $this->belongsTo('App\Models\Contabilidad\Proyecto', 'id_proyecto');
    }
}
