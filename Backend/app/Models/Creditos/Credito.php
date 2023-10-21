<?php

namespace App\Models\Creditos;

use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Credito extends Model {
    
    protected $table = 'creditos';
    protected $fillable = [
        'fecha',
        'venta_id',
        'total',
        'interes_anual',
        'tipo_cuota',
        'numero_de_cuotas',
        'forma_de_pago',
        'periodo_de_gracia',
        'prima',
        'nota',
        'cliente_id',
        'usuario_id',
        'empresa_id'
    ];

    protected $appends = ['nombre_usuario', 'nombre_cliente', 'cuota', 'mora', 
                        'cantidad_de_pagos', 'saldo', 'fecha_de_pago', 'fecha_vencida',
                        'total_intereses', 'total_abonos', 'total_pagos', 'total_moras'
                        ];

    public function getFechaAttribute($value)
    {
         return Carbon::parse($value)->format('Y-m-d');
    }

    public function getNombreUsuarioAttribute() 
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreClienteAttribute() 
    {
        return $this->cliente()->pluck('nombre')->first();
    }

    public function getMoraAttribute(){

        $fechaLimite = $this->fecha_de_pago;
        if ($fechaLimite > date('Y-m-d')) {
            return true;
        }else{
            return false;
        }
    }

    public function getFechaDePagoAttribute(){
        if ($this->saldo > 0){

            if ($this->forma_de_pago == 'Dias') {
                $fecha = \Carbon\Carbon::parse($this->fecha)->addDays($this->cantidad_de_pagos + 1)->format('d/m/Y');
            }
            if ($this->forma_de_pago == 'Semanas') {
                $fecha = \Carbon\Carbon::parse($this->fecha)->addWeeks($this->cantidad_de_pagos + 1)->format('d/m/Y');
            }
            if ($this->forma_de_pago == 'Meses') {
                $fecha = \Carbon\Carbon::parse($this->fecha)->addMonths($this->cantidad_de_pagos + 1)->format('d/m/Y');
            }
        }
        else{
            $fecha = 'Completado';
        }
        return $fecha;
    }

    public function getFechaVencidaAttribute(){
        if ($this->fecha_de_pago < date('d-m-Y'))
            return true;
        else
            return false;
    }

    public function getSaldoAttribute(){
        
        $descuentos = $this->pagos()->sum('descuento');
        return round($this->total - ($this->total_abonos + $this->prima + $descuentos), 4);

    }

    public function getTotalAbonosAttribute(){
        
        $pagos = $this->pagos()->sum('abono');
        return round($pagos, 4);

    }

    public function getTotalInteresesAttribute(){
        
        $intereses = $this->pagos()->sum('interes');
        return round($intereses, 4);

    }

    public function getTotalPagosAttribute(){
        
        $total_pagos = $this->total_abonos + $this->total_intereses;
        return round($total_pagos, 4);

    }

    public function getTotalMorasAttribute(){
        
        return $this->pagos()->sum('mora');

    }

    public function getCantidadDePagosAttribute(){
        
        return $this->pagos()->count();

    }

    public function getCuotaAttribute(){
        // P: Valor del préstamo
        // I: Tasa de interés nominal anual
        // M: Número de capitalizaciones en el año
        // N: Número de años
        $p = ($this->total - $this->prima);
        $i = $this->interes_anual;
        $m = 12;

        if ($i == 0)
            return ($this->total - $this->prima) / $this->numero_de_cuotas;

        if ($i > 1){
            $i = $i / 100;
        }

        $n = 1;
        for ($j = 0; $j < $this->numero_de_cuotas; $j++){
            $n = $n * (1 + ($i / $m));
        }

        return (($p * ($i / $m) * $n) / ($n - 1));

    
    }

    
    public function pagos() 
    {
        return $this->hasMany('App\Models\Creditos\Pago');
    }

    public function venta() 
    {
        return $this->belongsTo('App\Models\Ventas\Venta', 'venta_id');
    }

    public function usuario() 
    {
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function cliente() 
    {
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'cliente_id');
    }

    public function empresa() 
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }

}
