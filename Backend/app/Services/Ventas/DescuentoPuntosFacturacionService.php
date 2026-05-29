<?php

namespace App\Services\Ventas;

use App\Models\Admin\Empresa;
use Illuminate\Http\Request;

/**
 * Aplica descuento por puntos sobre la base gravada (sin IVA) y recalcula IVA / totales.
 * Idempotente si el IVA ya corresponde a la base neta. No modifica el DTE directamente.
NO SE OCUPARA AUN PERO YA QUEDA LA LOGICA PARA EL FUTURO
 */

class DescuentoPuntosFacturacionService
{
    public function aplicar(Request $request, Empresa $empresa): void
    {
        $ctx = $this->resolverContexto($request, $empresa);
        if ($ctx === null) {
            return;
        }

        $descApp = $this->calcularMontoDescuentoAAplicar(
            $ctx['G'],
            $ctx['dp'],
            $ctx['ivaReq'],
            $ctx['pct']
        );

        if ($descApp <= 0.00001) {
            $this->registrarSubTotalBrutoIdempotente($request, $ctx['dp'], $ctx['G']);

            return;
        }

        $reparto = $this->calcularRepartoPorGravada(
            $ctx['gravadaPorLinea'],
            count($ctx['detalles']),
            $descApp
        );

        if ($reparto === null) {
            return;
        }

        $sumLineTotalsBruto = $this->sumarCampoEnDetalles($ctx['detalles'], 'total', 2);
        $sumIvaAntes = $this->sumarCampoEnDetallesSinRedondear($ctx['detalles'], 'iva');

        $out = $this->aplicarRepartoEnDetalles(
            $ctx['detalles'],
            $reparto,
            $empresa
        );

        $totales = $this->agregarTotalesDesdeDetalles($out);

        $this->escalarMontosImpuestos($request, $sumIvaAntes, $totales['iva']);

        $total = $this->calcularTotalCabecera($request, $totales);

        $request->merge([
            'detalles' => array_values($out),
            'descuento' => $totales['descuento'],
            'sub_total' => $totales['sub_total_lineas'],
            'sub_total_bruto' => $sumLineTotalsBruto,
            'gravada' => $totales['gravada'],
            'exenta' => $totales['exenta'],
            'no_sujeta' => $totales['no_sujeta'],
            'iva' => $totales['iva'],
            'total' => $total,
        ]);
    }

    /**
     * @return array{detalles: array, gravadaPorLinea: array<int, float>, G: float, dp: float, ivaReq: float, pct: float}|null
     */
    private function resolverContexto(Request $request, Empresa $empresa): ?array
    {
        $dp = (float) ($request->input('descuento_puntos', 0) ?? 0);
        if ($dp <= 0.00001 || ! $empresa->tieneFidelizacionHabilitada()) {
            return null;
        }

        $ivaReq = (float) ($request->input('iva', 0) ?? 0);
        if ($ivaReq <= 0) {
            return null;
        }

        $detalles = $request->input('detalles', []);
        if (! is_array($detalles) || count($detalles) === 0) {
            return null;
        }

        $gravadaPorLinea = [];
        foreach ($detalles as $idx => $d) {
            if (! is_array($d)) {
                continue;
            }
            $gravadaPorLinea[$idx] = max(0.0, (float) ($d['gravada'] ?? 0));
        }

        $G = round(array_sum($gravadaPorLinea), 4);
        if ($G <= 0.00001) {
            return null;
        }

        $pct = max(0.0, (float) ($empresa->iva ?? 13)) / 100.0;
        if ($pct <= 0.00001) {
            return null;
        }

        return [
            'detalles' => $detalles,
            'gravadaPorLinea' => $gravadaPorLinea,
            'G' => $G,
            'dp' => $dp,
            'ivaReq' => $ivaReq,
            'pct' => $pct,
        ];
    }

    private function calcularMontoDescuentoAAplicar(float $G, float $dp, float $ivaReq, float $pct): float
    {
        $inferBaseDesdeIva = round($ivaReq / $pct, 2);
        $candidatoDesdeIva = max(0.0, round($G - $inferBaseDesdeIva, 2));
        $ivaSobreG = round($G * $pct, 2);

        if (abs($ivaReq - $ivaSobreG) < 0.05 && $dp > 0.00001 && $candidatoDesdeIva < 0.02) {
            return round(min($dp, $G), 2);
        }

        return round(min($dp, $candidatoDesdeIva), 2);
    }

    private function registrarSubTotalBrutoIdempotente(Request $request, float $dp, float $G): void
    {
        $descMaxInfo = round(min($dp, $G), 2);
        if ($descMaxInfo <= 0.00001) {
            return;
        }

        $stReq = round((float) ($request->input('sub_total', 0) ?? 0), 2);
        $request->merge(['sub_total_bruto' => round($stReq + $descMaxInfo, 2)]);
    }

