<?php

namespace App\Models\Contabilidad\Activos;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model {

    protected $table = 'empresa_activos_categorias';
    protected $fillable = array(
        'nombre',
        'empresa_id',
    );


}



