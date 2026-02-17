<?php

namespace App\Exports\ReportesAutomaticos\VentasPorCategoriaPorVendedor;

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
            $this->sucursales = $configuracion->sucursales;
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
            // Obtener los datos
            $datos = $this->getData();
            $encabezados = $this->getHeadings();
            
            // Formatear los datos para la vista - ¡Esta línea es importante!
            $datosFormateados = $this->formatDataForPdf($datos);
            
            // Título del reporte
            $titulo = 'Ventas por Categoría y Vendedor - ' . $this->fechaInicio . ' al ' . $this->fechaFin;
            
            // Generar el PDF a través de una vista
            $pdf = app('dompdf.wrapper')->loadView('pdf.reportes-automaticos.ventas_categoria_vendedor_pdf', [
                'datos' => $datosFormateados,
                'encabezados' => $encabezados,
                'titulo' => $titulo
            ]);
            
            // Configurar opciones del PDF
            $pdf->setPaper('a4', 'landscape'); // Formato horizontal para tablas grandes
            
            // Para depuración: guardar el PDF localmente y ver su contenido
            // $path = storage_path('app/public/test.pdf');
            // $pdf->save($path);
            
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
            $categoriasIds = [];
            if ($this->configuracion && isset($this->configuracion->configuracion) && !empty($this->configuracion->configuracion)) {
                $categoriasIds = collect($this->configuracion->configuracion)->pluck('id')->toArray();
            }

            $query = DB::table('detalles_venta as dv')
                ->join('productos as pro', 'dv.id_producto', '=', 'pro.id')
                ->join('categorias as cat', 'pro.id_categoria', '=', 'cat.id')
                ->join('users as us', 'dv.id_vendedor', '=', 'us.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);
    
            if (!empty($categoriasIds)) {
                $query->whereIn('cat.id', $categoriasIds);
            }
            
            if (!empty($this->sucursales)) {
                $query->whereIn('vv.id_sucursal', $this->sucursales);
            }
    
            $ventasData = $query->select(
                'cat.id as id_categoria',
                'cat.nombre as nombre_categoria',
                'us.name as nombre_vendedor',
                'us.id as id_vendedor',
                // DB::raw('SUM(dv.total - COALESCE(dv.descuento, 0)) as total_ventas')
                DB::raw('SUM(dv.total) as total_ventas')
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
            Log::error('Error en getData de VentasPorCategoriaVendedorPdfExport', [
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
    
    public function getHeadings()
    {
        try {
            $categoriasIds = [];
            if ($this->configuracion && isset($this->configuracion->configuracion) && !empty($this->configuracion->configuracion)) {
                $categoriasIds = collect($this->configuracion->configuracion)->pluck('id')->toArray();
            }

            // Obtener las categorías para las columnas
            $categorias = DB::table('detalles_venta as dv')
                ->join('productos as pro', 'dv.id_producto', '=', 'pro.id')
                ->join('categorias as cat', 'pro.id_categoria', '=', 'cat.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);

            if (!empty($categoriasIds)) {
                $categorias->whereIn('cat.id', $categoriasIds);
            }
    
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

    // Este método formatea los datos para la vista PDF
    public function formatDataForPdf($datos)
    {
        $datosFormateados = [];
        
        foreach ($datos as $fila) {
            $filaFormateada = [];
            $filaFormateada['Vendedor'] = $fila['Vendedor'];
            
            // Recorrer todas las demás claves (categorías y total)
            foreach ($fila as $clave => $valor) {
                if ($clave != 'Vendedor' && $clave != 'id_vendedor') {
                    // Formatear valores numéricos
                    if (is_numeric($valor)) {
                        $filaFormateada[$clave] = number_format(round($valor, 2), 2);
                    } else {
                        $filaFormateada[$clave] = $valor;
                    }
                }
            }
            
            $datosFormateados[] = $filaFormateada;
        }
        
        return $datosFormateados;
    }
}