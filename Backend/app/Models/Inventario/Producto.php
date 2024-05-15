<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Producto extends Model {

    use SoftDeletes;
    protected $table = 'productos';
    protected $fillable = array(
        'nombre',
        'descripcion',
        'codigo',
        'barcode',
        'medida',
        'precio',
        'costo',
        'costo_anterior',
        'id_categoria',
        'marca',
        'etiquetas',
        'tipo',
        'enable',
        'id_empresa',
    );

    protected $appends = ['nombre_categoria', 'img'];
    protected $casts = ['enable' => 'string'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {

            if (Auth::user()->tipo == 'Ventas') {
                static::addGlobalScope('sucursal', function (Builder $builder) {
                    $builder->with('inventarios', function($q){
                        return $q->where('id_sucursal', Auth::user()->id_sucursal);
                    })->where('id_empresa', Auth::user()->id_empresa);
                });
            }else{
                static::addGlobalScope('empresa', function (Builder $builder) {
                    $builder->where('id_empresa', Auth::user()->id_empresa);
                });
            }
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
        }else{
            return 'productos/default.jpg';
        }
    }
    
    public function getNombreCategoriaAttribute()
    {
        return $this->categoria()->pluck('nombre')->first();
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Inventario\Categorias\Categoria','id_categoria');
    }

    public function compras(){
        return $this->hasMany('App\Models\Compras\Detalle','id_producto');
    }

    public function inventarios(){
        return $this->hasMany('App\Models\Inventario\Inventario','id_producto');
    }

    public function sucursales(){
        return $this->hasMany('App\Models\Inventario\Sucursal','id_producto')->orderby('id', 'desc');
    }

    public function composiciones(){
        return $this->hasMany('App\Models\Inventario\Composiciones\Composicion', 'id_producto');
    }

    public function precios(){
        return $this->hasMany('App\Models\Inventario\Precios\Precio','id_producto');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function promocion(){
        return $this->belongsTo('App\Models\Inventario\Promociones\Promocion', 'id_promocion');
    }

    // public function promociones(){
    //     return $this->hasMany('App\Models\Inventario\Promocion','id_producto');
    // }

    public function imagenes(){
        return $this->hasMany('App\Models\Inventario\Imagen','id_producto');
    }

    public function proveedores(){
        return $this->hasMany('App\Models\Inventario\Proveedor', 'id_producto');
    }

    public function ventas(){
        return $this->hasMany('App\Models\Ventas\Detalle','id_producto');
    }

    public function traslados(){
        return $this->hasMany('App\Models\Inventario\Traslado','id_producto');
    }

    public function ajustes(){
        return $this->hasMany('App\Models\Inventario\Ajuste','id_producto');
    }

    public function kardex(){
        return $this->hasMany('App\Models\Inventario\Kardex', 'id_producto');
    }


}



