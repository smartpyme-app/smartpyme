<?php

namespace App\Models\Transporte\Motoristas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Empleados\Empleados\Empleado;

class Motorista extends Empleado {


    protected static function booted()
    {

        static::addGlobalScope('esMotorista', function (Builder $builder){
                $builder->where('cargo_id', 1);
        });
    }

    public function fletes()
    {
        return $this->hasMany('App\Models\Transporte\Fletes\Flete', 'motorista_id');
    }

}
