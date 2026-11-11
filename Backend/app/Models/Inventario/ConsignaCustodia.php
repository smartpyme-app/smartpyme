<?php

namespace App\Models\Inventario;

use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignaCustodia extends Model
{
    protected $table = 'consigna_custodias';

    protected $fillable = [
        'id_empresa',
        'id_cliente',
        'id_producto',
        'id_bodega',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'float',
    ];

    public function scopeForEmpresa($query, int $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'id_bodega');
    }
}
