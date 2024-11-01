<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;

class Regimen extends Model {

    protected $table = 'regimenes';
    protected $fillable = [
        'cod',
        'nombre'
    ];

}
