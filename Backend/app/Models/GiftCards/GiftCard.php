<?php

namespace App\Models\GiftCards;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    protected $table = 'gift_cards';

    const ESTADO_ACTIVA = 'activa';
    const ESTADO_AGOTADA = 'agotada';
    const ESTADO_ANULADA = 'anulada';

    protected $fillable = [
        'id_empresa',
        'codigo',
        'monto_inicial',
        'saldo',
        'fecha_emision',
        'fecha_vencimiento',
        'id_vendedor_emisor',
        'id_venta_emision',
        'id_detalle_venta_emision',
        'id_producto',
        'estado',
    ];

    protected $casts = [
        'monto_inicial' => 'decimal:4',
        'saldo' => 'decimal:4',
        'fecha_emision' => 'datetime',
        'fecha_vencimiento' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function vendedorEmisor()
    {
        return $this->belongsTo(User::class, 'id_vendedor_emisor');
    }

    public function ventaEmision()
    {
        return $this->belongsTo(Venta::class, 'id_venta_emision');
    }

    public function detalleVentaEmision()
    {
        return $this->belongsTo(Detalle::class, 'id_detalle_venta_emision');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }

    public function redenciones()
    {
        return $this->hasMany(GiftCardRedencion::class, 'id_gift_card');
    }
}
