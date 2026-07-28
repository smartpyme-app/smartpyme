<?php

namespace App\Services\Bonos;

use App\Models\Bonos\BonoGenerado;
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
     */
    public function __construct(
        private ?Closure $findForUpdate = null,
        private ?Closure $persist = null,
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
}
