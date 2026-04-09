<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Paquete extends Model {

    use SoftDeletes;
    protected $table = 'paquetes';
    protected $fillable = array(
        'fecha',
        'wr',
        'transportista',
        'consignatario',
        'transportador',
        'estado',
        'embalaje',
        'num_seguimiento',
        'num_guia',
        'piezas',
        'peso',
        'precio',
        'volumen',
        'cuenta_a_terceros',
        'otros',
        'total',
        'nota',
        'id_venta',
        'id_venta_detalle',
        'id_cliente',
        'id_asesor',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );

    protected $appends = ['nombre_cliente', 'nombre_asesor', 'nombre_usuario'];

    protected static function boot()
    {
        parent::boot();

        // JWT usa el guard `api`; Auth::check() sin guard usa `web` y queda en falso en la API,
        // así que antes no se registraba el scope y se listaban paquetes de todas las empresas.
        static::addGlobalScope('empresa', function (Builder $builder) {
            foreach (['api', 'web'] as $guard) {
                if (Auth::guard($guard)->check()) {
                    $table = $builder->getModel()->getTable();
                    $builder->where($table . '.id_empresa', Auth::guard($guard)->user()->id_empresa);

                    return;
                }
            }
        });
    }

     public function getNombreClienteAttribute()
    {   $cliente = $this->cliente()->first();
        if ($cliente) {
            return $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }
        return 'Consumidor Final';
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreAsesorAttribute()
    {
        return $this->asesor()->pluck('name')->first();
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'id_cliente');
    }
    
    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor', 'id_proveedor');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','id_venta');
    }

    public function ventaDetalle(){
        return $this->belongsTo('App\Models\Ventas\Detalle','id_venta_detalle');
    }
    
    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }
    
    public function asesor(){
        return $this->belongsTo('App\Models\User', 'id_asesor');
    }


}



