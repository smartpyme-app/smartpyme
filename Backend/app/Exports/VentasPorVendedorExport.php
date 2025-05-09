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

class VentasPorVendedorExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public $request;
    public $fechaInicio;
    public $fechaFin;
    public $id_empresa;

    public function __construct($fechaInicio = null, $fechaFin = null, $id_empresa = null)
    {
        $this->fechaInicio = $fechaInicio ?? Carbon::today()->format('Y-m-d');
        $this->fechaFin = $fechaFin ?? Carbon::today()->format('Y-m-d');
        $this->id_empresa = $id_empresa;
    }

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Ventas por Vendedor - ' . $this->fechaInicio . ' al ' . $this->fechaFin;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para los encabezados
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
        ];
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Cliente',
            'DUI',
            'NIT',
            'Producto',
            'SKU',
            'Marca',
            'Categoria',
            'Documento',
            'Correlativo',
            'Forma de pago',
            'Banco',
            'Estado',
            'Canal',
            'Cantidad',
            'Costo',
            'Precio',
            'Descuento',
            'IVA',
            'Utilidad',
            'Total',
            'Empresa',
            'Observaciones',
            'Usuario',
            'Vendedor',
            'Hora',
        ];
    }

    // public function collection()
    // {



    //     $detalles = Detalle::whereHas('venta', function ($query) {
    //         $query->where('fecha', '>=', $this->fechaInicio)
    //             ->where('fecha', '<=', $this->fechaFin)
    //             ->where('cotizacion', 0)
    //             ->where('id_empresa', $this->id_empresa)
    //             ->orderBy('id_vendedor', 'asc')
    //             ->orderBy('id', 'asc');
    //     })->get();


    //     return $detalles;
    // }

    public function collection()
    {
        $fechaInicio = $this->fechaInicio;
        $fechaFin = $this->fechaFin;
        $id_empresa = $this->id_empresa;

        $detalles = Detalle::whereHas('venta', function ($query) use ($fechaInicio, $fechaFin, $id_empresa) {
            $query->where('fecha', '>=', $fechaInicio)
                ->where('fecha', '<=', $fechaFin)
                ->where('cotizacion', 0)
                ->where('estado', '!=', 'Anulada');

            if ($id_empresa) {
                $query->where('id_empresa', $id_empresa);
            }

            $query->orderBy('id_vendedor', 'asc')
                ->orderBy('id', 'asc');
        })->get();

        return $detalles;
    }

    public function map($row): array
    {
        $venta = $row->venta()->first();
        $hora = $venta ? Carbon::parse($venta->created_at)->format('H:i:s') : '';

        $fields = [
            $venta ? $venta->fecha : '',
            $venta ? ($venta->nombre_cliente ?? 'Consumidor Final') : 'Consumidor Final',
            $venta && $venta->cliente ? $venta->cliente->dui : '',
            $venta && $venta->cliente ? $venta->cliente->nit : '',
            $row->producto ? $row->producto->nombre : '',
            $row->producto ? $row->producto->codigo : '',
            $row->producto ? $row->producto->marca : '',
            $row->producto && $row->producto->categoria ? $row->producto->categoria->nombre : '',
            $venta && $venta->documento ? $venta->documento->nombre : '',
            $venta ? $venta->correlativo : '',
            $venta ? $venta->forma_pago : '',
            $venta ? $venta->detalle_banco : '',
            $venta ? $venta->estado : '',
            $venta && $venta->canal ? $venta->canal->nombre : '',
            $row->cantidad,
            round($row->costo, 2),
            round($row->precio, 2),
            round($row->descuento, 2),
            round($venta && $venta->iva ? $row->total * 0.13 : 0, 2),
            round($row->total - ($row->costo * $row->cantidad), 2),
            round($row->total + ($venta && $venta->iva ? $row->total * 0.13 : 0), 2),
            $venta && $venta->sucursal && $venta->sucursal->empresa ? $venta->sucursal->empresa->nombre : '',
            $venta ? $venta->observaciones : '',
            $venta && $venta->usuario ? $venta->usuario->name : '',
            $venta && $venta->vendedor ? $venta->vendedor->name : 'Sin vendedor',
            $hora,
        ];

        return $fields;
    }
}
