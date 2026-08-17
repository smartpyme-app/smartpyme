<?php

namespace App\Services\Bonos;

use App\Models\Bonos\BonoGenerado;
use App\Models\Bonos\BonoRegla;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BonoGeneradoService
{
    /**
     * @param  Closure(int, int): BonoGenerado|null  $findForUpdate
     * @param  Closure(BonoGenerado, array<string, mixed>): void|null  $persist
     * @param  Closure(int, int): ?object|null  $obtenerRegla
     * @param  Closure(array<string, mixed>): bool|null  $existeGenerado
     * @param  Closure(array<string, mixed>): BonoGenerado|null  $crearGenerado
     * @param  Closure(int): ?object|null  $obtenerVendedor
     */
    public function __construct(
        private ?Closure $findForUpdate = null,
        private ?Closure $persist = null,
        private ?Closure $obtenerRegla = null,
        private ?Closure $existeGenerado = null,
        private ?Closure $crearGenerado = null,
        private ?Closure $obtenerVendedor = null,
    ) {
    }

    /** @return EloquentCollection<int, BonoGenerado> */
    public function listar(
        int $idEmpresa,
        ?string $estado = null,
        ?string $periodoInicio = null,
        ?string $periodoFin = null,
        ?int $idVendedor = null,
    ): EloquentCollection {
        $query = BonoGenerado::query()
            ->where('id_empresa', $idEmpresa)
            ->with(['vendedor', 'regla', 'aprobadoPor'])
            ->orderByDesc('periodo_inicio')
            ->orderByDesc('id');

        if ($estado !== null && $estado !== '') {
            $query->where('estado', $estado);
        }

        if ($periodoInicio !== null && $periodoInicio !== '') {
            $query->where('periodo_inicio', $periodoInicio);
        }

        if ($periodoFin !== null && $periodoFin !== '') {
            $query->where('periodo_fin', $periodoFin);
        }

        if ($idVendedor !== null) {
            $query->where('id_vendedor', $idVendedor);
        }

        return $query->get();
    }

    public function aprobar(int $idEmpresa, int $id, int $idUsuario): BonoGenerado
    {
        return DB::transaction(function () use ($idEmpresa, $id, $idUsuario) {
            $bono = $this->obtenerParaTransicion($idEmpresa, $id);

            if ($bono->estado !== BonoGenerado::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages([
                    'estado' => ['Solo se pueden aprobar bonos en estado pendiente.'],
                ]);
            }

            $this->guardar($bono, [
                'estado' => BonoGenerado::ESTADO_APROBADO,
                'aprobado_por' => $idUsuario,
                'aprobado_at' => Carbon::now(),
            ]);

            return $bono->fresh(['vendedor', 'regla', 'aprobadoPor']);
        });
    }

    public function pagar(int $idEmpresa, int $id): BonoGenerado
    {
        return DB::transaction(function () use ($idEmpresa, $id) {
            $bono = $this->obtenerParaTransicion($idEmpresa, $id);

            if ($bono->estado !== BonoGenerado::ESTADO_APROBADO) {
                throw ValidationException::withMessages([
                    'estado' => ['Solo se pueden pagar bonos en estado aprobado.'],
                ]);
            }

            $this->guardar($bono, [
                'estado' => BonoGenerado::ESTADO_PAGADO,
                'pagado_at' => Carbon::now(),
            ]);

            return $bono->fresh(['vendedor', 'regla', 'aprobadoPor']);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function crearManual(int $idEmpresa, array $data): BonoGenerado
    {
        $idRegla = (int) ($data['id_regla'] ?? 0);
        $idVendedor = (int) ($data['id_vendedor'] ?? 0);
        $regla = $this->resolverRegla($idEmpresa, $idRegla);

        if ($regla === null || ($regla->tipo ?? '') !== BonoRegla::TIPO_CUALITATIVO_MANUAL) {
            throw ValidationException::withMessages([
                'id_regla' => ['Solo se pueden asignar manualmente bonos cualitativos.'],
            ]);
        }

        $vendedor = $this->resolverVendedor($idVendedor);
        if ($vendedor === null || (int) ($vendedor->id_empresa ?? 0) !== $idEmpresa) {
            throw ValidationException::withMessages([
                'id_vendedor' => ['El vendedor no pertenece a la empresa.'],
            ]);
        }

        $alcance = BonoRegla::alcanceEfectivo($regla);
        $ids = array_map('intval', (array) ($regla->id_vendedores ?? []));
        if ($alcance !== BonoRegla::ALCANCE_GLOBAL && ! in_array($idVendedor, $ids, true)) {
            throw ValidationException::withMessages([
                'id_vendedor' => ['El vendedor no está cubierto por el alcance de la regla.'],
            ]);
        }

        $unique = [
            'id_empresa' => $idEmpresa,
            'id_vendedor' => $idVendedor,
            'id_regla' => $idRegla,
            'periodo_inicio' => $data['periodo_inicio'],
            'periodo_fin' => $data['periodo_fin'],
        ];

        if ($this->yaExiste($unique)) {
            throw ValidationException::withMessages([
                'id_regla' => ['Ya existe un bono para este vendedor, regla y período.'],
            ]);
        }

        $payload = array_merge($unique, [
            'monto' => (float) ($data['monto'] ?? 0),
            'monto_ventas_base' => (float) ($data['monto_ventas_base'] ?? 0),
            'estado' => BonoGenerado::ESTADO_PENDIENTE,
            'origen' => BonoGenerado::ORIGEN_MANUAL,
        ]);

        if ($this->crearGenerado !== null) {
            return ($this->crearGenerado)($payload);
        }

        return BonoGenerado::withoutGlobalScope('empresa')->create($payload);
    }

    /** @return array{bono: BonoGenerado, empresa: \App\Models\Admin\Empresa|null} */
    public function datosComprobante(int $idEmpresa, int $id): array
    {
        $bono = BonoGenerado::query()
            ->where('id_empresa', $idEmpresa)
            ->with(['vendedor', 'regla', 'aprobadoPor', 'empresa'])
            ->findOrFail($id);

        if (! in_array($bono->estado, [BonoGenerado::ESTADO_APROBADO, BonoGenerado::ESTADO_PAGADO], true)) {
            throw ValidationException::withMessages([
                'estado' => ['Solo se puede imprimir comprobante de bonos aprobados o pagados.'],
            ]);
        }

        return [
            'bono' => $bono,
            'empresa' => $bono->empresa,
        ];
    }

    private function obtenerParaTransicion(int $idEmpresa, int $id): BonoGenerado
    {
        if ($this->findForUpdate !== null) {
            $bono = ($this->findForUpdate)($idEmpresa, $id);

            if ($bono === null) {
                abort(404);
            }

            return $bono;
        }

        return BonoGenerado::query()
            ->where('id_empresa', $idEmpresa)
            ->lockForUpdate()
            ->findOrFail($id);
    }

    /** @param  array<string, mixed>  $values */
    private function guardar(BonoGenerado $bono, array $values): void
    {
        if ($this->persist !== null) {
            ($this->persist)($bono, $values);

            return;
        }

        $bono->update($values);
    }

    private function resolverRegla(int $idEmpresa, int $idRegla): ?object
    {
        if ($this->obtenerRegla !== null) {
            return ($this->obtenerRegla)($idEmpresa, $idRegla);
        }

        return BonoRegla::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->find($idRegla);
    }

    /** @param  array<string, mixed>  $unique */
    private function yaExiste(array $unique): bool
    {
        if ($this->existeGenerado !== null) {
            return ($this->existeGenerado)($unique);
        }

        return BonoGenerado::withoutGlobalScope('empresa')->where($unique)->exists();
    }

    private function resolverVendedor(int $idVendedor): ?object
    {
        if ($this->obtenerVendedor !== null) {
            return ($this->obtenerVendedor)($idVendedor);
        }

        return User::withoutGlobalScope('empresa')->find($idVendedor);
    }
}
