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
        'estado',
        'requiere_seguimiento',
        'fecha_seguimiento',
        'resolucion',
        'fecha_interaccion',
        'hora_interaccion',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'fecha_interaccion' => 'date',
        'hora_interaccion' => 'datetime:H:i',
        'fecha_seguimiento' => 'date',
        'requiere_seguimiento' => 'boolean'
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
        return $query->where('requiere_seguimiento', true)
                    ->whereNotNull('fecha_seguimiento')
                    ->where('fecha_seguimiento', '>=', now()->toDateString())
                    ->whereIn('estado', ['activo', 'pendiente', 'en_proceso']);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeEnProceso($query)
    {
        return $query->where('estado', 'en_proceso');
    }

    public function scopeResueltas($query)
    {
        return $query->where('estado', 'resuelto');
    }

    public function scopeArchivadas($query)
    {
        return $query->where('estado', 'archivado');
    }

    public function scopeRequierenSeguimiento($query)
    {
        return $query->where('requiere_seguimiento', true);
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
            'high' => 'Alta',
            'urgent' => 'Urgente'
        ];

        return $prioridades[$this->prioridad] ?? ucfirst($this->prioridad);
    }

    public function getEstadoFormateadoAttribute(): string
    {
        $estados = [
            'activo' => 'Activo',
            'pendiente' => 'Pendiente',
            'en_proceso' => 'En Proceso',
            'resuelto' => 'Resuelto',
            'archivado' => 'Archivado'
        ];

        return $estados[$this->estado] ?? ucfirst($this->estado);
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
            'high' => '#ef4444', // Rojo
            'urgent' => '#dc2626' // Rojo oscuro
        ];

        return $colores[$this->prioridad] ?? '#6b7280';
    }

    public function getColorEstadoAttribute(): string
    {
        $colores = [
            'activo' => '#10b981', // Verde
            'pendiente' => '#f59e0b', // Amarillo
            'en_proceso' => '#3b82f6', // Azul
            'resuelto' => '#6b7280', // Gris
            'archivado' => '#9ca3af' // Gris claro
        ];

        return $colores[$this->estado] ?? '#6b7280';
    }

    // Métodos de negocio
    public function marcarComoResuelto(string $resolucion = null): void
    {
        $this->update([
            'estado' => 'resuelto',
            'resolucion' => $resolucion,
            'requiere_seguimiento' => false
        ]);
    }

    public function archivar(): void
    {
        $this->update([
            'estado' => 'archivado',
            'requiere_seguimiento' => false
        ]);
    }

    public function activar(): void
    {
        $this->update(['estado' => 'activo']);
    }

    public function marcarEnProceso(): void
    {
        $this->update(['estado' => 'en_proceso']);
    }
}
