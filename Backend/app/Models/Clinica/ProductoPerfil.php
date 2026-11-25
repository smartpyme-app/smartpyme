<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoPerfil extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_producto_perfiles';

    protected $fillable = [
        'id_empresa',
        'id_producto',
        'via_habitual',
        'unidad_dosis',
        'controla_lote',
        'activo',
    ];

    protected $casts = [
        'controla_lote' => 'boolean',
        'activo' => 'boolean',
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
