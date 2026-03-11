<?php

namespace App\Models;

use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteVisita extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_cliente',
        'tipo_visita',
        'titulo',
        'descripcion',
        'responsable',
        'prioridad',
        'productos_mencionados',
        'servicios_mencionados',
        'valor_potencial',
        'estado',
        'fecha_visita',
        'hora_visita',
        'duracion_minutos',
        'resultados',
        'proximos_pasos',
        'fecha_seguimiento',
        'requiere_seguimiento'
    ];

    protected $casts = [
        'productos_mencionados' => 'array',
        'servicios_mencionados' => 'array',
        'valor_potencial' => 'decimal:2',
        'fecha_visita' => 'date',
        'hora_visita' => 'datetime:H:i',
        'fecha_seguimiento' => 'date',
        'requiere_seguimiento' => 'boolean'
    ];

    // Relaciones
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    // Scopes
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_visita', $tipo);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorResponsable($query, $responsable)
    {
        return $query->where('responsable', $responsable);
    }

    public function scopeProgramadas($query)
    {
        return $query->where('estado', 'programada');
    }

    public function scopeRealizadas($query)
    {
        return $query->where('estado', 'realizada');
    }

    public function scopePendientesSeguimiento($query)
    {
        return $query->where('requiere_seguimiento', true)
                    ->whereNotNull('fecha_seguimiento')
                    ->where('fecha_seguimiento', '>=', now()->toDateString());
    }

    // Métodos de utilidad
    public function getTipoFormateadoAttribute(): string
    {
        $tipos = [
            'presencial' => 'Visita Presencial',
            'virtual' => 'Reunión Virtual',
            'llamada' => 'Llamada Telefónica',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email'
        ];

        return $tipos[$this->tipo_visita] ?? ucfirst($this->tipo_visita);
    }

    public function getEstadoFormateadoAttribute(): string
    {
        $estados = [
            'programada' => 'Programada',
            'realizada' => 'Realizada',
            'cancelada' => 'Cancelada'
        ];

        return $estados[$this->estado] ?? ucfirst($this->estado);
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
            'presencial' => '👤',
            'virtual' => '💻',
            'llamada' => '📞',
            'whatsapp' => '💬',
            'email' => '📧'
        ];

        return $iconos[$this->tipo_visita] ?? '📝';
    }

    public function getColorEstadoAttribute(): string
    {
        $colores = [
            'programada' => '#3b82f6', // Azul
            'realizada' => '#10b981', // Verde
            'cancelada' => '#ef4444' // Rojo
        ];

        return $colores[$this->estado] ?? '#6b7280';
    }

    public function getDuracionFormateadaAttribute(): string
    {
        if (!$this->duracion_minutos) {
            return 'No especificada';
        }

        $horas = floor($this->duracion_minutos / 60);
        $minutos = $this->duracion_minutos % 60;

        if ($horas > 0) {
            return $minutos > 0 ? "{$horas}h {$minutos}m" : "{$horas}h";
        }

        return "{$minutos}m";
    }
}
