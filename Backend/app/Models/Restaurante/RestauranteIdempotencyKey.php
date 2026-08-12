<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class RestauranteIdempotencyKey extends Model
{
    protected $table = 'restaurante_idempotency_keys';

    protected $fillable = [
        'id_empresa',
        'user_id',
        'operation',
        'idempotency_key',
        'status',
        'response_code',
        'response_body',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
