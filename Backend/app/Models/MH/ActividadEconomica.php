<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;

class ActividadEconomica extends Model {

    protected $table = 'actividades_economicas';
    protected $fillable = [
        'cod',
        'nombre'
    ];



}
