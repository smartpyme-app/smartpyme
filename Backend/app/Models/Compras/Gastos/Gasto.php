<?php

namespace App\Models\Compras\Gastos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
class Gasto extends Model {

    protected $table = 'egresos';
    protected $fillable = [
        'tipo_dte',
        'numero_control',
        'codigo_generacion',
        'sello_mh',
        'fecha',
        'referencia',
        'tipo_documento',
        'concepto',
        'id_categoria',
        'tipo',
        'estado',
        'forma_pago',
        'detalle_banco',
        'condicion',
        'fecha_pago',
        'recurrente',
        'fecha_recurrente',
        'id_proveedor',
        'sub_total',
        'renta_retenida',
        'iva',
        'iva_percibido',
        'total',
        'nota',
        'id_area_empresa',
        'id_usuario',
        'id_proyecto',
        'id_empresa',
        'id_sucursal',
        'dte',
        'dte_invalidacion',
        'otros_impuestos',
        'es_retaceo',
        'clasificacion',
        'tipo_operacion',
        'sector',
        'tipo_gasto',
    ];

    protected $appends = ['nombre_usuario', 'nombre_proveedor', 'nombre_categoria', 'nombre_sucursal', 'id_departamento','nombre_departamento'];

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

    public function getDteInvalidacionAttribute($value) 
    {
        return is_string($value) ? json_decode($value,true) : $value;
    }

    public function getNombreUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreCategoriaAttribute(){
        return $this->categoria()->pluck('nombre')->first();
    }
    
    public function getNombreProveedorAttribute()
    {   $proveedor = $this->proveedor()->first();
        if ($proveedor) {
            return $proveedor->tipo == 'Empresa' ? $proveedor->nombre_empresa : $proveedor->nombre . ' ' . $proveedor->apellido;
        }
        return 'Consumidor Final';
    }
    
    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor', 'id_proveedor');
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Compras\Gastos\Categoria', 'id_categoria');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function retaceoGasto()
    {
        return $this->hasOne('App\Models\Compras\Retaceo\RetaceoGasto', 'id_gasto');
    }

    public function areaEmpresa(){
        return $this->belongsTo('App\Models\Compras\Gastos\AreaEmpresa', 'id_area_empresa');
    }

    public function departamento(){
        return $this->belongsTo('App\Models\Admin\Departamento', 'id_departamento');
    }

    public function getIdDepartamentoAttribute(){
        return $this->areaEmpresa ? $this->areaEmpresa->id_departamento : null;
    }

    public function getDepartamentoAttribute(){
        return $this->areaEmpresa ? $this->areaEmpresa->departamento : null;
    }

    public function getNombreDepartamentoAttribute(){
        return $this->areaEmpresa ? $this->areaEmpresa->departamento->nombre : null;
    }


}



