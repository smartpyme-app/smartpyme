<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class Abono extends Model {

    protected $table = 'venta_abonos';
    protected $fillable = array(
        'fecha',
        'concepto',
        'estado',
        'metodo_pago',
        'detalle_banco',
        'mora',
        'comision',
        'total',
        'nota',
        'caja_id',
        'corte_id',
        'venta_id',
        'cliente_id',
        'usuario_id',
        'sucursal_id',
    );

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','venta_id');
    }

    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente','cliente_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','usuario_id');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','sucursal_id');
    }


}
