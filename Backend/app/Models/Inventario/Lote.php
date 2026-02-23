<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Lote extends Model
{
    use SoftDeletes;

    protected $table = 'lotes';
    
    protected $fillable = [
        'id_producto',
        'id_bodega',
        'numero_lote',
        'fecha_vencimiento',
        'fecha_fabricacion',
        'stock',
        'stock_inicial',
        'id_empresa',
        'observaciones',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'fecha_fabricacion' => 'date',
        'stock' => 'decimal:2',
        'stock_inicial' => 'decimal:2',
    ];

    protected $appends = ['estado_vencimiento', 'dias_vencimiento', 'nombre_producto', 'nombre_bodega'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    /**
     * Obtiene el estado de vencimiento del lote
     */
    public function getEstadoVencimientoAttribute()
    {
        if (!$this->fecha_vencimiento) {
            return 'sin_vencimiento';
        }

        $hoy = now();
        $diasRestantes = $hoy->diffInDays($this->fecha_vencimiento, false);

        if ($diasRestantes < 0) {
            return 'vencido';
        } elseif ($diasRestantes == 0) {
            return 'venciendo_hoy';
        } elseif ($diasRestantes <= 7) {
            return 'venciendo_proximo';
        } else {
            return 'vigente';
        }
    }

    /**
     * Obtiene los días hasta el vencimiento
     */
    public function getDiasVencimientoAttribute()
    {
        if (!$this->fecha_vencimiento) {
            return null;
        }

        return now()->diffInDays($this->fecha_vencimiento, false);
    }

    /**
     * Obtiene el nombre del producto
     */
    public function getNombreProductoAttribute()
    {
        return $this->producto ? $this->producto->nombre : '';
    }

    /**
     * Obtiene el nombre de la bodega
     */
    public function getNombreBodegaAttribute()
    {
        return $this->bodega ? $this->bodega->nombre : '';
    }

    /**
     * Relación con Producto
     */
    public function producto()
    {
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    /**
     * Relación con Bodega
     */
    public function bodega()
    {
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    /**
     * Relación con Detalles de Compra
     */
    public function detallesCompra()
    {
        return $this->hasMany('App\Models\Compras\Detalle', 'lote_id');
    }

    /**
     * Relación con Detalles de Venta
     */
    public function detallesVenta()
    {
        return $this->hasMany('App\Models\Ventas\Detalle', 'lote_id');
    }
}
