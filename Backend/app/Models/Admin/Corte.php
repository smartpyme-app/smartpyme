<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Ventas\Venta;
use App\Models\Ventas\DevolucionVenta;

class Corte extends Model {

    protected $table = 'caja_cortes';
    protected $fillable = array(
        'fecha',
        'saldo_inicial',
        'saldo_final',
        'apertura',
        'cierre',
        'caja_id',
        'supervisor_id',
        'usuario_id'
    );
    protected $appends = ['usuario', 'supervisor', 'estado', 'ventas_cantidad', 'ventas_suma'];

    public function getUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }

    public function getSupervisorAttribute(){
        return $this->supervisor()->pluck('name')->first();
    }

    public function getEstadoAttribute(){
        return $this->cierre ? 'Cerrada' : 'Abierta';
    }

    public function getSaldoFinalAttribute($value){
        if ($this->estado == 'Abierta')
            return $this->saldo_final = $this->ventas_suma;
        else
            return $this->saldo_final = $value;
    }

    public function getVentasCantidadAttribute(){
        return $this->ventas()->count();
    }

    public function getVentasSumaAttribute(){
        return $this->ventas()->sum('total');
    }

    public function ventas(){
        return $this->hasMany('App\Models\Ventas\Venta', 'corte_id')->where('estado', '!=', 'Anulada');
    }

    public function devoluciones(){
        return $this->hasMany('App\Models\Ventas\Devoluciones\Devolucion', 'corte_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function supervisor(){
        return $this->belongsTo('App\Models\User', 'supervisor_id');
    }

    public function caja(){
    	return $this->belongsTo('App\Models\Admin\Caja', 'caja_id');
    }


}



