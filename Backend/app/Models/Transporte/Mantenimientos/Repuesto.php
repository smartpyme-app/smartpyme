<?php

namespace App\Models\Transporte\Mantenimientos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;
use Auth;

class Repuesto extends Model {

    use SoftDeletes;
    protected $table = 'productos';
    protected $fillable = array(
        'nombre',
        'descripcion',
        'codigo',
        'medida',
        'precio',
        'precio2',
        'precio3',
        'precio4',
        'costo',
        'costo_anterior',
        'categoria_id',
        'subcategoria_id',
        'marca',
        'etiquetas',
        'tipo',
        'tipo_impuesto',
        'compuesto',
        'activo',
        'nota',
        'empresa_id',
    );

    protected $appends = ['img', 'nombre_categoria', 'nombre_subcategoria', 'proveedor_id', 'nombre_proveedor', 'costo_promedio', 'promocion', 'bodega_venta'];
    protected $casts = ['activo' => 'boolean'];

    // protected static function booted()
    // {
    //     $usuario = JWTAuth::parseToken()->authenticate();

    //     if ($usuario && $usuario->tipo != 'Administrador') {
    //         static::addGlobalScope('sucursal', function (Builder $builder) use ($usuario) {
    //             $builder->wherehas('sucursales', function($q) use ($usuario){
    //                 $q->where('sucursal_id', $usuario->sucursal_id)
    //                     ->where('activo', true);
    //             });
    //         });
    //     }
    // }

    public function getEtiquetasAttribute($value) 
    {
        return is_string($value) ? json_decode($value) : $value;
    }

    public function getNombreAttribute($value)
    {
        return strtoupper($value);
    }

    public function getNombreCategoriaAttribute()
    {
        return $this->categoria()->pluck('nombre')->first();
    }

    public function getNombreSubcategoriaAttribute()
    {
        return $this->subcategoria()->pluck('nombre')->first();
    }

    public function getCostoPromedioAttribute()
    {
        return number_format(($this->costo + $this->costo_anterior) / 2, 2);
    }

    public function getPromocionAttribute()
    {
        return $this->promociones()->where('inicio', '<', \Carbon\Carbon::now())
                                ->where('fin', '>', \Carbon\Carbon::now())
                                ->latest()
                                ->first();
    }

    public function getBodegaVentaAttribute(){
        $usuario = JWTAuth::parseToken()->authenticate();
        if ($usuario)
            return $this->inventarios()->where('bodega_id', $usuario->sucursal_id)->first();
        else
            return null;
    }


    public function getProveedorIdAttribute(){
        $compra = $this->compras()->orderBy('id', 'desc')->first();
        if ($compra && $compra->compra)
            return $compra->compra->proveedor_id;
        else
            return null;
    }

    public function getNombreProveedorAttribute(){
        $compra = $this->compras()->orderBy('id', 'desc')->first();
        if ($compra && $compra->compra)
            return $compra->compra->proveedor;
        else
            return null;
    }

    public function getImgAttribute()
    {
        if ($this->imagenes()->count() > 0) {
            return $this->imagenes()->orderBy('id', 'asc')->pluck('img')->first();
        }else{
            return '/productos/default.jpg';
        }
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Inventario\Categorias\Categoria','categoria_id');
    }

    public function subcategoria(){
        return $this->belongsTo('App\Models\Inventario\Categorias\SubCategoria','subcategoria_id');
    }

    public function compras(){
        return $this->hasMany('App\Models\Compras\Detalle','producto_id');
    }

    public function inventarios(){
        return $this->hasMany('App\Models\Inventario\Inventario','producto_id');
    }

    public function sucursales(){
        return $this->hasMany('App\Models\Inventario\Sucursal','producto_id')->orderby('id', 'desc');
    }

    public function composiciones(){
        return $this->hasMany('App\Models\Inventario\Composicion','producto_id');
    }

    public function promociones(){
        return $this->hasMany('App\Models\Inventario\Promocion','producto_id');
    }

    public function imagenes(){
        return $this->hasMany('App\Models\Inventario\Imagen','producto_id');
    }

    public function ventas(){
        return $this->hasMany('App\Models\Ventas\Detalle','producto_id');
    }

    public function traslados(){
        return $this->hasMany('App\Models\Inventario\TrasladoDetalle','producto_id');
    }

    public function ajustes(){
        return $this->hasMany('App\Models\Inventario\Ajuste','producto_id');
    }

}



