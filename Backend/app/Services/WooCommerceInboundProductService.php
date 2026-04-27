<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class WooCommerceInboundProductService
{
    /** @var ImpuestosService */
    private $impuestosService;

    public function __construct(ImpuestosService $impuestosService)
    {
        $this->impuestosService = $impuestosService;
    }

    /**
     * Crea o actualiza un producto en SmartPyme desde el JSON de la REST API de WooCommerce (webhook de producto).
     *
     * @return array{action: string, producto_id: int|null, detail: string}
     */
    public function applyPayload(Empresa $empresa, User $user, array $p): array
    {
        $type = $p['type'] ?? '';
        if ($type === 'variable') {
            return ['action' => 'skipped', 'producto_id' => null, 'detail' => 'variable_parent'];
        }

        if (!in_array($type, ['simple', 'variation'], true)) {
            return ['action' => 'skipped', 'producto_id' => null, 'detail' => 'unsupported_type:' . (string) $type];
        }

        $status = $p['status'] ?? 'publish';
        if ($status === 'trash' || $status === 'draft') {
            $wooId = (int) ($p['id'] ?? 0);
            if ($wooId > 0) {
                $this->maybeDisableByWooId($empresa->id, $wooId, $user);
            }
            return ['action' => 'disabled_or_skipped', 'producto_id' => null, 'detail' => 'status:' . (string) $status];
        }

        $bodega = Bodega::where('id_empresa', $empresa->id)
            ->where('id', $user->id_bodega)
            ->first();
        if (!$bodega) {
            $bodega = Bodega::where('id_empresa', $empresa->id)->where('activo', true)->orderBy('id')->first();
        }
        if (!$bodega) {
            return ['action' => 'error', 'producto_id' => null, 'detail' => 'no_bodega'];
        }

        $wooId = (int) ($p['id'] ?? 0);
        if ($wooId < 1) {
            return ['action' => 'error', 'producto_id' => null, 'detail' => 'no_woo_id'];
        }

        $parentId = isset($p['parent_id']) ? (int) $p['parent_id'] : null;
        if ($type === 'variation' && $parentId !== null && $parentId < 1) {
            $parentId = null;
        }

        $sku = isset($p['sku']) && $p['sku'] !== '' && $p['sku'] !== null
            ? trim((string) $p['sku'])
            : null;
        if ($sku === null || $sku === '') {
            if ($type === 'variation' && $parentId) {
                $sku = $parentId . '-' . $wooId;
            } else {
                $sku = 'WC-' . $wooId;
            }
        }

        $nombre = trim((string) ($p['name'] ?? ''));
        if ($nombre === '') {
            return ['action' => 'error', 'producto_id' => null, 'detail' => 'no_name'];
        }

        $descShort = strip_tags((string) ($p['short_description'] ?? ''));
        $descLong = strip_tags((string) ($p['description'] ?? ''));
        $descripcion = $descShort !== '' ? $descShort : (strlen($descLong) > 500 ? mb_substr($descLong, 0, 500) : $descLong);
        $descripcionCompleta = $descLong !== '' ? $descLong : $descShort;

        $precioRebajado = $this->parseDecimal($p['sale_price'] ?? null);
        $precioNormal = $this->parseDecimal($p['regular_price'] ?? null);
        $priceFallback = $this->parseDecimal($p['price'] ?? null);
        $basePrice = $precioRebajado > 0 ? $precioRebajado : ($precioNormal > 0 ? $precioNormal : $priceFallback);
        [$precio, $precioSinIva, $precioConIva] = $this->resolverPreciosConIVA((float) $basePrice, (int) $empresa->id, (int) $user->id_empresa);

        $idCategoria = $this->resolverCategoriaDesdeWoo($p['categories'] ?? null, (int) $empresa->id);
        if (!$idCategoria) {
            $idCategoria = $this->obtenerCategoriaPorDefecto((int) $empresa->id);
        }

        $marca = '';
        if (!empty($p['brands']) && is_array($p['brands'])) {
            $last = end($p['brands']);
            if (is_array($last) && !empty($last['name'])) {
                $marca = (string) $last['name'];
            }
        }

        $stock = 0;
        if (!empty($p['manage_stock'])) {
            $stock = (int) ($p['stock_quantity'] ?? 0);
        } elseif (isset($p['stock_status'])) {
            $stock = ($p['stock_status'] === 'instock') ? 1 : 0;
        }
        if ($stock < 0) {
            $stock = 0;
        }

        $enable = ($status === 'publish' && (empty($p['catalog_visibility']) || $p['catalog_visibility'] !== 'hidden')) ? '1' : '0';

        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('woocommerce_id', $wooId)
            ->first();

        if (!$producto) {
            $producto = Producto::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->whereNull('woocommerce_id')
                ->where('codigo', $sku)
                ->first();
        }

        $esNuevo = $producto === null;
        if ($esNuevo) {
            $producto = new Producto();
        }

        $costoExistente = $producto->exists ? (float) $producto->costo : 0;

        return Model::withoutEvents(function () use (
            $producto,
            $esNuevo,
            $empresa,
            $user,
            $bodega,
            $sku,
            $wooId,
            $parentId,
            $type,
            $nombre,
            $descripcion,
            $descripcionCompleta,
            $precio,
            $precioSinIva,
            $precioConIva,
            $idCategoria,
            $marca,
            $stock,
            $enable,
            $costoExistente
        ) {
            $producto->nombre = $nombre;
            $producto->descripcion = $descripcion;
            $producto->descripcion_completa = $descripcionCompleta;
            $producto->codigo = $sku;
            $producto->precio = $precio;
            $producto->precio_sin_iva = $precioSinIva;
            $producto->precio_con_iva = $precioConIva;
            $producto->id_categoria = $idCategoria;
            $producto->marca = $marca;
            $producto->woocommerce_id = $wooId;
            $producto->woocommerce_parent_id = ($type === 'variation' && $parentId) ? $parentId : null;
            $producto->imported_from_woocommerce_csv = true;
            $producto->last_woocommerce_sync = now();
            $producto->tipo = 'Producto';
            $producto->medida = 'Unidad';
            $producto->enable = $enable;
            $producto->id_empresa = $empresa->id;
            if (!$producto->exists || $costoExistente <= 0) {
                $producto->costo = 0;
                $producto->costo_promedio = 0;
            }
            $producto->save();

            $inventario = Inventario::withoutGlobalScopes()
                ->where('id_producto', $producto->id)
                ->where('id_bodega', $bodega->id)
                ->first();
            if (!$inventario) {
                $inventario = new Inventario();
                $inventario->id_producto = $producto->id;
                $inventario->id_bodega = $bodega->id;
            }
            $inventario->stock = $stock;
            $inventario->save();

            if ($esNuevo && $stock > 0) {
                $ajuste = Ajuste::create([
                    'concepto' => 'Webhook producto WooCommerce',
                    'id_producto' => $producto->id,
                    'id_bodega' => $bodega->id,
                    'stock_actual' => 0,
                    'stock_real' => $stock,
                    'ajuste' => $stock,
                    'estado' => 'Confirmado',
                    'id_empresa' => $empresa->id,
                    'id_usuario' => $user->id,
                ]);
                $inventario->kardex($ajuste, $ajuste->ajuste);
            }

            return [
                'action' => $esNuevo ? 'created' : 'updated',
                'producto_id' => $producto->id,
                'detail' => 'ok',
            ];
        });
    }

    private function maybeDisableByWooId(int $idEmpresa, int $wooId, User $user): void
    {
        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('woocommerce_id', $wooId)
            ->first();
        if ($producto) {
            Model::withoutEvents(function () use ($producto) {
                $producto->enable = '0';
                $producto->save();
            });
        }
    }

    private function parseDecimal($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return (float) preg_replace('/[^\d.-]/', '', (string) $value);
    }

    /**
     * @return array{0: float, 1: float, 2: float} precio, precio_sin_iva, precio_con_iva
     */
    private function resolverPreciosConIVA(float $precioWooCommerce, int $idEmpresa, int $idEmpresaUsuario): array
    {
        $empresa = Empresa::withoutGlobalScope('empresa')->find($idEmpresa);
        if (!$empresa || $precioWooCommerce <= 0) {
            return [$precioWooCommerce, $precioWooCommerce, $precioWooCommerce];
        }
        $cobraIva = $empresa->cobra_iva === 'Si' || $empresa->cobra_iva === '1';
        $tieneIva = !empty($empresa->iva) && $empresa->iva > 0;
        if (!$cobraIva || !$tieneIva) {
            return [$precioWooCommerce, $precioWooCommerce, $precioWooCommerce];
        }
        $precioSinIva = $this->impuestosService->calcularPrecioSinImpuesto($precioWooCommerce, $idEmpresaUsuario);
        return [$precioSinIva, $precioSinIva, $precioWooCommerce];
    }

    /**
     * @param mixed $categories
     */
    private function resolverCategoriaDesdeWoo($categories, int $idEmpresa): ?int
    {
        if (empty($categories) || !is_array($categories)) {
            return null;
        }
        $last = end($categories);
        if (!is_array($last)) {
            return null;
        }
        $nombre = $last['name'] ?? $last['slug'] ?? null;
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        $nombre = trim($nombre);
        if ($nombre === '') {
            return null;
        }
        $categoria = Categoria::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('nombre', $nombre)
            ->first();
        if (!$categoria) {
            $categoria = Categoria::create([
                'nombre' => $nombre,
                'descripcion' => $nombre,
                'enable' => true,
                'id_empresa' => $idEmpresa,
            ]);
        }
        return $categoria->id;
    }

    private function obtenerCategoriaPorDefecto(int $idEmpresa): int
    {
        $categoria = Categoria::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->orderBy('id')
            ->first();
        if ($categoria) {
            return $categoria->id;
        }
        $nueva = Categoria::create([
            'nombre' => 'General',
            'descripcion' => 'Categoría por defecto',
            'enable' => true,
            'id_empresa' => $idEmpresa,
        ]);
        return $nueva->id;
    }
}
