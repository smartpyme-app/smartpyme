<?php

namespace App\Services\Contadores;

use App\Models\Compras\Abono as AbonoCompra;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Abono as AbonoGasto;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Ventas\Abono as AbonoVenta;
use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ContadorCarteraMetricasService
{
    private const TASA_PAGO_CUENTA_ISR_SV = 0.0175;

    /**
     * @param  list<int>  $idsEmpresa
     * @return array<int, array<string, mixed>>
     */
    public function metricasPorEmpresas(array $idsEmpresa, int $anio, int $mes): array
    {
        $idsEmpresa = array_values(array_unique(array_map('intval', $idsEmpresa)));
        $out = [];
        foreach ($idsEmpresa as $idEmpresa) {
            $out[$idEmpresa] = $this->metricasEmpresa($idEmpresa, $anio, $mes);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function detalleEmpresa(int $idEmpresa, int $anio, int $mes): array
    {
        [$desde, $hasta] = $this->rangoMes($anio, $mes);
        $desglose = $this->desglosePorTipo($idEmpresa, $desde, $hasta);
        $impuestos = $this->impuestosPeriodo($idEmpresa, $desde, $hasta);

        return [
            'desglose' => $desglose,
            'impuestos' => $impuestos,
            'totales' => $this->totalesDesdeDesglose($desglose),
        ];
    }

    /**
     * @return array{registradas: int, con_partida: int, por_contabilizar: int, avance: int, estado: string, iva_pagar: float}
     */
    public function metricasEmpresa(int $idEmpresa, int $anio, int $mes): array
    {
        [$desde, $hasta] = $this->rangoMes($anio, $mes);
        $desglose = $this->desglosePorTipo($idEmpresa, $desde, $hasta);
        $totales = $this->totalesDesdeDesglose($desglose);
        $registradas = (int) $totales['registradas'];
        $conPartida = (int) $totales['con_partida'];
        $avance = $registradas > 0 ? (int) round(($conPartida / $registradas) * 100) : 100;
        $impuestos = $this->impuestosPeriodo($idEmpresa, $desde, $hasta);

        return [
            'registradas' => $registradas,
            'con_partida' => $conPartida,
            'por_contabilizar' => max(0, $registradas - $conPartida),
            'avance' => $avance,
            'estado' => self::estadoDesdeAvance($avance),
            'iva_pagar' => (float) ($impuestos['f07']['iva_a_pagar'] ?? 0),
        ];
    }

    public static function estadoDesdeAvance(int $avance): string
    {
        if ($avance >= 95) {
            return 'lista';
        }
        if ($avance >= 85) {
            return 'casi';
        }
        if ($avance < 45) {
            return 'atrasada';
        }

        return 'proceso';
    }

    /** @return array{0: string, 1: string} */
    private function rangoMes(int $anio, int $mes): array
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = Carbon::create($anio, $mes, 1)->endOfMonth()->endOfDay();

        return [$inicio->toDateString(), $fin->toDateString()];
    }

    /**
     * @return array{
     *   ventas: array{registradas: int, por_correo: int, con_partida: int},
     *   compras: array{registradas: int, por_correo: int, con_partida: int},
     *   gastos: array{registradas: int, por_correo: int, con_partida: int}
     * }
     */
    private function desglosePorTipo(int $idEmpresa, string $desde, string $hasta): array
    {
        $ventaIds = $this->idsVentasContables($idEmpresa, $desde, $hasta);
        $abonoVentaIds = $this->idsAbonos($idEmpresa, $desde, $hasta, AbonoVenta::class);
        $compraIds = $this->idsComprasContables($idEmpresa, $desde, $hasta);
        $abonoCompraIds = $this->idsAbonos($idEmpresa, $desde, $hasta, AbonoCompra::class);
        $gastoIds = $this->idsGastosContables($idEmpresa, $desde, $hasta);
        $abonoGastoIds = $this->idsAbonos($idEmpresa, $desde, $hasta, AbonoGasto::class);

        $ventasPorCorreo = $this->contarPorCorreoVentas($idEmpresa, $desde, $hasta);
        $comprasPorCorreo = $this->contarPorCorreoCompras($idEmpresa, $desde, $hasta);
        $gastosPorCorreo = $this->contarPorCorreoGastos($idEmpresa, $desde, $hasta);

        return [
            'ventas' => $this->filaDesglose(
                $idEmpresa,
                [
                    ['Venta', $ventaIds],
                    ['Abono de Venta', $abonoVentaIds],
                ],
                $ventasPorCorreo
            ),
            'compras' => $this->filaDesglose(
                $idEmpresa,
                [
                    ['Compra', $compraIds],
                    ['Abono de Compra', $abonoCompraIds],
                ],
                $comprasPorCorreo
            ),
            'gastos' => $this->filaDesglose(
                $idEmpresa,
                [
                    ['Gasto', $gastoIds],
                    ['Abono de Gasto', $abonoGastoIds],
                ],
                $gastosPorCorreo
            ),
        ];
    }

    /**
     * @param  list<array{0: string, 1: list<int>}>  $lotes
     * @return array{registradas: int, por_correo: int, con_partida: int}
     */
    private function filaDesglose(int $idEmpresa, array $lotes, int $porCorreo): array
    {
        $registradas = 0;
        $conPartida = 0;
        foreach ($lotes as [$referencia, $ids]) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $registradas += count($ids);
            if ($ids !== []) {
                $conPartida += count($this->idsConPartida($idEmpresa, $referencia, $ids));
            }
        }

        return [
            'registradas' => $registradas,
            'por_correo' => min($porCorreo, $registradas),
            'con_partida' => $conPartida,
        ];
    }

    /**
     * @param  array{ventas: array, compras: array, gastos: array}  $desglose
     * @return array{registradas: int, con_partida: int, por_correo: int}
     */
    private function totalesDesdeDesglose(array $desglose): array
    {
        $registradas = 0;
        $conPartida = 0;
        $porCorreo = 0;
        foreach (['ventas', 'compras', 'gastos'] as $tipo) {
            $registradas += (int) ($desglose[$tipo]['registradas'] ?? 0);
            $conPartida += (int) ($desglose[$tipo]['con_partida'] ?? 0);
            $porCorreo += (int) ($desglose[$tipo]['por_correo'] ?? 0);
        }

        return [
            'registradas' => $registradas,
            'con_partida' => $conPartida,
            'por_correo' => $porCorreo,
        ];
    }

    /**
     * ponytail: “por correo” = DTE/import (codigo_generacion o id_authorization en compras).
     *
     * @return array{f07: array, f14: array}
     */
    private function impuestosPeriodo(int $idEmpresa, string $desde, string $hasta): array
    {
        $ventasQ = Venta::withoutGlobalScopes()
            ->contabilizable()
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$desde, $hasta]);

        $debitoFiscal = (float) (clone $ventasQ)->sum('iva');
        $ivaRetVentas = (float) (clone $ventasQ)->sum('iva_retenido');
        $ivaPercVentas = (float) (clone $ventasQ)->sum('iva_percibido');
        $rentaVentas = (float) (clone $ventasQ)->sum('renta_retenida');
        $ingresosBrutos = (float) (clone $ventasQ)->get()->sum(
            fn (Venta $v) => (float) ($v->gravada ?? 0)
                + (float) ($v->exenta ?? 0)
                + (float) ($v->no_sujeta ?? 0)
                + (float) ($v->cuenta_a_terceros ?? 0)
        );

        $comprasQ = Compra::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('estado', ['Pagada', 'Pendiente'])
            ->whereBetween('fecha', [$desde, $hasta]);

        $gastosQ = Gasto::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('estado', ['Confirmado', 'Pagado', 'Pendiente'])
            ->whereBetween('fecha', [$desde, $hasta]);

        $creditoCompras = (float) (clone $comprasQ)->sum('iva');
        $creditoGastos = (float) (clone $gastosQ)->sum('iva');
        $creditoFiscal = $creditoCompras + $creditoGastos;

        $ivaRetCompras = (float) (clone $comprasQ)->sum('iva_retenido');
        $ivaRetGastos = (float) (clone $gastosQ)->sum('iva_retenido');
        $rentaCompras = (float) (clone $comprasQ)->sum('renta_retenida');
        $rentaGastos = (float) (clone $gastosQ)->sum('renta_retenida');

        $retenciones = round($ivaRetVentas + $ivaPercVentas + $ivaRetCompras + $ivaRetGastos, 2);
        $diferencia = round($debitoFiscal - $creditoFiscal, 2);
        $ivaAPagar = round($diferencia - $retenciones, 2);

        $pagoCuenta = round($ingresosBrutos * self::TASA_PAGO_CUENTA_ISR_SV, 2);
        $rentaRetenida = round($rentaVentas + $rentaCompras + $rentaGastos, 2);
        $totalF14 = round($pagoCuenta + $rentaRetenida, 2);

        return [
            'f07' => [
                'debito_fiscal' => round($debitoFiscal, 2),
                'credito_fiscal' => round(-$creditoFiscal, 2),
                'retenciones' => round(-$retenciones, 2),
                'iva_a_pagar' => $ivaAPagar,
            ],
            'f14' => [
                'pago_cuenta_isr' => $pagoCuenta,
                'renta_retenida' => $rentaRetenida,
                'total' => $totalF14,
            ],
        ];
    }

    private function contarPorCorreoVentas(int $idEmpresa, string $desde, string $hasta): int
    {
        return (int) Venta::withoutGlobalScopes()
            ->contabilizable()
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function (Builder $q) {
                $this->scopeDocumentoPorCorreo($q);
            })
            ->count();
    }

    private function contarPorCorreoCompras(int $idEmpresa, string $desde, string $hasta): int
    {
        return (int) Compra::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('estado', ['Pagada', 'Pendiente'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function (Builder $q) {
                $this->scopeDocumentoPorCorreo($q, true);
            })
            ->count();
    }

    private function contarPorCorreoGastos(int $idEmpresa, string $desde, string $hasta): int
    {
        return (int) Gasto::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('estado', ['Confirmado', 'Pagado', 'Pendiente'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function (Builder $q) {
                $this->scopeDocumentoPorCorreo($q);
            })
            ->count();
    }

    private function scopeDocumentoPorCorreo(Builder $q, bool $incluirAuthorization = false): void
    {
        $q->where(function (Builder $inner) use ($incluirAuthorization) {
            $inner->whereNotNull('codigo_generacion')->where('codigo_generacion', '!=', '');
            if ($incluirAuthorization) {
                $inner->orWhereNotNull('id_authorization');
            }
        });
    }

    /** @return list<int> */
    private function idsVentasContables(int $idEmpresa, string $desde, string $hasta): array
    {
        return Venta::withoutGlobalScopes()
            ->contabilizable()
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$desde, $hasta])
            ->pluck('id')
            ->all();
    }

    /** @return list<int> */
    private function idsComprasContables(int $idEmpresa, string $desde, string $hasta): array
    {
        return Compra::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('estado', ['Pagada', 'Pendiente'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->pluck('id')
            ->all();
    }

    /** @return list<int> */
    private function idsGastosContables(int $idEmpresa, string $desde, string $hasta): array
    {
        return Gasto::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('estado', ['Confirmado', 'Pagado', 'Pendiente'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->pluck('id')
            ->all();
    }

    /**
     * @param  class-string  $modelClass
     * @return list<int>
     */
    private function idsAbonos(int $idEmpresa, string $desde, string $hasta, string $modelClass): array
    {
        return $modelClass::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('estado', 'Confirmado')
            ->whereBetween('fecha', [$desde, $hasta])
            ->pluck('id')
            ->all();
    }

    /** @param  list<int>  $ids */
    private function idsConPartida(int $idEmpresa, string $referencia, array $ids): array
    {
        return Partida::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('referencia', $referencia)
            ->whereIn('id_referencia', $ids)
            ->where('estado', '!=', 'Anulada')
            ->pluck('id_referencia')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
