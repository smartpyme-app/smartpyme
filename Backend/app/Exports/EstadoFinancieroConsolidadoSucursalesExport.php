<?php

namespace App\Exports;

use App\Models\Admin\Sucursal;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EstadoFinancieroConsolidadoSucursalesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public $fechaInicio;
    public $fechaFin;
    public $id_empresa;

    public function __construct($fechaInicio = null, $fechaFin = null, $id_empresa = null)
    {
        $this->fechaInicio = $fechaInicio ?? Carbon::today()->format('Y-m-d');
        $this->fechaFin = $fechaFin ?? Carbon::today()->format('Y-m-d');
        $this->id_empresa = $id_empresa;
        
        Log::info('Generando Estado Financiero Consolidado: ', [
            'fechaInicio' => $this->fechaInicio,
            'fechaFin' => $this->fechaFin,
            'empresa' => $this->id_empresa
        ]);
    }

    public function title(): string
    {
        return 'Estado Financiero Consolidado - ' . $this->fechaInicio . ' al ' . $this->fechaFin;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para los encabezados
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
            // Estilo para la columna de sucursales
            'A' => ['font' => ['bold' => true]],
            // Estilo para la columna de balance
            'D' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8F5E9']]],
        ];
    }

    public function collection()
    {
        // Obtenemos todas las sucursales de la empresa
        $sucursales = Sucursal::where('id_empresa', $this->id_empresa)
            ->where('activo', 1)
            ->orderBy('nombre', 'asc')
            ->get();
        
        $resultados = collect();
        
        foreach ($sucursales as $sucursal) {
            // Calculamos las ventas de la sucursal en el período
            $ventas = Venta::where('id_sucursal', $sucursal->id)
                ->whereBetween('fecha', [$this->fechaInicio, $this->fechaFin])
                ->where('id_empresa', $this->id_empresa)
                ->where('cotizacion', 0)
                ->sum('total');
                
            // Calculamos los gastos de la sucursal en el período
            $gastos = Gasto::where('id_sucursal', $sucursal->id)
                ->whereBetween('fecha', [$this->fechaInicio, $this->fechaFin])
                ->where('id_empresa', $this->id_empresa)
                ->sum('total');
                
            // Calculamos el balance (ventas - gastos)
            $balance = $ventas - $gastos;
            
            // Agregamos los datos a la colección
            $resultados->push([
                'sucursal' => $sucursal->nombre,
                'ventas' => $ventas,
                'gastos' => $gastos,
                'balance' => $balance
            ]);
        }
        
        // Añadimos una fila para el total
        $totalVentas = $resultados->sum('ventas');
        $totalGastos = $resultados->sum('gastos');
        $totalBalance = $totalVentas - $totalGastos;
        
        $resultados->push([
            'sucursal' => 'TOTAL',
            'ventas' => $totalVentas,
            'gastos' => $totalGastos,
            'balance' => $totalBalance
        ]);
        
        return $resultados;
    }

    public function headings(): array
    {
        return [
            'Sucursal',
            'Ventas',
            'Gastos',
            'Balance'
        ];
    }

    public function map($fila): array
    {
        return [
            $fila['sucursal'],
            '$' . number_format($fila['ventas'], 2),
            '$' . number_format($fila['gastos'], 2),
            '$' . number_format($fila['balance'], 2)
        ];
    }
}