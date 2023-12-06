<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Auth;

class Dashboard extends Model {

    protected $table = 'empresa_dashboards';
    protected $fillable = array(
        'nombre',
        'plataforma',
        'tipo',
        'codigo',
        'codigo_movil',
        'empresa_id'

    );

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}
