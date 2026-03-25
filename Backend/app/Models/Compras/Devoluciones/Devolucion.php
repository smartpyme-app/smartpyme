<?php

namespace App\Models\Compras\Devoluciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Devolucion extends Model {

    protected $table = 'devoluciones_compra';
    protected $fillable = array(
        'fecha',
        'tipo',
        'tipo_documento',
        'referencia',
        'id_proveedor',
        'descuento',
        'sub_total',
        'iva_percibido',
        'iva_retenido',
        'iva',
        'total',
        'enable',
        'observaciones',
        'id_compra',
        'id_usuario',
        'id_bodega',
        'id_sucursal',
        'id_empresa',

    );

    protected $appends = ['nombre_proveedor', 'nombre_usuario', 'nombre_sucursal'];
    protected $casts = ['enable' => 'string'];

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
    {   $proveedor = $this->proveedor()->first();
        if ($proveedor) {
            return $proveedor->tipo == 'Empresa' ? $proveedor->nombre_empresa : $proveedor->nombre . ' ' . $proveedor->apellido;
        }
        return 'Consumidor Final';
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor','id_proveedor');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }

    public function compra(){
        return $this->belongsTo('App\Models\Compras\Compra','id_compra');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Compras\Devoluciones\Detalle','id_devolucion_compra');
    }


}
