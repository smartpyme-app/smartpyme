<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use App\Models\MH\Municipio;
class Distrito extends Model {

    protected $table = 'distritos';
    protected $fillable = [
        'cod',
        'nombre',
        'cod_municipio',
        'cod_departamento',
    ];

    protected $appends = ['nombre_municipio', 'nombre_departamento'];

    public function getNombreMunicipioAttribute() 
    {
        return $this->municipio ? $this->municipio->nombre : null;
    }

    public function getNombreDepartamentoAttribute() 
    {
        return $this->departamento ? $this->departamento->nombre : null;
    }

    public function municipio(){
        return $this->belongsTo('App\Models\MH\Municipio', 'cod_municipio', 'cod')
                    ->where('cod_departamento', $this->cod_departamento);
    }

    public function departamento(){
        return $this->belongsTo('App\Models\MH\Departamento', 'cod_departamento', 'cod');
    }


}
