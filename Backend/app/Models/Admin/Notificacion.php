<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';
    protected $fillable = [
        'titulo',
        'descripcion',
        'tipo',
        'categoria',
        'prioridad',
        'leido',
        'referencia',
        'referencia_id',
        'empresa_id',
        'sucursal_id',
    ];

    public function usuario(){
        return $this->belongsTo('App\User', 'usuario_id');
    }

}
