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
        'id_cuenta_contable_inventario',
        'id_cuenta_contable_costo',
        'id_cuenta_contable_ingresos',
        'id_cuenta_contable_devoluciones',
    );

    protected $appends = ['nombre_sucursal', 'nombre_cuenta_inventario', 'nombre_cuenta_costo', 'nombre_cuenta_devoluciones', 'nombre_cuenta_ingresos'];

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function getNombreCuentaInventarioAttribute()
    {
        return $this->cuenta()->pluck('nombre')->first();
    }

    public function getNombreCuentaCostoAttribute()
    {
        return $this->cuentaCosto()->pluck('nombre')->first();
    }

    public function getNombreCuentaIngresosAttribute()
    {
        return $this->cuentaIngresos()->pluck('nombre')->first();
    }

    public function getNombreCuentaDevolucionesAttribute()
    {
        return $this->cuentaDevoluciones()->pluck('nombre')->first();
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Inventario\Categorias\Categoria', 'id_categoria');
    }

    public function cuenta(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta_contable_inventario');
    }

    public function cuentaCosto(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta_contable_costo');
    }

    public function cuentaIngresos(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta_contable_ingresos');
    }

    public function cuentaDevoluciones(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta_contable_devoluciones');
    }

}
