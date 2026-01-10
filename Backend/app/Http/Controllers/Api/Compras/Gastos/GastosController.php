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

        $gasto = Gasto::where('id', $id)->with('abonos')->first();
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
        $esNuevo = !$request->id;
        
        $gasto = $this->gastoService->crearOActualizarGasto($request->all());
        
        // Incrementar correlativo si es necesario
        $this->gastoService->incrementarCorrelativo($gasto, $esNuevo);
        
        // Procesar pagos (transacciones y cheques)
        $this->gastoService->procesarPagos($gasto, $esNuevo);

        return Response()->json($gasto, 200);
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
