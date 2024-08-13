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

    public function headings():array{
        return[
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

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $ventas = Venta::when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->recurrente !== null, function($q) use ($request){
                            $q->where('recurrente', !!$request->recurrente);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago)
                                    ->orwhereHas('metodos_de_pago', function($query) use ($request){
                                        $query->where('nombre', $request->forma_pago);
                                    });
                        })
                        ->when($request->id_vendedor, function($query) use ($request){
                            return $query->where('id_vendedor', $request->id_vendedor)
                                    ->orwhereHas('detalles', function($query) use ($request){
                                        $query->where('id_vendedor', $request->id_vendedor);
                                    });
                        })
                        ->when($request->id_canal, function($query) use ($request){
                            return $query->where('id_canal', $request->id_canal);
                        })
                        ->when($request->id_documento, function($query) use ($request){
                            return $query->where('id_documento', $request->id_documento);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                        ->where('cotizacion', 0)
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                            ->get();

        return $ventas;
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->nombre_cliente,
              $row->cliente()->pluck('dui')->first(),
              $row->cliente()->pluck('nit')->first(),
              $row->cliente()->pluck('direccion')->first(),
              $row->nombre_documento,
              $row->correlativo,
              $row->forma_pago,
              $row->detalle_banco,
              $row->estado,
              $row->nombre_canal,
              round($row->total_costo, 2),
              round($row->sub_total, 2),
              round($row->descuento, 2),
              round($row->iva, 2),
              round($row->total - $row->total_costo - $row->iva, 2),
              round($row->total, 2),
              $row->sucursal()->first()->empresa()->pluck('nombre')->first(),
              $row->observaciones,
              $row->nombre_usuario,
              $row->nombre_vendedor,

         ];
        return $fields;
    }
}
