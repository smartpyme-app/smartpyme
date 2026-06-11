<?php

namespace App\Http\Controllers\BoxFul;

use App\Http\Controllers\Controller;
use App\Services\BoxFul\BoxFulService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BoxFulAddressController extends Controller
{
    /**
     * El servicio de Boxful.
     */
    protected $boxfulService;

    /**
     * Constructor del controlador.
     */
    public function __construct(BoxFulService $boxfulService)
    {
        $this->boxfulService = $boxfulService;
    }

    /**
     * Obtiene la lista de estados y ciudades de Boxful.
     * Almacena el resultado en caché por 24 horas.
     */
    public function getStates()
    {
        try {
            $states = Cache::remember('boxful_states', now()->addHours(24), function () {
                $response = $this->boxfulService->get('states');

                if ($response->failed()) {
                    $body = $response->json();
                    Log::error('Error de API Boxful en getStates', [
                        'status' => $response->status(),
                        'response' => $body,
                    ]);
                    
                    throw new \Exception(
                        is_array($body) 
                            ? ($body['message'] ?? $body['error'] ?? 'Error al comunicarse con la API de Boxful') 
                            : 'Error al comunicarse con la API de Boxful'
                    );
                }

                return $response->json();
            });

            return response()->json($states, 200);

        } catch (\Exception $e) {
            Log::error('Excepción en BoxFulAddressController@getStates', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudieron obtener los estados y ciudades de Boxful: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las direcciones registradas en Boxful para la empresa activa.
     */
    public function getAddresses()
    {
        try {
            $response = $this->boxfulService->get('addresses');

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en getAddresses', [
                    'status' => $status,
                    'response' => $body,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al obtener las direcciones de Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 200);

        } catch (\Exception $e) {
            Log::error('Excepción en BoxFulAddressController@getAddresses', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener las direcciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registra una nueva dirección en Boxful.
     */
    public function storeAddress(Request $request)
    {
        try {
            $request->validate([
                'address' => 'required|string|max:500',
                'referencePoint' => 'nullable|string|max:500',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'stateId' => 'required|string',
                'cityId' => 'required|string',
                'addressPhone' => 'required|string|max:20',
                'addressAreaCode' => 'required|string|max:10',
            ]);

            // Filtrar estrictamente para enviar solo los campos requeridos (evitando _params u otros)
            $payload = $request->only([
                'address', 'referencePoint', 'latitude', 'longitude', 'stateId', 'cityId', 'addressPhone', 'addressAreaCode'
            ]);

            $response = $this->boxfulService->post('addresses', $payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en storeAddress', [
                    'status' => $status,
                    'response' => $body,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al registrar la dirección en Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los datos proporcionados no son válidos para Boxful.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Excepción en BoxFulAddressController@storeAddress', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar la dirección: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza una dirección existente en Boxful.
     */
    public function updateAddress(Request $request, $id)
    {
        try {
            $request->validate([
                'address' => 'sometimes|required|string|max:500',
                'referencePoint' => 'nullable|string|max:500',
                'latitude' => 'sometimes|required|numeric',
                'longitude' => 'sometimes|required|numeric',
                'stateId' => 'sometimes|required|string',
                'cityId' => 'sometimes|required|string',
                'addressPhone' => 'sometimes|required|string|max:20',
                'addressAreaCode' => 'sometimes|required|string|max:10',
            ]);

            // Filtrar estrictamente para enviar solo los campos requeridos
            $payload = $request->only([
                'address', 'referencePoint', 'latitude', 'longitude', 'stateId', 'cityId', 'addressPhone', 'addressAreaCode'
            ]);

            // Mantener solo los campos presentes en la petición
            $payload = array_filter($payload, function ($value, $key) use ($request) {
                return $request->has($key);
            }, ARRAY_FILTER_USE_BOTH);

            $response = $this->boxfulService->patch("addresses/{$id}", $payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en updateAddress', [
                    'id' => $id,
                    'status' => $status,
                    'response' => $body,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al actualizar la dirección en Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los datos proporcionados no son válidos para Boxful.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Excepción en BoxFulAddressController@updateAddress', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar la dirección: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina una dirección en Boxful.
     */
    public function destroyAddress($id)
    {
        try {
            $response = $this->boxfulService->delete("addresses/{$id}");

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en destroyAddress', [
                    'id' => $id,
                    'status' => $status,
                    'response' => $body,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al eliminar la dirección en Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            $body = $response->json();
            return response()->json($body ?? ['status' => 'success', 'message' => 'Dirección eliminada correctamente.'], $response->status());

        } catch (\Exception $e) {
            Log::error('Excepción en BoxFulAddressController@destroyAddress', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar la dirección: ' . $e->getMessage()
            ], 500);
        }
    }
}
