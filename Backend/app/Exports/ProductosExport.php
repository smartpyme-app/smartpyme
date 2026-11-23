<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Support\NombreComercial;

class ProductosExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */

    private $request;

    /** Se calculan una sola vez por exportación (antes se consultaban en cada fila). */
    private ?bool $mostrarCosto = null;
    private ?bool $incluirComponenteQuimico = null;
    /** @var array<int, bool> id_empresa => lotes activos */
    private array $lotesActivoPorEmpresa = [];

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    private function mostrarCosto(): bool
    {
        return $this->mostrarCosto ??= !\App\Constants\CotizacionConstants::esVentasLimitado(Auth::user());
    }

    private function incluirComponenteQuimico(): bool
    {
        if ($this->incluirComponenteQuimico !== null) {
            return $this->incluirComponenteQuimico;
        }
        $user = Auth::user();
        if (!$user || !$user->id_empresa) {
            return $this->incluirComponenteQuimico = false;
        }
        $empresa = Empresa::find($user->id_empresa);
        return $this->incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();
    }

    private function lotesActivo($empresa): bool
    {
        if (!$empresa) {
            return false;
        }
        return $this->lotesActivoPorEmpresa[$empresa->id] ??= $empresa->isLotesActivo();
    }

    /** Mismo texto que Proveedor::nombre_proveedor, pero con la relación ya cargada (sin consultas). */
    private function nombrePrimerProveedor($row): string
    {
        $productoProveedor = $row->proveedores->first();
        if (!$productoProveedor) {
            return '';
        }
        $proveedor = $productoProveedor->proveedor;
        if (!$proveedor) {
            return 'Consumidor Final';
        }
        $legal = $proveedor->tipo == 'Persona'
            ? $proveedor->nombre . ' ' . $proveedor->apellido
            : $proveedor->nombre_empresa;

        return NombreComercial::anexar($legal, $proveedor->nombre_comercial);
    }

    /**
     * Stock para Excel: lotes si el producto los usa y la empresa los tiene activos; si no, inventarios.
     * Misma regla que ProductosController::calcularStockBaseParaBusqueda / StockDisponibleService.
     *
     * @param  object  $producto  Producto (o stub) con inventarios/lotes cargados
     * @return float|string
     */
    public static function stockParaExport($producto, bool $lotesActivo)
    {
        if (!empty($producto->inventario_por_lotes) && $lotesActivo) {
            $stockSum = (float) $producto->lotes->sum('stock');
        } else {
            $stockSum = (float) $producto->inventarios->sum('stock');
        }

        return $stockSum ?: '0';
    }

    public static function tieneFotografiaParaExport($producto): string
    {
        $count = (int) ($producto->imagenes_count ?? 0);

        return $count > 0 ? 'Sí' : 'No';
    }

    public function headings(): array
    {
        $headings = [
            'Nombre',
            'Categoria',
            'Codigo',
            'Codigo_de_barra',
            'Marca',
        ];
        if ($this->incluirComponenteQuimico()) {
            $headings[] = 'Componente químico';
        }
        $precios = ['Precio sin IVA'];
        if ($this->mostrarCosto()) {
            array_unshift($precios, 'Costo');
            $precios[] = 'Ganancia';
        }
        $headings = array_merge($headings, $precios, [
            'Precio con IVA',
            'Stock',
            'Proveedor',
            'Estado',
            // 'Etiquetas',
            'Descripcion',
            'Tiene fotografía',
        ]);
        return $headings;
    }

    public function map($row): array
    {
        $empresa = $row->empresa;
        $lotesActivo = $this->lotesActivo($empresa);
        $stockSum = self::stockParaExport($row, $lotesActivo);

        // Obtener la empresa y verificar si tiene shopify_store_url configurado
        $nombreProducto = $row->nombre;

        // Si la empresa tiene shopify_store_url y el producto tiene nombre_variante, concatenar
        if ($empresa && $empresa->shopify_store_url && $row->nombre_variante) {
            $nombreProducto = $row->nombre . ' ' . $row->nombre_variante;
        }

        $fields = [
            $nombreProducto,
            $row->nombre_categoria,
            $row->codigo,
            $row->barcode,
            $row->marca,
        ];
        if ($this->incluirComponenteQuimico()) {
            $fields[] = $row->componente_quimico ?? '';
        }
        $precios = [number_format($row->precio, 2)];
        if ($this->mostrarCosto()) {
            $costo = ($empresa ? $empresa->valor_inventario : null) == 'promedio'
                ? number_format($row->costo_promedio, 2)
                : number_format($row->costo, 2);
            array_unshift($precios, $costo);
            $precios[] = number_format($row->precio - $row->costo, 2);
        }
        $fields = array_merge($fields, $precios, [
            number_format($row->precio + ($row->precio * ($empresa && $empresa->iva ? $empresa->iva / 100 : 0)), 2),
            $stockSum,
            $this->nombrePrimerProveedor($row),
            $row->enable ? 'Activo' : 'Inactivo',
            // $etiquetas,
            $row->descripcion,
            self::tieneFotografiaParaExport($row),
        ]);
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return Producto::withCount('imagenes')
            ->with(['inventarios' => function ($q) use ($request) {
            if ($request->id_bodega) {
                $q->where('id_bodega', $request->id_bodega);
            }
        }, 'lotes' => function ($q) use ($request) {
            if ($request->id_bodega) {
                $q->where('id_bodega', $request->id_bodega);
            }
        },
            'empresa',
            'categoria:id,nombre',
            'proveedores',
            'proveedores.proveedor',
        ])
            ->when($request->id_categoria, function ($query) use ($request) {
                return $query->where('id_categoria', $request->id_categoria);
            })
            ->when($request->buscador, function ($query) use ($request) {
                // Agrupado: sin el paréntesis, los OR anulaban los demás filtros (categoría, tipo, estado...).
                return $query->where(function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->buscador . '%')
                        ->orwhere('codigo', 'like', "%" . $request->buscador . "%")
                        ->orwhere('barcode', 'like', "%" . $request->buscador . "%")
                        ->orwhere('etiquetas', 'like', "%" . $request->buscador . "%")
                        ->orwhere('marca', 'like', "%" . $request->buscador . "%")
                        ->orwhere('descripcion', 'like', "%" . $request->buscador . "%");
                });
            })
            ->when($request->id_proveedor, function ($q) use ($request) {
                $q->whereHas('proveedores', function ($q) use ($request) {
                    return $q->where("id_proveedor", $request->id_proveedor);
                });
            })
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->when($request->marca, function ($query) use ($request) {
                return $query->where('marca', 'like', '%' . $request->marca . '%');
            })
            ->whereIn('tipo', ['Producto', 'Compuesto'])
            // ->whereNotIn('id_categoria', [1,2])
            ->orderBy('enable', 'desc')
            ->orderBy($request->orden, $request->direccion)
            ->get();
    }
}
