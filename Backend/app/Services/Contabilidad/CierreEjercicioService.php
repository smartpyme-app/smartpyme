<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\Empresa;
use App\Models\Contabilidad\EjercicioFiscal;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\SaldoMensual;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class CierreEjercicioService
{
    public function __construct(
        protected CierreMesService $cierreMesService,
        protected CierreResultadosService $cierreResultadosService
    ) {
    }

    /**
     * @throws Exception
     */
    public function obtenerEstado(int $empresaId, int $anioReferencia): array
    {
        $empresa = Empresa::where('id', $empresaId)->firstOrFail();
        $mesInicio = (int) ($empresa->mes_inicio_ejercicio_fiscal ?: 1);

        $periodos = FiscalYearCalendar::periodosEnEjercicio($mesInicio, $anioReferencia);
        if (count($periodos) !== 12) {
            throw new Exception('Configuración de ejercicio inválida.');
        }

        $ultimo = $periodos[11];
        $primerosOnce = array_slice($periodos, 0, 11);

        $meses = [];
        foreach ($periodos as $p) {
            $tieneAbierto = SaldoMensual::where('id_empresa', $empresaId)
                ->where('year', $p['year'])
                ->where('month', $p['month'])
                ->where('estado', 'Abierto')
                ->exists();

            $tieneCerrado = SaldoMensual::where('id_empresa', $empresaId)
                ->where('year', $p['year'])
                ->where('month', $p['month'])
                ->where('estado', 'Cerrado')
                ->exists();

            $meses[] = [
                'year' => $p['year'],
                'month' => $p['month'],
                'cerrado' => $tieneCerrado && !$tieneAbierto,
                'tiene_registros' => SaldoMensual::where('id_empresa', $empresaId)
                    ->where('year', $p['year'])
                    ->where('month', $p['month'])
                    ->exists(),
            ];
        }

        $onceOk = true;
        foreach ($primerosOnce as $idx => $p) {
            $ok = false;
            foreach ($meses as $mx) {
                if ($mx['year'] === $p['year'] && $mx['month'] === $p['month']) {
                    $ok = $mx['cerrado'];
                    break;
                }
            }
            if (!$ok) {
                $onceOk = false;
                break;
            }
        }

        $ejercicio = EjercicioFiscal::where('id_empresa', $empresaId)
            ->where('anio_referencia', $anioReferencia)
            ->first();

        $ultimoMesInfo = null;
        foreach ($meses as $mx) {
            if ($mx['year'] === $ultimo['year'] && $mx['month'] === $ultimo['month']) {
                $ultimoMesInfo = $mx;
                break;
            }
        }

        $partidaCierre = Partida::where('id_empresa', $empresaId)
            ->where('referencia', CierreResultadosService::REFERENCIA_PARTIDA)
            ->where('id_referencia', $anioReferencia)
            ->where('estado', '!=', 'Anulada')
            ->orderByDesc('id')
            ->first();

        $tienePartidaCierreEjercicio = (bool) $partidaCierre;

        $partidaCierreFecha = null;
        if ($partidaCierre && $partidaCierre->fecha) {
            $partidaCierreFecha = Carbon::parse($partidaCierre->fecha)->format('Y-m-d');
        }

        return [
            'anio_referencia' => $anioReferencia,
            'mes_inicio_ejercicio_fiscal' => $mesInicio,
            'ultimo_mes' => $ultimo,
            'meses' => $meses,
            'once_primeros_meses_cerrados' => $onceOk,
            'configuracion_ok' => (bool) $empresa->id_cuenta_cierre_resultados,
            'ejercicio_cerrado' => $ejercicio && $ejercicio->estado === EjercicioFiscal::ESTADO_CERRADO,
            'ejercicio' => $ejercicio,
            'ultimo_mes_cerrado' => (bool) (($ultimoMesInfo ?? [])['cerrado'] ?? false),
            'tiene_partida_cierre_ejercicio' => $tienePartidaCierreEjercicio,
            'id_partida_cierre' => $partidaCierre ? (int) $partidaCierre->id : null,
            'partida_cierre_concepto' => $partidaCierre->concepto ?? null,
            'partida_cierre_fecha' => $partidaCierreFecha,
        ];
    }

    /**
     * @throws Exception
     */
    public function cerrarEjercicio(int $empresaId, int $usuarioId, int $anioReferencia): array
    {
        $empresa = Empresa::where('id', $empresaId)->firstOrFail();
        $mesInicio = (int) ($empresa->mes_inicio_ejercicio_fiscal ?: 1);

        if (!$empresa->id_cuenta_cierre_resultados) {
            throw new Exception('Configure en la empresa la cuenta de cierre de resultados (patrimonio).');
        }

        if (EjercicioFiscal::estaCerradoSinScope($empresaId, $anioReferencia)) {
            throw new Exception('El ejercicio fiscal ya está cerrado.');
        }

        $periodos = FiscalYearCalendar::periodosEnEjercicio($mesInicio, $anioReferencia);
        $primerosOnce = array_slice($periodos, 0, 11);
        $ultimo = $periodos[11];

        foreach ($primerosOnce as $p) {
            if (SaldoMensual::where('id_empresa', $empresaId)
                ->where('year', $p['year'])
                ->where('month', $p['month'])
                ->where('estado', 'Abierto')
                ->exists()
            ) {
                throw new Exception("Debe cerrar todos los meses previos del ejercicio: falta {$p['month']}/{$p['year']}.");
            }
            if (!SaldoMensual::where('id_empresa', $empresaId)
                ->where('year', $p['year'])
                ->where('month', $p['month'])
                ->where('estado', 'Cerrado')
                ->exists()
            ) {
                throw new Exception("Sin cierre registrado en {$p['month']}/{$p['year']}.");
            }
        }

        /**
         * El asiento de cierre se registra en el último mes del ejercicio; si ese mes figura cerrado
         * por un cierre mensual previo, hay que reabrirlo. `reabrirPeriodo` exige que el mes siguiente
         * no esté cerrado: si enero (y meses posteriores) también están cerrados, reabrimos en cadena
         * desde el período más lejano hacia el último mes del ejercicio.
         */
        if ($this->cierreMesService->estaPeriodoCerrado($ultimo['year'], $ultimo['month'], $empresaId)) {
            $partidaDuplicada = Partida::where('id_empresa', $empresaId)
                ->where('referencia', CierreResultadosService::REFERENCIA_PARTIDA)
                ->where('id_referencia', $anioReferencia)
                ->where('estado', '!=', 'Anulada')
                ->exists();

            if ($partidaDuplicada) {
                throw new Exception(
                    'Ya existe una partida de cierre de ejercicio fiscal para este año. '.
                    'Si el ejercicio aún aparece abierto, revise datos o contacte soporte.'
                );
            }

            try {
                $this->reabrirPeriodosCerradosHastaUltimoMesEjercicio(
                    $empresaId,
                    $ultimo['year'],
                    $ultimo['month'],
                    0
                );
            } catch (Exception $e) {
                throw new Exception(
                    'No se pudo reabrir el último mes del ejercicio para generar el cierre. '.
                    'Use «Cierre de mes» si necesita ajustar períodos manualmente. '.
                    'Detalle: '.$e->getMessage()
                );
            }
        }

        return DB::transaction(function () use ($empresaId, $usuarioId, $anioReferencia, $empresa, $mesInicio, $ultimo) {

            $partidaCierre = $this->cierreResultadosService->crearPartidaCierreResultados(
                $empresaId,
                $usuarioId,
                $mesInicio,
                $anioReferencia,
                (int) $empresa->id_cuenta_cierre_resultados
            );

            $cierreMes = $this->cierreMesService->cerrarMes(
                $ultimo['year'],
                $ultimo['month'],
                $usuarioId,
                $empresaId
            );

            $ejercicio = EjercicioFiscal::updateOrCreate(
                [
                    'id_empresa' => $empresaId,
                    'anio_referencia' => $anioReferencia,
                ],
                [
                    'estado' => EjercicioFiscal::ESTADO_CERRADO,
                    'id_partida_cierre' => $partidaCierre->id,
                    'id_partida_reversa' => null,
                    'id_usuario_cierre' => $usuarioId,
                    'cerrado_en' => now(),
                ]
            );

            return [
                'success' => true,
                'message' => 'Ejercicio fiscal cerrado correctamente.',
                'anio_referencia' => $anioReferencia,
                'id_partida_cierre' => $partidaCierre->id,
                'cierre_mes' => $cierreMes,
                'ejercicio_fiscal_id' => $ejercicio->id,
            ];
        });
    }

    /**
     * Reabre el último mes del ejercicio. Si el mes siguiente calendario está cerrado,
     * reabre primero ese período (recursivo), para cumplir la validación de {@see CierreMesService::reabrirPeriodo}.
     *
     * @throws Exception
     */
    private function reabrirPeriodosCerradosHastaUltimoMesEjercicio(
        int $empresaId,
        int $year,
        int $month,
        int $depth
    ): void {
        if ($depth > 60) {
            throw new Exception(
                'Cadena de meses cerrados demasiado larga; revise los períodos con «Cierre de mes».'
            );
        }

        if (!$this->cierreMesService->estaPeriodoCerrado($year, $month, $empresaId)) {
            return;
        }

        $siguiente = $month === 12
            ? ['year' => $year + 1, 'month' => 1]
            : ['year' => $year, 'month' => $month + 1];

        if (SaldoMensual::where('id_empresa', $empresaId)
            ->where('year', $siguiente['year'])
            ->where('month', $siguiente['month'])
            ->where('estado', 'Cerrado')
            ->exists()
        ) {
            $this->reabrirPeriodosCerradosHastaUltimoMesEjercicio(
                $empresaId,
                $siguiente['year'],
                $siguiente['month'],
                $depth + 1
            );
        }

        $this->cierreMesService->reabrirPeriodo($year, $month, $empresaId);
    }

    /**
     * @param  'eliminar'|'reversa'  $modo
     *
     * @throws Exception
     */
    public function reabrirEjercicio(int $empresaId, int $usuarioId, int $anioReferencia, string $modo): array
    {
        if (!in_array($modo, ['eliminar', 'reversa'], true)) {
            throw new Exception('Modo de reapertura inválido.');
        }

        $siguiente = $anioReferencia + 1;
        if (EjercicioFiscal::estaCerradoSinScope($empresaId, $siguiente)) {
            throw new Exception('No se puede reabrir: el ejercicio siguiente ('.$siguiente.') está cerrado.');
        }

        $ejercicio = EjercicioFiscal::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('anio_referencia', $anioReferencia)
            ->first();

        if (!$ejercicio || $ejercicio->estado !== EjercicioFiscal::ESTADO_CERRADO) {
            throw new Exception('El ejercicio no está cerrado o no existe.');
        }

        $empresa = Empresa::where('id', $empresaId)->firstOrFail();
        $mesInicio = (int) ($empresa->mes_inicio_ejercicio_fiscal ?: 1);
        $ultimo = FiscalYearCalendar::ultimoMesEjercicio($mesInicio, $anioReferencia);

        return DB::transaction(function () use ($empresaId, $usuarioId, $anioReferencia, $modo, $ejercicio, $ultimo) {

            $this->cierreMesService->reabrirPeriodo($ultimo['year'], $ultimo['month'], $empresaId);

            $partidaCierreId = $ejercicio->id_partida_cierre;
            $partidaCierre = $partidaCierreId ? Partida::withoutGlobalScopes()->where('id', $partidaCierreId)->where('id_empresa', $empresaId)->first() : null;

            $idReversa = null;

            if ($modo === 'eliminar') {
                if ($partidaCierre) {
                    Detalle::where('id_partida', $partidaCierre->id)->delete();
                    $partidaCierre->delete();
                }
            } else {
                if (!$partidaCierre) {
                    throw new Exception('No se encontró la partida de cierre para reversar.');
                }

                $detalles = Detalle::where('id_partida', $partidaCierre->id)->get();
                $reversa = new Partida;
                $reversa->fecha = $partidaCierre->fecha;
                $reversa->tipo = 'Cierre';
                $reversa->concepto = 'Reversa cierre ejercicio fiscal '.$anioReferencia;
                $reversa->estado = 'Aplicada';
                $reversa->referencia = 'ReversaCierreEjercicioFiscal';
                $reversa->id_referencia = $anioReferencia;
                $reversa->id_usuario = $usuarioId;
                $reversa->id_empresa = $empresaId;
                $reversa->save();

                foreach ($detalles as $det) {
                    $d = new Detalle;
                    $d->id_partida = $reversa->id;
                    $d->id_cuenta = $det->id_cuenta;
                    $d->codigo = $det->codigo;
                    $d->nombre_cuenta = $det->nombre_cuenta;
                    $d->concepto = 'Reversa: '.$det->concepto;
                    $d->debe = $det->haber;
                    $d->haber = $det->debe;
                    $d->saldo = 0;
                    $d->save();
                }
                $idReversa = $reversa->id;
            }

            $ejercicio->estado = EjercicioFiscal::ESTADO_ABIERTO;
            $ejercicio->id_partida_cierre = null;
            $ejercicio->id_partida_reversa = $idReversa;
            $ejercicio->id_usuario_cierre = null;
            $ejercicio->cerrado_en = null;
            $ejercicio->save();

            return [
                'success' => true,
                'message' => 'Ejercicio fiscal reabierto.',
                'modo' => $modo,
                'id_partida_reversa' => $idReversa,
            ];
        });
    }

    /**
     * Fecha (Y-m-d) pertenece a un ejercicio ya cerrado.
     */
    public static function fechaEnEjercicioCerrado(int $empresaId, string $fecha): bool
    {
        $empresa = Empresa::where('id', $empresaId)->first();
        if (!$empresa) {
            return false;
        }
        $mesInicio = (int) ($empresa->mes_inicio_ejercicio_fiscal ?: 1);
        $c = Carbon::parse($fecha);
        $ar = FiscalYearCalendar::anioReferenciaParaFecha($mesInicio, $c);

        return EjercicioFiscal::estaCerradoSinScope($empresaId, $ar);
    }
}
