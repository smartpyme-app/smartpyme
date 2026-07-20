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
            'has_authorization' => $request->hasHeader('Authorization'),
            'keys' => array_keys($request->all()),
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
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'No autorizado.'
            ], 401);
        }

        // 3. Procesar el evento y actualizar el paquete
        $shipmentNumber = $request->input('shipmentNumber');
        $shipmentId = $request->input('shipmentId') ?? $request->input('id');
        $status = $request->input('status');
        $statusDescription = $request->input('statusDescription')
            ?? $request->input('status_description')
            ?? (is_string($status) ? $status : null);
        $statusNumeric = $this->mapBoxfulStatusToInt($status);

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
            if ($statusDescription) {
                $paquete->estado = $statusDescription;
            } elseif (is_string($status) && $status !== '') {
                $paquete->estado = $status;
            }
            if ($shipmentNumber && (empty($paquete->num_guia) || $paquete->num_guia === $shipmentId || str_starts_with($paquete->num_guia, 'BOXFUL-'))) {
                $paquete->num_guia = $shipmentNumber;
            }
            $paquete->save();

            $updateFields = [];
            if ($statusDescription) {
                $updateFields['boxful_status_description'] = $statusDescription;
            }
            if ($statusNumeric !== null) {
                $updateFields['boxful_status'] = $statusNumeric;
            }
            if ($shipmentNumber) {
                $updateFields['shipment_number'] = $shipmentNumber;
            }
            if (!empty($updateFields)) {
                $query = \App\Models\Inventario\BoxfulShipment::query();
                if ($shipmentId) {
                    $query->where('boxful_shipment_id', $shipmentId);
                } else {
                    $query->where('paquete_id', $paquete->id);
                }
                $query->update($updateFields);
            }

            Log::info('Webhook de Boxful: Paquete actualizado correctamente', [
                'paquete_id' => $paquete->id,
                'num_guia' => $paquete->num_guia,
                'nuevo_estado' => $statusDescription ?? $status ?? 'sin cambios',
                'boxful_status' => $statusNumeric,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook procesado y paquete actualizado.'
            ], 200);
        }

        Log::warning('Webhook de Boxful: Paquete no encontrado en el sistema', [
            'shipmentNumber' => $shipmentNumber,
            'shipmentId' => $shipmentId,
            'empresa_id' => $empresaId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook recibido, pero paquete no fue encontrado.'
        ], 200);
    }

    /**
     * Mapea status BoxFul (string o int) al tinyInteger local (-1..4+).
     */
    private function mapBoxfulStatusToInt($status): ?int
    {
        if ($status === null || $status === '') {
            return null;
        }
        if (is_numeric($status)) {
            return (int) $status;
        }

        $s = strtolower(trim((string) $status));
        $map = [
            'created' => -1,
            'creado' => -1,
            'registered' => 1,
            'registrado' => 1,
            'registrada' => 1,
            'picked' => 2,
            'pickup' => 2,
            'recolectado' => 2,
            'recolectada' => 2,
            'in_transit' => 3,
            'in transit' => 3,
            'transit' => 3,
            'ruta' => 3,
            'camino' => 3,
            'delivered' => 4,
            'entregado' => 4,
            'entregada' => 4,
        ];

        foreach ($map as $needle => $value) {
            if ($s === $needle || str_contains($s, $needle)) {
                return $value;
            }
        }

        return null;
    }
}
