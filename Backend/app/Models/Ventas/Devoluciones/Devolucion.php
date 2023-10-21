<?php

namespace App\Models\Ventas\Devoluciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Devolucion extends Model {

    protected $table = 'ventas_devoluciones';
    protected $fillable = array(
        'fecha',
        'estado',
        'tipo_documento',
        'referencia',
        'recibido',
        'subcosto',
        'descuento',
        'subtotal',
        'no_sujeta',
        'exenta',
        'gravada',
        'iva_percibido',
        'iva_retenido',
        'iva',
        'total',
        'nota',
        'venta_id',
        'caja_id',
        'corte_id',
        'cliente_id',
        'usuario_id',
        'sucursal_id'
    );

    protected $appends = ['nombre_cliente', 'nombre_usuario', 'exenta', 'gravada', 'no_sujeta'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario->tipo != 'Administrador') {
            static::addGlobalScope('sucursal', function (Builder $builder) use ($usuario) {
                $builder->where('sucursal_id', $usuario->sucursal_id);
            });
        }
    }


    public function getNombreClienteAttribute()
    {
        return $this->cliente()->first() ? $this->cliente()->pluck('nombre')->first() : '';
    }

    public function getNombreAttribute($name)
    {
        return strtoupper($name);
    }


    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getExentaAttribute(){
        return $this->detalles()->get()->sum('Exenta');
    }

    public function getGravadaAttribute(){
        return $this->detalles()->get()->sum('Gravada');
    }

    public function getNoSujetaAttribute(){
        return $this->detalles()->get()->sum('No Sujeta');
    }

    // Relaciones

    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente','cliente_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','usuario_id');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','sucursal_id');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','venta_id');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Ventas\Devoluciones\Detalle','devolucion_id');
    }


}
