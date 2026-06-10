<?php

namespace App\Http\Controllers\BoxFul;

use App\Http\Controllers\Controller;
use App\Services\BoxFul\BoxFulService;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Clientes\DireccionEnvio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoxFulShippingController extends Controller
{
    protected $boxfulService;

    public function __construct(BoxFulService $boxfulService)
    {
        $this->boxfulService = $boxfulService;
    }

    /**
     * A) Devuelve las direcciones locales de envío del cliente.
     */
    public function getClientAddresses($clienteId)
    {
        try {
            $cliente = Cliente::findOrFail($clienteId);
            $direcciones = $cliente->direccionesEnvio()->get();

            return response()->json($direcciones, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cliente no encontrado.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getClientAddresses error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener direcciones de envío: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * B) Guarda una dirección de envío localmente y en Boxful.
     */
    public function storeClientAddress($clienteId, Request $request)
    {
        try {
            $cliente = Cliente::findOrFail($clienteId);

            $request->validate([
                'alias' => 'nullable|string|max:100',
                'direccion' => 'required|string|max:500',
                'referencia' => 'nullable|string|max:500',
                'telefono' => 'required|string|max:20',
                'codigo_area' => 'required|string|max:10',
                'latitud' => 'required|numeric',
                'longitud' => 'required|numeric',
                'boxful_state_id' => 'required|integer',
                'boxful_city_id' => 'required|integer',
                'es_predeterminada' => 'boolean'
            ]);

            // Payload limpio para Boxful (EXCLUYENDO _params u otros campos locales)
            $boxfulPayload = [
                'address' => $request->direccion,
                'referencePoint' => $request->referencia,
                'latitude' => (float) $request->latitud,
                'longitude' => (float) $request->longitud,
                'stateId' => (int) $request->boxful_state_id,
                'cityId' => (int) $request->boxful_city_id,
                'addressPhone' => $request->telefono,
                'addressAreaCode' => $request->codigo_area,
            ];

            // Registrar dirección en Boxful
            $response = $this->boxfulService->post('addresses', $boxfulPayload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful al registrar dirección en storeClientAddress', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al registrar la dirección en Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            $boxfulAddressId = $response->json('id') ?? $response->json('data.id') ?? $response->json('addressId');

            if (empty($boxfulAddressId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La API de Boxful no devolvió un ID de dirección válido.'
                ], 500);
            }

            // Si se marca como predeterminada, quitar predeterminada de las demás del mismo cliente
            if ($request->es_predeterminada) {
                $cliente->direccionesEnvio()->update(['es_predeterminada' => false]);
            }

            // Guardar localmente
            $direccionLocal = new DireccionEnvio([
                'id_cliente' => $cliente->id,
                'id_empresa' => $cliente->id_empresa,
                'alias' => $request->alias ?? 'Dirección de envío',
                'direccion' => $request->direccion,
                'referencia' => $request->referencia,
                'telefono' => $request->telefono,
                'codigo_area' => $request->codigo_area,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'boxful_state_id' => $request->boxful_state_id,
                'boxful_city_id' => $request->boxful_city_id,
                'boxful_address_id' => $boxfulAddressId,
                'es_predeterminada' => (bool) $request->es_predeterminada
            ]);

            $direccionLocal->save();

            return response()->json($direccionLocal, 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cliente no encontrado.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación local fallida.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@storeClientAddress exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar la dirección de envío: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * C) Proxy a Boxful POST /courier/available.
     */
    public function getCouriersAvailable(Request $request)
    {
        try {
            $user = auth()->user();
            $empresa = $user ? $user->empresa : null;

            if (!$empresa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo verificar el contexto de la empresa.'
                ], 400);
            }

            $payload = $request->all();

            // Limpieza crítica de Swagger/Postman
            unset($payload['_params']);
            unset($payload['_radioTexts']);

            // Mutuamente excluyentes: recolectAddress vs recolectionAddressId
            if (array_key_exists('recolectionAddressId', $payload) && !empty($payload['recolectionAddressId'])) {
                unset($payload['recolectionAddress']);
            } elseif (array_key_exists('recolectionAddress', $payload)) {
                unset($payload['recolectionAddressId']);
            }

            // Inyectar clientId de la empresa si no viene o para sobreescribir con seguridad
            $payload['clientId'] = (int) ($payload['clientId'] ?? $empresa->boxful_client_id);

            if (empty($payload['clientId'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La empresa no tiene configurado un clientId de Boxful. Verifique la conexión en Ajustes de Empresa.'
                ], 400);
            }

            $response = $this->boxfulService->post('courier/available', $payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en getCouriersAvailable', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error de Boxful al cotizar mensajería.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 200);

        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getCouriersAvailable exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener mensajerías: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * D) Proxy a Boxful POST /shipment.
     */
    public function createShipment(Request $request)
    {
        try {
            $user = auth()->user();
            $empresa = $user ? $user->empresa : null;

            if (!$empresa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo verificar el contexto de la empresa.'
                ], 400);
            }

            $payload = $request->all();

            // Limpieza de Swagger/Postman
            unset($payload['_params']);
            unset($payload['_radioTexts']);

            // Grupo pickup: si viene recolectionAddressId, eliminar recolectionAddress (y viceversa)
            if (array_key_exists('recolectionAddressId', $payload) && !empty($payload['recolectionAddressId'])) {
                unset($payload['recolectionAddress']);
            } elseif (array_key_exists('recolectionAddress', $payload)) {
                unset($payload['recolectionAddressId']);
            }

            // Grupo delivery: si viene customerAddress, eliminar lockerId (y viceversa)
            if (array_key_exists('customerAddress', $payload) && !empty($payload['customerAddress'])) {
                unset($payload['lockerId']);
            } elseif (array_key_exists('lockerId', $payload)) {
                unset($payload['customerAddress']);
            }

            // Inyectar clientId de la empresa
            $payload['clientId'] = (int) ($payload['clientId'] ?? $empresa->boxful_client_id);

            if (empty($payload['clientId'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La empresa no tiene configurado un clientId de Boxful.'
                ], 400);
            }

            $response = $this->boxfulService->post('shipment', $payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en createShipment', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error de Boxful al crear envío.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 201);

        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@createShipment exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el envío: ' . $e->getMessage()
            ], 500);
        }
    }
}
