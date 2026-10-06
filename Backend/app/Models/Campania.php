<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campania extends Model
{
    protected $table = 'campanias';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
