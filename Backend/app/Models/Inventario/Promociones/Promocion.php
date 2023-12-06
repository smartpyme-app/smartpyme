<?php

namespace App\Models\Inventario\Promociones;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventario\Promociones\Detalle;

class Promocion extends Model
{
    protected $table = 'promociones';
    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'codigo',
        'enable',
        'descuento',
        'sub_total',
        'id_sucursal',
        'id_empresa',
    ];


    public function empresa(){
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Inventario\Promociones\Detalle', 'id_promocion');
    }

    public function ventas(){
        return Detalle::where('id_producto', $this->producto()->pluck('id')->first());
    }

    public function producto(){
        return $this->hasOne('App\Models\Inventario\Producto', 'id_promocion');
    }

}
