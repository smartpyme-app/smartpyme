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
        // 'tipo',
        'forma_pago',
        'tipo_documento',
        // 'condicion',
        'fecha_pago',
        'num_referencia',
        'num_serie',
        'num_orden_compra',
        'detalle_banco',
        // 'aplicada_inventario',
        'notas',
        'id_proveedor',
        'no_sujeta',
        'exenta',
        'gravada',
        'percepcion',
        // 'iva_retenido',
        'descuento',
        'iva',
        'sub_total',
        'total',
        // 'id_bodega',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );

    protected $appends = ['nombre_proveedor', 'nombre_usuario', 'nombre_sucursal'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function getSaldoAttribute(){
        return round($this->total - $this->abonos()->where('estado', 'Confirmado')->sum('total'),2);
    }


    public function getNombreSucursalAttribute()
    {
        if ($this->sucursal()->first()) {
            return $this->sucursal()->pluck('nombre')->first();
        }
        return '';
    }

    public function getNombreProveedorAttribute()
    {
        if ($this->proveedor()->first()) {
            return $this->proveedor()->pluck('nombre')->first() . ' ' . $this->proveedor()->pluck('apellido')->first();
        }
        return 'Consumidor Final';
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega','id_bodega');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor','id_proveedor');
    }

    public function abonos(){
        return $this->hasMany('App\Models\Compras\Abono','id_compra');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Compras\Detalle','id_compra');
    }

    public function devoluciones(){
        return $this->hasMany('App\Models\Compras\Devoluciones\Devolucion', 'id_compra');
    }


}
