<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class AnalisisVentasMensualQueue extends Model
{
    protected $table = 'analisis_ventas_mensual_queue';

    protected $fillable = [
        'email',
        'id_empresa',
        'id_usuario',
        'params',
        'status',
        'error_message',
        'file_path',
        'file_name',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
