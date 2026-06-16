<?php

namespace App\Http\Controllers\BoxFul;

use App\Http\Controllers\Controller;
use App\Services\BoxFul\BoxFulService;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Clientes\DireccionEnvio;
use App\Models\Admin\Integracion;
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

            $integracion = $this->boxfulService->getIntegracion();
            if (!$integracion) {
                $integracion = $empresa->obtenerOcrearIntegracion();
            }

            $clientId = $integracion->getCredential('client_id');
            if (empty($clientId)) {
                // Autorecuperar si es null
                $this->boxfulService->getAccessToken();
                $integracion->refresh();
                $clientId = $integracion->getCredential('client_id');
            }

            if (empty($clientId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La empresa no tiene configurado un clientId de Boxful. Verifique la conexión en Ajustes de Empresa.'
                ], 400);
            }

            // Datos del paquete
            $peso = (float) ($paqueteInput['peso'] ?? $paqueteInput['weight'] ?? ($paqueteModel ? $paqueteModel->peso : 1.0));
            $alto = (float) ($paqueteInput['alto'] ?? $paqueteInput['height'] ?? ($paqueteModel && $paqueteModel->alto ? $paqueteModel->alto : 10.0));
            $ancho = (float) ($paqueteInput['ancho'] ?? $paqueteInput['width'] ?? ($paqueteModel && $paqueteModel->ancho ? $paqueteModel->ancho : 10.0));
            $largo = (float) ($paqueteInput['largo'] ?? $paqueteInput['length'] ?? ($paqueteModel && $paqueteModel->largo ? $paqueteModel->largo : 10.0));
            $valor = (float) ($paqueteInput['valor'] ?? $paqueteInput['price'] ?? ($paqueteModel ? ($paqueteModel->precio ?? $paqueteModel->total) : 10.00));
            $contenido = $paqueteInput['contenido'] ?? $paqueteInput['content'] ?? 'Productos varios';
            $isFragile = (bool) ($paqueteInput['es_fragil'] ?? $paqueteInput['isFragile'] ?? ($paqueteModel ? $paqueteModel->es_fragil : false));

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
                    'codAmount' => 0,
                    'isFragile' => $isFragile
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

            // Obtener dirección de origen de la bodega/tienda
            $origenInput = $request->input('origen');
            $direccionOrigenId = null;

            if (!empty($origenInput)) {
                $direccionOrigenId = $origenInput['id'] ?? $origenInput['recolectionAddressId'] ?? null;
            }

            if (empty($direccionOrigenId)) {
                // Fallback a la configuración de la integración
                $direccionOrigenId = $integracion->getConfig('direccion_origen_id');
            }

            if (empty($direccionOrigenId)) {
                // Fallback: si no está configurada, intentar buscar las direcciones registradas de la empresa en Boxful y tomar la primera
                try {
                    $addrResponse = $this->boxfulService->get('addresses');
                    if ($addrResponse->successful()) {
                        $addresses = $addrResponse->json();
                        $addressList = isset($addresses['addresses']) ? $addresses['addresses'] : (isset($addresses['data']) ? $addresses['data'] : $addresses);
                        if (is_array($addressList) && count($addressList) > 0) {
                            $firstAddr = $addressList[0];
                            $direccionOrigenId = $firstAddr['id'] ?? $firstAddr['addressId'] ?? null;
                            
                            if (!empty($direccionOrigenId)) {
                                // Guardar en configuración para no repetir la llamada HTTP en el futuro
                                $integracion->setConfig(['direccion_origen_id' => $direccionOrigenId]);
                                $integracion->save();
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('No se pudo autodetectar dirección de origen de la empresa para disponible: ' . $e->getMessage());
                }
            }

            if (empty($direccionOrigenId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dirección de bodega de origen no configurada. Por favor, configure la dirección de origen en Ajustes de Empresa.'
                ], 400);
            }

            $boxfulPayload['recolectionAddressId'] = $direccionOrigenId;

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

            $destino = $request->input('destino');
            $clienteInput = $request->input('cliente') ?? [];
            $clienteId = $request->input('clienteId') ?? ($clienteInput['id'] ?? ($paqueteModel ? $paqueteModel->id_cliente : null));
            $courierId = $request->input('courierId');

            $integracion = $this->boxfulService->getIntegracion();
            if (!$integracion) {
                $integracion = $empresa->obtenerOcrearIntegracion();
            }

            $clientId = $integracion->getCredential('client_id');
            if (empty($clientId)) {
                $this->boxfulService->getAccessToken();
                $integracion->refresh();
                $clientId = $integracion->getCredential('client_id');
            }

            if (empty($clientId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La empresa no tiene configurado un clientId de Boxful.'
                ], 400);
            }

            // Datos del paquete
            $peso = (float) ($paqueteInput['peso'] ?? $paqueteInput['weight'] ?? ($paqueteModel ? $paqueteModel->peso : 1.0));
            $alto = (float) ($paqueteInput['alto'] ?? $paqueteInput['height'] ?? ($paqueteModel && $paqueteModel->alto ? $paqueteModel->alto : 10.0));
            $ancho = (float) ($paqueteInput['ancho'] ?? $paqueteInput['width'] ?? ($paqueteModel && $paqueteModel->ancho ? $paqueteModel->ancho : 10.0));
            $largo = (float) ($paqueteInput['largo'] ?? $paqueteInput['length'] ?? ($paqueteModel && $paqueteModel->largo ? $paqueteModel->largo : 10.0));
            $valor = (float) ($paqueteInput['valor'] ?? $paqueteInput['price'] ?? ($paqueteModel ? ($paqueteModel->precio ?? $paqueteModel->total) : 10.00));
            $contenido = $paqueteInput['contenido'] ?? $paqueteInput['content'] ?? 'Productos varios';
            $isFragile = (bool) ($paqueteInput['es_fragil'] ?? $paqueteInput['isFragile'] ?? ($paqueteModel ? $paqueteModel->es_fragil : false));

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
                    'isFragile' => $isFragile
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

            // Obtener dirección de origen de la bodega/tienda
            $origenInput = $request->input('origen');
            $direccionOrigenId = null;

            if (!empty($origenInput)) {
                $direccionOrigenId = $origenInput['id'] ?? $origenInput['recolectionAddressId'] ?? null;
            }

            if (empty($direccionOrigenId)) {
                // Fallback a la configuración de la integración
                $direccionOrigenId = $integracion->getConfig('direccion_origen_id');
            }

            if (empty($direccionOrigenId)) {
                // Fallback: si no está configurada, intentar buscar las direcciones registradas de la empresa en Boxful y tomar la primera
                try {
                    $addrResponse = $this->boxfulService->get('addresses');
                    if ($addrResponse->successful()) {
                        $addresses = $addrResponse->json();
                        $addressList = isset($addresses['addresses']) ? $addresses['addresses'] : (isset($addresses['data']) ? $addresses['data'] : $addresses);
                        if (is_array($addressList) && count($addressList) > 0) {
                            $firstAddr = $addressList[0];
                            $direccionOrigenId = $firstAddr['id'] ?? $firstAddr['addressId'] ?? null;
                            
                            if (!empty($direccionOrigenId)) {
                                // Guardar en configuración para no repetir la llamada HTTP en el futuro
                                $integracion->setConfig(['direccion_origen_id' => $direccionOrigenId]);
                                $integracion->save();
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('No se pudo autodetectar dirección de origen de la empresa para envío: ' . $e->getMessage());
                }
            }

            if (empty($direccionOrigenId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dirección de bodega de origen no configurada. Por favor, configure la dirección de origen en Ajustes de Empresa.'
                ], 400);
            }

            $boxfulPayload['recolectionAddressId'] = $direccionOrigenId;

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
            $shipmentId = $shipmentData['id'] ?? $shipmentData['data']['id'] ?? $shipmentData['response']['id'] ?? null;
            $labelUrl = $shipmentData['labelUrl'] ?? $shipmentData['data']['labelUrl'] ?? $shipmentData['response']['labelUrl'] ?? null;
            $trackingUrl = $shipmentData['trackingUrl'] ?? $shipmentData['data']['trackingUrl'] ?? $shipmentData['response']['trackingUrl'] ?? null;

            if ($shipmentNumber && $paqueteModel) {
                // ponytail: save shipment & parcel data to separate boxful tables
                $paqueteModel->num_guia = $shipmentNumber;
                $paqueteModel->save();

                $localOrigenId = null;
                if (!empty($direccionOrigenId)) {
                    $localOrigen = \App\Models\Admin\DireccionOrigen::where('boxful_address_id', $direccionOrigenId)->first();
                    if ($localOrigen) {
                        $localOrigenId = $localOrigen->id;
                    } else {
                        // Create a skeleton local record so foreign key is satisfied
                        $localOrigen = \App\Models\Admin\DireccionOrigen::create([
                            'id_empresa' => $empresa->id,
                            'alias' => 'Dirección autogenerada',
                            'direccion' => $destino['direccion'] ?? 'Dirección',
                            'referencia' => $destino['referencia'] ?? 'Autogenerado',
                            'telefono' => $destino['telefono'] ?? '70000000',
                            'boxful_state_id' => $destino['stateId'] ?? '0',
                            'boxful_city_id' => $destino['cityId'] ?? '0',
                            'boxful_address_id' => $direccionOrigenId,
                            'latitud' => 0,
                            'longitud' => 0,
                        ]);
                        $localOrigenId = $localOrigen->id;
                    }
                }

                $boxfulShipment = \App\Models\Inventario\BoxfulShipment::create([
                    'paquete_id' => $paqueteModel->id,
                    'direccion_origen_id' => $localOrigenId,
                    'fecha_recoleccion' => now(),
                    'cod' => false,
                    'cod_monto' => 0,
                    'boxful_shipment_id' => $shipmentId,
                    'shipment_number' => $shipmentNumber,
                    'boxful_courier_id' => $courierId,
                    'boxful_courier_name' => $shipmentData['courierName'] ?? $shipmentData['data']['courierName'] ?? $shipmentData['response']['courierName'] ?? null,
                    'boxful_label_url' => $labelUrl,
                    'boxful_tracking_url' => $trackingUrl,
                    'boxful_status' => $shipmentData['status'] ?? null,
                    'boxful_status_description' => $shipmentData['statusDescription'] ?? null,
                ]);

                if ($boxfulShipment) {
                    \App\Models\Inventario\BoxfulParcel::create([
                        'boxful_shipment_id' => $boxfulShipment->id,
                        'contenido' => $contenido,
                        'alto' => $alto,
                        'ancho' => $ancho,
                        'largo' => $largo,
                        'peso' => $peso,
                        'valor_declarado' => $valor,
                        'es_fragil' => $isFragile,
                    ]);
                }

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

    /**
     * E) Obtiene los detalles de un envío en Boxful por su ID.
     */
    public function getShipment($id)
    {
        try {
            $response = $this->boxfulService->getShipment($id);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en getShipment', [
                    'id' => $id,
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al consultar el envío en Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 200);

        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getShipment exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al consultar el envío: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * F) Obtiene los transportistas (couriers) disponibles de Shiphero en Boxful.
     */
    public function getShipheroCouriers(Request $request)
    {
        try {
            $response = $this->boxfulService->getShipheroCouriers($request->query());

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en getShipheroCouriers', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al obtener transportistas de Shiphero.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 200);

        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getShipheroCouriers exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los transportistas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * G) Proxy a Boxful POST /quoter.
     */
    public function getQuote(Request $request)
    {
        try {
            $request->validate([
                'recollectionCityId' => 'required|string',
                'customerCityId' => 'required|string',
            ]);

            $payload = $request->only(['recollectionCityId', 'customerCityId']);

            $response = $this->boxfulService->getQuote($payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en getQuote', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error de Boxful al cotizar.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación local fallida.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getQuote exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al cotizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * H) Proxy a Boxful POST /shiphero/orders.
     */
    public function createShipheroOrder(Request $request)
    {
        try {
            $request->validate([
                'cityId' => 'required|string',
                'completeName' => 'required|string',
                'customerAreaCode' => 'required|string',
                'customerPhone' => 'required|string',
                'customerAddress' => 'required|string',
                'customerReferencePoint' => 'required|string',
                'cod' => 'required|boolean',
                'courierId' => 'required|string',
                'totalTax' => 'required|numeric',
                'subtotal' => 'required|numeric',
                'totalDiscounts' => 'required|numeric',
                'totalPrice' => 'required|numeric',
                'shippingCost' => 'required|numeric',
                'products' => 'required|array',
                'products.*.sku' => 'required|string',
                'products.*.quantity' => 'required|numeric',
                'products.*.price' => 'required|numeric',
                'products.*.productName' => 'required|string',
                'isFragile' => 'required|boolean',
            ]);

            $payload = $request->only([
                'cityId', 'completeName', 'email', 'customerAreaCode', 'customerPhone',
                'customerAddress', 'customerReferencePoint', 'cod', 'courierId',
                'totalTax', 'subtotal', 'totalDiscounts', 'totalPrice', 'shippingCost',
                'products', 'makeCustomerFavorite', 'isFavoriteCustomerSelected',
                'favoriteCustomerId', 'isFragile'
            ]);

            if ($request->has('_params')) {
                $payload['_params'] = $request->input('_params');
            } else {
                $payload['_params'] = [
                    'cityId' => ['required' => true],
                    'completeName' => ['required' => true],
                    'customerAreaCode' => ['required' => true],
                    'customerPhone' => ['required' => true],
                    'customerAddress' => ['required' => true],
                    'customerReferencePoint' => ['required' => true],
                    'cod' => ['required' => true],
                    'courierId' => ['required' => true],
                    'totalTax' => ['required' => true],
                    'subtotal' => ['required' => true],
                    'totalDiscounts' => ['required' => true],
                    'totalPrice' => ['required' => true],
                    'shippingCost' => ['required' => true],
                    'products.sku' => ['required' => true],
                    'products.quantity' => ['required' => true],
                    'products.price' => ['required' => true],
                    'products.productName' => ['required' => true],
                    'isFragile' => ['required' => true],
                ];
            }

            $response = $this->boxfulService->createShipheroOrder($payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en createShipheroOrder', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error de Boxful al crear la orden de Shiphero.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación local fallida.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@createShipheroOrder exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear la orden de Shiphero: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * I) Proxy a Boxful POST /shiphero/orders/search.
     */
    public function searchShipheroOrders(Request $request)
    {
        try {
            $request->validate([
                'startDate' => 'nullable|string',
                'endDate' => 'nullable|string',
                'orderNumber' => 'nullable|string',
            ]);

            $payload = $request->only(['startDate', 'endDate', 'orderNumber']);

            if ($request->has('_params')) {
                $payload['_params'] = $request->input('_params');
            } else {
                $payload['_params'] = [
                    'startDate' => ['required' => false],
                    'endDate' => ['required' => false],
                    'orderNumber' => ['required' => false],
                ];
            }

            $response = $this->boxfulService->searchShipheroOrders($payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en searchShipheroOrders', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error de Boxful al buscar la orden de Shiphero.') : 'Error de respuesta de la API de Boxful.',
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
            Log::error('BoxFulShippingController@searchShipheroOrders exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al buscar la orden de Shiphero: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * J) Proxy a Boxful GET /shiphero/products.
     */
    public function getShipheroProducts(Request $request)
    {
        try {
            $response = $this->boxfulService->getShipheroProducts($request->query());

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en getShipheroProducts', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al obtener productos de Shiphero.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 200);

        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getShipheroProducts exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los productos de Shiphero: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * K) Proxy a Boxful POST /locker/available.
     */
    public function getAvailableLockers(Request $request)
    {
        try {
            $request->validate([
                'weight' => 'required|numeric',
                'volume' => 'required|numeric',
                'cityId' => 'required|string',
            ]);

            $payload = $request->only(['weight', 'volume', 'cityId']);

            if ($request->has('_params')) {
                $payload['_params'] = $request->input('_params');
            } else {
                $payload['_params'] = [
                    'weight' => [
                        'description' => 'Expressed in pounds',
                        'required' => true
                    ],
                    'volume' => [
                        'description' => 'Expressed in pounds',
                        'required' => true
                    ],
                    'cityId' => [
                        'cityId' => 'cityId on /states',
                        'required' => true
                    ]
                ];
            }

            $response = $this->boxfulService->getAvailableLockers($payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en getAvailableLockers', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error de Boxful al buscar lockers disponibles.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación local fallida.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getAvailableLockers exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al buscar lockers disponibles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * L) Proxy a Boxful GET /tracking/{id}.
     */
    public function getTracking($id)
    {
        try {
            $response = $this->boxfulService->getTracking($id);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en getTracking', [
                    'id' => $id,
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al obtener la información de rastreo en Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 200);

        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getTracking exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al consultar el rastreo: ' . $e->getMessage()
            ], 500);
        }
    }
}
