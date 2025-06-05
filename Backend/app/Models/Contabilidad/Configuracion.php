<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Configuracion extends Model
{
    use HasFactory;
    protected $table = 'contabilidad_configuracion';
    protected $fillable = [
        'id_cuenta_ventas',
        'id_cuenta_devoluciones_ventas',
        'id_cuenta_inventario',
        'id_cuenta_costo_venta',
        'id_cuenta_ajustes_inventario',
        'id_cuenta_cxc',
        'id_cuenta_devoluciones_clientes',
        'id_cuenta_cxp',
        'id_cuenta_devoluciones_proveedores',

        'id_cuenta_iva_ventas',
        'id_cuenta_iva_retenido_ventas',
        'id_cuenta_renta_retenida_ventas',
        'id_cuenta_iva_compras',
        'id_cuenta_iva_retenido_compras',
        'id_cuenta_renta_retenida_compras',
        
        'id_cuenta_perdida_ajuste',
        'id_cuenta_ganancia_ajuste',

        'generar_partidas', // Manual, Auto
        'id_empresa',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }


    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
    
}
