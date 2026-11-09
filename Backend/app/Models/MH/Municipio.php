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
        if ($this->relationLoaded('departamento') && $this->departamento) {
            return $this->departamento->nombre;
        }
        return $this->departamento()->value('nombre');
    }

        public function departamento() 
    {
        return $this->belongsTo('App\Models\MH\Departamento', 'cod_departamento');
    }

}
