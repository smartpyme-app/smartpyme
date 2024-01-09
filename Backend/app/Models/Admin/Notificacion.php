<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

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
        'referencia_id',
        'empresa_id',
        'sucursal_id',
    ];

    protected $casts = ['leido' => 'string'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function usuario(){
        return $this->belongsTo('App\User', 'usuario_id');
    }

}
