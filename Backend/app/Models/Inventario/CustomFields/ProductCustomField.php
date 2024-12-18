<?php

namespace App\Models\Inventario\CustomFields;

use App\Models\CotizacionVenta;
use App\Models\CotizacionVentaDetalle;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCustomField extends Model
{
    protected $fillable = [
        'custom_field_id',
        'value',
        'custom_field_value_id',
        'cotizacion_venta_detalle_id'

    ];
    public function customFieldValue(): BelongsTo
    {
        return $this->belongsTo(CustomFieldValue::class, 'custom_field_value_id');
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function cotizacionVentaDetalle(): BelongsTo
    {
        return $this->belongsTo(CotizacionVentaDetalle::class, 'cotizacion_venta_detalle_id');
    }
}