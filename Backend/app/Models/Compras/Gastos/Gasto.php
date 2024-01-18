<?php

namespace App\Models\Compras\Gastos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;
class Gasto extends Model {

    protected $table = 'egresos';
    protected $fillable = array(
        'fecha',
        'referencia',
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
        'proveedor',
        'sub_total',
        'iva',
        'total',
        'id_usuario',
        'id_empresa',
        'id_sucursal',
    );

    protected $appends = ['nombre_usuario', 'nombre_proveedor', 'nombre_categoria', 'nombre_sucursal'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
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
            return $proveedor->tipo == 'Persona' ? $proveedor->nombre . ' ' . $proveedor->apellido : $proveedor->nombre_empresa;
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


}



