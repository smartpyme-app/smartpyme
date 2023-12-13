<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Dashboard extends Model {

    protected $table = 'dashboards';
    protected $fillable = array(
        'titulo',
        'plataforma',
        'tipo',
        'codigo_embed',
        'codigo_embed_movil',
        'id_empresa'

    );

    protected $appends = ['nombre_empresa'];

    public function getNombreEmpresaAttribute(){
        return $this->empresa()->pluck('nombre')->first();
    }

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            if (Auth::user()->id_empresa != 2) {
                static::addGlobalScope('empresa', function (Builder $builder) {
                    $builder->where('id_empresa', Auth::user()->id_empresa);
                });
            }
        }
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
