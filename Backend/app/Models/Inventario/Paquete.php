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
        'num_seguimiento',
        'num_guia',
        'piezas',
        'peso',
        'precio',
        'volumen',
        'nota',
        'id_cliente',
        'id_proveedor',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );

    protected $appends = ['nombre_cliente', 'nombre_usuario'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {

            if (Auth::user()->tipo == 'Ventas') {
                static::addGlobalScope('sucursal', function (Builder $builder) {
                    return $q->where('id_sucursal', Auth::user()->id_sucursal);
                });
            }else{
                static::addGlobalScope('empresa', function (Builder $builder) {
                    $builder->where('id_empresa', Auth::user()->id_empresa);
                });
            }
        }
        
    }

    public function getNombreClienteAttribute()
    {   $cliente = $this->cliente()->first();
        if ($cliente) {
            return $cliente->tipo == 'Persona' ? $cliente->nombre . ' ' . $cliente->apellido : $cliente->nombre_empresa;
        }
        return 'Consumidor Final';
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
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
    
    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }


}



