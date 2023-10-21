<?php

namespace App\Models\Contabilidad\Activos;

use Illuminate\Database\Eloquent\Model;

class Activo extends Model {

    protected $table = 'empresa_activos';
    protected $fillable = array(
        'nombre',
        'fecha_compra',
        'fecha_retiro',
        'referencia',
        'estado',
        'categoria_id',
        'numero_de_serie',
        'descripcion',
        'ubicacion',
        'valor_compra',
        'deprecicion',
        'vida_util',
        'valor_actual',
        'responsable_id',
        'usuario_id',
        'sucursal_id',
        'empresa_id',
    );

    protected $appends = ['nombre_usuario', 'nombre_categoria', 'nombre_sucursal'];

    public function getNombreUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function getNombreCategoriaAttribute(){
        return $this->categoria()->pluck('nombre')->first();
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Contabilidad\Activos\Categoria', 'categoria_id');
    }
    
    public function usuario(){
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'sucursal_id');
    }


}



