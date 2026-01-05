<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\Admin\Empresa;
use App\Services\WooCommerceApiClient;
use App\Services\ShopifyApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IntegracionEcommerceService
{
    /**
     * Guarda credenciales de integración
     *
     * @param User $usuario
     * @param Empresa $empresa
     * @param string $tipo
     * @param array $credenciales
     * @return array
     */
    public function guardarCredenciales(User $usuario, Empresa $empresa, string $tipo, array $credenciales): array
    {
        try {
            // Generar API key si no existe
            if (empty($empresa->woocommerce_api_key)) {
                $empresa->woocommerce_api_key = Str::random(64);
            }

            // Configurar según tipo de plataforma
            if ($tipo === 'woocommerce') {
                $empresa->woocommerce_store_url = $credenciales['store_url'];
                $empresa->woocommerce_consumer_key = $credenciales['consumer_key'];
                $empresa->woocommerce_consumer_secret = $credenciales['consumer_secret'];
                $empresa->woocommerce_status = 'connecting';
                $empresa->woocommerce_canal_id = $credenciales['canal_id'] ?? null;
            } else { // shopify
                $empresa->shopify_store_url = $credenciales['store_url'];
                $empresa->shopify_consumer_secret = $credenciales['consumer_secret'];
                $empresa->shopify_status = 'connecting';
                $empresa->shopify_canal_id = $credenciales['canal_id'] ?? null;
            }

            $empresa->save();

            // Probar conexión
            $connectionResult = $this->probarConexion($empresa, $tipo);

            if ($connectionResult['success']) {
                $this->marcarComoConectado($empresa, $usuario, $tipo);

                Log::info("Conexión exitosa con {$tipo}", [
                    'empresa_id' => $empresa->id,
                    'tipo' => $tipo,
                    'store_url' => $tipo === 'woocommerce' ? $empresa->woocommerce_store_url : $empresa->shopify_store_url
                ]);

                return [
                    'status' => 'success',
                    'mensaje' => "Credenciales guardadas correctamente. Conexión con " . ucfirst($tipo) . " establecida.",
                    'connection_status' => 'connected',
                    'platform' => $tipo
                ];
            } else {
                $this->marcarComoDesconectado($empresa, $usuario, $tipo);

                return [
                    'status' => 'error',
                    'mensaje' => "Credenciales guardadas, pero no se pudo establecer conexión con " . ucfirst($tipo) . ": " . $connectionResult['error'],
                    'connection_status' => 'disconnected',
                    'platform' => $tipo
                ];
            }
        } catch (\Exception $e) {
            if (isset($empresa) && isset($tipo)) {
                $this->marcarComoDesconectado($empresa, $usuario, $tipo);
            }

            Log::error("Error general en guardarCredenciales", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'mensaje' => 'Error interno del servidor: ' . $e->getMessage(),
                'connection_status' => 'disconnected',
            ];
        }
    }

    /**
     * Prueba conexión con la plataforma
     *
     * @param Empresa $empresa
     * @param string $tipo
     * @return array
     */
    public function probarConexion(Empresa $empresa, string $tipo): array
    {
        try {
            if ($tipo === 'woocommerce') {
                return $this->probarConexionWooCommerce($empresa);
            } else {
                return $this->probarConexionShopify($empresa);
            }
        } catch (\Exception $e) {
            Log::error("Error probando conexión con {$tipo}", [
                'error' => $e->getMessage(),
                'empresa_id' => $empresa->id
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Prueba conexión con WooCommerce
     *
     * @param Empresa $empresa
     * @return array
     */
    private function probarConexionWooCommerce(Empresa $empresa): array
    {
        $client = new WooCommerceApiClient(
            $empresa->woocommerce_store_url,
            $empresa->woocommerce_consumer_key,
            $empresa->woocommerce_consumer_secret
        );

        $response = $client->get('products', ['per_page' => 1]);

        if ($response['status'] !== 'success') {
            throw new \Exception('Respuesta inválida de WooCommerce API');
        }

        return ['success' => true];
    }

    /**
     * Prueba conexión con Shopify
     *
     * @param Empresa $empresa
     * @return array
     */
    private function probarConexionShopify(Empresa $empresa): array
    {
        $client = new ShopifyApiClient(
            $empresa->shopify_store_url,
            $empresa->shopify_consumer_secret
        );

        $response = $client->get('shop.json');
        
        Log::info("Conexión exitosa con Shopify", [
            'empresa_id' => $empresa->id,
            'store_url' => $empresa->shopify_store_url,
            'response' => $response['body']['shop'] ?? null
        ]);

        if ($response['status'] !== 'success') {
            throw new \Exception('Respuesta inválida de Shopify API');
        }

        if (!isset($response['body']['shop'])) {
            throw new \Exception('No se pudo obtener información de la tienda Shopify');
        }

        return ['success' => true];
    }

    /**
     * Marca integración como conectada
     *
     * @param Empresa $empresa
     * @param User $usuario
     * @param string $tipo
     * @return void
     */
    private function marcarComoConectado(Empresa $empresa, User $usuario, string $tipo): void
    {
        $statusField = $tipo . '_status';
        $empresa->$statusField = 'connected';
        $empresa->save();

        if ($tipo === 'woocommerce') {
            $usuario->woocommerce_status = 'connected';
        } else {
            $usuario->shopify_status = 'connected';
        }
        $usuario->save();
    }

    /**
     * Marca integración como desconectada
     *
     * @param Empresa $empresa
     * @param User $usuario
     * @param string $tipo
     * @return void
     */
    private function marcarComoDesconectado(Empresa $empresa, User $usuario, string $tipo): void
    {
        $statusField = $tipo . '_status';
        $empresa->$statusField = 'disconnected';
        $empresa->save();

        if ($tipo === 'woocommerce') {
            $usuario->woocommerce_status = 'disconnected';
        } else {
            $usuario->shopify_status = 'disconnected';
        }
        $usuario->save();
    }

    /**
     * Desconecta WooCommerce
     *
     * @param User $usuario
     * @param Empresa $empresa
     * @return array
     */
    public function desconectarWooCommerce(User $usuario, Empresa $empresa): array
    {
        if (empty($empresa->woocommerce_api_key)) {
            return [
                'status' => 'error',
                'mensaje' => 'No tienes configurada la integración con WooCommerce'
            ];
        }

        $empresa->woocommerce_status = 'disconnected';
        $empresa->save();

        return [
            'status' => 'success',
            'mensaje' => 'Conexión con WooCommerce desactivada'
        ];
    }
}
