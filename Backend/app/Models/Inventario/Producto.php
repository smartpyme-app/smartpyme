<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Producto extends Model
{

    use SoftDeletes;
    protected $table = 'productos';
    protected $fillable = array(
        'nombre',
        'nombre_variante',
        'descripcion',
        'descripcion_completa',
        'codigo',
        'barcode',
        'medida',
        'precio',
        'precio_sin_iva',
        'precio_con_iva',
        'costo',
        'costo_anterior',
        'costo_promedio',
        'id_categoria',
        'id_subcategoria',
        'marca',
        'etiquetas',
        'tipo',
        'enable',
        'id_empresa',
        'woocommerce_id',
        'shopify_product_id',
        'shopify_variant_id',
        'shopify_inventory_item_id',
        'syncing_from_shopify',
        'last_shopify_sync',
        'cod_proveed_prod',
        'talla',
        'color',
        'dimension',
        'material',
        'dimensiones'
    );

    protected $appends = ['nombre_categoria', 'img', 'nombre_completo'];
    protected $casts = [
        'enable' => 'string',
        'syncing_from_shopify' => 'boolean',
        'last_shopify_sync' => 'datetime',
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

    public function getEtiquetasAttribute($value)
    {
        return is_string($value) ? json_decode($value) : $value;
    }

    public function setEtiquetasAttribute($valor)
    {
        $this->attributes['etiquetas'] = json_encode($valor);
    }


    public function getImgAttribute()
    {
        if ($this->imagenes()->count() > 0) {
            return $this->imagenes->pluck('img')->first();
        } else {
            return 'productos/default.jpg';
        }
    }

    public function getNombreCategoriaAttribute()
    {
        return $this->categoria()->pluck('nombre')->first();
    }

    /**
     * Obtiene el nombre completo del producto (base + variante)
     */
    public function getNombreCompletoAttribute()
    {
        if (!empty($this->nombre_variante)) {
            return $this->nombre . ' (' . $this->nombre_variante . ')';
        }
        return $this->nombre;
    }

    public function categoria()
    {
        return $this->belongsTo('App\Models\Inventario\Categorias\Categoria', 'id_categoria');
    }

    public function compras()
    {
        return $this->hasMany('App\Models\Compras\Detalle', 'id_producto');
    }

    public function inventarios()
    {
        return $this->hasMany('App\Models\Inventario\Inventario', 'id_producto')
            ->whereHas('bodega', function ($query) {
                $query->where('activo', 1);
            });
    }

    public function sucursales()
    {
        return $this->hasMany('App\Models\Inventario\Sucursal', 'id_producto')->orderby('id', 'desc');
    }

    public function composiciones()
    {
        return $this->hasMany('App\Models\Inventario\Composiciones\Composicion', 'id_producto');
    }

    public function precios()
    {
        return $this->hasMany('App\Models\Inventario\Precios\Precio', 'id_producto');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function promocion()
    {
        return $this->belongsTo('App\Models\Inventario\Promociones\Promocion', 'id_promocion');
    }

    // public function promociones(){
    //     return $this->hasMany('App\Models\Inventario\Promocion','id_producto');
    // }

    public function imagenes()
    {
        return $this->hasMany('App\Models\Inventario\Imagen', 'id_producto');
    }

    public function proveedores()
    {
        return $this->hasMany('App\Models\Inventario\Proveedor', 'id_producto');
    }

    public function ventas()
    {
        return $this->hasMany('App\Models\Ventas\Detalle', 'id_producto');
    }

    public function traslados()
    {
        return $this->hasMany('App\Models\Inventario\Traslado', 'id_producto');
    }

    public function ajustes()
    {
        return $this->hasMany('App\Models\Inventario\Ajuste', 'id_producto');
    }

    public function kardex()
    {
        return $this->hasMany('App\Models\Inventario\Kardex', 'id_producto');
    }
}
