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
            // Estilo para los encabezados
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
        ];
    }

    public function collection()
    {
        try {
            // Registrar entrada en el log para depuración
            // Log::info('Iniciando collection en VentasPorCategoriaVendedorExport', [
            //     'fechaInicio' => $this->fechaInicio,
            //     'fechaFin' => $this->fechaFin
            // ]);
    
            // Filtrar por fecha, empresa y estado no anulado
            $query = DB::table('detalles_venta as dv')
                ->join('productos as pro', 'dv.id_producto', '=', 'pro.id')
                ->join('categorias as cat', 'pro.id_categoria', '=', 'cat.id')
                ->join('users as us', 'dv.id_vendedor', '=', 'us.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);
    
            // Aplicar filtro de sucursales si está definido
            if (!empty($this->sucursales)) {
                $query->whereIn('vv.id_sucursal', $this->sucursales);
            }
    
            // Obtener los datos base agrupados por categoría y vendedor
            $ventasData = $query->select(
                'cat.id as id_categoria',
                'cat.nombre as nombre_categoria',
                'us.name as nombre_vendedor',
                // DB::raw('SUM(dv.total) as total_ventas')
                DB::raw('SUM(dv.total - COALESCE(dv.descuento, 0)) as total_ventas')
            )
                ->groupBy('cat.id', 'cat.nombre', 'us.name')
                ->orderBy('cat.nombre')
                ->get();
    
            // Log::info('Datos de ventas obtenidos', ['count' => count($ventasData)]);
    
            // Obtener lista de vendedores únicos
            $vendedores = $ventasData->pluck('nombre_vendedor')->unique()->values()->toArray();
            // Log::info('Vendedores únicos', ['vendedores' => $vendedores]);
    
            // Obtener lista de categorías únicas
            $categorias = $ventasData->pluck('nombre_categoria', 'id_categoria')->unique();
            // Log::info('Categorías únicas', ['count' => count($categorias)]);
    
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
            // Log::info('Porcentajes por categoría', ['count' => count($porcentajesCategorias)]);
    
            // Preparar estructura para el formato requerido
            $resultadoFormateado = [];
    
            // Inicializar estructura para cada categoría
            foreach ($categorias as $id => $nombre) {
                $porcentaje = isset($porcentajesCategorias[$id]) ? $porcentajesCategorias[$id]['porcentaje'] : 1;
                $nombreConPorcentaje = $nombre . ' (' . ($porcentaje * 100) . '%)';
                
                $resultadoFormateado[$id] = [
                    'id_categoria' => $id,
                    'Categoria' => $nombreConPorcentaje,
                ];
    
                // Inicializar columnas de vendedores con cero
                foreach ($vendedores as $vendedor) {
                    $resultadoFormateado[$id][$vendedor] = 0;
                }
    
                // Inicializar columna de total
                $resultadoFormateado[$id]['TOTAL'] = 0;
            }
    
            // Inicializar fila de totales con estructura idéntica a las otras filas
            $totales = [
                'id_categoria' => null,
                'Categoria' => 'TOTAL'
            ];
            
            foreach ($vendedores as $vendedor) {
                $totales[$vendedor] = 0;
            }
            
            $totales['TOTAL'] = 0;
    
            // Log::info('Estructura inicializada', [
            //     'num_categorias' => count($resultadoFormateado),
            //     'totales_keys' => array_keys($totales)
            // ]);
    
            // Llenar la estructura con los datos de ventas
            foreach ($ventasData as $venta) {
                $idCategoria = $venta->id_categoria;
                $vendedor = $venta->nombre_vendedor;
                $total = $venta->total_ventas;
    
                // Verificar que la categoría existe en nuestro resultado formateado
                if (!isset($resultadoFormateado[$idCategoria])) {
                    Log::warning('Categoría no encontrada en estructura', [
                        'id_categoria' => $idCategoria,
                        'nombre' => $venta->nombre_categoria
                    ]);
                    continue;
                }
    
                // Aplicar el porcentaje si existe
                if (isset($porcentajesCategorias[$idCategoria])) {
                    $total = $total * $porcentajesCategorias[$idCategoria]['porcentaje'];
                }
    
                // Asignar valor a la celda correspondiente
                $resultadoFormateado[$idCategoria][$vendedor] = $total;
    
                // Actualizar total por categoría
                $resultadoFormateado[$idCategoria]['TOTAL'] += $total;
    
                // Actualizar total por vendedor
                if (isset($totales[$vendedor])) {
                    $totales[$vendedor] += $total;
                } else {
                    $totales[$vendedor] = $total;
                }
    
                // Actualizar total general
                $totales['TOTAL'] += $total;
            }
    
            // Convertir a colección para retornar
            $resultado = collect(array_values($resultadoFormateado));
            
            // Agregar la fila de totales
            $resultado->push($totales);
    
            // Log::info('Resultado final generado', [
            //     'num_filas' => $resultado->count(),
            //     'example_keys' => $resultado->first() ? array_keys($resultado->first()) : []
            // ]);
    
            return $resultado;
            
        } catch (\Exception $e) {
            Log::error('Error en collection de VentasPorCategoriaVendedorExport', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolver una estructura básica para evitar que la exportación falle completamente
            return collect([
                [
                    'Categoria' => 'Error al generar el reporte',
                    'TOTAL' => 0
                ]
            ]);
        }
    }
    
    public function headings(): array
    {
        try {
            // Log::info('Generando encabezados para el reporte');
            
            // Obtener los vendedores para las columnas
            $vendedores = DB::table('detalles_venta as dv')
                ->join('users as us', 'dv.id_vendedor', '=', 'us.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);
    
            // Aplicar filtro de sucursales si está definido
            if (!empty($this->sucursales)) {
                $vendedores->whereIn('vv.id_sucursal', $this->sucursales);
            }
    
            $vendedores = $vendedores->select('us.name')
                ->distinct()
                ->pluck('name')
                ->toArray();
    
            // Log::info('Vendedores para encabezados', ['vendedores' => $vendedores]);
    
            // Construir encabezados: Categoría, [Vendedores...], TOTAL
            $encabezados = ['Categoría'];
            foreach ($vendedores as $vendedor) {
                $encabezados[] = $vendedor;
            }
            $encabezados[] = 'TOTAL';
    
            // Log::info('Encabezados generados', ['encabezados' => $encabezados]);
            return $encabezados;
            
        } catch (\Exception $e) {
            Log::error('Error generando encabezados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolver encabezados básicos
            return ['Categoría', 'TOTAL'];
        }
    }
    
    public function map($fila): array
    {
        try {
            // Asegurarse de que la clave 'Categoria' exista
            if (!isset($fila['Categoria'])) {
                // Log::warning('Fila sin campo Categoria en map', ['keys' => array_keys($fila)]);
                $categoria = 'Sin categoría';
            } else {
                $categoria = $fila['Categoria'];
            }
            
            $resultado = [$categoria];
    
            // Obtener los encabezados para asegurarnos de recorrer las columnas en el orden correcto
            $encabezados = $this->headings();
            
            // Saltamos el primer encabezado que es 'Categoría'
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
