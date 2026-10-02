<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use Illuminate\Database\Eloquent\Model;

class Raza extends Model
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_razas';

    protected $fillable = [
        'id_empresa',
        'id_especie',
        'nombre',
    ];

    public function especie()
    {
        return $this->belongsTo(Especie::class, 'id_especie');
    }
}
