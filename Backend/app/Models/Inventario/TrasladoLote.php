<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class TrasladoLote extends Model
{
    protected $table = 'traslado_lotes';

    protected $fillable = [
        'traslado_id',
        'lote_id',
        'lote_id_destino',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
    ];

    public function traslado()
    {
        return $this->belongsTo(Traslado::class, 'traslado_id');
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function loteDestino()
    {
        return $this->belongsTo(Lote::class, 'lote_id_destino');
    }
}
