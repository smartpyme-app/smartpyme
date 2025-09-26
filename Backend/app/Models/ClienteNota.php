<?php

namespace App\Models;

use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClienteNota extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_cliente',
        'tipo',
        'titulo',
        'contenido',
        'responsable',
        'prioridad',
        'metadata',
        'fecha_interaccion',
        'hora_interaccion',
        'fecha_seguimiento',
        'resuelto',
        'resolucion'
    ];

    protected $casts = [
        'metadata' => 'array',
        'fecha_interaccion' => 'date',
        'hora_interaccion' => 'datetime:H:i',
        'fecha_seguimiento' => 'date',
        'resuelto' => 'boolean'
    ];

    // Relaciones
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(ClienteNotaCategoria::class, 'id_nota');
    }

    // Scopes
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorPrioridad($query, $prioridad)
    {
        return $query->where('prioridad', $prioridad);
    }

    public function scopePorResponsable($query, $responsable)
    {
        return $query->where('responsable', $responsable);
    }

    public function scopePendientesSeguimiento($query)
    {
        return $query->whereNotNull('fecha_seguimiento')
                    ->where('fecha_seguimiento', '>=', now()->toDateString())
                    ->where('resuelto', false);
    }

    public function scopeResueltas($query)
    {
        return $query->where('resuelto', true);
    }

    // Métodos de utilidad
    public function getTipoFormateadoAttribute(): string
    {
        $tipos = [
            'preferencias' => 'Preferencias',
            'quejas' => 'Quejas',
            'comentarios' => 'Comentarios',
            'visita' => 'Visita',
            'llamada' => 'Llamada',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email'
        ];

        return $tipos[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function getPrioridadFormateadaAttribute(): string
    {
        $prioridades = [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta'
        ];

        return $prioridades[$this->prioridad] ?? ucfirst($this->prioridad);
    }

    public function getIconoTipoAttribute(): string
    {
        $iconos = [
            'preferencias' => '⭐',
            'quejas' => '⚠️',
            'comentarios' => '💬',
            'visita' => '👤',
            'llamada' => '📞',
            'whatsapp' => '💬',
            'email' => '📧'
        ];

        return $iconos[$this->tipo] ?? '📝';
    }

    public function getColorPrioridadAttribute(): string
    {
        $colores = [
            'low' => '#10b981', // Verde
            'medium' => '#f59e0b', // Amarillo
            'high' => '#ef4444' // Rojo
        ];

        return $colores[$this->prioridad] ?? '#6b7280';
    }
}
