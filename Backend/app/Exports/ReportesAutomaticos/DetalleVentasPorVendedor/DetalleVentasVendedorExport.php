<?php

namespace App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor;

use App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor\DetalleVentasResumenSheet;
use App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor\DetalleVentasVendedorSheet;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DetalleVentasVendedorExport implements WithMultipleSheets
{

    use Exportable;

    public $fechaInicio;
    public $fechaFin;
    public $id_empresa;
    public $sucursales;
    public $detalleVentas;
    public $vendedoresUnicos;

    public function __construct($fechaInicio = null, $fechaFin = null, $id_empresa = null, $sucursales = null)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->id_empresa = $id_empresa;
        $this->sucursales = $sucursales;
        
        // Cargar los datos al inicializar para evitar múltiples consultas
        $this->cargarDatos();
    }
    
    /**
     * Carga los datos de ventas una sola vez para reutilizarlos en todas las hojas
     */
    private function cargarDatos()
    {
        try {
            // Consulta principal para obtener los detalles de ventas
            $query = DB::table('detalles_venta as dv')
                ->join('productos as pro', 'dv.id_producto', '=', 'pro.id')
                ->join('categorias as cat', 'pro.id_categoria', '=', 'cat.id')
                ->join('users as us', 'dv.id_vendedor', '=', 'us.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->leftJoin('clientes as cl', 'vv.id_cliente', '=', 'cl.id')
                ->leftJoin('sucursales as suc', 'vv.id_sucursal', '=', 'suc.id')
                ->leftJoin('documentos as doc', 'vv.id_documento', '=', 'doc.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);

            // Aplicar filtro de sucursales si está definido
            if (!empty($this->sucursales)) {
                $query->whereIn('vv.id_sucursal', $this->sucursales);
            }

            // Seleccionar los campos necesarios
            $this->detalleVentas = $query->select(
                'us.name as nombre_vendedor',
                'vv.correlativo',
                'doc.nombre as tipo_documento',
                'vv.fecha',
                DB::raw('DATE_FORMAT(vv.created_at, "%H:%i:%s") as hora'),
                'cl.nombre as nombre_cliente',
                'suc.nombre as nombre_sucursal',
                'cat.nombre as nombre_categoria',
                'pro.nombre as nombre_producto',
                'dv.cantidad',
                'dv.precio',
                'dv.descuento',
                DB::raw('(dv.cantidad * dv.precio) as subtotal'),
                DB::raw('(dv.cantidad * dv.precio - COALESCE(dv.descuento, 0)) as total_con_descuento')
            )
                ->orderBy('us.name')
                ->orderBy('vv.fecha')
                ->orderBy('vv.created_at')
                ->get();
                
            // Obtener lista de vendedores únicos
            $this->vendedoresUnicos = $this->detalleVentas->pluck('nombre_vendedor')->unique();
            
        } catch (\Exception $e) {
            Log::error('Error en cargarDatos de DetalleVentasVendedorMultisheetsExport', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Si hay error, inicializar con arrays vacíos para evitar errores
            $this->detalleVentas = collect([]);
            $this->vendedoresUnicos = collect([]);
        }
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        
        // Crear una hoja para cada vendedor
        foreach ($this->vendedoresUnicos as $vendedor) {
            $ventasVendedor = $this->detalleVentas->where('nombre_vendedor', $vendedor);
            $sheets[] = new DetalleVentasVendedorSheet($vendedor, $ventasVendedor);
        }
        
        // Crear la hoja de resumen
        $sheets[] = new DetalleVentasResumenSheet('Resumen', $this->detalleVentas, $this->vendedoresUnicos);
        
        return $sheets;
    }
}
