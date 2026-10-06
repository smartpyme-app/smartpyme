<?php

namespace App\Models\Contadores;

use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContadorEmpresaAcceso extends Model
{
    protected $table = 'contador_empresa_accesos';

    protected $fillable = [
        'id_usuario_contador',
        'id_empresa',
        'estado',
        'permisos',
        'invitado_por_user_id',
        'revoked_at',
    ];

    protected $casts = [
        'permisos' => 'array',
        'revoked_at' => 'datetime',
    ];

    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_REVOCADO = 'revocado';
    public const ESTADO_PENDIENTE = 'pendiente';

    public static function permisosPorDefecto(): array
    {
        return ['ver', 'registrar', 'aprobar'];
    }

    public function contador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_contador');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitado_por_user_id');
    }
}