    /**
     * @param  array<int, float>  $gravadaPorLinea
     * @return array<int, float>|null
     */
    private function calcularRepartoPorGravada(array $gravadaPorLinea, int $numDetalles, float $descApp): ?array
    {
        $indices = [];
        $pesos = [];
        foreach ($gravadaPorLinea as $idx => $g) {
            if ($g > 0) {
                $indices[] = $idx;
                $pesos[] = $g;
            }
        }

        $pesoTotal = array_sum($pesos);
        if ($pesoTotal <= 0.00001) {
            return null;
        }

        $reparto = array_fill(0, $numDetalles, 0.0);
        $asignado = 0.0;
        $last = count($indices) - 1;

        foreach ($indices as $i => $idx) {
            if ($i === $last) {
                $reparto[$idx] = round($descApp - $asignado, 2);
            } else {
                $p = round($descApp * ($pesos[$i] / $pesoTotal), 2);
                $reparto[$idx] = $p;
                $asignado += $p;
            }
        }

        return $reparto;
    }

    private function sumarCampoEnDetalles(array $detalles, string $campo, int $decimales): float
    {
        return round($this->sumarCampoEnDetallesSinRedondear($detalles, $campo), $decimales);
    }

    private function sumarCampoEnDetallesSinRedondear(array $detalles, string $campo): float
    {
        return array_sum(array_map(
            static fn ($x) => is_array($x) ? (float) ($x[$campo] ?? 0) : 0.0,
            $detalles
        ));
    }

    /**
     * @param  array<int, float>  $reparto
     */
    private function aplicarRepartoEnDetalles(array $detalles, array $reparto, Empresa $empresa): array
    {
        $out = [];

        foreach ($detalles as $idx => $d) {
            if (! is_array($d)) {
                $out[] = $d;
                continue;
            }

            $parte = (float) ($reparto[$idx] ?? 0);
            $gravadaAntes = (float) ($d['gravada'] ?? 0);
            $descAntes = (float) ($d['descuento'] ?? 0);
            $subTotalL = (float) ($d['sub_total'] ?? 0);
            $pctLinea = (float) ($d['porcentaje_impuesto'] ?? 0);
            if ($pctLinea <= 0.00001) {
                $pctLinea = (float) ($empresa->iva ?? 13);
            }
            $pctLineaFrac = $pctLinea / 100.0;

            if ($parte > 0.00001 && $gravadaAntes > 0.00001) {
                $d['descuento'] = round($descAntes + $parte, 4);
                $d['gravada'] = round(max(0.0, $gravadaAntes - $parte), 4);
                $d['iva'] = round($d['gravada'] * $pctLineaFrac, 4);
                $d['total'] = round($subTotalL - (float) $d['descuento'], 4);
            }

            $out[] = $d;
        }

        return $out;
    }

    /**
     * @return array{descuento: float, gravada: float, exenta: float, no_sujeta: float, sub_total_lineas: float, iva: float}
     */
    private function agregarTotalesDesdeDetalles(array $out): array
    {
        return [
            'descuento' => round(array_sum(array_map(
                static fn ($x) => is_array($x) ? (float) ($x['descuento'] ?? 0) : 0.0,
                $out
            )), 2),
            'gravada' => round(array_sum(array_map(
                static fn ($x) => is_array($x) ? (float) ($x['gravada'] ?? 0) : 0.0,
                $out
            )), 2),
            'exenta' => round(array_sum(array_map(
                static fn ($x) => is_array($x) ? (float) ($x['exenta'] ?? 0) : 0.0,
                $out
            )), 2),
            'no_sujeta' => round(array_sum(array_map(
                static fn ($x) => is_array($x) ? (float) ($x['no_sujeta'] ?? 0) : 0.0,
                $out
            )), 2),
            'sub_total_lineas' => round(array_sum(array_map(
                static fn ($x) => is_array($x) ? (float) ($x['total'] ?? 0) : 0.0,
                $out
            )), 2),
            'iva' => round(array_sum(array_map(
                static fn ($x) => is_array($x) ? (float) ($x['iva'] ?? 0) : 0.0,
                $out
            )), 4),
        ];
    }

    private function escalarMontosImpuestos(Request $request, float $sumIvaAntes, float $sumIvaDespues): void
    {
        $impuestos = $request->input('impuestos');
        if (! is_array($impuestos) || count($impuestos) === 0 || $sumIvaAntes <= 0.00001) {
            return;
        }

        $fIva = $sumIvaDespues / $sumIvaAntes;
        $impuestosAjustados = [];

        foreach ($impuestos as $imp) {
            if (! is_array($imp)) {
                $impuestosAjustados[] = $imp;
                continue;
            }
            $row = $imp;
            $row['monto'] = round((float) ($imp['monto'] ?? 0) * $fIva, 4);
            $impuestosAjustados[] = $row;
        }

        $request->merge(['impuestos' => $impuestosAjustados]);
    }

    /**
     * @param  array{sub_total_lineas: float, iva: float}  $totales
     */
    private function calcularTotalCabecera(Request $request, array $totales): float
    {
        $cuentaTerceros = (float) ($request->input('cuenta_a_terceros', 0) ?? 0);
        $ivaPerc = (float) ($request->input('iva_percibido', 0) ?? 0);
        $ivaRet = (float) ($request->input('iva_retenido', 0) ?? 0);
        $rentaRet = (float) ($request->input('renta_retenida', 0) ?? 0);

        return round(
            $totales['sub_total_lineas'] + $totales['iva'] + $cuentaTerceros + $ivaPerc - $ivaRet - $rentaRet,
            2
        );
    }
}
