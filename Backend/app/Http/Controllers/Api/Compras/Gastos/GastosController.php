<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin\Documento;
use App\Models\Compras\Gastos\Gasto;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;

use App\Exports\GastosExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Compras\Proveedores\Proveedor as ProveedorToGasto;
use Illuminate\Support\Facades\Log;

class GastosController extends Controller
{

    protected $transaccionesService;
    protected $chequesService;

    public function __construct(TransaccionesService $transaccionesService, ChequesService $chequesService)
    {
        $this->transaccionesService = $transaccionesService;
        $this->chequesService = $chequesService;
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

    public function store(Request $request)
    {

        $request->validate([
            'fecha'         => 'required|date',
            'concepto'      => 'sometimes|max:255',
            'tipo_documento'     => 'required|max:255',
            'forma_pago'     => 'required|max:255',
            'estado'     => 'required|max:255',
            'total'         => 'required|numeric',
            'id_categoria'    => 'required|numeric',
            'id_proveedor'    => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_sucursal'   => 'required|numeric',
            'id_empresa'   => 'required|numeric',
            'otros_impuestos' => 'nullable',
            'area_empresa'   => 'nullable',
            'id_area_empresa'   => 'nullable',
        ],[
            'id_categoria.required' => 'El campo categoria es obligatorio.',
            'id_proveedor.required' => 'El campo proveedor es obligatorio.',
            'id_usuario.required' => 'El campo usuario es obligatorio.',
            'id_empresa.required' => 'El campo empresa es obligatorio.'
        ]);

        if ($request->id)
            $gasto = Gasto::findOrFail($request->id);
        else
            $gasto = new Gasto;

        $data = $request->all();
        if (isset($data['otros_impuestos']) && empty($data['otros_impuestos'])) {
            $data['otros_impuestos'] = null;
        }
        $gasto->fill($data);
        $gasto->save();

        // Incrementar el correlarivo de Sujeto excluido
        if (!$request->id && $request->tipo_documento == 'Sujeto excluido') {
            $documento = Documento::where('nombre', $gasto->tipo_documento)->where('id_sucursal', $gasto->id_sucursal)->first();
            $documento->increment('correlativo');
        }

        // Crear transaccion bancaria
            if(!$request->id && $gasto->forma_pago != 'Efectivo' && $gasto->forma_pago != 'Cheque'){
                $this->transaccionesService->crear($gasto, 'Cargo', 'Gasto: ' . $gasto->tipo_documento . ' #' . ($gasto->referencia ? $gasto->referencia : ''), 'Gasto');
            }

        // Crear cheque
            if(!$request->id && $gasto->forma_pago == 'Cheque'){
                $this->chequesService->crear($gasto, $gasto->nombre_proveedor, 'Gasto: ' . $gasto->tipo_documento . ' #' . ($gasto->referencia ? $gasto->referencia : ''), 'Gasto');
            }

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
    public function importarJson(Request $request)
    {
        $request->validate([
            'json_data' => 'required|json',
        ]);

        try {
            $jsonData = json_decode($request->json_data, true);

            // Inicializar el gasto con datos predeterminados
            $gasto = new Gasto();
            $gasto->forma_pago = 'Efectivo';
            $gasto->estado = 'Confirmado';
            $gasto->tipo_documento = 'Factura';
            $gasto->tipo = 'Gastos varios';
            $gasto->fecha = date('Y-m-d');
            $gasto->id_empresa = auth()->user()->id_empresa;
            $gasto->id_sucursal = auth()->user()->id_sucursal;
            $gasto->id_usuario = auth()->user()->id;

            // Mapear datos de identificación
            if (isset($jsonData['identificacion'])) {
                if (isset($jsonData['identificacion']['fecEmi'])) {
                    $gasto->fecha = $jsonData['identificacion']['fecEmi'];
                }

                if (isset($jsonData['identificacion']['numeroControl'])) {
                    $gasto->referencia = substr($jsonData['identificacion']['numeroControl'], -10);
                }

                if (isset($jsonData['identificacion']['tipoDte'])) {
                    $tiposDte = [
                        '01' => 'Factura',
                        '03' => 'Crédito fiscal',
                        '05' => 'Nota de débito',
                        '06' => 'Nota de crédito',
                        '07' => 'Comprobante de retención',
                        '11' => 'Factura de exportación',
                        '14' => 'Sujeto excluido'
                    ];

                    $gasto->tipo_documento = $tiposDte[$jsonData['identificacion']['tipoDte']] ?? 'Factura';
                }

                $gasto->codigo_generacion = $jsonData['identificacion']['codigoGeneracion'] ?? null;
                $gasto->numero_control = $jsonData['identificacion']['numeroControl'] ?? null;
            }

            // Mapear datos del proveedor
            if (isset($jsonData['emisor'])) {
                $proveedor = $this->buscarOCrearProveedor($jsonData['emisor']);
                if ($proveedor) {
                    $gasto->id_proveedor = $proveedor->id;
                }
            }

            // Mapear conceptos e ítems
            if (isset($jsonData['cuerpoDocumento']) && !empty($jsonData['cuerpoDocumento'])) {
                // Usar la primera descripción como concepto principal
                $gasto->concepto = $jsonData['cuerpoDocumento'][0]['descripcion'];

                // Si hay más de un ítem, añadirlos como nota
                if (count($jsonData['cuerpoDocumento']) > 1) {
                    $itemsAdicionales = [];
                    for ($i = 1; $i < count($jsonData['cuerpoDocumento']); $i++) {
                        $item = $jsonData['cuerpoDocumento'][$i];
                        $itemsAdicionales[] = ($i + 1) . ". " . $item['descripcion'] .
                            " (" . $item['cantidad'] . " x $" . $item['precioUni'] . ")";
                    }

                    $gasto->nota = "Detalle adicional:\n" . implode("\n", $itemsAdicionales);
                }

                // Intentar determinar categoría basada en las descripciones
                $gasto->tipo = $this->determinarCategoria($jsonData['cuerpoDocumento']);
            }

            // Mapear totales financieros
            if (isset($jsonData['resumen'])) {
                $resumen = $jsonData['resumen'];

                // Montos base
                if (isset($resumen['subTotal'])) {
                    $gasto->sub_total = $resumen['subTotal'];
                } elseif (isset($resumen['totalGravada'])) {
                    $gasto->sub_total = $resumen['totalGravada'];
                }

                // IVA
                if (isset($resumen['tributos']) && !empty($resumen['tributos'])) {
                    foreach ($resumen['tributos'] as $tributo) {
                        if ($tributo['codigo'] === '20') { // Código para IVA
                            $gasto->iva = $tributo['valor'];
                            break;
                        }
                    }
                }

                // Retención de renta
                if (isset($resumen['reteRenta']) && $resumen['reteRenta'] > 0) {
                    $gasto->renta_retenida = $resumen['reteRenta'];
                }

                // Percepción
                if (isset($resumen['ivaPerci1']) && $resumen['ivaPerci1'] > 0) {
                    $gasto->iva_percibido = $resumen['ivaPerci1'];
                }

                // Total
                if (isset($resumen['totalPagar'])) {
                    $gasto->total = $resumen['totalPagar'];
                } elseif (isset($resumen['montoTotalOperacion'])) {
                    $gasto->total = $resumen['montoTotalOperacion'];
                }

                // Forma de pago
                if (isset($resumen['pagos']) && !empty($resumen['pagos'])) {
                    $formaPagoCodigos = [
                        '01' => 'Efectivo',
                        '02' => 'Tarjeta de Crédito',
                        '03' => 'Tarjeta de Débito',
                        '04' => 'Cheque',
                        '05' => 'Transferencia',
                        '06' => 'Crédito',
                        '07' => 'Tarjeta de regalo',
                        '08' => 'Dinero electrónico',
                        '99' => 'Otros'
                    ];

                    $pago = $resumen['pagos'][0];
                    $gasto->forma_pago = $formaPagoCodigos[$pago['codigo']] ?? 'Efectivo';

                    // Manejo de crédito
                    if ($pago['codigo'] === '06') {
                        $gasto->estado = 'Pendiente';

                        // Si hay plazo, calcular fecha de pago
                        if (isset($pago['plazo'])) {
                            $fechaPago = date('Y-m-d', strtotime($gasto->fecha . ' + ' . $pago['plazo'] . ' days'));
                            $gasto->fecha_pago = $fechaPago;
                        }
                    }
                }

                // Condición de operación
                if (isset($resumen['condicionOperacion'])) {
                    if ($resumen['condicionOperacion'] == 1) {
                        $gasto->condicion = 'Contado';
                        $gasto->estado = 'Confirmado';
                    } elseif ($resumen['condicionOperacion'] == 2) {
                        $gasto->condicion = 'Crédito';
                        $gasto->estado = 'Pendiente';
                    }
                }
            }

            // Guardar DTE completo para referencia
            $gasto->dte = $jsonData;

            // Retornar el gasto mapeado sin guardar
            return response()->json([
                'gasto' => $gasto,
                'mensaje' => 'DTE importado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al procesar el JSON: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Busca o crea un proveedor basado en los datos del emisor del DTE
     */
    private function buscarOCrearProveedor($emisorData)
    {
        if (!isset($emisorData['nit'])) {
            return null;
        }

        // Buscar por NIT
        $proveedor = \App\Models\Compras\Proveedores\Proveedor::where('nit', $emisorData['nit'])
            ->where('id_empresa', auth()->user()->id_empresa)
            ->first();

        if ($proveedor) {
            return $proveedor;
        }

        // Crear nuevo proveedor
        $proveedor = new \App\Models\Compras\Proveedores\Proveedor();
        $proveedor->tipo = 'Empresa';
        $proveedor->nombre_empresa = $emisorData['nombre'];
        $proveedor->nit = $emisorData['nit'];
        $proveedor->ncr = $emisorData['nrc'] ?? '';
        $proveedor->telefono = $emisorData['telefono'] ?? '';
        $proveedor->email = $emisorData['correo'] ?? '';

        // Manejar dirección
        if (isset($emisorData['direccion']) && isset($emisorData['direccion']['complemento'])) {
            $proveedor->direccion = $emisorData['direccion']['complemento'];
        } else {
            $proveedor->direccion = 'No especificada';
        }

        // Añadir ID de empresa y usuario actual
        $proveedor->id_empresa = auth()->user()->id_empresa;
        $proveedor->id_usuario = auth()->user()->id; // Añadir el ID del usuario actual

        $proveedor->save();

        return $proveedor;
    }

    /**
     * Determina la categoría del gasto basándose en las descripciones de los ítems
     */
    private function determinarCategoria($items)
    {
        // Palabras clave para cada categoría
        $categoriasKeywords = [
            'Alquiler' => ['alquiler', 'renta', 'arrendamiento', 'local'],
            'Combustible' => ['combustible', 'gasolina', 'diesel', 'gas'],
            'Costo de venta' => ['costo', 'venta', 'producto'],
            'Insumos' => ['insumos', 'suministros', 'papelería', 'oficina'],
            'Impuestos' => ['impuesto', 'iva', 'renta', 'fiscal', 'tributario'],
            'Gastos Administrativos' => ['administrativo', 'gestión', 'admin'],
            'Mantenimiento' => ['mantenimiento', 'reparación', 'arreglo'],
            'Marketing' => ['marketing', 'publicidad', 'promoción'],
            'Materia Prima' => ['materia prima', 'material', 'insumo'],
            'Servicios' => ['servicio', 'suscripción', 'internet', 'teléfono', 'electricidad', 'agua'],
            'Planilla' => ['planilla', 'salario', 'sueldo', 'nómina'],
            'Préstamos' => ['préstamo', 'crédito', 'financiamiento']
        ];

        // Concatenar todas las descripciones
        $descripcionCompleta = '';
        foreach ($items as $item) {
            if (isset($item['descripcion'])) {
                $descripcionCompleta .= ' ' . strtolower($item['descripcion']);
            }
        }

        // Buscar coincidencias con palabras clave
        foreach ($categoriasKeywords as $categoria => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($descripcionCompleta, strtolower($keyword)) !== false) {
                    return $categoria;
                }
            }
        }

        // Categoría predeterminada
        return 'Gastos varios';
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
