<?php

namespace App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use ZipArchive;

class DetalleVentasVendedorPdfExport
{
    public $fechaInicio;
    public $fechaFin;
    public $id_empresa;
    public $configuracion;
    public $sucursales;
    protected $maxRegistrosPorPdf = 1000;

    public function __construct($fechaInicio = null, $fechaFin = null, $id_empresa = null, $configuracion = null, $sucursales = null)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->id_empresa = $id_empresa;
        $this->configuracion = $configuracion;
        $this->sucursales = $sucursales;
    }

    public function download()
    {
        try {
            // Verificar que tenemos los datos necesarios
            if (empty($this->fechaInicio) || empty($this->fechaFin) || empty($this->id_empresa)) {
                Log::error('Faltan parámetros necesarios', [
                    'fechaInicio' => $this->fechaInicio,
                    'fechaFin' => $this->fechaFin,
                    'id_empresa' => $this->id_empresa
                ]);
                throw new Exception("Faltan parámetros necesarios para generar el reporte");
            }
            
            // Obtener los datos
            $datos = $this->getData();
            
            // Verificar si tenemos que usar paginación
            if ($datos->count() > $this->maxRegistrosPorPdf) {
                
                return $this->downloadPaginatedPDF($datos);
            }
            
            if ($datos->isEmpty()) {
                Log::warning('No se encontraron datos para el período seleccionado');
                
                // Generar PDF sin datos
                try {
                    $html = $this->generateNoDataHTML();
                    
                    $pdfFilePath = $this->generatePdf($html, 'P');

                    return response()->download($pdfFilePath, 'detalle_ventas_vendedor_sin_datos.pdf')
                        ->deleteFileAfterSend(true);
                } catch (Exception $e) {
                    Log::error('Error generando PDF sin datos', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    throw $e;
                }
            }
            
            $encabezados = $this->getHeadings();
            $datosFormateados = $this->formatDataForPdf($datos);
            $titulo = 'Detalle de Ventas por Vendedor - ' . $this->fechaInicio . ' al ' . $this->fechaFin;
            
            // Generar HTML para mPDF
            $html = $this->generateHtmlForMpdf($titulo, $encabezados, $datosFormateados);
            
            // Generar PDF
            try {
                $pdfFilePath = $this->generatePdf($html, 'L');
                
                return response()->download($pdfFilePath, 'detalle_ventas_vendedor_' . $this->fechaInicio . '_' . $this->fechaFin . '.pdf')
                    ->deleteFileAfterSend(true);
            } catch (Exception $e) {
                Log::error('Error generando PDF', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                throw $e;
            }
            
        } catch (Exception $e) {
            Log::error('Error generando PDF de Detalle de Ventas por Vendedor', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolver una respuesta de error
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }

    private function downloadPdfDirect($html, $filename, $orientation = 'P')
    {
        // Crear y configurar mPDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'tempDir' => storage_path('app/public/temp')
        ]);
        
        $mpdf->WriteHTML($html);
        
        // Enviar directamente al navegador
        $pdfContent = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Genera un PDF y devuelve la ruta al archivo
     */
    private function generatePdf($html, $orientation = 'P')
    {
        // Asegurar que existe el directorio temporal
        $tempDir = storage_path('app/public/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        // Usar una configuración más básica
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 9,
            'margin_footer' => 9,
            'tempDir' => $tempDir,
            'fontDir' => array_merge($fontDirs, [
                resource_path('fonts')
            ]),
            'fontdata' => $fontData,
            // Usar una fuente estándar
            'default_font' => 'dejavusans'
        ]);
        
        // Desactivar opciones que pueden causar problemas
        $mpdf->showImageErrors = false;
        $mpdf->simpleTables = true;
        $mpdf->packTableData = true;
        $mpdf->shrink_tables_to_fit = 1;
        
        // Escribir el HTML al PDF
        $mpdf->WriteHTML($html);
        
        // Guardar el PDF en un archivo temporal
        $outputPath = storage_path('app/public/temp/report_' . time() . '.pdf');
        $mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);
        
        // Verificar que el archivo existe y tiene contenido válido
        if (!file_exists($outputPath) || filesize($outputPath) < 100) {
            throw new Exception("Error al generar el PDF: el archivo está vacío o corrupto");
        }
        
        return $outputPath;
    }

    /**
     * Genera un PDF paginado para grandes volúmenes de datos y lo comprime en ZIP
     */
    private function downloadPaginatedPDF($datos)
    {
        ini_set('memory_limit', '4G'); // Double the current value
        ini_set('max_execution_time', 1800);
        
        try {
            // Crear un zip para almacenar los PDFs
            $zipPath = storage_path('app/public/detalle_ventas_' . time() . '.zip');
            $zip = new ZipArchive();
            
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("No se pudo crear el archivo ZIP");
            }
            
            // Tamaño del lote (registros por PDF)
            $batchSize = $this->maxRegistrosPorPdf;
            
            // Agrupar por vendedor para mantener los totales juntos
            $datosPorVendedor = [];
            
            foreach ($datos as $index => $fila) {
                // Si no es una fila de total, agrupar por vendedor
                if (!isset($fila['es_total']) || !$fila['es_total']) {
                    if (!isset($datosPorVendedor[$fila['vendedor']])) {
                        $datosPorVendedor[$fila['vendedor']] = [];
                    }
                    $datosPorVendedor[$fila['vendedor']][] = $fila;
                }
            }
            
            // Generar los totales por vendedor y el total general
            $vendedoresTotales = [];
            $totalGeneral = [
                'subtotal' => 0,
                'descuento' => 0,
                'total' => 0
            ];
            
            foreach ($datosPorVendedor as $vendedor => $filas) {
                $totalVendedor = [
                    'subtotal' => 0,
                    'descuento' => 0,
                    'total' => 0
                ];
                
                foreach ($filas as $fila) {
                    $totalVendedor['subtotal'] += $fila['subtotal'];
                    $totalVendedor['descuento'] += $fila['descuento'] ?? 0;
                    $totalVendedor['total'] += $fila['total'];
                }
                
                $vendedoresTotales[$vendedor] = $totalVendedor;
                
                $totalGeneral['subtotal'] += $totalVendedor['subtotal'];
                $totalGeneral['descuento'] += $totalVendedor['descuento'];
                $totalGeneral['total'] += $totalVendedor['total'];
            }
            
            // Dividir los datos en lotes por vendedor
            $lotes = [];
            $loteActual = 0;
            
            foreach ($datosPorVendedor as $vendedor => $filas) {
                // Si las filas de este vendedor exceden el tamaño del lote, dividirlas
                $chunks = array_chunk($filas, $batchSize);
                
                foreach ($chunks as $index => $chunk) {
                    if (!isset($lotes[$loteActual])) {
                        $lotes[$loteActual] = [];
                    }
                    
                    // Agregar las filas de datos
                    foreach ($chunk as $fila) {
                        $lotes[$loteActual][] = $fila;
                    }
                    
                    // Si es el último chunk del vendedor, agregar el total del vendedor
                    if ($index == count($chunks) - 1) {
                        $lotes[$loteActual][] = [
                            'vendedor' => 'TOTAL ' . $vendedor,
                            'correlativo' => '',
                            'fecha' => '',
                            'producto' => '',
                            'cantidad' => '',
                            'precio' => '',
                            'descuento' => $vendedoresTotales[$vendedor]['descuento'],
                            'subtotal' => $vendedoresTotales[$vendedor]['subtotal'],
                            'total' => $vendedoresTotales[$vendedor]['total'],
                            'es_total' => true
                        ];
                    }
                    
                    // Pasar al siguiente lote si este ya está lleno
                    if (count($lotes[$loteActual]) >= $batchSize) {
                        $loteActual++;
                    }
                }
            }
            
            // Agregar el total general al último lote
            if (!empty($lotes)) {
                $ultimoLote = count($lotes) - 1;
                $lotes[$ultimoLote][] = [
                    'vendedor' => 'TOTAL GENERAL',
                    'correlativo' => '',
                    'fecha' => '',
                    'producto' => '',
                    'cantidad' => '',
                    'precio' => '',
                    'descuento' => $totalGeneral['descuento'],
                    'subtotal' => $totalGeneral['subtotal'],
                    'total' => $totalGeneral['total'],
                    'es_total' => true
                ];
            }
            
            // Generar un PDF para cada lote
            $encabezados = $this->getHeadings();
            $tempFiles = [];
            
            foreach ($lotes as $index => $lote) {
                $numParte = $index + 1;
                $titulo = 'Detalle de Ventas por Vendedor - ' . $this->fechaInicio . ' al ' . $this->fechaFin . ' (Parte ' . $numParte . ')';
                
                // Formatear los datos para el PDF
                $datosFormateados = $this->formatDataForPdf(collect($lote));
                
                // Generar HTML para mPDF
                $html = $this->generateHtmlForMpdf($titulo, $encabezados, $datosFormateados);
                
                // Generar PDF
                try {
                    $pdfPath = $this->generatePdf($html, 'L');
                    $tempFiles[] = $pdfPath;
                    
                    // Agregar al ZIP
                    $nombreArchivo = 'detalle_ventas_parte_' . $numParte . '.pdf';
                    $zip->addFile($pdfPath, $nombreArchivo);
                } catch (Exception $e) {
                    Log::error('Error generando PDF parte ' . $numParte, [
                        'error' => $e->getMessage()
                    ]);
                    // Continuar con las demás partes
                }
            }
            
            // Cerrar el ZIP
            $zip->close();
            
            // Eliminar los PDFs individuales después de un tiempo para asegurar que el ZIP se creó correctamente
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
            // Verificar que el ZIP se creó correctamente y tiene contenido
            if (!file_exists($zipPath) || filesize($zipPath) < 100) {
                throw new Exception("Error al generar el archivo ZIP: el archivo está vacío o corrupto");
            }
            
            // Descargar el ZIP
            return response()->download($zipPath, 'detalle_ventas_vendedor_' . $this->fechaInicio . '_' . $this->fechaFin . '.zip')
                ->deleteFileAfterSend(true);
                
        } catch (Exception $e) {
            Log::error('Error generando PDFs paginados', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Error al generar los PDFs paginados: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Genera el HTML para mPDF sin datos
     */
    private function generateNoDataHTML()
    {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Detalle de Ventas - Sin Datos</title>
            <style>
                body { 
                    font-family: sans-serif; 
                    font-size: 12pt; 
                    text-align: center; 
                    margin: 2cm; 
                }
                h1 { 
                    margin-top: 3cm; 
                    font-size: 18pt; 
                }
                p { 
                    margin-top: 1cm; 
                    font-size: 12pt; 
                }
            </style>
        </head>
        <body>
            <h1>Detalle de Ventas por Vendedor</h1>
            <p>No se encontraron datos para el período del ' . $this->fechaInicio . ' al ' . $this->fechaFin . '.</p>
        </body>
        </html>';
    }
    
    /**
     * Genera el HTML para mPDF con los datos
     */
    private function generateHtmlForMpdf($titulo, $encabezados, $datos)
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>' . htmlspecialchars($titulo) . '</title>
            <style>
                body { 
                    font-family: sans-serif; 
                    font-size: 8pt; 
                    margin: 0; 
                    padding: 0; 
                }
                h1 { 
                    text-align: center; 
                    font-size: 12pt; 
                    margin-bottom: 10mm; 
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 5mm; 
                }
                th { 
                    background-color: #f2f2f2; 
                    font-weight: bold; 
                    text-align: left; 
                    font-size: 8pt; 
                    padding: 2mm; 
                    border: 0.1mm solid #000000; 
                }
                td { 
                    font-size: 7pt; 
                    padding: 1.5mm; 
                    border: 0.1mm solid #000000; 
                }
                .total-row { 
                    font-weight: bold; 
                    background-color: #f9f9f9; 
                }
                .total-general { 
                    font-weight: bold; 
                    background-color: #e9e9e9; 
                }
                .numeric { 
                    text-align: right; 
                }
                .date { 
                    text-align: center; 
                }
                
                /* Settings for page breaks */
                thead { display: table-header-group; }
                tfoot { display: table-footer-group; }
                tr { page-break-inside: avoid; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($titulo) . '</h1>
            
            <table>
                <thead>
                    <tr>';
        
        // Encabezados de la tabla
        foreach ($encabezados as $encabezado) {
            $html .= '<th>' . htmlspecialchars($encabezado) . '</th>';
        }
        
        $html .= '</tr>
                </thead>
                <tbody>';
        
        // Datos de la tabla
        if (count($datos) > 0) {
            foreach ($datos as $fila) {
                $rowClass = '';
                if (isset($fila['es_total']) && $fila['es_total']) {
                    if (isset($fila['vendedor']) && strpos($fila['vendedor'], 'GENERAL') !== false) {
                        $rowClass = ' class="total-general"';
                    } else {
                        $rowClass = ' class="total-row"';
                    }
                }
                
                $html .= '<tr' . $rowClass . '>';
                
                // Vendedor
                $html .= '<td>' . htmlspecialchars($fila['vendedor'] ?? '') . '</td>';
                
                // Correlativo
                $html .= '<td>' . htmlspecialchars($fila['correlativo'] ?? '') . '</td>';
                
                // Fecha
                $html .= '<td class="date">' . htmlspecialchars($fila['fecha'] ?? '') . '</td>';
                
                // Producto (posiblemente largo)
                $html .= '<td>' . htmlspecialchars($fila['producto'] ?? '') . '</td>';
                
                // Cantidad
                $html .= '<td class="numeric">' . htmlspecialchars($fila['cantidad'] ?? '') . '</td>';
                
                // Precio
                $html .= '<td class="numeric">' . htmlspecialchars($fila['precio'] ?? '') . '</td>';
                
                // Descuento
                $html .= '<td class="numeric">' . htmlspecialchars($fila['descuento'] ?? '') . '</td>';
                
                // Subtotal
                $html .= '<td class="numeric">' . htmlspecialchars($fila['subtotal'] ?? '') . '</td>';
                
                // Total
                $html .= '<td class="numeric">' . htmlspecialchars($fila['total'] ?? '') . '</td>';
                
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="' . count($encabezados) . '" style="text-align: center;">No hay datos disponibles</td></tr>';
        }
        
        $html .= '</tbody>
            </table>
        </body>
        </html>';
        
        return $html;
    }
    
    public function getData()
    {
        try {
            // Consulta principal para obtener los detalles de ventas
            $query = DB::table('detalles_venta as dv')
                ->join('productos as pro', 'dv.id_producto', '=', 'pro.id')
                ->join('users as us', 'dv.id_vendedor', '=', 'us.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);
    
            // Aplicar filtro de sucursales si está definido
            if (!empty($this->sucursales)) {
                $query->whereIn('vv.id_sucursal', $this->sucursales);
            }
    
            // Seleccionar los campos necesarios según la estructura requerida
            $ventasData = $query->select(
                'us.name as vendedor',
                'vv.correlativo',
                'vv.fecha',
                'pro.nombre as producto',
                'dv.cantidad',
                'dv.precio',
                'dv.descuento',
                DB::raw('(dv.cantidad * dv.precio) as subtotal'),
                DB::raw('(dv.cantidad * dv.precio - COALESCE(dv.descuento, 0)) as total')
            )
                ->orderBy('us.name')
                ->orderBy('vv.fecha')
                ->orderBy('vv.correlativo')
                ->get();
    
            // Si no hay datos, devolver colección vacía
            if ($ventasData->isEmpty()) {
                Log::warning('No se encontraron datos para el período seleccionado');
                return collect([]);
            }
    
            // Calcular totales por vendedor
            $vendedoresTotales = [];
            foreach ($ventasData as $venta) {
                $vendedor = $venta->vendedor;
                if (!isset($vendedoresTotales[$vendedor])) {
                    $vendedoresTotales[$vendedor] = [
                        'subtotal' => 0,
                        'descuento' => 0,
                        'total' => 0
                    ];
                }
                
                $vendedoresTotales[$vendedor]['subtotal'] += $venta->subtotal;
                $vendedoresTotales[$vendedor]['descuento'] += $venta->descuento ?? 0;
                $vendedoresTotales[$vendedor]['total'] += $venta->total;
            }
    
            // Agregar totales al final de cada grupo de vendedor
            $resultadoFinal = [];
            $vendedorActual = null;
            
            foreach ($ventasData as $venta) {
                // Si cambiamos de vendedor y ya teníamos uno anterior, agregar fila de totales
                if ($vendedorActual !== null && $vendedorActual !== $venta->vendedor) {
                    $resultadoFinal[] = [
                        'vendedor' => 'TOTAL ' . $vendedorActual,
                        'correlativo' => '',
                        'fecha' => '',
                        'producto' => '',
                        'cantidad' => '',
                        'precio' => '',
                        'descuento' => $vendedoresTotales[$vendedorActual]['descuento'],
                        'subtotal' => $vendedoresTotales[$vendedorActual]['subtotal'],
                        'total' => $vendedoresTotales[$vendedorActual]['total'],
                        'es_total' => true
                    ];
                }
                
                // Actualizar vendedor actual
                $vendedorActual = $venta->vendedor;
                
                // Agregar fila de datos
                $resultadoFinal[] = [
                    'vendedor' => $venta->vendedor,
                    'correlativo' => $venta->correlativo,
                    'fecha' => $venta->fecha,
                    'producto' => $venta->producto,
                    'cantidad' => $venta->cantidad,
                    'precio' => $venta->precio,
                    'descuento' => $venta->descuento,
                    'subtotal' => $venta->subtotal,
                    'total' => $venta->total,
                    'es_total' => false
                ];
            }
            
            // Agregar totales del último vendedor si hay datos
            if ($vendedorActual !== null) {
                $resultadoFinal[] = [
                    'vendedor' => 'TOTAL ' . $vendedorActual,
                    'correlativo' => '',
                    'fecha' => '',
                    'producto' => '',
                    'cantidad' => '',
                    'precio' => '',
                    'descuento' => $vendedoresTotales[$vendedorActual]['descuento'],
                    'subtotal' => $vendedoresTotales[$vendedorActual]['subtotal'],
                    'total' => $vendedoresTotales[$vendedorActual]['total'],
                    'es_total' => true
                ];
            }
    
            // Agregar fila de total general al final
            $totalGeneral = [
                'vendedor' => 'TOTAL GENERAL',
                'correlativo' => '',
                'fecha' => '',
                'producto' => '',
                'cantidad' => '',
                'precio' => '',
                'descuento' => array_sum(array_column($vendedoresTotales, 'descuento')),
                'subtotal' => array_sum(array_column($vendedoresTotales, 'subtotal')),
                'total' => array_sum(array_column($vendedoresTotales, 'total')),
                'es_total' => true
            ];
            
            $resultadoFinal[] = $totalGeneral;
    
            return collect($resultadoFinal);
            
        } catch (Exception $e) {
            Log::error('Error en getData de DetalleVentasVendedorPdfExport', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolver colección vacía en lugar de null
            return collect([]);
        }
    }
    
    public function getHeadings()
    {
        return [
            'Vendedor', 
            'Correlativo', 
            'Fecha', 
            'Producto', 
            'Cantidad', 
            'Precio', 
            'Descuento', 
            'Subtotal', 
            'Total'
        ];
    }

    // Este método formatea los datos para el PDF
    public function formatDataForPdf($datos)
    {
        $datosFormateados = [];
        
        foreach ($datos as $fila) {
            $filaFormateada = [];
            
            // Formatear cada campo según su tipo
            $filaFormateada['vendedor'] = $fila['vendedor'] ?? '';
            $filaFormateada['correlativo'] = $fila['correlativo'] ?? '';
            $filaFormateada['fecha'] = $fila['fecha'] ?? '';
            $filaFormateada['producto'] = $fila['producto'] ?? '';
            
            // Formatear valores numéricos
            $filaFormateada['cantidad'] = is_numeric($fila['cantidad'] ?? '') ? number_format((float)$fila['cantidad'], 2) : $fila['cantidad'] ?? '';
            $filaFormateada['precio'] = is_numeric($fila['precio'] ?? '') ? number_format((float)$fila['precio'], 2) : $fila['precio'] ?? '';
            $filaFormateada['descuento'] = is_numeric($fila['descuento'] ?? '') ? number_format((float)$fila['descuento'], 2) : $fila['descuento'] ?? '';
            $filaFormateada['subtotal'] = is_numeric($fila['subtotal'] ?? '') ? number_format((float)$fila['subtotal'], 2) : $fila['subtotal'] ?? '';
            $filaFormateada['total'] = is_numeric($fila['total'] ?? '') ? number_format((float)$fila['total'], 2) : $fila['total'] ?? '';
            
            // Agregar bandera para aplicar estilos diferentes a filas de totales
            $filaFormateada['es_total'] = $fila['es_total'] ?? false;
            
            $datosFormateados[] = $filaFormateada;
        }
        
        return $datosFormateados;
    }
}