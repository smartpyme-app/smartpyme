<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KardexMasivoQueue extends Model
{
    use HasFactory;
    
    protected $table = 'kardex_masivo_queue';
    
    protected $fillable = [
        'email',
        'id_empresa',
        'status',
        'error_message',
        'file_path',
        'file_name',
        'total_records',
        'started_at',
        'completed_at'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }
    
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
