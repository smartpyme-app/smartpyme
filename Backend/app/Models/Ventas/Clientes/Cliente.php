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
       'apellido',
       'ncr',
       'giro',
       'tipo',
       'tipo_contribuyente',
       'dui',
       'nit',
       'nombre_empresa',
       'empresa_telefono',
       'empresa_direccion',
       'direccion',
       'municipio',
       'departamento',
       'fecha_cumpleanos',
       'telefono',
       'correo',
       'nota',
       'red_social',
       'enable',
       'etiquetas',
       'id_usuario',
       'id_empresa',
    ];
    protected $appends = ['nombre_completo'];
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

    public function cotizaciones() 
    {
        return $this->hasMany('App\Models\Cotizaciones\Cotizacion', 'id_cliente');
    }

    public function eventos() 
    {
        return $this->hasMany('App\Models\Eventos\Evento', 'id_cliente');
    }

    public function ventas() 
    {
        return $this->hasMany('App\Models\Ventas\Venta', 'id_cliente');
    }

    public function creditos() 
    {
        return $this->hasMany('App\Models\Creditos\Credito', 'id_cliente');
    }
    
    public function empresa() 
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
}
