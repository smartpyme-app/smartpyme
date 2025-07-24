<?php

namespace App\Models\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Authorization extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'authorization_type_id',
        'authorizeable_type',
        'authorizeable_id',
        'id_empresa',
        'requested_by',
        'authorized_by',
        'status',
        'description',
        'data',
        'notes',
        'operation_type',
        'operation_data', 
        'operation_hash',
        'expires_at',
        'authorized_at'
    ];

    protected $casts = [
        'data' => 'array',          
        'operation_data' => 'array', 
        'expires_at' => 'datetime',
        'authorized_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($authorization) {
            $authorization->code = self::generateUniqueCode();
            if (!$authorization->id_empresa && Auth::check()) {
                $authorization->id_empresa = Auth::user()->id_empresa;
            }
        });

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function empresa()
    {
        return $this->belongsTo(\App\Models\Admin\Empresa::class, 'id_empresa');
    }

    public function authorizationType()
    {
        return $this->belongsTo(AuthorizationType::class);
    }

    public function authorizeable()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function authorizer()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function isExpired()
    {
        return $this->expires_at < now();
    }

    public function isPending()
    {
        return $this->status === 'pending';
        // return $this->status === 'pending' && !$this->isExpired(); sera asi si quieren que no se permitan autorizar al expirar
    }

    private static function generateUniqueCode()
    {
        do {
            $code = strtoupper(substr(md5(time() . rand()), 0, 8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
                    // ->where('expires_at', '>', now()); sera asi si quieren que no se permitan autorizar al expirar
    }
}
