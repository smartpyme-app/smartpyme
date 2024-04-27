<?php

namespace App\Models\Licencias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Empresa extends Model
{
    protected $table = 'licencia_empresas';
    protected $fillable = [
        'id_licencia',
        'id_empresa',
    ];

    protected $appends = ['nombre_empresa'];

    // protected static function boot()
    // {
    //     parent::boot();

    //     if (Auth::check()) {
    //         static::addGlobalScope('empresa', function (Builder $builder) {
    //             $builder->where('id_licencia', Auth::user()->empresa()->first()->licencia()->pluck('id')->first());
    //         });
    //     }
    // }

    public function getNombreEmpresaAttribute(){
        return $this->empresa()->pluck('nombre')->first();
    }

    public function licencia(){
        return $this->belongsTo('App\Models\Licencias\Licencia', 'id_licencia');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

}
