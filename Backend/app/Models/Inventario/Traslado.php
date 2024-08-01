<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Traslado extends Model
{
    use HasFactory;
    protected $table = 'traslados';
    protected $fillable = [
        'id_producto',
        'id_bodega_de',
        'id_bodega',
        'cantidad',
        'id_empresa',
        'id_usuario',
        'concepto',
        'estado'
    ];

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

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function destino(){
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function origen(){
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega_de');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

}
