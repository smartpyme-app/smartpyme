<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Abono extends Model {

    protected $table = 'abonos_compras';
    protected $fillable = array(
        'fecha',
        'concepto',
        'estado',
        'nombre_de',
        'forma_pago',
        'detalle_banco',
        'mora',
        'comision',
        'total',
        'nota',
        'id_caja',
        'id_corte',
        'id_compra',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );

    protected $appends = ['nombre_proveedor'];

    public function getNombreProveedorAttribute()
    {
        $compra = $this->compra()->first();
        if ($compra) {
            return $compra->nombre_proveedor;
        }else{
            return null;
        }
    }

    public function compra(){
        return $this->belongsTo('App\Models\Compras\Compra','id_compra');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }


}
