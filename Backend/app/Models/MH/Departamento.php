<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model {

    protected $table = 'departamentos';
    protected $fillable = [
        'cod',
        'nombre'
    ];

    public function municipios() 
    {
        return $this->hasMany('App\Models\MH\Municipio', 'cod_departamento');
    }
}
