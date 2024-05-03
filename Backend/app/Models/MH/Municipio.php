<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;

class Municipio extends Model {

    protected $table = 'municipios';
    protected $fillable = [
        'cod',
        'nombre',
        'cod_departamento',
    ];

    protected $appends = ['nombre_departamento'];

    public function getNombreDepartamentoAttribute() 
    {
        return $this->departamento()->pluck('nombre')->first();
    }

        public function departamento() 
    {
        return $this->belongsTo('App\Models\MH\Departamento', 'cod_departamento');
    }

}
