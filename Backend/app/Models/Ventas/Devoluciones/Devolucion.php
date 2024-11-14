<?php

namespace App\Models\Ventas\Devoluciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Devolucion extends Model {

    protected $table = 'devoluciones_venta';
    protected $fillable = array(
        'tipo_dte',
        'numero_control',
        'codigo_generacion',
        'sello_mh',
        'dte',
        'dte_invalidacion',
        'fecha',
        'correlativo',
        'id_documento',
        'sub_total',
        'no_sujeta',
        'exenta',
        'cuenta_a_terceros',
        'total',
        'iva',
        'iva_retenido',
        'observaciones',
        'id_cliente',
        'id_empresa',
        'enable',
        'id_venta',
        'id_usuario'
    );

    protected $appends = ['nombre_cliente', 'nombre_usuario', 'nombre_documento'];
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

    public function getDteAttribute($value) 
    {
        return is_string($value) ? json_decode($value,true) : $value;
    }

    public function getDteInvalidacionAttribute($value) 
    {
        return is_string($value) ? json_decode($value,true) : $value;
    }

    public function getNombreDocumentoAttribute(){
        return $this->documento()->pluck('nombre')->first();
    }

    public function getNombreClienteAttribute()
    {   $cliente = $this->cliente()->first();
        if ($cliente) {
            return $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }
        return 'Consumidor Final';
    }

    public function getNombreAttribute($name)
    {
        return strtoupper($name);
    }


    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }


    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente','id_cliente');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function sucursal()
    {
        return $this->venta ? $this->venta->sucursal : null;
    }

    public function documento(){
        return $this->belongsTo('App\Models\Admin\Documento','id_documento');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','id_venta');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Ventas\Devoluciones\Detalle','id_devolucion_venta');
    }


}
