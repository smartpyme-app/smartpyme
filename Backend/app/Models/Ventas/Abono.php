<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class Abono extends Model {

    protected $table = 'recibos';
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
        'id_venta',
        'id_cliente',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','id_venta');
    }

    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente','id_cliente');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }


}
