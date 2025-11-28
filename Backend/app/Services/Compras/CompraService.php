<?php

namespace App\Services\Compras;

use App\Models\Compras\Compra;
use App\Models\Admin\Documento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CompraService
{
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
        if (!in_array($tipoDocumento, ['Orden de compra', 'Sujeto excluido'])) {
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
}

