<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class BoxfulParcel extends Model
{
    protected $table = 'boxful_parcels';

    protected $fillable = [
        'boxful_shipment_id',
        'contenido',
        'alto',
        'ancho',
        'largo',
        'peso',
        'valor_declarado',
        'es_fragil'
    ];

    protected $casts = [
        'alto' => 'float',
        'ancho' => 'float',
        'largo' => 'float',
        'peso' => 'float',
        'valor_declarado' => 'float',
        'es_fragil' => 'boolean'
    ];

    public function shipment()
    {
        return $this->belongsTo(BoxfulShipment::class, 'boxful_shipment_id');
    }
}
