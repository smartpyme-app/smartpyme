<?php

namespace App\Models\Clinica;

use App\Models\Admin\Empresa;
use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use Illuminate\Database\Eloquent\Model;

class Especie extends Model
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_especies';

    protected $fillable = [
        'id_empresa',
        'nombre',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function razas()
    {
        return $this->hasMany(Raza::class, 'id_especie');
    }
}
