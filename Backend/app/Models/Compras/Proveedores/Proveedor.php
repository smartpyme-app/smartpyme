<?php

namespace App\Models\Compras\Proveedores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model {
    
    // use SoftDeletes;
    protected $table = 'proveedores';
    protected $fillable = array(
        'nombre',
        'apellido',
        'ncr',
        'giro',
        'tipo',
        'tipo_contribuyente',
        'dui',
        'nit',
        'nombre_empresa',
        'direccion',
        'municipio',
        'departamento',
        'telefono',
        'correo',
        'nota',
        'enable',
        'etiquetas',
        'id_usuario',
        'id_empresa',
    );

    protected $appends = ['nombre_completo'];
    protected $casts = ['enable' => 'boolean'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function getNombreCompletoAttribute() 
    {
        return $this->nombre . ' ' . ($this->apellido ? $this->apellido : '');
    }

    public function getEtiquetasAttribute($value) 
    {
        return is_string($value) ? json_decode($value) : $value;
    }

    public function setEtiquetasAttribute($valor)
    {
        $this->attributes['etiquetas'] = json_encode($valor);
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Admin\Categoria', 'id_categoria');
    }

    public function comprasPendientes(){
        return $this->hasMany('App\Models\Compras\Compra', 'id_proveedor')->where('estado', 'Pendiente');
    }

    public function compras(){
        return $this->hasMany('App\Models\Compras\Compra', 'id_proveedor');
    }

}

