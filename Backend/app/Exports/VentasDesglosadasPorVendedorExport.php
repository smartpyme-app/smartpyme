<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export de ventas totales por factura.
 * El vendedor mostrado es el de la venta (cabecera), no el del detalle de línea.
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
        $ventas = $this->baseExport->query()
            ->with(['vendedor'])
            ->get();

        return $ventas->map(function (Venta $venta) {
            $venta->loadMissing(['vendedor']);

            $subTotal = (float) ($venta->sub_total ?? 0);
            $descuento = (float) ($venta->descuento ?? 0);
            $iva = (float) ($venta->iva ?? 0);
            $total = (float) ($venta->total ?? 0);
            $totalCosto = (float) ($venta->total_costo ?? 0);

            return (object) [
                'venta' => $venta,
                'grupo' => [
                    'vendedor_id' => (int) ($venta->id_vendedor ?? 0),
                    'vendedor_nombre' => $venta->vendedor?->name ?? 'Sin vendedor',
                    'total_costo' => $totalCosto,
                    'sub_total' => $subTotal,
                    'descuento' => $descuento,
                    'iva' => $iva,
                    'total_sin_iva' => max(0, $subTotal - $descuento),
                    'total' => $total,
                    'utilidad' => $total - $totalCosto - $iva,
                    'share' => 1.0,
                ],
            ];
        });
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

        $share = (float) ($grupo['share'] ?? 1);
        $cuentaTerceros = ($venta->cuenta_a_terceros ?? 0) * $share;
        $propina = ($venta->propina ?? 0) * $share;
        $anulada = $venta->estado === 'Anulada';

        $fila = [
            $venta->fecha,
            $nombreCliente,
            $cliente ? $cliente->telefono : '',
            $cliente ? $cliente->dui : '',
            $cliente ? $cliente->nit : '',
            $cliente ? $cliente->direccion : '',
            ($documento && isset($documento->nombre)) ? $documento->nombre : '',
            ($proyecto && isset($proyecto->nombre)) ? $proyecto->nombre : '',
            $venta->num_identificacion,
            $venta->correlativo,
            $venta->forma_pago,
            $venta->detalle_banco,
            $venta->estado,
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
