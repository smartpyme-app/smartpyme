<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteNota;
use App\Models\ClienteVisita;
use App\Models\ClienteNotaCategoria;
use App\Constants\ClienteNotasConstants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ClienteNotasService
{
    /**
     * Obtener todas las notas de un cliente
     */
    public function getNotasCliente(int $clienteId, array $filtros = []): array
    {
        $query = ClienteNota::where('id_cliente', $clienteId)
            ->with('categorias')
            ->orderBy('fecha_interaccion', 'desc')
            ->orderBy('hora_interaccion', 'desc');

        // Aplicar filtros
        if (isset($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (isset($filtros['prioridad'])) {
            $query->where('prioridad', $filtros['prioridad']);
        }

        if (isset($filtros['responsable'])) {
            $query->where('responsable', $filtros['responsable']);
        }

        if (isset($filtros['fecha_desde'])) {
            $query->where('fecha_interaccion', '>=', $filtros['fecha_desde']);
        }

        if (isset($filtros['fecha_hasta'])) {
            $query->where('fecha_interaccion', '<=', $filtros['fecha_hasta']);
        }

        if (isset($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (isset($filtros['requiere_seguimiento'])) {
            $query->where('requiere_seguimiento', $filtros['requiere_seguimiento']);
        }

        $notas = $query->get();

        $resultado = $notas->map(function ($nota) {
            return [
                'id' => $nota->id,
                'tipo' => $nota->tipo,
                'tipo_formateado' => $nota->tipo_formateado,
                'icono' => $nota->icono_tipo,
                'titulo' => $nota->titulo,
                'contenido' => $nota->contenido,
                'responsable' => $nota->responsable,
                'prioridad' => $nota->prioridad,
                'prioridad_formateada' => $nota->prioridad_formateada,
                'color_prioridad' => $nota->color_prioridad,
                'fecha_interaccion' => $nota->fecha_interaccion->format('Y-m-d'),
                'hora_interaccion' => $nota->hora_interaccion->format('H:i'),
                'estado' => $nota->estado,
                'estado_formateado' => $nota->estado_formateado,
                'color_estado' => $nota->color_estado,
                'requiere_seguimiento' => $nota->requiere_seguimiento,
                'fecha_seguimiento' => $nota->fecha_seguimiento ? $nota->fecha_seguimiento->format('Y-m-d') : null,
                'resolucion' => $nota->resolucion,
                'metadata' => $nota->metadata,
                'categorias' => $nota->categorias->map(function ($categoria) {
                    return [
                        'categoria' => $categoria->categoria,
                        'subcategoria' => $categoria->subcategoria,
                        'categoria_formateada' => $categoria->categoria_formateada,
                        'subcategoria_formateada' => $categoria->subcategoria_formateada,
                        'icono' => $categoria->icono_categoria,
                        'score_relevancia' => $categoria->score_relevancia
                    ];
                }),
                'created_at' => $nota->created_at->format('Y-m-d H:i:s')
            ];
        })->toArray();
        
        return $resultado;
    }

    /**
     * Validar datos de una nota usando las constantes
     */
    public function validarNota(array $datos): array
    {
        $errores = [];

        // Validar título
        if (empty($datos['titulo'])) {
            $errores[] = 'El título es requerido';
        } elseif (strlen($datos['titulo']) < ClienteNotasConstants::TITULO_MIN_LENGTH) {
            $errores[] = 'El título debe tener al menos ' . ClienteNotasConstants::TITULO_MIN_LENGTH . ' caracteres';
        } elseif (strlen($datos['titulo']) > ClienteNotasConstants::LIMITE_CARACTERES_TITULO) {
            $errores[] = 'El título no puede exceder ' . ClienteNotasConstants::LIMITE_CARACTERES_TITULO . ' caracteres';
        }

        // Validar contenido
        if (empty($datos['contenido'])) {
            $errores[] = 'El contenido es requerido';
        } elseif (strlen($datos['contenido']) < ClienteNotasConstants::CONTENIDO_MIN_LENGTH) {
            $errores[] = 'El contenido debe tener al menos ' . ClienteNotasConstants::CONTENIDO_MIN_LENGTH . ' caracteres';
        } elseif (strlen($datos['contenido']) > ClienteNotasConstants::LIMITE_CARACTERES_CONTENIDO) {
            $errores[] = 'El contenido no puede exceder ' . ClienteNotasConstants::LIMITE_CARACTERES_CONTENIDO . ' caracteres';
        }

        // Validar tipo de interacción
        $tiposValidos = array_values([
            ClienteNotasConstants::TIPO_VISITA_PRESENCIAL,
            ClienteNotasConstants::TIPO_LLAMADA_TELEFONICA,
            ClienteNotasConstants::TIPO_WHATSAPP,
            ClienteNotasConstants::TIPO_EMAIL,
            ClienteNotasConstants::TIPO_NOTA_INTERNA,
            ClienteNotasConstants::TIPO_PREFERENCIAS,
            ClienteNotasConstants::TIPO_QUEJAS
        ]);

        if (!in_array($datos['tipo'], $tiposValidos)) {
            $errores[] = 'Tipo de interacción no válido';
        }

        // Validar prioridad
        $prioridadesValidas = [
            ClienteNotasConstants::PRIORIDAD_BAJA,
            ClienteNotasConstants::PRIORIDAD_MEDIA,
            ClienteNotasConstants::PRIORIDAD_ALTA,
            ClienteNotasConstants::PRIORIDAD_URGENTE
        ];

        if (!in_array($datos['prioridad'], $prioridadesValidas)) {
            $errores[] = 'Prioridad no válida';
        }

        // Validar estado
        $estadosValidos = [
            ClienteNotasConstants::ESTADO_NOTA_ACTIVO,
            ClienteNotasConstants::ESTADO_NOTA_PENDIENTE,
            ClienteNotasConstants::ESTADO_NOTA_EN_PROCESO,
            ClienteNotasConstants::ESTADO_NOTA_RESUELTO,
            ClienteNotasConstants::ESTADO_NOTA_ARCHIVADO
        ];

        if (!in_array($datos['estado'], $estadosValidos)) {
            $errores[] = 'Estado no válido';
        }

        // Validar fecha de seguimiento
        if (isset($datos['fecha_seguimiento']) && !empty($datos['fecha_seguimiento'])) {
            $fechaSeguimiento = Carbon::parse($datos['fecha_seguimiento']);
            $fechaMaxima = Carbon::now()->addDays(ClienteNotasConstants::FECHA_FUTURA_MAX_DAYS);
            
            if ($fechaSeguimiento->isPast()) {
                $errores[] = 'La fecha de seguimiento no puede ser en el pasado';
            } elseif ($fechaSeguimiento->gt($fechaMaxima)) {
                $errores[] = 'La fecha de seguimiento no puede ser más de ' . ClienteNotasConstants::FECHA_FUTURA_MAX_DAYS . ' días en el futuro';
            }
        }

        return $errores;
    }

    /**
     * Obtener configuración por defecto para nuevas notas
     */
    public function getConfiguracionDefault(): array
    {
        return [
            'tiempo_seguimiento_default' => ClienteNotasConstants::TIEMPO_SEGUIMIENTO_DEFAULT,
            'tiempo_recordatorio' => ClienteNotasConstants::TIEMPO_RECORDATORIO,
            'limite_caracteres_titulo' => ClienteNotasConstants::LIMITE_CARACTERES_TITULO,
            'limite_caracteres_contenido' => ClienteNotasConstants::LIMITE_CARACTERES_CONTENIDO,
            'notas_por_pagina' => ClienteNotasConstants::NOTAS_POR_PAGINA,
            'filtro_fecha_desde_default' => ClienteNotasConstants::FILTRO_FECHA_DESDE_DEFAULT,
            'filtro_estado_default' => ClienteNotasConstants::FILTRO_ESTADO_DEFAULT,
            'filtro_prioridad_default' => ClienteNotasConstants::FILTRO_PRIORIDAD_DEFAULT,
            'tipos_interaccion' => ClienteNotasConstants::TEXTOS_TIPO,
            'prioridades' => ClienteNotasConstants::TEXTOS_PRIORIDAD,
            'estados' => ClienteNotasConstants::TEXTOS_ESTADO,
            'responsables' => ClienteNotasConstants::RESPONSABLES,
            'categorias' => ClienteNotasConstants::CATEGORIAS,
            'iconos' => ClienteNotasConstants::ICONOS,
            'colores_prioridad' => ClienteNotasConstants::COLORES_PRIORIDAD,
            'colores_estado' => ClienteNotasConstants::COLORES_ESTADO
        ];
    }

    /**
     * Obtener todas las visitas de un cliente
     */
    public function getVisitasCliente(int $clienteId, array $filtros = []): array
    {
        $query = ClienteVisita::where('id_cliente', $clienteId)
            ->orderBy('fecha_visita', 'desc')
            ->orderBy('hora_visita', 'desc');

        // Aplicar filtros
        if (isset($filtros['tipo_visita'])) {
            $query->where('tipo_visita', $filtros['tipo_visita']);
        }

        if (isset($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (isset($filtros['responsable'])) {
            $query->where('responsable', $filtros['responsable']);
        }

        if (isset($filtros['fecha_desde'])) {
            $query->where('fecha_visita', '>=', $filtros['fecha_desde']);
        }

        if (isset($filtros['fecha_hasta'])) {
            $query->where('fecha_visita', '<=', $filtros['fecha_hasta']);
        }

        $visitas = $query->get();

        return $visitas->map(function ($visita) {
            return [
                'id' => $visita->id,
                'tipo_visita' => $visita->tipo_visita,
                'tipo_formateado' => $visita->tipo_formateado,
                'icono' => $visita->icono_tipo,
                'titulo' => $visita->titulo,
                'descripcion' => $visita->descripcion,
                'responsable' => $visita->responsable,
                'prioridad' => $visita->prioridad,
                'prioridad_formateada' => $visita->prioridad_formateada,
                'productos_mencionados' => $visita->productos_mencionados,
                'servicios_mencionados' => $visita->servicios_mencionados,
                'valor_potencial' => $visita->valor_potencial,
                'estado' => $visita->estado,
                'estado_formateado' => $visita->estado_formateado,
                'color_estado' => $visita->color_estado,
                'fecha_visita' => $visita->fecha_visita->format('Y-m-d'),
                'hora_visita' => $visita->hora_visita->format('H:i'),
                'duracion_minutos' => $visita->duracion_minutos,
                'duracion_formateada' => $visita->duracion_formateada,
                'resultados' => $visita->resultados,
                'proximos_pasos' => $visita->proximos_pasos,
                'fecha_seguimiento' => $visita->fecha_seguimiento ? $visita->fecha_seguimiento->format('Y-m-d') : null,
                'requiere_seguimiento' => $visita->requiere_seguimiento,
                'created_at' => $visita->created_at->format('Y-m-d H:i:s')
            ];
        })->toArray();
    }

    /**
     * Crear una nueva nota
     */
    public function crearNota(array $datos): ClienteNota
    {
        return DB::transaction(function () use ($datos) {
            $nota = ClienteNota::create([
                'id_cliente' => $datos['cliente_id'],
                'tipo' => $datos['tipo'],
                'titulo' => $datos['titulo'],
                'contenido' => $datos['contenido'],
                'responsable' => $datos['responsable'] ?? Auth::user()->name ?? 'Sistema',
                'prioridad' => $datos['prioridad'] ?? 'medium',
                'estado' => $datos['estado'] ?? 'activo',
                'requiere_seguimiento' => $datos['requiere_seguimiento'] ?? false,
                'fecha_interaccion' => $datos['fecha_interaccion'],
                'hora_interaccion' => $datos['hora_interaccion'],
                'fecha_seguimiento' => $datos['fecha_seguimiento'] ?? null,
                'resolucion' => $datos['resolucion'] ?? null,
                'metadata' => $datos['metadata'] ?? null
            ]);

            // Categorización automática
            $this->categorizarNota($nota);

            return $nota;
        });
    }

    /**
     * Crear una nueva visita
     */
    public function crearVisita(array $datos): ClienteVisita
    {
        return DB::transaction(function () use ($datos) {
            return ClienteVisita::create([
                'id_cliente' => $datos['cliente_id'],
                'tipo_visita' => $datos['tipo_visita'],
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'],
                'responsable' => $datos['responsable'] ?? Auth::user()->name ?? 'Sistema',
                'prioridad' => $datos['prioridad'] ?? 'medium',
                'productos_mencionados' => $datos['productos_mencionados'] ?? null,
                'servicios_mencionados' => $datos['servicios_mencionados'] ?? null,
                'valor_potencial' => $datos['valor_potencial'] ?? null,
                'estado' => $datos['estado'] ?? 'programada',
                'fecha_visita' => $datos['fecha_visita'],
                'hora_visita' => $datos['hora_visita'],
                'duracion_minutos' => $datos['duracion_minutos'] ?? null,
                'resultados' => $datos['resultados'] ?? null,
                'proximos_pasos' => $datos['proximos_pasos'] ?? null,
                'fecha_seguimiento' => $datos['fecha_seguimiento'] ?? null,
                'requiere_seguimiento' => $datos['requiere_seguimiento'] ?? false
            ]);
        });
    }

    /**
     * Actualizar una nota
     */
    public function actualizarNota(int $notaId, array $datos): ClienteNota
    {
        return DB::transaction(function () use ($notaId, $datos) {
            $nota = ClienteNota::findOrFail($notaId);
            $nota->update($datos);

            // Recategorizar si es necesario
            if (isset($datos['contenido']) || isset($datos['tipo'])) {
                $nota->categorias()->delete();
                $this->categorizarNota($nota);
            }

            return $nota;
        });
    }

    /**
     * Actualizar una visita
     */
    public function actualizarVisita(int $visitaId, array $datos): ClienteVisita
    {
        $visita = ClienteVisita::findOrFail($visitaId);
        $visita->update($datos);
        return $visita;
    }

    /**
     * Eliminar una nota
     */
    public function eliminarNota(int $notaId): bool
    {
        return DB::transaction(function () use ($notaId) {
            $nota = ClienteNota::findOrFail($notaId);
            $nota->categorias()->delete();
            return $nota->delete();
        });
    }

    /**
     * Eliminar una visita
     */
    public function eliminarVisita(int $visitaId): bool
    {
        $visita = ClienteVisita::findOrFail($visitaId);
        return $visita->delete();
    }

    /**
     * Categorización automática de notas
     */
    private function categorizarNota(ClienteNota $nota): void
    {
        $contenido = strtolower($nota->contenido);
        $categorias = [];

        // Detectar preferencias
        if (preg_match('/\b(favorito|prefiere|gusta|le gusta|disfruta|ama)\b/', $contenido)) {
            $categorias[] = [
                'categoria' => 'preferencias',
                'subcategoria' => 'productos_favoritos',
                'score_relevancia' => 0.9
            ];
        }

        // Detectar métodos de pago
        if (preg_match('/\b(pago|efectivo|tarjeta|transferencia|cheque|crédito|débito)\b/', $contenido)) {
            $categorias[] = [
                'categoria' => 'preferencias',
                'subcategoria' => 'metodos_pago',
                'score_relevancia' => 0.8
            ];
        }

        // Detectar quejas
        if (preg_match('/\b(problema|queja|molesto|insatisfecho|error|falla|defecto|malo)\b/', $contenido)) {
            $categorias[] = [
                'categoria' => 'quejas',
                'subcategoria' => 'problemas_tecnico',
                'score_relevancia' => 0.9
            ];
        }

        // Detectar problemas de atención
        if (preg_match('/\b(atención|servicio|soporte|ayuda|asistencia)\b/', $contenido)) {
            $categorias[] = [
                'categoria' => 'quejas',
                'subcategoria' => 'problemas_atencion',
                'score_relevancia' => 0.8
            ];
        }

        // Detectar sugerencias
        if (preg_match('/\b(sugerencia|recomendación|mejora|idea|propuesta)\b/', $contenido)) {
            $categorias[] = [
                'categoria' => 'comentarios',
                'subcategoria' => 'sugerencias',
                'score_relevancia' => 0.7
            ];
        }

        // Detectar feedback
        if (preg_match('/\b(feedback|opinión|comentario|experiencia|satisfacción)\b/', $contenido)) {
            $categorias[] = [
                'categoria' => 'comentarios',
                'subcategoria' => 'feedback',
                'score_relevancia' => 0.8
            ];
        }

        // Detectar seguimiento
        if (preg_match('/\b(seguimiento|próximo|siguiente|continuar|proceso)\b/', $contenido)) {
            $categorias[] = [
                'categoria' => 'seguimiento',
                'subcategoria' => 'seguimiento_comercial',
                'score_relevancia' => 0.9
            ];
        }

        // Si no se detectó ninguna categoría específica, marcar como comentario general
        if (empty($categorias)) {
            $categorias[] = [
                'categoria' => 'comentarios',
                'subcategoria' => null,
                'score_relevancia' => 0.5
            ];
        }

        // Guardar categorías
        foreach ($categorias as $categoria) {
            ClienteNotaCategoria::create([
                'id_nota' => $nota->id,
                'categoria' => $categoria['categoria'],
                'subcategoria' => $categoria['subcategoria'],
                'score_relevancia' => $categoria['score_relevancia']
            ]);
        }
    }

    /**
     * Obtener estadísticas de notas y visitas
     */
    public function getEstadisticas(int $clienteId): array
    {
        $notas = ClienteNota::where('id_cliente', $clienteId);
        $visitas = ClienteVisita::where('id_cliente', $clienteId);

        return [
            'total_notas' => $notas->count(),
            'notas_este_mes' => $notas->whereMonth('fecha_interaccion', now()->month)->count(),
            'notas_pendientes' => $notas->whereIn('estado', ['activo', 'pendiente', 'en_proceso'])->count(),
            'notas_alta_prioridad' => $notas->where('prioridad', 'high')->count(),
            'total_visitas' => $visitas->count(),
            'visitas_este_mes' => $visitas->whereMonth('fecha_visita', now()->month)->count(),
            'visitas_programadas' => $visitas->where('estado', 'programada')->count(),
            'visitas_realizadas' => $visitas->where('estado', 'realizada')->count(),
            'ultima_nota' => $notas->latest('fecha_interaccion')->first() ? $notas->latest('fecha_interaccion')->first()->fecha_interaccion->format('Y-m-d') : null,
            'ultima_visita' => $visitas->latest('fecha_visita')->first() ? $visitas->latest('fecha_visita')->first()->fecha_visita->format('Y-m-d') : null
        ];
    }

    /**
     * Buscar notas por contenido
     */
    public function buscarNotas(int $clienteId, string $termino): array
    {
        $notas = ClienteNota::where('id_cliente', $clienteId)
            ->where(function ($query) use ($termino) {
                $query->where('titulo', 'like', "%{$termino}%")
                      ->orWhere('contenido', 'like', "%{$termino}%");
            })
            ->with('categorias')
            ->orderBy('fecha_interaccion', 'desc')
            ->get();

        return $notas->map(function ($nota) {
            return [
                'id' => $nota->id,
                'tipo' => $nota->tipo,
                'titulo' => $nota->titulo,
                'contenido' => $nota->contenido,
                'fecha_interaccion' => $nota->fecha_interaccion->format('Y-m-d'),
                'categorias' => $nota->categorias->pluck('categoria')->toArray()
            ];
        })->toArray();
    }

    /**
     * Marcar nota como resuelta
     */
    public function marcarComoResuelta(int $notaId, string $resolucion = null): ClienteNota
    {
        $nota = ClienteNota::findOrFail($notaId);
        $nota->marcarComoResuelto($resolucion);
        return $nota;
    }

    /**
     * Archivar nota
     */
    public function archivarNota(int $notaId): ClienteNota
    {
        $nota = ClienteNota::findOrFail($notaId);
        $nota->archivar();
        return $nota;
    }

    /**
     * Cambiar estado de nota
     */
    public function cambiarEstado(int $notaId, string $estado): ClienteNota
    {
        $nota = ClienteNota::findOrFail($notaId);
        $nota->update(['estado' => $estado]);
        return $nota;
    }
}
