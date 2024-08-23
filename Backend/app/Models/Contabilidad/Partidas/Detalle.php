<?php

namespace App\Models\Contabilidad\Partidas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Detalle extends Model
{
    use HasFactory;
    protected $table = 'partida_detalles';
    protected $fillable = [
        'id_cuenta',
        'codigo',
        'nombre_cuenta',
        'concepto',
        'debe',
        'haber',
        'saldo',
        'id_partida',
    ];

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function cuenta(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta');
    }

    public function partida()
    {
        return $this->belongsTo(Partida::class, 'id_partida');
    }

}
