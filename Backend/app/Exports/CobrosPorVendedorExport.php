<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CobrosPorVendedorExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Vendedor',
            'Cliente',
            'Fecha Factura',
            'Correlativo',
            'Documento',
            'Total Factura',
            'Saldo Pendiente',
            'Estado',
            'Fecha Vencimiento',
            'Días de Vencimiento',
            'Fecha Último Abono',
            'Días de Crédito hasta Pago',
            'Total Abonado',
            'Sucursal'
        ];
    }

    public function collection()
    {
        $request = $this->request;
        $fechaActual = Carbon::now();

        $ventas = Venta::with(['vendedor', 'cliente', 'abonos' => function($query) {
                $query->where('estado', 'Confirmado')->orderBy('fecha', 'desc');
            }, 'documento', 'sucursal', 'devoluciones' => function($query) {
                $query->where('enable', 1);
            }])
            ->when($request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
            })
            ->when($request->id_sucursal && $request->id_sucursal !== '', function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->id_vendedor && $request->id_vendedor !== '', function ($query) use ($request) {
                return $query->where('id_vendedor', $request->id_vendedor);
            })
            ->where('cotizacion', 0)
            ->whereNotNull('id_vendedor')
            ->orderBy('id_vendedor')
            ->orderBy('fecha', 'desc')
            ->get();

        return $ventas;
    }

    public function map($venta): array
    {
        $fechaActual = Carbon::now();
        $fechaVenta = Carbon::parse($venta->fecha);
        
        // Calcular fecha de vencimiento
        $fechaVencimiento = null;
        if ($venta->fecha_pago) {
            $fechaVencimiento = Carbon::parse($venta->fecha_pago);
        } elseif ($venta->fecha_expiracion) {
            $fechaVencimiento = Carbon::parse($venta->fecha_expiracion);
        } else {
            // Por defecto, 30 días desde la fecha de la venta
            $fechaVencimiento = $fechaVenta->copy()->addDays(30);
        }

        // Calcular días de vencimiento
        // Si la fecha actual es mayor que la de vencimiento, está vencido (días positivos)
        // Si la fecha actual es menor, aún no vence (días negativos)
        $diasVencimiento = $fechaActual->diffInDays($fechaVencimiento, false);
        // diffInDays con false ya devuelve negativo si la primera fecha es menor
        // Necesitamos invertir para que positivo = vencido, negativo = no vencido
        if ($fechaActual->greaterThan($fechaVencimiento)) {
            // Está vencido: días positivos
            $diasVencimiento = $fechaVencimiento->diffInDays($fechaActual);
        } else {
            // Aún no vence: días negativos (restantes)
            $diasVencimiento = -$fechaActual->diffInDays($fechaVencimiento);
        }

        // Obtener último abono confirmado
        $ultimoAbono = $venta->abonos->first();
        $fechaUltimoAbono = $ultimoAbono ? Carbon::parse($ultimoAbono->fecha) : null;
        
        // Calcular días de crédito hasta el pago
        $diasCreditoHastaPago = null;
        if ($ultimoAbono) {
            $diasCreditoHastaPago = $fechaVenta->diffInDays($fechaUltimoAbono);
        }

        // Calcular total abonado
        $totalAbonado = $venta->abonos->sum('total');

        // Obtener nombre del vendedor
        $nombreVendedor = $venta->vendedor ? $venta->vendedor->name : 'Sin vendedor';
        
        // Obtener nombre del cliente
        $cliente = $venta->cliente;
        $nombreCliente = 'Consumidor Final';
        if ($cliente) {
            $nombreCliente = $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }

        // Obtener nombre del documento
        $nombreDocumento = $venta->documento ? $venta->documento->nombre : 'N/A';

        // Obtener nombre de la sucursal
        $nombreSucursal = $venta->sucursal ? $venta->sucursal->nombre : 'N/A';

        return [
            $nombreVendedor,
            $nombreCliente,
            $venta->fecha,
            $venta->correlativo,
            $nombreDocumento,
            round($venta->total, 2),
            round($venta->saldo, 2),
            $venta->estado,
            $fechaVencimiento ? $fechaVencimiento->format('Y-m-d') : 'N/A',
            $diasVencimiento,
            $fechaUltimoAbono ? $fechaUltimoAbono->format('Y-m-d') : 'Sin abonos',
            $diasCreditoHastaPago !== null ? $diasCreditoHastaPago : 'N/A',
            round($totalAbonado, 2),
            $nombreSucursal
        ];
    }
}
