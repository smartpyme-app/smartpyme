<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VentasExport implements FromCollection, WithHeadings, WithMapping
{
    public $request;

    // Filtrar el request que viene del controlador
    public function filter(Request $request)
    {
        $this->request = $request;
    }

    // Definir los encabezados de las columnas del Excel
    public function headings(): array
    {
        return [
            'Fecha',
            'Cliente',
            'DUI',
            'NIT',
            'Dirección',
            'Documento',
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
            'Total',
            'Empresa',
            'Observaciones',
            'Usuario',
            'Vendedor'
        ];
    }

    // Recopilar los datos que se exportarán
    public function collection()
    {
        $request = $this->request;

        // Optimizar la consulta con relaciones cargadas
        $ventas = Venta::with(['cliente', 'sucursal.empresa'])
                        ->when($request->inicio, function ($query) use ($request) {
                            $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function ($query) use ($request) {
                            $query->where('fecha', '<=', $request->fin);
                        })
                        ->when(isset($request->recurrente), function ($query) use ($request) {
                            $query->where('recurrente', $request->recurrente);
                        })
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_bodega, function ($query) use ($request) {
                            $query->where('id_bodega', $request->id_bodega);
                        })
                        ->when($request->id_cliente, function ($query) use ($request) {
                            $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->id_usuario, function ($query) use ($request) {
                            $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->forma_pago, function ($query) use ($request) {
                            $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->id_vendedor, function ($query) use ($request) {
                            $query->where('id_vendedor', $request->id_vendedor);
                        })
                        ->when($request->id_canal, function ($query) use ($request) {
                            $query->where('id_canal', $request->id_canal);
                        })
                        ->when($request->estado, function ($query) use ($request) {
                            $query->where('estado', $request->estado);
                        })
                        ->where('cotizacion', 0)
                        ->when($request->buscador, function ($query) use ($request) {
                            $query->whereHas('cliente', function ($q) use ($request) {
                                $q->where('nombre', 'like', "%" . $request->buscador . "%")
                                    ->orWhere('nombre_empresa', 'like', "%" . $request->buscador . "%")
                                    ->orWhere('ncr', 'like', "%" . $request->buscador . "%")
                                    ->orWhere('nit', 'like', "%" . $request->buscador . "%");
                            })->orWhere('correlativo', 'like', '%' . $request->buscador . '%')
                              ->orWhere('estado', 'like', '%' . $request->buscador . '%')
                              ->orWhere('observaciones', 'like', '%' . $request->buscador . '%')
                              ->orWhere('forma_pago', 'like', '%' . $request->buscador . '%');
                        })
                        ->orderBy($request->orden ?? 'id', $request->direccion ?? 'desc')
                        ->get();

        return $ventas;
    }

    // Mapeo de los datos para cada fila del Excel
    public function map($row): array
    {
        $cliente = $row->cliente;
        $sucursal = $row->sucursal;
        $empresa = $sucursal ? $sucursal->empresa->first() : null;

        return [
            $row->fecha,
            $row->nombre_cliente,
            $cliente->dui ?? '',
            $cliente->nit ?? '',
            $cliente->direccion ?? '',
            $row->nombre_documento,
            $row->correlativo,
            $row->forma_pago,
            $row->detalle_banco,
            $row->estado,
            $row->nombre_canal,
            round($row->total_costo, 2),
            round($row->cuenta_a_terceros, 2),
            round($row->sub_total + $row->descuento, 2),
            round($row->descuento, 2),
            round($row->iva, 2),
            round($row->total - $row->total_costo - $row->iva, 2), // Utilidad
            round($row->total, 2),
            $empresa ? $empresa->nombre : '',
            $row->observaciones,
            $row->nombre_usuario,
            $row->nombre_vendedor,
        ];
    }
}
