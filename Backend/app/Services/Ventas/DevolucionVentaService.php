<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Devoluciones\Detalle;
use App\Models\Ventas\Devoluciones\DetalleCompuesto;
use App\Models\Ventas\Venta;
use App\Models\Admin\Documento;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Paquete;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DevolucionVentaService
{
    /**
     * Valida que la diferencia entre notas de crédito y notas de débito
     * no supere el total de la venta
     *
     * @param int $idVenta
     * @param int|null $idDevolucionExcluir ID de devolución a excluir (para actualizaciones)
     * @param int|null $idDocumentoNuevo ID del documento nuevo
     * @param float $totalNuevo Total de la nueva devolución
     * @return void
     * @throws Exception Si la diferencia supera el total de la venta
     */
    public function validarLimitesDevolucion(
        int $idVenta,
        ?int $idDevolucionExcluir,
        ?int $idDocumentoNuevo,
        float $totalNuevo
    ): void {
        $venta = Venta::findOrFail($idVenta);

        $devolucionesActivas = Devolucion::where('id_venta', $idVenta)
            ->where('enable', true)
            ->when($idDevolucionExcluir, function ($query) use ($idDevolucionExcluir) {
                $query->where('id', '!=', $idDevolucionExcluir);
            })
            ->with('documento')
            ->get();

        $totalCreditos = 0;
        $totalDebitos = 0;

        foreach ($devolucionesActivas as $devolucionExistente) {
            $nombreDocumentoExistente = optional($devolucionExistente->documento)->nombre;

            if ($nombreDocumentoExistente == 'Nota de crédito') {
                $totalCreditos += $devolucionExistente->total;
            } elseif ($nombreDocumentoExistente == 'Nota de débito') {
                $totalDebitos += $devolucionExistente->total;
            }
        }

        $documentoNuevo = $idDocumentoNuevo ? Documento::find($idDocumentoNuevo) : null;
        $nombreDocumentoNuevo = optional($documentoNuevo)->nombre;

        if ($nombreDocumentoNuevo == 'Nota de crédito') {
            $totalCreditos += $totalNuevo;
        } elseif ($nombreDocumentoNuevo == 'Nota de débito') {
            $totalDebitos += $totalNuevo;
        }

        $diferencia = abs($totalCreditos - $totalDebitos);
        $totalVenta = $venta->total;

        if ($diferencia > $totalVenta) {
            throw new Exception(
                'No se puede registrar la devolución. La diferencia entre notas de crédito y notas de débito (' .
                number_format($diferencia, 2) .
                ') supera el total de la venta (' . number_format($totalVenta, 2) . ').'
            );
        }
    }

    /**
     * Procesa los detalles de la devolución:
     * - Crea los detalles
     * - Maneja composiciones
     * - Actualiza inventario (condicional)
     * - Maneja inventario de compuestos
     * - Maneja paquetes
     *
     * @param Devolucion $devolucion
     * @param array $detalles
     * @param string $tipo Tipo de devolución
     * @param int $idBodega
     * @return void
     * @throws Exception
     */
    public function procesarDetalles(
        Devolucion $devolucion,
        array $detalles,
        string $tipo,
        int $idBodega
    ): void {
        foreach ($detalles as $det) {
            $detalle = new Detalle;
            $det['id_devolucion_venta'] = $devolucion->id;
            $detalle->fill($det);
            $detalle->save();

            // Si es compuesto
            if (isset($det['composiciones'])) {
                foreach ($det['composiciones'] as $item) {
                    $cd = new DetalleCompuesto;
                    $cd->id_producto = $item['id_producto'];
                    $cd->cantidad = $item['cantidad'];
                    $cd->id_detalle = $detalle->id;
                    $cd->save();
                }
            }

            // Solo afectar inventario si el tipo de nota de crédito lo requiere
            if ($tipo !== 'descuento_ajuste') {
                $inventario = Inventario::where('id_producto', $det['id_producto'])
                    ->where('id_bodega', $idBodega)
                    ->first();

                if ($inventario) {
                    $inventario->stock += $det['cantidad'];
                    $inventario->save();
                    $inventario->kardex($devolucion, $det['cantidad']);
                }

                // Inventario compuestos
                if (isset($det['composiciones'])) {
                    foreach ($det['composiciones'] as $comp) {
                        $inventario = Inventario::where('id_producto', $comp['id_producto'])
                            ->where('id_bodega', $devolucion->id_bodega)
                            ->first();

                        if ($inventario) {
                            $inventario->stock += $det['cantidad'] * $comp['cantidad'];
                            $inventario->save();
                            $inventario->kardex($devolucion, ($det['cantidad'] * $comp['cantidad']));
                        }
                    }
                }
            }

            // Si es paquete cambiar estado
            $paquetes = Paquete::where('id_venta', $devolucion->id_venta)->get();
            foreach ($paquetes as $paquete) {
                $paquete->estado = 'En bodega';
                $paquete->id_venta = null;
                $paquete->id_venta_detalle = null;
                $paquete->save();
            }
        }
    }

    /**
     * Incrementa el correlativo del documento asociado a la devolución
     *
     * @param Devolucion $devolucion
     * @return void
     */
    public function incrementarCorrelativo(Devolucion $devolucion): void
    {
        if ($devolucion->id_documento) {
            Documento::where('id', $devolucion->id_documento)->increment('correlativo');
        }
    }
}

