<?php

namespace Tests\Unit\Services;

use App\Http\Controllers\Api\Ventas\VentasController;
use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Services\ShopifyVentaConsolidacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopifyVentaConsolidacionServiceTest extends TestCase
{
    private Empresa $empresa;
    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('empresas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->decimal('iva', 8, 2)->default(13);
            $table->boolean('shopify_sync_bidirectional')->default(false);
            $table->string('shopify_store_url')->nullable();
            $table->string('shopify_access_token')->nullable();
            $table->string('shopify_consumer_secret')->nullable();
            $table->string('shopify_client_id')->nullable();
            $table->string('woocommerce_api_key')->nullable();
            $table->string('shopify_status')->nullable();
            $table->text('custom_empresa')->nullable();
            $table->timestamps();
        });

        Schema::create('empresa_configuracion', function ($table) {
            $table->id();
            $table->unsignedInteger('empresa_id');
            $table->string('pais', 3)->default('SV');
            $table->string('modulo', 50);
            $table->json('configuracion');
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->string('shopify_status')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamp('ultimo_login')->nullable();
            $table->timestamps();
        });

        Schema::create('categorias', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->boolean('enable')->default(true);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_categoria')->nullable();
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('tipo')->nullable();
            $table->decimal('precio', 10, 4)->default(0);
            $table->decimal('costo', 10, 2)->default(0);
            $table->boolean('enable')->default(true);
            $table->boolean('control_stock')->default(true);
            $table->boolean('inventario_por_lotes')->default(false);
            $table->boolean('syncing_from_shopify')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sucursal_bodegas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->timestamps();
        });

        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('kardexs', function ($table) {
            $table->id();
            $table->date('fecha')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->unsignedBigInteger('id_inventario')->nullable();
            $table->string('detalle')->nullable();
            $table->string('referencia')->nullable();
            $table->decimal('entrada_cantidad', 10, 2)->nullable();
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->decimal('entrada_valor', 10, 2)->nullable();
            $table->decimal('salida_cantidad', 10, 2)->nullable();
            $table->decimal('precio_unitario', 10, 2)->nullable();
            $table->decimal('salida_valor', 10, 2)->nullable();
            $table->decimal('total_cantidad', 10, 2)->default(0);
            $table->decimal('total_valor', 10, 2)->default(0);
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->timestamps();
        });

        Schema::create('ventas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('estado')->default('Pagada');
            $table->string('correlativo')->nullable();
            $table->string('sello_mh')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('gravada', 12, 2)->default(0);
            $table->decimal('exenta', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('monto_pago', 12, 2)->default(0);
            $table->integer('cotizacion')->default(0);
            $table->string('referencia_shopify')->nullable();
            $table->text('observaciones_shopify')->nullable();
            $table->timestamps();
        });

        Schema::create('detalles_venta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio', 12, 4)->default(0);
            $table->decimal('precio_sin_iva', 12, 4)->default(0);
            $table->decimal('precio_con_iva', 12, 4)->default(0);
            $table->decimal('descuento', 12, 4)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('gravada', 12, 2)->default(0);
            $table->decimal('exenta', 12, 2)->default(0);
            $table->decimal('no_sujeta', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('costo', 12, 2)->default(0);
            $table->decimal('total_costo', 12, 2)->default(0);
            $table->decimal('cuenta_a_terceros', 12, 2)->default(0);
            $table->string('descripcion')->nullable();
            $table->unsignedBigInteger('id_vendedor')->nullable();
            $table->timestamps();
        });

        $this->empresa = Empresa::create([
            'nombre' => 'Test',
            'iva' => 13,
            'shopify_status' => null,
        ]);

        $this->usuario = User::create([
            'id_empresa' => $this->empresa->id,
            'id_bodega' => 1,
            'name' => 'Cajero',
        ]);
    }

    public function test_consolidar_alinea_lineas_envio_total_y_stock(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'envios',
            'id_empresa' => $this->empresa->id,
            'enable' => 1,
        ]);

        $calcetines = Producto::create([
            'nombre' => 'Calcetines',
            'tipo' => 'Producto',
            'id_empresa' => $this->empresa->id,
            'shopify_variant_id' => 111,
            'precio' => 12,
            'costo' => 1,
        ]);
        $case = Producto::create([
            'nombre' => 'case s25 ultra',
            'tipo' => 'Producto',
            'id_empresa' => $this->empresa->id,
            'shopify_variant_id' => 222,
            'precio' => 20,
            'costo' => 1,
        ]);
        $envioViejo = Producto::create([
            'nombre' => 'cambio de envio',
            'tipo' => 'Servicio',
            'id_categoria' => $categoria->id,
            'id_empresa' => $this->empresa->id,
            'precio' => 3,
        ]);

        Inventario::create(['id_producto' => $calcetines->id, 'id_bodega' => 1, 'stock' => 10]);
        Inventario::create(['id_producto' => $case->id, 'id_bodega' => 1, 'stock' => 7]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'id_bodega' => 1,
            'id_usuario' => $this->usuario->id,
            'estado' => 'Pagada',
            'correlativo' => '27',
            'referencia_shopify' => 'SHOPIFY-17148162310514',
            'total' => 50,
            'descuento' => 0,
        ]);

        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $calcetines->id,
            'descripcion' => 'Calcetines',
            'cantidad' => 9,
            'precio' => 1,
            'gravada' => 1,
            'iva' => 1,
            'total' => 1,
        ]);
        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $case->id,
            'descripcion' => 'case s25 ultra',
            'cantidad' => 1,
            'precio' => 20,
            'gravada' => 20,
            'iva' => 2.6,
            'total' => 22.6,
        ]);
        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $envioViejo->id,
            'descripcion' => 'cambio de envio',
            'cantidad' => 1,
            'precio' => 3,
            'exenta' => 3,
            'total' => 3,
        ]);
        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $envioViejo->id,
            'descripcion' => 'cambio de envio',
            'cantidad' => 1,
            'precio' => 3,
            'exenta' => 3,
            'total' => 3,
        ]);

        $resultado = app(ShopifyVentaConsolidacionService::class)->consolidar(
            $venta,
            $this->pedidoShopify(),
            $this->usuario
        );

        $venta->refresh();
        $this->assertSame('ok', $resultado['status']);
        $this->assertEquals(102.00, (float) $venta->total);
        $this->assertEquals(9.73, (float) $venta->descuento);

        $productos = $venta->detalles()->where('id_producto', $calcetines->id)->get();
        $this->assertCount(1, $productos);
        $this->assertEquals(9, (float) $productos[0]->cantidad);
        $this->assertEquals(85.84, (float) $productos[0]->gravada);
        $this->assertEquals(11.16, (float) $productos[0]->iva);

        $this->assertEquals(0, $venta->detalles()->where('id_producto', $case->id)->count());
        $this->assertEquals(8, (float) Inventario::where('id_producto', $case->id)->value('stock'));
        $this->assertEquals(10, (float) Inventario::where('id_producto', $calcetines->id)->value('stock'));

        $envios = $venta->detalles()->where('descripcion', 'cargo express')->get();
        $this->assertCount(1, $envios);
        $this->assertEquals(1, Producto::where('nombre', 'cargo express')->where('tipo', 'Servicio')->count());
        $this->assertEquals(0, $venta->detalles()->where('descripcion', 'cambio de envio')->count());
    }

    public function test_consolidar_no_toca_una_venta_con_sello(): void
    {
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'sello_mh' => 'SELLO',
            'total' => 40,
            'referencia_shopify' => 'SHOPIFY-1',
        ]);
        Detalle::create([
            'id_venta' => $venta->id,
            'cantidad' => 1,
            'precio' => 40,
            'total' => 40,
            'descripcion' => 'queda',
        ]);

        $resultado = app(ShopifyVentaConsolidacionService::class)->consolidar($venta, $this->pedidoShopify(), $this->usuario);

        $this->assertSame('ignored', $resultado['status']);
        $venta->refresh();
        $this->assertEquals(40, (float) $venta->total);
        $this->assertEquals(1, $venta->detalles()->count());
    }

    public function test_orders_updated_consolida_lineas_y_envio(): void
    {
        $this->empresa->update([
            'woocommerce_api_key' => 'token_webhook_123',
            'shopify_status' => 'connected',
        ]);
        User::where('id', $this->usuario->id)->update(['shopify_status' => 'connected']);

        $categoria = Categoria::create([
            'nombre' => 'envios',
            'id_empresa' => $this->empresa->id,
            'enable' => 1,
        ]);
        $envio = Producto::create([
            'nombre' => 'cargo express',
            'tipo' => 'Servicio',
            'id_categoria' => $categoria->id,
            'id_empresa' => $this->empresa->id,
            'precio' => 5,
        ]);
        $calcetines = Producto::create([
            'nombre' => 'Calcetines',
            'tipo' => 'Producto',
            'id_empresa' => $this->empresa->id,
            'shopify_variant_id' => 111,
            'precio' => 12,
            'costo' => 1,
        ]);
        Inventario::create(['id_producto' => $calcetines->id, 'id_bodega' => 1, 'stock' => 10]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'id_bodega' => 1,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-17148162310514',
            'total' => 1,
        ]);
        $venta->created_at = now()->subMinute();
        $venta->save();
        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $calcetines->id,
            'cantidad' => 1,
            'precio' => 1,
            'gravada' => 1,
            'iva' => 0,
            'total' => 1,
            'descripcion' => 'Calcetines',
        ]);

        $request = Request::create('/api/webhook/shopify/token_webhook_123/orders/updated', 'POST', $this->pedidoShopify());
        $response = app(ShopifyController::class)->procesarVentaActualizada('token_webhook_123', $request);

        $this->assertEquals(200, $response->getStatusCode());
        $venta->refresh();
        $this->assertEquals(102.00, (float) $venta->total);
        $this->assertEquals(9, (float) $venta->detalles()->where('id_producto', $calcetines->id)->value('cantidad'));
        $this->assertEquals(1, $venta->detalles()->where('descripcion', 'cargo express')->count());
        $this->assertEquals(1, Producto::where('nombre', 'cargo express')->count());
        $this->assertEquals($envio->id, (int) $venta->detalles()->where('descripcion', 'cargo express')->value('id_producto'));
        $this->assertEquals(2, (float) Inventario::where('id_producto', $calcetines->id)->value('stock'));
    }

    public function test_endpoint_consolida_la_venta_de_la_empresa(): void
    {
        $this->empresa->update([
            'shopify_status' => 'connected',
            'shopify_store_url' => 'https://tienda.myshopify.com',
            'shopify_consumer_secret' => 'shpat_test',
        ]);
        Auth::login($this->usuario);

        $calcetines = Producto::create([
            'nombre' => 'Calcetines',
            'tipo' => 'Producto',
            'id_empresa' => $this->empresa->id,
            'shopify_variant_id' => 111,
            'precio' => 12,
            'costo' => 1,
        ]);
        Inventario::create(['id_producto' => $calcetines->id, 'id_bodega' => 1, 'stock' => 10]);
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'id_bodega' => 1,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-17148162310514',
            'total' => 1,
        ]);

        Http::fake([
            'https://tienda.myshopify.com/admin/api/2024-01/orders/17148162310514.json' => Http::response([
                'order' => $this->pedidoShopify(),
            ], 200),
        ]);

        $response = app(VentasController::class)->consolidarShopify($venta->id);

        $this->assertEquals(200, $response->getStatusCode());
        $venta->refresh();
        $this->assertEquals(102.00, (float) $venta->total);
    }

    public function test_endpoint_rechaza_venta_ajena_desconectada_o_sin_referencia(): void
    {
        Auth::login($this->usuario);
        $ajena = Empresa::create(['nombre' => 'Otra', 'iva' => 13]);
        $ventaAjena = Venta::create([
            'id_empresa' => $ajena->id,
            'referencia_shopify' => 'SHOPIFY-9',
            'total' => 8,
        ]);

        $this->assertEquals(404, app(VentasController::class)->consolidarShopify($ventaAjena->id)->getStatusCode());

        $sinReferencia = Venta::create([
            'id_empresa' => $this->empresa->id,
            'total' => 8,
        ]);
        $this->assertEquals(422, app(VentasController::class)->consolidarShopify($sinReferencia->id)->getStatusCode());
        $sinReferencia->refresh();
        $this->assertEquals(8, (float) $sinReferencia->total);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'referencia_shopify' => 'SHOPIFY-17148162310514',
            'total' => 8,
        ]);
        $this->assertEquals(422, app(VentasController::class)->consolidarShopify($venta->id)->getStatusCode());
        $venta->refresh();
        $this->assertEquals(8, (float) $venta->total);
    }

    private function pedidoShopify(): array
    {
        return [
            'id' => 17148162310514,
            'financial_status' => 'paid',
            'taxes_included' => true,
            'created_at' => '2026-09-24T10:47:48-06:00',
            'current_total_price' => '102.00',
            'current_total_tax' => '11.16',
            'current_subtotal_price' => '97.00',
            'total_shipping_price_set' => ['shop_money' => ['amount' => '5.00']],
            'line_items' => [[
                'id' => 1,
                'title' => 'Calcetines',
                'price' => '12.00',
                'quantity' => 9,
                'current_quantity' => 9,
                'variant_id' => 111,
                'product_id' => 999,
                'tax_lines' => [['price' => '11.16']],
                'discount_allocations' => [
                    ['amount' => '1.00'],
                    ['amount' => '10.00'],
                ],
            ]],
            'shipping_lines' => [[
                'title' => 'cargo express',
                'price' => '5.00',
                'discounted_price' => '5.00',
            ]],
        ];
    }
}
