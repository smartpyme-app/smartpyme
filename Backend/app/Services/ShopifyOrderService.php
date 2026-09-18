<?php

namespace App\Services;

use App\Helpers\ShopifyHelper;
use App\Models\Admin\Empresa;
use App\Models\Admin\ShopifyLocation;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Log;

class ShopifyOrderService
{
    protected ?ShopifyApiClient $client;
    protected ShopifyTokenService $tokenService;

    public function __construct(?ShopifyApiClient $client = null, ?ShopifyTokenService $tokenService = null)
    {
        $this->client = $client;
        $this->tokenService = $tokenService ?: app(ShopifyTokenService::class);
    }

    /**
     * Obtiene el cliente de API de Shopify para la empresa dada.
     */
    protected function getClient(Empresa $empresa): ShopifyApiClient
    {
        if ($this->client) {
            return $this->client;
        }

        return new ShopifyApiClient(
            $empresa->shopify_store_url,
            $empresa->shopify_consumer_secret,
            $this->tokenService,
            $empresa
        );
    }

    /**
     * Sincroniza una venta local de SmartPyme hacia Shopify como una nueva orden.
     *
     * @param Venta $venta
     * @return array|false Datos de la orden en Shopify si fue exitoso, false en caso contrario.
     */
    public function crearOrdenDesdeVenta(Venta $venta)
    {
        // 1. Guardas de integridad: no procesar cotizaciones ni ventas originadas en Shopify
        if ((int) ($venta->getAttribute('cotizacion') ?? 0) === 1) {
            ShopifyHelper::log("Sincronización a Shopify omitida: la venta #{$venta->id} es una cotización.");
            return false;
        }

        if (!empty($venta->referencia_shopify)) {
            ShopifyHelper::log("Sincronización a Shopify omitida: la venta #{$venta->id} ya tiene referencia ({$venta->referencia_shopify}).");
            return false;
        }

        // 2. Cargar empresa y verificar configuración
        $empresa = Empresa::find($venta->id_empresa);
        if (!$empresa) {
            ShopifyHelper::log("Sincronización a Shopify omitida: empresa #{$venta->id_empresa} no encontrada.", [], 'warning');
            return false;
        }

        if (!$empresa->shopify_sync_ventas) {
            ShopifyHelper::log("Sincronización a Shopify omitida: la empresa no tiene activado shopify_sync_ventas.");
            return false;
        }

        if ($empresa->shopify_status !== 'connected' || !$empresa->tieneCredencialesShopify()) {
            ShopifyHelper::log("Sincronización a Shopify omitida: la empresa no está conectada a Shopify o carece de credenciales.", [], 'warning');
            return false;
        }

        // 3. Cargar relaciones necesarias
        $venta->loadMissing(['detalles.producto', 'cliente']);

        if ($venta->detalles->isEmpty()) {
            ShopifyHelper::log("Sincronización a Shopify omitida: la venta #{$venta->id} no tiene detalles.", [], 'warning');
            return false;
        }

        // 4. Construir payload de la orden
        $orderPayload = $this->construirPayloadOrden($venta, $empresa);
        if (!$orderPayload) {
            return false;
        }

        try {
            $client = $this->getClient($empresa);
            $response = $client->post('orders.json', ['order' => $orderPayload]);

            $exito = is_array($response)
                ? (($response['status'] ?? '') === 'success')
                : ($response && method_exists($response, 'successful') && $response->successful());

            if ($exito) {
                $data = is_array($response)
                    ? ($response['body'] ?? [])
                    : (method_exists($response, 'json') ? $response->json() : []);
                $createdOrder = $data['order'] ?? null;

                if ($createdOrder && !empty($createdOrder['id'])) {
                    $venta->referencia_shopify = 'SHOPIFY-' . $createdOrder['id'];
                    $venta->num_orden = (string) ($createdOrder['order_number'] ?? $createdOrder['name'] ?? '');

                    if (method_exists($venta, 'saveQuietly')) {
                        $venta->saveQuietly();
                    } else {
                        $venta->save();
                    }

                    ShopifyHelper::log("Venta #{$venta->id} sincronizada exitosamente en Shopify", [
                        'venta_id' => $venta->id,
                        'shopify_order_id' => $createdOrder['id'],
                        'order_number' => $venta->num_orden,
                        'referencia_shopify' => $venta->referencia_shopify,
                    ]);

                    return $createdOrder;
                }
            }

            $errorBody = is_array($response) ? json_encode($response) : ($response ? $response->body() : 'Sin respuesta');
            ShopifyHelper::log("Fallo al crear orden en Shopify para venta #{$venta->id}", [
                'venta_id' => $venta->id,
                'status' => is_array($response) ? ($response['http_status'] ?? null) : ($response ? $response->status() : null),
                'error' => $errorBody,
            ], 'error');

            return false;
        } catch (\Throwable $e) {
            ShopifyHelper::log("Excepción al sincronizar venta #{$venta->id} a Shopify: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ], 'error');
            return false;
        }
    }

    /**
     * Construye el array con los datos requeridos por la API de órdenes de Shopify.
     */
    public function construirPayloadOrden(Venta $venta, Empresa $empresa): ?array
    {
        $lineItems = [];

        foreach ($venta->detalles as $detalle) {
            $producto = $detalle->producto;
            $cantidad = (int) max(1, round($detalle->cantidad));
            $precio = number_format((float) $detalle->precio, 2, '.', '');

            // Si el producto está vinculado con una variante de Shopify
            if ($producto && !empty($producto->shopify_variant_id)) {
                $item = [
                    'variant_id' => (int) $producto->shopify_variant_id,
                    'quantity' => $cantidad,
                    'price' => $precio,
                ];
            } else {
                // Custom line item para servicios o productos locales no catalogados en Shopify
                $nombreItem = $producto ? $producto->nombre : ($detalle->descripcion ?: 'Item');
                if ($producto && !empty($producto->nombre_variante)) {
                    $nombreItem .= ' (' . $producto->nombre_variante . ')';
                }

                $item = [
                    'title' => $nombreItem,
                    'quantity' => $cantidad,
                    'price' => $precio,
                ];
            }

            if ($producto && !empty($producto->shopify_sku)) {
                $item['sku'] = $producto->shopify_sku;
            } elseif ($producto && !empty($producto->codigo)) {
                $item['sku'] = $producto->codigo;
            }

            $lineItems[] = $item;
        }

        if (empty($lineItems)) {
            return null;
        }

        // Estado financiero: 'paid' para ventas cobradas, 'pending' para créditos / pendientes
        $financialStatus = ($venta->estado === 'Pagada') ? 'paid' : 'pending';

        // Fulfillment: si fue pagada físicamente en mostrador, marcarla como entregada (fulfilled)
        $fulfillmentStatus = ($venta->estado === 'Pagada') ? 'fulfilled' : null;

        // Mapeo de ubicación según la bodega de la venta
        $locationId = null;
        if (!empty($venta->id_bodega)) {
            $mapping = ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('id_bodega', $venta->id_bodega)
                ->first();

            if ($mapping && !empty($mapping->shopify_location_id)) {
                $locationId = (int) $mapping->shopify_location_id;
            }
        }

        // Cliente
        $customerData = null;
        $cliente = $venta->cliente;
        if ($cliente) {
            if (!empty($cliente->shopify_customer_id)) {
                $customerData = ['id' => (int) $cliente->shopify_customer_id];
            } elseif (!empty($cliente->correo) || !empty($cliente->telefono)) {
                $customerData = array_filter([
                    'first_name' => $cliente->nombre,
                    'last_name' => $cliente->apellido ?? '',
                    'email' => !empty($cliente->correo) ? $cliente->correo : null,
                    'phone' => !empty($cliente->telefono) ? $cliente->telefono : null,
                ]);
            }
        }

        $tagCorrelativo = !empty($venta->correlativo) ? ', DTE-' . $venta->correlativo : '';
        $notaCorrelativo = !empty($venta->correlativo) ? " (Doc: {$venta->correlativo})" : '';

        $payload = [
            'line_items' => $lineItems,
            'financial_status' => $financialStatus,
            // IMPORTANTE: inventory_behaviour: bypass evita que Shopify descuente el inventario de nuevo,
            // ya que ShopifyInventarioObserver actualiza el stock real desde SmartPyme.
            'inventory_behaviour' => 'bypass',
            'source_name' => 'smartpyme',
            'tags' => "SmartPyme, Venta-Local, Venta #{$venta->id}{$tagCorrelativo}",
            'note' => "Venta registrada desde SmartPyme #{$venta->id}{$notaCorrelativo}",
            'note_attributes' => [
                ['name' => 'smartpyme_venta_id', 'value' => (string) $venta->id],
                ['name' => 'smartpyme_correlativo', 'value' => (string) ($venta->correlativo ?? '')],
            ],
        ];

        if ($fulfillmentStatus) {
            $payload['fulfillment_status'] = $fulfillmentStatus;
        }

        if ($locationId) {
            $payload['location_id'] = $locationId;
        }

        if ($customerData) {
            $payload['customer'] = $customerData;
        }

        return $payload;
    }

    /**
     * Registra una transacción de pago (sale) en Shopify para liquidar una orden pendiente
     * cuando la venta en SmartPyme pasa al estado 'Pagada'.
     *
     * @param Venta $venta
     * @return bool True si la transacción fue registrada exitosamente, false en caso contrario.
     */
    public function marcarOrdenPagadaEnShopify(Venta $venta): bool
    {
        // 1. Validar que la venta tenga referencia a Shopify
        if (empty($venta->referencia_shopify)) {
            ShopifyHelper::log("Sincronización de pago omitida: la venta #{$venta->id} no tiene referencia_shopify.");
            return false;
        }

        // Extraer ID de la orden en Shopify (ej: "SHOPIFY-123456789" -> "123456789")
        $shopifyOrderId = preg_replace('/[^0-9]/', '', (string) $venta->referencia_shopify);
        if (empty($shopifyOrderId)) {
            ShopifyHelper::log("Sincronización de pago omitida: no se pudo extraer el ID de Shopify de la referencia {$venta->referencia_shopify}.", [], 'warning');
            return false;
        }

        // 2. Cargar empresa y verificar configuración
        $empresa = Empresa::find($venta->id_empresa);
        if (!$empresa) {
            ShopifyHelper::log("Sincronización de pago omitida: empresa #{$venta->id_empresa} no encontrada.", [], 'warning');
            return false;
        }

        if (!$empresa->shopify_sync_ventas) {
            ShopifyHelper::log("Sincronización de pago omitida: la empresa no tiene activado shopify_sync_ventas.");
            return false;
        }

        if ($empresa->shopify_status !== 'connected' || !$empresa->tieneCredencialesShopify()) {
            ShopifyHelper::log("Sincronización de pago omitida: la empresa no está conectada o carece de credenciales.", [], 'warning');
            return false;
        }

        // 3. Preparar payload de la transacción de pago
        $monto = number_format((float) $venta->total, 2, '.', '');
        $gateway = !empty($venta->forma_pago) ? strtolower((string) $venta->forma_pago) : 'manual';

        $transactionPayload = [
            'transaction' => [
                'kind' => 'sale',
                'status' => 'success',
                'amount' => $monto,
                'gateway' => $gateway,
                'source' => 'external',
            ],
        ];

        try {
            $client = $this->getClient($empresa);
            $response = $client->post("orders/{$shopifyOrderId}/transactions.json", $transactionPayload);

            $exito = is_array($response)
                ? (($response['status'] ?? '') === 'success')
                : ($response && method_exists($response, 'successful') && $response->successful());

            if ($exito) {
                ShopifyHelper::log("Pago de orden #{$shopifyOrderId} registrado exitosamente en Shopify para venta #{$venta->id}", [
                    'venta_id' => $venta->id,
                    'shopify_order_id' => $shopifyOrderId,
                    'monto' => $monto,
                    'gateway' => $gateway,
                ]);
                return true;
            }

            $errorBody = is_array($response) ? json_encode($response) : ($response ? $response->body() : 'Sin respuesta');
            ShopifyHelper::log("Fallo al registrar pago en Shopify para orden #{$shopifyOrderId}", [
                'venta_id' => $venta->id,
                'shopify_order_id' => $shopifyOrderId,
                'status' => is_array($response) ? ($response['http_status'] ?? null) : ($response ? $response->status() : null),
                'error' => $errorBody,
            ], 'error');

            return false;
        } catch (\Throwable $e) {
            ShopifyHelper::log("Excepción al registrar pago en Shopify para orden #{$shopifyOrderId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ], 'error');
            return false;
        }
    }
}
