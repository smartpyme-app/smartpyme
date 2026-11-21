<?php

namespace App\Services\Clinica;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Clinica\Consulta;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Profesional;
use App\Models\Clinica\Receta;
use App\Models\Clinica\RecetaLinea;
use App\Models\Clinica\Tratamiento;
use App\Models\Inventario\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecetaService
{
    public const TIPO_HISTORIAL = 'receta';

    public function __construct(
        private ExpedienteService $expedientes,
        private HistorialClinicoService $historial,
    ) {
    }

    public function crear(int $idEmpresa, ?int $idUsuarioRegistro, Paciente $paciente, array $datos): Receta
    {
        if (! $paciente->activo) {
            throw ValidationException::withMessages(['paciente' => 'El paciente debe estar activo.']);
        }
        $this->expedientes->exigirOperativo($paciente);
        $expediente = $this->expedientes->dePaciente($paciente);
        $cabecera = $this->atributosCabecera($idEmpresa, $expediente, $paciente, $datos);
        $lineas = $this->normalizarLineas($idEmpresa, $datos['lineas'] ?? []);

        return DB::transaction(function () use ($idEmpresa, $idUsuarioRegistro, $cabecera, $lineas) {
            $receta = Receta::create($cabecera + [
                'id_empresa' => $idEmpresa,
                'id_usuario_registro' => $idUsuarioRegistro,
                'estado' => 'borrador',
            ]);
            $this->guardarLineas($receta, $idEmpresa, $lineas);

            return $receta->fresh(['lineas']);
        });
    }

    public function actualizar(Receta $receta, array $datos): Receta
    {
        $this->exigirBorrador($receta);
        $paciente = Paciente::findOrFail($receta->id_paciente);
        $expediente = $this->expedientes->dePaciente($paciente);
        $cabecera = $this->atributosCabecera((int) $receta->id_empresa, $expediente, $paciente, $datos);
        $lineas = $this->normalizarLineas((int) $receta->id_empresa, $datos['lineas'] ?? []);

        return DB::transaction(function () use ($receta, $cabecera, $lineas) {
            $receta->update($cabecera);
            RecetaLinea::where('id_receta', $receta->id)->delete();
            $this->guardarLineas($receta, (int) $receta->id_empresa, $lineas);

            return $receta->fresh(['lineas']);
        });
    }

    public function emitir(Receta $receta): Receta
    {
        $this->exigirBorrador($receta);
        $receta->load('lineas');
        if ($receta->lineas->isEmpty()) {
            throw ValidationException::withMessages(['lineas' => 'Agrega al menos una línea con medicamento y dosis.']);
        }
        foreach ($receta->lineas as $linea) {
            if (PacienteReglas::vacio($linea->nombre_medicamento) === null || PacienteReglas::vacio($linea->dosis) === null) {
                throw ValidationException::withMessages(['lineas' => 'Cada línea requiere nombre de medicamento y dosis.']);
            }
        }

        $receta->update(['estado' => 'emitida']);
        $this->sincronizarHistorial($receta->fresh(['lineas']));

        return $receta->fresh(['lineas']);
    }

    public function anular(Receta $receta, string $motivo): Receta
    {
        $motivo = PacienteReglas::vacio($motivo);
        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo_anulacion' => 'Indica el motivo de anulación.']);
        }
        if ($receta->estado !== 'emitida') {
            throw ValidationException::withMessages(['estado' => 'Solo se anulan recetas emitidas.']);
        }

        $receta->update(['estado' => 'anulada', 'motivo_anulacion' => $motivo]);
        $this->sincronizarHistorial($receta->fresh(['lineas']), 'anulado');

        return $receta->fresh(['lineas']);
    }

    public function presentar(Receta $receta, bool $detalle): array
    {
        $profesional = User::withoutGlobalScopes()->find($receta->id_usuario_profesional, ['id', 'name']);
        $base = [
            'id' => $receta->id,
            'id_paciente' => $receta->id_paciente,
            'id_expediente' => $receta->id_expediente,
            'id_consulta' => $receta->id_consulta,
            'id_tratamiento' => $receta->id_tratamiento,
            'fecha' => $receta->fecha?->format('Y-m-d'),
            'estado' => $receta->estado,
            'motivo_anulacion' => $receta->motivo_anulacion,
            'observaciones' => $detalle ? $receta->observaciones : null,
            'editable' => $receta->estado === 'borrador',
            'emitida' => $receta->estado === 'emitida',
            'profesional' => $profesional ? ['id' => $profesional->id, 'nombre' => $profesional->name] : null,
        ];

        if (! $detalle) {
            return $base;
        }

        $receta->loadMissing('lineas');

        return $base + [
            'id_sucursal' => $receta->id_sucursal,
            'lineas' => $receta->lineas->map(fn (RecetaLinea $l) => [
                'id' => $l->id,
                'orden' => $l->orden,
                'id_producto' => $l->id_producto,
                'nombre_medicamento' => $l->nombre_medicamento,
                'dosis' => $l->dosis,
                'frecuencia' => $l->frecuencia,
                'duracion' => $l->duracion,
                'via' => $l->via,
                'indicaciones' => $l->indicaciones,
                'peso_referencia' => $l->peso_referencia,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function datosPdf(Receta $receta, Empresa $empresa, Paciente $paciente): array
    {
        $receta->load('lineas');
        $profesional = User::withoutGlobalScopes()->find($receta->id_usuario_profesional);

        return [
            'empresa' => $empresa,
            'paciente' => $paciente,
            'receta' => $receta,
            'profesional' => $profesional,
        ];
    }

    /** @param list<array<string, mixed>> $lineas */
    private function guardarLineas(Receta $receta, int $idEmpresa, array $lineas): void
    {
        foreach ($lineas as $i => $linea) {
            RecetaLinea::create([
                'id_empresa' => $idEmpresa,
                'id_receta' => $receta->id,
                'orden' => $i + 1,
                'id_producto' => $linea['id_producto'] ?? null,
                'nombre_medicamento' => $linea['nombre_medicamento'],
                'dosis' => $linea['dosis'],
                'frecuencia' => $linea['frecuencia'] ?? null,
                'duracion' => $linea['duracion'] ?? null,
                'via' => $linea['via'] ?? null,
                'indicaciones' => $linea['indicaciones'] ?? null,
                'peso_referencia' => $linea['peso_referencia'] ?? null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function normalizarLineas(int $idEmpresa, array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }
        $normalizadas = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $nombre = PacienteReglas::vacio($linea['nombre_medicamento'] ?? null);
            $dosis = PacienteReglas::vacio($linea['dosis'] ?? null);
            $idProducto = ! empty($linea['id_producto']) ? (int) $linea['id_producto'] : null;
            if ($idProducto !== null) {
                $producto = Producto::withoutGlobalScopes()
                    ->where('id', $idProducto)
                    ->where('id_empresa', $idEmpresa)
                    ->first();
                if ($producto === null) {
                    throw ValidationException::withMessages(['lineas' => 'Producto inválido para esta empresa.']);
                }
                if ($nombre === null) {
                    $nombre = $producto->nombre;
                }
            }
            if ($nombre === null) {
                throw ValidationException::withMessages(['lineas' => 'Cada línea requiere nombre de medicamento.']);
            }
            $normalizadas[] = [
                'id_producto' => $idProducto,
                'nombre_medicamento' => $nombre,
                'dosis' => $dosis ?? '',
                'frecuencia' => PacienteReglas::vacio($linea['frecuencia'] ?? null),
                'duracion' => PacienteReglas::vacio($linea['duracion'] ?? null),
                'via' => PacienteReglas::vacio($linea['via'] ?? null),
                'indicaciones' => PacienteReglas::vacio($linea['indicaciones'] ?? null),
                'peso_referencia' => isset($linea['peso_referencia']) && $linea['peso_referencia'] !== ''
                    ? (float) $linea['peso_referencia']
                    : null,
            ];
        }

        return $normalizadas;
    }

    /** @return array<string, mixed> */
    private function atributosCabecera(int $idEmpresa, $expediente, Paciente $paciente, array $datos): array
    {
        $fecha = PacienteReglas::vacio($datos['fecha'] ?? null);
        if ($fecha === null) {
            throw ValidationException::withMessages(['fecha' => 'La fecha es obligatoria.']);
        }
        $idProfesionalUsuario = (int) ($datos['id_usuario_profesional'] ?? 0);
        $idSucursal = (int) ($datos['id_sucursal'] ?? 0);
        $this->exigirProfesionalEnSucursal($idEmpresa, $idProfesionalUsuario, $idSucursal);

        $idConsulta = ! empty($datos['id_consulta']) ? (int) $datos['id_consulta'] : null;
        if ($idConsulta !== null) {
            $ok = Consulta::withoutGlobalScopes()
                ->where('id', $idConsulta)
                ->where('id_paciente', $paciente->id)
                ->where('id_empresa', $idEmpresa)
                ->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['id_consulta' => 'La consulta no pertenece al paciente.']);
            }
        }

        $idTratamiento = ! empty($datos['id_tratamiento']) ? (int) $datos['id_tratamiento'] : null;
        if ($idTratamiento !== null) {
            $ok = Tratamiento::withoutGlobalScopes()
                ->where('id', $idTratamiento)
                ->where('id_paciente', $paciente->id)
                ->where('id_empresa', $idEmpresa)
                ->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['id_tratamiento' => 'El tratamiento no pertenece al paciente.']);
            }
        }

        return [
            'id_expediente' => $expediente->id,
            'id_paciente' => $paciente->id,
            'id_consulta' => $idConsulta,
            'id_tratamiento' => $idTratamiento,
            'id_sucursal' => $idSucursal,
            'id_usuario_profesional' => $idProfesionalUsuario,
            'fecha' => $fecha,
            'observaciones' => PacienteReglas::vacio($datos['observaciones'] ?? null),
        ];
    }

    private function sincronizarHistorial(Receta $receta, string $estadoEvento = 'activo'): void
    {
        $expediente = $this->expedientes->dePaciente(Paciente::findOrFail($receta->id_paciente));
        $primera = $receta->lineas->first();
        $resumen = 'Receta: '.($primera?->nombre_medicamento ?? 'prescripción').' ('.$receta->estado.')';
        $this->historial->registrar(
            $expediente,
            self::TIPO_HISTORIAL,
            $receta->fecha->format('Y-m-d'),
            null,
            'receta',
            (int) $receta->id,
            $resumen,
            $estadoEvento,
            (int) $receta->id_usuario_profesional,
        );
    }

    private function exigirBorrador(Receta $receta): void
    {
        if ($receta->estado !== 'borrador') {
            throw ValidationException::withMessages(['estado' => 'La receta emitida o anulada no se puede editar.']);
        }
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
}
