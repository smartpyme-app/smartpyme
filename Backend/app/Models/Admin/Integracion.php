<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Integracion extends Model
{
    protected $table = 'integraciones';

    // ⚠️ Solo campos reales de la tabla en $fillable
    protected $fillable = [
        'id_empresa',
        'proveedor',
        'estado',
        'configuracion',
        'credenciales',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_sync_at',
        'last_error',
    ];

    protected $casts = [
        'configuracion' => 'array',
        'credenciales' => 'encrypted:array',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'credenciales',
        'access_token',
        'refresh_token',
    ];

    protected $appends = [
        'has_boxful_password',
        'is_connected',
    ];

    // 🔐 Scope multi-tenant (CRÍTICO para seguridad)
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('empresa', function (Builder $builder) {
            $user = Auth::guard('api')->user() ?? Auth::user();
            if ($user && $user->id_empresa) {
                $builder->where('integraciones.id_empresa', $user->id_empresa);
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    // ========================================
    // MÉTODOS HELPER PARA TRABAJAR CON CREDENCIALES
    // ========================================

    /**
     * Obtener una credencial específica del JSON encriptado
     * Ej: $integracion->getCredential('email')
     */
    public function getCredential(string $key, $default = null)
    {
        return $this->credenciales[$key] ?? $default;
    }

    /**
     * Actualizar UNA o varias credenciales sin sobrescribir las demás
     * Ej: $integracion->setCredentials(['email' => '...', 'password' => '...'])
     */
    public function setCredentials(array $data): self
    {
        $current = $this->credenciales ?? [];
        $this->credenciales = array_merge($current, $data);
        return $this;
    }

    /**
     * Obtener una configuración específica
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->configuracion[$key] ?? $default;
    }

    /**
     * Actualizar configuración
     */
    public function setConfig(array $data): self
    {
        $current = $this->configuracion ?? [];
        $this->configuracion = array_merge($current, $data);
        return $this;
    }

    // ========================================
    // ACCESORES/MUTADORES PARA COMPATIBILIDAD HACIA ATRÁS
    // ========================================

    /**
     * Accesor/Mutador para boxful_email (almacenado en credenciales['email'])
     */
    public function getBoxfulEmailAttribute()
    {
        return $this->getCredential('email');
    }

    public function setBoxfulEmailAttribute($value)
    {
        $this->setCredentials(['email' => $value]);
    }

    /**
     * Accesor/Mutador para boxful_client_id (almacenado en credenciales['client_id'])
     */
    public function getBoxfulClientIdAttribute()
    {
        return $this->getCredential('client_id');
    }

    public function setBoxfulClientIdAttribute($value)
    {
        $this->setCredentials(['client_id' => $value]);
    }

    /**
     * Accesor/Mutador para boxful_password (almacenado en credenciales['password'])
     */
    public function getBoxfulPasswordAttribute()
    {
        return $this->getCredential('password');
    }

    public function setBoxfulPasswordAttribute($value)
    {
        if (is_null($value) || $value === '') {
            return;
        }
        $this->setCredentials(['password' => $value]);
    }

    /**
     * Accesor/Mutador para boxful_access_token (almacenado en access_token)
     */
    public function getBoxfulAccessTokenAttribute()
    {
        return $this->access_token;
    }

    public function setBoxfulAccessTokenAttribute($value)
    {
        $this->access_token = $value;
    }

    /**
     * Accesor/Mutador para boxful_token_expires_at (almacenado en token_expires_at)
     */
    public function getBoxfulTokenExpiresAtAttribute()
    {
        return $this->token_expires_at;
    }

    public function setBoxfulTokenExpiresAtAttribute($value)
    {
        $this->token_expires_at = $value;
    }

    /**
     * Accesor/Mutador para boxful_status (almacenado en estado)
     */
    public function getBoxfulStatusAttribute()
    {
        return $this->estado;
    }

    public function setBoxfulStatusAttribute($value)
    {
        $this->estado = $value;
    }

    // ========================================
    // ACCESORES COMPUTADOS
    // ========================================

    /**
     * Determina si la contraseña de Boxful está configurada.
     */
    public function getHasBoxfulPasswordAttribute(): bool
    {
        return !empty($this->getCredential('password'));
    }

    /**
     * Determina si la integración está conectada y activa
     */
    public function getIsConnectedAttribute(): bool
    {
        return $this->estado === 'connected';
    }

    /**
     * Verifica si el token está expirado
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false; // Si no tiene expiración, asumimos que no expira
        }
        return now()->gte($this->token_expires_at);
    }

    /**
     * Verifica si el token es válido (existe y no ha expirado)
     */
    public function hasValidToken(): bool
    {
        return !empty($this->access_token) && !$this->isTokenExpired();
    }

    // ========================================
    // MÉTODOS ESTÁTICOS PARA OBTENER INTEGRACIONES ESPECÍFICAS
    // ========================================

    /**
     * Obtener la integración de Boxful para la empresa actual
     */
    public static function boxful(): ?self
    {
        return self::withoutGlobalScope('empresa')
            ->where('id_empresa', Auth::guard('api')->user()?->id_empresa ?? Auth::user()?->id_empresa)
            ->where('proveedor', 'boxful')
            ->first();
    }

    /**
     * Obtener o crear la integración de Boxful para la empresa actual
     */
    public static function boxfulOrCreate(int $empresaId): self
    {
        return self::withoutGlobalScope('empresa')
            ->firstOrCreate(
                [
                    'id_empresa' => $empresaId,
                    'proveedor' => 'boxful'
                ],
                [
                    'estado' => 'disconnected'
                ]
            );
    }

    // ========================================
    // MÉTODOS DE ACTUALIZACIÓN DE ESTADO
    // ========================================

    /**
     * Marcar la integración como conectada
     */
    public function markAsConnected(): self
    {
        $this->estado = 'connected';
        $this->last_error = null;
        $this->save();
        return $this;
    }

    /**
     * Marcar la integración como desconectada
     */
    public function markAsDisconnected(): self
    {
        $this->estado = 'disconnected';
        $this->access_token = null;
        $this->refresh_token = null;
        $this->token_expires_at = null;
        $this->save();
        return $this;
    }

    /**
     * Marcar la integración con error
     */
    public function markAsError(string $errorMessage): self
    {
        $this->estado = 'error';
        $this->last_error = $errorMessage;
        $this->save();
        return $this;
    }

    /**
     * Actualizar la fecha de última sincronización
     */
    public function updateLastSync(): self
    {
        $this->last_sync_at = now();
        $this->save();
        return $this;
    }
}