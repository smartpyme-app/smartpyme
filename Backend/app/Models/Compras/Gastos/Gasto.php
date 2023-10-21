<?php

namespace App\Models\Compras\Gastos;

use Illuminate\Database\Eloquent\Model;

class Gasto extends Model {

    protected $table = 'gastos';
    protected $fillable = array(
        'fecha',
        'referencia',
        'descripcion',
        'categoria_id',
        'estado',
        'metodo_pago',
        'detalle_banco',
        'condicion',
        'fecha_pago',
        'recurrente',
        'fecha_recurrente',
        'proveedor_id',
        'subtotal',
        'iva',
        'total',
        'usuario_id',
        'sucursal_id',
    );

    protected $appends = ['nombre_usuario', 'nombre_proveedor', 'nombre_categoria', 'nombre_sucursal'];

    public function getNombreUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreCategoriaAttribute(){
        return $this->categoria()->pluck('nombre')->first();
    }
    
    public function getNombreProveedorAttribute(){
        return $this->proveedor()->pluck('nombre')->first();
    }
    
    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor', 'proveedor_id');
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Compras\Gastos\Categoria', 'categoria_id');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'sucursal_id');
    }


}



