<?php

namespace App\Models\Restaurante;

use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SesionMesa extends Model
{
    protected $table = 'restaurante_sesiones_mesa';

    protected $fillable = [
        'mesa_id',
        'usuario_id',
        'id_empresa',
        'id_sucursal',
        'num_comensales',
        'observaciones',
        'estado',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }

    public function mesero()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function ordenDetalle()
    {
        return $this->hasMany(OrdenDetalle::class, 'sesion_id');
    }

    public function comandas()
    {
        return $this->hasMany(Comanda::class, 'sesion_id');
    }

    public function preCuentas()
    {
        return $this->hasMany(PreCuenta::class, 'sesion_id');
    }
}
