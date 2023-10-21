<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Wompi extends Model {

    protected $table = 'empresa_wompi';
    protected $fillable = array(
        'identificador',
        'aplicativo',
        'secret',
        'empresa_id'
    );

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}
