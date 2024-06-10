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
        'id_partida',
        'concepto',
        'cargo',
        'abono',
    ];

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function cuenta(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta');
    }
    
}
