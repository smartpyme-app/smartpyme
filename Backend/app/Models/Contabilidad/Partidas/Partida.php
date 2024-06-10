<?php

namespace App\Models\Contabilidad\Partidas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partida extends Model
{
    use HasFactory;
    protected $table = 'partidas';
    protected $fillable = [
        'fecha',
        'tipo',
        'concepto',
        'estado',
        'id_usuario',
        'id_empresa',
    ];

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
    
}
