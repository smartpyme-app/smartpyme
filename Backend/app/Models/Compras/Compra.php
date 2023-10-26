<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Compra extends Model {

    protected $table = 'compras';
    protected $fillable = array(
        'fecha',
        'estado',
        'tipo',
        'metodo_pago',
        'tipo_documento',
        'condicion',
        'fecha_pago',
        'num_referencia',
        'num_serie',
        'num_orden_compra',
        'detalle_banco',
        'aplicada_inventario',
        'notas',
        'proveedor_id',
        'no_sujeta',
        'exenta',
        'gravada',
        'iva_percibido',
        'iva_retenido',
        'descuento',
        'iva',
        'subtotal',
        'total',
        'bodega_id',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );

    protected $appends = ['nombre_proveedor', 'nombre_usuario'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function getNombreProveedorAttribute()
    {
        return $this->proveedor()->pluck('nombre')->first();
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega','bodega_id');
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor','id_proveedor');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Compras\Detalle','id_compra');
    }


}
