<?php

namespace App\Imports;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Bodega;
use App\Models\Admin\Empresa;
use App\Services\ImpuestosService;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Ajuste;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use JWTAuth;

class WooCommerceProductosImport implements ToModel, WithHeadingRow, SkipsEmptyRows, WithCustomCsvSettings
{
    private $usuario;
    private $bodega;
    private $parentMap = []; // parent_id => ['sku' => x, 'categorias' => x, 'descripcion' => x, 'descripcion_corta' => x]
    private $created = 0;
    private $updated = 0;
    private $skipped = 0;
    private $errores = [];

    public function __construct()
    {
        $this->usuario = JWTAuth::parseToken()->authenticate();
        $bodega = Bodega::where('id_empresa', $this->usuario->id_empresa)
            ->where('activo', true)
            ->orderBy('id')
            ->first();

        if (!$bodega) {
            $bodega = Bodega::where('id_empresa', $this->usuario->id_empresa)->first();
        }
        $this->bodega = $bodega;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
            'line_ending' => PHP_EOL,
            'use_bom' => true,
        ];
    }

    public function model(array $row)
    {
        $tipo = $this->getValue($row, ['tipo', 'type']);
        $id = $this->getValue($row, ['id']);
        $nombre = $this->getValue($row, ['nombre', 'name']);

        if (empty($nombre)) {
            $this->skipped++;
            return null;
        }

        // variable: solo guardar datos del padre para las variaciones
        if (in_array(strtolower($tipo), ['variable'])) {
            $this->parentMap[(int) $id] = [
                'sku' => $this->getValue($row, ['sku']),
                'categorias' => $this->getValue($row, ['categorias', 'categories']),
                'descripcion' => $this->getValue($row, ['descripcion', 'description']),
                'descripcion_corta' => $this->getValue($row, ['descripcion_corta', 'short_description']),
                'marca' => $this->getValue($row, ['marcas', 'brands']),
            ];
            $this->skipped++;
            return null;
        }

        // simple o variation: crear/actualizar producto
        if (in_array(strtolower($tipo), ['simple', 'variation'])) {
            return $this->procesarProducto($row, $tipo, $id, $nombre);
        }

        $this->skipped++;
        return null;
    }

    private function procesarProducto(array $row, string $tipo, $id, string $nombre)
    {
        $wooId = (int) $id;
        $parentId = null;
        $parentData = null;

        if (strtolower($tipo) === 'variation') {
            $superior = $this->getValue($row, ['superior', 'parent']);
            if (!empty($superior)) {
                $parentId = $this->parseParentId($superior);
                $parentData = $this->parentMap[$parentId] ?? null;
            }
        }

        // Resolver categoría (desde variación, padre o fila actual)
        $categoriasRaw = $this->getValue($row, ['categorias', 'categories'])
            ?? ($parentData['categorias'] ?? '');
        $id_categoria = $this->resolverCategoria($categoriasRaw);

        // Resolver descripción
        $descripcionCorta = $this->getValue($row, ['descripcion_corta', 'short_description'])
            ?? ($parentData['descripcion_corta'] ?? '');
        $descripcionLarga = $this->getValue($row, ['descripcion', 'description'])
            ?? ($parentData['descripcion'] ?? '');
        $descripcion = !empty($descripcionCorta) ? $descripcionCorta : $descripcionLarga;
        $descripcionCompleta = $descripcionLarga;

        // Precio WooCommerce: viene con IVA incluido. Preferir Precio rebajado si existe, sino Precio normal.
        $precioRebajado = $this->getValue($row, ['precio_rebajado', 'sale_price']);
        $precioNormal = $this->getValue($row, ['precio_normal', 'regular_price']);
        $precioConIva = $this->parseDecimal($precioRebajado ?: $precioNormal);

        // Resolver precio_sin_iva y precio_con_iva según si la empresa cobra IVA
        [$precio, $precioSinIva, $precioConIvaFinal] = $this->resolverPreciosConIVA($precioConIva);

        // Stock
        $stock = (int) ($this->getValue($row, ['inventario', 'stock']) ?? 0);
        $stock = max(0, $stock);

        // Marca
        $marca = $this->getValue($row, ['marcas', 'brands'])
            ?? ($parentData['marca'] ?? '');

        // Barcode
        $barcode = $this->getValue($row, ['gtin_upc_ean_o_isbn', 'gtin', 'upc', 'ean']);

        // Etiquetas
        $etiquetasRaw = $this->getValue($row, ['etiquetas', 'tags']);
        $etiquetas = !empty($etiquetasRaw) ? array_map('trim', explode(',', $etiquetasRaw)) : [];

        // SKU
        $sku = $this->getValue($row, ['sku']);
        if (strtolower($tipo) === 'variation' && empty($sku)) {
            $parentSku = $parentData['sku'] ?? null;
            $parentIdVal = $parentId ?? $wooId;
            $sku = !empty($parentSku)
                ? $parentSku . '-' . $wooId
                : $parentIdVal . '-' . $wooId;
        }

        if (empty($sku)) {
            $sku = 'WC-' . $wooId;
        }

        // Buscar producto existente: primero por woocommerce_id, luego por codigo (si no tiene woocommerce_id)
        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->usuario->id_empresa)
            ->where('woocommerce_id', $wooId)
            ->first();

        if (!$producto) {
            $producto = Producto::withoutGlobalScope('empresa')
                ->where('id_empresa', $this->usuario->id_empresa)
                ->whereNull('woocommerce_id')
                ->where('codigo', $sku)
                ->first();
        }

        $esNuevo = !$producto;
        if ($esNuevo) {
            $producto = new Producto();
            $this->created++;
        } else {
            $this->updated++;
        }

        // No sobrescribir costo si ya existe y el CSV no trae costo
        $costoActual = $producto->exists ? $producto->costo : 0;

        $producto->nombre = $nombre;
        $producto->descripcion = $descripcion;
        $producto->descripcion_completa = $descripcionCompleta;
        $producto->codigo = $sku;
        $producto->precio = $precio;
        $producto->precio_sin_iva = $precioSinIva;
        $producto->precio_con_iva = $precioConIvaFinal;
        $producto->id_categoria = $id_categoria ?: $this->obtenerCategoriaPorDefecto();
        $producto->marca = $marca;
        $producto->barcode = $barcode;
        $producto->etiquetas = $etiquetas;
        $producto->woocommerce_id = $wooId;
        $producto->imported_from_woocommerce_csv = true;
        $producto->last_woocommerce_sync = now();
        $producto->woocommerce_parent_id = ($parentId && strtolower($tipo) === 'variation') ? $parentId : null;
        $producto->tipo = 'Producto';
        $producto->medida = 'Unidad';
        $producto->enable = '1';
        $producto->id_empresa = $this->usuario->id_empresa;

        if ($producto->exists && $costoActual > 0) {
            // No sobrescribir costo existente
        } else {
            $producto->costo = 0;
            $producto->costo_promedio = 0;
        }

        $producto->save();

        // Inventario en bodega
        if ($this->bodega && $producto->id) {
            $inventario = Inventario::withoutGlobalScopes()
                ->where('id_producto', $producto->id)
                ->where('id_bodega', $this->bodega->id)
                ->first();

            if (!$inventario) {
                $inventario = new Inventario();
                $inventario->id_producto = $producto->id;
                $inventario->id_bodega = $this->bodega->id;
            }

            $stockAnterior = $inventario->stock ?? 0;
            $inventario->stock = $stock;
            $inventario->save();

            if ($esNuevo && $stock > 0) {
                $ajuste = Ajuste::create([
                    'concepto' => 'Importación WooCommerce',
                    'id_producto' => $producto->id,
                    'id_bodega' => $this->bodega->id,
                    'stock_actual' => 0,
                    'stock_real' => $stock,
                    'ajuste' => $stock,
                    'estado' => 'Confirmado',
                    'id_empresa' => $this->usuario->id_empresa,
                    'id_usuario' => $this->usuario->id,
                ]);
                $inventario->kardex($ajuste, $ajuste->ajuste);
            }
        }

        return $producto;
    }

    private function getValue(array $row, array $keys)
    {
        foreach ($keys as $key) {
            $keyNorm = $this->normalizeHeader($key);
            foreach ($row as $header => $value) {
                $headerNorm = $this->normalizeHeader($header);
                if ($headerNorm === $keyNorm && $value !== '' && $value !== null) {
                    return is_string($value) ? trim($value) : $value;
                }
            }
        }
        return null;
    }

    private function normalizeHeader($header)
    {
        $s = preg_replace('/[^a-z0-9]/', '_', strtolower((string) $header));
        return trim(preg_replace('/_+/', '_', $s), '_');
    }

    private function parseParentId($superior)
    {
        if (is_numeric($superior)) {
            return (int) $superior;
        }
        if (preg_match('/id[:\s]*(\d+)/i', $superior, $m)) {
            return (int) $m[1];
        }
        return (int) $superior;
    }

    private function parseDecimal($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        return (float) preg_replace('/[^\d.-]/', '', (string) $value);
    }

    private function resolverCategoria($categoriasRaw)
    {
        if (empty($categoriasRaw)) {
            return null;
        }
        $nombres = array_map('trim', explode('>', str_replace(',', ' > ', $categoriasRaw)));
        $nombreFinal = trim(end($nombres));

        if (empty($nombreFinal)) {
            return null;
        }

        $categoria = Categoria::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->usuario->id_empresa)
            ->where('nombre', $nombreFinal)
            ->first();

        if (!$categoria) {
            $categoria = Categoria::create([
                'nombre' => $nombreFinal,
                'descripcion' => $nombreFinal,
                'enable' => true,
                'id_empresa' => $this->usuario->id_empresa,
            ]);
        }

        return $categoria->id;
    }

    /**
     * Resuelve precios según configuración de IVA de la empresa.
     * WooCommerce envía el precio con IVA incluido.
     *
     * @return array [precio, precio_sin_iva, precio_con_iva]
     */
    private function resolverPreciosConIVA(float $precioWooCommerce)
    {
        $empresa = Empresa::withoutGlobalScope('empresa')->find($this->usuario->id_empresa);

        if (!$empresa || $precioWooCommerce <= 0) {
            return [$precioWooCommerce, $precioWooCommerce, $precioWooCommerce];
        }

        $cobraIva = $empresa->cobra_iva === 'Si' || $empresa->cobra_iva === '1';
        $tieneIva = !empty($empresa->iva) && $empresa->iva > 0;

        if (!$cobraIva || !$tieneIva) {
            return [$precioWooCommerce, $precioWooCommerce, $precioWooCommerce];
        }

        $impuestosService = app(ImpuestosService::class);
        $precioSinIva = $impuestosService->calcularPrecioSinImpuesto($precioWooCommerce, $this->usuario->id_empresa);

        return [$precioSinIva, $precioSinIva, $precioWooCommerce];
    }

    private function obtenerCategoriaPorDefecto()
    {
        $categoria = Categoria::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->usuario->id_empresa)
            ->orderBy('id')
            ->first();
        if ($categoria) {
            return $categoria->id;
        }
        $nueva = Categoria::create([
            'nombre' => 'General',
            'descripcion' => 'Categoría por defecto',
            'enable' => true,
            'id_empresa' => $this->usuario->id_empresa,
        ]);
        return $nueva->id;
    }

    public function getEstadisticas(): array
    {
        return [
            'creados' => $this->created,
            'actualizados' => $this->updated,
            'omitidos' => $this->skipped,
            'errores' => $this->errores,
        ];
    }
}
