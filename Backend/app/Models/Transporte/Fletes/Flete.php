<?php

namespace App\Models\Transporte\Fletes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Flete extends Model {

    protected $table = 'transporte_fletes';
    protected $fillable = array(
       'fecha',
       'tipo',
       'estado',
       'cliente_id',
       'proveedor_id',
       'motorista_id',
       'cabezal_id',
       'remolque_id',
       'tipo_transporte',
       'fecha_carga',
       'fecha_descarga',
       'punto_origen',
       'punto_destino',
       'aduana_entrada',
       'aduana_salida',
       'num_seguimiento',
       'num_pedido',
       'galones',
       'subtotal',
       'motorista',
       'combustible',
       'gastos',
       'seguro',
       'otros',
       'total',
       'no_sujeto',
       'nota_facturacion',
       'nota',
       'venta_id',
       'usuario_id',
       'sucursal_id',
    );

    protected $appends = ['nombre_cliente', 'nombre_motorista', 'tiempo_viaje', 'utilidad', 'nombre_usuario', 'nombre_cabezal', 'nombre_remolque'];

    // protected static function booted()
    // {
    //     $usuario = JWTAuth::parseToken()->authenticate();

    //     if ($usuario && $usuario->tipo != 'Administrador'){
    //         static::addGlobalScope('sucursal', function (Builder $builder) use ($usuario) {
    //             $builder->where('sucursal_id', $usuario->sucursal_id);
    //         });
    //     }
    // }

    public function getTiempoViajeAttribute(){
        return \Carbon\Carbon::parse($this->fecha_carga)->diffInDays(\Carbon\Carbon::parse($this->fecha_descarga)) . ' dias';
    }

    public function getUtilidadAttribute(){
        return $this->subtotal - $this->motorista - $this->combustible - $this->gastos - $this->seguro + $this->no_sujeta;
    }

    public function getNombreClienteAttribute()
    {
        if ($this->cliente()->first()) {
            return $this->cliente()->pluck('nombre')->first();
        }
        if ($this->nombre) {
            return $this->nombre;
        }
        return 'Consumidor Final';
    }

    public function getNombreRemolqueAttribute()
    {
        return $this->remolque()->pluck('placa')->first();
    }

    public function getNombreCabezalAttribute()
    {
        return $this->cabezal()->pluck('placa')->first();
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreMotoristaAttribute()
    {
        return $this->motorista()->pluck('nombre')->first();
    }

    // Relaciones

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor','proveedor_id');
    }

    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente','cliente_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','usuario_id');
    }

    public function motorista(){
        return $this->belongsTo('App\Models\Empleados\Empleados\Empleado','motorista_id');
    }

    public function remolque(){
        return $this->belongsTo('App\Models\Transporte\Flotas\Flota','remolque_id');
    }

    public function cabezal(){
        return $this->belongsTo('App\Models\Transporte\Flotas\Flota','cabezal_id');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','sucursal_id');
    }

    public function venta(){
        return $this->hasOne('App\Models\Ventas\Venta','nota');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Transporte\Fletes\Detalle','flete_id');
    }


}
