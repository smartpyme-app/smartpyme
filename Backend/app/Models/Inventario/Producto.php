<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Producto extends Model {

    // use SoftDeletes;
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
        'activo',
        'id_empresa',
    );

    protected $appends = ['nombre_categoria'];
    protected $casts = ['activo' => 'boolean'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){

            if ($usuario->tipo == 'Ventas') {
                static::addGlobalScope('sucursal', function (Builder $builder) use ($usuario) {
                    $builder->whereHas('inventarios', function($q) use ($usuario){
                            return $q->where('id_sucursal', $usuario->id_sucursal);
                        });
                });
            }else{
                static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                    $builder->where('id_empresa', $usuario->id_empresa);
                });
            }
        }
        
    }

    public function getEtiquetasAttribute($value) 
    {
        return is_string($value) ? json_decode($value) : $value;
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
        return $this->hasMany('App\Models\Inventario\Composicion','id_producto');
    }

    public function precios(){
        return $this->hasMany('App\Models\Inventario\Precios\Precio','id_producto');
    }

    public function promociones(){
        return $this->hasMany('App\Models\Inventario\Promocion','id_producto');
    }

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
        return $this->hasMany('App\Models\Inventario\TrasladoDetalle','id_producto');
    }

    public function ajustes(){
        return $this->hasMany('App\Models\Inventario\Ajuste','id_producto');
    }

}



