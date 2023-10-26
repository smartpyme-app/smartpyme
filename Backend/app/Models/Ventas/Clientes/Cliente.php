<?php

namespace App\Models\Ventas\Clientes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model {

    // use SoftDeletes;
    protected $table = 'clientes';
    protected $fillable = [
       'nombre',
       'ncr',
       'giro',
       'tipo_contribuyente',
       'dui',
       'nit',
       'fecha_nacimiento',
       'direccion',
       'municipio',
       'departamento',
       'telefono',
       'correo',
       'sexo',
       'profesion',
       'estado_civil',
       'nota',
       'etiquetas',
       'usuario_id',
       'empresa_id',
    ];

    protected $casts = ['enable' => 'boolean'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
    }

    public function getEtiquetasAttribute($value) 
    {
        return is_string($value) ? json_decode($value) : $value;
    }

    public function ordenes() 
    {
        return $this->hasMany('App\Models\Ordenes\Orden', 'cliente_id');
    }

    public function eventos() 
    {
        return $this->hasMany('App\Models\Eventos\Evento', 'cliente_id');
    }

    public function ventas() 
    {
        return $this->hasMany('App\Models\Ventas\Venta', 'cliente_id');
    }

    public function fletes() 
    {
        return $this->hasMany('App\Models\Transporte\Fletes\Flete', 'cliente_id');
    }
    
    public function creditos() 
    {
        return $this->hasMany('App\Models\Creditos\Credito', 'cliente_id');
    }
    
    public function empresa() 
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }
}
