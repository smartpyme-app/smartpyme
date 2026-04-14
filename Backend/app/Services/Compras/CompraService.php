<?php

namespace App\Services\Compras;

use App\Models\Compras\Compra;
use App\Models\Compras\Detalle;
use App\Models\Admin\Documento;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CompraService
{
    protected $transaccionesService;
    protected $chequesService;

    public function __construct(
        TransaccionesService $transaccionesService,
        ChequesService $chequesService
    ) {
        $this->transaccionesService = $transaccionesService;
        $this->chequesService = $chequesService;
    }
    /**
     * Calcula el total de una compra desde los datos del request
     *
     * @param array|\Illuminate\Http\Request $data Datos de la compra
     * @return float Total calculado
     */
    public function calcularTotal($data): float
    {
        $total = $data->total ?? $data->sub_total ?? 0;

        // Si no hay total, calcularlo de los detalles
        if ($total == 0 && isset($data->detalles)) {
            $total = collect($data->detalles)->sum('total');
        }

        return (float) $total;
    }

    /**
     * Crear o actualizar una compra
     *
     * @param array $data Datos de la compra
     * @return Compra
     */
    public function crearOActualizarCompra(array $data): Compra
    {
        if (isset($data['id'])) {
            $compra = Compra::findOrFail($data['id']);
        } else {
            $compra = new Compra();
        }

        // Merge con id_sucursal del usuario autenticado
        $data = array_merge($data, ['id_sucursal' => Auth::user()->id_sucursal]);

        $compra->fill($data);
        $compra->save();

        return $compra;
    }

    /**
     * Incrementar el correlativo del documento según el tipo
     *
     * @param Compra $compra
     * @param string $tipoDocumento Tipo de documento (ej: 'Orden de compra', 'Sujeto excluido')
     * @return void
     */
    public function incrementarCorrelativo(Compra $compra, string $tipoDocumento): void
    {
        // Solo incrementar para tipos específicos
        if (! in_array($tipoDocumento, ['Orden de compra', 'Sujeto excluido', 'Compra electrónica'], true)) {
            return;
        }

        $documento = Documento::where('nombre', $tipoDocumento)
            ->where('id_sucursal', $compra->id_sucursal)
            ->first();

        if ($documento) {
            $documento->increment('correlativo');
            Log::info("Correlativo incrementado para documento: {$tipoDocumento}", [
                'documento_id' => $documento->id,
                'nuevo_correlativo' => $documento->correlativo
            ]);
        }
    }

    /**
     * Procesar detalles de compra con actualización de inventario y costo promedio
     *
     * @param Compra $compra Compra a la que pertenecen los detalles
     * @param array $detalles Array de detalles a procesar
     * @param bool $esNueva Indica si es una compra nueva
     * @param bool $esCotizacion Indica si es una cotización (no actualiza inventario)
     * @return void
     */
    public function procesarDetallesConInventario(Compra $compra, array $detalles, bool $esNueva, bool $esCotizacion = false): void
    {
        foreach ($detalles as $det) {
            // Crear o actualizar detalle
            if (isset($det['id'])) {
                $detalle = Detalle::findOrFail($det['id']);
            } else {
                $detalle = new Detalle();
            }

            $det['id_compra'] = $compra->id;
            $detalle->fill($det);

            // Actualizar inventario solo si no es cotización
            if (!$esCotizacion) {
                // Actualizar inventario solo si existe (si no existe, el producto no lleva inventario)
                $inventario = Inventario::where('id_producto', $det['id_producto'])
                    ->where('id_bodega', $compra->id_bodega)
                    ->lockForUpdate() // Bloquear fila para evitar condiciones de carrera
                    ->first();

                if ($inventario) {
                    // Actualizar stock de forma atómica
                    $inventario->stock += $det['cantidad'];
                    $inventario->save();

                    // Registrar kardex
                    // Si falla el kardex, la transacción hará rollback automáticamente
                    $inventario->kardex($compra, $det['cantidad']);
                }
            }

            $detalle->save();

            // Calcular costo promedio solo para compras nuevas
            if ($esNueva) {
                $producto = $detalle->producto()->with('inventarios')->first();
                if ($producto) {
                    $stock_anterior = ($producto->inventarios->sum('stock') ?? 0) - $det['cantidad'];
                    $stock_actual = $det['cantidad']; // Cantidad comprada
                    $stock_total = $stock_anterior + $stock_actual; // Nuevo stock total

                    // Evitar división por cero
                    if ($stock_total > 0) {
                        $costo_promedio = (($stock_anterior * $producto->costo) + ($stock_actual * $det['costo'])) / $stock_total;
                    } else {
                        $costo_promedio = $det['costo'];
                    }

                    $producto->costo_anterior = $producto->costo;
                    $producto->costo = $det['costo'];
                    $producto->costo_promedio = $costo_promedio;
                    $producto->save();
                }
            }
        }
    }

    /**
     * Procesar pagos de la compra (transacciones bancarias y cheques)
     *
     * @param Compra $compra Compra a procesar
     * @param bool $esNueva Indica si es una compra nueva
     * @param bool $esCotizacion Indica si es una cotización (no crea pagos)
     * @return void
     */
    public function procesarPagos(Compra $compra, bool $esNueva, bool $esCotizacion = false): void
    {
        // Solo procesar pagos para compras nuevas que no sean cotizaciones
        if (!$esNueva || $esCotizacion) {
            return;
        }

        // Crear transacción bancaria si no es efectivo ni cheque
        if ($compra->forma_pago != 'Efectivo' && $compra->forma_pago != 'Cheque') {
            $concepto = 'Compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : '');
            $this->transaccionesService->crear($compra, 'Cargo', $concepto, 'Compra');
        }

        // Crear cheque si la forma de pago es Cheque
        if ($compra->forma_pago == 'Cheque') {
            $concepto = 'Compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : '');
            $this->chequesService->crear($compra, $compra->nombre_proveedor, $concepto, 'Compra');
        }
    }
}

