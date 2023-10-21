<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

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

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}
