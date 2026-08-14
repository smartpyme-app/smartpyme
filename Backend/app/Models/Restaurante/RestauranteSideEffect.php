<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class RestauranteSideEffect extends Model
{
    protected $table = 'restaurante_side_effects';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const TYPE_COMANDA_TICKET = 'comanda_ticket_notify';

    public const TYPE_PRECUENTA_TICKET = 'precuenta_ticket_notify';

    protected $fillable = [
        'id_empresa',
        'type',
        'resource_type',
        'resource_id',
        'status',
        'attempts',
        'payload',
        'last_error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
        'resource_id' => 'integer',
        'id_empresa' => 'integer',
    ];
}
