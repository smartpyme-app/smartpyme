<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MH\Pais;

class EstadoPais extends Model
{
    use HasFactory;

    protected $table = 'estados_paises';
    protected $fillable = [
        'nombre',
        'codigo',
        'codigo_postal',
        'pais_id',
        'type',
        'latitude',
        'longitude'
    ];

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }
}
