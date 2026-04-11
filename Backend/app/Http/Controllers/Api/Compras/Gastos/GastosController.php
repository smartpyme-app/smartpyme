<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin\Documento;
use App\Models\Compras\Gastos\Gasto;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use App\Services\Compras\Gastos\GastoService;
use App\Services\Compras\Gastos\GastoImportService;
use App\Models\Compras\Gastos\DetalleEgreso;
use Illuminate\Support\Facades\DB;

use App\Exports\GastosExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Compras\Proveedores\Proveedor as ProveedorToGasto;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Compras\Gastos\StoreGastoRequest;
use App\Http\Requests\Compras\Gastos\ImportarJsonGastoRequest;

class GastosController extends Controller
{

    protected $gastoService;
    protected $gastoImportService;

    public function __construct(GastoService $gastoService, GastoImportService $gastoImportService)
    {
        $this->gastoService = $gastoService;
        $this->gastoImportService = $gastoImportService;
    }


    public function index(Request $request)
    {

        $gastos = Gasto::with('retaceoGasto')->when($request->id_proveedor, function ($query) use ($request) {
            return $query->where('id_proveedor', $request->id_proveedor);
        })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->recurrente !== null, function ($q) use ($request) {
                $q->where('recurrente', !!$request->recurrente);
            })
            ->when($request->id_area_empresa, function($query) use ($request){
                return $query->where('id_area_empresa', $request->id_area_empresa);
            })
            ->when($request->num_identificacion, function ($q) use ($request) {
                $q->where('num_identificacion', $request->num_identificacion);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->where('id_proyecto', $request->id_proyecto);
            })
            ->when($request->tipo, function ($query) use ($request) {
                return $query->where('tipo', $request->tipo);
            })
            ->when($request->dte && $request->dte == 0, function ($query) {
                return $query->whereNull('sello_mh');
            })
            ->when($request->dte && $request->dte == 1, function ($query) {
                return $query->whereNotNull('sello_mh');
            })
            ->when($request->es_retaceo, function($query) use ($request) {
                return $query->where('es_retaceo', true)
                    ->when($request->es_retaceo === 'true',
                        function($q) { return $q->whereDoesntHave('retaceoGasto'); },
                        function($q) { return $q->whereHas('retaceoGasto'); }
                    );
            })
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->whereHas('proveedor', function ($q2) use ($request) {
                        $q2->where('nombre', 'like', "%" . $request->buscador . "%")
                            ->orWhere('nombre_empresa', 'like', "%" . $request->buscador . "%")
                            ->orWhere('ncr', 'like', "%" . $request->buscador . "%")
                            ->orWhere('nit', 'like', "%" . $request->buscador . "%");
                    })
                    ->orWhereRaw("CONCAT(tipo_documento, ' #', referencia) like ?", ['%' . $request->buscador . '%'])
                    ->orWhere('referencia', 'like', '%' . $request->buscador . '%')
                    ->orWhere('estado', 'like', '%' . $request->buscador . '%')
                    ->orWhere('concepto', 'like', '%' . $request->buscador . '%')
                    ->orWhere('forma_pago', 'like', '%' . $request->buscador . '%')
                    ->orWhere('num_identificacion', 'like', '%' . $request->buscador . '%')
                    ->orWhereHas('proyecto', function ($q3) use ($request) {
                        $q3->where('nombre', 'like', '%' . $request->buscador . '%');
                    });
                });
            })
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->paginate($request->paginate);

        return Response()->json($gastos, 200);
    }


    public function read($id)
    {
        $gasto = Gasto::where('id', $id)->with(['abonos', 'detalles', 'categoria'])->first();
        if (!$gasto) {
            return response()->json(['error' => 'Gasto no encontrado'], 404);
        }
        $gasto->tiene_detalles = $gasto->tiene_detalles;
        $gasto->saldo = $gasto->saldo;
        return Response()->json($gasto, 200);
    }

    public function filter(Request $request)
    {


        $gastos = Gasto::when($request->inicio, function ($query) use ($request) {
            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
        })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->id_proveedor, function ($query) use ($request) {
                return $query->where('id_proveedor', $request->id_proveedor);
            })
            ->when($request->concepto, function ($query) use ($request) {
                return $query->where('concepto', $request->concepto);
            })
            ->when($request->usuario_id, function ($query) use ($request) {
                return $query->where('usuario_id', $request->usuario_id);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->categoria, function ($query) use ($request) {
                return $query->where('categoria', $request->categoria);
            })
            ->orderBy('id', 'desc')->paginate(100000);

        return Response()->json($gastos, 200);
    }

    public function store(StoreGastoRequest $request)
    {
        if ($request->input('id_categoria') === '' || $request->input('id_categoria') === null) {
            $request->merge(['id_categoria' => null]);
        } else {
            $request->merge(['id_categoria' => (int) $request->input('id_categoria')]);
        }
        if ($request->input('id_area_empresa') === '' || $request->input('id_area_empresa') === null) {
            $request->merge(['id_area_empresa' => null]);
        } else {
            $request->merge(['id_area_empresa' => (int) $request->input('id_area_empresa')]);
        }

        $tieneMultiplesItems = $request->has('varios_items') && $request->varios_items
            && $request->has('detalles') && is_array($request->detalles) && count($request->detalles) > 0;

        if ($tieneMultiplesItems) {
            $request->validate([
                'fecha' => 'required|date',
                'tipo_documento' => 'required|max:255',
                'forma_pago' => 'required|max:255',
                'estado' => 'required|max:255',
                'id_proveedor' => 'required|numeric',
                'id_usuario' => 'required|numeric',
                'id_sucursal' => 'required|numeric',
                'id_empresa' => 'required|numeric',
                'detalles' => 'required|array|min:1',
                'detalles.*.concepto' => 'required|string|max:255',
                'detalles.*.tipo' => 'required|string|max:255',
                'detalles.*.sub_total' => 'required|numeric|min:0',
                'detalles.*.iva' => 'nullable|numeric|min:0',
                'detalles.*.total' => 'required|numeric|min:0',
            ], [
                'id_proveedor.required' => 'El campo proveedor es obligatorio.',
                'id_usuario.required' => 'El campo usuario es obligatorio.',
                'id_empresa.required' => 'El campo empresa es obligatorio.',
                'detalles.required' => 'Debe agregar al menos un ítem al detalle.',
            ]);
        } else {
            $request->validate([
                'fecha' => 'required|date',
                'concepto' => 'required|max:255',
                'tipo_documento' => 'required|max:255',
                'tipo' => 'required|max:255',
                'forma_pago' => 'required|max:255',
                'estado' => 'required|max:255',
                'total' => 'required|numeric',
                'id_proveedor' => 'required|numeric',
                'id_usuario' => 'required|numeric',
                'id_sucursal' => 'required|numeric',
                'id_empresa' => 'required|numeric',
                'otros_impuestos' => 'nullable',
                'area_empresa' => 'nullable',
                'id_categoria' => 'nullable|integer|exists:gastos_categorias,id',
                'id_area_empresa' => 'nullable|integer|exists:areas_empresa,id',
            ], [
                'tipo.required' => 'El campo tipo de gasto es obligatorio.',
                'id_proveedor.required' => 'El campo proveedor es obligatorio.',
                'id_usuario.required' => 'El campo usuario es obligatorio.',
                'id_empresa.required' => 'El campo empresa es obligatorio.'
            ]);
        }

        return DB::transaction(function () use ($request, $tieneMultiplesItems) {
            if ($request->id) {
                $gasto = Gasto::findOrFail($request->id);
            } else {
                $gasto = new Gasto();
            }

            $headerData = $request->except(['detalles', 'varios_items', 'concepto', 'sub_total', 'iva', 'renta_retenida', 'iva_percibido', 'total', 'tipo', 'impuesto', 'renta', 'percepcion']);
            $gasto->fill($headerData);

            if ($tieneMultiplesItems) {
                $this->guardarConDetalles($gasto, $request->detalles, $request->input('tipo'));
            } else {
                $gasto->sub_total = $request->sub_total ?? 0;
                $gasto->iva = $request->iva ?? 0;
                $gasto->renta_retenida = $request->renta_retenida ?? 0;
                $gasto->iva_percibido = $request->iva_percibido ?? 0;
                $gasto->total = $request->total ?? 0;
                $gasto->concepto = $request->concepto;
                $gasto->tipo = $request->input('tipo') ?? '';
                $gasto->save();
                $this->sincronizarDetalleUnico($gasto, $request);
            }

            if (!$request->id && $request->tipo_documento == 'Sujeto excluido') {
                $documento = Documento::where('nombre', $gasto->tipo_documento)->where('id_sucursal', $gasto->id_sucursal)->first();
                if ($documento) {
                    $documento->increment('correlativo');
                }
            }

            return response()->json($gasto->load(['detalles', 'categoria']), 200);
        });
    }

    private function guardarConDetalles(Gasto $gasto, array $detalles, ?string $tipoCabecera = null): void
    {
        $gasto->concepto = collect($detalles)->pluck('concepto')->take(1)->implode(', ');
        $tipoCabecera = is_string($tipoCabecera) ? trim($tipoCabecera) : '';
        $gasto->tipo = $tipoCabecera !== ''
            ? $tipoCabecera
            : ($detalles[0]['tipo'] ?? 'Gastos varios');

        $subTotal = 0;
        $iva = 0;
        $rentaRetenida = 0;
        $ivaPercibido = 0;
        $total = 0;

        foreach ($detalles as $d) {
            $sub = (float) ($d['sub_total'] ?? 0);
            $iv = (float) ($d['iva'] ?? 0);
            $renta = (float) ($d['renta_retenida'] ?? 0);
            $perc = (float) ($d['iva_percibido'] ?? 0);
            $tot = (float) ($d['total'] ?? $sub + $iv - $renta + $perc);
            $subTotal += $sub;
            $iva += $iv;
            $rentaRetenida += $renta;
            $ivaPercibido += $perc;
            $total += $tot;
        }

        $gasto->sub_total = round($subTotal, 2);
        $gasto->iva = round($iva, 2);
        $gasto->renta_retenida = round($rentaRetenida, 2);
        $gasto->iva_percibido = round($ivaPercibido, 2);
        $gasto->total = round($total, 2);
        $gasto->save();

        $gasto->detalles()->delete();

        foreach ($detalles as $idx => $d) {
            $sub = (float) ($d['sub_total'] ?? 0);
            $iv = (float) ($d['iva'] ?? 0);
            $renta = (float) ($d['renta_retenida'] ?? 0);
            $perc = (float) ($d['iva_percibido'] ?? 0);
            $tot = (float) ($d['total'] ?? $sub + $iv - $renta + $perc);

            $idCategoriaLinea = $d['id_categoria'] ?? null;
            if ($idCategoriaLinea === '' || $idCategoriaLinea === false) {
                $idCategoriaLinea = null;
            } else {
                $idCategoriaLinea = $idCategoriaLinea !== null ? (int) $idCategoriaLinea : null;
            }

            DetalleEgreso::create([
                'id_egreso' => $gasto->id,
                'numero_item' => $idx + 1,
                'concepto' => $d['concepto'] ?? '',
                'tipo' => $d['tipo'] ?? 'Gastos varios',
                'tipo_gravado' => in_array($d['tipo_gravado'] ?? '', ['gravada', 'exenta', 'no_sujeta']) ? $d['tipo_gravado'] : 'gravada',
                'id_categoria' => $idCategoriaLinea,
                'cantidad' => $d['cantidad'] ?? 1,
                'precio_unitario' => $d['precio_unitario'] ?? $sub,
                'sub_total' => $sub,
                'iva' => $iv,
                'renta_retenida' => $renta,
                'iva_percibido' => $perc,
                'total' => $tot,
                'aplica_iva' => ($d['tipo_gravado'] ?? '') === 'gravada',
                'aplica_renta' => !empty($d['aplica_renta']),
                'aplica_percepcion' => !empty($d['aplica_percepcion']),
                'area_empresa' => $d['area_empresa'] ?? null,
                'id_proyecto' => $d['id_proyecto'] ?? null,
            ]);
        }
    }

    private function sincronizarDetalleUnico(Gasto $gasto, Request $request): void
    {
        $gasto->detalles()->delete();
        $top = $request->tipo_operacion ?? '';
        if (!empty($request->impuesto)) {
            $tipoGravado = 'gravada';
        } elseif ($top === 'Excluido') {
            $tipoGravado = 'exenta';
        } elseif ($top === 'No Gravada' || $top === 'Mixta') {
            $tipoGravado = 'no_sujeta';
        } else {
            $tipoGravado = 'no_sujeta';
        }
        $idCatUnico = $request->input('id_categoria');
        if ($idCatUnico === '' || $idCatUnico === null) {
            $idCatUnico = null;
        } else {
            $idCatUnico = (int) $idCatUnico;
        }

        DetalleEgreso::create([
            'id_egreso' => $gasto->id,
            'numero_item' => 1,
            'concepto' => $request->concepto ?? '',
            'tipo' => $request->tipo ?? 'Gastos varios',
            'tipo_gravado' => $tipoGravado,
            'id_categoria' => $idCatUnico,
            'cantidad' => 1,
            'precio_unitario' => $request->sub_total ?? $request->total ?? 0,
            'sub_total' => $gasto->sub_total ?? 0,
            'iva' => $gasto->iva ?? 0,
            'renta_retenida' => $gasto->renta_retenida ?? 0,
            'iva_percibido' => $gasto->iva_percibido ?? 0,
            'total' => $gasto->total ?? 0,
            'aplica_iva' => !empty($request->impuesto),
            'aplica_renta' => !empty($request->renta),
            'aplica_percepcion' => !empty($request->percepcion),
            'area_empresa' => $request->area_empresa ?? null,
            'id_proyecto' => $request->id_proyecto ?? null,
        ]);
    }

    public function delete($id)
    {

        $gasto = Gasto::findOrFail($id);
        $gasto->delete();

        return Response()->json($gasto, 201);
    }

    public function dash(Request $request)
    {

        $datos = new \stdClass();

        $datos->categorias   = Gasto::selectRaw('sum(total) AS total, categoria')
            ->groupBy('categoria')
            // ->when('sucursal', function($q) use($request){
            //     $q->where('id_sucursal', $request->id_sucursal);
            // })
            // ->when('sucursal', function($q) use($request){
            //     $q->where('id_sucursal', $request->id_sucursal);
            // })
            // ->orderBy('total', 'desc')
            ->take(5)
            ->get();

        $datos->meses   = Gasto::selectRaw('sum(total) AS total, MONTH(fecha) as mes, MONTHNAME(fecha) as nombre_mes')
            ->groupBy('mes', 'nombre_mes')
            // ->when('sucursal', function($q) use($request){
            //     $q->where('id_sucursal', $request->id_sucursal);
            // })
            // ->when('sucursal', function($q) use($request){
            //     $q->where('id_sucursal', $request->id_sucursal);
            // })
            ->orderBy('mes', 'desc')
            ->take(5)
            ->get();


        return Response()->json($datos, 200);
    }

    public function export(Request $request)
    {
        $gastos = new GastosExport();
        $gastos->filter($request);

        return Excel::download($gastos, 'gastos.xlsx');
    }

    /**
     * Importar gasto desde JSON DTE
     *
     * @param Request $request
     * @return Response
     */
    public function importarJson(ImportarJsonGastoRequest $request)
    {
        try {
            $jsonData = json_decode($request->json_data, true);

            $gasto = $this->gastoImportService->importarDesdeJson($jsonData);

            // Retornar el gasto mapeado sin guardar
            return response()->json([
                'gasto' => $gasto,
                'mensaje' => 'DTE importado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error importando gasto desde JSON", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al procesar el JSON: ' . $e->getMessage()
            ], 422);
        }
    }

    public function getNumerosIdentificacion(){
        $numsIds = Gasto::select('num_identificacion')
            ->distinct()
            ->where('id_empresa', auth()->user()->id_empresa)
            ->whereNotNull('num_identificacion')
            ->where('num_identificacion', '!=', '')
            ->get();

        return Response()->json($numsIds, 200);
     }
}
