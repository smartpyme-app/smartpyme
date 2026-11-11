<?php

namespace App\Models\MH;

use Ramsey\Uuid\Uuid;

/**
 * Evento de Retorno v1 (tipo 18) sobre Factura de sujeto excluido (14) emitida en una compra: disminución de la compra.
 * Los ítems usan el campo "compra" (igual que la FSE original) y la renta retenida se reparte en proporción a lo devuelto.
 */
class MHEventoRetornoCompra extends MHEventoRetorno
{
    public function generarDTE($devolucion)
    {
        $this->devolucion = $devolucion;
        $this->empresa = $this->devolucion->empresa()->first();
        $this->dteOriginal = $this->documentoOriginal();

        $this->devolucion->tipo_dte = '18';
        if (!$this->devolucion->codigo_generacion) {
            $this->devolucion->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
        }
        $this->devolucion->save();

        $cuerpo = $this->detallesCompra();
        $totalCompra = floatval(number_format(collect($cuerpo)->sum('compra'), 2, '.', ''));
        $totalCompraOriginal = (float) ($this->dteOriginal['resumen']['totalCompra'] ?? 0);
        $proporcion = $totalCompraOriginal > 0 ? $totalCompra / $totalCompraOriginal : 0;
        $reteRenta = round((float) ($this->dteOriginal['resumen']['reteRenta'] ?? 0) * $proporcion, 2);
        $cuerpo = $this->prorratearRetenciones($cuerpo, 0, $reteRenta);
        $totalPagar = floatval(number_format($totalCompra - $reteRenta, 2, '.', ''));

        return [
            "identificacion" => [
                "version" => 1,
                "ambiente" => $this->empresa->fe_ambiente,
                "tipoModelo" => 1,
                "tipoOperacion" => 1,
                "tipoEvento" => "18",
                "tipoContingencia" => NULL,
                "motivoContin" => NULL,
                "codigoGeneracion" => $this->devolucion->codigo_generacion,
                "fecEmi" => \Carbon\Carbon::parse($this->devolucion->fecha)->format('Y-m-d'),
                "horEmi" => \Carbon\Carbon::parse($this->devolucion->created_at)->format('H:i:s'),
                "fusion" => NULL,
                "tipoMoneda" => "USD",
            ],
            "documentoRelacionado" => [[
                "tipoDocumento" => "14",
                "codigoGeneracion" => $this->dteOriginal['identificacion']['codigoGeneracion'],
                "fechaEmision" => $this->dteOriginal['identificacion']['fecEmi'],
            ]],
            "emisor" => $this->emisor(),
            "documento" => $this->receptor(),
            "ventaTercero" => NULL,
            "compraTercero" => NULL,
            "cuerpoDocumento" => $cuerpo,
            "resumen" => [
                "totalNoSuj" => 0,
                "totalExenta" => 0,
                "totalGravada" => 0,
                "totalCompraExcluidos" => $totalCompra,
                "subTotalVentas" => 0,
                "tributos" => NULL,
                "totalSeguro" => 0,
                "totalFlete" => 0,
                "montoTotalOperacion" => $totalCompra,
                "ivaRete" => 0,
                "reteRenta" => $reteRenta,
                "totalNoGravado" => 0,
                "totalPagar" => $totalPagar,
                "totalLetras" => $this->totalEnLetras($totalPagar),
                "totalNoOnerosas" => 0,
                "totalIva" => 0,
                "saldoFavor" => 0,
            ],
            "apendice" => NULL,
        ];
    }

    protected function documentoOriginal(): array
    {
        return $this->devolucion->compra->dte;
    }

    protected function detallesCompra()
    {
        $codigoGeneracion = $this->dteOriginal['identificacion']['codigoGeneracion'];
        $factorIva = (float) ($this->devolucion->compra->iva ?? 0) > 0 ? 1.13 : 1;

        return collect($this->devolucion->detalles)->values()->map(function ($detalle, $index) use ($codigoGeneracion, $factorIva) {
            $precioUni = round(floatval($detalle->costo) * $factorIva, 4);
            $cantidad = round(floatval($detalle->cantidad), 2);
            $montoDescu = round(floatval($detalle->descuento ?? 0), 2);

            return [
                "numItem" => $index + 1,
                "tipoItem" => optional($detalle->producto)->tipo == 'Servicio' ? 2 : 1,
                "codigoGeneracion" => $codigoGeneracion,
                "cantidad" => $cantidad,
                "codigo" => NULL,
                "uniMedida" => 59,
                "descripcion" => $detalle->nombre_producto,
                "precioUni" => $precioUni,
                "montoDescu" => $montoDescu,
                "codTributo" => NULL,
                "ventaNoSuj" => 0,
                "ventaExenta" => 0,
                "ventaGravada" => 0,
                "compra" => round($precioUni * $cantidad - $montoDescu, 2),
                "tributos" => NULL,
                "psv" => 0,
                "ivaItem" => 0,
                "noGravado" => 0,
                "seguro" => 0,
                "flete" => 0,
            ];
        });
    }
}
