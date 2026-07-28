<?php

namespace App\Models\Comisiones;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Categorias\SubCategoria;
use App\Models\User;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComisionMovimiento extends Model
{
    protected $table = 'comision_movimientos';

    const ORIGEN_VENTA = 'venta';
    const ORIGEN_REDENCION_GIFT_CARD = 'redencion_gift_card';
    const ORIGEN_AJUSTE_DEVOLUCION = 'ajuste_devolucion';

    protected $fillable = [
        'id_empresa',
        'id_vendedor',
        'id_periodo',
        'origen',
        'id_venta',
        'id_detalle_venta',
        'id_gift_card_redencion',
        'id_categoria',
        'id_subcategoria',
        'monto_base',
        'porcentaje_aplicado',
        'monto_comision',
        'id_movimiento_origen',
        'fecha_evento',
    ];

    protected $casts = [
        'monto_base' => 'decimal:4',
        'porcentaje_aplicado' => 'decimal:4',
        'monto_comision' => 'decimal:4',
        'fecha_evento' => 'datetime',
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

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'id_vendedor');
    }

    public function periodo()
    {
        return $this->belongsTo(ComisionPeriodo::class, 'id_periodo');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta');
    }

    public function detalleVenta()
    {
        return $this->belongsTo(Detalle::class, 'id_detalle_venta');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function subcategoria()
    {
        return $this->belongsTo(SubCategoria::class, 'id_subcategoria');
    }

    public function movimientoOrigen()
    {
        return $this->belongsTo(self::class, 'id_movimiento_origen');
    }

    public function ajustes()
    {
        return $this->hasMany(self::class, 'id_movimiento_origen');
    }
}
