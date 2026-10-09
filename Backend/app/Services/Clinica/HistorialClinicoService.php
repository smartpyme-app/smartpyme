<?php

namespace App\Services\Clinica;

use App\Models\Clinica\Expediente;
use App\Models\Clinica\HistorialEvento;
use App\Models\User;
class HistorialClinicoService
{
    public const TIPO_APERTURA = 'expediente_apertura';

    public const TIPO_CONSULTA = 'consulta';

    public const TIPO_TRATAMIENTO = 'tratamiento';

    public const TIPO_DIAGNOSTICO = 'diagnostico';

    /** @param array{tipo?: string, fecha_desde?: string, fecha_hasta?: string, id_usuario_profesional?: int} $filtros */
    public function listar(Expediente $expediente, array $filtros, bool $detalleClinico): array
    {
        $consulta = HistorialEvento::query()
            ->where('id_expediente', $expediente->id)
            ->orderByDesc('fecha_evento')
            ->orderByDesc('hora_evento')
            ->orderByDesc('id');

        if (! empty($filtros['tipo'])) {
            $consulta->where('tipo', $filtros['tipo']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $consulta->whereDate('fecha_evento', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $consulta->whereDate('fecha_evento', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['id_usuario_profesional'])) {
            $consulta->where('id_usuario_profesional', (int) $filtros['id_usuario_profesional']);
        }

        return $consulta->get()->map(fn (HistorialEvento $evento) => $this->presentar($evento, $expediente, $detalleClinico))->all();
    }

    public function registrar(
        Expediente $expediente,
        string $tipo,
        string $fecha,
        ?string $hora,
        string $origenTipo,
        int $origenId,
        string $resumen,
        string $estado = 'activo',
        ?int $idUsuarioProfesional = null,
    ): HistorialEvento {
        return HistorialEvento::updateOrCreate(
            [
                'origen_tipo' => $origenTipo,
                'origen_id' => $origenId,
            ],
            [
                'id_empresa' => $expediente->id_empresa,
                'id_expediente' => $expediente->id,
                'tipo' => $tipo,
                'fecha_evento' => $fecha,
                'hora_evento' => $hora,
                'id_usuario_profesional' => $idUsuarioProfesional,
                'resumen' => $resumen,
                'estado' => $estado,
            ]
        );
    }

    public function registrarApertura(Expediente $expediente): HistorialEvento
    {
        return $this->registrar(
            $expediente,
            self::TIPO_APERTURA,
            $expediente->fecha_apertura?->format('Y-m-d') ?? now()->toDateString(),
            null,
            'expediente',
            (int) $expediente->id,
            'Apertura del expediente clínico',
        );
    }

    private function presentar(HistorialEvento $evento, Expediente $expediente, bool $detalleClinico): array
    {
        $profesional = $evento->id_usuario_profesional
            ? User::withoutGlobalScopes()->find($evento->id_usuario_profesional, ['id', 'name'])
            : null;

        $mostrarResumen = $detalleClinico || $evento->tipo === self::TIPO_APERTURA;

        return [
            'id' => $evento->id,
            'tipo' => $evento->tipo,
            'tipo_etiqueta' => $this->etiquetaTipo($evento->tipo),
            'fecha_evento' => $evento->fecha_evento?->format('Y-m-d'),
            'hora_evento' => $evento->hora_evento ? substr((string) $evento->hora_evento, 0, 5) : null,
            'resumen' => $mostrarResumen ? $evento->resumen : 'Evento clínico',
            'estado' => $evento->estado,
            'anulado' => $evento->estado === 'anulado',
            'profesional' => $profesional ? ['id' => $profesional->id, 'nombre' => $profesional->name] : null,
            'origen_tipo' => $evento->origen_tipo,
            'origen_id' => $evento->origen_id,
            'enlace' => $this->enlace($evento, (int) $expediente->id_paciente),
        ];
    }

    private function enlace(HistorialEvento $evento, int $idPaciente): ?array
    {
        if ($evento->origen_tipo === 'consulta') {
            return [
                'ruta' => '/clinica/pacientes/'.$idPaciente.'/consultas/'.$evento->origen_id,
            ];
        }
        if ($evento->origen_tipo === 'tratamiento') {
            return [
                'ruta' => '/clinica/pacientes/'.$idPaciente.'/tratamientos/'.$evento->origen_id,
            ];
        }
        if ($evento->origen_tipo === 'diagnostico') {
            return [
                'ruta' => '/clinica/pacientes/'.$idPaciente.'/diagnosticos/'.$evento->origen_id,
            ];
        }

        return null;
    }

    private function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_APERTURA => 'Apertura de expediente',
            self::TIPO_CONSULTA => 'Consulta',
            self::TIPO_TRATAMIENTO => 'Tratamiento',
            self::TIPO_DIAGNOSTICO => 'Diagnóstico',
            'vacuna' => 'Vacuna',
            'laboratorio' => 'Laboratorio',
            default => ucfirst(str_replace('_', ' ', $tipo)),
        };
    }
}
