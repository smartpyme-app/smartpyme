<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class VentasExport implements FromCollection, WithHeadings, WithMapping
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
            'Fecha',
            'Cliente',
            'Telefono',
            'DUI',
            'NIT',
            'Dirección',
            'Documento',
            'Proyecto',
            'Num Identificacion',
            'Correlativo',
            'Forma de pago',
            'Banco',
            'Estado',
            'Canal',
            'Costo',
            'Cuenta terceros',
            'Sub Total',
            'Descuento',
            'IVA',
            'Utilidad',
            'Total sin IVA',
            'Total',
            'Propina',
            'Empresa',
            'Observaciones',
            'Usuario',
            'Vendedor'
        ];
    }

    public function collection()
    {
        $request = $this->request;

        $ventas = Venta::when($request->inicio, function ($query) use ($request) {
            return $query->where('fecha', '>=', $request->inicio);
        })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
            })
            ->when($request->recurrente !== null, function ($q) use ($request) {
                $q->where('recurrente', !!$request->recurrente);
            })
            ->when($request->num_identificacion, function ($q) use ($request) {
                $q->where('num_identificacion', $request->num_identificacion);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->id_bodega, function ($query) use ($request) {
                return $query->where('id_bodega', $request->id_bodega);
            })
            ->when($request->id_cliente, function ($query) use ($request) {
                return $query->where('id_cliente', $request->id_cliente);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->forma_pago, function ($query) use ($request) {
                return $query->where('forma_pago', $request->forma_pago)
                    ->orwhereHas('metodos_de_pago', function ($query) use ($request) {
                        $query->where('nombre', $request->forma_pago);
                    });
            })
            ->when($request->id_vendedor, function ($query) use ($request) {
                return $query->where('id_vendedor', $request->id_vendedor)
                    ->orwhereHas('detalles', function ($query) use ($request) {
                        $query->where('id_vendedor', $request->id_vendedor);
                    });
            })
            ->when($request->id_canal, function ($query) use ($request) {
                return $query->where('id_canal', $request->id_canal);
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->where('id_proyecto', $request->id_proyecto);
            })
            ->when($request->id_documento, function ($query) use ($request) {
                $documento = \App\Models\Admin\Documento::find($request->id_documento);
                if ($documento) {
                    return $query->whereHas('documento', function ($q) use ($documento) {
                        $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                    });
                } else {
                    return $query->where('id_documento', $request->id_documento);
                }
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->metodo_pago, function ($query) use ($request) {
                return $query->where('metodo_pago', $request->metodo_pago);
            })
            ->when($request->tipo_documento, function ($query) use ($request) {
                return $query->whereHas('documento', function ($q) use ($request) {
                    $q->where('nombre', $request->tipo_documento);
                });
            })
            ->when($request->dte && $request->dte == 1, function ($query) {
                return $query->whereNull('sello_mh');
            })
            ->when($request->dte && $request->dte == 2, function ($query) {
                return $query->whereNotNull('sello_mh');
            })
            ->where('cotizacion', 0)
            ->when($request->buscador, function ($query) use ($request) {
                $buscador = '%' . $request->buscador . '%';
                return $query->where(function ($q) use ($buscador) {
                    $q->whereHas('cliente', function ($qCliente) use ($buscador) {
                        $qCliente->where('nombre', 'like', $buscador)
                            ->orWhere('nombre_empresa', 'like', $buscador)
                            ->orWhere('ncr', 'like', $buscador)
                            ->orWhere('nit', 'like', $buscador);
                    })
                        ->orWhere('correlativo', 'like', $buscador)
                        ->orWhere('estado', 'like', $buscador)
                        ->orWhere('observaciones', 'like', $buscador)
                        ->orWhere('forma_pago', 'like', $buscador);
                });
            })
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->get();

        return $ventas;
    }

    public function map($row): array
    {
        $fields = [
            $row->fecha,
            $row->nombre_cliente,
            $row->cliente()->pluck('telefono')->first(),
            $row->cliente()->pluck('dui')->first(),
            $row->cliente()->pluck('nit')->first(),
            $row->cliente()->pluck('direccion')->first(),
            $row->nombre_documento,
            $row->nombre_proyecto,
            $row->num_identificacion,
            $row->correlativo,
            $row->forma_pago,
            $row->detalle_banco,
            $row->estado,
            $row->nombre_canal,
            $row->estado == 'Anulada' ? '0.0' : round($row->total_costo, 2),
            round($row->cuenta_a_terceros, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->sub_total, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->descuento, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->iva, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->total - $row->total_costo - $row->iva, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->sub_total - $row->descuento, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->total, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->propina ?? 0, 2),
            $row->sucursal()->first()->empresa()->pluck('nombre')->first(),
            $row->observaciones,
            $row->nombre_usuario,
            $row->nombre_vendedor,

        ];
        return $fields;
    }
}
