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
        if ($this->cod_municipio === null || $this->cod_departamento === null) {
            return null;
        }
        if (
            $this->relationLoaded('municipio')
            && $this->municipio
            && (string) $this->municipio->cod_departamento === (string) $this->cod_departamento
        ) {
            return $this->municipio->nombre;
        }

        return Municipio::query()
            ->where('cod', $this->cod_municipio)
            ->where('cod_departamento', $this->cod_departamento)
            ->value('nombre');
    }

    public function getNombreDepartamentoAttribute() 
    {
        return $this->departamento ? $this->departamento->nombre : null;
    }

    public function municipio()
    {
        // ponytail: clave compuesta solo por código de municipio; validar cod_departamento en el accessor.
        return $this->belongsTo(Municipio::class, 'cod_municipio', 'cod');
    }

    public function departamento(){
        return $this->belongsTo('App\Models\MH\Departamento', 'cod_departamento', 'cod');
    }


}
