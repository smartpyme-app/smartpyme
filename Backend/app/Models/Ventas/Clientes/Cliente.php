<?php

namespace App\Models\Ventas\Clientes;

use App\Models\MH\ActividadEconomica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;
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
       'pais',
       'cod_pais',
       'municipio',
       'distrito',
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

       'cod_giro',
       'cod_municipio',
       'cod_distrito',
       'cod_departamento',
       'tipo_persona',
       'tipo_documento',
       'codigo_cliente',
       
    ];
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

    public function getNombreActividadEconomicaAttribute()
    {
        return $this->actividadEconomica ? $this->actividadEconomica->nombre : null;
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

    public function paquetes() 
    {
        return $this->hasMany('App\Models\Inventario\Paquete', 'id_cliente');
    }

    public function creditos() 
    {
        return $this->hasMany('App\Models\Creditos\Credito', 'id_cliente');
    }
    
    public function empresa() 
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function contactos()
    {
        return $this->hasMany(ContactoCliente::class, 'id_cliente')
                    ->where('estado', 1);
    }
    
    public function actividadEconomica()
    {
        return $this->belongsTo(ActividadEconomica::class, 'cod_giro', 'cod');
    }
}
