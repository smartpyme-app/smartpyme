<?php

namespace App\Exports;

use App\Models\Admin\Sucursal;
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
    public $sucursalesData;

    public function __construct($fechaInicio = null, $fechaFin = null, $id_empresa = null, $configuracion = null)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->id_empresa = $id_empresa;
        $this->configuracion = $configuracion;
        $this->sucursalesData = collect();
        

        if ($configuracion && isset($configuracion->sucursales) && !empty($configuracion->sucursales)) {
            $this->sucursalesData = Sucursal::whereIn('id', $configuracion->sucursales)->get()->keyBy('id');
        } else {
            $this->sucursalesData = Sucursal::where('id_empresa', $id_empresa)->get()->keyBy('id');
            if ($configuracion) {
                $configuracion->sucursales = $this->sucursalesData->pluck('id')->toArray();
            }
        }
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
        $sucursalesIds = $this->configuracion->sucursales;

        Log::info('fecha: ' . $this->fechaInicio . ' al ' . $this->fechaFin);
        
        // Consulta base para obtener los detalles de ventas
        $ventasQuery = Detalle::whereHas('venta', function ($q) use ($sucursalesIds) {
            $q->whereBetween('fecha', [$this->fechaInicio, $this->fechaFin])
            ->where('id_empresa', $this->id_empresa)
            ->where('cotizacion', 0)
            ->where('estado', '!=', 'Anulada');
                
            if (!empty($sucursalesIds)) {
                $q->whereIn('id_sucursal', $sucursalesIds);
            }
        })
        ->whereHas('producto.categoria', function ($q) use ($categoriaIds) {
            $q->whereIn('id', $categoriaIds);
        })
        ->with(['venta.vendedor', 'producto.categoria', 'venta.sucursal'])
        ->get();

        $ventasAgrupadas = collect();
        $vendedoresAgrupados = $ventasQuery->groupBy(function ($detalle) {
            return optional($detalle->vendedor)->name ?? 'Sin vendedor';
        });

        foreach ($vendedoresAgrupados as $nombreVendedor => $detallesVendedor) {
            // Inicializar resultado con nombre del vendedor
            $resultado = [
                'Vendedor' => $nombreVendedor,
                'Total General' => 0
            ];
            
            // Inicializar categorías
            foreach ($categoriasSeleccionadas as $categoria) {
                $resultado[$categoria['nombre']] = 0;
            }
            
            // Inicializar totales por sucursal
            foreach ($sucursalesIds as $sucursalId) {
                $sucursalNombre = $this->sucursalesData->get($sucursalId)->nombre ?? 'Sucursal ' . $sucursalId;
                $resultado['Sucursal_' . $sucursalId] = 0;
            }

            // Procesar detalles de ventas para este vendedor
            foreach ($detallesVendedor as $detalle) {
                $nombreCategoria = $detalle->producto->categoria->nombre;
                $categoriaConfig = collect($categoriasSeleccionadas)
                    ->firstWhere('nombre', $nombreCategoria);
                
                $idSucursal = $detalle->venta->id_sucursal;
                
                if ($categoriaConfig) {
                    // Calcular el total con el porcentaje
                    $totalConPorcentaje = round($detalle->total * ($categoriaConfig['porcentaje'] / 100), 2);
                    
                    // Acumular el total para la categoría
                    $resultado[$nombreCategoria] += $totalConPorcentaje;
                    
                    // Acumular el total para la sucursal
                    $resultado['Sucursal_' . $idSucursal] += $totalConPorcentaje;
                    
                    // Acumular el total general
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
        $sucursalesIds = $this->configuracion->sucursales;
        
        // Columna para el vendedor
        $columnas = ['Vendedor'];
        
        // Columnas para cada categoría
        foreach ($categoriasSeleccionadas as $categoria) {
            $columnas[] = $categoria['nombre'] . ' (' . $categoria['porcentaje'] . '%)';
        }
        
        // Columnas para cada sucursal
        foreach ($sucursalesIds as $sucursalId) {
            $sucursalNombre = $this->sucursalesData->get($sucursalId)->nombre ?? 'Sucursal ' . $sucursalId;
            $columnas[] = $sucursalNombre;
        }
        
        // Columna para el total general
        $columnas[] = 'Total General';

        return $columnas;
    }

    public function map($fila): array
    {
        $categoriasSeleccionadas = $this->configuracion->configuracion;
        $sucursalesIds = $this->configuracion->sucursales;
        
        // Empezar con el nombre del vendedor
        $resultado = [$fila['Vendedor']];
        
        // Agregar totales por categoría
        foreach ($categoriasSeleccionadas as $categoria) {
            $resultado[] = '$' . number_format(round($fila[$categoria['nombre']], 2), 2);
        }
        
        // Agregar totales por sucursal
        foreach ($sucursalesIds as $sucursalId) {
            $resultado[] = '$' . number_format(round($fila['Sucursal_' . $sucursalId] ?? 0, 2), 2);
        }
        
        // Agregar total general
        $resultado[] = '$' . number_format(round($fila['Total General'], 2), 2);

        return $resultado;
    }
}