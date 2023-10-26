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
        'id_sucursal_de',
        'id_sucursal',
        'cantidad',
        'id_empresa',
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

        return $this->sucursalDe()->pluck('nombre')->first();
    }

    public function getNombreDestinoAttribute(){

        return $this->sucursal()->pluck('nombre')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function sucursalDe(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal_de');
    }

}
