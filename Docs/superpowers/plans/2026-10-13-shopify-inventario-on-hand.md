# Inventario Shopify por on_hand Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un ajuste de cantidad hecho en Shopify actualiza la bodega correspondiente en SmartPyme, y un pedido pendiente no la mueve.

**Architecture:** SmartPyme guarda el stock físico, así que la cantidad de Shopify que le corresponde es `on_hand`, no `available`. `available` ya restó los pedidos sin entregar (`committed`). Al llegar `inventory_levels/update` se leen las tres cantidades por GraphQL (API 2026-10) para esa ubicación y, si `on_hand` difiere del stock local, se copia `on_hand`. La salida escribe `on_hand` con `inventorySetQuantities` y `changeFromQuantity`, para no pisar un cambio más nuevo de la tienda. El candado de 120 segundos del producto no participa en este webhook.

**Tech Stack:** Laravel, PHPUnit, Shopify Admin API 2026-10.

## Global Constraints

- La versión de estas llamadas es `2026-10`. `ShopifyApiClient` hoy tiene `2024-01` escrito; `graphql()` recibe la versión y el inventario pasa `2026-10` sin mover el resto de REST.
- Stock local de una bodega = `on_hand` de su ubicación. No sumar cotizaciones para explicar una baja.
- Si la lectura GraphQL falla, responder 500 y no copiar `available`.
- `inventory_levels/update` no consulta `sync_lock_{producto}` ni se aborta por ese candado.
- `products/update` no mueve stock.
- Una sola escritura de salida: `ShopifyInventarioCantidad::fijarOnHand`. Los `inventory_levels/set.json` de stock pasan por ahí.
- Sin dependencia nueva. Sin revivir código de otra rama.
- No modificar ventas ni stock histórico al implementar. La prueba manual es un ajuste nuevo en Shopify.
- Los commits los hace quien ejecute el plan, cuando se lo pidan.

---

### Task 1: Cliente GraphQL y lectura de cantidades

**Files:**
- Modify: `Backend/app/Services/ShopifyApiClient.php`
- Create: `Backend/app/Services/ShopifyInventarioCantidad.php`
- Test: `Backend/tests/Unit/Services/ShopifyInventarioCantidadTest.php`

**Interfaces:**
- Consumes: `ShopifyApiClient` construido como hoy (`store url`, token, `ShopifyTokenService`, `Empresa`)
- Produces:
  - `ShopifyApiClient::graphql(string $query, array $variables = [], string $version = '2026-10'): array`
  - `ShopifyInventarioCantidad::leer(ShopifyApiClient $client, int $inventoryItemId, int $locationId): ?array` con claves `on_hand`, `available`, `committed` (int). `null` si la respuesta no trae el nivel.
  - `ShopifyInventarioCantidad::API_VERSION = '2026-10'`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\ShopifyApiClient;
