<?php

namespace App\Http\Controllers\BoxFul;

use App\Http\Controllers\Controller;
use App\Services\BoxFul\BoxFulService;
use App\Models\Ventas\Clientes\Cliente;
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
     * A) Devuelve las direcciones locales de origen de la empresa.
     */
    public function getOriginAddresses()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No autorizado.'
                ], 401);
            }

            //synchronize with Boxful on demand when origin addresses are requested
            $this->boxfulService->syncOriginAddresses();

            //query local origin addresses filtered by empresa
            $direcciones = \App\Models\Admin\DireccionOrigen::where('id_empresa', $user->id_empresa)->get();

            return response()->json($direcciones, 200);
        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@getOriginAddresses error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener direcciones de origen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * B) Guarda una dirección de origen localmente y en Boxful.
     */
    public function storeOriginAddress(Request $request)
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

            $request->validate([
                'alias' => 'required|string|max:100',
                'direccion' => 'required|string|max:500',
                'referencia' => 'required|string|max:500',
                'telefono' => 'required|string|max:20',
                'codigo_area' => 'required|string|max:10',
                'latitud' => 'required|numeric',
                'longitud' => 'required|numeric',
                'boxful_state_id' => 'required|string',
                'boxful_city_id' => 'required|string',
                'es_predeterminada' => 'boolean'
            ]);

            // Payload para Boxful
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
                Log::error('Error de API Boxful al registrar dirección en storeOriginAddress', [
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

            if ($request->es_predeterminada) {
                \App\Models\Admin\DireccionOrigen::where('id_empresa', $empresa->id)->update(['es_predeterminada' => false]);
            }

            // Guardar localmente
            //save origin address to local DB and reference Boxful address ID
            $direccionLocal = new \App\Models\Admin\DireccionOrigen([
                'id_empresa' => $empresa->id,
                'alias' => $request->alias,
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación local fallida.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('BoxFulShippingController@storeOriginAddress exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar la dirección de origen: ' . $e->getMessage()
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
                'paquetes' => 'nullable|array',
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

            $paquetesInput = $request->input('paquetes');
            $paqueteInput = $request->input('paquete') ?? [];
            $paqueteId = $request->input('paqueteId') ?? ($paqueteInput['id'] ?? null);
            $paqueteModel = $paqueteId ? \App\Models\Inventario\Paquete::find($paqueteId) : null;

            if (empty($paquetesInput) && empty($paqueteInput) && empty($paqueteModel)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debe proporcionar un paqueteId válido, los datos del paquete o el listado de paquetes.'
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

            $packages = [];

            if (is_array($paquetesInput) && count($paquetesInput) > 0) {
                foreach ($paquetesInput as $p) {
                    $pWeight = (float) ($p['peso'] ?? $p['weight'] ?? 1.0);
                    $pHeight = (float) ($p['alto'] ?? $p['height'] ?? 10.0);
                    $pWidth = (float) ($p['ancho'] ?? $p['width'] ?? 10.0);
                    $pLength = (float) ($p['largo'] ?? $p['length'] ?? 10.0);
                    $pValue = (float) ($p['valor'] ?? $p['price'] ?? 10.00);
                    $pContent = $p['contenido'] ?? $p['content'] ?? 'Productos varios';
                    $pFragile = (bool) ($p['es_fragil'] ?? $p['isFragile'] ?? false);

                    if ($pWeight <= 0) $pWeight = 1.0;
                    if ($pHeight <= 0) $pHeight = 10.0;
                    if ($pWidth <= 0) $pWidth = 10.0;
                    if ($pLength <= 0) $pLength = 10.0;
                    if ($pValue <= 0) $pValue = 10.0;

                    $packages[] = [
                        'weight' => $pWeight,
                        'height' => $pHeight,
                        'width' => $pWidth,
                        'length' => $pLength,
                        'content' => $pContent,
                        'price' => $pValue,
                        'cod' => false,
                        'codAmount' => null,
                        'isFragile' => $pFragile
                    ];
                }
            } else {
                // Datos del paquete único
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

                $packages[] = [
                    'weight' => $peso,
                    'height' => $alto,
                    'width' => $ancho,
                    'length' => $largo,
                    'content' => $contenido,
                    'price' => $valor,
                    'cod' => false,
                    'codAmount' => null,
                    'isFragile' => $isFragile
                ];
            }

            // Calcular totales del envío
            $totalWeight = 0;
            $maxHeight = 0;
            $maxWidth = 0;
            $maxLength = 0;
            foreach ($packages as $p) {
                $totalWeight += $p['weight'];
                $maxHeight = max($maxHeight, $p['height']);
                $maxWidth = max($maxWidth, $p['width']);
                $maxLength = max($maxLength, $p['length']);
            }

            // Datos del destino (customerAddress)
            $customerAddress = [
                'latitude' => (float) ($destino['latitud'] ?? $destino['latitude'] ?? $destino['lat'] ?? 0.0),
                'longitude' => (float) ($destino['longitud'] ?? $destino['longitude'] ?? $destino['lng'] ?? 0.0),
                'stateId' => $destino['stateId'] ?? $destino['boxful_state_id'] ?? '',
                'cityId' => $destino['cityId'] ?? $destino['boxful_city_id'] ?? '',
            ];

            $fechaRecoleccion = $request->input('fecha_recoleccion') ?? $request->input('recolectionDateTime') ?? $request->input('recolectionDate') ?? now()->toIso8601String();
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRecoleccion)) {
                $fechaRecoleccion = \Carbon\Carbon::parse($fechaRecoleccion . ' 15:00:00')->toIso8601String();
            } else {
                try {
                    $fechaRecoleccion = \Carbon\Carbon::parse($fechaRecoleccion)->toIso8601String();
                } catch (\Exception $e) {
                    $fechaRecoleccion = now()->toIso8601String();
                }
            }

            // Construir payload oficial para Boxful
            $boxfulPayload = [
                'clientId' => $clientId,
                'recolectionDateTime' => $fechaRecoleccion,
                'weight' => $totalWeight,
                'height' => $maxHeight,
                'width' => $maxWidth,
                'length' => $maxLength,
                'packages' => $packages,
                'cod' => false,
                'codAmount' => null,
                'customerAddress' => $customerAddress,
            ];

            // Obtener dirección de origen de la bodega/tienda
            $origenInput = $request->input('origen');
            $direccionOrigenId = null;

            if (!empty($origenInput)) {
                $direccionOrigenId = $origenInput['id'] ?? $origenInput['recolectionAddressId'] ?? null;
                if (!empty($direccionOrigenId) && (!is_string($direccionOrigenId) || strlen($direccionOrigenId) !== 24 || !ctype_xdigit($direccionOrigenId))) {
                    $localOrigen = \App\Models\Admin\DireccionOrigen::find($direccionOrigenId);
                    if ($localOrigen && !empty($localOrigen->boxful_address_id)) {
                        $direccionOrigenId = $localOrigen->boxful_address_id;
                    }
                }
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
                'paquetes' => 'nullable|array',
                'destino' => 'required|array',
                'courierId' => 'required',
                'clienteId' => 'nullable|integer',
                'cliente' => 'nullable|array',
                'origen' => 'nullable|array',
            ]);

            $paquetesInput = $request->input('paquetes');
            $paqueteInput = $request->input('paquete') ?? [];
            $paqueteId = $request->input('paqueteId') ?? ($paqueteInput['id'] ?? null);
            $paqueteModel = $paqueteId ? \App\Models\Inventario\Paquete::find($paqueteId) : null;

            if (empty($paquetesInput) && empty($paqueteInput) && empty($paqueteModel)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debe proporcionar un paqueteId válido, los datos del paquete o el listado de paquetes.'
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

            $parcels = [];

            if (is_array($paquetesInput) && count($paquetesInput) > 0) {
                foreach ($paquetesInput as $p) {
                    $pWeight = (float) ($p['peso'] ?? $p['weight'] ?? 1.0);
                    $pHeight = (float) ($p['alto'] ?? $p['height'] ?? 10.0);
                    $pWidth = (float) ($p['ancho'] ?? $p['width'] ?? 10.0);
                    $pLength = (float) ($p['largo'] ?? $p['length'] ?? 10.0);
                    $pValue = (float) ($p['valor'] ?? $p['price'] ?? 10.00);
                    $pContent = $p['contenido'] ?? $p['content'] ?? 'Productos varios';
                    $pFragile = (bool) ($p['es_fragil'] ?? $p['isFragile'] ?? false);

                    if ($pWeight <= 0) $pWeight = 1.0;
                    if ($pHeight <= 0) $pHeight = 10.0;
                    if ($pWidth <= 0) $pWidth = 10.0;
                    if ($pLength <= 0) $pLength = 10.0;
                    if ($pValue <= 0) $pValue = 10.0;

                    $parcels[] = [
                        'weight' => $pWeight,
                        'height' => $pHeight,
                        'width' => $pWidth,
                        'length' => $pLength,
                        'content' => $pContent,
                        'price' => $pValue,
                        'isFragile' => $pFragile
                    ];
                }
            } else {
                // Datos del paquete único
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

                $parcels[] = [
                    'weight' => $peso,
                    'height' => $alto,
                    'width' => $ancho,
                    'length' => $largo,
                    'content' => $contenido,
                    'price' => $valor,
                    'isFragile' => $isFragile
                ];
            }

            // Calcular totales del envío
            $totalWeight = 0;
            $maxHeight = 0;
            $maxWidth = 0;
            $maxLength = 0;
            foreach ($parcels as $p) {
                $totalWeight += $p['weight'];
                $maxHeight = max($maxHeight, $p['height']);
                $maxWidth = max($maxWidth, $p['width']);
                $maxLength = max($maxLength, $p['length']);
            }

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
            } else {
                $orderNumber = $request->input('storeOrderNumber') ?? $request->input('orderNumber') ?? null;
            }

            $fechaRecoleccion = $request->input('fecha_recoleccion') ?? $request->input('recolectionDate') ?? $request->input('recolectionDateTime') ?? now()->toIso8601String();
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRecoleccion)) {
                $fechaRecoleccion = \Carbon\Carbon::parse($fechaRecoleccion . ' 08:00:00')->toIso8601String();
            } else {
                try {
                    $fechaRecoleccion = \Carbon\Carbon::parse($fechaRecoleccion)->toIso8601String();
                } catch (\Exception $e) {
                    $fechaRecoleccion = now()->toIso8601String();
                }
            }

            // Obtener dirección de origen de la bodega/tienda
            $origenInput = $request->input('origen');
            $direccionOrigenId = null;

            if (!empty($origenInput)) {
                $direccionOrigenId = $origenInput['id'] ?? $origenInput['recolectionAddressId'] ?? null;
                if (!empty($direccionOrigenId) && (!is_string($direccionOrigenId) || strlen($direccionOrigenId) !== 24 || !ctype_xdigit($direccionOrigenId))) {
                    $localOrigen = \App\Models\Admin\DireccionOrigen::find($direccionOrigenId);
                    if ($localOrigen && !empty($localOrigen->boxful_address_id)) {
                        $direccionOrigenId = $localOrigen->boxful_address_id;
                    }
                }
            }

            // Construir el payload oficial para POST /shipment
            $boxfulPayload = [
                'clientId' => $clientId,
                'recolectionDate' => $fechaRecoleccion,
                'courierId' => (string) $courierId,
                'weight' => $totalWeight,
                'height' => $maxHeight,
                'width' => $maxWidth,
                'length' => $maxLength,
                'parcels' => $parcels,
                'cod' => false,
                'codAmount' => 0,
                
                // Datos del cliente
                'customerName' => $nombre,
                'customerLastname' => $apellido,
                'customerEmail' => $email,
                'customerPhone' => $telefono,
                'customerPhoneAreaCode' => $codigoArea,
                
                // Root-level delivery fields (backward compatibility fallback)
                'customerAddress' => $destino['direccion'] ?? $destino['address'] ?? 'Dirección de destino',
                'customerState' => $destino['stateId'] ?? $destino['boxful_state_id'] ?? '',
                'customerCity' => $destino['cityId'] ?? $destino['boxful_city_id'] ?? '',
                'customerAddressReferencePoint' => $destino['referencia'] ?? $destino['referencePoint'] ?? 'Sin referencias',
                'instructions' => $destino['instrucciones'] ?? $destino['instructions'] ?? 'Entregar en dirección indicada',
                'customerAddressLatitude' => (float) ($destino['latitud'] ?? $destino['latitude'] ?? $destino['lat'] ?? 0.0),
                'customerAddressLongitude' => (float) ($destino['longitud'] ?? $destino['longitude'] ?? $destino['lng'] ?? 0.0),
                'recolectionAddressId' => $direccionOrigenId,

                // Structured delivery/pickup objects (new API specification)
                'delivery' => [
                    'customerAddress' => $destino['direccion'] ?? $destino['address'] ?? 'Dirección de destino',
                    'customerState' => $destino['stateId'] ?? $destino['boxful_state_id'] ?? '',
                    'customerCity' => $destino['cityId'] ?? $destino['boxful_city_id'] ?? '',
                    'customerAddressReferencePoint' => $destino['referencia'] ?? $destino['referencePoint'] ?? 'Sin referencias',
                    'instructions' => $destino['instrucciones'] ?? $destino['instructions'] ?? 'Entregar en dirección indicada',
                    'customerAddressLatitude' => (float) ($destino['latitud'] ?? $destino['latitude'] ?? $destino['lat'] ?? 0.0),
                    'customerAddressLongitude' => (float) ($destino['longitud'] ?? $destino['longitude'] ?? $destino['lng'] ?? 0.0),
                ],
                'pickup' => [
                    'recolectionAddressId' => $direccionOrigenId
                ]
            ];

            if ($orderNumber) {
                $boxfulPayload['storeOrderNumber'] = $orderNumber;
                $boxfulPayload['orderNumber'] = $orderNumber;
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
            $shipment = null;
            if (isset($shipmentData['shipmentData']) && is_array($shipmentData['shipmentData'])) {
                $shipment = $shipmentData['shipmentData'];
            } elseif (isset($shipmentData['data']) && is_array($shipmentData['data'])) {
                $shipment = $shipmentData['data'];
            } elseif (isset($shipmentData['response']) && is_array($shipmentData['response'])) {
                $shipment = $shipmentData['response'];
            } else {
                $shipment = $shipmentData;
            }

            $shipmentNumber = $shipment['shipmentNumber'] ?? null;
            $shipmentId = $shipment['id'] ?? null;
            $labelUrl = $shipment['labelUrl'] ?? null;
            $trackingUrl = $shipment['trackingUrl'] ?? null;
            $courierName = $shipment['Courier']['name'] ?? $shipment['courierName'] ?? ($shipment['courier_name'] ?? null);
            $statusDesc = $shipment['statusDescription'] ?? ($shipment['status_description'] ?? null);
            $shipmentStatus = $shipment['status'] ?? null;

            if ($shipmentId || $shipmentNumber) {
                // ponytail: dynamically create a local package stub if none exists to satisfy database schema and link the shipment correctly
                if (!$paqueteModel) {
                    $paqueteModel = new \App\Models\Inventario\Paquete();
                    $paqueteModel->id_empresa = $empresa->id;
                    $paqueteModel->id_usuario = $user->id;
                    $paqueteModel->id_sucursal = $user->id_sucursal ?? ($empresa->sucursales()->first()->id ?? null);
                    
                    // If there's an associated Pedido, find it and associate id_venta if available
                    $pedidoId = $request->input('storeOrderNumber') ?? $request->input('orderNumber') ?? null;
                    if ($pedidoId) {
                        $pedido = \App\Models\Restaurante\PedidoRestaurante::find($pedidoId);
                        if ($pedido) {
                            if ($pedido->id_venta) {
                                $paqueteModel->id_venta = $pedido->id_venta;
                            }
                            if (empty($clienteId) && $pedido->cliente_id) {
                                $clienteId = $pedido->cliente_id;
                            }
                            $paqueteModel->nota = "Creado desde pedido #" . $pedido->id;
                        }
                    }
                    
                    $paqueteModel->id_cliente = $clienteId;
                    $paqueteModel->fecha = now();
                    $paqueteModel->wr = $orderNumber ?? ('BOXFUL-' . strtoupper(\Illuminate\Support\Str::random(8)));
                    $paqueteModel->transportista = 'Boxful';
                    $paqueteModel->consignatario = $nombre . ' ' . $apellido;
                    $paqueteModel->estado = 'Pendiente';
                    $paqueteModel->peso = $totalWeight;
                    $paqueteModel->precio = $valor ?? 0;
                    $paqueteModel->piezas = count($parcels);
                    $paqueteModel->num_guia = $shipmentNumber ?? $shipmentId;
                    $paqueteModel->save();
                } else {
                    $paqueteModel->num_guia = $shipmentNumber ?? $shipmentId;
                    $paqueteModel->save();
                }

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
                    'fecha_recoleccion' => $request->input('fecha_recoleccion') ?? $request->input('recolectionDate') ?? now(),
                    'cod' => false,
                    'cod_monto' => 0,
                    'boxful_shipment_id' => $shipmentId,
                    'shipment_number' => $shipmentNumber,
                    'boxful_courier_id' => $courierId,
                    'boxful_courier_name' => $courierName,
                    'boxful_label_url' => $labelUrl,
                    'boxful_tracking_url' => $trackingUrl,
                    'boxful_status' => $shipmentStatus,
                    'boxful_status_description' => $statusDesc,
                ]);

                if ($boxfulShipment) {
                    foreach ($parcels as $p) {
                        \App\Models\Inventario\BoxfulParcel::create([
                            'boxful_shipment_id' => $boxfulShipment->id,
                            'contenido' => $p['content'],
                            'alto' => $p['height'],
                            'ancho' => $p['width'],
                            'largo' => $p['length'],
                            'peso' => $p['weight'],
                            'valor_declarado' => $p['price'],
                            'es_fragil' => $p['isFragile'],
                        ]);
                    }
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
