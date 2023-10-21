<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';
    protected $fillable = [
        'estado',
        'inicio',
        'fin',
        'plan_id',
        'usuario_id',
    ];

    public function usuario(){
        return $this->belongsTo('App\User', 'usuario_id');
    }

}
