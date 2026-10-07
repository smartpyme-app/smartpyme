<?php

namespace App\Services\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Consulta;
use App\Models\Clinica\Expediente;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Profesional;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultaService
{
    public function __construct(
        private ExpedienteService $expedientes,
        private HistorialClinicoService $historial,
        private DiagnosticoService $diagnosticos,
    ) {
    }

    public function crear(int $idEmpresa, ?int $idUsuarioRegistro, Paciente $paciente, array $datos): Consulta
    {
        if (! $paciente->activo) {
            throw ValidationException::withMessages(['paciente' => 'El paciente debe estar activo para una consulta nueva.']);
        }
        $this->expedientes->exigirOperativo($paciente);

        $expediente = $this->expedientes->dePaciente($paciente);
        $atributos = $this->atributos($idEmpresa, $expediente, $paciente, $datos);
        $idEvento = ! empty($datos['id_evento']) ? (int) $datos['id_evento'] : null;
        if ($idEvento !== null) {
            $this->exigirEventoEmpresa($idEmpresa, $idEvento);
            $atributos['id_evento'] = $idEvento;
        }

        return DB::transaction(function () use ($idEmpresa, $idUsuarioRegistro, $atributos) {
            $consulta = Consulta::create($atributos + [
                'id_empresa' => $idEmpresa,
                'id_usuario_registro' => $idUsuarioRegistro,
                'estado' => 'borrador',
            ]);

            return $consulta;
        });
    }

    public function actualizar(Consulta $consulta, array $datos): Consulta
    {
        $this->exigirEditable($consulta);
        $paciente = Paciente::findOrFail($consulta->id_paciente);
        $expediente = Expediente::findOrFail($consulta->id_expediente);
        $consulta->update($this->atributos((int) $consulta->id_empresa, $expediente, $paciente, $datos));

        return $consulta->fresh();
    }

    public function cerrar(Consulta $consulta): Consulta
    {
        $this->exigirEditable($consulta);
        if (PacienteReglas::vacio($consulta->motivo) === null) {
            throw ValidationException::withMessages(['motivo' => 'El motivo es obligatorio para cerrar la consulta.']);
        }

        return DB::transaction(function () use ($consulta) {
            $consulta->update(['estado' => 'cerrada']);
            $this->diagnosticos->cerrarActivosPorConsulta($consulta->fresh());
            $this->sincronizarHistorial($consulta);

            return $consulta->fresh();
        });
    }

    public function registrarAddendum(Consulta $consulta, string $texto): Consulta
    {
        if ($consulta->estado !== 'cerrada') {
            throw ValidationException::withMessages(['estado' => 'Solo se puede agregar addendum a una consulta cerrada.']);
        }

        $texto = PacienteReglas::vacio($texto);
        if ($texto === null) {
            throw ValidationException::withMessages(['addendum' => 'El texto del addendum es obligatorio.']);
        }

        $linea = '['.now()->format('Y-m-d H:i').'] '.$texto;
        $addendum = trim(($consulta->addendum ?? '').($consulta->addendum ? "\n\n" : '').$linea);
        $consulta->update(['addendum' => $addendum]);

        return $consulta->fresh();
    }

    public function anular(Consulta $consulta, string $motivo): Consulta
    {
        $motivo = PacienteReglas::vacio($motivo);
        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo_anulacion' => 'Indica el motivo de anulación.']);
        }

        return DB::transaction(function () use ($consulta, $motivo) {
            $consulta->update([
                'estado' => 'anulada',
                'motivo_anulacion' => $motivo,
            ]);
            $this->sincronizarHistorial($consulta, 'anulado');

            return $consulta->fresh();
        });
    }

    public function presentar(Consulta $consulta, bool $textoClinico): array
    {
        $profesional = User::withoutGlobalScopes()->find($consulta->id_usuario_profesional, ['id', 'name']);
        $sucursal = Sucursal::withoutGlobalScopes()->find($consulta->id_sucursal, ['id', 'nombre']);

        $base = [
            'id' => $consulta->id,
            'id_paciente' => $consulta->id_paciente,
            'id_expediente' => $consulta->id_expediente,
            'fecha' => $consulta->fecha?->format('Y-m-d'),
            'hora' => $consulta->hora ? substr((string) $consulta->hora, 0, 5) : null,
            'motivo' => $consulta->motivo,
            'estado' => $consulta->estado,
            'motivo_anulacion' => $consulta->motivo_anulacion,
            'id_evento' => $consulta->id_evento,
            'editable' => $consulta->estado === 'borrador',
            'puede_addendum' => $consulta->estado === 'cerrada',
            'profesional' => $profesional ? ['id' => $profesional->id, 'nombre' => $profesional->name] : null,
            'sucursal' => $sucursal ? ['id' => $sucursal->id, 'nombre' => $sucursal->nombre] : null,
        ];

        if (! $textoClinico) {
            return $base;
        }

        return $base + [
            'anamnesis' => $consulta->anamnesis,
            'antecedentes' => $consulta->antecedentes,
            'examen_fisico' => $consulta->examen_fisico,
            'observaciones' => $consulta->observaciones,
            'indicaciones' => $consulta->indicaciones,
            'signos_vitales' => $consulta->signos_vitales ?? [],
            'addendum' => $consulta->addendum,
        ];
    }

    private function sincronizarHistorial(Consulta $consulta, string $estadoEvento = 'activo'): void
    {
        $expediente = Expediente::findOrFail($consulta->id_expediente);
        $this->historial->registrar(
            $expediente,
            HistorialClinicoService::TIPO_CONSULTA,
            $consulta->fecha->format('Y-m-d'),
            $consulta->hora ? substr((string) $consulta->hora, 0, 8) : null,
            'consulta',
            (int) $consulta->id,
            'Consulta: '.$consulta->motivo,
            $estadoEvento,
            (int) $consulta->id_usuario_profesional,
        );
    }

    private function atributos(int $idEmpresa, Expediente $expediente, Paciente $paciente, array $datos): array
    {
        $fecha = PacienteReglas::vacio($datos['fecha'] ?? null);
        if ($fecha === null) {
            throw ValidationException::withMessages(['fecha' => 'La fecha de la consulta es obligatoria.']);
        }
        $motivo = PacienteReglas::vacio($datos['motivo'] ?? null);
        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo' => 'El motivo es obligatorio.']);
        }

        $idSucursal = (int) ($datos['id_sucursal'] ?? 0);
        $idProfesionalUsuario = (int) ($datos['id_usuario_profesional'] ?? 0);
        $this->exigirProfesionalEnSucursal($idEmpresa, $idProfesionalUsuario, $idSucursal);

        $hora = PacienteReglas::vacio($datos['hora'] ?? null);
        $signos = $datos['signos_vitales'] ?? null;
        if (is_string($signos) && $signos !== '') {
            $decodificado = json_decode($signos, true);
            $signos = json_last_error() === JSON_ERROR_NONE ? $decodificado : null;
        }
        if (is_array($signos)) {
            $signos = array_filter($signos, fn ($valor) => PacienteReglas::vacio($valor) !== null);
            $signos = $signos !== [] ? $signos : null;
        }

        return [
            'id_expediente' => $expediente->id,
            'id_paciente' => $paciente->id,
            'id_sucursal' => $idSucursal,
            'id_usuario_profesional' => $idProfesionalUsuario,
            'fecha' => $fecha,
            'hora' => $hora,
            'motivo' => $motivo,
            'anamnesis' => PacienteReglas::vacio($datos['anamnesis'] ?? null),
            'antecedentes' => PacienteReglas::vacio($datos['antecedentes'] ?? null),
            'examen_fisico' => PacienteReglas::vacio($datos['examen_fisico'] ?? null),
            'observaciones' => PacienteReglas::vacio($datos['observaciones'] ?? null),
            'indicaciones' => PacienteReglas::vacio($datos['indicaciones'] ?? null),
            'signos_vitales' => is_array($signos) ? $signos : null,
        ];
    }

    private function exigirEventoEmpresa(int $idEmpresa, int $idEvento): void
    {
        if (! DB::table('eventos')->where('id', $idEvento)->where('id_empresa', $idEmpresa)->exists()) {
            throw ValidationException::withMessages(['id_evento' => 'La cita no pertenece a la empresa.']);
        }
    }

    private function exigirProfesionalEnSucursal(int $idEmpresa, int $idUsuario, int $idSucursal): void
    {
        $sucursalValida = Sucursal::withoutGlobalScopes()
            ->where('id', $idSucursal)
            ->where('id_empresa', $idEmpresa)
            ->exists();
        if (! $sucursalValida) {
            throw ValidationException::withMessages(['id_sucursal' => 'La sucursal no pertenece a la empresa.']);
        }

        $profesional = Profesional::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('id_usuario', $idUsuario)
            ->where('activo', true)
            ->first();

        if ($profesional === null) {
            throw ValidationException::withMessages(['id_usuario_profesional' => 'El profesional no está habilitado en la clínica.']);
        }

        $enSucursal = DB::table('clinica_profesional_sucursales')
            ->where('id_profesional', $profesional->id)
            ->where('id_sucursal', $idSucursal)
            ->exists();

        if (! $enSucursal) {
            throw ValidationException::withMessages(['id_usuario_profesional' => 'El profesional no está habilitado en esta sucursal.']);
        }
    }

    private function exigirEditable(Consulta $consulta): void
    {
        if ($consulta->estado !== 'borrador') {
            throw ValidationException::withMessages(['estado' => 'La consulta ya no se puede editar.']);
        }
    }
}
