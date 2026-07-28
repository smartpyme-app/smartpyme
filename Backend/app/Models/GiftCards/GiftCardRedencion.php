<?php

namespace App\Models\GiftCards;

use App\Models\Admin\Empresa;
use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Categorias\SubCategoria;
use App\Models\User;
use App\Models\Ventas\Venta;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GiftCardRedencion extends Model
{
    protected $table = 'gift_card_redenciones';

    protected $fillable = [
        'id_empresa',
        'id_gift_card',
        'id_venta',
        'id_vendedor',
        'monto',
        'saldo_resultante',
        'id_categoria',
        'id_subcategoria',
        'id_comision_movimiento',
    ];

    protected $casts = [
        'monto' => 'decimal:4',
        'saldo_resultante' => 'decimal:4',
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

    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class, 'id_gift_card');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta');
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'id_vendedor');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function subcategoria()
    {
        return $this->belongsTo(SubCategoria::class, 'id_subcategoria');
    }

    public function comisionMovimiento()
    {
        return $this->belongsTo(ComisionMovimiento::class, 'id_comision_movimiento');
    }
}
