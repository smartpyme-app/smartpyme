<?php

namespace App\Models\Compras;

use App\Models\Compras\Retaceo\Retaceo;
use App\Models\Compras\Retaceo\RetaceoCompra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
class Compra extends Model {

    protected $table = 'compras';
    protected $fillable = array(
        'tipo_dte',
        'numero_control',
        'codigo_generacion',
        'sello_mh',
        'fecha',
        'estado',
        // 'tipo',
        'forma_pago',
        'tipo_documento',
        // 'condicion',
        'fecha_pago',
        'referencia',
        'num_serie',
        'num_orden_compra',
        'detalle_banco',
        // 'aplicada_inventario',
        'notas',
        'id_proveedor',
        'id_authorization',
        'no_sujeta',
        'exenta',
        'percepcion',
        'renta_retenida',
        // 'iva_retenido',
        'descuento',
        'recurrente',
        'cotizacion',
        'iva',
        'sub_total',
        'observaciones',
        'total',
        'id_bodega',
        'id_proyecto',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
        'dte',
        'dte_invalidacion',
        'no_sujeta',
        'es_retaceo',
        'tipo_operacion',
        'tipo_clasificacion',
        'tipo_sector',
        'tipo_costo_gasto',

    );

    protected $appends = ['nombre_proveedor', 'nombre_usuario', 'nombre_sucursal'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function getDteAttribute($value) 
    {
        return is_string($value) ? json_decode($value,true) : $value;
    }

    public function getSaldoAttribute(){
        return round($this->total - $this->abonos()->where('estado', 'Confirmado')->sum('total'),2);
    }


    public function getNombreSucursalAttribute()
    {
        if ($this->sucursal()->first()) {
            return $this->sucursal()->pluck('nombre')->first();
        }
        return '';
    }

    public function getNombreProveedorAttribute()
    {   $proveedor = $this->proveedor()->first();
        if ($proveedor) {
            return $proveedor->tipo == 'Empresa' ? $proveedor->nombre_empresa : $proveedor->nombre . ' ' . $proveedor->apellido;
        }
        return 'Consumidor Final';
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega','id_bodega');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor','id_proveedor');
    }

    public function abonos(){
        return $this->hasMany('App\Models\Compras\Abono','id_compra');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function proyecto()
    {
        return $this->belongsTo('App\Models\Contabilidad\Proyecto', 'id_proyecto');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Compras\Detalle','id_compra');
    }

    public function devoluciones(){
        return $this->hasMany('App\Models\Compras\Devoluciones\Devolucion', 'id_compra');
    }

    /**
     * Retaceo vinculado (una compra solo puede estar en un retaceo).
     */
    public function retaceo()
    {
        return $this->hasOneThrough(
            Retaceo::class,
            RetaceoCompra::class,
            'id_compra',
            'id',
            'id',
            'id_retaceo'
        );
    }


}
