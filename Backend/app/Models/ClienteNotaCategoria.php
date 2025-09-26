<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteNotaCategoria extends Model
{
    use HasFactory;

    protected $table = 'cliente_notas_categorias';

    protected $fillable = [
        'id_nota',
        'categoria',
        'subcategoria',
        'score_relevancia'
    ];

    protected $casts = [
        'score_relevancia' => 'decimal:2'
    ];

    // Relaciones
    public function nota(): BelongsTo
    {
        return $this->belongsTo(ClienteNota::class, 'id_nota');
    }

    // Scopes
    public function scopePorCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    public function scopePorSubcategoria($query, $subcategoria)
    {
        return $query->where('subcategoria', $subcategoria);
    }

    public function scopeAltaRelevancia($query)
    {
        return $query->where('score_relevancia', '>=', 0.8);
    }

    // Métodos de utilidad
    public function getCategoriaFormateadaAttribute(): string
    {
        $categorias = [
            'preferencias' => 'Preferencias',
            'quejas' => 'Quejas',
            'comentarios' => 'Comentarios',
            'seguimiento' => 'Seguimiento'
        ];

        return $categorias[$this->categoria] ?? ucfirst($this->categoria);
    }

    public function getSubcategoriaFormateadaAttribute(): string
    {
        $subcategorias = [
            'productos_favoritos' => 'Productos Favoritos',
            'metodos_pago' => 'Métodos de Pago',
            'horarios_preferidos' => 'Horarios Preferidos',
            'problemas_tecnico' => 'Problemas Técnicos',
            'problemas_atencion' => 'Problemas de Atención',
            'sugerencias' => 'Sugerencias',
            'feedback' => 'Feedback',
            'seguimiento_comercial' => 'Seguimiento Comercial',
            'seguimiento_soporte' => 'Seguimiento Soporte'
        ];

        return $subcategorias[$this->subcategoria] ?? ucfirst($this->subcategoria);
    }

    public function getIconoCategoriaAttribute(): string
    {
        $iconos = [
            'preferencias' => '⭐',
            'quejas' => '⚠️',
            'comentarios' => '💬',
            'seguimiento' => '📋'
        ];

        return $iconos[$this->categoria] ?? '📝';
    }
}
