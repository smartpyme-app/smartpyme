<?php

namespace App\Models\Restaurante;

use App\Models\Admin\Empresa;
use App\Models\Concerns\AuditableModel;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;

class Reserva extends AuditableModel
{
    protected static function auditModule(): string
    {
        return 'restaurante';
    }

    protected $table = 'reservas_restaurante';

    protected $fillable = [
        'mesa_id',
        'id_empresa',
        'fecha_reserva',
        'hora_reserva',
        'cliente_nombre',
        'cliente_telefono',
        'observaciones',
        'estado',
        'usuario_id',
        'cliente_id',
    ];

    protected $casts = [
        'fecha_reserva' => 'date',
    ];

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
