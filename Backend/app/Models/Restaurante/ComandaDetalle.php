<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class ComandaDetalle extends Model
{
    protected $table = 'comanda_detalle_restaurante';

    protected $fillable = [
        'comanda_id',
        'orden_detalle_id',
    ];

    public function comanda()
    {
        return $this->belongsTo(Comanda::class);
    }

    public function ordenDetalle()
    {
        return $this->belongsTo(OrdenDetalle::class, 'orden_detalle_id');
    }
}
