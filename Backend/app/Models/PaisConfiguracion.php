<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaisConfiguracion extends Model
{
    protected $table = 'pais_configuracion';

    public const MODULO_DOCUMENTOS = 'documentos';

    public const MODULO_PLANILLAS = 'planillas';

    protected $fillable = [
        'pais',
        'modulo',
        'configuracion',
    ];

    protected $casts = [
        'configuracion' => 'array',
    ];

    public function scopeModulo($query, string $modulo)
    {
        return $query->where('modulo', $modulo);
    }

    public function scopePais($query, string $pais)
    {
        return $query->where('pais', strtoupper($pais));
    }
}
