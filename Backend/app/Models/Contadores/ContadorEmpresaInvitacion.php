<?php

namespace App\Models\Contadores;

use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContadorEmpresaInvitacion extends Model
{
    protected $table = 'contador_empresa_invitaciones';

    protected $fillable = [
        'token_hash',
        'email_admin',
        'id_empresa_cliente',
        'id_empresa_despacho',
        'id_usuario_invito',
        'estado',
        'expira_en',
        'respondida_en',
    ];

    protected $casts = [
        'expira_en' => 'datetime',
        'respondida_en' => 'datetime',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_ACEPTADA = 'aceptada';
    public const ESTADO_CANCELADA = 'cancelada';
    public const ESTADO_RECHAZADA = 'rechazada';
    public const ESTADO_EXPIRADA = 'expirada';

    public function empresaCliente(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa_cliente');
    }

    public function empresaDespacho(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa_despacho');
    }

    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_invito');
    }

    public function estaVigente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE && $this->expira_en->isFuture();
    }
}
