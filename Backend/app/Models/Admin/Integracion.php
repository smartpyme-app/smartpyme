<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Integracion extends Model
{
    protected $table = 'integraciones';

    protected $fillable = [
        'id_empresa',
        'boxful_client_id',
        'boxful_email',
        'boxful_password',
        'boxful_access_token',
        'boxful_token_expires_at',
        'boxful_status'
    ];

    protected $casts = [
        'boxful_token_expires_at' => 'datetime',
        'boxful_password' => 'encrypted',
        'boxful_access_token' => 'encrypted',
    ];

    protected $hidden = [
        'boxful_password',
        'boxful_access_token',
    ];

    protected $appends = [
        'has_boxful_password',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * Set/encrypt the Boxful password if a value is provided.
     */
    public function setBoxfulPasswordAttribute($value)
    {
        if (is_null($value) || $value === '') {
            return;
        }

        $this->attributes['boxful_password'] = encrypt($value, false);
    }

    /**
     * Determine if a Boxful password has been configured.
     */
    public function getHasBoxfulPasswordAttribute(): bool
    {
        return !empty($this->attributes['boxful_password']);
    }
}
