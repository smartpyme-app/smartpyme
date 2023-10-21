<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class FormaDePago extends Model {

    protected $table = 'empresa_formas_de_pago';
    protected $fillable = array(
        'nombre',
        'orden',
        'empresa_id'

    );

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}
