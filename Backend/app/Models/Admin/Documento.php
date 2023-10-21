<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model {

    protected $table = 'documentos';
    protected $fillable = array(
        'nombre',
        'prefijo',
        'actual',
        'inicial',
        'final',
        'rangos',
        'numero_autorizacion',
        'resolucion',
        'fecha',
        'nota',
        'caja_id'

    );

    public function caja(){
        return $this->belongsTo('App\Models\Admin\Caja', 'caja_id');
    }


}



