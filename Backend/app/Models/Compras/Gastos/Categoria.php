<?php

namespace App\Models\Compras\Gastos;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model {

    protected $table = 'gastos_categorias';
    protected $fillable = array(
        'nombre',
        'id_empresa',
    );


}



