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

            // Si después de buscar no tiene sucursal vinculada, auto-crear la sucursal y bodega
            if (empty($mapping->id_sucursal)) {
                $this->crearSucursalYBodega($mapping, $empresa, $loc);
            }

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
    public function crearSucursalYBodega(ShopifyLocation $location, Empresa $empresa, array $locData = []): array
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
            $telefono = !empty($locData['phone']) ? $locData['phone'] : ($empresa->telefono ?? '');
            $direccion = trim(($locData['address1'] ?? '') . ' ' . ($locData['address2'] ?? ''));
            $direccionFinal = !empty($direccion) ? $direccion : ($empresa->direccion ?? '');
            $municipio = $locData['city'] ?? '';
            $departamento = $locData['province'] ?? '';
            $activo = isset($locData['active']) ? ($locData['active'] ? '1' : '0') : '1';

            $nuevaSucursal = Sucursal::create([
                'nombre' => $location->shopify_location_name,
                'telefono' => $telefono,
                'correo' => $empresa->correo ?? '',
                'direccion' => $direccionFinal,
                'municipio' => $municipio,
                'departamento' => $departamento,
                'tipo_establecimiento' => '02', // Sucursal/Agencia
                'activo' => $activo,
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

            // Inicializar inventario en 0 para productos activos de la empresa
            $productos = \App\Models\Inventario\Producto::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->whereIn('tipo', ['Producto', 'Compuesto'])
                ->get(['id']);

            if ($productos->isNotEmpty()) {
                $now = now();
                $rows = [];
                foreach ($productos as $prod) {
                    $rows[] = [
                        'id_bodega' => $nuevaBodega->id,
                        'id_producto' => $prod->id,
                        'stock' => 0,
                        'stock_minimo' => 0,
                        'stock_maximo' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    \App\Models\Inventario\Inventario::insert($chunk);
                }
            }

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
            Log::channel('shopify')->error('Error creando sucursal desde Shopify: ' . $e->getMessage());
            return [
                'success' => false,
                'mensaje' => 'Error al crear la sucursal: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Procesa la creación de una sucursal desde un payload de webhook de Shopify (locations/create).
     */
    public function crearSucursalDesdeShopifyPayload(array $locData, Empresa $empresa): array
    {
        $shopifyLocId = $locData['id'] ?? null;
        if (empty($shopifyLocId)) {
            return ['success' => false, 'mensaje' => 'Payload no contiene id de ubicación.'];
        }

        $shopifyLocName = $locData['name'] ?? 'Sucursal Shopify';
        $isActive = (bool)($locData['active'] ?? true);

        // 1. Buscar o crear mapping en shopify_locations
        $mapping = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('shopify_location_id', $shopifyLocId)
            ->first();

        if (!$mapping) {
            $mapping = new ShopifyLocation();
            $mapping->id_empresa = $empresa->id;
            $mapping->shopify_location_id = $shopifyLocId;
            $mapping->sincronizar_stock = true;
            $mapping->es_default = !ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('es_default', true)
                ->exists();
        }

        $mapping->shopify_location_name = $shopifyLocName;
        $mapping->shopify_active = $isActive;
        $mapping->save();

        // 2. Crear o vincular sucursal y bodega
        return $this->crearSucursalYBodega($mapping, $empresa, $locData);
    }

    /**
     * Procesa la actualización de una sucursal desde un webhook de Shopify (locations/update, locations/activate, locations/deactivate).
     */
    public function actualizarSucursalDesdeShopifyPayload(array $locData, Empresa $empresa, ?string $topic = null): array
    {
        $shopifyLocId = $locData['id'] ?? null;
        if (empty($shopifyLocId)) {
            return ['success' => false, 'mensaje' => 'Payload no contiene id de ubicación.'];
        }

        $mapping = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('shopify_location_id', $shopifyLocId)
            ->first();

        if (!$mapping) {
            // Si la ubicación no existía aún en SP, la creamos directamente
            return $this->crearSucursalDesdeShopifyPayload($locData, $empresa);
        }

        // Determinar estado activo según topic o payload
        $isActive = (bool)($locData['active'] ?? true);
        if ($topic === 'locations/deactivate') {
            $isActive = false;
        } elseif ($topic === 'locations/activate') {
            $isActive = true;
        }

        $nuevoNombre = $locData['name'] ?? $mapping->shopify_location_name;
        $mapping->shopify_location_name = $nuevoNombre;
        $mapping->shopify_active = $isActive;
        $mapping->save();

        // Buscar la sucursal vinculada
        $sucursal = null;
        if ($mapping->id_sucursal) {
            $sucursal = Sucursal::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->find($mapping->id_sucursal);
        }

        if (!$sucursal) {
            $sucursal = Sucursal::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('shopify_location_id', $shopifyLocId)
                ->first();
        }

        if (!$sucursal) {
            // Si el mapping existía pero no la sucursal, la creamos
            return $this->crearSucursalYBodega($mapping, $empresa, $locData);
        }

        // Actualizar datos de la sucursal
        $nombreAnterior = $sucursal->nombre;
        $sucursal->nombre = $nuevoNombre;
        $sucursal->activo = $isActive ? '1' : '0';

        if (!empty($locData['phone'])) {
            $sucursal->telefono = $locData['phone'];
        }

        $direccion = trim(($locData['address1'] ?? '') . ' ' . ($locData['address2'] ?? ''));
        if (!empty($direccion)) {
            $sucursal->direccion = $direccion;
        }

        if (!empty($locData['city'])) {
            $sucursal->municipio = $locData['city'];
        }

        if (!empty($locData['province'])) {
            $sucursal->departamento = $locData['province'];
        }

        $sucursal->save();

        // Si el nombre de la sucursal cambió, actualizar también el nombre de la bodega por defecto
        if ($nombreAnterior !== $nuevoNombre) {
            Bodega::withoutGlobalScope('empresa')
                ->where('id_sucursal', $sucursal->id)
                ->where(function ($q) use ($nombreAnterior) {
                    $q->where('nombre', 'Bodega ' . $nombreAnterior)
                      ->orWhere('nombre', $nombreAnterior);
                })
                ->update(['nombre' => 'Bodega ' . $nuevoNombre]);
        }

        // Si se desactivó la sucursal, desactivar también sus bodegas
        if (!$isActive) {
            Bodega::withoutGlobalScope('empresa')
                ->where('id_sucursal', $sucursal->id)
                ->update(['activo' => '0']);
        } elseif ($isActive && $sucursal->wasChanged('activo')) {
            // Si se reactivó, reactivar la bodega vinculada a la ubicación
            if ($mapping->id_bodega) {
                Bodega::withoutGlobalScope('empresa')
                    ->where('id', $mapping->id_bodega)
                    ->update(['activo' => '1']);
            }
        }

        return [
            'success' => true,
            'mensaje' => "Sucursal '{$sucursal->nombre}' actualizada exitosamente desde Shopify.",
            'sucursal' => $sucursal,
            'location' => $mapping
        ];
    }

    /**
     * Procesa la eliminación de una sucursal desde un webhook de Shopify (locations/delete).
     * Si la sucursal tiene historial (ventas, compras, traslados, usuarios o stock > 0), se desactiva
     * de forma segura para preservar integridad de datos. Si no tiene historial, se elimina limpiamente.
     */
    public function eliminarSucursalDesdeShopify($shopifyLocationId, Empresa $empresa): array
    {
        if (empty($shopifyLocationId)) {
            return ['success' => false, 'mensaje' => 'Identificador de ubicación no proporcionado.'];
        }

        $mapping = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('shopify_location_id', $shopifyLocationId)
            ->first();

        $sucursal = null;
        if ($mapping && $mapping->id_sucursal) {
            $sucursal = Sucursal::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->find($mapping->id_sucursal);
        }

        if (!$sucursal) {
            $sucursal = Sucursal::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('shopify_location_id', $shopifyLocationId)
                ->first();
        }

        if (!$sucursal) {
            if ($mapping) {
                $mapping->delete();
            }
            return [
                'success' => true,
                'mensaje' => 'La ubicación de Shopify fue eliminada y no tenía sucursal vinculada en SmartPyme.',
                'accion' => 'mapping_eliminado'
            ];
        }

        // Evaluar si tiene transacciones o historial
        $bodegaIds = Bodega::withoutGlobalScope('empresa')
            ->where('id_sucursal', $sucursal->id)
            ->pluck('id');

        $tieneVentas = \App\Models\Ventas\Venta::where('id_sucursal', $sucursal->id)->exists();
        $tieneCompras = \App\Models\Compras\Compra::where('id_sucursal', $sucursal->id)->exists();
        $tieneTraslados = \App\Models\Inventario\Traslados\Traslado::where('id_sucursal_origen', $sucursal->id)
            ->orWhere('id_sucursal_destino', $sucursal->id)->exists();
        $tieneUsuarios = \App\Models\User::where('id_sucursal', $sucursal->id)->exists();
        $tieneStock = \App\Models\Inventario\Inventario::whereIn('id_bodega', $bodegaIds)
            ->where('stock', '>', 0)->exists();

        $tieneHistorial = $tieneVentas || $tieneCompras || $tieneTraslados || $tieneUsuarios || $tieneStock;

        DB::beginTransaction();
        try {
            if ($tieneHistorial) {
                // Desactivación segura para proteger histórico
                $sucursal->activo = '0';
                $sucursal->shopify_location_id = null;
                $sucursal->save();

                Bodega::withoutGlobalScope('empresa')
                    ->where('id_sucursal', $sucursal->id)
                    ->update(['activo' => '0']);

                if ($mapping) {
                    $mapping->delete();
                }

                DB::commit();

                return [
                    'success' => true,
                    'mensaje' => "La sucursal '{$sucursal->nombre}' tiene historial transaccional, por lo que se desactivó y desvinculó de Shopify para proteger los registros.",
                    'accion' => 'desactivada'
                ];
            } else {
                // Eliminación física completa (no hay historial)
                \App\Models\Inventario\Inventario::whereIn('id_bodega', $bodegaIds)->delete();
                Bodega::withoutGlobalScope('empresa')->where('id_sucursal', $sucursal->id)->delete();
                $sucursal->delete();

                if ($mapping) {
                    $mapping->delete();
                }

                DB::commit();

                return [
                    'success' => true,
                    'mensaje' => "La sucursal '{$sucursal->nombre}' y su bodega fueron eliminadas exitosamente.",
                    'accion' => 'eliminada'
                ];
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('shopify')->error('Error al eliminar sucursal desde Shopify: ' . $e->getMessage());
            return [
                'success' => false,
                'mensaje' => 'Error al procesar la eliminación de la sucursal: ' . $e->getMessage()
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
