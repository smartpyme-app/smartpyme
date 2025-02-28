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

class WooCommerceExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, ShouldAutoSize, WithCustomCsvSettings
{
    /**
     * @return \Illuminate\Support\Collection
     */
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
            'C' => '@',  // Formato de texto para SKU
            'L' => NumberFormat::FORMAT_NUMBER,  // Formato numérico para stock
            'M' => NumberFormat::FORMAT_NUMBER_00, // Formato de precio con dos decimales
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Type',
            'SKU',
            'Name',
            'Published',
            'Is featured?',
            'Visibility in catalog',
            'Short description',
            'Description',
            'Date sale price starts',
            'Date sale price ends',
            'Tax status',
            'Tax class',
            'In stock?',
            'Stock',
            'Backorders allowed?',
            'Sold individually?',
            'Weight (kg)',
            'Length (cm)',
            'Width (cm)',
            'Height (cm)',
            'Allow customer reviews?',
            'Purchase note',
            'Sale price',
            'Regular price',
            'Categories',
            'Tags',
            'Shipping class',
            'Images',
            'Download limit',
            'Download expiry days',
            'Parent',
            'Grouped products',
            'Upsells',
            'Cross-sells',
            'External URL',
            'Button text',
            'Position'
        ];
    }

    public function map($row): array
    {
        // Obtener el stock sumando todos los inventarios relacionados
        $stockSum = $row->inventarios->sum('stock');
        $inStock = ($stockSum > 0) ? 1 : 0;

        // Preparar la URL de la imagen
        $imagenUrl = '';
        if (!empty($row->img)) {
            $imagenUrl = url('/img/' . $row->img);
        }

        // Obtener categoría
        $categoria = $row->categoria ? $row->categoria->nombre : '';

        // Datos de dimensiones
        $largo = $row->largo ?? '';
        $ancho = $row->ancho ?? '';
        $alto = $row->alto ?? '';

        // Formatear el precio sin usar number_format (para evitar comas)
        $precio = $row->precio ? number_format($row->precio, 2, '.', '') : '';

        return [
            '', // ID (vacío para nuevos)
            'simple', // Type
            $row->codigo, // SKU
            $row->nombre, // Name
            '1', // Published
            '0', // Is featured?
            'visible', // Visibility in catalog
            '', // Short description
            $row->descripcion ?? '', // Description
            '', // Date sale price starts
            '', // Date sale price ends
            'taxable', // Tax status
            '', // Tax class
            $inStock, // In stock?
            $stockSum, // Stock
            '0', // Backorders allowed?
            '0', // Sold individually?
            $row->peso ?? '', // Weight (kg)
            $largo, // Length (cm)
            $ancho, // Width (cm)
            $alto, // Height (cm)
            '1', // Allow customer reviews?
            '', // Purchase note
            '', // Sale price
            $precio, // Regular price
            $categoria, // Categories
            '', // Tags
            '', // Shipping class
            $imagenUrl, // Images
            '', // Download limit
            '', // Download expiry days
            '', // Parent
            '', // Grouped products
            '', // Upsells
            '', // Cross-sells
            '', // External URL
            '', // Button text
            '0'  // Position
        ];
    }

    public function collection()
    {
        try {
            // Obtener usuario y verificar si tiene configuración de WooCommerce
            $user = User::find($this->userId);

            if (!$user) {
                Log::warning("Usuario no encontrado", [
                    'user_id' => $this->userId
                ]);
            }

            // Obtener id_empresa del request
            $idEmpresa = $this->request->id_empresa;

            if (!$idEmpresa) {
                throw new \Exception("ID de empresa no proporcionado");
            }

            // Obtener bodegas relacionadas con la sucursal del usuario
            $bodegas = [];
            if ($user && $user->id_sucursal) {
                $bodegas = Bodega::where('id_sucursal', $user->id_sucursal)
                    ->where('id_empresa', $idEmpresa)
                    ->pluck('id')
                    ->toArray();
            }

            // Consulta base de productos
            $query = Producto::with(['inventarios' => function ($q) use ($bodegas) {
                if (!empty($bodegas)) {
                    $q->whereIn('id_bodega', $bodegas);
                }
            }, 'categoria'])
                ->where('id_empresa', $idEmpresa)
                ->where('enable', 1)
                ->whereNotNull('codigo');

            // Si hay bodegas, filtrar por inventario en esas bodegas
            if (!empty($bodegas)) {
                $query->whereHas('inventarios', function ($q) use ($bodegas) {
                    $q->whereIn('id_bodega', $bodegas)
                        ->where('stock', '>', 0);
                });
            }



            Log::info("Exportando " . $query->count() . " productos para WooCommerce");
            return $query->whereIn('tipo', ['Producto', 'Compuesto'])
                ->orderBy('codigo')
                ->get();

            //contar los productos
        } catch (\Exception $e) {
            Log::error("Error al exportar productos para WooCommerce: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return collect([]);
        }
    }
}
