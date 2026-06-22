<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class BoxfulShipment extends Model
{
    protected $table = 'boxful_shipments';

    protected $fillable = [
        'paquete_id',
        'direccion_origen_id',
        'fecha_recoleccion',
        'cod',
        'cod_monto',
        'boxful_shipment_id',
        'shipment_number',
        'boxful_courier_id',
        'boxful_courier_name',
        'boxful_label_url',
        'boxful_tracking_url',
        'boxful_status',
        'boxful_status_description'
    ];

    protected $casts = [
        'cod' => 'boolean',
        'cod_monto' => 'float',
        'boxful_status' => 'integer',
        'fecha_recoleccion' => 'datetime'
    ];

    public function paquete()
    {
        return $this->belongsTo(Paquete::class, 'paquete_id');
    }

    public function parcels()
    {
        return $this->hasMany(BoxfulParcel::class, 'boxful_shipment_id');
    }
}
