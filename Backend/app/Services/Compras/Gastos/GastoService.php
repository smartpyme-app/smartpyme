<?php

namespace App\Services\Compras\Gastos;

use App\Models\Admin\Documento;
use App\Models\Compras\Gastos\Gasto;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use Illuminate\Support\Facades\Log;

class GastoService
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
     * Crea o actualiza un gasto
     *
     * @param array $data Datos del gasto
     * @return Gasto
     */
    public function crearOActualizarGasto(array $data): Gasto
    {
        if (isset($data['id'])) {
            $gasto = Gasto::findOrFail($data['id']);
        } else {
            $gasto = new Gasto;
        }

        // Limpiar otros_impuestos si está vacío
        if (isset($data['otros_impuestos']) && empty($data['otros_impuestos'])) {
            $data['otros_impuestos'] = null;
        }

        $gasto->fill($data);
        $gasto->save();

        return $gasto;
    }

    /**
     * Procesa los pagos asociados a un gasto (transacciones bancarias y cheques)
     *
     * @param Gasto $gasto
     * @param bool $esNuevo Si el gasto es nuevo o se está actualizando
     * @return void
     */
    public function procesarPagos(Gasto $gasto, bool $esNuevo): void
    {
        // Solo procesar pagos para gastos nuevos
        if (!$esNuevo) {
            return;
        }

        // Crear transacción bancaria si no es efectivo ni cheque
        if ($gasto->forma_pago != 'Efectivo' && $gasto->forma_pago != 'Cheque') {
            $concepto = 'Gasto: ' . $gasto->tipo_documento . ' #' . ($gasto->referencia ? $gasto->referencia : '');
            $this->transaccionesService->crear($gasto, 'Cargo', $concepto, 'Gasto');
        }

        // Crear cheque si la forma de pago es Cheque
        if ($gasto->forma_pago == 'Cheque') {
            $concepto = 'Gasto: ' . $gasto->tipo_documento . ' #' . ($gasto->referencia ? $gasto->referencia : '');
            $this->chequesService->crear($gasto, $gasto->nombre_proveedor, $concepto, 'Gasto');
        }
    }

    /**
     * Incrementa el correlativo del documento si es necesario
     *
     * @param Gasto $gasto
     * @param bool $esNuevo Si el gasto es nuevo
     * @return void
     */
    public function incrementarCorrelativo(Gasto $gasto, bool $esNuevo): void
    {
        // Solo incrementar correlativo para gastos nuevos de tipo "Sujeto excluido"
        if (!$esNuevo || $gasto->tipo_documento != 'Sujeto excluido') {
            return;
        }

        $documento = Documento::where('nombre', $gasto->tipo_documento)
            ->where('id_sucursal', $gasto->id_sucursal)
            ->first();

        if ($documento) {
            $documento->increment('correlativo');
            Log::info("Correlativo incrementado para gasto", [
                'gasto_id' => $gasto->id,
                'documento_id' => $documento->id,
                'nuevo_correlativo' => $documento->correlativo
            ]);
        }
    }
}

