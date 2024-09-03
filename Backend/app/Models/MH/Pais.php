<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;

class Pais extends Model {

    protected $table = 'paises';
    protected $fillable = [
        'cod',
        'nombre',
    ];

}
