<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

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
        'predeterminado',
        'activo',
        'caja_id',
        'id_sucursal',
        'id_empresa'
    );

    protected $appends = ['nombre_sucursal'];
    protected $casts = ['predeterminado' => 'string', 'activo' => 'string'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
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



