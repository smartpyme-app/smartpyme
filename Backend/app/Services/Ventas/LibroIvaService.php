<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use Illuminate\Support\Collection;

class LibroIvaService
{
    /**
     * Generar libro IVA
     *
     * @param string $fechaInicio
     * @param string $fechaFin
     * @param string|null $tipoDocumento
     * @return Collection
     */
    public function generarLibroIva(string $fechaInicio, string $fechaFin, ?string $tipoDocumento = null): Collection
    {
        $ventas = Venta::with('cliente')
            ->where('estado', '!=', 'Pendiente')
            ->when($tipoDocumento, function ($query) use ($tipoDocumento) {
                return $query->whereHas('documento', function ($q) use ($tipoDocumento) {
                    $q->where('nombre', $tipoDocumento);
                });
            })
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('cotizacion', 0)
            ->orderBy('fecha', 'desc')
            ->get();

        $ivas = collect();

        foreach ($ventas as $venta) {
            $ivas->push([
                'fecha' => $venta->fecha,
                'clase_documento' => 1,
                'tipo_documento' => '03',
                'num_resolucion' => $venta->documento()->pluck('resolucion')->first(),
                'num_serie' => $venta->documento()->pluck('numero_autorizacion')->first(),
                'num_documento' => $venta->correlativo,
                'num_control_interno' => $venta->correlativo,
                'nit_nrc' => $venta->cliente()->pluck('nit')->first() ?: $venta->cliente()->pluck('ncr')->first(),
                'nombre_cliente' => $venta->nombre_cliente,
                'ventas_exentas' => $venta->exenta,
                'ventas_no_sujetas' => $venta->no_sujeta,
                'ventas_gravadas' => $venta->sub_total,
                'cuenta_a_terceros' => $venta->cuenta_a_terceros,
                'debito_fiscal' => $venta->iva,
                'ventas_cuenta_terceros' => 0,
                'debito_cuenta_terceros' => 0,
                'total' => $venta->total,
                'dui' => $venta->cliente()->pluck('dui')->first(),
                'num_anexto' => 1,
            ]);
        }

        return $ivas->sortByDesc('correlativo')->values();
    }
}


