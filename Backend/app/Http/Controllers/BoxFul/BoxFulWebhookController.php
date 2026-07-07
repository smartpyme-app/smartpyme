<?php

namespace App\Http\Controllers\BoxFul;

use App\Http\Controllers\Controller;
use App\Models\Admin\Integracion;
use App\Models\Inventario\Paquete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoxFulWebhookController extends Controller
{
    /**
     * Recibir el webhook de Boxful para actualizar el estado del paquete.
     */
    public function handleWebhook(Request $request, $empresaId)
    {
        Log::info('Webhook de Boxful recibido', [
            'empresa_id' => $empresaId,
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);

        // 1. Obtener la integración de Boxful para esta empresa
        $integracion = Integracion::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->where('proveedor', 'boxful')
            ->first();

        if (!$integracion) {
            Log::error('Webhook de Boxful: Integración no encontrada para la empresa', ['empresa_id' => $empresaId]);
            return response()->json([
                'status' => 'error',
                'message' => 'Integración no encontrada.'
            ], 404);
        }

        // 2. Validar que el header Authorization o el query param coincida con el secret guardado
        $secret = $integracion->getConfig('webhook_secret');
        if (empty($secret)) {
            Log::error('Webhook de Boxful: Webhook secret no configurado para la empresa', ['empresa_id' => $empresaId]);
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook secret no configurado.'
            ], 401);
        }

        $incomingSecret = $request->header('Authorization') ?? $request->query('secret');
        if (str_starts_with(strtolower($incomingSecret ?? ''), 'bearer ')) {
            $incomingSecret = substr($incomingSecret, 7);
        }

        if ($incomingSecret !== $secret) {
            Log::warning('Webhook de Boxful: Credencial de webhook inválida para la empresa', [
                'empresa_id' => $empresaId,
                'incoming' => $incomingSecret,
                'expected' => $secret
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'No autorizado.'
            ], 401);
        }

        // 3. Procesar el evento y actualizar el paquete
        $shipmentNumber = $request->input('shipmentNumber');
        $shipmentId = $request->input('shipmentId') ?? $request->input('id');
        $status = $request->input('status'); // e.g. "delivered", "in_transit", etc.

        $paquete = null;
        if ($shipmentId) {
            $shipment = \App\Models\Inventario\BoxfulShipment::where('boxful_shipment_id', $shipmentId)->first();
            if ($shipment) {
                $paquete = Paquete::withoutGlobalScope('empresa')
                    ->where('id_empresa', $empresaId)
                    ->find($shipment->paquete_id);
            }
        }
        if (!$paquete && $shipmentNumber) {
            $paquete = Paquete::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresaId)
                ->where('num_guia', $shipmentNumber)
                ->first();
        }

        if ($paquete) {
            if ($status) {
                $paquete->estado = $status;
            }
            if ($shipmentNumber && (empty($paquete->num_guia) || $paquete->num_guia === $shipmentId || str_starts_with($paquete->num_guia, 'BOXFUL-'))) {
                $paquete->num_guia = $shipmentNumber;
            }
            $paquete->save();

            // also update status description and shipment number in boxful_shipments
            if ($shipmentId) {
                $updateFields = [];
                if ($status) {
                    $updateFields['boxful_status_description'] = $status;
                }
                if ($shipmentNumber) {
                    $updateFields['shipment_number'] = $shipmentNumber;
                }
                if (!empty($updateFields)) {
                    \App\Models\Inventario\BoxfulShipment::where('boxful_shipment_id', $shipmentId)
                        ->update($updateFields);
                }
            }

            Log::info('Webhook de Boxful: Paquete actualizado correctamente', [
                'paquete_id' => $paquete->id,
                'num_guia' => $paquete->num_guia,
                'nuevo_estado' => $status ?? 'sin cambios'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook procesado y paquete actualizado.'
            ], 200);
        }

        Log::warning('Webhook de Boxful: Paquete no encontrado en el sistema', [
            'shipmentNumber' => $shipmentNumber,
            'shipmentId' => $shipmentId
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook recibido, pero paquete no fue encontrado.'
        ], 200);
    }
}
