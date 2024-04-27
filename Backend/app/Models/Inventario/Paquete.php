<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Paquete extends Model {

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

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
        
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



