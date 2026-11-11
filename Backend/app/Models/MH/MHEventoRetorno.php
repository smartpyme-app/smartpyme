<?php

namespace App\Models\MH;

use App\Models\MH\Concerns\BuildsTributosVenta;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

/**
 * Evento de Retorno v1 (tipoEvento 18, fe-eret-v1): devolución de bienes sobre una Factura (01) o Factura de exportación (11).
 * Los ítems siguen las reglas del documento original: en factura la venta gravada incluye IVA (ivaItem = gravada × 13/113);
 * en exportación no hay IVA.
 */
class MHEventoRetorno extends Model
{
    use BuildsTributosVenta;

    public $devolucion;
    public $empresa;
    public $dteOriginal;

    public function generarDTE($devolucion)
    {
        $this->devolucion = $devolucion;
        $this->empresa = $this->devolucion->empresa()->first();
        $this->dteOriginal = $this->devolucion->venta->dte;

        $this->devolucion->tipo_dte = '18';
        $this->devolucion->numero_control = NULL;
        if (!$this->devolucion->codigo_generacion) {
            $this->devolucion->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
        }
        $this->devolucion->save();

        $esExportacion = ($this->dteOriginal['identificacion']['tipoDte'] ?? null) === '11';
        $cuerpo = $this->detalles($esExportacion);
        $bases = $this->resumenVentasDesdeCuerpoDocumento($cuerpo);
        $ivaRete = floatval(number_format($this->devolucion->iva_retenido ?? 0, 2, '.', ''));
        $reteRenta = floatval(number_format($this->devolucion->renta_retenida ?? 0, 2, '.', ''));
        $cuerpo = $this->prorratearRetenciones($cuerpo, $ivaRete, $reteRenta);
        $tributos = $this->tributosProporcionales($cuerpo, $bases['subTotalVentas']);
        $montoTotalOperacion = floatval(number_format($bases['subTotalVentas'] + $this->sumaValoresTributosResumen($tributos), 2, '.', ''));
        $totalPagar = floatval(number_format($montoTotalOperacion - $ivaRete - $reteRenta, 2, '.', ''));

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
                "tipoDocumento" => $this->dteOriginal['identificacion']['tipoDte'],
                "codigoGeneracion" => $this->dteOriginal['identificacion']['codigoGeneracion'],
                "fechaEmision" => $this->dteOriginal['identificacion']['fecEmi'],
            ]],
            "emisor" => $this->emisor(),
            "documento" => $this->receptor(),
            "ventaTercero" => NULL,
            "compraTercero" => NULL,
            "cuerpoDocumento" => $cuerpo,
            "resumen" => [
                "totalNoSuj" => $bases['totalNoSuj'],
                "totalExenta" => $bases['totalExenta'],
                "totalGravada" => $bases['totalGravada'],
                "totalCompraExcluidos" => 0,
                "subTotalVentas" => $bases['subTotalVentas'],
                "tributos" => $tributos,
                "totalSeguro" => 0,
                "totalFlete" => 0,
                "montoTotalOperacion" => $montoTotalOperacion,
                "ivaRete" => $ivaRete,
                "reteRenta" => $reteRenta,
                "totalNoGravado" => 0,
                "totalPagar" => $totalPagar,
                "totalLetras" => $this->totalEnLetras($totalPagar),
                "totalNoOnerosas" => 0,
                "totalIva" => floatval(number_format(collect($cuerpo)->sum('ivaItem'), 2, '.', '')),
                "saldoFavor" => 0,
            ],
            "apendice" => NULL,
        ];
    }

    /** El emisor del evento debe coincidir con el del DTE original (establecimiento, punto de venta y datos de aduana). */
    protected function emisor(): array
    {
        $original = $this->dteOriginal['emisor'] ?? [];
        $numeroControl = (string) ($this->dteOriginal['identificacion']['numeroControl'] ?? '');
        $codEstable = substr($numeroControl, 7, 4) ?: NULL;
        $codPuntoVenta = substr($numeroControl, 11, 4) ?: NULL;

        return [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nombre" => $this->empresa->nombre,
            "codEstableMH" => $codEstable,
            "codEstable" => $codEstable,
            "codPuntoVentaMH" => $codPuntoVenta,
            "codPuntoVenta" => $codPuntoVenta,
            "recintoFiscal" => $original['recintoFiscal'] ?? NULL,
            "tipoRegimen" => $original['tipoRegimen'] ?? NULL,
            "regimen" => $original['regimen'] ?? NULL,
            "tipoItemExpor" => $original['tipoItemExpor'] ?? NULL,
        ];
    }

    /** Receptor tal como quedó en el DTE original. */
    protected function receptor(): array
    {
        $r = $this->dteOriginal['receptor'] ?? [];

        return [
            "tipoDocumento" => $r['tipoDocumento'] ?? NULL,
            "numDocumento" => $r['numDocumento'] ?? NULL,
            "nombre" => $r['nombre'] ?? NULL,
            "codPais" => $r['codPais'] ?? NULL,
            "nombrePais" => $r['nombrePais'] ?? NULL,
            "telefono" => $r['telefono'] ?? NULL,
            "correo" => $r['correo'] ?? NULL,
        ];
    }

    protected function detalles(bool $esExportacion)
    {
        $codigoGeneracion = $this->dteOriginal['identificacion']['codigoGeneracion'];
        $factorIva = (!$esExportacion && (float) $this->devolucion->iva > 0) ? 1.13 : 1;
        // Tributos no-IVA (p. ej. turismo) por ítem: los mismos códigos que tenía el ítem en el DTE original.
        $tributosOriginales = collect($this->dteOriginal['cuerpoDocumento'] ?? [])->pluck('tributos', 'descripcion');

        return collect($this->devolucion->detalles)->values()->map(function ($detalle, $index) use ($codigoGeneracion, $factorIva, $esExportacion, $tributosOriginales) {
            $cod = $this->codigoUnidad($detalle->unidad);
            $tipoItem = optional($detalle->producto)->tipo == 'Servicio' ? 2 : 1;
            $precioUni = round(floatval($detalle->precio) * $factorIva, 4);
            $cantidad = round(floatval($detalle->cantidad), 2);
            $montoDescu = round(floatval($detalle->descuento) * $factorIva, 2);
            $ventaItem = round($precioUni * $cantidad - $montoDescu, 2);
            $gravada = $esExportacion || (float) $this->devolucion->iva > 0;

            return [
                "numItem" => $index + 1,
                "tipoItem" => $tipoItem,
                "codigoGeneracion" => $codigoGeneracion,
                "cantidad" => $cantidad,
                "codigo" => $detalle->codigo ?: NULL,
                "uniMedida" => $cod ?: 59,
                "descripcion" => $detalle->nombre_producto,
                "precioUni" => $precioUni,
                "montoDescu" => $montoDescu,
                "codTributo" => NULL,
                "ventaNoSuj" => 0,
                "ventaExenta" => $gravada ? 0 : $ventaItem,
                "ventaGravada" => $gravada ? $ventaItem : 0,
                "compra" => 0,
                "tributos" => $tributosOriginales->get($detalle->nombre_producto) ?: NULL,
                "psv" => 0,
                "ivaItem" => $factorIva > 1 ? round($ventaItem * 13 / 113, 4) : 0,
                "noGravado" => 0,
                "seguro" => 0,
                "flete" => 0,
            ];
        });
    }

    /**
     * resumen.tributos del DTE original escalados por la proporción devuelta (subtotal del retorno / subtotal original),
     * solo para los códigos que llevan los ítems devueltos.
     */
    protected function tributosProporcionales($cuerpo, float $subTotalRetorno)
    {
        $codigos = collect($cuerpo)->pluck('tributos')->filter()->flatten()->unique();
        $subTotalOriginal = (float) ($this->dteOriginal['resumen']['subTotalVentas'] ?? 0);
        if ($codigos->isEmpty() || $subTotalOriginal <= 0) {
            return NULL;
        }
        $proporcion = $subTotalRetorno / $subTotalOriginal;

        $tributos = collect($this->dteOriginal['resumen']['tributos'] ?? [])
            ->filter(fn ($t) => $codigos->contains($t['codigo']))
            ->map(fn ($t) => [
                'codigo' => $t['codigo'],
                'descripcion' => $t['descripcion'],
                'valor' => round((float) $t['valor'] * $proporcion, 2),
            ])
            ->values();

        return $tributos->isEmpty() ? NULL : $tributos;
    }

    protected function codigoUnidad($unidad)
    {
        return Unidad::where('nombre', ucfirst((string) $unidad))->pluck('cod')->first();
    }

    /** ivaRete y reteRenta por ítem según su peso en la venta; el último ítem absorbe el redondeo. */
    protected function prorratearRetenciones($cuerpo, float $ivaRete, float $reteRenta)
    {
        $items = collect($cuerpo)->values();
        $base = (float) $items->sum(fn ($i) => $i['ventaGravada'] + $i['ventaExenta']);
        $ultimo = $items->count() - 1;
        $acumRete = 0.0;
        $acumRenta = 0.0;

        return $items->map(function ($item, $i) use ($base, $ultimo, $ivaRete, $reteRenta, &$acumRete, &$acumRenta) {
            $peso = $base > 0 ? ($item['ventaGravada'] + $item['ventaExenta']) / $base : ($i === 0 ? 1.0 : 0.0);
            $rete = $i === $ultimo ? round($ivaRete - $acumRete, 2) : round($ivaRete * $peso, 2);
            $renta = $i === $ultimo ? round($reteRenta - $acumRenta, 2) : round($reteRenta * $peso, 2);
            $acumRete += $rete;
            $acumRenta += $renta;

            return $item + ['ivaRete' => $rete, 'reteRenta' => $renta];
        });
    }
}
