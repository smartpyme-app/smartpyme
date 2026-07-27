<?php

namespace App\Services\Comisiones;

use App\Models\Admin\EmpresaFuncionalidad;
use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionPeriodo;
use App\Services\Funcionalidades\FuncionalidadAccess;
use Carbon\Carbon;
use Closure;

class ComisionPeriodoService
{
    /** @var Closure(int): ?object */
    private Closure $findPeriodoById;

    /** @var Closure(int, Carbon): ?object */
    private Closure $findNextAbiertoAfter;

    /** @var Closure(int, Carbon, Carbon): object */
    private Closure $firstOrCreatePeriodo;

    /**
     * @param  Closure(int): ?object|null  $findPeriodoById
     * @param  Closure(int, Carbon): ?object|null  $findNextAbiertoAfter
     * @param  Closure(int, Carbon, Carbon): object|null  $firstOrCreatePeriodo
     */
    public function __construct(
        ?Closure $findPeriodoById = null,
        ?Closure $findNextAbiertoAfter = null,
        ?Closure $firstOrCreatePeriodo = null
    ) {
        $defaults = self::defaultClosures();
        $this->findPeriodoById = $findPeriodoById ?? $defaults['findPeriodoById'];
        $this->findNextAbiertoAfter = $findNextAbiertoAfter ?? $defaults['findNextAbiertoAfter'];
        $this->firstOrCreatePeriodo = $firstOrCreatePeriodo ?? $defaults['firstOrCreatePeriodo'];
    }

    public function periodoParaFecha(int $idEmpresa, Carbon $fecha): object
    {
        $inicio = $fecha->copy()->startOfMonth();
        $fin = $fecha->copy()->endOfMonth();

        return ($this->firstOrCreatePeriodo)($idEmpresa, $inicio, $fin);
    }

    public function periodoParaAjuste(object $original): object
    {
        $periodo = $original->id_periodo
            ? ($this->findPeriodoById)((int) $original->id_periodo)
            : null;

        if ($periodo === null) {
            $fecha = $original->fecha_evento
                ? Carbon::parse($original->fecha_evento)
                : Carbon::now();

            return $this->periodoParaFecha((int) $original->id_empresa, $fecha);
        }

        if ($periodo->estado !== ComisionPeriodo::ESTADO_PAGADO) {
            return $periodo;
        }

        $next = ($this->findNextAbiertoAfter)(
            (int) $original->id_empresa,
            Carbon::parse($periodo->fecha_fin)
        );

        if ($next !== null) {
            return $next;
        }

        return $this->periodoParaFecha((int) $original->id_empresa, Carbon::now());
    }

    /** @return array{findPeriodoById: Closure, findNextAbiertoAfter: Closure, firstOrCreatePeriodo: Closure} */
    private static function defaultClosures(): array
    {
        return [
            'findPeriodoById' => fn (int $id) => ComisionPeriodo::withoutGlobalScope('empresa')->find($id),
            'findNextAbiertoAfter' => function (int $idEmpresa, Carbon $afterFin) {
                return ComisionPeriodo::withoutGlobalScope('empresa')
                    ->where('id_empresa', $idEmpresa)
                    ->where('estado', ComisionPeriodo::ESTADO_ABIERTO)
                    ->where('fecha_inicio', '>', $afterFin->toDateString())
                    ->orderBy('fecha_inicio')
                    ->first();
            },
            'firstOrCreatePeriodo' => function (int $idEmpresa, Carbon $inicio, Carbon $fin) {
                return ComisionPeriodo::withoutGlobalScope('empresa')->firstOrCreate(
                    [
                        'id_empresa' => $idEmpresa,
                        'fecha_inicio' => $inicio->toDateString(),
                        'fecha_fin' => $fin->toDateString(),
                    ],
                    ['estado' => ComisionPeriodo::ESTADO_ABIERTO]
                );
            },
        ];
    }
}
