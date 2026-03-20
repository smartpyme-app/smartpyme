<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class Comanda extends Model
{
    protected $table = 'comandas_restaurante';

    protected $fillable = [
        'sesion_id',
        'numero_comanda',
        'estado',
        'enviado_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
    ];

    public function sesion()
    {
        return $this->belongsTo(SesionMesa::class, 'sesion_id');
    }

    public function detalles()
    {
        return $this->hasMany(ComandaDetalle::class, 'comanda_id');
    }
}
