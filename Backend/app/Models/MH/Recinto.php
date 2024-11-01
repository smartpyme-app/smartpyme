<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;

class Recinto extends Model {

    protected $table = 'recintos';
    protected $fillable = [
        'cod',
        'nombre'
    ];

}
