<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Empresa extends Model {

    // use SoftDeletes;
    protected $table = 'empresas';
    protected $fillable = [
        'nombre',
        'nombre_propietario',
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
        'sucursal_limit',
        'iva',
        'moneda',
        'pais',
        'total',
        'forma_pago',
        'link_pago',
        'fecha_ultimo_pago',
        'editar_precio_venta',
        'agrupar_detalles_venta',
        'editar_descripcion_venta',
        'impresion_en_facturacion',
        'vendedor_detalle_venta',
        'venta_consigna',
        'plan',
        'cobra_iva',
        'tipo_plan',
        'fecha_cancelacion',
        'referido',
        'campania',
        'wompi_aplicativo',
        'wompi_id',
        'wompi_secret',
        'modulo_paquetes',
        'modulo_citas',
        'modulo_proyectos',
        'activo',
        'cotizacion_compras_terminos',

        //Facturacíon
        'facturacion_electronica',
        'enviar_dte',
        'fe_ambiente',
        'cod_municipio',
        'cod_departamento',
        'cod_actividad_economica',
        'tipo_establecimiento',
        'mh_pwd_certificado',
        'mh_usuario',
        'mh_contrasena',
        'cod_estable_mh',
        'cod_estable',

        //Permiso para vendedores
        'vendedor_inventario',
    ];

    protected $casts = [
        'enviar_dte' => 'boolean',
        'facturacion_electronica' => 'boolean',
    ];

    protected $appends = ['estado_plan', 'generar_partidas'];

    public function limiteUsuarios(){
        if($this->usuarios->where('enable', true)->count() < $this->user_limit)
            return false;
        return true;
    }

    public function limiteSucursales(){
        if($this->sucursales->where('activo', true)->count() < $this->sucursal_limit)
            return false;
        return true;
    }

    public function getEstadoPlanAttribute(){
        $pago_mes = $this->pagos()->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'))->count();

        $dias_creaccion = $this->created_at->diffInDays(Carbon::now());

        if ($dias_creaccion <= 15) {
            return ['estado' => 'Prueba', 'dias_faltantes' => (15 - $dias_creaccion)];
        }
        if ($dias_creaccion > 15) {
            return ['estado' => 'Pendiente de pago'];
        }

        return $this->pagos->count();
    }

    public function getGenerarPartidasAttribute(){
        return $this->contabilidad()->pluck('generar_partidas')->first();
    }


    public function contabilidad(){
        return $this->hasOne('App\Models\Contabilidad\Configuracion', 'id_empresa');
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

    public function licencia(){
        return $this->hasOne('App\Models\Licencias\Licencia', 'id_empresa');
    }

    public function dashboards(){
        return $this->hasMany('App\Models\Admin\Dashboard', 'id_empresa');
    }

    public function gastos(){
        return $this->hasMany('App\Models\Compras\Gastos\Gasto', 'id_empresa');
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
        return $this->hasMany('App\Models\Inventario\Ajuste', 'id_empresa');
    }

    public function impuestos(){
        return $this->hasMany('App\Models\Admin\Impuesto', 'id_empresa');
    }

    public function traslados(){
        return $this->hasMany('App\Models\Inventario\Traslado', 'id_empresa');
    }

    public function presupuestos(){
        return $this->hasMany('App\Models\Contabilidad\Presupuesto', 'id_empresa');
    }

    public function categorias(){
        return $this->hasMany('App\Models\Inventario\Categorias\Categoria', 'id_empresa');
    }

    public function pagos(){
        return $this->hasMany('App\Models\Transaccion', 'id_empresa');
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



}
