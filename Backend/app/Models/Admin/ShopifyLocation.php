<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ShopifyLocation extends Model
{
    protected $table = 'shopify_locations';

    protected $fillable = [
        'id_empresa',
        'shopify_location_id',
        'shopify_location_name',
        'shopify_active',
        'id_sucursal',
        'id_bodega',
        'sincronizar_stock',
        'es_default',
    ];

    protected $casts = [
        'id_sucursal' => 'integer',
        'id_bodega' => 'integer',
        'shopify_active' => 'boolean',
        'sincronizar_stock' => 'boolean',
        'es_default' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check() && Auth::user()->id_empresa != 2) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    public function bodega()
    {
        return $this->belongsTo(\App\Models\Inventario\Bodega::class, 'id_bodega');
    }
}
