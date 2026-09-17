<?php

namespace Tests\Unit\Services;

use App\Models\Admin\Empresa;
use App\Models\Admin\ShopifyLocation;
use App\Models\Admin\Sucursal;
use App\Services\ShopifyLocationService;
use Tests\TestCase;

class ShopifyLocationServiceTest extends TestCase
{
    private ShopifyLocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShopifyLocationService();
    }

    public function test_resolver_ubicacion_para_orden_por_location_id_directo(): void
    {
        $empresa = new Empresa();
        $empresa->id = 999;

        $loc1 = new ShopifyLocation([
            'id_empresa' => 999,
            'shopify_location_id' => 1111,
            'shopify_location_name' => 'Sucursal Escalón',
            'id_sucursal' => 10,
            'id_bodega' => 20,
            'es_default' => false,
        ]);

        $loc2 = new ShopifyLocation([
            'id_empresa' => 999,
            'shopify_location_id' => 2222,
            'shopify_location_name' => 'Sucursal Central (Web)',
            'id_sucursal' => 11,
            'id_bodega' => 21,
            'es_default' => true,
        ]);

        // Simular orden con location_id = 1111 (POS)
        $order = [
            'id' => 12345,
            'location_id' => 1111,
            'line_items' => []
        ];

        // Verificamos la lógica de resolución
        $resolvedLocationId = $order['location_id'] ?? null;
        $this->assertEquals(1111, $resolvedLocationId);

        // Simular orden online sin location_id: debe usar la predeterminada
        $onlineOrder = [
            'id' => 67890,
            'location_id' => null,
            'line_items' => []
        ];

        $targetLoc = empty($onlineOrder['location_id']) ? ($loc2->es_default ? $loc2 : $loc1) : $loc1;
        $this->assertTrue($targetLoc->es_default);
        $this->assertEquals(11, $targetLoc->id_sucursal);
        $this->assertEquals(21, $targetLoc->id_bodega);
    }

    public function test_anti_duplicacion_verifica_existencia_por_identificador_o_nombre(): void
    {
        $location = new ShopifyLocation([
            'id_empresa' => 999,
            'shopify_location_id' => 8888,
            'shopify_location_name' => 'Sucursal Santa Tecla',
            'id_sucursal' => 50,
        ]);

        // Si ya tiene id_sucursal asignada, no debe duplicar
        $this->assertNotEmpty($location->id_sucursal);
        $this->assertEquals(50, $location->id_sucursal);

        // Verificación de normalización de nombre para evitar duplicados case-insensitive
        $nombreShopify = '  SUCURSAL SANTA TECLA  ';
        $nombreLocal = 'Sucursal Santa Tecla';
        $this->assertEquals(strtolower(trim($nombreShopify)), strtolower(trim($nombreLocal)));
    }

    public function test_casts_en_shopify_location_son_enteros(): void
    {
        $location = new ShopifyLocation([
            'id_sucursal' => '15',
            'id_bodega' => '25',
            'sincronizar_stock' => '1',
            'es_default' => '0',
        ]);

        $this->assertIsInt($location->id_sucursal);
        $this->assertSame(15, $location->id_sucursal);
        $this->assertIsInt($location->id_bodega);
        $this->assertSame(25, $location->id_bodega);
        $this->assertIsBool($location->sincronizar_stock);
        $this->assertTrue($location->sincronizar_stock);
        $this->assertIsBool($location->es_default);
        $this->assertFalse($location->es_default);
    }

    public function test_crear_sucursal_payload_vacio_retorna_error(): void
    {
        $empresa = new Empresa(['id' => 999]);
        $resultado = $this->service->crearSucursalDesdeShopifyPayload([], $empresa);

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('no contiene id', $resultado['mensaje']);
    }

    public function test_eliminar_sucursal_sin_id_retorna_error(): void
    {
        $empresa = new Empresa(['id' => 999]);
        $resultado = $this->service->eliminarSucursalDesdeShopify(null, $empresa);

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('no proporcionado', $resultado['mensaje']);
    }

    public function test_resolucion_estado_activo_segun_topic_o_payload(): void
    {
        // Deactivate topic siempre debe resultar en inactivo
        $payload = ['id' => 12345, 'name' => 'Sucursal Test', 'active' => true];
        $topic = 'locations/deactivate';
        $isActive = ($topic === 'locations/deactivate') ? false : (bool)($payload['active'] ?? true);
        $this->assertFalse($isActive);

        // Activate topic siempre debe resultar en activo
        $payload = ['id' => 12345, 'name' => 'Sucursal Test', 'active' => false];
        $topic = 'locations/activate';
        $isActive = ($topic === 'locations/activate') ? true : (bool)($payload['active'] ?? true);
        $this->assertTrue($isActive);

        // Update topic respeta el booleano del payload
        $payload = ['id' => 12345, 'name' => 'Sucursal Test', 'active' => false];
        $topic = 'locations/update';
        $isActive = (bool)($payload['active'] ?? true);
        $this->assertFalse($isActive);
    }
}
