<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Notificacion extends Model
{
    protected $table = 'notificaciones';
    protected $fillable = [
        'titulo',
        'descripcion',
        'tipo',
        'categoria',
        'prioridad',
        'leido',
        'referencia',
        'id_referencia',
        'id_empresa',
        'id_sucursal',
    ];

    protected $casts = ['leido' => 'string'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function usuario(){
        return $this->belongsTo('App\User', 'id_usuario');
    }

}
