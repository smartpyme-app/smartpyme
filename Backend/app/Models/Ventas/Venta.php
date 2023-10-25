<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Venta extends Model {

    protected $table = 'ventas';
    protected $fillable = array(
        'fecha',
        'correlativo',
        'estado',
        'tipo',
        'canal_id',
        'metodo_pago',
        'tipo_documento',
        'condicion',
        'referencia',
        'nombre',
        'fecha_pago',
        'recibido',
        'iva_percibido',
        'iva_retenido',
        'iva',
        'subcosto',
        'descuento',
        'subtotal',
        'no_sujeta',
        'exenta',
        'gravada',
        'total',
        'nota',
        'caja_id',
        'bodega_id',
        'corte_id',
        'cliente_id',
        'usuario_id',
        'vendedor_id',
        'id_empresa',
        'id_sucursal'
    );

    protected $appends = ['nombre_cliente', 'nombre_usuario', 'nombre_canal'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
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

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreCanalAttribute(){
        return $this->canal()->pluck('nombre')->first();
    }

    // public function getSaldoAttribute(){
    //     return $this->total - $this->credito()->pagos()->sum('abono');
    // }

    // Relaciones

    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente','cliente_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','usuario_id');
    }

    public function vendedor(){
        return $this->belongsTo('App\Models\Empleados\Empleados\Empleado','vendedor_id');
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega','bodega_id');
    }

    public function canal(){
        return $this->belongsTo('App\Models\Admin\Canal','canal_id');
    }

    public function documento(){
        return $this->belongsTo('App\Models\Admin\Documento','tipo_documento', 'nombre');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }


    public function detalles(){
        return $this->hasMany('App\Models\Ventas\Detalle','id_venta');
    }


}
