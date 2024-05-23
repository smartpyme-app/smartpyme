<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;

class Unidad extends Model {

    protected $table = 'unidades';
    protected $fillable = [
        'cod',
        'nombre'
    ];

}
