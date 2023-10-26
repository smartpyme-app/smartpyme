<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Documento extends Model {

    protected $table = 'documentos';
    protected $fillable = array(
        'nombre',
        'prefijo',
        'correlativo',
        'inicial',
        'final',
        'rangos',
        'numero_autorizacion',
        'resolucion',
        'fecha',
        'nota',
        'activo',
        'caja_id',
        'id_sucursal'

    );

    protected $appends = ['nombre_sucursal'];
    protected $casts = ['activo' => 'string'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }


}



