<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class DireccionOrigen extends Model
{
    protected $table = 'direcciones_origen';

    protected $fillable = [
        'id_empresa',
        'alias',
        'nombre_contacto',
        'direccion',
        'referencia',
        'telefono',
        'codigo_area',
        'latitud',
        'longitud',
        'boxful_state_id',
        'boxful_city_id',
        'boxful_address_id',
        'es_predeterminada'
    ];

    protected $casts = [
        'es_predeterminada' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
}
