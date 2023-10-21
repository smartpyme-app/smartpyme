<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model {

    protected $table = 'cajas';
    protected $fillable = array(
        'nombre',
        'tipo',
        'descripcion',
        'sucursal_id'
    );

    protected $appends = ['nombre_sucursal'];

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function corte(){
        return $this->hasOne('App\Models\Admin\Corte')->latest();
    }

    public function ventasDia(){
        return $this->hasMany('App\Models\Ventas\Venta', 'caja_id')->whereDate('fecha', $this->corte()->pluck('fecha')->first())->where('estado', '!=', 'Anulada');
    }

    public function cortesDia(){
        return $this->hasMany('App\Models\Admin\Corte')->whereDate('fecha', $this->corte()->pluck('fecha')->first());
    }

    public function cortes(){
        return $this->hasMany('App\Models\Admin\Corte');
    }

    public function devolucionesDia(){
        return $this->hasMany('App\Models\Ventas\DevolucionVenta', 'caja_id')->whereDate('fecha', $this->corte()->pluck('fecha')->first());
    }

    public function formasPago(){
        return $this->hasMany('App\Models\Admin\FormaPago');
    }

    public function documentos(){
        return $this->hasMany('App\Models\Admin\Documento');
    }

    public function usuarios(){
    	return $this->hasMany('App\Models\User');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'sucursal_id');
    }


}



