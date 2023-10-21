<?php

namespace App\Models\Transporte\Mantenimientos;

use Illuminate\Database\Eloquent\Model;

class Mantenimiento extends Model {

    protected $table = 'transporte_mantenimientos';
    protected $fillable = array(
        'fecha',
        'estado',
        'flota_id',
        'tipo',
        'total',
        'nota',
        'bodega_id',
        'usuario_id',
        'sucursal_id',
    );

    protected $appends = ['nombre_usuario', 'nombre_flota'];

    public function getNombreFlotaAttribute()
    {
        return $this->flota()->pluck('placa')->first();
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'sucursal_id');
    }

    public function bodega()
    {
        return $this->belongsTo('App\Models\Inventario\Bodega', 'bodega_id');
    }

    public function usuario()
    {
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function flota()
    {
        return $this->belongsTo('App\Models\Transporte\Flotas\Flota', 'flota_id');
    }

    public function detalles()
    {
        return $this->hasMany('App\Models\Transporte\Mantenimientos\Detalle', 'mantenimiento_id');
    }


}
