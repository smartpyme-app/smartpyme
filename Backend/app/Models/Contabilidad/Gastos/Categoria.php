<?php

namespace App\Models\Contabilidad\Gastos;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model {

    protected $table = 'empresa_sucursal_gastos_categorias';
    protected $fillable = array(
        'nombre',
        'empresa_id',
    );


}



