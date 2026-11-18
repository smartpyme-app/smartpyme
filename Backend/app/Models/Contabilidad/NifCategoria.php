<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;

/**
 * Categoría de gasto por función, NIIF para las PYMES, Sección 5.
 * En El Salvador rige la versión 2015 (CVPCPA, Res. 462). La tercera edición (2025) aplica desde 2027.
 */
class NifCategoria extends Model
{
    protected $table = 'nif_categorias';

    protected $fillable = ['codigo', 'nombre', 'afecta_resultados', 'orden', 'norma'];

    protected $casts = ['afecta_resultados' => 'boolean'];
}
