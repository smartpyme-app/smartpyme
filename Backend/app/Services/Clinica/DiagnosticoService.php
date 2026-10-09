<?php

namespace App\Services\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Consulta;
use App\Models\Clinica\Diagnostico;
use App\Models\Clinica\DiagnosticoCatalogo;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Profesional;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosticoService
{
    public function __construct(
        private ExpedienteService $expedientes,
        private HistorialClinicoService $historial,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listarCatalogo(int $idEmpresa, ?string $busqueda = null): array
    {
        $consulta = DiagnosticoCatalogo::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->orderBy('codigo');

        $busqueda = PacienteReglas::vacio($busqueda);
        if ($busqueda !== null) {
            $consulta->where(function ($q) use ($busqueda) {
                $q->where('codigo', 'like', '%'.$busqueda.'%')
                    ->orWhere('nombre', 'like', '%'.$busqueda.'%');
            });
        }

        return $consulta->limit(50)->get()->map(fn (DiagnosticoCatalogo $item) => [
            'id' => $item->id,
            'codigo' => $item->codigo,
            'nombre' => $item->nombre,
        ])->all();
    }

    public function guardarCatalogo(int $idEmpresa, array $datos, ?DiagnosticoCatalogo $existente = null): DiagnosticoCatalogo
    {
        $codigo = PacienteReglas::vacio($datos['codigo'] ?? null);
        $nombre = PacienteReglas::vacio($datos['nombre'] ?? null);
        if ($codigo === null) {
            throw ValidationException::withMessages(['codigo' => 'El código del catálogo es obligatorio.']);
        }
        if ($nombre === null) {
            throw ValidationException::withMessages(['nombre' => 'El nombre del catálogo es obligatorio.']);
        }

        $duplicado = DiagnosticoCatalogo::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('codigo', $codigo)
            ->when($existente !== null, fn ($q) => $q->where('id', '!=', $existente->id))
            ->exists();
        if ($duplicado) {
            throw ValidationException::withMessages(['codigo' => 'Ya existe ese código en el catálogo.']);
        }

        if ($existente === null) {
            return DiagnosticoCatalogo::create([
                'id_empresa' => $idEmpresa,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'activo' => true,
            ]);
        }

        $existente->update([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : $existente->activo,
        ]);

        return $existente->fresh();
    }

    public function crear(int $idEmpresa, ?int $idUsuarioRegistro, Paciente $paciente, array $datos): Diagnostico
    {
        if (! $paciente->activo) {
            throw ValidationException::withMessages(['paciente' => 'El paciente debe estar activo.']);
        }
        $this->expedientes->exigirOperativo($paciente);
        $expediente = $this->expedientes->dePaciente($paciente);
        $atributos = $this->atributos($idEmpresa, $expediente, $paciente, $datos);

        return DB::transaction(function () use ($idEmpresa, $idUsuarioRegistro, $atributos) {
            $diagnostico = Diagnostico::create($atributos + [
                'id_empresa' => $idEmpresa,
                'id_usuario_registro' => $idUsuarioRegistro,
                'estado' => 'activo',
            ]);
            $this->asegurarUnicoPrincipal($diagnostico->fresh());

            return $diagnostico->fresh();
        });
    }

    public function actualizar(Diagnostico $diagnostico, array $datos): Diagnostico
    {
        $this->exigirEditable($diagnostico);
        $paciente = Paciente::findOrFail($diagnostico->id_paciente);
        $expediente = $this->expedientes->dePaciente($paciente);
        $diagnostico->update($this->atributos((int) $diagnostico->id_empresa, $expediente, $paciente, $datos, $diagnostico));

        return DB::transaction(function () use ($diagnostico) {
            $this->asegurarUnicoPrincipal($diagnostico->fresh());

            return $diagnostico->fresh();
        });
    }

    public function cerrar(Diagnostico $diagnostico): Diagnostico
    {
        if ($diagnostico->estado !== 'activo') {
            throw ValidationException::withMessages(['estado' => 'Solo un diagnóstico activo puede cerrarse.']);
        }

        return DB::transaction(function () use ($diagnostico) {
            $diagnostico->update(['estado' => 'cerrado']);
            $this->sincronizarHistorial($diagnostico->fresh());

            return $diagnostico->fresh();
        });
    }

    public function anular(Diagnostico $diagnostico, string $motivo): Diagnostico
    {
        $motivo = PacienteReglas::vacio($motivo);
        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo_anulacion' => 'Indica el motivo de anulación.']);
        }
        if ($diagnostico->estado === 'anulado') {
            throw ValidationException::withMessages(['estado' => 'El diagnóstico ya está anulado.']);
        }

        return DB::transaction(function () use ($diagnostico, $motivo) {
            $diagnostico->update(['estado' => 'anulado', 'motivo_anulacion' => $motivo]);
            $this->sincronizarHistorial($diagnostico->fresh(), 'anulado');

            return $diagnostico->fresh();
        });
    }

    public function corregir(Diagnostico $diagnostico, int $idEmpresa, ?int $idUsuarioRegistro, string $motivo, array $datos): Diagnostico
    {
        if ($diagnostico->estado !== 'cerrado') {
            throw ValidationException::withMessages(['estado' => 'Solo se corrige un diagnóstico cerrado mediante un registro nuevo.']);
        }
        $motivo = PacienteReglas::vacio($motivo);
        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo de la corrección.']);
        }

        $paciente = Paciente::findOrFail($diagnostico->id_paciente);

        return DB::transaction(function () use ($diagnostico, $idEmpresa, $idUsuarioRegistro, $motivo, $datos, $paciente) {
            $this->anular($diagnostico, $motivo);
            $datos['id_consulta'] = $datos['id_consulta'] ?? $diagnostico->id_consulta;
            $datos['id_sucursal'] = $datos['id_sucursal'] ?? $diagnostico->id_sucursal;
            $datos['id_usuario_profesional'] = $datos['id_usuario_profesional'] ?? $diagnostico->id_usuario_profesional;
            $nuevo = $this->crear($idEmpresa, $idUsuarioRegistro, $paciente, $datos);
            $nuevo->update(['id_diagnostico_anterior' => $diagnostico->id]);

            return $nuevo->fresh();
        });
    }

    public function cerrarActivosPorConsulta(Consulta $consulta): void
    {
        Diagnostico::withoutGlobalScopes()
            ->where('id_consulta', $consulta->id)
            ->where('estado', 'activo')
            ->orderBy('id')
            ->get()
            ->each(fn (Diagnostico $d) => $this->cerrar($d));
    }

    public function presentar(Diagnostico $diagnostico, bool $detalle): array
    {
        $profesional = User::withoutGlobalScopes()->find($diagnostico->id_usuario_profesional, ['id', 'name']);
        $base = [
            'id' => $diagnostico->id,
            'id_paciente' => $diagnostico->id_paciente,
            'id_expediente' => $diagnostico->id_expediente,
            'id_consulta' => $diagnostico->id_consulta,
            'id_sucursal' => $diagnostico->id_sucursal,
            'id_diagnostico_anterior' => $diagnostico->id_diagnostico_anterior,
            'codigo' => $diagnostico->codigo,
            'descripcion' => $detalle ? $diagnostico->descripcion : mb_substr($diagnostico->descripcion, 0, 120),
            'rol' => $diagnostico->rol,
            'fecha' => $diagnostico->fecha?->format('Y-m-d'),
            'estado' => $diagnostico->estado,
            'motivo_anulacion' => $diagnostico->motivo_anulacion,
            'editable' => $this->esEditable($diagnostico),
            'puede_corregir' => $diagnostico->estado === 'cerrado',
            'profesional' => $profesional ? ['id' => $profesional->id, 'nombre' => $profesional->name] : null,
        ];

        return $base;
    }

    private function atributos(int $idEmpresa, $expediente, Paciente $paciente, array $datos, ?Diagnostico $actual = null): array
    {
        $descripcion = PacienteReglas::vacio($datos['descripcion'] ?? null);
        if ($descripcion === null) {
            throw ValidationException::withMessages(['descripcion' => 'La descripción del diagnóstico es obligatoria.']);
        }
        $fecha = PacienteReglas::vacio($datos['fecha'] ?? null);
        if ($fecha === null) {
            throw ValidationException::withMessages(['fecha' => 'La fecha es obligatoria.']);
        }

        $rol = strtolower((string) ($datos['rol'] ?? 'secundario'));
        if (! in_array($rol, ['principal', 'secundario'], true)) {
            throw ValidationException::withMessages(['rol' => 'El rol debe ser principal o secundario.']);
        }

        $codigo = PacienteReglas::vacio($datos['codigo'] ?? null);
        if ($codigo !== null) {
            $this->exigirCodigoCatalogo($idEmpresa, $codigo);
        }

        $idProfesionalUsuario = (int) ($datos['id_usuario_profesional'] ?? 0);
        $idSucursal = (int) ($datos['id_sucursal'] ?? 0);
        $this->exigirProfesionalEnSucursal($idEmpresa, $idProfesionalUsuario, $idSucursal);

        $idConsulta = ! empty($datos['id_consulta']) ? (int) $datos['id_consulta'] : null;
        if ($idConsulta !== null) {
            $consulta = Consulta::withoutGlobalScopes()
                ->where('id', $idConsulta)
                ->where('id_paciente', $paciente->id)
                ->where('id_empresa', $idEmpresa)
                ->first();
            if ($consulta === null) {
                throw ValidationException::withMessages(['id_consulta' => 'La consulta no pertenece al paciente.']);
            }
            if ($consulta->estado !== 'borrador' && ($actual === null || $actual->id_consulta !== $idConsulta)) {
                throw ValidationException::withMessages(['id_consulta' => 'Solo se vinculan diagnósticos a consultas en borrador.']);
            }
            if ($actual !== null && $actual->estado === 'activo' && $consulta->estado !== 'borrador') {
                throw ValidationException::withMessages(['estado' => 'No se puede editar un diagnóstico ligado a una consulta cerrada.']);
            }
        }

        return [
            'id_expediente' => $expediente->id,
            'id_paciente' => $paciente->id,
            'id_consulta' => $idConsulta,
            'id_sucursal' => $idSucursal,
            'id_usuario_profesional' => $idProfesionalUsuario,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'rol' => $rol,
            'fecha' => $fecha,
        ];
    }

    private function exigirCodigoCatalogo(int $idEmpresa, string $codigo): void
    {
        $existe = DiagnosticoCatalogo::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('codigo', $codigo)
            ->where('activo', true)
            ->exists();
        if (! $existe) {
            throw ValidationException::withMessages(['codigo' => 'El código no existe en el catálogo activo de la empresa.']);
        }
    }

    private function asegurarUnicoPrincipal(Diagnostico $diagnostico): void
    {
        if ($diagnostico->rol !== 'principal' || $diagnostico->id_consulta === null || $diagnostico->estado === 'anulado') {
            return;
        }

        Diagnostico::withoutGlobalScopes()
            ->where('id_consulta', $diagnostico->id_consulta)
            ->where('rol', 'principal')
            ->where('estado', '!=', 'anulado')
            ->where('id', '!=', $diagnostico->id)
            ->update(['rol' => 'secundario']);
    }

    private function sincronizarHistorial(Diagnostico $diagnostico, string $estadoEvento = 'activo'): void
    {
        $expediente = $this->expedientes->dePaciente(Paciente::findOrFail($diagnostico->id_paciente));
        $etiqueta = $diagnostico->rol === 'principal' ? 'Diagnóstico principal' : 'Diagnóstico';
        $resumen = $etiqueta.': '.$diagnostico->descripcion;
        if ($diagnostico->codigo) {
            $resumen = $etiqueta.' ['.$diagnostico->codigo.']: '.$diagnostico->descripcion;
        }

        $this->historial->registrar(
            $expediente,
            HistorialClinicoService::TIPO_DIAGNOSTICO,
            $diagnostico->fecha->format('Y-m-d'),
            null,
            'diagnostico',
            (int) $diagnostico->id,
            $resumen,
            $estadoEvento,
            (int) $diagnostico->id_usuario_profesional,
        );
    }

    private function esEditable(Diagnostico $diagnostico): bool
    {
        if ($diagnostico->estado !== 'activo') {
            return false;
        }
        if ($diagnostico->id_consulta === null) {
            return true;
        }
        $consulta = Consulta::withoutGlobalScopes()->find($diagnostico->id_consulta);

        return $consulta !== null && $consulta->estado === 'borrador';
    }

    private function exigirEditable(Diagnostico $diagnostico): void
    {
        if (! $this->esEditable($diagnostico)) {
            throw ValidationException::withMessages(['estado' => 'El diagnóstico ya no se puede editar.']);
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
}
