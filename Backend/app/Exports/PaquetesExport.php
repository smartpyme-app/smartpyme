<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Inventario\Paquete;

class PaquetesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */

    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings():array{
       return[
            'fecha',
            'cliente',
            'codigo_asesor',
            'wr',
            'seguimiento',
            'guia',
            'piezas',
            'precio',
            'peso',
            'cuenta_a_terceros',
            'otros',
            'embalaje',
            'total',
            'Estado',
            'Nota',
        ];
    }

    public function map($row): array{
            $fields = [
              $row->fecha,
              $row->nombre_cliente,
              $row->asesor() ? $row->asesor()->pluck('codigo')->first() : '',
              $row->wr,
              $row->num_seguimiento,
              $row->num_guia,
              $row->piezas,
              number_format($row->precio, 2),
              number_format($row->peso, 2),
              number_format($row->cuenta_a_terceros, 2),
              number_format($row->otros, 2),
              $row->embalaje,
              number_format($row->total, 2),
              $row->estado,
              $row->nota,
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        $user = Auth::user();
        $sucursalFiltro = ($user->tipo === 'Administrador')
            ? ($request->filled('id_sucursal') ? $request->id_sucursal : null)
            : $user->id_sucursal;

        return Paquete::with('cliente', 'proveedor')
                                ->when($sucursalFiltro, function ($q) use ($sucursalFiltro) {
                                    return $q->where('id_sucursal', $sucursalFiltro);
                                })
                                ->when($request->buscador, function ($query) use ($request) {
                                    $term = $request->buscador;
                                    return $query->where(function ($q) use ($term) {
                                        $q->whereHas('cliente', function ($sub) use ($term) {
                                            $sub->where('nombre', 'like', '%' . $term . '%');
                                        })
                                            ->orWhere('num_guia', 'like', '%' . $term . '%')
                                            ->orWhere('embalaje', 'like', '%' . $term . '%')
                                            ->orWhere('nota', 'like', '%' . $term . '%')
                                            ->orWhere('wr', 'like', '%' . $term . '%')
                                            ->orWhere('num_seguimiento', 'like', '%' . $term . '%');
                                    });
                                })
                                ->when($request->wr, function($q) use ($request){
                                    $q->where('wr', $request->wr);
                                })
                                ->when($request->cuenta_a_terceros !== null, function($q) use ($request){
                                    $q->where('cuenta_a_terceros', '>', 0);
                                })
                                ->when($request->id_cliente, function($q) use ($request){
                                    return $q->where("id_cliente", $request->id_cliente);
                                })
                                ->when($request->id_asesor, function($q) use ($request){
                                    return $q->where("id_asesor", $request->id_asesor);
                                })
                                ->when($request->id_usuario, function($q) use ($request){
                                    return $q->where("id_usuario", $request->id_usuario);
                                })
                                ->when($request->inicio, function($query) use ($request){
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->when($request->estado, function($q) use ($request){
                                    $q->where('estado', $request->estado);
                                })
                                ->orderBy($request->orden ? $request->orden : 'nombre', $request->direccion ? $request->direccion : 'desc')
                    ->get();
    }
}
