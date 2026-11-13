<?php

namespace App\Services\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Profesional;
use App\Models\Clinica\VacunaAplicacion;
use App\Models\Clinica\VacunaCatalogo;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacunaService
{
    public function __construct(
        private ExpedienteService $expedientes,
        private HistorialClinicoService $historial,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listarCatalogo(int $idEmpresa, Paciente $paciente, ?string $q = null): array
    {
        $consulta = VacunaCatalogo::query()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->orderBy('nombre');

        $this->aplicarFiltroPacienteCatalogo($consulta, $paciente);

        $q = PacienteReglas::vacio($q);
        if ($q !== null) {
            $consulta->where('nombre', 'like', '%'.$q.'%');
        }

        return $consulta->limit(50)->get()->map(fn (VacunaCatalogo $c) => [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'tipo' => $c->tipo,
            'intervalo_dias' => $c->intervalo_dias,
        ])->all();
    }

    public function guardarCatalogo(int $idEmpresa, array $datos, ?VacunaCatalogo $existente = null): VacunaCatalogo
    {
        $nombre = PacienteReglas::vacio($datos['nombre'] ?? null);
        if ($nombre === null) {
            throw ValidationException::withMessages(['nombre' => 'El nombre es obligatorio.']);
        }
        $tipo = PacienteReglas::vacio($datos['tipo'] ?? null) ?? 'vacuna';
        if (! in_array($tipo, ['vacuna', 'preventivo'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo inválido.']);
        }

        $payload = [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'tipo_paciente' => PacienteReglas::vacio($datos['tipo_paciente'] ?? null),
            'id_especie' => ! empty($datos['id_especie']) ? (int) $datos['id_especie'] : null,
            'intervalo_dias' => ! empty($datos['intervalo_dias']) ? (int) $datos['intervalo_dias'] : null,
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : true,
        ];

        if ($existente === null) {
            return VacunaCatalogo::create(['id_empresa' => $idEmpresa] + $payload);
        }

        $existente->update($payload);

        return $existente->fresh();
    }

    public function registrar(int $idEmpresa, ?int $idUsuarioRegistro, Paciente $paciente, array $datos): VacunaAplicacion
    {
        if (! $paciente->activo) {
            throw ValidationException::withMessages(['paciente' => 'El paciente debe estar activo.']);
        }
        $this->expedientes->exigirOperativo($paciente);
        $expediente = $this->expedientes->dePaciente($paciente);
        $atributos = $this->atributosAplicacion($idEmpresa, $expediente, $paciente, $datos);

        return DB::transaction(function () use ($idEmpresa, $idUsuarioRegistro, $atributos) {
            $aplicacion = VacunaAplicacion::create($atributos + [
                'id_empresa' => $idEmpresa,
                'id_usuario_registro' => $idUsuarioRegistro,
                'estado' => 'activa',
            ]);
            $this->sincronizarHistorial($aplicacion);

            return $aplicacion;
        });
    }

    public function anular(VacunaAplicacion $aplicacion, string $motivo): VacunaAplicacion
    {
        $motivo = PacienteReglas::vacio($motivo);
        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo_anulacion' => 'Indica el motivo.']);
        }
        if ($aplicacion->estado !== 'activa') {
            throw ValidationException::withMessages(['estado' => 'La aplicación ya está anulada.']);
        }

        $aplicacion->update(['estado' => 'anulada', 'motivo_anulacion' => $motivo]);
        $this->sincronizarHistorial($aplicacion->fresh(), 'anulado');

        return $aplicacion->fresh();
    }

    public function presentar(VacunaAplicacion $aplicacion, bool $detalle): array
    {
        $profesional = User::withoutGlobalScopes()->find($aplicacion->id_usuario_profesional, ['id', 'name']);

        return [
            'id' => $aplicacion->id,
            'id_paciente' => $aplicacion->id_paciente,
            'nombre_vacuna' => $aplicacion->nombre_vacuna,
            'tipo' => $aplicacion->tipo,
            'fecha_aplicacion' => $aplicacion->fecha_aplicacion?->format('Y-m-d'),
            'dosis' => $aplicacion->dosis,
            'proxima_fecha' => $aplicacion->proxima_fecha?->format('Y-m-d'),
            'estado' => $aplicacion->estado,
            'motivo_anulacion' => $detalle ? $aplicacion->motivo_anulacion : null,
            'profesional' => $profesional ? ['id' => $profesional->id, 'nombre' => $profesional->name] : null,
            'lote_numero' => $detalle ? $aplicacion->lote_numero : null,
            'lote_vencimiento' => $detalle && $aplicacion->lote_vencimiento
                ? $aplicacion->lote_vencimiento->format('Y-m-d')
                : null,
            'fabricante' => $detalle ? $aplicacion->fabricante : null,
            'observaciones' => $detalle ? $aplicacion->observaciones : null,
        ];
    }

    /** @return array<string, mixed> */
    private function atributosAplicacion(int $idEmpresa, $expediente, Paciente $paciente, array $datos): array
    {
        $fecha = PacienteReglas::vacio($datos['fecha_aplicacion'] ?? null);
        if ($fecha === null) {
            throw ValidationException::withMessages(['fecha_aplicacion' => 'La fecha es obligatoria.']);
        }

        $idCatalogo = ! empty($datos['id_catalogo']) ? (int) $datos['id_catalogo'] : null;
        $nombre = PacienteReglas::vacio($datos['nombre_vacuna'] ?? null);
        $tipo = PacienteReglas::vacio($datos['tipo'] ?? null) ?? 'vacuna';
        $intervaloDias = null;

        if ($idCatalogo !== null) {
            $catalogo = VacunaCatalogo::query()
                ->where('id', $idCatalogo)
                ->where('id_empresa', $idEmpresa)
                ->where('activo', true)
                ->first();
            if ($catalogo === null) {
                throw ValidationException::withMessages(['id_catalogo' => 'Vacuna no encontrada en el catálogo.']);
            }
            $this->exigirCatalogoCompatible($catalogo, $paciente);
            if ($nombre === null) {
                $nombre = $catalogo->nombre;
            }
            $tipo = $catalogo->tipo;
            $intervaloDias = $catalogo->intervalo_dias;
        }

        if ($nombre === null) {
            throw ValidationException::withMessages(['nombre_vacuna' => 'Indica la vacuna o preventivo.']);
        }

        $proxima = PacienteReglas::vacio($datos['proxima_fecha'] ?? null);
        if ($proxima === null && $intervaloDias !== null && $intervaloDias > 0) {
            $proxima = date('Y-m-d', strtotime($fecha.' +'.$intervaloDias.' days'));
        }
        if ($proxima !== null && $proxima < $fecha) {
            throw ValidationException::withMessages(['proxima_fecha' => 'La próxima fecha no puede ser anterior a la aplicación.']);
        }

        $idProfesionalUsuario = (int) ($datos['id_usuario_profesional'] ?? 0);
        $idSucursal = (int) ($datos['id_sucursal'] ?? 0);
        $this->exigirProfesionalEnSucursal($idEmpresa, $idProfesionalUsuario, $idSucursal);

        [$idProducto, $idLote, $loteNumero, $loteVencimiento, $fabricante] = $this->resolverLote(
            $idEmpresa,
            $datos,
        );

        return [
            'id_expediente' => $expediente->id,
            'id_paciente' => $paciente->id,
            'id_catalogo' => $idCatalogo,
            'nombre_vacuna' => $nombre,
            'tipo' => $tipo,
            'fecha_aplicacion' => $fecha,
            'dosis' => PacienteReglas::vacio($datos['dosis'] ?? null),
            'id_sucursal' => $idSucursal,
            'id_usuario_profesional' => $idProfesionalUsuario,
            'id_producto' => $idProducto,
            'id_lote' => $idLote,
            'lote_numero' => $loteNumero,
            'lote_vencimiento' => $loteVencimiento,
            'fabricante' => $fabricante ?? PacienteReglas::vacio($datos['fabricante'] ?? null),
            'proxima_fecha' => $proxima,
            'observaciones' => PacienteReglas::vacio($datos['observaciones'] ?? null),
        ];
    }

    /** @return array{0: ?int, 1: ?int, 2: ?string, 3: ?string, 4: ?string} */
    private function resolverLote(int $idEmpresa, array $datos): array
    {
        $idLote = ! empty($datos['id_lote']) ? (int) $datos['id_lote'] : null;
        $idProducto = ! empty($datos['id_producto']) ? (int) $datos['id_producto'] : null;
        $loteNumero = PacienteReglas::vacio($datos['lote_numero'] ?? null);
        $loteVencimiento = PacienteReglas::vacio($datos['lote_vencimiento'] ?? null);
        $fabricante = PacienteReglas::vacio($datos['fabricante'] ?? null);

        if ($idLote !== null) {
            $lote = Lote::withoutGlobalScopes()->where('id', $idLote)->first();
            if ($lote === null) {
                throw ValidationException::withMessages(['id_lote' => 'Lote no encontrado.']);
            }
            $producto = Producto::withoutGlobalScopes()->where('id', $lote->id_producto ?? 0)->where('id_empresa', $idEmpresa)->first();
            if ($producto === null) {
                throw ValidationException::withMessages(['id_lote' => 'El lote no pertenece a la empresa.']);
            }
            $idProducto = (int) $producto->id;
            $loteNumero = $lote->numero_lote ?? $loteNumero;
            $loteVencimiento = $lote->fecha_vencimiento?->format('Y-m-d') ?? $loteVencimiento;
        } elseif ($idProducto !== null) {
            $ok = Producto::withoutGlobalScopes()->where('id', $idProducto)->where('id_empresa', $idEmpresa)->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['id_producto' => 'Producto inválido.']);
            }
        }

        return [$idProducto, $idLote, $loteNumero, $loteVencimiento, $fabricante];
    }

    /** @return list<array<string, mixed>> */
    public function listarAplicaciones(Paciente $paciente, bool $detalle): array
    {
        return VacunaAplicacion::query()
            ->where('id_paciente', $paciente->id)
            ->orderByDesc('fecha_aplicacion')
            ->orderByDesc('id')
            ->get()
            ->map(fn (VacunaAplicacion $a) => $this->presentar($a, $detalle))
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function listarCatalogoAdmin(int $idEmpresa, ?string $q = null): array
    {
        $consulta = VacunaCatalogo::query()
            ->where('id_empresa', $idEmpresa)
            ->orderBy('nombre');

        $q = PacienteReglas::vacio($q);
        if ($q !== null) {
            $consulta->where('nombre', 'like', '%'.$q.'%');
        }

        return $consulta->limit(100)->get()->map(fn (VacunaCatalogo $c) => [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'tipo' => $c->tipo,
            'tipo_paciente' => $c->tipo_paciente,
            'id_especie' => $c->id_especie,
            'intervalo_dias' => $c->intervalo_dias,
            'activo' => (bool) $c->activo,
        ])->all();
    }

    private function exigirCatalogoCompatible(VacunaCatalogo $catalogo, Paciente $paciente): void
    {
        if ($catalogo->tipo_paciente === 'HUMANO' && $paciente->tipo !== 'HUMANO') {
            throw ValidationException::withMessages(['id_catalogo' => 'Esta vacuna no aplica a pacientes animales.']);
        }
        if ($catalogo->tipo_paciente === 'ANIMAL' && $paciente->tipo !== 'ANIMAL') {
            throw ValidationException::withMessages(['id_catalogo' => 'Esta vacuna no aplica a pacientes humanos.']);
        }
        if ($catalogo->id_especie !== null && (int) $paciente->id_especie !== (int) $catalogo->id_especie) {
            throw ValidationException::withMessages(['id_catalogo' => 'Esta vacuna no aplica a la especie del paciente.']);
        }
    }

    private function aplicarFiltroPacienteCatalogo($consulta, Paciente $paciente): void
    {
        $consulta->where(function ($q) use ($paciente) {
            $q->whereNull('tipo_paciente');
            if ($paciente->tipo === 'HUMANO') {
                $q->orWhere('tipo_paciente', 'HUMANO');
            } else {
                $q->orWhere('tipo_paciente', 'ANIMAL');
            }
            if ($paciente->id_especie) {
                $q->orWhere('id_especie', $paciente->id_especie);
            }
        });
    }

    private function sincronizarHistorial(VacunaAplicacion $aplicacion, string $estadoEvento = 'activo'): void
    {
        $expediente = $this->expedientes->dePaciente(Paciente::findOrFail($aplicacion->id_paciente));
        $this->historial->registrar(
            $expediente,
            'vacuna',
            $aplicacion->fecha_aplicacion->format('Y-m-d'),
            null,
            'vacuna',
            (int) $aplicacion->id,
            'Vacuna/preventivo: '.$aplicacion->nombre_vacuna.' ('.$aplicacion->estado.')',
            $estadoEvento,
            (int) $aplicacion->id_usuario_profesional,
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
}
