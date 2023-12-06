<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model {

    // use SoftDeletes;
    protected $table = 'empresas';
    protected $fillable = [
        'nombre',
        'propietario',
        'sector',
        'giro',
        'nit',
        'ncr',
        'tipo_contribuyente',
        'departamento',
        'municipio',
        'direccion',
        'telefono',
        'correo',
        'municipio',
        'departamento',
        'logo',
        'propina',
        'valor_inventario',
        'vender_sin_stock',
        'user_limit',
        'editar_precio_venta',
        'wompi_aplicativo',
        'wompi_id',
        'wompi_secret',
        'ips'
    ];

    public function limiteUsuarios(){

        if($this->usuarios->where('enable', true)->count() < $this->user_limit)
            return false;
        
        return true;
    }


    public function usuarios(){
        return $this->hasMany('App\Models\User', 'id_empresa');
    }

    public function ventas(){
        return $this->hasMany('App\Models\Ventas\Venta', 'id_empresa');
    }

    public function proveedores(){
        return $this->hasMany('App\Models\Compras\Proveedores\Proveedor', 'id_empresa');
    }

    public function documentos(){
        return $this->hasMany('App\Models\Admin\Documento', 'id_empresa');
    }

    public function formasDePago(){
        return $this->hasMany('App\Models\Admin\FormasDePago', 'id_empresa');
    }

    public function clientes(){
        return $this->hasMany('App\Models\Ventas\Clientes\Cliente', 'id_empresa');
    }

    public function productos(){
        return $this->hasMany('App\Models\Inventario\Producto', 'id_empresa');
    }

    public function materiales(){
        return $this->hasMany('App\Models\MateriaPrima', 'id_empresa');
    }

    public function promociones(){
        return $this->hasMany('App\Models\Promocion', 'id_empresa');
    }

    public function dashboards(){
        return $this->hasMany('App\Models\Admin\Dashboard', 'id_empresa');
    }

    public function egresos(){
        return $this->hasMany('App\Models\Contabilidad\Gasto', 'id_empresa');
    }

    public function compras(){
        return $this->hasMany('App\Models\Compras\Compra', 'id_empresa');
    }

    public function canales(){
        return $this->hasMany('App\Models\Admin\Canal', 'id_empresa');
    }

    public function sucursales(){
        return $this->hasMany('App\Models\Admin\Sucursal', 'id_empresa');
    }

    public function deventas(){
        return $this->hasMany('App\Models\Ventas\Devoluciones\Devolucion', 'id_empresa');
    }
    public function decompras(){
        return $this->hasMany('App\Models\Compras\Devoluciones\Devolucion', 'id_empresa');
    }

    public function recordatorios(){
        return $this->hasMany('App\Models\Admin\Notification', 'id_empresa');
    }

    public function ajustes(){
        return $this->hasMany('App\Models\Inventario\Ajustes\Ajuste', 'id_empresa');
    }

    public function traslados(){
        return $this->hasMany('App\Models\Inventario\Traslados\Traslado', 'id_empresa');
    }

    public function presupuestos(){
        return $this->hasMany('App\Models\Contabilidad\Presupuesto', 'id_empresa');
    }
    
    public function pagos(){
        return $this->hasMany('App\Models\Recibo', 'id_empresa');
    }

    public function getRecibosPendientesAttribute(){
        return $this->pagos()->where('estado', 'Pendiente')->count();
    }

    public function getLastPayAttribute(){
        return $this->pagos()->pluck('created_at')->last();
    }

    public function getNextPayAttribute(){

        $next_pay = $this->pagos()->pluck('created_at')->last();
        if($this->pagos()->count())
            $next_pay->addMonth(1);
        
        return $next_pay;
    }

    public function getLeidosAttribute(){
        $re = $this->recordatorios()->where('leido', false)->get();
        return $re->count();
    }

    public function categorias(){
        return $this->hasMany(Categoria::class, 'id_empresa');
    }


}
