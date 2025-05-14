<?php

namespace App\Exports\ReportesAutomaticos\VentasPorCategoriaPorVendedor;

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
use Illuminate\Support\Facades\DB;
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
    public $sucursales;

    public function __construct($fechaInicio = null, $fechaFin = null, $id_empresa = null, $configuracion = null, $sucursales = null)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->id_empresa = $id_empresa;
        $this->configuracion = $configuracion;
        $this->sucursales = $sucursales;
        // $this->sucursalesData = collect();


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
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
        ];
    }

    public function collection()
    {
        try {
            $query = DB::table('detalles_venta as dv')
                ->join('productos as pro', 'dv.id_producto', '=', 'pro.id')
                ->join('categorias as cat', 'pro.id_categoria', '=', 'cat.id')
                ->join('users as us', 'dv.id_vendedor', '=', 'us.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);
    
            if (!empty($this->sucursales)) {
                $query->whereIn('vv.id_sucursal', $this->sucursales);
            }
    
            $ventasData = $query->select(
                'cat.id as id_categoria',
                'cat.nombre as nombre_categoria',
                'us.name as nombre_vendedor',
                'us.id as id_vendedor',
                DB::raw('SUM(dv.total - COALESCE(dv.descuento, 0)) as total_ventas')
            )
                ->groupBy('cat.id', 'cat.nombre', 'us.name', 'us.id')
                ->orderBy('us.name')
                ->get();
    
            // Obtener lista de categorías únicas
            $categorias = $ventasData->pluck('nombre_categoria', 'id_categoria')->unique();
    
            // Obtener lista de vendedores únicos
            $vendedores = $ventasData->pluck('nombre_vendedor', 'id_vendedor')->unique();
    
            // Crear un mapa de porcentajes por categoría
            $porcentajesCategorias = [];
            if ($this->configuracion && isset($this->configuracion->configuracion) && !empty($this->configuracion->configuracion)) {
                foreach ($this->configuracion->configuracion as $config) {
                    if (isset($config['id']) && isset($config['nombre']) && isset($config['porcentaje'])) {
                        $porcentajesCategorias[$config['id']] = [
                            'nombre' => $config['nombre'],
                            'porcentaje' => $config['porcentaje'] / 100 // Convertir a decimal para multiplicar
                        ];
                    }
                }
            }
    
            $resultadoFormateado = [];
    
            // Inicializar estructura para cada vendedor
            foreach ($vendedores as $id => $nombre) {
                $resultadoFormateado[$id] = [
                    'id_vendedor' => $id,
                    'Vendedor' => $nombre,
                ];
    
                // Inicializar columnas de categorías con cero
                foreach ($categorias as $idCat => $nombreCat) {
                    $porcentaje = isset($porcentajesCategorias[$idCat]) ? $porcentajesCategorias[$idCat]['porcentaje'] : 1;
                    $nombreConPorcentaje = $nombreCat . ' (' . ($porcentaje * 100) . '%)';
                    $resultadoFormateado[$id][$nombreConPorcentaje] = 0;
                }
    
                // Inicializar columna de total por vendedor
                $resultadoFormateado[$id]['TOTAL'] = 0;
            }
    
            // Inicializar fila de totales
            $totales = [
                'id_vendedor' => null,
                'Vendedor' => 'TOTAL'
            ];
            
            foreach ($categorias as $idCat => $nombreCat) {
                $porcentaje = isset($porcentajesCategorias[$idCat]) ? $porcentajesCategorias[$idCat]['porcentaje'] : 1;
                $nombreConPorcentaje = $nombreCat . ' (' . ($porcentaje * 100) . '%)';
                $totales[$nombreConPorcentaje] = 0;
            }
            
            $totales['TOTAL'] = 0;
    
            // Llenar la estructura con los datos de ventas
            foreach ($ventasData as $venta) {
                $idVendedor = $venta->id_vendedor;
                $idCategoria = $venta->id_categoria;
                $nombreCategoria = $venta->nombre_categoria;
                $total = $venta->total_ventas;
    
                // Aplicar el porcentaje si existe
                if (isset($porcentajesCategorias[$idCategoria])) {
                    $total = $total * $porcentajesCategorias[$idCategoria]['porcentaje'];
                }
    
                // Determinar nombre de categoría con porcentaje
                $porcentaje = isset($porcentajesCategorias[$idCategoria]) ? $porcentajesCategorias[$idCategoria]['porcentaje'] : 1;
                $nombreConPorcentaje = $nombreCategoria . ' (' . ($porcentaje * 100) . '%)';
    
                // Asignar valor a la celda correspondiente
                $resultadoFormateado[$idVendedor][$nombreConPorcentaje] = $total;
    
                // Actualizar total por vendedor
                $resultadoFormateado[$idVendedor]['TOTAL'] += $total;
    
                // Actualizar total por categoría
                $totales[$nombreConPorcentaje] += $total;
    
                // Actualizar total general
                $totales['TOTAL'] += $total;
            }
    
            // Convertir a colección para retornar
            $resultado = collect(array_values($resultadoFormateado));
            
            // Agregar la fila de totales
            $resultado->push($totales);
    
            return $resultado;
            
        } catch (\Exception $e) {
            Log::error('Error en collection de VentasPorCategoriaVendedorExport', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolver una estructura básica para evitar que la exportación falle completamente
            return collect([
                [
                    'Vendedor' => 'Error al generar el reporte',
                    'TOTAL' => 0
                ]
            ]);
        }
    }
    
    public function headings(): array
    {
        try {
            // Obtener las categorías para las columnas
            $categorias = DB::table('detalles_venta as dv')
                ->join('productos as pro', 'dv.id_producto', '=', 'pro.id')
                ->join('categorias as cat', 'pro.id_categoria', '=', 'cat.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);
    
            // Aplicar filtro de sucursales si está definido
            if (!empty($this->sucursales)) {
                $categorias->whereIn('vv.id_sucursal', $this->sucursales);
            }
    
            $categorias = $categorias->select('cat.id', 'cat.nombre')
                ->distinct()
                ->orderBy('cat.nombre')
                ->get();
    
            // Crear un mapa de porcentajes por categoría
            $porcentajesCategorias = [];
            if ($this->configuracion && isset($this->configuracion->configuracion) && !empty($this->configuracion->configuracion)) {
                foreach ($this->configuracion->configuracion as $config) {
                    if (isset($config['id']) && isset($config['nombre']) && isset($config['porcentaje'])) {
                        $porcentajesCategorias[$config['id']] = [
                            'nombre' => $config['nombre'],
                            'porcentaje' => $config['porcentaje'] / 100 // Convertir a decimal para multiplicar
                        ];
                    }
                }
            }
    
            $encabezados = ['Vendedor'];
            
            foreach ($categorias as $categoria) {
                $porcentaje = isset($porcentajesCategorias[$categoria->id]) ? $porcentajesCategorias[$categoria->id]['porcentaje'] * 100 : 100;
                $nombreConPorcentaje = $categoria->nombre . ' (' . $porcentaje . '%)';
                $encabezados[] = $nombreConPorcentaje;
            }
            
            $encabezados[] = 'TOTAL';
    
            return $encabezados;
            
        } catch (\Exception $e) {
            Log::error('Error generando encabezados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolver encabezados básicos
            return ['Vendedor', 'TOTAL'];
        }
    }
    
    public function map($fila): array
    {
        try {
            // Asegurarse de que la clave 'Vendedor' exista
            if (!isset($fila['Vendedor'])) {
                $vendedor = 'Sin vendedor';
            } else {
                $vendedor = $fila['Vendedor'];
            }
            
            $resultado = [$vendedor];
    
            // Obtener los encabezados para asegurarnos de recorrer las columnas en el orden correcto
            $encabezados = $this->headings();
            
            // Saltamos el primer encabezado que es 'Vendedor'
            for ($i = 1; $i < count($encabezados); $i++) {
                $columna = $encabezados[$i];
                
                // Verificar si la columna existe en la fila
                $valor = isset($fila[$columna]) ? $fila[$columna] : 0;
                
                // Formatear el valor
                $valorFormateado = is_numeric($valor) ? number_format(round($valor, 2), 2) : $valor;
                $resultado[] = $valorFormateado;
            }
    
            return $resultado;
        } catch (\Exception $e) {
            Log::error('Error en map de VentasPorCategoriaVendedorExport', [
                'error' => $e->getMessage(),
                'fila' => $fila
            ]);
            
            // En caso de error, devolver una fila con valores por defecto
            $resultado = ['Error al formatear'];
            $encabezados = $this->headings();
            
            for ($i = 1; $i < count($encabezados); $i++) {
                $resultado[] = '$0.00';
            }
            
            return $resultado;
        }
    }
}