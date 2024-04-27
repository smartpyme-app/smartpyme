<?php

namespace App\Models\Licencias;

use Illuminate\Database\Eloquent\Model;

class Licencia extends Model
{
    protected $table = 'licencias';
    protected $fillable = [
        'num_licencias',
        'id_empresa',
    ];

    protected $appends = ['nombre_empresa', 'num_empresas'];

    public function getNombreEmpresaAttribute(){
        return $this->empresa()->pluck('nombre')->first();
    }

    public function getNumEmpresasAttribute(){
        return $this->empresas()->count();
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function empresas(){
        return $this->hasMany('App\Models\Licencias\Empresa', 'id_licencia');
    }

    public function usuarios(){
        return \App\Models\User::whereIn('id_empresa', $this->empresas()->pluck('id_empresa')->toArray());
    }

}
