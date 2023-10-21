<?php

namespace App\Models\Ventas\Clientes;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model {

    protected $table = 'cliente_documentos';
    protected $fillable = [
        'nombre',
        'url',
        'tamano',
        'tipo',
        'nota',
        'cliente_id',
    ];

    public function cliente() 
    {
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'cliente_id');
    }
}
