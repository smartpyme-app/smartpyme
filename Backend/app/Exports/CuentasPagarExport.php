<?php

namespace App\Exports;

use App\Models\Compras\Compra;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class CuentasPagarExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Proveedor',
            'Documento',
            'Fecha compra',
            'Vencimiento',
            'Días vencimiento',
            'Estado',
            'Total',
            'Total abonado',
            'Último abono',
            'Saldo pendiente',
        ];
    }

    public function collection()
    {
        $request = $this->request;
        $orden = $request->orden ?? 'fecha';
        $direccion = $request->direccion ?? 'desc';

        return Compra::where('estado', 'Pendiente')
            ->when($request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
            })
            ->when($request->id_proveedor, function ($query) use ($request) {
                return $query->where('id_proveedor', $request->id_proveedor);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->buscador, function ($query) use ($request) {
                $buscador = '%' . $request->buscador . '%';
                return $query->where(function ($q) use ($buscador) {
                    $q->whereHas('proveedor', function ($qProveedor) use ($buscador) {
                        $qProveedor->where('nombre', 'like', $buscador)
                            ->orWhere('nombre_empresa', 'like', $buscador)
                            ->orWhere('ncr', 'like', $buscador)
                            ->orWhere('nit', 'like', $buscador);
                    })
                        ->orWhere('referencia', 'like', $buscador)
                        ->orWhere('estado', 'like', $buscador)
                        ->orWhere('observaciones', 'like', $buscador);
                });
            })
            ->where('cotizacion', 0)
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->withMax(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'fecha')
            ->orderBy($orden, $direccion)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function map($row): array
    {
        $fechaActual = Carbon::now();
        $fechaActual->setTime(0, 0, 0);

        $fechaVence = $row->fecha_pago
            ? Carbon::parse($row->fecha_pago)->setTime(0, 0, 0)
            : Carbon::parse($row->fecha)->addDays(30)->setTime(0, 0, 0);

        $dias = (int) $fechaActual->diffInDays($fechaVence, false);
        $estado = $dias >= 0 ? 'Vigente' : 'Vencido';

        $documento = ($row->tipo_documento ?? '') . ' #' . ($row->referencia ?? '');
        $abonado = round((float) ($row->abonos_sum_total ?? 0), 2);
        $devoluciones = round((float) ($row->devoluciones_sum_total ?? 0), 2);
        $saldo = round((float) $row->total - $abonado - $devoluciones, 2);

        $ultimoAbono = '';
        if (!empty($row->abonos_max_fecha)) {
            $ultimoAbono = Carbon::parse($row->abonos_max_fecha)->format('d/m/Y');
        }

        return [
            $row->nombre_proveedor ?? 'Consumidor Final',
            trim($documento),
            $row->fecha ? Carbon::parse($row->fecha)->format('d/m/Y') : '',
            $row->fecha_pago ? Carbon::parse($row->fecha_pago)->format('d/m/Y') : '',
            $dias,
            $estado,
            round((float) $row->total, 2),
            $abonado,
            $ultimoAbono,
            $saldo,
        ];
    }
}
