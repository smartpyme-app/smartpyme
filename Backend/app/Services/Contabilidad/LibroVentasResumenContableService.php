<?php

namespace App\Services\Contabilidad;

use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;

/**
 * Cuadro resumen de ventas agrupado por documento (id_documento) y clasificación fiscal.
 */
final class LibroVentasResumenContableService
{
    public function __construct(
        private FacturacionElectronicaHelperService $feHelper
    ) {}

    /**
     * @return array{filas: array<int, array{
     *   descripcion: string,
     *   valor_neto: float,
     *   debito_fiscal: float,
     *   iva_retenido: float,
     *   total_ventas: float,
     *   tipo: string
     * }>}
     */
    public function build(BaseLibroIVARequest $request): array
    {
        /** @var array<int, array{nombre: string, gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}> */
        $porDocumento = [];

        $ventas = Venta::with(['documento'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $empresa = $this->feHelper->obtenerEmpresa();
        if (optional($empresa)->pais === 'El Salvador') {
            $ventas = $this->feHelper->filtrarVentasPorFacturacionElectronica($ventas);
        }

        foreach ($ventas as $venta) {
            $this->acumularVenta($porDocumento, $venta, 1.0);
        }

        foreach ($this->devolucionesPeriodo($request) as $devolucion) {
            $this->acumularDevolucion($porDocumento, $devolucion);
        }

        return ['filas' => $this->armarFilas($porDocumento)];
    }

    /**
     * @param  array<int, array{nombre: string, gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}>  $porDocumento
     * @return array<int, array<string, mixed>>
     */
    private function armarFilas(array $porDocumento): array
    {
        uasort($porDocumento, fn (array $a, array $b) => strcasecmp($a['nombre'], $b['nombre']));

        $filas = [];
        $detalleTotales = [];

        foreach ($porDocumento as $bucket) {
            if ($this->bucketVacio($bucket)) {
                continue;
            }

            $nombre = strtoupper(trim($bucket['nombre']));
            $filasDoc = [];

            if (abs($bucket['gravadas']) >= 0.00001 || abs($bucket['debito']) >= 0.00001 || abs($bucket['iva_retenido']) >= 0.00001) {
                $fGrav = $this->fila(
                    "{$nombre} — VENTAS GRAVADAS",
                    $bucket['gravadas'],
                    $bucket['debito'],
                    $bucket['iva_retenido'],
                    'detalle'
                );
                $filasDoc[] = $fGrav;
            }

            if (abs($bucket['exentas']) >= 0.00001) {
                $filasDoc[] = $this->fila("{$nombre} — VENTAS EXENTAS", $bucket['exentas'], 0, 0, 'detalle');
            }

            if (abs($bucket['no_sujetas']) >= 0.00001) {
                $filasDoc[] = $this->fila("{$nombre} — VENTAS NO SUJETAS", $bucket['no_sujetas'], 0, 0, 'detalle');
            }

            if ($filasDoc === []) {
                continue;
            }

            foreach ($filasDoc as $f) {
                $filas[] = $f;
            }

            if (count($filasDoc) > 1) {
                $filas[] = $this->filaSubtotal("TOTAL {$nombre}", $filasDoc);
            }

            array_push($detalleTotales, ...$filasDoc);
        }

        if ($detalleTotales !== []) {
            $filas[] = $this->filaSubtotal('TOTALES', $detalleTotales, 'total');
        }

        return $filas;
    }

    /**
     * @param  array<int, array{nombre: string, gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}>  $porDocumento
     */
    private function acumularVenta(array &$porDocumento, Venta $venta, float $signo): void
    {
        $idDoc = (int) ($venta->id_documento ?? 0);
        $nombre = trim((string) (optional($venta->documento)->nombre ?? 'Sin documento'));

        if (! isset($porDocumento[$idDoc])) {
            $porDocumento[$idDoc] = $this->vaciosDocumento($nombre);
        }

        $porDocumento[$idDoc]['gravadas'] += $signo * LibroIvaMontosHelper::ventasGravadas($venta);
        $porDocumento[$idDoc]['exentas'] += $signo * LibroIvaMontosHelper::ventasExentas($venta);
        $porDocumento[$idDoc]['no_sujetas'] += $signo * LibroIvaMontosHelper::ventasNoSujetas($venta);
        $porDocumento[$idDoc]['debito'] += $signo * (float) ($venta->iva ?? 0);
        $porDocumento[$idDoc]['iva_retenido'] += $signo * (float) ($venta->iva_retenido ?? 0);
    }

    /**
     * @param  array<int, array{nombre: string, gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}>  $porDocumento
     */
    private function acumularDevolucion(array &$porDocumento, DevolucionVenta $devolucion): void
    {
        $ventaPadre = $devolucion->venta;
        $idDoc = (int) ($ventaPadre->id_documento ?? 0);
        $nombre = trim((string) (optional($ventaPadre?->documento)->nombre ?? 'Sin documento'));

        if (! isset($porDocumento[$idDoc])) {
            $porDocumento[$idDoc] = $this->vaciosDocumento($nombre);
        }

        $mult = fn (float $v) => $v > 0 ? -$v : $v;

        $porDocumento[$idDoc]['gravadas'] += $mult(LibroIvaMontosHelper::ventasGravadas($devolucion));
        $porDocumento[$idDoc]['exentas'] += $mult(LibroIvaMontosHelper::ventasExentas($devolucion));
        $porDocumento[$idDoc]['no_sujetas'] += $mult(LibroIvaMontosHelper::ventasNoSujetas($devolucion));
        $porDocumento[$idDoc]['debito'] += $mult((float) ($devolucion->iva ?? 0));
        $ivaRet = (float) ($devolucion->iva_retenido ?? 0);
        $porDocumento[$idDoc]['iva_retenido'] += $ivaRet > 0 ? -$ivaRet : $ivaRet;
    }

    /**
     * @return array{nombre: string, gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}
     */
    private function vaciosDocumento(string $nombre): array
    {
        return [
            'nombre' => $nombre !== '' ? $nombre : 'Sin documento',
            'gravadas' => 0.0,
            'exentas' => 0.0,
            'no_sujetas' => 0.0,
            'debito' => 0.0,
            'iva_retenido' => 0.0,
        ];
    }

    /**
     * @param  array{nombre: string, gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}  $bucket
     */
    private function bucketVacio(array $bucket): bool
    {
        return abs($bucket['gravadas']) < 0.00001
            && abs($bucket['exentas']) < 0.00001
            && abs($bucket['no_sujetas']) < 0.00001
            && abs($bucket['debito']) < 0.00001
            && abs($bucket['iva_retenido']) < 0.00001;
    }

    /**
     * @return \Illuminate\Support\Collection<int, DevolucionVenta>
     */
    private function devolucionesPeriodo(BaseLibroIVARequest $request)
    {
        return DevolucionVenta::with(['venta.documento'])
            ->where('enable', true)
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', 'Anulada'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();
    }

    /**
     * @param  array<int, array{valor_neto: float, debito_fiscal: float, iva_retenido: float, total_ventas: float}>  $filas
     * @return array{descripcion: string, valor_neto: float, debito_fiscal: float, iva_retenido: float, total_ventas: float, tipo: string}
     */
    private function filaSubtotal(string $descripcion, array $filas, string $tipo = 'subtotal'): array
    {
        $neto = 0.0;
        $debito = 0.0;
        $retenido = 0.0;
        $total = 0.0;
        foreach ($filas as $f) {
            $neto += (float) ($f['valor_neto'] ?? 0);
            $debito += (float) ($f['debito_fiscal'] ?? 0);
            $retenido += (float) ($f['iva_retenido'] ?? 0);
            $total += (float) ($f['total_ventas'] ?? 0);
        }

        return [
            'descripcion' => $descripcion,
            'valor_neto' => round($neto, 2),
            'debito_fiscal' => round($debito, 2),
            'iva_retenido' => round($retenido, 2),
            'total_ventas' => round($total, 2),
            'tipo' => $tipo,
        ];
    }

    private function fila(
        string $descripcion,
        float $valorNeto,
        float $debitoFiscal,
        float $ivaRetenido,
        string $tipo
    ): array {
        return [
            'descripcion' => $descripcion,
            'valor_neto' => round($valorNeto, 2),
            'debito_fiscal' => round($debitoFiscal, 2),
            'iva_retenido' => round($ivaRetenido, 2),
            'total_ventas' => $this->totalVentasFila($valorNeto, $debitoFiscal, $ivaRetenido),
            'tipo' => $tipo,
        ];
    }

    private function totalVentasFila(float $neto, float $debito, float $retenido): float
    {
        if ($retenido > 0.00001) {
            return round($neto + $debito - $retenido, 2);
        }
        if ($debito > 0.00001) {
            return round($neto + $debito, 2);
        }

        return round($neto, 2);
    }
}
