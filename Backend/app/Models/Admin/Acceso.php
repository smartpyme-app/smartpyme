<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Acceso extends Model
{
    protected $table = 'accesos';
    protected $fillable = [
        'fecha',
        'usuario_id'
    ];

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

}
