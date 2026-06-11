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
                'boxful_state_id' => 'required|string',
                'boxful_city_id' => 'required|string',
                'es_predeterminada' => 'boolean'
            ]);

            // Payload limpio para Boxful (EXCLUYENDO _params u otros campos locales)
            $boxfulPayload = [
                'address' => $request->direccion,
                'referencePoint' => $request->referencia,
                'latitude' => (float) $request->latitud,
                'longitude' => (float) $request->longitud,
                'stateId' => $request->boxful_state_id,
                'cityId' => $request->boxful_city_id,
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

            // Validar que vengan los datos requeridos en formato normalizado
            $request->validate([
                'paquete' => 'nullable|array',
                'paqueteId' => 'nullable|integer',
                'destino' => 'nullable|array',
                'direccionDestino' => 'nullable|array',
                'origen' => 'nullable|array',
            ]);

            $destino = $request->input('destino') ?? $request->input('direccionDestino');
            if (empty($destino)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La dirección de destino es obligatoria.'
                ], 422);
            }

            $paqueteInput = $request->input('paquete') ?? [];
            $paqueteId = $request->input('paqueteId') ?? ($paqueteInput['id'] ?? null);
            $paqueteModel = $paqueteId ? \App\Models\Inventario\Paquete::find($paqueteId) : null;

            if (empty($paqueteInput) && empty($paqueteModel)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debe proporcionar un paqueteId válido o los datos del paquete.'
                ], 422);
            }

            $clientId = $empresa->boxful_client_id;
            if (empty($clientId)) {
                // Autorecuperar si es null
                $this->boxfulService->getAccessToken();
                $empresa->refresh();
                $clientId = $empresa->boxful_client_id;
            }

            if (empty($clientId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La empresa no tiene configurado un clientId de Boxful. Verifique la conexión en Ajustes de Empresa.'
                ], 400);
            }

            // Datos del paquete
            $peso = (float) ($paqueteInput['peso'] ?? $paqueteInput['weight'] ?? ($paqueteModel ? $paqueteModel->peso : 1.0));
            $alto = (float) ($paqueteInput['alto'] ?? $paqueteInput['height'] ?? 10.0);
            $ancho = (float) ($paqueteInput['ancho'] ?? $paqueteInput['width'] ?? 10.0);
            $largo = (float) ($paqueteInput['largo'] ?? $paqueteInput['length'] ?? 10.0);
            $valor = (float) ($paqueteInput['valor'] ?? $paqueteInput['price'] ?? ($paqueteModel ? ($paqueteModel->precio ?? $paqueteModel->total) : 10.00));
            $contenido = $paqueteInput['contenido'] ?? $paqueteInput['content'] ?? 'Productos varios';

            // Evitar valores no positivos para evitar errores de validación de Boxful
            if ($peso <= 0) $peso = 1.0;
            if ($alto <= 0) $alto = 10.0;
            if ($ancho <= 0) $ancho = 10.0;
            if ($largo <= 0) $largo = 10.0;
            if ($valor <= 0) $valor = 10.0;

            $packages = [
                [
                    'weight' => $peso,
                    'height' => $alto,
                    'width' => $ancho,
                    'length' => $largo,
                    'content' => $contenido,
                    'price' => $valor,
                    'cod' => false,
                    'codAmount' => 0
                ]
            ];

            // Datos del destino (customerAddress)
            $customerAddress = [
                'latitude' => (float) ($destino['latitud'] ?? $destino['latitude'] ?? $destino['lat'] ?? 0.0),
                'longitude' => (float) ($destino['longitud'] ?? $destino['longitude'] ?? $destino['lng'] ?? 0.0),
                'stateId' => $destino['stateId'] ?? $destino['boxful_state_id'] ?? '',
                'cityId' => $destino['cityId'] ?? $destino['boxful_city_id'] ?? '',
            ];

            // Construir payload oficial para Boxful
            $boxfulPayload = [
                'clientId' => $clientId,
                'recolectionDateTime' => now()->toIso8601String(),
                'weight' => $peso,
                'height' => $alto,
                'width' => $ancho,
                'length' => $largo,
                'packages' => $packages,
                'cod' => false,
                'codAmount' => 0,
                'customerAddress' => $customerAddress,
            ];

            $origen = $request->input('origen');

            // Grupo Pickup (Mutuamente excluyentes)
            if (!empty($origen)) {
                if (!empty($origen['id']) || !empty($origen['recolectionAddressId'])) {
                    $boxfulPayload['recolectionAddressId'] = $origen['id'] ?? $origen['recolectionAddressId'];
                } else {
                    $boxfulPayload['recolectionAddress'] = [
                        'address' => $origen['direccion'] ?? $origen['address'] ?? '',
                        'referencePoint' => $origen['referencia'] ?? $origen['referencePoint'] ?? '',
                        'latitude' => (float) ($origen['latitud'] ?? $origen['latitude'] ?? 0.0),
                        'longitude' => (float) ($origen['longitud'] ?? $origen['longitude'] ?? 0.0),
                        'stateId' => $origen['stateId'] ?? $origen['boxful_state_id'] ?? '',
                        'cityId' => $origen['cityId'] ?? $origen['boxful_city_id'] ?? '',
                        'addressPhone' => $origen['telefono'] ?? $origen['addressPhone'] ?? '',
                        'addressAreaCode' => $origen['codigo_area'] ?? $origen['addressAreaCode'] ?? '503',
                    ];
                }
            } else {
                // Fallback: si no viene origen, intentar buscar las direcciones registradas de la empresa en Boxful y tomar la primera
                try {
                    $addrResponse = $this->boxfulService->get('addresses');
                    if ($addrResponse->successful() && !empty($addrResponse->json())) {
                        $addresses = $addrResponse->json();
                        $addressList = isset($addresses['data']) ? $addresses['data'] : (isset($addresses['addresses']) ? $addresses['addresses'] : $addresses);
                        if (is_array($addressList) && count($addressList) > 0) {
                            $boxfulPayload['recolectionAddressId'] = $addressList[0]['id'] ?? $addressList[0]['addressId'] ?? null;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('No se pudo autodetectar dirección de origen de la empresa: ' . $e->getMessage());
                }
            }

            $response = $this->boxfulService->post('courier/available', $boxfulPayload);

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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación local fallida.',
                'errors' => $e->errors()
            ], 422);
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

            // Validar campos requeridos en formato normalizado
            $request->validate([
                'paquete' => 'nullable|array',
                'paqueteId' => 'nullable|integer',
                'destino' => 'required|array',
                'courierId' => 'required',
                'clienteId' => 'nullable|integer',
                'cliente' => 'nullable|array',
                'origen' => 'nullable|array',
            ]);

            $paqueteInput = $request->input('paquete') ?? [];
            $paqueteId = $request->input('paqueteId') ?? ($paqueteInput['id'] ?? null);
            $paqueteModel = $paqueteId ? \App\Models\Inventario\Paquete::find($paqueteId) : null;

            if (empty($paqueteInput) && empty($paqueteModel)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debe proporcionar un paqueteId válido o los datos del paquete.'
                ], 422);
            }

            $origen = $request->input('origen');
            $destino = $request->input('destino');
            $clienteInput = $request->input('cliente') ?? [];
            $clienteId = $request->input('clienteId') ?? ($clienteInput['id'] ?? ($paqueteModel ? $paqueteModel->id_cliente : null));
            $courierId = $request->input('courierId');

            $clientId = $empresa->boxful_client_id;
            if (empty($clientId)) {
                $this->boxfulService->getAccessToken();
                $empresa->refresh();
                $clientId = $empresa->boxful_client_id;
            }

            if (empty($clientId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La empresa no tiene configurado un clientId de Boxful.'
                ], 400);
            }

            // Datos del paquete
            $peso = (float) ($paqueteInput['peso'] ?? $paqueteInput['weight'] ?? ($paqueteModel ? $paqueteModel->peso : 1.0));
            $alto = (float) ($paqueteInput['alto'] ?? $paqueteInput['height'] ?? 10.0);
            $ancho = (float) ($paqueteInput['ancho'] ?? $paqueteInput['width'] ?? 10.0);
            $largo = (float) ($paqueteInput['largo'] ?? $paqueteInput['length'] ?? 10.0);
            $valor = (float) ($paqueteInput['valor'] ?? $paqueteInput['price'] ?? ($paqueteModel ? ($paqueteModel->precio ?? $paqueteModel->total) : 10.00));
            $contenido = $paqueteInput['contenido'] ?? $paqueteInput['content'] ?? 'Productos varios';

            if ($peso <= 0) $peso = 1.0;
            if ($alto <= 0) $alto = 10.0;
            if ($ancho <= 0) $ancho = 10.0;
            if ($largo <= 0) $largo = 10.0;
            if ($valor <= 0) $valor = 10.0;

            $parcels = [
                [
                    'weight' => $peso,
                    'height' => $alto,
                    'width' => $ancho,
                    'length' => $largo,
                    'content' => $contenido,
                    'price' => $valor,
                    'isFragile' => false
                ]
            ];

            // Cargar datos del cliente de BD para mayor robustez
            $clienteModel = $clienteId ? Cliente::find($clienteId) : null;

            $nombre = $clienteInput['nombre'] ?? $clienteInput['firstName'] ?? ($clienteModel ? $clienteModel->nombre : 'Cliente');
            $apellido = $clienteInput['apellido'] ?? $clienteInput['lastName'] ?? ($clienteModel ? $clienteModel->apellido : 'Smartpyme');
            $email = $clienteInput['email'] ?? ($clienteModel ? ($clienteModel->correo ?? $clienteModel->email) : 'cliente@smartpyme.app');
            $telefono = $clienteInput['telefono'] ?? $clienteInput['phone'] ?? ($clienteModel ? ($clienteModel->getTelefonoEfectivo() ?? $clienteModel->telefono) : '70000000');
            $codigoArea = $clienteInput['codigo_area'] ?? $clienteInput['codigoArea'] ?? ($clienteModel ? $clienteModel->cod_pais : '503');

            // Formatear el código de área para que inicie con +
            if (!str_starts_with($codigoArea, '+')) {
                $codigoArea = '+' . $codigoArea;
            }

            // Mapear wr del paquete como storeOrderNumber y orderNumber
            $orderNumber = null;
            if ($paqueteModel && !empty($paqueteModel->wr)) {
                $orderNumber = $paqueteModel->wr;
            } elseif (!empty($paqueteInput['wr']) || !empty($paqueteInput['orderNumber'])) {
                $orderNumber = $paqueteInput['wr'] ?? $paqueteInput['orderNumber'];
            }

            // Construir el payload oficial para POST /shipment
            $boxfulPayload = [
                'clientId' => $clientId,
                'recolectionDate' => now()->toIso8601String(),
                'courierId' => (string) $courierId,
                'weight' => $peso,
                'height' => $alto,
                'width' => $ancho,
                'length' => $largo,
                'parcels' => $parcels,
                'cod' => false,
                'codAmount' => 0,
                
                // Datos del cliente
                'customerName' => $nombre,
                'customerLastname' => $apellido,
                'customerEmail' => $email,
                'customerPhone' => $telefono,
                'customerPhoneAreaCode' => $codigoArea,
                
                // Grupo Delivery (Envío a domicilio)
                'customerAddress' => $destino['direccion'] ?? $destino['address'] ?? 'Dirección de destino',
                'customerState' => $destino['stateId'] ?? $destino['boxful_state_id'] ?? '',
                'customerCity' => $destino['cityId'] ?? $destino['boxful_city_id'] ?? '',
                'customerAddressReferencePoint' => $destino['referencia'] ?? $destino['referencePoint'] ?? 'Sin referencias',
                'instructions' => $destino['instrucciones'] ?? $destino['instructions'] ?? 'Entregar en dirección indicada',
                'customerAddressLatitude' => (float) ($destino['latitud'] ?? $destino['latitude'] ?? $destino['lat'] ?? 0.0),
                'customerAddressLongitude' => (float) ($destino['longitud'] ?? $destino['longitude'] ?? $destino['lng'] ?? 0.0),
            ];

            if ($orderNumber) {
                $boxfulPayload['storeOrderNumber'] = $orderNumber;
                $boxfulPayload['orderNumber'] = $orderNumber;
            }

            // Grupo Pickup (Mutuamente excluyentes)
            if (!empty($origen)) {
                if (!empty($origen['id']) || !empty($origen['recolectionAddressId'])) {
                    $boxfulPayload['recolectionAddressId'] = $origen['id'] ?? $origen['recolectionAddressId'];
                } else {
                    $boxfulPayload['recolectionAddress'] = [
                        'address' => $origen['direccion'] ?? $origen['address'] ?? '',
                        'referencePoint' => $origen['referencia'] ?? $origen['referencePoint'] ?? '',
                        'latitude' => (float) ($origen['latitud'] ?? $origen['latitude'] ?? 0.0),
                        'longitude' => (float) ($origen['longitud'] ?? $origen['longitude'] ?? 0.0),
                        'stateId' => $origen['stateId'] ?? $origen['boxful_state_id'] ?? '',
                        'cityId' => $origen['cityId'] ?? $origen['boxful_city_id'] ?? '',
                        'addressPhone' => $origen['telefono'] ?? $origen['addressPhone'] ?? '',
                        'addressAreaCode' => $origen['codigo_area'] ?? $origen['addressAreaCode'] ?? '503',
                    ];
                }
            } else {
                // Fallback: si no viene origen, intentar buscar las direcciones registradas de la empresa en Boxful y tomar la primera
                try {
                    $addrResponse = $this->boxfulService->get('addresses');
                    if ($addrResponse->successful() && !empty($addrResponse->json())) {
                        $addresses = $addrResponse->json();
                        $addressList = isset($addresses['data']) ? $addresses['data'] : (isset($addresses['addresses']) ? $addresses['addresses'] : $addresses);
                        if (is_array($addressList) && count($addressList) > 0) {
                            $boxfulPayload['recolectionAddressId'] = $addressList[0]['id'] ?? $addressList[0]['addressId'] ?? null;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('No se pudo autodetectar dirección de origen de la empresa para envío: ' . $e->getMessage());
                }
            }

            $response = $this->boxfulService->post('shipment', $boxfulPayload);

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

            $shipmentData = $response->json();
            $shipmentNumber = $shipmentData['shipmentNumber'] ?? $shipmentData['data']['shipmentNumber'] ?? $shipmentData['response']['shipmentNumber'] ?? null;

            if ($shipmentNumber && $paqueteModel) {
                $paqueteModel->num_guia = $shipmentNumber;
                $paqueteModel->save();
                Log::info('Guía de Boxful guardada en el paquete local correctamente', [
                    'paqueteId' => $paqueteModel->id,
                    'num_guia' => $shipmentNumber
                ]);
            }

            return response()->json($shipmentData, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación local fallida.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@createShipment exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el envío: ' . $e->getMessage()
            ], 500);
        }
    }
}
