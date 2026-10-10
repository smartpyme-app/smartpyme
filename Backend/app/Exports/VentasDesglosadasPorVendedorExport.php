<?php

namespace App\Exports;

use App\Exports\Support\DevolucionEnReporte;
use App\Models\Ventas\Venta;
use App\Services\Ventas\VentaMontosPorVendedorService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ventas del período, una fila por vendedor de línea.
 * Los montos de la cabecera se parten según el peso de cada vendedor.
 * Las devoluciones del período se restan con el mismo reparto de la venta original.
 */
class VentasDesglosadasPorVendedorExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    private VentasExport $baseExport;

    public function __construct($request = null)
    {
        $this->baseExport = new VentasExport($request);
    }

    public function filter(Request $request): void
    {
        $this->baseExport->filter($request);
    }

    public function headings(): array
    {
        return $this->baseExport->headings();
    }

    public function collection()
    {
        $filas = collect();

        foreach ($this->baseExport->query()->get() as $venta) {
            if ($venta->estado === 'Anulada') {
                $filas->push((object) [
                    'venta' => $venta,
                    'venta_origen' => null,
                    'es_devolucion' => false,
                    'grupo' => $this->grupoCero($venta),
                ]);
                continue;
            }

            foreach (VentaMontosPorVendedorService::montosPorVendedor($venta) as $grupo) {
                $filas->push((object) [
                    'venta' => $venta,
                    'venta_origen' => null,
                    'es_devolucion' => false,
                    'grupo' => $this->escalarCabecera($venta, $grupo),
                ]);
            }
        }

        foreach ($this->baseExport->queryDevoluciones()->get() as $devolucion) {
            if ($devolucion->relationLoaded('detalles')) {
                $devolucion->total_costo = $devolucion->detalles->sum(function ($detalle) {
                    return ((float) $detalle->cantidad) * ((float) $detalle->costo);
                });
            }

            $montos = DevolucionEnReporte::montosVentaNegados($devolucion);
            $origen = $devolucion->venta;
            $grupos = $origen
                ? VentaMontosPorVendedorService::montosPorVendedor($origen)
                : [[
                    'vendedor_id' => 0,
                    'vendedor_nombre' => 'Sin vendedor',
                    'share' => 1.0,
                ]];

            foreach ($grupos as $grupo) {
                $share = (float) ($grupo['share'] ?? 1);
                $filas->push((object) [
                    'venta' => $devolucion,
                    'venta_origen' => $origen,
                    'es_devolucion' => true,
                    'grupo' => [
                        'vendedor_nombre' => $grupo['vendedor_nombre'],
                        'total_costo' => $montos['costo'] * $share,
                        'sub_total' => $montos['sub_total'] * $share,
                        'descuento' => $montos['descuento'] * $share,
                        'iva' => $montos['iva'] * $share,
                        'total_sin_iva' => $montos['total_sin_iva'] * $share,
                        'total' => $montos['total'] * $share,
                        'utilidad' => $montos['utilidad'] * $share,
                        'share' => -$share,
                    ],
                ]);
            }
        }

        return $filas;
    }

    private function grupoCero(Venta $venta): array
    {
        return [
            'vendedor_nombre' => $venta->vendedor?->name ?? 'Sin vendedor',
            'total_costo' => 0.0,
            'sub_total' => 0.0,
            'descuento' => 0.0,
            'iva' => 0.0,
            'total_sin_iva' => 0.0,
            'total' => 0.0,
            'utilidad' => 0.0,
            'share' => 1.0,
        ];
    }

    private function escalarCabecera(Venta $venta, array $grupo): array
    {
        $share = (float) $grupo['share'];
        $subTotal = (float) ($venta->sub_total ?? 0);
        $descuento = (float) ($venta->descuento ?? 0);
        $iva = (float) ($venta->iva ?? 0);
        $total = (float) ($venta->total ?? 0);
        $totalCosto = (float) ($venta->total_costo ?? 0);

        return [
            'vendedor_nombre' => $grupo['vendedor_nombre'],
            'total_costo' => $totalCosto * $share,
            'sub_total' => $subTotal * $share,
            'descuento' => $descuento * $share,
            'iva' => $iva * $share,
            'total_sin_iva' => max(0, $subTotal - $descuento) * $share,
            'total' => $total * $share,
            'utilidad' => ($total - $totalCosto - $iva) * $share,
            'share' => $share,
        ];
    }

    public function map($row): array
    {
        /** @var Venta $venta */
        $venta = $row->venta;
        /** @var array $grupo */
        $grupo = $row->grupo;

        $cliente = $venta->relationLoaded('cliente') ? $venta->cliente : null;
        $sucursal = $venta->relationLoaded('sucursal') ? $venta->sucursal : null;
        $empresa = ($sucursal && $sucursal->relationLoaded('empresa')) ? $sucursal->empresa : null;
        $usuario = $venta->relationLoaded('usuario') ? $venta->usuario : null;
        $documento = $venta->relationLoaded('documento') ? $venta->documento : null;
        $canal = $venta->relationLoaded('canal') ? $venta->canal : null;
        $proyecto = $venta->relationLoaded('proyecto') ? $venta->proyecto : null;

        $nombreCliente = 'Consumidor Final';
        if ($cliente) {
            $nombreCliente = ($cliente->tipo == 'Empresa')
                ? $cliente->nombre_empresa
                : trim($cliente->nombre . ' ' . $cliente->apellido);
        }

        $esDevolucion = !empty($row->es_devolucion);
        $origen = $row->venta_origen ?? null;
        $datosVenta = ($esDevolucion && $origen) ? $origen : $venta;
        if ($esDevolucion && $origen) {
            $canal = $origen->relationLoaded('canal') ? $origen->canal : null;
            $proyecto = $origen->relationLoaded('proyecto') ? $origen->proyecto : null;
        }

        $share = (float) ($grupo['share'] ?? 1);
        $cuentaTerceros = ($venta->cuenta_a_terceros ?? 0) * $share;
        $propina = ($venta->propina ?? 0) * $share;
        $anulada = !$esDevolucion && $venta->estado === 'Anulada';

        $fila = [
            $venta->fecha,
            $nombreCliente,
            $cliente ? $cliente->telefono : '',
            $cliente ? $cliente->dui : '',
            $cliente ? $cliente->nit : '',
            $cliente ? $cliente->direccion : '',
            ($documento && isset($documento->nombre)) ? $documento->nombre : ($esDevolucion ? 'Devolución' : ''),
            ($proyecto && isset($proyecto->nombre)) ? $proyecto->nombre : '',
            $datosVenta->num_identificacion ?? '',
            $venta->correlativo,
            $datosVenta->forma_pago ?? '',
            $datosVenta->detalle_banco ?? '',
            $esDevolucion ? DevolucionEnReporte::ESTADO : $venta->estado,
            VentasExport::motivoAnulacionParaExport($esDevolucion ? ($origen ?: $venta) : $venta),
            ($canal && isset($canal->nombre)) ? $canal->nombre : '',
            $anulada ? '0.0' : round($grupo['total_costo'], 2),
            round($cuentaTerceros, 2),
            $anulada ? '0.0' : round($grupo['sub_total'], 2),
            $anulada ? '0.0' : round($grupo['descuento'], 2),
            $anulada ? '0.0' : round($grupo['iva'], 2),
            $anulada ? '0.0' : round($grupo['utilidad'], 2),
            $anulada ? '0.0' : round($grupo['total_sin_iva'], 2),
            $anulada ? '0.0' : round($grupo['total'], 2),
            $anulada ? '0.0' : round($propina, 2),
            $empresa ? $empresa->nombre : '',
            $venta->observaciones,
            ($usuario && isset($usuario->name)) ? $usuario->name : '',
            $grupo['vendedor_nombre'],
        ];

        if ($this->baseExport->tieneModuloPaquetes) {
            $paquete = $venta->relationLoaded('paquetes') ? $venta->paquetes->first() : null;
            $fila[] = $paquete !== null ? ($paquete->wr ?? '') : '';
            $fila[] = $paquete !== null ? ($paquete->num_guia ?? '') : '';
            $fila[] = $paquete !== null ? ($paquete->num_seguimiento ?? '') : '';
        }

        return $fila;
    }
}
