<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenPagoDetalle extends Model
{
    use HasFactory;

    protected $table = 'ordenes_pago_detalles';

    protected $fillable = [
        'orden_pago_id',
        'item_id',
        'name',
        'price',
        'quantity',
        'modifiers_total',
        'sku',
        'product_image_url',
        'note',
        'description',
        'quantity_available',
        'requires_shipping',
        'promo_id',
        'promo_price',
        'promo_name'
    ];

    public function ordenPago()
    {
        return $this->belongsTo(OrdenPago::class, 'orden_pago_id');
    }
}
