<?php

namespace App\Exports;

use App\Exports\Support\DevolucionEnReporte;
use App\Helpers\CountryTermsHelper;
use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion;
use App\Support\ComprasListQuery;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class ComprasExport implements FromCollection, WithHeadings, WithMapping
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
        $empresa = Auth::check() ? Auth::user()->empresa : null;
        if (!$empresa && $this->request && $this->request->id_empresa) {
            $empresa = Empresa::find($this->request->id_empresa);
        }

        return[
            'Fecha',
            'Proveedor',
            'DUI',
            'NIT',
            'Documento',
            'Referencia',
            'Proyecto',
            'Num identificación',
            'Estado', 
            'Vencimiento', 
            'Costo',
            CountryTermsHelper::tax('taxLabel', $empresa),
            'Percepción', 
            'Descuento', 
            'Total',
            'Forma de pago',
            'Banco',
            'Tipo de cuenta',
            'Número de cuenta',
            'Titular',
        ];

    }

    public function collection()
    {
        $request = $this->request;
        $idEmpresa = Auth::check()
            ? Auth::user()->id_empresa
            : ($request->id_empresa ?? null);
        $compras = ComprasListQuery::apply(
            Compra::query()->when($idEmpresa, fn ($query) => $query->where('id_empresa', $idEmpresa)),
            $request,
        )
            ->when(!empty($request->sucursales) && is_array($request->sucursales), function ($query) use ($request) {
                return $query->whereIn('id_sucursal', $request->sucursales);
            })
            ->with(['proveedor', 'proyecto'])
            ->get();

        $devoluciones = $this->queryDevoluciones()->get()->each(function (Devolucion $devolucion) {
            DevolucionEnReporte::marcar($devolucion);
        });

        return $compras->concat($devoluciones)
            ->sortByDesc(function ($row) {
                return sprintf('%s-%010d', (string) $row->fecha, (int) ($row->id ?? 0));
            })
            ->values();
        
    }

    private function queryDevoluciones()
    {
        $request = $this->request;
        $idEmpresa = Auth::check() ? Auth::user()->id_empresa : ($request->id_empresa ?? null);

        return Devolucion::withoutGlobalScopes()
            ->with(['proveedor', 'compra.proyecto'])
            ->where('enable', 1)
            ->when($idEmpresa, function ($query) use ($idEmpresa) {
                return $query->where('id_empresa', $idEmpresa);
            })
            ->when($request->filled('inicio') && $request->filled('fin'), function ($query) use ($request) {
                return $query->whereDate('fecha', '>=', $request->inicio)
                    ->whereDate('fecha', '<=', $request->fin);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when(!empty($request->sucursales) && is_array($request->sucursales), function ($query) use ($request) {
                return $query->whereIn('id_sucursal', $request->sucursales);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->id_proveedor, function ($query) use ($request) {
                return $query->where('id_proveedor', $request->id_proveedor);
            })
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('referencia', 'like', '%'.$request->buscador.'%')
                        ->orWhere('observaciones', 'like', '%'.$request->buscador.'%')
                        ->orWhere('tipo_documento', 'like', '%'.$request->buscador.'%');
                });
            });
    }

    public function map($row): array{
        if (DevolucionEnReporte::esDevolucion($row)) {
            $proveedor = $row->proveedor;
            $compra = $row->compra;
            return [
                $row->fecha,
                $row->nombre_proveedor,
                $proveedor ? $proveedor->dui : '',
                $proveedor ? $proveedor->nit : '',
                $row->tipo_documento ?: 'Devolución',
                $row->referencia,
                ($compra && $compra->proyecto) ? $compra->proyecto->nombre : '',
                $compra ? $compra->num_identificacion : '',
                DevolucionEnReporte::ESTADO,
                $compra ? $compra->fecha_pago : '',
                DevolucionEnReporte::negar($row->sub_total),
                DevolucionEnReporte::negar($row->iva),
                DevolucionEnReporte::negar($row->iva_percibido ?? 0),
                DevolucionEnReporte::negar($row->descuento),
                DevolucionEnReporte::negar($row->total),
            ];
        }

           $proveedor = $row->relationLoaded('proveedor') ? $row->proveedor : null;
           $proyecto = $row->relationLoaded('proyecto') ? $row->proyecto : null;

           $fields = [
              $row->fecha,
              $row->nombre_proveedor,
              $proveedor?->dui,
              $proveedor?->nit,
              $row->tipo_documento,
              $row->referencia,
              $proyecto?->nombre,
              $row->num_identificacion,
              $row->estado,
              $row->fecha_pago,
              $row->sub_total,
              $row->iva,
              $row->percepcion,
              $row->descuento,
              $row->total,
              $row->forma_pago,
              $proveedor?->banco,
              $proveedor?->tipo_cuenta,
              $proveedor?->numero_cuenta,
              $proveedor?->titular_cuenta,
         ];
        return $fields;
    }
}
