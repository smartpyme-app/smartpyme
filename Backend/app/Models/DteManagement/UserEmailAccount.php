<?php

namespace App\Models\DteManagement;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Bodega;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserEmailAccount extends Model
{
    protected $table = 'user_email_accounts';

    protected $fillable = [
        'id_empresa',
        'user_id',
        'provider',
        'email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_user',
        'imap_password',
        'id_sucursal',
        'id_bodega',
        'actualizar_inventario',
        'is_active',
        'last_sync_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'actualizar_inventario' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'imap_password',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    /**
     * Encrypt access_token on set, decrypt on get.
     */
    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = $value ? encrypt($value) : null;
    }

    public function getAccessTokenAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * Encrypt refresh_token on set, decrypt on get.
     */
    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = $value ? encrypt($value) : null;
    }

    public function getRefreshTokenAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * Encrypt imap_password on set, decrypt on get.
     */
    public function setImapPasswordAttribute($value)
    {
        $this->attributes['imap_password'] = $value ? encrypt($value) : null;
    }

    public function getImapPasswordAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'id_bodega');
    }

    public function dteDocuments()
    {
        return $this->hasMany(DteDocument::class, 'user_email_account_id');
    }

    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class, 'user_email_account_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }
}
