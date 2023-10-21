<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaccion extends Model
{
    protected $table = 'transacciones';
    protected $fillable = [
       'fecha',
       'correlativo',
       'estado',
       'metodo_pago',
       'tipo_documento',
       'referencia',
       'total',
       'nota',
       'empresa_id',
       'usuario_id',
    ];

    public function usuario(){
        return $this->belongsTo('App\User', 'usuario_id');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }

}