use App\Services\ShopifyInventarioCantidad;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyInventarioCantidadTest extends TestCase
{
    public function test_leer_devuelve_on_hand_available_y_committed(): void
    {
        Http::fake([
            'https://tienda.myshopify.com/admin/api/2026-10/graphql.json' => Http::response([
                'data' => [
                    'inventoryItem' => [
                        'inventoryLevel' => [
                            'quantities' => [
                                ['name' => 'on_hand', 'quantity' => 15],
                                ['name' => 'available', 'quantity' => 12],
                                ['name' => 'committed', 'quantity' => 3],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new ShopifyApiClient('https://tienda.myshopify.com', 'shpat_test');
        $cantidades = (new ShopifyInventarioCantidad())->leer($client, 64416012992882, 116424671602);

        $this->assertSame(['on_hand' => 15, 'available' => 12, 'committed' => 3], $cantidades);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/ShopifyInventarioCantidadTest.php`
Expected: FAIL. La clase o `graphql()` no existen.

- [ ] **Step 3: Write minimal implementation**

En `ShopifyApiClient`, método público. No cambiar `$apiVersion` ni `request()`:

```php
public function graphql(string $query, array $variables = [], string $version = '2026-10'): array
{
    $token = $this->resolveToken();
    if (empty($token)) {
        throw new \Exception('No se pudo obtener un access token de Shopify.');
    }

    $url = "{$this->shopDomain}/admin/api/{$version}/graphql.json";
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'X-Shopify-Access-Token' => $token,
    ])->post($url, ['query' => $query, 'variables' => $variables]);

    if ($response->status() === 401 && $this->empresa && !empty($this->empresa->shopify_client_id)) {
        $token = $this->resolveToken(true);
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Shopify-Access-Token' => $token,
        ])->post($url, ['query' => $query, 'variables' => $variables]);
    }

    if (!$response->successful()) {
        throw new \Exception('Shopify GraphQL HTTP ' . $response->status());
    }

    $body = $response->json();
    if (!empty($body['errors'])) {
        throw new \Exception('Shopify GraphQL: ' . json_encode($body['errors']));
    }

    return $body['data'] ?? [];
}
```

Revisar los headers reales de `request()` y usar los mismos (el token ya sale de `resolveToken()`). Si `request()` loguea con `ShopifyHelper`, este método también deja una línea `Enviando petición a Shopify API` con la URL GraphQL.

`ShopifyInventarioCantidad`:

```php
<?php

namespace App\Services;

class ShopifyInventarioCantidad
{
    public const API_VERSION = '2026-10';

    public function leer(ShopifyApiClient $client, int $inventoryItemId, int $locationId): ?array
    {
        $data = $client->graphql(
            'query Nivel($id: ID!, $locationId: ID!) {
                inventoryItem(id: $id) {
                    inventoryLevel(locationId: $locationId) {
                        quantities(names: ["on_hand", "available", "committed"]) { name quantity }
                    }
                }
            }',
            [
                'id' => "gid://shopify/InventoryItem/{$inventoryItemId}",
                'locationId' => "gid://shopify/Location/{$locationId}",
            ],
            self::API_VERSION
        );

        $filas = $data['inventoryItem']['inventoryLevel']['quantities'] ?? null;
        if (!is_array($filas)) {
            return null;
        }

        $out = ['on_hand' => 0, 'available' => 0, 'committed' => 0];
        foreach ($filas as $fila) {
            $nombre = $fila['name'] ?? '';
            if (array_key_exists($nombre, $out)) {
                $out[$nombre] = (int) $fila['quantity'];
            }
        }

        return $out;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/ShopifyInventarioCantidadTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/ShopifyApiClient.php Backend/app/Services/ShopifyInventarioCantidad.php Backend/tests/Unit/Services/ShopifyInventarioCantidadTest.php
git commit -m "feat: leer on_hand, available y committed de una ubicación Shopify"
```

---

### Task 2: El webhook de inventario copia on_hand y no obedece el candado

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Webhook/ShopifyController.php` (`procesarInventarioActualizadoShopify`, cerca de la línea 590)
- Modify: `Backend/tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php`
- Delete usage: `stockLocalSegunShopify()` queda sin llamadores en este webhook. Borrar el método si nadie más lo usa.

**Interfaces:**
- Consumes: `ShopifyInventarioCantidad::leer()`
- Produces: `procesarInventarioActualizadoShopify` aplica `on_hand` de esa bodega. Responde 500 si `leer()` lanza o devuelve null. Responde 200 sin escribir si `on_hand` ya es el stock local.

Comportamiento que reemplaza al bloque actual (`isLocked`, igualdad contra `available`, `stockLocalSegunShopify`):

```php
$cantidades = app(\App\Services\ShopifyInventarioCantidad::class)->leer($client, (int) $inventoryItemId, (int) $locationId);
if ($cantidades === null) {
    return response()->json(['status' => 'error', 'message' => 'No se pudo leer on_hand'], 500);
}

$onHand = $cantidades['on_hand'];
$stockLocal = $inventario ? (float) $inventario->stock : 0.0;

ShopifyHelper::log('inventory_levels/update: cantidades', [
    'producto_id' => $producto->id,
    'bodega_id' => $bodegaId,
    'on_hand' => $onHand,
    'available' => $cantidades['available'],
    'committed' => $cantidades['committed'],
    'stock_smartpyme' => $stockLocal,
]);

if ((float) $onHand === $stockLocal) {
    return response()->json(['status' => 'success', 'message' => 'Stock ya sincronizado'], 200);
}

$this->actualizarInventario(
    $producto->id,
    $onHand,
    $bodegaId,
    $usuario->id,
    ['origen' => 'shopify', 'tipo' => 'ajuste_inventario', 'stock_anterior' => $stockLocal, 'location_id' => $locationId]
);

return response()->json(['status' => 'success', 'message' => 'Inventario actualizado'], 200);
```

El cliente se arma igual que en `sincronizarStockDesdeShopifyMultiSucursal` (`shopify_store_url`, `shopify_consumer_secret`, `ShopifyTokenService`, `$empresa`). Quitar el `if` de `isLocked` / `shopify_syncing_inv` al inicio de este método. Quitar las dos llamadas a `lockSync` que quedaran en este método. `actualizarInventario` sigue poniendo `shopify_syncing_inv_{id}` durante la escritura para que el observer no devuelva el número a Shopify.

`shopify_syncing_inv` durante la escritura se mantiene. El candado `sync_lock_{id}` de 120 segundos no se consulta aquí.

- [ ] **Step 1: Write the failing test**

En `ShopifyStockActualizarTest`, reemplazar la expectativa de los tests de baja por cotización. Caso nuevo, el de las 10:04:

```php
public function test_ajuste_de_shopify_copia_on_hand_aunque_el_candado_este_activo(): void
{
    // Mismas tablas que test_ajuste_manual_de_shopify_baja_el_stock_si_no_hay_cotizacion
    // (empresas, productos, inventario, ventas, detalles_venta, sucursal_bodegas, kardexs).
    \Illuminate\Support\Facades\Cache::put('sync_lock_1', true, 120);

    $producto = Producto::create([
        'nombre' => 'soda de prueba sin azucar',
        'id_empresa' => 1,
        'shopify_inventory_item_id' => 64416012992882,
        'precio' => 1,
        'costo' => 1,
    ]);
    Inventario::create(['id_producto' => $producto->id, 'id_bodega' => 689, 'stock' => 10]);

    $empresa = new Empresa();
    $empresa->id = 1;
    $empresa->shopify_store_url = 'https://tienda.myshopify.com';
    $empresa->shopify_consumer_secret = 'shpat_test';
    $usuario = new \App\Models\User();
    $usuario->id = 1;
    $usuario->id_bodega = 689;

    Http::fake([
        'https://tienda.myshopify.com/admin/api/2026-10/graphql.json' => Http::response([
            'data' => ['inventoryItem' => ['inventoryLevel' => ['quantities' => [
                ['name' => 'on_hand', 'quantity' => 15],
                ['name' => 'available', 'quantity' => 15],
                ['name' => 'committed', 'quantity' => 0],
            ]]]],
        ], 200),
    ]);

    $request = new Request([
        'inventory_item_id' => 64416012992882,
        'location_id' => 116424671602,
        'available' => 15,
    ]);

    $method = (new ReflectionClass(ShopifyController::class))->getMethod('procesarInventarioActualizadoShopify');
    $method->setAccessible(true);
    $response = $method->invoke($this->controller, $request, $empresa, $usuario);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('success', $response->getData(true)['status']);
    $this->assertEquals(15, (float) Inventario::where('id_bodega', 689)->value('stock'));
}
```

Segundo test en el mismo archivo:

```php
public function test_pedido_pendiente_no_mueve_el_stock_si_on_hand_no_cambio(): void
{
    // Mismas tablas. Stock local 11. Sin Cache::put del candado.
    // GraphQL: on_hand 11, available 9, committed 2.
    // Request available 9.
    // Assert status success y stock sigue 11.
}
```

El test viejo `test_baja_de_inventario_shopify_no_revienta_y_no_descuenta` y `test_ajuste_manual_de_shopify_baja_el_stock_si_no_hay_cotizacion` deben llamar GraphQL. Si no, `leer()` revienta por red. Apuntarlos al mismo `Http::fake`: el de cotización pasa a ser el de `on_hand` sin cambio; el manual pasa `on_hand` 9.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php --filter test_ajuste_de_shopify_copia_on_hand_aunque_el_candado_este_activo`
Expected: FAIL. El candado responde `ignored` y el stock sigue en 10.

- [ ] **Step 3: Write minimal implementation**

Sustituir el cuerpo de `procesarInventarioActualizadoShopify` desde el chequeo de candado hasta el return final por la lectura de `on_hand` de arriba. Resolver bodega como ya está (mapeo `ShopifyLocation`, si no hay, bodega del usuario) antes de leer, porque el log necesita `bodega_id`. Borrar `stockLocalSegunShopify` si `grep` ya no lo encuentra.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php tests/Unit/Services/ShopifyInventarioCantidadTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Http/Controllers/Api/Webhook/ShopifyController.php Backend/tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php
git commit -m "fix: el ajuste de Shopify copia on_hand por bodega"
```

---

### Task 3: products/update deja de mover stock

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Webhook/ShopifyController.php` (`sincronizarStockDesdeShopifyMultiSucursal`, cerca de la línea 1439)
- Test: `Backend/tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php`

**Interfaces:**
- Consumes: el argumento `$tipoMovimiento` que ya recibe el método
- Produces: con `actualizacion_shopify` el método vuelve sin escribir inventario. `inventario_inicial` sigue creando stock en bodegas vacías.

- [ ] **Step 1: Write the failing test**

Reescribir `test_actualizacion_de_producto_no_baja_el_stock_de_una_cotizacion` para que no dependa de una cotización: stock 10, Shopify diría 15, y después de `sincronizarStockDesdeShopifyMultiSucursal(..., 'actualizacion_shopify')` el stock sigue 10. No hace falta `Http::fake` si el método sale antes de la petición.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php --filter test_actualizacion_de_producto_no_baja_el_stock_de_una_cotizacion`
Expected: FAIL si el método todavía escribe, o PASS prematuro si ya salía por la guarda de baja. Forzar el caso en el que Shopify está por encima (15 > 10): hoy ese camino sí escribe. Expected de ese caso: FAIL, el stock pasa a 15.

- [ ] **Step 3: Write minimal implementation**

Al entrar al `if` de credenciales y mapeos, antes del `get('inventory_levels.json')`:

```php
if ($tipoMovimiento === 'actualizacion_shopify') {
    ShopifyHelper::log('products/update: el stock lo trae inventory_levels/update', [
        'producto_id' => $producto->id,
    ]);
    return;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Http/Controllers/Api/Webhook/ShopifyController.php Backend/tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php
git commit -m "fix: products/update ya no copia el stock de Shopify"
```

---

### Task 4: La salida escribe on_hand y compara la cantidad previa

**Files:**
- Modify: `Backend/app/Services/ShopifyInventarioCantidad.php`
- Modify: `Backend/app/Services/ShopifyStockService.php` (`actualizarSoloInventario`, los `inventory_levels/set.json` de `actualizarProductoPorId`, `actualizarProductoPorSku`, `crearNuevoProducto`)
- Modify: `Backend/app/Http/Controllers/Api/Webhook/ShopifyController.php` (`setInventoryLevelWithTracking`, cerca de la línea 2801)
- Modify: `Backend/app/Services/ShopifyConsolidationService.php` (los `inventory_levels/set.json`, cerca de 1224 y 1240)
- Modify: `Backend/app/Services/ShopifyExportService.php` (el `inventory_levels/set.json`, cerca de 336)
- Test: `Backend/tests/Unit/Services/ShopifyInventarioCantidadTest.php`

**Interfaces:**
- Consumes: `leer()` para conocer el `on_hand` actual
- Produces: `ShopifyInventarioCantidad::fijarOnHand(ShopifyApiClient $client, int $inventoryItemId, int $locationId, int $onHand): bool`
  - `true` si `inventorySetQuantities` no trae `userErrors`
  - `false` si Shopify rechaza la comparación (`changeFromQuantity`) o hay otro `userError`. No reintenta con un set absoluto.

Mutación:

```graphql
mutation Fijar($input: InventorySetQuantitiesInput!) {
  inventorySetQuantities(input: $input) {
    userErrors { field message code }
  }
}
```

Variables:

```php
[
    'input' => [
        'name' => 'on_hand',
        'reason' => 'correction',
        'referenceDocumentUri' => "smartpyme://inventario/{$inventoryItemId}/{$locationId}",
        'quantities' => [[
            'inventoryItemId' => "gid://shopify/InventoryItem/{$inventoryItemId}",
            'locationId' => "gid://shopify/Location/{$locationId}",
            'quantity' => $onHand,
            'changeFromQuantity' => $actual['on_hand'],
        ]],
    ],
]
```

`$actual` sale de `leer()`. Si `leer()` devuelve null, `fijarOnHand` devuelve false. Si `$actual['on_hand'] === $onHand`, devuelve true sin llamar a la mutación.

- [ ] **Step 1: Write the failing test**

```php
public function test_fijar_on_hand_envia_la_cantidad_previa_para_comparar(): void
{
    Http::fake([
        'https://tienda.myshopify.com/admin/api/2026-10/graphql.json' => Http::sequence()
            ->push(['data' => ['inventoryItem' => ['inventoryLevel' => ['quantities' => [
                ['name' => 'on_hand', 'quantity' => 11],
                ['name' => 'available', 'quantity' => 11],
                ['name' => 'committed', 'quantity' => 0],
            ]]]]], 200)
            ->push(['data' => ['inventorySetQuantities' => ['userErrors' => []]]], 200),
    ]);

    $client = new ShopifyApiClient('https://tienda.myshopify.com', 'shpat_test');
    $ok = (new ShopifyInventarioCantidad())->fijarOnHand($client, 64416012992882, 116424671602, 15);

    $this->assertTrue($ok);
    Http::assertSent(function ($request) {
        $input = $request['variables']['input'] ?? [];
        return ($input['name'] ?? null) === 'on_hand'
            && ($input['quantities'][0]['quantity'] ?? null) === 15
            && ($input['quantities'][0]['changeFromQuantity'] ?? null) === 11;
    });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/ShopifyInventarioCantidadTest.php --filter test_fijar_on_hand_envia_la_cantidad_previa_para_comparar`
Expected: FAIL. `fijarOnHand` no existe.

- [ ] **Step 3: Write minimal implementation**

Implementar `fijarOnHand` en `ShopifyInventarioCantidad`. En cada `inventory_levels/set.json` de los archivos de esta tarea, sustituir el `post` por:

```php
app(\App\Services\ShopifyInventarioCantidad::class)->fijarOnHand(
    $client,
    (int) $inventoryItemId,
    (int) $locationId,
    (int) round((float) $stockLocal)
);
```

`setInventoryLevelWithTracking` queda como delegación a `fijarOnHand` para no dejar dos escrituras. El nombre del parámetro deja de ser `$available`; pasa a ser el stock local que se publica como `on_hand`.

No cambiar precio, título, imágenes ni el alta del producto en `products.json`. Solo la línea que fija la cantidad.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/ShopifyInventarioCantidadTest.php tests/Unit/Http/Controllers/Api/Webhook/ShopifyStockActualizarTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/ShopifyInventarioCantidad.php Backend/app/Services/ShopifyStockService.php Backend/app/Services/ShopifyConsolidationService.php Backend/app/Services/ShopifyExportService.php Backend/app/Http/Controllers/Api/Webhook/ShopifyController.php Backend/tests/Unit/Services/ShopifyInventarioCantidadTest.php
git commit -m "feat: publicar el stock local como on_hand con comparación"
```

---

## Fuera de este plan

- Cambiar de golpe el REST que todavía llama a `2024-01` (`ShopifyApiClient::$apiVersion` y el GraphQL de traslados). El inventario nuevo ya sale por `2026-10`.
- Cambiar el candado `sync_lock` de precio, título o imágenes. Sigue sirviendo para `products/update`.
- Reconciliar la soda que ya quedó desfasada (bodegas en 10 u 11 contra un 15 de Shopify). Eso se comprueba con un ajuste nuevo después de desplegar.
- Fórmula de salida `stock local menos cotizaciones abiertas`. Con `on_hand` no hace falta: el pedido pendiente no mueve `on_hand`, y al cobrar SmartPyme baja su stock y publica el `on_hand` nuevo.

## Prueba manual

1. En Shopify, poner la soda en 15 en una sola ubicación.
2. En el log: `cantidades` con `on_hand` 15 y `Inventario actualizado` para esa bodega. Las otras ubicaciones solo se mueven si su `on_hand` cambió.
3. Crear un pedido pendiente del mismo producto. El log muestra `on_hand` igual al stock local y `Stock ya sincronizado`. La cotización no descuenta.
4. Ajustar el stock en SmartPyme. Shopify cambia `on_hand`. El webhook de vuelta no vuelve a escribir en SmartPyme.
