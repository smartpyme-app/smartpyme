<?php

namespace App\Models\Transporte\Flotas;

use Illuminate\Database\Eloquent\Model;

class Flota extends Model {

    protected $table = 'transporte_flotas';
    protected $fillable = array(
        'img',
        'propietario',
        'placa',
        'tipo',
        'vin',
        'num_chasis',
        'num_motor',
        'marca',
        'modelo',
        'capacidad',
        'anio',
        'color',
        'kilometraje',
        'tipo_combustible',
        'nota',
        'ultimo_mantenimiento',
        'proximo_mantenimiento',
        'vencimiento_tarjeta',
        'vencimiento_garantia',
        'vencimiento_poliza',
        'activo',
        'usuario_id',
        'sucursal_id'
    );


    public function mantenimientos(){
        return $this->hasMany('App\Models\Transporte\Mantenimientos\Mantenimiento','flota_id');
    }

    public function fletes(){
        if ($this->tipo == 'Remolque') {
            return $this->belongsTo('App\Models\Transporte\Fletes\Flete','id', 'remolque_id');
        }
        return $this->belongsTo('App\Models\Transporte\Fletes\Flete','id', 'cabezal_id');
    }



}
