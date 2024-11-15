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

    protected $appends = ['nombre_sucursal', 'nombre_cuenta', 'nombre_cuenta_costo'];

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function getNombreCuentaAttribute()
    {
        return $this->cuenta()->pluck('nombre')->first();
    }

    public function getNombreCuentaCostoAttribute()
    {
        return $this->cuentaCosto()->pluck('nombre')->first();
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Inventario\Categorias\Categoria', 'id_categoria');
    }

    public function cuenta(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta_contable');
    }

    public function cuentaCosto(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta_contable_costo');
    }

}
