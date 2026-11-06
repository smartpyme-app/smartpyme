<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetaLinea extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_receta_lineas';

    protected $fillable = [
        'id_empresa',
        'id_receta',
        'orden',
        'id_producto',
        'nombre_medicamento',
        'dosis',
        'frecuencia',
        'duracion',
        'via',
        'indicaciones',
        'peso_referencia',
    ];

    protected $casts = [
        'peso_referencia' => 'decimal:2',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
}
