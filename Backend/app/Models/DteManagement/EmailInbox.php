<?php

namespace App\Models\DteManagement;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Model;

class EmailInbox extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_PAUSED = 'PAUSED';

    public const STATUS_DISABLED = 'DISABLED';

    protected $table = 'email_inboxes';

    protected $fillable = [
        'id_empresa',
        'user_email_account_id',
        'token',
        'email',
        'status',
        'purpose',
        'last_email_at',
        'last_dte_at',
        'last_error_at',
        'last_error_message',
        'emails_received',
        'emails_rejected',
        'dtes_imported',
        'created_by_user_id',
        'revoked_at',
    ];

    protected $casts = [
        'last_email_at' => 'datetime',
        'last_dte_at' => 'datetime',
        'last_error_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function userEmailAccount()
    {
        return $this->belongsTo(UserEmailAccount::class, 'user_email_account_id');
    }

    public function isAccepting(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
