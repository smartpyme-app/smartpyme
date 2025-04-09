<?php

namespace App\Exports;

use App\Models\Ventas\Detalle;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Cache\Lock;
use Illuminate\Support\Facades\Log;

class VentasPorCategoriaVendedorExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public $request;
    public $fechaInicio;
    public $fechaFin;
    public $id_empresa;
    public $configuracion;

    public function __construct($fechaInicio = null, $fechaFin = null, $id_empresa = null, $configuracion = null)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->id_empresa = $id_empresa;
        $this->configuracion = $configuracion;
    }

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Ventas por Categoría y Vendedor - ' . $this->fechaInicio . ' al ' . $this->fechaFin;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para los encabezados
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
        ];
    }
    public function collection()
    {
        $categoriasSeleccionadas = $this->configuracion->configuracion;
        $categoriaIds = array_column($categoriasSeleccionadas, 'id');
        
        Log::info('fecha: ' . $this->fechaInicio . ' al ' . $this->fechaFin);
        $ventasQuery = Detalle::whereHas('venta', function ($q) {
            $q->whereBetween('fecha', [$this->fechaInicio, $this->fechaFin])
                ->where('id_empresa', $this->id_empresa)
                ->where('cotizacion', 0);
        })
        ->whereHas('producto.categoria', function ($q) use ($categoriaIds) {
            $q->whereIn('id', $categoriaIds);
        })
        ->with(['venta.vendedor', 'producto.categoria'])
        ->get();
    
        $ventasAgrupadas = collect();
        $vendedoresAgrupados = $ventasQuery->groupBy(function ($detalle) {
            return $detalle->vendedor ? $detalle->vendedor->name : 'Sin vendedor';
        });
    
        foreach ($vendedoresAgrupados as $nombreVendedor => $detallesVendedor) {
            $resultado = [
                'Vendedor' => $nombreVendedor,
                'Total General' => 0
            ];
            foreach ($categoriasSeleccionadas as $categoria) {
                $resultado[$categoria['nombre']] = 0;
                $resultado[$categoria['nombre'] . ' (%)'] = $categoria['porcentaje'] . '%';
            }
    
            foreach ($detallesVendedor as $detalle) {
                $nombreCategoria = $detalle->producto->categoria->nombre;
                $categoriaConfig = collect($categoriasSeleccionadas)
                    ->firstWhere('nombre', $nombreCategoria);
    
                if ($categoriaConfig) {
                    // Calcular el total con el porcentaje
                    $totalConPorcentaje = round($detalle->total * ($categoriaConfig['porcentaje'] / 100), 2);
                    
                    $resultado[$nombreCategoria] += $totalConPorcentaje;
                    $resultado['Total General'] += $totalConPorcentaje;
                }
            }
    
            $ventasAgrupadas->push($resultado);
        }
    
        return $ventasAgrupadas;
    }
    
    public function headings(): array
    {
        $categoriasSeleccionadas = $this->configuracion->configuracion;
    
        $columnas = ['Vendedor'];
        foreach ($categoriasSeleccionadas as $categoria) {
            $columnas[] = $categoria['nombre'];
        }
        $columnas[] = 'Total General';
    
        return $columnas;
    }
    
    public function map($fila): array
    {
        $categoriasSeleccionadas = $this->configuracion->configuracion;
    
        $resultado = [$fila['Vendedor']];
        foreach ($categoriasSeleccionadas as $categoria) {
            $resultado[] = '$' . number_format(round($fila[$categoria['nombre']], 2), 2);
        }
        $resultado[] = '$' . number_format(round($fila['Total General'], 2), 2);
    
        return $resultado;
    }
}
