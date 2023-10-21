<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Impuesto extends Model {

    protected $table = 'empresa_impuestos';
    protected $fillable = array(
        'nombre',
        'porcentaje',
        'empresa_id'

    );

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}
