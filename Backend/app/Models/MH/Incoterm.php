<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;

class Incoterm extends Model {

    protected $table = 'incoterms';
    protected $fillable = [
        'cod',
        'nombre'
    ];

}
