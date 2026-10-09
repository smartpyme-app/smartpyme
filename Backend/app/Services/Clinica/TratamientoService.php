<?php

namespace App\Services\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Consulta;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Profesional;
use App\Models\Clinica\Tratamiento;
use App\Models\Clinica\TratamientoAvance;
use App\Models\Clinica\TratamientoTerapia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TratamientoService
{
    public function __construct(
        private ExpedienteService $expedientes,
        private HistorialClinicoService $historial,
    ) {
    }

    public function crear(int $idEmpresa, ?int $idUsuarioRegistro, Paciente $paciente, array $datos): Tratamiento
    {
        if (! $paciente->activo) {
            throw ValidationException::withMessages(['paciente' => 'El paciente debe estar activo para un tratamiento nuevo.']);
        }
        $this->expedientes->exigirOperativo($paciente);
        $expediente = $this->expedientes->dePaciente($paciente);
        $atributos = $this->atributosPlan($idEmpresa, $expediente, $paciente, $datos);

        return DB::transaction(function () use ($idEmpresa, $idUsuarioRegistro, $atributos, $expediente) {
            $tratamiento = Tratamiento::create($atributos + [
                'id_empresa' => $idEmpresa,
                'id_usuario_registro' => $idUsuarioRegistro,
                'estado' => 'indicado',
            ]);
            $this->sincronizarHistorial($tratamiento);

            return $tratamiento;
        });
    }

    public function actualizar(Tratamiento $tratamiento, array $datos): Tratamiento
    {
        $this->exigirEditable($tratamiento);
        $paciente = Paciente::findOrFail($tratamiento->id_paciente);
        $expediente = $this->expedientes->dePaciente($paciente);
        $tratamiento->update($this->atributosPlan((int) $tratamiento->id_empresa, $expediente, $paciente, $datos));
        $this->sincronizarHistorial($tratamiento->fresh());

        return $tratamiento->fresh();
    }

    public function iniciar(Tratamiento $tratamiento): Tratamiento
    {
        if ($tratamiento->estado !== 'indicado') {
            throw ValidationException::withMessages(['estado' => 'Solo un plan indicado puede pasar a en curso.']);
        }
        $tratamiento->update(['estado' => 'en_curso']);
        $this->sincronizarHistorial($tratamiento->fresh());

        return $tratamiento->fresh();
    }

    public function suspender(Tratamiento $tratamiento, string $motivo): Tratamiento
    {
        $motivo = PacienteReglas::vacio($motivo);
        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo_suspension' => 'Indica el motivo de suspensión.']);
        }
        if (! in_array($tratamiento->estado, ['indicado', 'en_curso'], true)) {
            throw ValidationException::withMessages(['estado' => 'El tratamiento no se puede suspender en este estado.']);
        }

        $tratamiento->update(['estado' => 'suspendido', 'motivo_suspension' => $motivo]);
        $this->sincronizarHistorial($tratamiento->fresh(), 'anulado');

        return $tratamiento->fresh();
    }

    public function finalizar(Tratamiento $tratamiento, string $motivo): Tratamiento
    {
        $motivo = PacienteReglas::vacio($motivo);
        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo_cierre' => 'Indica el motivo de cierre.']);
        }
        if (! in_array($tratamiento->estado, ['indicado', 'en_curso'], true)) {
            throw ValidationException::withMessages(['estado' => 'El tratamiento no se puede finalizar en este estado.']);
        }

        $tratamiento->update(['estado' => 'finalizado', 'motivo_cierre' => $motivo]);
        $this->sincronizarHistorial($tratamiento->fresh());

        return $tratamiento->fresh();
    }

    public function registrarAvance(Tratamiento $tratamiento, int $idEmpresa, int $idUsuario, array $datos): TratamientoAvance
    {
        if ($tratamiento->estado !== 'en_curso') {
            throw ValidationException::withMessages(['estado' => 'Solo se registra avance en tratamientos en curso.']);
        }
        $fecha = PacienteReglas::vacio($datos['fecha'] ?? null);
        if ($fecha === null) {
            throw ValidationException::withMessages(['fecha' => 'La fecha del avance es obligatoria.']);
        }

        return TratamientoAvance::create([
            'id_empresa' => $idEmpresa,
            'id_tratamiento' => $tratamiento->id,
            'fecha' => $fecha,
            'nota' => PacienteReglas::vacio($datos['nota'] ?? null),
            'incumplimiento' => (bool) ($datos['incumplimiento'] ?? false),
            'id_usuario' => $idUsuario,
        ]);
    }

    public function registrarTerapia(Tratamiento $tratamiento, int $idEmpresa, int $idUsuario, array $datos): TratamientoTerapia
    {
        if (! in_array($tratamiento->estado, ['indicado', 'en_curso'], true)) {
            throw ValidationException::withMessages(['estado' => 'No se pueden registrar terapias en este estado.']);
        }
        $tipo = PacienteReglas::vacio($datos['tipo'] ?? null);
        if ($tipo === null) {
            throw ValidationException::withMessages(['tipo' => 'El tipo de terapia es obligatorio.']);
        }
        if ($this->esTipoCirugia($tipo)) {
            throw ValidationException::withMessages(['tipo' => 'Las cirugías se registran en el módulo de cirugías, no aquí.']);
        }
        $fecha = PacienteReglas::vacio($datos['fecha'] ?? null);
        if ($fecha === null) {
            throw ValidationException::withMessages(['fecha' => 'La fecha es obligatoria.']);
        }

        return TratamientoTerapia::create([
            'id_empresa' => $idEmpresa,
            'id_tratamiento' => $tratamiento->id,
            'fecha' => $fecha,
            'tipo' => $tipo,
            'descripcion' => PacienteReglas::vacio($datos['descripcion'] ?? null),
            'notas_resultado' => PacienteReglas::vacio($datos['notas_resultado'] ?? null),
            'id_usuario' => $idUsuario,
        ]);
    }

    public function presentar(Tratamiento $tratamiento, bool $detalle): array
    {
        $profesional = User::withoutGlobalScopes()->find($tratamiento->id_usuario_profesional, ['id', 'name']);
        $base = [
            'id' => $tratamiento->id,
            'id_paciente' => $tratamiento->id_paciente,
            'id_expediente' => $tratamiento->id_expediente,
            'id_consulta' => $tratamiento->id_consulta,
            'nombre' => $tratamiento->nombre,
            'descripcion' => $tratamiento->descripcion,
            'fecha_inicio' => $tratamiento->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $tratamiento->fecha_fin?->format('Y-m-d'),
            'frecuencia' => $tratamiento->frecuencia,
            'duracion' => $tratamiento->duracion,
            'estado' => $tratamiento->estado,
            'motivo_suspension' => $tratamiento->motivo_suspension,
            'motivo_cierre' => $tratamiento->motivo_cierre,
            'editable' => in_array($tratamiento->estado, ['indicado', 'en_curso'], true),
            'profesional' => $profesional ? ['id' => $profesional->id, 'nombre' => $profesional->name] : null,
        ];

        if (! $detalle) {
            return $base;
        }

        $tratamiento->load(['avances' => fn ($q) => $q->orderByDesc('fecha')->orderByDesc('id'), 'terapias' => fn ($q) => $q->orderByDesc('fecha')->orderByDesc('id')]);

        return $base + [
            'indicaciones' => $tratamiento->indicaciones,
            'avances' => $tratamiento->avances->map(fn (TratamientoAvance $a) => [
                'id' => $a->id,
                'fecha' => $a->fecha?->format('Y-m-d'),
                'nota' => $a->nota,
                'incumplimiento' => (bool) $a->incumplimiento,
            ])->all(),
            'terapias' => $tratamiento->terapias->map(fn (TratamientoTerapia $t) => [
                'id' => $t->id,
                'fecha' => $t->fecha?->format('Y-m-d'),
                'tipo' => $t->tipo,
                'descripcion' => $t->descripcion,
                'notas_resultado' => $t->notas_resultado,
            ])->all(),
        ];
    }

    private function atributosPlan(int $idEmpresa, $expediente, Paciente $paciente, array $datos): array
    {
        $descripcion = PacienteReglas::vacio($datos['descripcion'] ?? null);
        if ($descripcion === null) {
            throw ValidationException::withMessages(['descripcion' => 'La descripción del tratamiento es obligatoria.']);
        }
        $fechaInicio = PacienteReglas::vacio($datos['fecha_inicio'] ?? null);
        if ($fechaInicio === null) {
            throw ValidationException::withMessages(['fecha_inicio' => 'La fecha de inicio es obligatoria.']);
        }
        $fechaFin = PacienteReglas::vacio($datos['fecha_fin'] ?? null);
        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            throw ValidationException::withMessages(['fecha_fin' => 'La fecha fin no puede ser anterior al inicio.']);
        }

        $idProfesionalUsuario = (int) ($datos['id_usuario_profesional'] ?? 0);
        $idSucursal = (int) ($datos['id_sucursal'] ?? 0);
        $this->exigirProfesionalEnSucursal($idEmpresa, $idProfesionalUsuario, $idSucursal);

        $idConsulta = ! empty($datos['id_consulta']) ? (int) $datos['id_consulta'] : null;
        if ($idConsulta !== null) {
            $consultaValida = Consulta::withoutGlobalScopes()
                ->where('id', $idConsulta)
                ->where('id_paciente', $paciente->id)
                ->where('id_empresa', $idEmpresa)
                ->exists();
            if (! $consultaValida) {
                throw ValidationException::withMessages(['id_consulta' => 'La consulta no pertenece al paciente.']);
            }
        }

        return [
            'id_expediente' => $expediente->id,
            'id_paciente' => $paciente->id,
            'id_consulta' => $idConsulta,
            'id_usuario_profesional' => $idProfesionalUsuario,
            'nombre' => PacienteReglas::vacio($datos['nombre'] ?? null),
            'descripcion' => $descripcion,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'frecuencia' => PacienteReglas::vacio($datos['frecuencia'] ?? null),
            'duracion' => PacienteReglas::vacio($datos['duracion'] ?? null),
            'indicaciones' => PacienteReglas::vacio($datos['indicaciones'] ?? null),
        ];
    }

    private function sincronizarHistorial(Tratamiento $tratamiento, string $estadoEvento = 'activo'): void
    {
        $expediente = $this->expedientes->dePaciente(Paciente::findOrFail($tratamiento->id_paciente));
        $titulo = $tratamiento->nombre ?: mb_substr($tratamiento->descripcion, 0, 80);
        $this->historial->registrar(
            $expediente,
            HistorialClinicoService::TIPO_TRATAMIENTO,
            $tratamiento->fecha_inicio->format('Y-m-d'),
            null,
            'tratamiento',
            (int) $tratamiento->id,
            'Tratamiento: '.$titulo.' ('.$tratamiento->estado.')',
            $estadoEvento,
            (int) $tratamiento->id_usuario_profesional,
        );
    }

    private function exigirProfesionalEnSucursal(int $idEmpresa, int $idUsuario, int $idSucursal): void
    {
        if ($idSucursal <= 0) {
            throw ValidationException::withMessages(['id_sucursal' => 'La sucursal es obligatoria.']);
        }
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

    private function exigirEditable(Tratamiento $tratamiento): void
    {
        if (! in_array($tratamiento->estado, ['indicado', 'en_curso'], true)) {
            throw ValidationException::withMessages(['estado' => 'El tratamiento ya no se puede editar.']);
        }
    }

    private function esTipoCirugia(string $tipo): bool
    {
        $normalizado = mb_strtolower($tipo);

        return str_contains($normalizado, 'cirug') || str_contains($normalizado, 'quir');
    }
}
