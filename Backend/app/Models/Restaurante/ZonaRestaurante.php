<?php

namespace App\Models\Restaurante;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Model;

class ZonaRestaurante extends Model
{
    protected $table = 'restaurante_zonas';

    protected $fillable = [
        'id_empresa',
        'id_sucursal',
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function mesas()
    {
        return $this->hasMany(Mesa::class, 'zona_id');
    }
}
