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
       'descripcion',
       'referencia',
       'total',
       'nota',
       'id_empresa',
       'id_usuario',
    ];

    public function usuario(){
        return $this->belongsTo('App\User', 'id_usuario');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

}
