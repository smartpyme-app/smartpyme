<?php

namespace App\Models\Inventario\Categorias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Cuenta extends Model
{
    protected $table = 'categoria_sucursal_cuenta';
    protected $fillable = array(
        'id_categoria',
        'id_sucursal',
        'id_cuenta_contable',
        'id_cuenta_contable_costo',
    );


    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Inventario\Categorias\Categoria', 'id_categoria');
    }

    public function cuenta(){
        return $this->hasMany('App\Models\Contabilidad\Cuenta', 'id_cuenta_contable');
    }

}
