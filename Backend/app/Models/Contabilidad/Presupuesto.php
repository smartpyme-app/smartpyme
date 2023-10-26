<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Presupuesto extends Model {

    protected $table = 'presupuestos';
    protected $fillable = array(
        'titulo',
        'fecha_inicio',
        'fecha_fin',
        'ingresos',
        'egresos',
        'alquiler',
        'varios',
        'mantenimiento',
        'marketing',
        'materia_prima',
        'comisiones',
        'combustible',
        'planilla',
        'servicios',
        'prestamos',
        'publicidad',
        'enable',
        'id_empresa',
    );

    protected $casts = ['enable' => 'string'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
