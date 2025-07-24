<?php

namespace App\Models\Inventario\Traslados;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Traslado extends Model {

    protected $table = 'traslados';
    protected $fillable = array(
       'fecha',
       'concepto',
       'estado',
       'cantidad',
       'id_bodega_de',
       'id_bodega',
       'id_usuario',
       'id_empresa',
    );

    protected $appends = ['nombre_producto', 'nombre_origen', 'nombre_destino'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function getNombreProductoAttribute(){

        return $this->producto()->pluck('nombre')->first();
    }

    public function getNombreOrigenAttribute(){

        return $this->origen()->pluck('nombre')->first();
    }

    public function getNombreDestinoAttribute(){

        return $this->destino()->pluck('nombre')->first();
    }

    public function getUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getCantidadAttribute($value)
    {
        if (!$value)
            return  $this->detalles()->sum('cantidad');
        else
            return $value;
    }

    public function detalles(){
        return $this->hasMany('App\Models\Inventario\Traslados\Detalle','id_traslado');
    }

    public function origen(){
        return $this->belongsTo('App\Models\Inventario\Bodega','id_bodega_de');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function destino(){
        return $this->belongsTo('App\Models\Inventario\Bodega','id_bodega');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin','id_empresa');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

}



