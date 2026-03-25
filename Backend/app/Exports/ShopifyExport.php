<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Http\Request;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Bodega;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, ShouldAutoSize, WithCustomCsvSettings
{
    private $request;
    private $userId;

    public function filter(Request $request)
    {
        $this->request = $request;
        $this->userId = $request->user_id ?? auth()->id();
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
            'line_ending' => PHP_EOL,
            'use_bom' => true,
            'include_separator_line' => false,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => '@',  // Handle (texto)
            'D' => '@',  // Vendor (texto)
            'E' => '@',  // Type (texto)
            'F' => '@',  // Tags (texto)
            'I' => '@',  // Option1 Name (texto)
            'J' => '@',  // Option1 Value (texto)
            'L' => '@',  // SKU (texto)
            'M' => NumberFormat::FORMAT_NUMBER, // Grams
            'N' => NumberFormat::FORMAT_NUMBER, // Inventory Tracker
            'O' => NumberFormat::FORMAT_NUMBER, // Inventory Qty
            'P' => '@',  // Inventory Policy (texto)
            'Q' => '@',  // Fulfillment Service (texto)
            'R' => NumberFormat::FORMAT_NUMBER_00, // Price
            'S' => NumberFormat::FORMAT_NUMBER_00, // Compare At Price
            'T' => '@',  // Requires Shipping (texto)
            'U' => '@',  // Taxable (texto)
            'V' => '@',  // Barcode (texto)
        ];
    }

    public function headings(): array
    {
        return [
            'Handle',
            'Title',
            'Body (HTML)',
            'Vendor',
            'Type',
            'Tags',
            'Published',
            'Option1 Name',
            'Option1 Value',
            'Option2 Name',
            'Option2 Value',
            'Option3 Name',
            'Option3 Value',
            'Variant SKU',
            'Variant Grams',
            'Variant Inventory Tracker',
            'Variant Inventory Qty',
            'Variant Inventory Policy',
            'Variant Fulfillment Service',
            'Variant Price',
            'Variant Compare At Price',
            'Variant Requires Shipping',
            'Variant Taxable',
            'Variant Barcode',
            'Image Src',
            'Image Position',
            'Image Alt Text',
            'Gift Card',
            'SEO Title',
            'SEO Description',
            'Google Shopping / Google Product Category',
            'Google Shopping / Gender',
            'Google Shopping / Age Group',
            'Google Shopping / MPN',
            'Google Shopping / AdWords Grouping',
            'Google Shopping / AdWords Labels',
            'Google Shopping / Condition',
            'Google Shopping / Custom Product',
            'Google Shopping / Custom Label 0',
            'Google Shopping / Custom Label 1',
            'Google Shopping / Custom Label 2',
            'Google Shopping / Custom Label 3',
            'Google Shopping / Custom Label 4',
            'Variant Image',
            'Variant Weight Unit',
            'Variant Tax Code',
            'Cost per item'
        ];
    }

    public function map($row): array
    {
        // Calcular stock total
        $stockSum = $row->inventarios->sum('stock');
        
        // Generar handle único (slug del producto)
        $handle = $this->generateHandle($row->nombre, $row->id);
        
        // Obtener imagen principal
        $imagenUrl = '';
        if (!empty($row->imagenes) && $row->imagenes->count() > 0 && $row->imagenes->first() !== null) {
            $imagenUrl = url('/img' . $row->imagenes->first()->img);
        }

        // Obtener categoría como tipo de producto
        $productType = $row->categoria ? $row->categoria->nombre : 'General';
        
        // Obtener vendor/marca
        $vendor = $row->marca ?? 'Mi Tienda';
        
        // Precios formateados
        $precio = $row->precio ? number_format($row->precio, 2, '.', '') : '0.00';
        $precioComparacion = $row->precio_comparacion ? number_format($row->precio_comparacion, 2, '.', '') : '';
        
        // Peso en gramos
        $pesoGramos = $row->peso ? ($row->peso * 1000) : 0; // Convertir kg a gramos
        
        // Tags (etiquetas)
        $tags = $this->generateTags($row);
        
        return [
            $handle, // Handle
            $row->nombre, // Title
            $this->formatDescription($row->descripcion), // Body (HTML)
            $vendor, // Vendor
            $productType, // Type
            $tags, // Tags
            'TRUE', // Published
            '', // Option1 Name (vacío para productos simples)
            '', // Option1 Value
            '', // Option2 Name
            '', // Option2 Value
            '', // Option3 Name
            '', // Option3 Value
            $row->codigo ?: $row->barcode, // Variant SKU
            $pesoGramos, // Variant Grams
            'shopify', // Variant Inventory Tracker
            $stockSum, // Variant Inventory Qty
            'deny', // Variant Inventory Policy
            'manual', // Variant Fulfillment Service
            $precio, // Variant Price
            $precioComparacion, // Variant Compare At Price
            'TRUE', // Variant Requires Shipping
            'TRUE', // Variant Taxable
            $row->barcode ?? $row->codigo, // Variant Barcode
            $imagenUrl, // Image Src
            '1', // Image Position
            $row->nombre, // Image Alt Text
            'FALSE', // Gift Card
            $row->nombre, // SEO Title
            $this->generateSeoDescription($row), // SEO Description
            '', // Google Shopping / Google Product Category
            '', // Google Shopping / Gender
            '', // Google Shopping / Age Group
            $row->codigo, // Google Shopping / MPN
            '', // Google Shopping / AdWords Grouping
            '', // Google Shopping / AdWords Labels
            'new', // Google Shopping / Condition
            '', // Google Shopping / Custom Product
            '', // Google Shopping / Custom Label 0
            '', // Google Shopping / Custom Label 1
            '', // Google Shopping / Custom Label 2
            '', // Google Shopping / Custom Label 3
            '', // Google Shopping / Custom Label 4
            '', // Variant Image
            'kg', // Variant Weight Unit
            '', // Variant Tax Code
            $row->costo ? number_format($row->costo, 2, '.', '') : '0.00' // Cost per item
        ];
    }

    public function collection()
    {
        try {
            $user = User::find($this->userId);

            if (!$user) {
                Log::warning("Usuario no encontrado para Shopify export", [
                    'user_id' => $this->userId
                ]);
                return collect([]);
            }

            $idEmpresa = $this->request->id_empresa;

            if (!$idEmpresa) {
                throw new \Exception("ID de empresa no proporcionado");
            }

            // Obtener bodega del usuario
            if ($user && $user->id_bodega) {
                $bodega = Bodega::where('id', $user->id_bodega)
                    ->where('id_empresa', $idEmpresa)
                    ->first();
            }

            // Query base para productos
            $query = Producto::with(['imagenes', 'inventarios' => function ($q) use ($bodega) {
                if ($bodega) {
                    $q->where('id_bodega', $bodega->id);
                }
            }, 'categoria'])
                ->where('id_empresa', $idEmpresa)
                ->where('enable', 1)
                ->whereNotNull('codigo');

            // Filtrar solo productos con stock si hay bodega
            if ($bodega) {
                $query->whereHas('inventarios', function ($q) use ($bodega) {
                    $q->where('id_bodega', $bodega->id)
                        ->where('stock', '>', 0);
                });
            }

            $productos = $query->whereIn('tipo', ['Producto', 'Compuesto'])
                ->orderBy('codigo')
                ->get();

            Log::info("Exportando " . $productos->count() . " productos para Shopify");
            
            return $productos;

        } catch (\Exception $e) {
            Log::error("Error al exportar productos para Shopify: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return collect([]);
        }
    }

    /**
     * Generar handle único para Shopify
     */
    private function generateHandle($nombre, $id)
    {
        // Convertir a minúsculas y reemplazar espacios y caracteres especiales
        $handle = strtolower($nombre);
        $handle = preg_replace('/[^a-z0-9\s-]/', '', $handle);
        $handle = preg_replace('/\s+/', '-', $handle);
        $handle = trim($handle, '-');
        
        // Agregar ID para asegurar unicidad
        $handle .= '-' . $id;
        
        return $handle;
    }

    /**
     * Formatear descripción para HTML
     */
    private function formatDescription($descripcion)
    {
        if (empty($descripcion)) {
            return '';
        }

        // Convertir saltos de línea a <br> si no hay HTML
        if (strip_tags($descripcion) === $descripcion) {
            return '<p>' . nl2br(htmlspecialchars($descripcion)) . '</p>';
        }

        return $descripcion;
    }

    /**
     * Generar tags automáticamente
     */
    private function generateTags($producto)
    {
        $tags = [];
        
        // Agregar categoría como tag
        if ($producto->categoria) {
            $tags[] = $producto->categoria->nombre;
        }
        
        // Agregar marca como tag
        if ($producto->marca) {
            $tags[] = $producto->marca;
        }
        
        // Agregar tipo como tag
        if ($producto->tipo) {
            $tags[] = $producto->tipo;
        }
        
        return implode(', ', array_filter($tags));
    }

    /**
     * Generar descripción SEO
     */
    private function generateSeoDescription($producto)
    {
        if ($producto->descripcion) {
            // Tomar primeras 155 caracteres para SEO
            $seoDesc = strip_tags($producto->descripcion);
            return strlen($seoDesc) > 155 ? substr($seoDesc, 0, 152) . '...' : $seoDesc;
        }
        
        return $producto->nombre . ' - Disponible en nuestra tienda online';
    }
}