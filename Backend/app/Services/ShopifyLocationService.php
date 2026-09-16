<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use App\Models\Admin\ShopifyLocation;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Bodega;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyLocationService
{
    /**
     * Sincroniza las ubicaciones desde Shopify hacia la tabla local shopify_locations.
     * Incluye verificación inteligente para auto-vincular sucursales existentes y evitar duplicados.
     */
    public function syncLocationsFromShopify(Empresa $empresa): array
    {
        $client = new ShopifyApiClient(
            $empresa->shopify_store_url,
            $empresa->shopify_consumer_secret,
            app(ShopifyTokenService::class),
            $empresa
        );

        $response = $client->get('locations.json');
        $locations = $response['body']['locations'] ?? [];

        if (empty($locations)) {
            return [
                'success' => false,
                'mensaje' => 'No se encontraron ubicaciones en la tienda de Shopify.',
                'locations' => []
            ];
        }

        $sincronizadas = [];
        $tieneDefault = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('es_default', true)
            ->exists();

        foreach ($locations as $index => $loc) {
            $shopifyLocId = $loc['id'];
            $shopifyLocName = $loc['name'] ?? 'Sucursal Shopify';
            $isActive = (bool)($loc['active'] ?? true);

            $mapping = ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('shopify_location_id', $shopifyLocId)
                ->first();

            if (!$mapping) {
                $mapping = new ShopifyLocation();
                $mapping->id_empresa = $empresa->id;
                $mapping->shopify_location_id = $shopifyLocId;
                $mapping->sincronizar_stock = true;
                // Si no hay ninguna por defecto y es la primera ubicación, asignarla como default
                $mapping->es_default = (!$tieneDefault && $index === 0);
            }

            $mapping->shopify_location_name = $shopifyLocName;
            $mapping->shopify_active = $isActive;

            // Auto-vincular con sucursal existente en SmartPyme si aún no está asignada
            if (empty($mapping->id_sucursal)) {
                // 1. Buscar por shopify_location_id en sucursales
                $sucursal = Sucursal::withoutGlobalScope('empresa')
                    ->where('id_empresa', $empresa->id)
                    ->where('shopify_location_id', $shopifyLocId)
                    ->first();

                // 2. Si no, buscar por coincidencia de nombre (case-insensitive)
                if (!$sucursal) {
                    $sucursal = Sucursal::withoutGlobalScope('empresa')
                        ->where('id_empresa', $empresa->id)
                        ->whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower(trim($shopifyLocName))])
                        ->first();
                }

                if ($sucursal) {
                    $mapping->id_sucursal = $sucursal->id;

                    // Si la sucursal no tenía shopify_location_id, asignarlo
                    if (empty($sucursal->shopify_location_id)) {
                        $sucursal->shopify_location_id = $shopifyLocId;
                        $sucursal->save();
                    }

                    // Auto-asignar la primera bodega activa de esa sucursal
                    if (empty($mapping->id_bodega)) {
                        $bodega = Bodega::withoutGlobalScope('empresa')
                            ->where('id_sucursal', $sucursal->id)
                            ->where('activo', '1')
                            ->first();

                        if ($bodega) {
                            $mapping->id_bodega = $bodega->id;
                        }
                    }
                }
            }

            $mapping->save();
            $sincronizadas[] = $mapping;
        }

        return [
            'success' => true,
            'mensaje' => 'Ubicaciones de Shopify sincronizadas exitosamente.',
            'total' => count($sincronizadas),
            'locations' => $sincronizadas
        ];
    }

    /**
     * Crea una Sucursal y su Bodega en SmartPyme a partir de una ubicación de Shopify,
     * verificando exhaustivamente que no se dupliquen.
     */
    public function crearSucursalYBodega(ShopifyLocation $location, Empresa $empresa): array
    {
        // 1. Si ya tiene sucursal asignada, retornar esa misma
        if (!empty($location->id_sucursal)) {
            $existente = Sucursal::withoutGlobalScope('empresa')->find($location->id_sucursal);
            if ($existente) {
                return [
                    'success' => true,
                    'mensaje' => 'La ubicación ya está vinculada a una sucursal existente.',
                    'sucursal' => $existente,
                    'ya_existia' => true
                ];
            }
        }

        // 2. Verificar por shopify_location_id en la tabla sucursales para evitar duplicados
        $sucursalPorId = Sucursal::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('shopify_location_id', $location->shopify_location_id)
            ->first();

        if ($sucursalPorId) {
            $location->id_sucursal = $sucursalPorId->id;
            if (empty($location->id_bodega)) {
                $bodega = Bodega::withoutGlobalScope('empresa')
                    ->where('id_sucursal', $sucursalPorId->id)
                    ->where('activo', '1')
                    ->first();
                if ($bodega) {
                    $location->id_bodega = $bodega->id;
                }
            }
            $location->save();

            return [
                'success' => true,
                'mensaje' => 'Se encontró una sucursal existente con el mismo identificador y se vinculó.',
                'sucursal' => $sucursalPorId,
                'ya_existia' => true
            ];
        }

        // 3. Verificar por nombre de sucursal (evita duplicar por nombre)
        $sucursalPorNombre = Sucursal::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower(trim($location->shopify_location_name))])
            ->first();

        if ($sucursalPorNombre) {
            $sucursalPorNombre->shopify_location_id = $location->shopify_location_id;
            $sucursalPorNombre->save();

            $location->id_sucursal = $sucursalPorNombre->id;
            if (empty($location->id_bodega)) {
                $bodega = Bodega::withoutGlobalScope('empresa')
                    ->where('id_sucursal', $sucursalPorNombre->id)
                    ->where('activo', '1')
                    ->first();
                if ($bodega) {
                    $location->id_bodega = $bodega->id;
                }
            }
            $location->save();

            return [
                'success' => true,
                'mensaje' => 'Se encontró una sucursal con el mismo nombre y se vinculó automáticamente.',
                'sucursal' => $sucursalPorNombre,
                'ya_existia' => true
            ];
        }

        // 4. Si no existe ninguna coincidencia, crear la Sucursal y su Bodega
        DB::beginTransaction();
        try {
            $nuevaSucursal = Sucursal::create([
                'nombre' => $location->shopify_location_name,
                'telefono' => $empresa->telefono ?? '',
                'correo' => $empresa->correo ?? '',
                'direccion' => $empresa->direccion ?? '',
                'tipo_establecimiento' => '02', // Sucursal/Agencia
                'activo' => '1',
                'id_empresa' => $empresa->id,
                'shopify_location_id' => $location->shopify_location_id,
            ]);

            $nuevaBodega = Bodega::create([
                'nombre' => 'Bodega ' . $location->shopify_location_name,
                'descripcion' => 'Bodega creada automáticamente para Shopify Location ' . $location->shopify_location_name,
                'activo' => '1',
                'id_sucursal' => $nuevaSucursal->id,
                'id_empresa' => $empresa->id,
            ]);

            $location->id_sucursal = $nuevaSucursal->id;
            $location->id_bodega = $nuevaBodega->id;
            $location->save();

            DB::commit();

            return [
                'success' => true,
                'mensaje' => "Sucursal '{$nuevaSucursal->nombre}' y bodega creadas y vinculadas exitosamente.",
                'sucursal' => $nuevaSucursal,
                'bodega' => $nuevaBodega,
                'ya_existia' => false
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creando sucursal desde Shopify: ' . $e->getMessage());
            return [
                'success' => false,
                'mensaje' => 'Error al crear la sucursal: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Resuelve la sucursal y bodega que debe atender una orden proveniente de Shopify.
     * Prioridad:
     * 1. Ubicación explícita de la orden (Shopify POS o location_id en la orden).
     * 2. Ubicación marcada como predeterminada (es_default = true) para ventas online.
     * 3. Primera ubicación mapeada con sucursal y bodega activas.
     */
    public function resolverUbicacionParaOrden(Empresa $empresa, array $orderPayload): ?ShopifyLocation
    {
        $orderLocationId = $orderPayload['location_id'] ?? null;

        // Si no viene en la raíz, buscar en fulfillments
        if (empty($orderLocationId) && !empty($orderPayload['fulfillments'])) {
            foreach ($orderPayload['fulfillments'] as $fulfillment) {
                if (!empty($fulfillment['location_id'])) {
                    $orderLocationId = $fulfillment['location_id'];
                    break;
                }
            }
        }

        // 1. Intentar resolver por el location_id específico de la orden
        if (!empty($orderLocationId)) {
            $loc = ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('shopify_location_id', $orderLocationId)
                ->whereNotNull('id_sucursal')
                ->whereNotNull('id_bodega')
                ->first();

            if ($loc) {
                return $loc;
            }
        }

        // 2. Ubicación predeterminada para ventas online
        $locDefault = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('es_default', true)
            ->whereNotNull('id_sucursal')
            ->whereNotNull('id_bodega')
            ->first();

        if ($locDefault) {
            return $locDefault;
        }

        // 3. Fallback: primera ubicación válidamente mapeada
        return ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->whereNotNull('id_sucursal')
            ->whereNotNull('id_bodega')
            ->first();
    }

    /**
     * Obtiene el mapeo de Shopify activo para una bodega local.
     */
    public function obtenerLocationPorBodega(int $empresaId, int $bodegaId): ?ShopifyLocation
    {
        return ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->where('id_bodega', $bodegaId)
            ->where('sincronizar_stock', true)
            ->first();
    }
}
