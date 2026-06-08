<?php

namespace App\Models\DteManagement;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SyncLog extends Model
{
    protected $table = 'sync_logs';

    protected $fillable = [
        'id_empresa',
        'user_email_account_id',
        'started_at',
        'finished_at',
        'status',
        'emails_scanned',
        'dtes_found',
        'dtes_processed',
        'dtes_failed',
        'error_message',
        'failure_details',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'failure_details' => 'array',
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
     * Relationships
     */
    public function userEmailAccount()
    {
        return $this->belongsTo(UserEmailAccount::class, 'user_email_account_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * Scopes
     */
    public function scopeForEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }
}
