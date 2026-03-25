<?php

namespace App\Exports;

use App\Models\Admin\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentasPorCategoriaVendedorPdfExport
{
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

        if ($configuracion && isset($configuracion->sucursales) && !empty($configuracion->sucursales)) {
            $this->sucursalesData = Sucursal::whereIn('id', $configuracion->sucursales)->get()->keyBy('id');
        } else {
            $this->sucursalesData = Sucursal::where('id_empresa', $id_empresa)->get()->keyBy('id');
            if ($configuracion) {
                $configuracion->sucursales = $this->sucursalesData->pluck('id')->toArray();
            }
        }
    }

    public function download()
    {
        try {
            // Obtener los datos usando el mismo método que en la exportación Excel
            $datos = $this->getData();
            $encabezados = $this->getHeadings();
            
            // Título del reporte
            $titulo = 'Ventas por Categoría y Vendedor - ' . $this->fechaInicio . ' al ' . $this->fechaFin;
            
            // Generar el PDF a través de una vista
            $pdf = app('dompdf.wrapper')->loadView('exports.ventas_categoria_vendedor_pdf', [
                'datos' => $datos,
                'encabezados' => $encabezados,
                'titulo' => $titulo
            ]);
            
            // Configurar opciones del PDF
            $pdf->setPaper('a4', 'landscape'); // Formato horizontal para tablas grandes
            
            // Descargar el PDF
            return $pdf->download('ventas_categoria_vendedor_' . $this->fechaInicio . '_' . $this->fechaFin . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error generando PDF de Ventas por Categoría y Vendedor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolver una respuesta de error
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }

    public function getData()
    {
        try {
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
                DB::raw('SUM(dv.total - COALESCE(dv.descuento, 0)) as total_ventas')
            )
                ->groupBy('cat.id', 'cat.nombre', 'us.name')
                ->orderBy('cat.nombre')
                ->get();
    
            // Obtener lista de vendedores únicos
            $vendedores = $ventasData->pluck('nombre_vendedor')->unique()->values()->toArray();
    
            // Obtener lista de categorías únicas
            $categorias = $ventasData->pluck('nombre_categoria', 'id_categoria')->unique();
    
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
    
            return $resultado;
            
        } catch (\Exception $e) {
            Log::error('Error en getData de VentasPorCategoriaVendedorPdfExport', [
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
    
    public function getHeadings()
    {
        try {
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
    
            // Construir encabezados: Categoría, [Vendedores...], TOTAL
            $encabezados = ['Categoría'];
            foreach ($vendedores as $vendedor) {
                $encabezados[] = $vendedor;
            }
            $encabezados[] = 'TOTAL';
    
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
}